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
use local_mailwhistle\manager\tag_manager;

/**
 * Unit tests for the audience_manager class.
 *
 * Covers the set/get roundtrip of a campaign's tag audience, idempotent
 * replacement, clearing, invalid-id rejection, and the stored row shape.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\audience_manager
 */
final class audience_manager_test extends \advanced_testcase {
    /**
     * Insert a minimal campaign row and return its id.
     *
     * @return int The new campaign id.
     */
    private function create_campaign(): int {
        global $DB, $USER;
        $now = time();
        return (int) $DB->insert_record('local_mailwhistle_campaigns', (object) [
            'name' => 'Test campaign',
            'subject' => 'Subject',
            'createdby' => (int) $USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Test: set then get returns the same tag ids.
     */
    public function test_set_and_get_roundtrip(): void {
        $this->resetAfterTest();

        $campaignid = $this->create_campaign();
        $tag1 = tag_manager::create_tag('Alpha');
        $tag2 = tag_manager::create_tag('Beta');

        audience_manager::set_campaign_tags($campaignid, [$tag1, $tag2]);

        $got = audience_manager::get_campaign_tagids($campaignid);
        sort($got);
        $expected = [$tag1, $tag2];
        sort($expected);
        $this->assertSame($expected, $got);
    }

    /**
     * Test: re-setting replaces the tag set rather than duplicating rows.
     */
    public function test_set_replaces(): void {
        $this->resetAfterTest();
        global $DB;

        $campaignid = $this->create_campaign();
        $tag1 = tag_manager::create_tag('Alpha');
        $tag2 = tag_manager::create_tag('Beta');
        $tag3 = tag_manager::create_tag('Gamma');

        audience_manager::set_campaign_tags($campaignid, [$tag1, $tag2]);
        audience_manager::set_campaign_tags($campaignid, [$tag3]);

        $this->assertSame([$tag3], audience_manager::get_campaign_tagids($campaignid));
        $this->assertSame(1, $DB->count_records('local_mailwhistle_audrules', [
            'campaignid' => $campaignid,
            'type' => audience_manager::TYPE_TAG,
        ]));
    }

    /**
     * Test: setting an empty array clears the campaign's tag audience.
     */
    public function test_set_empty_clears(): void {
        $this->resetAfterTest();

        $campaignid = $this->create_campaign();
        $tag1 = tag_manager::create_tag('Alpha');

        audience_manager::set_campaign_tags($campaignid, [$tag1]);
        audience_manager::set_campaign_tags($campaignid, []);

        $this->assertSame([], audience_manager::get_campaign_tagids($campaignid));
    }

    /**
     * Test: a non-existent tag id is not written.
     */
    public function test_invalid_tag_ignored(): void {
        $this->resetAfterTest();

        $campaignid = $this->create_campaign();
        $tag1 = tag_manager::create_tag('Alpha');

        audience_manager::set_campaign_tags($campaignid, [$tag1, 999999]);

        $this->assertSame([$tag1], audience_manager::get_campaign_tagids($campaignid));
    }

    /**
     * Test: stored rows use the tag type and the correct campaign id.
     */
    public function test_row_shape(): void {
        $this->resetAfterTest();
        global $DB;

        $campaignid = $this->create_campaign();
        $tag1 = tag_manager::create_tag('Alpha');

        audience_manager::set_campaign_tags($campaignid, [$tag1]);

        $row = $DB->get_record('local_mailwhistle_audrules', [
            'campaignid' => $campaignid,
            'type' => audience_manager::TYPE_TAG,
        ], '*', MUST_EXIST);
        $this->assertSame($tag1, (int) $row->instanceid);
        $this->assertSame($campaignid, (int) $row->campaignid);
    }
}
