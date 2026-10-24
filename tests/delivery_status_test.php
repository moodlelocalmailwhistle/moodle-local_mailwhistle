<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_mailwhistle;

use local_mailwhistle\manager\delivery_status_manager;
use local_mailwhistle\manager\send_manager;
use local_mailwhistle\output\delivery_status;

/**
 * Tests for read-only sending diagnostics.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\delivery_status_manager
 * @covers    \local_mailwhistle\output\delivery_status
 */
final class delivery_status_test extends \advanced_testcase {
    /**
     * Queue activity distinguishes waiting, delayed, retrying and missing workers.
     */
    public function test_campaign_activity_and_queue_states(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $now = time();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign(['status' => 'sending', 'timemodified' => $now - 60]);
        $recipient = $generator->create_recipient([
            'campaignid' => $campaign->id, 'userid' => 1, 'status' => 'pending', 'timemodified' => $now - 60,
        ]);
        send_manager::queue_campaign($campaign->id);
        $status = delivery_status_manager::get_campaign_status($campaign, $now);
        $this->assertSame('queued', $status['state']);
        $this->assertSame(60, $status['age']);
        $this->assertSame(0, $status['lastactivity']);
        $this->assertSame('delayed', delivery_status_manager::get_campaign_status($campaign, $now + 601)['state']);

        $task = $DB->get_record('task_adhoc', ['classname' => '\\local_mailwhistle\\task\\send_campaign'], '*', MUST_EXIST);
        $DB->set_field('task_adhoc', 'timestarted', $now - 10, ['id' => $task->id]);
        $this->assertSame('started', delivery_status_manager::get_campaign_status($campaign, $now)['state']);
        $DB->update_record('local_mailwhistle_recipients', (object) [
            'id' => $recipient->id, 'status' => 'sent', 'timemodified' => $now - 5,
        ]);
        $this->assertSame($now - 5, delivery_status_manager::get_campaign_status($campaign, $now)['lastactivity']);

        $DB->update_record('task_adhoc', (object) [
            'id' => $task->id, 'timestarted' => null, 'faildelay' => 120, 'nextruntime' => $now + 120,
        ]);
        $status = delivery_status_manager::get_campaign_status($campaign, $now);
        $this->assertSame('retry', $status['state']);
        $this->assertSame($now + 120, $status['retryat']);
        $DB->delete_records('task_adhoc', ['id' => $task->id]);
        $other = $generator->create_campaign(['status' => 'sending']);
        send_manager::queue_campaign($other->id);
        $this->assertSame('missing', delivery_status_manager::get_campaign_status($campaign, $now)['state']);
        $this->assertSame(1, $DB->count_records('task_adhoc'));
        $this->assertSame('sending', $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $campaign->id]));
        $campaign->status = 'sent';
        $this->assertSame([], delivery_status_manager::get_campaign_status($campaign, $now));
    }

    /**
     * Task diagnostics respect disabled, overdue and retry states and missing records.
     */
    public function test_scheduled_task_health_uses_recorded_times(): void {
        global $DB;

        $this->resetAfterTest();
        $now = time();
        $classname = '\\local_mailwhistle\\task\\dispatch_scheduled_campaigns';
        $task = $DB->get_record('task_scheduled', ['classname' => $classname], '*', MUST_EXIST);
        $task->disabled = 0;
        $task->faildelay = 0;
        $task->timestarted = null;
        $task->lastruntime = $now - 60;
        $task->nextruntime = $now + 60;
        $DB->update_record('task_scheduled', $task);
        $row = delivery_status_manager::get_task_statuses($now)[0];
        $this->assertSame('scheduled', $row['state']);
        $this->assertSame($now - 60, $row['lastrun']);
        $this->assertFalse($row['warning']);
        $DB->set_field('task_scheduled', 'nextruntime', $now - 601, ['id' => $task->id]);
        $this->assertSame('delayed', delivery_status_manager::get_task_statuses($now)[0]['state']);
        $DB->set_field('task_scheduled', 'faildelay', 60, ['id' => $task->id]);
        $this->assertSame('retry', delivery_status_manager::get_task_statuses($now)[0]['state']);
        $DB->set_field('task_scheduled', 'disabled', 1, ['id' => $task->id]);
        $this->assertSame('disabled', delivery_status_manager::get_task_statuses($now)[0]['state']);
        $DB->delete_records('task_scheduled', ['id' => $task->id]);
        $this->assertSame('missing', delivery_status_manager::get_task_statuses($now)[0]['state']);
    }

    /**
     * Managers get useful guidance; only site administrators get administration links.
     */
    public function test_output_does_not_offer_admin_links_to_managers(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/local/mailwhistle/index.php');
        $renderer = $PAGE->get_renderer('local_mailwhistle');
        $data = (new delivery_status())->export_for_template($renderer);
        $this->assertArrayHasKey('taskurl', $data);
        $this->assertArrayHasKey('logurl', $data);
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id, \context_system::instance()->id);
        $this->setUser($manager);
        $data = (new delivery_status())->export_for_template($renderer);
        $this->assertArrayNotHasKey('taskurl', $data);
        $this->assertArrayNotHasKey('logurl', $data);
        $html = $renderer->render(new delivery_status());
        $this->assertStringContainsString(get_string('delivery_contactadmin', 'local_mailwhistle'), $html);
    }
}
