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

use local_mailwhistle\form\test_email_form;
use local_mailwhistle\manager\send_manager;
use local_mailwhistle\manager\test_email_manager;

/**
 * Authorisation, address handling, throttling and rendering of test copies.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\test_email_manager
 * @covers    \local_mailwhistle\manager\send_manager
 * @covers    \local_mailwhistle\form\test_email_form
 */
final class test_email_manager_test extends \advanced_testcase {
    /** @var \stdClass Current reviewer with the actual system Manager role. */
    private \stdClass $reviewer;

    /**
     * Enable isolated email capture and a non-administrator Manager account.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        global $DB, $CFG, $PAGE;
        $CFG->noemailever = false;
        $DB->set_field('message_processors', 'enabled', 1, ['name' => 'email']);
        $this->redirectEmails();
        $this->reviewer = self::getDataGenerator()->create_user([
            'firstname' => 'Alice', 'lastname' => 'Reviewer', 'email' => 'alice@example.com',
        ]);
        $managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);
        role_assign($managerrole->id, $this->reviewer->id, \context_system::instance());
        $this->setUser($this->reviewer);
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/local/mailwhistle/campaign_edit.php'));
    }

    /**
     * Create saved content with no audience or recipient snapshot.
     *
     * @param array $fields Optional saved campaign fields.
     * @return \stdClass Campaign record.
     */
    private function campaign(array $fields = []): \stdClass {
        return self::getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_campaign($fields + [
            'name' => 'Review copy',
            'subject' => 'Hello {{firstname}}',
            'bodyhtml' => '<p>Dear {{fullname}} ({{email}})</p><p><a href="https://example.com/news">Read news</a></p>',
            'bodytext' => 'Dear {{fullname}} ({{email}})',
            'sendername' => 'Newsletter team',
            'senderemail' => 'reply@example.com',
        ]);
    }

    /**
     * Different target addresses receive the same saved content with current-user sample values.
     */
    public function test_explicit_destinations_reuse_saved_content_without_tracking(): void {
        global $DB, $CFG;
        $CFG->messagingallowemailoverride = true;
        set_user_preference('message_processor_email_email', 'notification@example.com', $this->reviewer);
        $campaign = $this->campaign();
        $sink = $this->redirectEmails();

        $results = test_email_manager::send($campaign->id, "one@example.com, TWO@example.com;one@example.com\ntwo@example.com");

        $emails = $sink->get_messages();
        $this->assertCount(2, $emails);
        $this->assertSame(['one@example.com', 'TWO@example.com'], array_column($results, 'address'));
        $this->assertSame(['accepted', 'accepted'], array_column($results, 'status'));
        $this->assertSame('one@example.com', $emails[0]->to);
        $this->assertSame('TWO@example.com', $emails[1]->to);
        foreach ($emails as $email) {
            $body = quoted_printable_decode($email->body);
            $this->assertSame('[Test] Hello Alice', $email->subject);
            $this->assertStringContainsString('Dear Alice Reviewer (notification@example.com)', $body);
            $this->assertStringContainsString('https://example.com/news', $body);
            $this->assertStringContainsString('reply@example.com', $email->header);
            $this->assertStringNotContainsString('pixel.php', $body);
            $this->assertStringNotContainsString('click.php', $body);
            $this->assertStringContainsString('unsubscribe.php?preview=1', $body);
            $this->assertStringNotContainsString('unsubscribe.php?t=', $body);
        }
        $this->assertSame(0, $DB->count_records('local_mailwhistle_recipients'));
        $this->assertSame(0, $DB->count_records('local_mailwhistle_tracking'));
        $this->assertSame(0, $DB->count_records('local_mailwhistle_sendlogs'));
        $this->assertEquals($campaign, $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaign->id]));
    }

    /**
     * Site diversion remains effective and is disclosed rather than claiming the requested inbox received mail.
     */
    public function test_site_diversion_is_reported(): void {
        global $CFG;
        $CFG->divertallemailsto = 'catcher@example.com';
        $campaign = $this->campaign();
        $sink = $this->redirectEmails();

        $results = test_email_manager::send($campaign->id, 'requested@example.com');

        $this->assertSame('requested@example.com', $results[0]['address']);
        $this->assertSame('catcher@example.com', $results[0]['destination']);
        $this->assertTrue($results[0]['diverted']);
        $this->assertSame('catcher@example.com', $sink->get_messages()[0]->to);
    }

    /**
     * Sample profile values remain escaped in the HTML copy sent to every reviewer.
     */
    public function test_sample_values_are_html_escaped(): void {
        global $DB;
        $DB->set_field('user', 'firstname', 'Alice <strong>sample</strong>', ['id' => $this->reviewer->id]);
        $sink = $this->redirectEmails();
        test_email_manager::send($this->campaign()->id, 'target@example.com');
        $body = quoted_printable_decode($sink->get_messages()[0]->body);
        $this->assertStringContainsString('Alice &lt;strong&gt;sample&lt;/strong&gt; Reviewer', $body);
        $this->assertStringNotContainsString('Alice <strong>sample</strong>', $body);
    }

    /**
     * Explicit reviewer addresses cannot bypass Moodle's disabled email transport policy.
     */
    public function test_site_policy_blocks_test_copies(): void {
        global $CFG;
        $CFG->noemailever = true;
        $sink = $this->redirectEmails();
        $results = test_email_manager::send($this->campaign()->id, 'target@example.com');
        $this->assertSame('failed', $results[0]['status']);
        $this->assertSame(get_string('delivery_disabled', 'local_mailwhistle'), $results[0]['message']);
        $this->assertCount(0, $sink->get_messages());
    }

    /**
     * Invalid lists cannot partly send or consume the reviewer's allowance.
     *
     * @dataProvider invalid_addresses_provider
     * @param string $input Invalid destination list.
     */
    public function test_invalid_list_sends_nothing(string $input): void {
        global $DB;
        $campaign = $this->campaign();
        $sink = $this->redirectEmails();
        try {
            test_email_manager::send($campaign->id, $input);
            $this->fail('The invalid list should be rejected before sending.');
        } catch (\moodle_exception $exception) {
            $this->assertContains($exception->errorcode, ['testmail_invalidaddresses', 'testmail_toomany']);
        }
        $this->assertCount(0, $sink->get_messages());
        $this->assertFalse($DB->record_exists('user_preferences', [
            'userid' => $this->reviewer->id, 'name' => test_email_manager::RATE_PREFERENCE,
        ]));
    }

    /**
     * Invalid complete-list cases, including a valid address before the invalid one.
     *
     * @return array Test cases.
     */
    public static function invalid_addresses_provider(): array {
        return [
            'empty' => [' , ; '],
            'invalid second address' => ['valid@example.com, invalid'],
            'display name' => ['Person <person@example.com>'],
            'header injection' => ["valid@example.com\r\nBcc: other@example.com"],
            'too many' => [implode(',', array_map(static fn($i) => "person{$i}@example.com", range(1, 6)))],
        ];
    }

    /**
     * Persistent counters survive a fresh user session and apply across different campaigns.
     */
    public function test_rate_limit_survives_a_fresh_session(): void {
        global $DB, $USER;
        $campaign = $this->campaign();
        set_user_preference(test_email_manager::RATE_PREFERENCE, json_encode(array_fill(0, 19, time())), $this->reviewer);
        $sink = $this->redirectEmails();
        test_email_manager::send($campaign->id, 'first@example.com');
        $this->assertCount(1, $sink->get_messages());

        $this->setUser($DB->get_record('user', ['id' => $this->reviewer->id]));
        $USER->preference = [test_email_manager::RATE_PREFERENCE => '[]', '_lastloaded' => time()];
        $othercampaign = $this->campaign();
        try {
            test_email_manager::send($othercampaign->id, 'second@example.com');
            $this->fail('A new session must not reset the allowance.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('testmail_limit', $exception->errorcode);
        }
        $this->assertCount(1, $sink->get_messages());
    }

    /**
     * Expired attempts are discarded and duplicate list entries consume only one attempt.
     */
    public function test_allowance_recovers_and_counts_unique_addresses(): void {
        global $DB;
        set_user_preference(
            test_email_manager::RATE_PREFERENCE,
            json_encode(array_fill(0, 20, time() - HOURSECS - 1)),
            $this->reviewer
        );
        test_email_manager::send($this->campaign()->id, 'one@example.com, ONE@example.com');
        $attempts = json_decode($DB->get_field('user_preferences', 'value', [
            'userid' => $this->reviewer->id, 'name' => test_email_manager::RATE_PREFERENCE,
        ]), true);
        $this->assertCount(1, $attempts);
        $this->assertGreaterThanOrEqual(time() - 10, $attempts[0]);
    }

    /**
     * A forbidden non-manager request cannot send mail even when it knows a valid campaign id.
     */
    public function test_service_requires_manage_capability(): void {
        $campaign = $this->campaign();
        $this->setUser(self::getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        test_email_manager::send($campaign->id, 'target@example.com');
    }

    /**
     * The native dynamic form checks capability before displaying campaign data.
     */
    public function test_dynamic_form_requires_manage_capability(): void {
        $campaign = $this->campaign();
        $this->setUser(self::getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        new test_email_form(null, null, 'post', '', [], true, ['campaignid' => $campaign->id], true);
    }

    /**
     * A valid native modal submission delivers to the addresses entered in the form.
     */
    public function test_dynamic_form_processes_validated_addresses(): void {
        $campaign = $this->campaign();
        $data = test_email_form::mock_ajax_submit(['campaignid' => $campaign->id, 'addresses' => 'entered@example.com']);
        $form = new test_email_form(null, null, 'post', '', [], true, $data, true);
        $form->set_data_for_dynamic_submission();

        $this->assertTrue($form->is_validated());
        $results = $form->process_dynamic_submission();
        $this->assertSame('entered@example.com', $results['results'][0]['address']);
        $this->assertSame('accepted', $results['results'][0]['status']);
    }

    /**
     * Missing or incorrect session keys cannot trigger the modal's submission handler.
     */
    public function test_dynamic_form_requires_session_key(): void {
        $campaign = $this->campaign();
        $form = new test_email_form(null, null, 'post', '', [], true, [
            'campaignid' => $campaign->id, 'addresses' => 'entered@example.com', 'sesskey' => 'incorrect',
        ], true);
        try {
            $form->process_dynamic_submission();
            $this->fail('An invalid session key must not send any copies.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidsesskey', $exception->errorcode);
        }
    }

    /**
     * Per-address failures do not hide successful copies to other destinations.
     */
    public function test_per_address_outcomes_distinguish_policy_failure(): void {
        $sink = $this->redirectEmails();
        $results = test_email_manager::send($this->campaign()->id, 'invalid@example.invalid; valid@example.com');
        $this->assertSame(['failed', 'accepted'], array_column($results, 'status'));
        $this->assertCount(1, $sink->get_messages());
        $this->assertSame('valid@example.com', $sink->get_messages()[0]->to);
    }

    /**
     * Unconfirmed transport results remain uncertain, rather than promising that nothing was sent.
     */
    public function test_unconfirmed_transport_is_explicitly_uncertain(): void {
        $sender = new class extends send_manager {
            /**
             * Simulate an SMTP acknowledgement that was not received.
             *
             * @param \core\message\message $message Prepared test copy.
             * @return bool Unconfirmed outcome.
             */
            protected static function deliver_message(\core\message\message $message): bool {
                return false;
            }
        };
        $result = $sender::send_test_to_address($this->campaign(), $this->reviewer, 'target@example.com');
        $this->assertSame('uncertain', $result['status']);
        $this->assertSame(get_string('testmail_uncertain', 'local_mailwhistle'), $result['message']);
    }

    /**
     * Saved resource files use the same attachment path as campaign delivery.
     */
    public function test_test_copy_includes_saved_attachments(): void {
        global $CFG;
        $CFG->allowattachments = true;
        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_mailwhistle',
            'filearea' => \local_mailwhistle\output\resources::FILEAREA,
            'itemid' => 0, 'filepath' => '/', 'filename' => 'review.txt',
        ], 'Attachment content');
        $campaign = $this->campaign(['attachmentsjson' => '["review.txt"]']);
        $sink = $this->redirectEmails();
        $results = test_email_manager::send($campaign->id, 'target@example.com');
        $this->assertSame('accepted', $results[0]['status']);
        $this->assertStringContainsString('Content-Disposition: attachment;', $sink->get_messages()[0]->body);
        $this->assertStringContainsString('review.txt', $sink->get_messages()[0]->body);
    }
}
