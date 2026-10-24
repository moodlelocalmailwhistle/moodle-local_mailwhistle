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

use core\task\manager as task_manager;
use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\manager\recipient_manager;
use local_mailwhistle\task\send_campaign;

/**
 * Tests for the campaign task's batch continuation.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\task\send_campaign
 */
final class send_campaign_task_test extends \advanced_testcase {
    /**
     * Each running batch queues a successor that survives the current task's completion.
     */
    public function test_running_task_continues_until_all_recipients_are_sent(): void {
        global $DB, $CFG;

        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $CFG->noemailever = false;
        $DB->set_field('message_processors', 'enabled', 1, ['name' => 'email']);
        set_config('sendbatchsize', 1, 'local_mailwhistle');

        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign([
            'subject' => 'Hello {{firstname}}',
            'bodyhtml' => '<p>Your newsletter</p>',
            'status' => campaign_manager::STATUS_SENDING,
        ]);
        $emails = [];
        for ($i = 0; $i < 3; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $emails[] = $user->email;
            $generator->create_recipient([
                'campaignid' => $campaign->id,
                'userid' => $user->id,
                'email' => $user->email,
                'firstname' => $user->firstname,
                'status' => recipient_manager::STATUS_PENDING,
                'timesent' => 0,
            ]);
        }

        $task = new send_campaign();
        $task->set_custom_data(['campaignid' => (int) $campaign->id]);
        task_manager::queue_adhoc_task($task, true);
        $sink = $this->redirectEmails();

        for ($batch = 1; $batch <= 3; $batch++) {
            $task = task_manager::get_next_adhoc_task(time(), false, send_campaign::class);
            $this->assertInstanceOf(send_campaign::class, $task);
            try {
                // Moodle keeps the running task in its queue until execute() returns.
                $this->assertTrue($DB->record_exists('task_adhoc', ['id' => $task->get_id()]));
                $task->execute();
            } finally {
                task_manager::adhoc_task_complete($task);
            }

            $this->assertSame(3 - $batch, recipient_manager::count_pending($campaign->id));
            $this->assertCount($batch, $sink->get_messages());
            $this->assertSame($batch < 3 ? 1 : 0, $DB->count_records('task_adhoc', [
                'classname' => '\\' . send_campaign::class,
            ]), 'A pending batch must remain queued after the previous task is removed.');
        }

        $messages = $sink->get_messages();
        $sink->close();
        $this->assertEqualsCanonicalizing($emails, array_column($messages, 'to'));
        $this->assertSame(campaign_manager::STATUS_SENT, $DB->get_field(
            'local_mailwhistle_campaigns',
            'status',
            ['id' => $campaign->id]
        ));
    }
}
