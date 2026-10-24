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

use local_mailwhistle\manager\audience_manager;
use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\manager\recipient_manager;
use local_mailwhistle\manager\report_manager;
use local_mailwhistle\manager\send_manager;
use local_mailwhistle\manager\tag_manager;
use local_mailwhistle\task\requeue_stuck_sends;
use local_mailwhistle\task\send_campaign;

/**
 * Delivery state, preference, queue, and retry regressions.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\send_manager
 * @covers    \local_mailwhistle\manager\campaign_manager
 * @covers    \local_mailwhistle\manager\recipient_manager
 * @covers    \local_mailwhistle\manager\report_manager
 * @covers    \local_mailwhistle\task\requeue_stuck_sends
 */
final class delivery_reliability_test extends \advanced_testcase {
    /**
     * Configure the email processor; every test captures transport locally.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        global $DB, $CFG;
        $CFG->noemailever = false;
        $DB->set_field('message_processors', 'enabled', 1, ['name' => 'email']);
        $this->redirectEmails();
    }

    /**
     * Create a complete ready campaign without precreating its snapshot.
     *
     * @param int $count Number of active audience members.
     * @return array Campaign record and users.
     */
    private function ready_campaign(int $count = 1): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign([
            'subject' => 'Hello {{firstname}}',
            'bodyhtml' => '<p>Email: {{email}}</p>',
            'bodytext' => 'Email: {{email}}',
            'sendername' => 'Campus newsletters',
            'senderemail' => 'replies@example.com',
            'status' => campaign_manager::STATUS_DRAFT,
        ]);
        $tag = tag_manager::create_tag('Audience');
        audience_manager::set_campaign_tags($campaign->id, [$tag]);
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $user = $this->getDataGenerator()->create_user(['email' => 'reader' . $i . '@example.com']);
            tag_manager::assign_tag($tag, $user->id);
            $users[] = $user;
        }
        campaign_manager::mark_complete($campaign->id);
        return [$campaign, $users];
    }

    /**
     * Starting atomically creates a complete audience and exactly one task.
     */
    public function test_start_snapshots_and_queues_once(): void {
        global $DB;
        [$campaign] = $this->ready_campaign(3);
        $this->assertTrue(campaign_manager::begin_sending($campaign->id));
        $this->assertFalse(campaign_manager::begin_sending($campaign->id));
        $this->assertSame(3, recipient_manager::count_pending($campaign->id));
        $this->assertSame(1, $DB->count_records('task_adhoc', ['classname' => '\\' . send_campaign::class]));
        (new requeue_stuck_sends())->execute();
        $this->assertSame(1, $DB->count_records('task_adhoc', ['classname' => '\\' . send_campaign::class]));
    }

    /**
     * A stale ready campaign with unavailable attachments cannot partially start.
     */
    public function test_start_failure_leaves_ready_without_snapshot_or_task(): void {
        global $DB;
        [$campaign] = $this->ready_campaign(2);
        $DB->set_field('local_mailwhistle_campaigns', 'attachmentsjson', '["missing.pdf"]', ['id' => $campaign->id]);
        try {
            campaign_manager::begin_sending($campaign->id);
            $this->fail('Starting with unavailable attachments must fail.');
        } catch (\moodle_exception $e) {
            $this->assertSame('campaignincomplete', $e->errorcode);
        }
        $this->assertSame(campaign_manager::STATUS_READY, $DB->get_field(
            'local_mailwhistle_campaigns',
            'status',
            ['id' => $campaign->id]
        ));
        $this->assertFalse($DB->record_exists('local_mailwhistle_recipients', ['campaignid' => $campaign->id]));
        $this->assertFalse($DB->record_exists('task_adhoc', ['classname' => '\\' . send_campaign::class]));
    }

    /**
     * Disabled email is skipped, never counted as accepted or successful.
     */
    public function test_disabled_processor_is_skipped_and_test_copy_fails(): void {
        global $DB;
        [$campaign, $users] = $this->ready_campaign();
        campaign_manager::begin_sending($campaign->id);
        $DB->set_field('message_processors', 'enabled', 0, ['name' => 'email']);
        $sink = $this->redirectEmails();
        send_manager::process_campaign($campaign->id, 50);
        $this->assertFalse(send_manager::send_test($campaign->id, $users[0]));
        $this->assertCount(0, $sink->get_messages());
        $stats = report_manager::get_campaign_stats($campaign->id);
        $this->assertSame(0, $stats->sent);
        $this->assertSame(1, $stats->skipped);
        $this->assertSame(campaign_manager::STATUS_FAILED, $DB->get_field(
            'local_mailwhistle_campaigns',
            'status',
            ['id' => $campaign->id]
        ));
    }

    /**
     * Current preferences and opt-outs are checked even after recipients were snapshotted.
     */
    public function test_preferences_and_late_opt_out_are_preserved(): void {
        global $DB;
        [$campaign, $users] = $this->ready_campaign(3);
        campaign_manager::begin_sending($campaign->id);
        set_user_preference('message_provider_local_mailwhistle_campaign_enabled', 'none', $users[0]);
        $DB->insert_record('local_mailwhistle_unsubscribes', (object) [
            'userid' => $users[1]->id, 'email' => $users[1]->email, 'scope' => 'global', 'timecreated' => time(),
        ]);
        $sink = $this->redirectEmails();
        send_manager::process_campaign($campaign->id, 50);
        $this->assertCount(1, $sink->get_messages());
        $this->assertSame($users[2]->email, $sink->get_messages()[0]->to);
        $stats = report_manager::get_campaign_stats($campaign->id);
        $this->assertSame(2, $stats->skipped);
        $this->assertSame(1, $stats->sent);
        $this->assertSame(campaign_manager::STATUS_COMPLETED_WITH_ERRORS, $DB->get_field(
            'local_mailwhistle_campaigns',
            'status',
            ['id' => $campaign->id]
        ));
    }

    /**
     * Admin-locked preferences follow the same precedence as Moodle messaging.
     */
    public function test_locked_preference_and_provider_disable(): void {
        [$campaign, $users] = $this->ready_campaign();
        $user = $users[0];
        $user->emailstop = 1;
        set_user_preference('message_provider_local_mailwhistle_campaign_enabled', 'none', $user);
        set_config('email_provider_local_mailwhistle_campaign_locked', 1, 'message');
        set_config('message_provider_local_mailwhistle_campaign_enabled', 'email', 'message');
        $this->assertSame('', send_manager::get_skip_reason($user));
        set_config('message_provider_local_mailwhistle_campaign_enabled', 'none', 'message');
        $this->assertNotSame('', send_manager::get_skip_reason($user));
        set_config('message_provider_local_mailwhistle_campaign_enabled', 'email', 'message');
        set_config('local_mailwhistle_campaign_disable', 1, 'message');
        $this->assertNotSame('', send_manager::get_skip_reason($user));
        $this->assertFalse(send_manager::send_test($campaign->id, $user));
    }

    /**
     * Actual destination and configured sender/Reply-To survive standard email processing.
     */
    public function test_destination_and_sender_match_the_email(): void {
        global $CFG, $DB;
        $CFG->messagingallowemailoverride = true;
        [$campaign, $users] = $this->ready_campaign();
        campaign_manager::begin_sending($campaign->id);
        set_user_preference('message_processor_email_email', 'preferred@example.com', $users[0]);
        $sink = $this->redirectEmails();
        send_manager::process_campaign($campaign->id, 50);
        $emails = $sink->get_messages();
        $this->assertCount(1, $emails);
        $this->assertSame('preferred@example.com', $emails[0]->to);
        $this->assertSame($CFG->noreplyaddress, $emails[0]->from);
        $this->assertStringContainsString('Campus newsletters', $emails[0]->header);
        $this->assertMatchesRegularExpression(
            '/^Reply-To: "?Campus newsletters"? <replies@example\.com>\r?$/m',
            $emails[0]->header
        );
        $this->assertStringContainsString('preferred@example.com', quoted_printable_decode($emails[0]->body));
        $this->assertSame('preferred@example.com', $DB->get_field('local_mailwhistle_recipients', 'email', [
            'campaignid' => $campaign->id, 'userid' => $users[0]->id,
        ]));
    }

    /**
     * Retrying resets only known failures, never successful or uncertain deliveries.
     */
    public function test_retry_only_failed_recipients(): void {
        global $DB;
        [$campaign, $users] = $this->ready_campaign(4);
        campaign_manager::begin_sending($campaign->id);
        $recipients = array_values($DB->get_records('local_mailwhistle_recipients', ['campaignid' => $campaign->id], 'id'));
        recipient_manager::mark_sent($recipients[0]->id);
        recipient_manager::mark_failed($recipients[1]->id, 'Temporary rejection');
        recipient_manager::mark_skipped($recipients[2]->id, 'Email opted out');
        recipient_manager::mark_uncertain($recipients[3]->id, 'Interrupted');
        campaign_manager::mark_sent($campaign->id);
        $this->assertTrue(send_manager::retry_failed($campaign->id));
        $this->assertFalse(send_manager::retry_failed($campaign->id));
        $this->assertSame(1, recipient_manager::count_pending($campaign->id));
        $sink = $this->redirectEmails();
        send_manager::process_campaign($campaign->id, 50);
        $emails = $sink->get_messages();
        $this->assertCount(1, $emails);
        $this->assertSame($users[1]->email, $emails[0]->to);
        $stats = report_manager::get_campaign_stats($campaign->id);
        $this->assertSame(2, $stats->sent);
        $this->assertSame(1, $stats->skipped);
        $this->assertSame(1, $stats->uncertain);
        $this->assertSame(0, $stats->failed);
        $this->assertFalse(send_manager::retry_failed($campaign->id));
    }

    /**
     * A false transport result cannot prove rejection after an SMTP acknowledgement is lost.
     */
    public function test_unconfirmed_transport_result_is_not_retryable(): void {
        global $DB;
        [$campaign, $users] = $this->ready_campaign();
        campaign_manager::begin_sending($campaign->id);
        $sender = new class extends send_manager {
            /**
             * Simulate the processor's false return after losing the final DATA acknowledgement.
             *
             * @param \core\message\message $message Prepared campaign email.
             * @return bool Unconfirmed transport outcome.
             */
            protected static function deliver_message(\core\message\message $message): bool {
                return false;
            }
        };
        $sink = $this->redirectEmails();
        $sender::process_campaign($campaign->id, 50);
        $stats = report_manager::get_campaign_stats($campaign->id);
        $this->assertSame(1, $stats->uncertain);
        $this->assertSame(0, $stats->failed);
        $this->assertSame(0, $stats->sent);
        $this->assertSame(1, (int) $DB->get_field('local_mailwhistle_recipients', 'attempts', [
            'campaignid' => $campaign->id, 'userid' => $users[0]->id,
        ]));
        $this->assertFalse(send_manager::retry_failed($campaign->id));
        $this->assertFalse($sender::send_test($campaign->id, $users[0]));
        $this->assertCount(0, $sink->get_messages());
    }

    /**
     * Recovery never resends an email that might have reached its recipient.
     */
    public function test_interrupted_delivery_becomes_uncertain(): void {
        global $DB;
        [$campaign] = $this->ready_campaign(2);
        campaign_manager::begin_sending($campaign->id);
        $recipients = $DB->get_records('local_mailwhistle_recipients', ['campaignid' => $campaign->id]);
        $recipient = reset($recipients);
        recipient_manager::mark_sending($recipient->id);
        $sink = $this->redirectEmails();
        send_manager::process_campaign($campaign->id, 50);
        $this->assertCount(1, $sink->get_messages());
        $this->assertSame(recipient_manager::STATUS_UNCERTAIN, $DB->get_field(
            'local_mailwhistle_recipients',
            'status',
            ['id' => $recipient->id]
        ));
        $this->assertFalse(send_manager::retry_failed($campaign->id));
    }

    /**
     * Legacy empty snapshots cannot remain stuck in sending forever.
     */
    public function test_reaper_finalises_empty_sending_campaign(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign(['status' => campaign_manager::STATUS_SENDING]);
        (new requeue_stuck_sends())->execute();
        $this->assertSame(campaign_manager::STATUS_FAILED, $DB->get_field(
            'local_mailwhistle_campaigns',
            'status',
            ['id' => $campaign->id]
        ));
        $this->assertFalse($DB->record_exists('task_adhoc', ['classname' => '\\' . send_campaign::class]));
    }
}
