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
use local_mailwhistle\manager\send_manager;
use local_mailwhistle\manager\subscription_manager;
use local_mailwhistle\manager\tag_manager;
use local_mailwhistle\manager\tracking_manager;
use local_mailwhistle\page\send;
use local_mailwhistle\reportbuilder\local\helper\campaign as campaignhelper;

/**
 * Duplication preserves authoring data without reusing delivery state or history.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\campaign_manager
 * @covers    \local_mailwhistle\page\send
 * @covers    \local_mailwhistle\reportbuilder\local\helper\campaign
 */
final class campaign_duplicate_test extends \advanced_testcase {
    /**
     * Isolate committed campaign transactions and act as a manager.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        global $CFG, $DB;
        $CFG->noemailever = false;
        $DB->set_field('message_processors', 'enabled', 1, ['name' => 'email']);
        $this->redirectEmails();
        $this->preventResetByRollback();
        $this->setAdminUser();
    }

    /**
     * Content, sender, template and audience rules survive, but no delivery records are copied.
     */
    public function test_duplicate_preserves_authoring_data_and_resets_delivery_state(): void {
        global $DB, $USER;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $author = $this->getDataGenerator()->create_user();
        $template = $generator->create_template([
            'editormode' => 'builder', 'builderjson' => '{"blocks":[]}', 'bodyhtml' => '<p>Current template</p>',
        ]);
        $source = $generator->create_campaign([
            'name' => 'Autumn news', 'subject' => 'Personalised {{firstname}}',
            'bodyhtml' => '<p>Saved campaign content {{unsubscribe}}</p>', 'bodytext' => 'Saved plain text',
            'sendername' => 'Campus news', 'senderemail' => 'replies@example.com',
            'templateid' => $template->id, 'attachmentsjson' => '["handbook.pdf","calendar.pdf"]',
            'createdby' => $author->id, 'status' => campaign_manager::STATUS_SENT,
            'timecreated' => 100, 'timemodified' => 200, 'timescheduled' => 300, 'timesent' => 400,
        ]);
        $tagid = tag_manager::create_tag('Newsletter');
        audience_manager::set_campaign_tags($source->id, [$tagid]);
        $DB->insert_record('local_mailwhistle_audrules', (object) [
            'campaignid' => $source->id, 'type' => 'course', 'instanceid' => 23, 'roleid' => 5, 'timecreated' => 100,
        ]);
        $recipient = $generator->create_recipient(['campaignid' => $source->id, 'userid' => $author->id]);
        tracking_manager::record_open($source->id, $recipient->id);
        helper::log_send($source->id, $recipient->id, 'info', 'Historical send');
        send_manager::queue_campaign($source->id);
        $before = $DB->get_record('local_mailwhistle_campaigns', ['id' => $source->id], '*', MUST_EXIST);
        $tables = ['local_mailwhistle_recipients', 'local_mailwhistle_tracking', 'local_mailwhistle_sendlogs', 'task_adhoc'];
        $history = [];
        foreach ($tables as $table) {
            $history[$table] = $DB->get_records($table);
        }

        $start = time();
        $copyid = campaign_manager::duplicate($source->id);
        $copy = $DB->get_record('local_mailwhistle_campaigns', ['id' => $copyid], '*', MUST_EXIST);
        $this->assertNotSame((int) $source->id, $copyid);
        $this->assertSame('Copy of Autumn news', $copy->name);
        $fields = ['subject', 'bodyhtml', 'bodytext', 'sendername', 'senderemail', 'templateid', 'attachmentsjson', 'sendengine'];
        foreach ($fields as $field) {
            $this->assertSame($before->$field, $copy->$field, $field);
        }
        $this->assertSame(campaign_manager::STATUS_DRAFT, $copy->status);
        $this->assertSame((int) $USER->id, (int) $copy->createdby);
        $this->assertGreaterThanOrEqual($start, (int) $copy->timecreated);
        $this->assertSame($copy->timecreated, $copy->timemodified);
        $this->assertSame(0, (int) $copy->timescheduled);
        $this->assertSame(0, (int) $copy->timesent);
        $originalrules = array_values($DB->get_records('local_mailwhistle_audrules', ['campaignid' => $source->id], 'id'));
        $copiedrules = array_values($DB->get_records('local_mailwhistle_audrules', ['campaignid' => $copyid], 'id'));
        $this->assertCount(2, $copiedrules);
        foreach ($originalrules as $index => $rule) {
            $this->assertNotSame($rule->id, $copiedrules[$index]->id);
            foreach (['type', 'instanceid', 'roleid'] as $field) {
                $this->assertSame($rule->$field, $copiedrules[$index]->$field);
            }
        }
        foreach ($history as $table => $records) {
            $this->assertEquals($records, $DB->get_records($table), $table);
        }
        $this->assertEquals($before, $DB->get_record('local_mailwhistle_campaigns', ['id' => $source->id], '*', MUST_EXIST));
        $this->assertEquals($template, $DB->get_record('local_mailwhistle_templates', ['id' => $template->id], '*', MUST_EXIST));
    }

    /**
     * Every lifecycle state can be reused without changing the original or requiring completeness.
     *
     * @dataProvider status_provider
     * @param string $status Source status.
     */
    public function test_duplicate_accepts_every_campaign_status(string $status): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $source = $generator->create_campaign(['status' => $status, 'bodyhtml' => null, 'bodytext' => null]);
        $copyid = campaign_manager::duplicate($source->id);
        $copy = $DB->get_record('local_mailwhistle_campaigns', ['id' => $copyid], '*', MUST_EXIST);
        $this->assertSame(campaign_manager::STATUS_DRAFT, $copy->status);
        $this->assertNull($copy->bodyhtml);
        $this->assertNull($copy->bodytext);
        $this->assertSame($status, $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $source->id]));
        $this->assertFalse($DB->record_exists('local_mailwhistle_recipients', ['campaignid' => $copyid]));
        $html = campaignhelper::actions($source->id, $source);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('value="duplicatecampaign"', $html);
        $this->assertStringContainsString('aria-label="Duplicate campaign:', $html);
        $this->assertStringContainsString('name="sesskey"', $html);
    }

    /**
     * Known campaign lifecycle states.
     *
     * @return array Source states.
     */
    public static function status_provider(): array {
        return array_map(static fn($status) => [$status], [
            campaign_manager::STATUS_DRAFT, campaign_manager::STATUS_READY, campaign_manager::STATUS_SCHEDULED,
            campaign_manager::STATUS_SCHEDULE_FAILED, campaign_manager::STATUS_SENDING, campaign_manager::STATUS_SENT,
            campaign_manager::STATUS_COMPLETED_WITH_ERRORS, campaign_manager::STATUS_FAILED,
        ]);
    }

    /**
     * New recipients come from current membership and opt-outs, never the original snapshot.
     */
    public function test_copy_resolves_current_members_and_opt_outs_at_send_time(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $source = $generator->create_campaign(['status' => campaign_manager::STATUS_SENT, 'bodyhtml' => '<p>News</p>']);
        $olduser = $this->getDataGenerator()->create_user();
        $optedout = $this->getDataGenerator()->create_user();
        $tagid = tag_manager::create_tag('Newsletter');
        foreach ([$olduser, $optedout] as $user) {
            tag_manager::assign_tag($tagid, $user->id);
            $generator->create_recipient(['campaignid' => $source->id, 'userid' => $user->id]);
        }
        audience_manager::set_campaign_tags($source->id, [$tagid]);
        $copyid = campaign_manager::duplicate($source->id);
        $newuser = $this->getDataGenerator()->create_user();
        tag_manager::assign_tag($tagid, $newuser->id);
        $DB->delete_records('local_mailwhistle_tag_assign', ['tagid' => $tagid, 'userid' => $olduser->id]);
        subscription_manager::set_subscription($optedout->id, false);
        campaign_manager::mark_complete($copyid);
        $this->assertTrue(campaign_manager::begin_sending($copyid));
        $recipients = array_values($DB->get_records('local_mailwhistle_recipients', ['campaignid' => $copyid]));
        $this->assertCount(1, $recipients);
        $this->assertSame((int) $newuser->id, (int) $recipients[0]->userid);
        $this->assertSame(recipient_manager::STATUS_PENDING, $recipients[0]->status);
        $this->assertSame(2, $DB->count_records('local_mailwhistle_recipients', ['campaignid' => $source->id]));
        $this->assertSame(campaign_manager::STATUS_SENT, $DB->get_field(
            'local_mailwhistle_campaigns',
            'status',
            ['id' => $source->id]
        ));
    }

    /**
     * Rolling back a copy removes both its campaign and rules while preserving the original.
     */
    public function test_duplication_participates_in_the_database_transaction(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $source = $generator->create_campaign();
        $tagid = tag_manager::create_tag('Newsletter');
        audience_manager::set_campaign_tags($source->id, [$tagid]);
        $transaction = $DB->start_delegated_transaction();
        try {
            $copyid = campaign_manager::duplicate($source->id);
            $this->assertSame([$tagid], audience_manager::get_campaign_tagids($copyid));
            $transaction->rollback(new \RuntimeException('Abort copy transaction'));
        } catch (\RuntimeException $exception) {
            $this->assertSame('Abort copy transaction', $exception->getMessage());
        }
        $this->assertSame(1, $DB->count_records('local_mailwhistle_campaigns'));
        $this->assertSame(1, $DB->count_records('local_mailwhistle_audrules'));
        $this->assertSame([$tagid], audience_manager::get_campaign_tagids($source->id));
    }

    /**
     * Legacy unnamed sources and Unicode names cannot create empty or oversized names.
     */
    public function test_copy_name_handles_legacy_and_maximum_length_names(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        foreach ([' ', str_repeat('é', 255)] as $name) {
            $source = $generator->create_campaign(['name' => $name]);
            $copyid = campaign_manager::duplicate($source->id);
            $copyname = $DB->get_field('local_mailwhistle_campaigns', 'name', ['id' => $copyid]);
            $this->assertLessThanOrEqual(255, \core_text::strlen($copyname));
            $this->assertStringStartsWith('Copy of ', $copyname);
            $this->assertNotSame('', trim($copyname));
        }
    }

    /**
     * The public action rejects GET and invalid session keys without creating a draft.
     *
     * @dataProvider invalid_request_provider
     * @param bool $post Whether to submit POST data.
     * @param string $errorcode Expected Moodle exception code.
     */
    public function test_duplicate_action_rejects_invalid_requests(bool $post, string $errorcode): void {
        global $DB;
        $source = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_campaign();
        $params = ['campaignid' => $source->id, 'sesskey' => $post ? 'invalid' : sesskey()];
        $_POST = $post ? $params : [];
        $_GET = $post ? [] : $params;
        try {
            send::handle_write('duplicatecampaign');
            $this->fail('An invalid duplication request must be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame($errorcode, $exception->errorcode);
        }
        $this->assertSame(1, $DB->count_records('local_mailwhistle_campaigns'));
    }

    /**
     * Invalid request examples.
     *
     * @return array Request method and expected error.
     */
    public static function invalid_request_provider(): array {
        return [[false, 'invalidrequest'], [true, 'invalidsesskey']];
    }

    /**
     * View-only access permits neither the action nor the corresponding button.
     */
    public function test_view_only_user_cannot_duplicate(): void {
        global $DB;
        $source = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_campaign();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $roleid = create_role('Mail reader', 'mailreader', '');
        assign_capability('local/mailwhistle:view', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);
        $this->assertSame('', campaignhelper::actions($source->id, $source));
        $_POST = ['campaignid' => $source->id, 'sesskey' => sesskey()];
        try {
            send::handle_write('duplicatecampaign');
            $this->fail('View-only users cannot duplicate.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame(1, $DB->count_records('local_mailwhistle_campaigns'));
        }
    }

    /**
     * An authorised submission creates one new draft and redirects to its editor.
     */
    public function test_duplicate_action_creates_draft(): void {
        global $DB;
        $source = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_campaign();
        $_POST = ['campaignid' => $source->id, 'sesskey' => sesskey()];
        try {
            send::handle_write('duplicatecampaign');
            $this->fail('The action must redirect to the new draft.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('redirecterrordetected', $exception->errorcode);
        }
        $copy = $DB->get_record('local_mailwhistle_campaigns', ['name' => 'Copy of ' . $source->name], '*', MUST_EXIST);
        $this->assertSame(2, $DB->count_records('local_mailwhistle_campaigns'));
        $this->assertSame(campaign_manager::STATUS_DRAFT, $copy->status);
    }
}
