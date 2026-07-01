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

namespace local_mailwhistle\tests;

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
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\campaign_manager
 */
class campaign_manager_test extends \advanced_testcase {
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
}
