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

use core_reportbuilder\external\system_report_data_exporter;
use core_reportbuilder\system_report_factory;
use local_mailwhistle\form\campaign_schedule_form;
use local_mailwhistle\manager\audience_manager;
use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\manager\recipient_manager;
use local_mailwhistle\manager\tag_manager;
use local_mailwhistle\page\campaign_schedule;
use local_mailwhistle\reportbuilder\local\systemreports\draft_campaigns;
use local_mailwhistle\task\dispatch_scheduled_campaigns;
use local_mailwhistle\task\send_campaign;

/**
 * Scheduling, due-time claiming, cancellation, and scheduling-page regressions.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\campaign_manager
 * @covers    \local_mailwhistle\task\dispatch_scheduled_campaigns
 * @covers    \local_mailwhistle\form\campaign_schedule_form
 * @covers    \local_mailwhistle\page\campaign_schedule
 * @covers    \local_mailwhistle\reportbuilder\local\systemreports\draft_campaigns
 * @covers    \local_mailwhistle\reportbuilder\local\entities\campaign
 * @covers    \local_mailwhistle\reportbuilder\local\helper\campaign
 */
final class campaign_schedule_test extends \advanced_testcase {
    /**
     * Load the native form library and isolate committed campaign transactions.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
    }

    /**
     * Create a complete campaign with one current audience member.
     *
     * @return array Campaign, tag id and user.
     */
    private function ready_campaign(): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign(['bodyhtml' => '<p>Scheduled newsletter</p>']);
        $tagid = tag_manager::create_tag('Audience ' . $campaign->id);
        $user = $this->getDataGenerator()->create_user();
        tag_manager::assign_tag($tagid, $user->id);
        audience_manager::set_campaign_tags($campaign->id, [$tagid]);
        campaign_manager::mark_complete($campaign->id);
        return [$campaign, $tagid, $user];
    }

    /**
     * Assert a campaign status directly from storage.
     *
     * @param int $id Campaign id.
     * @param string $status Expected state.
     */
    private function assert_status(int $id, string $status): void {
        global $DB;
        $this->assertSame($status, $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $id]));
    }

    /**
     * Make a previously scheduled campaign due without waiting for wall-clock time.
     *
     * @param int $id Campaign id.
     */
    private function make_due(int $id): void {
        global $DB;
        $DB->set_field('local_mailwhistle_campaigns', 'timescheduled', time() - 1, ['id' => $id]);
    }

    /**
     * Scheduling persists one absolute time but no recipients or queued emails.
     */
    public function test_scheduling_does_not_snapshot_or_queue_early(): void {
        global $DB;
        [$campaign] = $this->ready_campaign();
        $future = time() + DAYSECS;
        $this->assertTrue(campaign_manager::schedule($campaign->id, $future));
        $this->assertFalse(campaign_manager::schedule($campaign->id, $future + DAYSECS));
        $this->assertFalse(campaign_manager::begin_sending($campaign->id));
        $this->assertFalse(campaign_manager::begin_scheduled_sending($campaign->id));
        (new dispatch_scheduled_campaigns())->execute();
        $this->assert_status($campaign->id, campaign_manager::STATUS_SCHEDULED);
        $this->assertSame($future, (int) $DB->get_field('local_mailwhistle_campaigns', 'timescheduled', ['id' => $campaign->id]));
        $this->assertFalse($DB->record_exists('local_mailwhistle_recipients', ['campaignid' => $campaign->id]));
        $this->assertFalse($DB->record_exists('task_adhoc', ['classname' => '\\' . send_campaign::class]));
    }

    /**
     * A stale past timestamp is rejected by the manager as well as the form.
     */
    public function test_past_time_cannot_be_scheduled(): void {
        [$campaign] = $this->ready_campaign();
        try {
            campaign_manager::schedule($campaign->id, time() - 1);
            $this->fail('A past timestamp must be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('schedulefuture', $exception->errorcode);
        }
        $this->assert_status($campaign->id, campaign_manager::STATUS_READY);
    }

    /**
     * Ready state alone is insufficient if the audience has since disappeared.
     */
    public function test_scheduling_rechecks_readiness(): void {
        global $DB;
        [$campaign, $tagid] = $this->ready_campaign();
        $DB->delete_records('local_mailwhistle_tag_assign', ['tagid' => $tagid]);
        try {
            campaign_manager::schedule($campaign->id, time() + DAYSECS);
            $this->fail('An empty audience must fail readiness.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('campaignincomplete', $exception->errorcode);
        }
        $this->assert_status($campaign->id, campaign_manager::STATUS_READY);
    }

    /**
     * The due claim snapshots the current membership and repeated cron runs queue once.
     */
    public function test_due_dispatch_uses_current_audience_and_queues_once(): void {
        global $DB;
        [$campaign, $tagid, $olduser] = $this->ready_campaign();
        campaign_manager::schedule($campaign->id, time() + DAYSECS);
        $newuser = $this->getDataGenerator()->create_user();
        $DB->delete_records('local_mailwhistle_tag_assign', ['tagid' => $tagid, 'userid' => $olduser->id]);
        tag_manager::assign_tag($tagid, $newuser->id);
        $this->make_due($campaign->id);
        (new dispatch_scheduled_campaigns())->execute();
        (new dispatch_scheduled_campaigns())->execute();
        $this->assertFalse(campaign_manager::begin_scheduled_sending($campaign->id));
        $this->assert_status($campaign->id, campaign_manager::STATUS_SENDING);
        $this->assertSame(1, recipient_manager::count_pending($campaign->id));
        $this->assertSame((int) $newuser->id, (int) $DB->get_field('local_mailwhistle_recipients', 'userid', [
            'campaignid' => $campaign->id,
        ]));
        $this->assertSame(1, $DB->count_records('task_adhoc', ['classname' => '\\' . send_campaign::class]));
        $this->assertSame(0, (int) $DB->get_field('local_mailwhistle_campaigns', 'timesent', ['id' => $campaign->id]));
    }

    /**
     * Dispatch freezes an inert placeholder footer while preserving absent alternate formats.
     */
    public function test_dispatch_freezes_footer_in_saved_body(): void {
        global $DB;
        [$campaign] = $this->ready_campaign();
        campaign_manager::schedule($campaign->id, time() + DAYSECS);
        $this->assertSame($campaign->bodyhtml, $DB->get_field('local_mailwhistle_campaigns', 'bodyhtml', ['id' => $campaign->id]));
        $this->make_due($campaign->id);
        $this->assertTrue(campaign_manager::begin_scheduled_sending($campaign->id));
        $saved = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaign->id], '*', MUST_EXIST);
        $this->assertStringStartsWith($campaign->bodyhtml, $saved->bodyhtml);
        $this->assertStringContainsString('href="{{unsubscribe}}"', $saved->bodyhtml);
        $this->assertSame($campaign->bodytext, $saved->bodytext);
        $this->assertFalse(campaign_manager::begin_scheduled_sending($campaign->id));
        $this->assertSame($saved->bodyhtml, $DB->get_field('local_mailwhistle_campaigns', 'bodyhtml', ['id' => $campaign->id]));
    }

    /**
     * An aborted dispatch transaction rolls back body, status, audience and task together.
     */
    public function test_dispatch_rollback_restores_body_and_queue(): void {
        global $DB;
        [$campaign] = $this->ready_campaign();
        campaign_manager::schedule($campaign->id, time() + DAYSECS);
        $this->make_due($campaign->id);
        $before = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaign->id], '*', MUST_EXIST);
        $transaction = $DB->start_delegated_transaction();
        try {
            $this->assertTrue(campaign_manager::begin_scheduled_sending($campaign->id));
            $this->assertSame(1, recipient_manager::count_pending($campaign->id));
            $transaction->rollback(new \RuntimeException('Abort dispatch transaction'));
        } catch (\RuntimeException $exception) {
            $this->assertSame('Abort dispatch transaction', $exception->getMessage());
        }
        $this->assertEquals($before, $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaign->id], '*', MUST_EXIST));
        $this->assertFalse($DB->record_exists('local_mailwhistle_recipients', ['campaignid' => $campaign->id]));
        $this->assertFalse($DB->record_exists('task_adhoc', ['classname' => '\\' . send_campaign::class]));
        $this->assertTrue(
            campaign_manager::begin_scheduled_sending($campaign->id),
            'The rolled-back schedule must remain claimable.'
        );
    }

    /**
     * Cancellation before the claim clears the schedule, and the draft can be reopened.
     */
    public function test_cancellation_prevents_stale_dispatch_and_allows_editing(): void {
        global $DB;
        [$campaign] = $this->ready_campaign();
        campaign_manager::schedule($campaign->id, time() + DAYSECS);
        $this->make_due($campaign->id);
        $this->assertTrue(campaign_manager::cancel_schedule($campaign->id));
        $this->assertFalse(campaign_manager::begin_scheduled_sending($campaign->id));
        $this->assertFalse(campaign_manager::cancel_schedule($campaign->id));
        $this->assertSame(0, (int) $DB->get_field('local_mailwhistle_campaigns', 'timescheduled', ['id' => $campaign->id]));
        campaign_manager::return_to_draft($campaign->id);
        campaign_manager::update_fields($campaign->id, ['subject' => 'Rescheduled newsletter']);
        $this->assert_status($campaign->id, campaign_manager::STATUS_DRAFT);
        $this->assertSame(0, recipient_manager::count_pending($campaign->id));
    }

    /**
     * A cancellation arriving after the due claim cannot reopen a sending campaign.
     */
    public function test_cancellation_after_dispatch_preserves_delivery(): void {
        [$campaign] = $this->ready_campaign();
        campaign_manager::schedule($campaign->id, time() + DAYSECS);
        $this->make_due($campaign->id);
        $this->assertTrue(campaign_manager::begin_scheduled_sending($campaign->id));
        $this->assertFalse(campaign_manager::cancel_schedule($campaign->id));
        $this->assert_status($campaign->id, campaign_manager::STATUS_SENDING);
        $this->assertSame(1, recipient_manager::count_pending($campaign->id));
    }

    /**
     * Readiness failures are visible, do not loop, and do not block other due campaigns.
     */
    public function test_invalid_due_campaign_needs_review_without_partial_snapshot(): void {
        global $DB;
        [$invalid] = $this->ready_campaign();
        [$valid] = $this->ready_campaign();
        foreach ([$invalid, $valid] as $campaign) {
            campaign_manager::schedule($campaign->id, time() + DAYSECS);
            $this->make_due($campaign->id);
        }
        $DB->set_field('local_mailwhistle_campaigns', 'attachmentsjson', '["missing.pdf"]', ['id' => $invalid->id]);
        (new dispatch_scheduled_campaigns())->execute();
        (new dispatch_scheduled_campaigns())->execute();
        $this->assert_status($invalid->id, campaign_manager::STATUS_SCHEDULE_FAILED);
        $this->assert_status($valid->id, campaign_manager::STATUS_SENDING);
        $this->assertFalse($DB->record_exists('local_mailwhistle_recipients', ['campaignid' => $invalid->id]));
        $this->assertSame(0, (int) $DB->get_field('local_mailwhistle_campaigns', 'timesent', ['id' => $invalid->id]));
        $logs = $DB->get_records('local_mailwhistle_sendlogs', ['campaignid' => $invalid->id, 'recipientid' => 0]);
        $this->assertCount(1, $logs);
        $this->assertNotEmpty(json_decode(reset($logs)->datajson)->errors);
        $this->assertTrue(campaign_manager::cancel_schedule($invalid->id));
        $this->assert_status($invalid->id, campaign_manager::STATUS_READY);
    }

    /**
     * Scheduled states cannot be modified through draft APIs or rescheduled from stale forms.
     *
     * @dataProvider schedule_states_provider
     * @param string $status Scheduled state.
     */
    public function test_scheduled_content_is_immutable(string $status): void {
        global $DB;
        [$campaign] = $this->ready_campaign();
        $DB->set_field('local_mailwhistle_campaigns', 'status', $status, ['id' => $campaign->id]);
        $this->assertFalse(campaign_manager::schedule($campaign->id, time() + DAYSECS));
        try {
            campaign_manager::update_fields($campaign->id, ['subject' => 'Unsafe stale edit']);
            $this->fail('Scheduled content must be immutable.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('campaignnoteditable', $exception->errorcode);
        }
        try {
            campaign_manager::return_to_draft($campaign->id);
            $this->fail('The schedule must first be cancelled.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('campaigncannotreopen', $exception->errorcode);
        }
        $this->assert_status($campaign->id, $status);
    }

    /**
     * Scheduled states which require explicit cancellation before editing.
     *
     * @return array Test states.
     */
    public static function schedule_states_provider(): array {
        return [[campaign_manager::STATUS_SCHEDULED], [campaign_manager::STATUS_SCHEDULE_FAILED]];
    }

    /**
     * Inconsistent legacy snapshots cannot be reopened or reused by a new schedule.
     *
     * @dataProvider snapshot_action_provider
     * @param string $status Campaign state.
     * @param string $action Attempted mutation.
     */
    public function test_existing_snapshot_prevents_schedule_changes(string $status, string $action): void {
        global $DB;
        [$campaign] = $this->ready_campaign();
        $DB->set_field('local_mailwhistle_campaigns', 'status', $status, ['id' => $campaign->id]);
        recipient_manager::snapshot_recipients($campaign->id);
        $before = $DB->get_records('local_mailwhistle_recipients', ['campaignid' => $campaign->id]);
        try {
            if ($action === 'schedule') {
                campaign_manager::schedule($campaign->id, time() + DAYSECS);
            } else if ($action === 'cancel') {
                campaign_manager::cancel_schedule($campaign->id);
            } else {
                campaign_manager::return_to_draft($campaign->id);
            }
            $this->fail('Existing snapshots must block authoring and schedule changes.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('campaignhassnapshot', $exception->errorcode);
        }
        $this->assert_status($campaign->id, $status);
        $this->assertEquals($before, $DB->get_records('local_mailwhistle_recipients', ['campaignid' => $campaign->id]));
        $this->assertFalse($DB->record_exists('task_adhoc', ['classname' => '\\' . send_campaign::class]));
    }

    /**
     * All editable/scheduled entry points must preserve an existing snapshot.
     *
     * @return array State and action combinations.
     */
    public static function snapshot_action_provider(): array {
        return [
            [campaign_manager::STATUS_READY, 'schedule'],
            [campaign_manager::STATUS_READY, 'reopen'],
            [campaign_manager::STATUS_SCHEDULED, 'cancel'],
            [campaign_manager::STATUS_SCHEDULE_FAILED, 'cancel'],
        ];
    }

    /**
     * A pre-existing recipient snapshot is never treated as a newly resolved due audience.
     */
    public function test_due_dispatch_rejects_existing_snapshot(): void {
        global $DB;
        [$campaign] = $this->ready_campaign();
        campaign_manager::schedule($campaign->id, time() + DAYSECS);
        $this->make_due($campaign->id);
        recipient_manager::snapshot_recipients($campaign->id);
        $before = $DB->get_records('local_mailwhistle_recipients', ['campaignid' => $campaign->id]);
        $this->assertFalse(campaign_manager::begin_scheduled_sending($campaign->id));
        $this->assert_status($campaign->id, campaign_manager::STATUS_SCHEDULE_FAILED);
        $this->assertEquals($before, $DB->get_records('local_mailwhistle_recipients', ['campaignid' => $campaign->id]));
        $this->assertFalse($DB->record_exists('task_adhoc', ['classname' => '\\' . send_campaign::class]));
    }

    /**
     * Moodle's date selector exports the chosen user's local time as an absolute instant.
     */
    public function test_form_uses_user_timezone_and_rejects_past_time(): void {
        global $CFG;
        $CFG->forcetimezone = '99';
        $user = $this->getDataGenerator()->create_user(['timezone' => 'Pacific/Auckland']);
        $this->setUser($user);
        $date = new \DateTimeImmutable('tomorrow 12:34:00', new \DateTimeZone('Pacific/Auckland'));
        campaign_schedule_form::mock_submit(['campaignid' => 1, 'timescheduled' => [
            'day' => $date->format('j'), 'month' => $date->format('n'), 'year' => $date->format('Y'),
            'hour' => $date->format('G'), 'minute' => $date->format('i'),
        ]]);
        $form = new campaign_schedule_form();
        $data = $form->get_data();
        $this->assertNotNull($data);
        $this->assertSame($date->getTimestamp(), (int) $data->timescheduled);
        $this->assertStringContainsString('Pacific/Auckland', $form->render());
        $this->assertArrayHasKey('timescheduled', $form->validation(['timescheduled' => time() - 1], []));
        $this->assertStringContainsString('Pacific/Auckland', campaign_schedule::format_time($date->getTimestamp()));
    }

    /**
     * Execute the scheduling controller without leaking rendered page output.
     *
     * @param bool $redirect Whether a Moodle CLI redirect should occur.
     * @return string Rendered page HTML.
     */
    private function execute_page(bool $redirect = false): string {
        $redirected = false;
        ob_start();
        try {
            campaign_schedule::execute();
        } catch (\moodle_exception $exception) {
            if ($exception->errorcode !== 'redirecterrordetected') {
                throw $exception;
            }
            $redirected = true;
        } finally {
            $html = ob_get_clean();
        }
        $this->assertSame($redirect, $redirected);
        return $html;
    }

    /**
     * Viewing a scheduling form never schedules a campaign.
     */
    public function test_get_page_is_read_only_and_displays_timezone(): void {
        [$campaign] = $this->ready_campaign();
        $_GET = ['campaignid' => $campaign->id];
        $html = $this->execute_page();
        $this->assertStringContainsString('name="timescheduled[year]"', $html);
        $this->assertStringContainsString(s(\core_date::get_user_timezone_object()->getName()), $html);
        $this->assert_status($campaign->id, campaign_manager::STATUS_READY);
    }

    /**
     * Scheduling requires a valid form submission and confirms the saved date.
     */
    public function test_posting_form_creates_schedule(): void {
        global $DB;
        [$campaign] = $this->ready_campaign();
        $date = new \DateTimeImmutable('tomorrow 12:34:00', \core_date::get_user_timezone_object());
        campaign_schedule_form::mock_submit(['campaignid' => $campaign->id, 'timescheduled' => [
            'day' => $date->format('j'), 'month' => $date->format('n'), 'year' => $date->format('Y'),
            'hour' => $date->format('G'), 'minute' => $date->format('i'),
        ]]);
        $this->execute_page(true);
        $this->assert_status($campaign->id, campaign_manager::STATUS_SCHEDULED);
        $this->assertSame($date->getTimestamp(), (int) $DB->get_field(
            'local_mailwhistle_campaigns',
            'timescheduled',
            ['id' => $campaign->id]
        ));
    }

    /**
     * A rejected past date remains visible instead of silently reverting to the default time.
     */
    public function test_invalid_form_keeps_the_submitted_date(): void {
        [$campaign] = $this->ready_campaign();
        $date = new \DateTimeImmutable('yesterday 05:17:00', \core_date::get_user_timezone_object());
        campaign_schedule_form::mock_submit(['campaignid' => $campaign->id, 'timescheduled' => [
            'day' => $date->format('j'), 'month' => $date->format('n'), 'year' => $date->format('Y'),
            'hour' => '5', 'minute' => '17',
        ]]);
        $html = $this->execute_page();
        $this->assertStringContainsString(get_string('schedulefuture', 'local_mailwhistle'), $html);
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        foreach (['day' => $date->format('j'), 'hour' => '5', 'minute' => '17'] as $part => $expected) {
            $selected = $xpath->query('//select[@name="timescheduled[' . $part . ']"]/option[@selected]');
            $this->assertSame($expected, $selected->item(0)->getAttribute('value'));
        }
        $this->assert_status($campaign->id, campaign_manager::STATUS_READY);
    }

    /**
     * Cancellation is a POST-only action, even if the URL contains a valid sesskey.
     */
    public function test_get_cannot_cancel_schedule(): void {
        [$campaign] = $this->ready_campaign();
        campaign_manager::schedule($campaign->id, time() + DAYSECS);
        $_GET = ['campaignid' => $campaign->id, 'action' => 'cancelschedule', 'sesskey' => sesskey()];
        try {
            $this->execute_page();
            $this->fail('GET cancellation must be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidrequest', $exception->errorcode);
        }
        $this->assert_status($campaign->id, campaign_manager::STATUS_SCHEDULED);
    }

    /**
     * A forged POST cannot cancel another manager's scheduled campaign.
     */
    public function test_post_cancellation_requires_sesskey(): void {
        [$campaign] = $this->ready_campaign();
        campaign_manager::schedule($campaign->id, time() + DAYSECS);
        $_POST = ['campaignid' => $campaign->id, 'action' => 'cancelschedule', 'sesskey' => 'invalid'];
        try {
            $this->execute_page();
            $this->fail('An invalid sesskey must be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidsesskey', $exception->errorcode);
        }
        $this->assert_status($campaign->id, campaign_manager::STATUS_SCHEDULED);
    }

    /**
     * Authorized cancellation returns a scheduled campaign to ready.
     */
    public function test_post_cancellation_succeeds(): void {
        [$campaign] = $this->ready_campaign();
        campaign_manager::schedule($campaign->id, time() + DAYSECS);
        $_POST = ['campaignid' => $campaign->id, 'action' => 'cancelschedule', 'sesskey' => sesskey()];
        $this->execute_page(true);
        $this->assert_status($campaign->id, campaign_manager::STATUS_READY);
    }

    /**
     * A view-only user cannot access the schedule page or cancel schedules.
     */
    public function test_view_capability_does_not_allow_scheduling(): void {
        [$campaign] = $this->ready_campaign();
        campaign_manager::schedule($campaign->id, time() + DAYSECS);
        $user = $this->getDataGenerator()->create_user();
        $roleid = create_role('Mail reader', 'mailreader', '');
        assign_capability('local/mailwhistle:view', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        $this->setUser($user);
        $_POST = ['campaignid' => $campaign->id, 'action' => 'cancelschedule', 'sesskey' => sesskey()];
        $this->expectException(\required_capability_exception::class);
        $this->execute_page();
    }

    /**
     * Blocked schedules show actionable errors and their planned date, not a completion date.
     */
    public function test_failed_schedule_page_explains_review(): void {
        global $DB;
        [$campaign, $tagid] = $this->ready_campaign();
        campaign_manager::schedule($campaign->id, time() + DAYSECS);
        $DB->delete_records('local_mailwhistle_tag_assign', ['tagid' => $tagid]);
        $this->make_due($campaign->id);
        campaign_manager::begin_scheduled_sending($campaign->id);
        $_GET = ['campaignid' => $campaign->id];
        $html = $this->execute_page();
        $this->assertStringContainsString(get_string('schedulefailedhelp', 'local_mailwhistle'), $html);
        $this->assertStringContainsString(get_string('campaignemptyaudience', 'local_mailwhistle'), $html);
        $this->assertStringContainsString(get_string('cancelschedule', 'local_mailwhistle'), $html);
        $this->assertStringNotContainsString(get_string('col_completedat', 'local_mailwhistle'), $html);
    }

    /**
     * The native campaign table includes future and blocked schedules with scheduling actions.
     */
    public function test_native_report_includes_scheduled_campaigns(): void {
        global $DB, $PAGE;
        [$scheduled] = $this->ready_campaign();
        [$blocked] = $this->ready_campaign();
        campaign_manager::schedule($scheduled->id, time() + DAYSECS);
        campaign_manager::schedule($blocked->id, time() + DAYSECS);
        $DB->set_field('local_mailwhistle_campaigns', 'status', campaign_manager::STATUS_SCHEDULE_FAILED, ['id' => $blocked->id]);
        $PAGE->set_url('/local/mailwhistle/index.php', ['tab' => 'send']);
        $PAGE->set_context(\context_system::instance());
        $report = system_report_factory::create(draft_campaigns::class, \context_system::instance());
        $exporter = new system_report_data_exporter(null, ['report' => $report, 'page' => 0, 'perpage' => 50]);
        $data = $exporter->export($PAGE->get_renderer('core_reportbuilder'));
        $this->assertSame(2, $data->totalrowcount);
        $this->assertCount(5, $data->headers);
        foreach ($data->rows as $row) {
            $this->assertStringContainsString('campaign_schedule.php', $row['columns'][0]);
            $this->assertStringContainsString(\core_date::get_user_timezone_object()->getName(), $row['columns'][3]);
            $this->assertStringContainsString(get_string('viewschedule', 'local_mailwhistle'), $row['columns'][4]);
            $this->assertStringNotContainsString('action=sendnow', $row['columns'][4]);
        }
    }
}
