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

use local_mailwhistle\manager\send_manager;

/**
 * Upgrade safety for delivery records created before email-specific outcomes.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::xmldb_local_mailwhistle_upgrade
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Load the actual upgrade callback and configure an old installed version.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        global $CFG;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');
        set_config('version', 2026091101, 'local_mailwhistle');
    }

    /**
     * Create a recipient with a unique user and a historical timestamp.
     *
     * @param int $campaignid Campaign id.
     * @param string $status Legacy recipient state.
     * @param string|null $error Previous processor error.
     * @return \stdClass Stored snapshot.
     */
    private function recipient(int $campaignid, string $status, ?string $error = null): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        return $generator->create_recipient([
            'campaignid' => $campaignid,
            'userid' => $user->id,
            'email' => $user->email,
            'status' => $status,
            'attempts' => $status === 'pending' ? 0 : 2,
            'error' => $error,
            'timesent' => $status === 'sent' ? 1700000000 : 0,
            'timemodified' => 1700000010,
        ]);
    }

    /**
     * Historical failures and active pending sends cannot become retryable after upgrade.
     */
    public function test_legacy_ambiguous_deliveries_are_quarantined_without_losing_history(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $mixed = $generator->create_campaign(['status' => 'sent', 'timesent' => 1700000100, 'timemodified' => 1700000101]);
        $sent = $this->recipient($mixed->id, 'sent');
        $failed = $this->recipient($mixed->id, 'failed', 'Popup failed after email: <original details>');
        helper::log_send($mixed->id, $failed->id, 'error', $failed->error);
        $history = $DB->get_records('local_mailwhistle_sendlogs');

        $allfailed = $generator->create_campaign(['status' => 'sent', 'timesent' => 1700000200]);
        $nullerror = $this->recipient($allfailed->id, 'failed');
        $active = $generator->create_campaign(['status' => 'sending', 'timesent' => 0]);
        $this->recipient($active->id, 'sent');
        $pending = $this->recipient($active->id, 'pending');
        $emptyactive = $generator->create_campaign(['status' => 'sending']);
        $complete = $generator->create_campaign(['status' => 'sent', 'timesent' => 1700000300]);
        $this->recipient($complete->id, 'sent');
        $ready = $generator->create_campaign(['status' => 'ready']);
        $readypending = $this->recipient($ready->id, 'pending');
        $draft = $generator->create_campaign(['status' => 'draft']);

        $this->assertTrue(\xmldb_local_mailwhistle_upgrade(2026091101));
        $this->assertSame(2026102301, (int) get_config('local_mailwhistle', 'version'));
        $reason = get_string('delivery_legacyuncertain', 'local_mailwhistle');
        foreach ([$failed, $nullerror, $pending] as $original) {
            $updated = $DB->get_record('local_mailwhistle_recipients', ['id' => $original->id], '*', MUST_EXIST);
            $this->assertSame('uncertain', $updated->status);
            $this->assertSame($reason . ($original->error !== null ? "\n" . $original->error : ''), $updated->error);
            foreach (['email', 'firstname', 'lastname', 'attempts', 'timesent', 'timemodified'] as $field) {
                $this->assertEquals($original->$field, $updated->$field, 'Preserve historical ' . $field);
            }
        }
        foreach ([$sent, $readypending] as $unchanged) {
            $this->assertEquals($unchanged, $DB->get_record('local_mailwhistle_recipients', ['id' => $unchanged->id]));
        }
        $expected = [
            $mixed->id => 'completedwitherrors',
            $allfailed->id => 'failed',
            $active->id => 'completedwitherrors',
            $emptyactive->id => 'failed',
            $complete->id => 'sent',
            $ready->id => 'ready',
            $draft->id => 'draft',
        ];
        foreach ($expected as $id => $status) {
            $this->assertSame($status, $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $id]));
        }
        $this->assertSame(1700000100, (int) $DB->get_field('local_mailwhistle_campaigns', 'timesent', ['id' => $mixed->id]));
        $this->assertSame(1700000101, (int) $DB->get_field('local_mailwhistle_campaigns', 'timemodified', ['id' => $mixed->id]));
        $this->assertEquals($history, $DB->get_records('local_mailwhistle_sendlogs'));
        $this->assertFalse(send_manager::retry_failed($mixed->id));
        $this->assertFalse(send_manager::retry_failed($allfailed->id));
        $this->assertFalse(send_manager::retry_failed($active->id));
        $this->assertSame(0, $DB->count_records('task_adhoc'));

        // An interrupted upgrade can safely repeat the data step without duplicating explanations.
        $recipients = $DB->get_records('local_mailwhistle_recipients');
        $campaigns = $DB->get_records('local_mailwhistle_campaigns');
        set_config('version', 2026091101, 'local_mailwhistle');
        $this->assertTrue(\xmldb_local_mailwhistle_upgrade(2026091101));
        $this->assertEquals($recipients, $DB->get_records('local_mailwhistle_recipients'));
        $this->assertEquals($campaigns, $DB->get_records('local_mailwhistle_campaigns'));
    }

    /**
     * New failures with known pre-transport outcomes remain eligible for explicit retry.
     */
    public function test_current_version_does_not_quarantine_new_known_failures(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign(['status' => 'failed']);
        $recipient = $this->recipient($campaign->id, 'failed', 'Attachment unavailable before transport');
        set_config('version', 2026102300, 'local_mailwhistle');
        $this->assertTrue(\xmldb_local_mailwhistle_upgrade(2026102300));
        $this->assertEquals($recipient, $DB->get_record('local_mailwhistle_recipients', ['id' => $recipient->id]));
        $this->assertTrue(send_manager::retry_failed($campaign->id));
        $this->assertSame('pending', $DB->get_field('local_mailwhistle_recipients', 'status', ['id' => $recipient->id]));
    }
}
