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

use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\manager\audience_manager;
use local_mailwhistle\manager\tag_manager;

/**
 * Unit tests for the campaign_manager class.
 *
 * Covers whitelisted field updates, the draft completeness gate, and the
 * mark-complete transition (including its guard).
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\campaign_manager
 */
final class campaign_manager_test extends \advanced_testcase {
    /**
     * Insert a minimal draft campaign and return its id.
     *
     * @return int The new campaign id.
     */
    private function create_campaign(): int {
        global $DB, $USER;
        $now = time();
        return (int) $DB->insert_record('local_mailwhistle_campaigns', (object) [
            'name' => 'Draft',
            'subject' => '',
            'status' => campaign_manager::STATUS_DRAFT,
            'createdby' => (int) $USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Test: update_fields writes whitelisted columns and bumps timemodified.
     */
    public function test_update_fields_writes_whitelisted(): void {
        $this->resetAfterTest();
        global $DB;

        $id = $this->create_campaign();
        $before = $DB->get_record('local_mailwhistle_campaigns', ['id' => $id], '*', MUST_EXIST);

        campaign_manager::update_fields($id, [
            'name' => 'New name',
            'subject' => 'Hello',
            'sendername' => 'Team',
        ]);

        $after = $DB->get_record('local_mailwhistle_campaigns', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('New name', $after->name);
        $this->assertSame('Hello', $after->subject);
        $this->assertSame('Team', $after->sendername);
        $this->assertGreaterThanOrEqual($before->timemodified, (int) $after->timemodified);
    }

    /**
     * Test: update_fields ignores keys that are not whitelisted columns.
     */
    public function test_update_fields_ignores_unknown(): void {
        $this->resetAfterTest();
        global $DB;

        $id = $this->create_campaign();

        campaign_manager::update_fields($id, [
            'name' => 'Kept',
            'status' => 'ready', // Not whitelisted; must be ignored.
            'createdby' => 99999, // Not whitelisted; must be ignored.
        ]);

        $row = $DB->get_record('local_mailwhistle_campaigns', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('Kept', $row->name);
        $this->assertSame(campaign_manager::STATUS_DRAFT, $row->status);
        $this->assertNotEquals(99999, (int) $row->createdby);
    }

    /**
     * An invalid rename must not partially save the other supplied fields.
     */
    public function test_update_fields_rejects_blank_names_without_writes(): void {
        global $DB;
        $this->resetAfterTest();
        $id = $this->create_campaign();
        $before = $DB->get_record('local_mailwhistle_campaigns', ['id' => $id], '*', MUST_EXIST);

        foreach (['', '   ', "\t\r\n", null] as $name) {
            $writes = $DB->perf_get_writes();
            try {
                campaign_manager::update_fields($id, ['name' => $name, 'subject' => 'Changed subject']);
                $this->fail('A blank campaign name must be rejected.');
            } catch (\invalid_parameter_exception $exception) {
                $this->assertSame('Campaign name must not be empty.', $exception->debuginfo);
            }

            $this->assertSame($writes, $DB->perf_get_writes());
            $after = $DB->get_record('local_mailwhistle_campaigns', ['id' => $id], '*', MUST_EXIST);
            $this->assertEquals($before, $after);
        }
    }

    /**
     * Valid names are retained, including the nonempty name "0".
     */
    public function test_update_fields_preserves_valid_names(): void {
        global $DB;
        $this->resetAfterTest();
        $id = $this->create_campaign();

        foreach (['0', ' Padded name '] as $name) {
            campaign_manager::update_fields($id, ['name' => $name]);
            $this->assertSame($name, $DB->get_field('local_mailwhistle_campaigns', 'name', ['id' => $id]));
        }
    }

    /**
     * Updates without a name can still edit legacy campaigns with blank names.
     */
    public function test_update_fields_without_name_updates_legacy_campaign(): void {
        global $DB;
        $this->resetAfterTest();
        $id = $this->create_campaign();
        $DB->set_field('local_mailwhistle_campaigns', 'name', '', ['id' => $id]);

        campaign_manager::update_fields($id, ['subject' => 'Updated subject']);

        $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('', $campaign->name);
        $this->assertSame('Updated subject', $campaign->subject);
    }

    /**
     * Attachment filenames are stored as JSON and ignore unsafe names.
     */
    public function test_encode_decode_attachments(): void {
        $this->resetAfterTest();
        global $DB;

        $json = campaign_manager::encode_attachments(['handbook.pdf', '../etc/passwd', '', 'handbook.pdf']);
        $this->assertSame(['handbook.pdf'], campaign_manager::decode_attachments($json));
        $this->assertSame([], campaign_manager::decode_attachments(null));
        $this->assertSame([], campaign_manager::decode_attachments('not-json'));

        $id = $this->create_campaign();
        campaign_manager::update_fields($id, ['attachmentsjson' => $json]);
        $row = $DB->get_record('local_mailwhistle_campaigns', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame($json, $row->attachmentsjson);
    }

    /**
     * Test: is_complete is false while any required part is missing and true
     * once name, subject, body and an audience tag are all present.
     */
    public function test_is_complete_gate(): void {
        $this->resetAfterTest();

        $id = $this->create_campaign();
        $this->assertFalse(campaign_manager::is_complete($id));

        campaign_manager::update_fields($id, ['subject' => 'Subject']);
        $this->assertFalse(campaign_manager::is_complete($id));

        campaign_manager::update_fields($id, ['bodyhtml' => '<p>Hi</p>']);
        $this->assertFalse(campaign_manager::is_complete($id));

        $tag = tag_manager::create_tag('Alpha');
        audience_manager::set_campaign_tags($id, [$tag]);
        $user = $this->getDataGenerator()->create_user();
        tag_manager::assign_tag($tag, $user->id);
        $this->assertTrue(campaign_manager::is_complete($id));
    }

    /**
     * Test: mark_complete moves a complete campaign to ready.
     */
    public function test_mark_complete_sets_ready(): void {
        $this->resetAfterTest();
        global $DB;

        $id = $this->create_campaign();
        campaign_manager::update_fields($id, ['subject' => 'Subject', 'bodyhtml' => '<p>Hi</p>']);
        $tag = tag_manager::create_tag('Alpha');
        audience_manager::set_campaign_tags($id, [$tag]);

        $user = $this->getDataGenerator()->create_user();
        tag_manager::assign_tag($tag, $user->id);
        campaign_manager::mark_complete($id);

        $row = $DB->get_record('local_mailwhistle_campaigns', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(campaign_manager::STATUS_READY, $row->status);
    }

    /**
     * Test: mark_complete throws when the campaign is not complete.
     */
    public function test_mark_complete_throws_when_incomplete(): void {
        $this->resetAfterTest();

        $id = $this->create_campaign();

        $this->expectException(\moodle_exception::class);
        campaign_manager::mark_complete($id);
    }

    /**
     * set_templateid stores a template id and clears it when passed 0.
     */
    public function test_set_templateid(): void {
        $this->resetAfterTest();
        global $DB;

        $id = $this->create_campaign();
        campaign_manager::set_templateid($id, 7);
        $row = $DB->get_record('local_mailwhistle_campaigns', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(7, (int) $row->templateid);

        campaign_manager::set_templateid($id, 0);
        $row = $DB->get_record('local_mailwhistle_campaigns', ['id' => $id], '*', MUST_EXIST);
        $this->assertEmpty($row->templateid);
    }

    /**
     * Empty rich-text markup cannot satisfy readiness, while image-only mail can.
     *
     * @dataProvider body_content_provider
     * @param string $html HTML body.
     * @param string $text Plain body.
     * @param bool $expected Whether useful content exists.
     */
    public function test_meaningful_body(string $html, string $text, bool $expected): void {
        $this->assertSame($expected, campaign_manager::has_meaningful_body($html, $text));
    }

    /**
     * Meaningful and empty message representations.
     *
     * @return array
     */
    public static function body_content_provider(): array {
        return [
            'empty' => ['', '', false],
            'editor paragraph' => ['<p><br></p>', '', false],
            'nonbreaking spaces' => ['<p>&nbsp; &#160; &#8203;</p>', '', false],
            'document shell' => ['<html><head><style>body {color:red}</style></head><body></body></html>', '', false],
            'script only' => ['<script>alert(1)</script>', '', false],
            'image without source' => ['<img alt="">', '', false],
            'image only' => ['<p><img src="https://example.test/news.png" alt="News"></p>', '', true],
            'ordinary text' => ['<p>Hello</p>', '', true],
            'zero text' => ['<p>0</p>', '', true],
            'plain text' => ['', "Hello\nEveryone", true],
        ];
    }

    /**
     * Readiness counts each eligible person once and excludes unavailable recipients.
     */
    public function test_readiness_requires_eligible_recipients(): void {
        global $DB;
        $this->resetAfterTest();
        $id = $this->create_campaign();
        campaign_manager::update_fields($id, ['subject' => 'News', 'bodyhtml' => '<p>Hello</p>']);
        $first = tag_manager::create_tag('First');
        $second = tag_manager::create_tag('Second');
        audience_manager::set_campaign_tags($id, [$first, $second]);
        $suspended = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $deleted = $this->getDataGenerator()->create_user();
        $unsubscribed = $this->getDataGenerator()->create_user();
        foreach ([$suspended, $deleted, $unsubscribed] as $user) {
            tag_manager::assign_tag($first, $user->id);
        }
        delete_user($deleted);
        $DB->insert_record('local_mailwhistle_unsubscribes', (object) [
            'userid' => $unsubscribed->id,
            'email' => $unsubscribed->email,
            'scope' => 'global',
            'scopeid' => 0,
            'timecreated' => time(),
        ]);
        $readiness = campaign_manager::get_readiness($id);
        $this->assertSame(0, $readiness['recipientcount']);
        $this->assertContains(get_string('campaignemptyaudience', 'local_mailwhistle'), $readiness['errors']);
        $this->assertFalse(campaign_manager::is_complete($id));
        try {
            campaign_manager::mark_complete($id);
            $this->fail('An empty eligible audience must not become ready.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('campaignincomplete', $exception->errorcode);
        }
        $active = $this->getDataGenerator()->create_user();
        tag_manager::assign_tag($first, $active->id);
        tag_manager::assign_tag($second, $active->id);
        $readiness = campaign_manager::get_readiness($id);
        $this->assertSame(1, $readiness['recipientcount']);
        $this->assertSame([], $readiness['errors']);
    }

    /**
     * Stale forms cannot write fields, template identity or audience after a state change.
     *
     * @dataProvider nondraft_status_provider
     * @param string $status Current state.
     */
    public function test_nondraft_writes_are_rejected(string $status): void {
        global $DB;
        $this->resetAfterTest();
        $id = $this->create_campaign();
        $tag = tag_manager::create_tag('Original');
        audience_manager::set_campaign_tags($id, [$tag]);
        $DB->set_field('local_mailwhistle_campaigns', 'status', $status, ['id' => $id]);
        $before = $DB->get_record('local_mailwhistle_campaigns', ['id' => $id]);
        $writes = [
            static fn() => campaign_manager::update_fields($id, ['subject' => 'Stale body'], 42),
            static fn() => campaign_manager::set_templateid($id, 42),
            static fn() => campaign_manager::with_draft_lock($id, static function () use ($id): void {
                audience_manager::set_campaign_tags($id, []);
            }),
            static fn() => campaign_manager::mark_complete($id),
        ];
        foreach ($writes as $write) {
            try {
                $write();
                $this->fail('A non-draft campaign must be immutable.');
            } catch (\moodle_exception $exception) {
                $this->assertSame('campaignnoteditable', $exception->errorcode);
            }
            $this->assertEquals($before, $DB->get_record('local_mailwhistle_campaigns', ['id' => $id]));
            $this->assertSame([$tag], audience_manager::get_campaign_tagids($id));
        }
    }

    /**
     * States whose delivery content must stay immutable.
     *
     * @return array
     */
    public static function nondraft_status_provider(): array {
        return [
            'ready' => ['ready'],
            'sending' => ['sending'],
            'sent' => ['sent'],
            'mixed outcome' => ['completedwitherrors'],
            'failed' => ['failed'],
        ];
    }

    /**
     * Reopening is available for ready campaigns only and preserves saved content.
     */
    public function test_return_to_draft_only_allows_ready(): void {
        global $DB;
        $this->resetAfterTest();
        $id = $this->create_campaign();
        campaign_manager::update_fields($id, ['subject' => 'Saved subject', 'bodyhtml' => '<p>Saved body</p>']);
        $DB->set_field('local_mailwhistle_campaigns', 'status', 'ready', ['id' => $id]);
        campaign_manager::return_to_draft($id);
        $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $id]);
        $this->assertSame('draft', $campaign->status);
        $this->assertSame('Saved subject', $campaign->subject);
        $this->assertSame('<p>Saved body</p>', $campaign->bodyhtml);
        foreach (['draft', 'sending', 'sent', 'completedwitherrors', 'failed'] as $status) {
            $DB->set_field('local_mailwhistle_campaigns', 'status', $status, ['id' => $id]);
            try {
                campaign_manager::return_to_draft($id);
                $this->fail('Only ready campaigns can be reopened.');
            } catch (\moodle_exception $exception) {
                $this->assertSame('campaigncannotreopen', $exception->errorcode);
            }
            $this->assertSame($status, $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $id]));
        }
    }
}
