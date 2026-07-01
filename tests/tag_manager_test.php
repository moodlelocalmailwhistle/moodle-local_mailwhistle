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

use local_mailwhistle\manager\tag_manager;

/**
 * Unit tests for the tag_manager class.
 *
 * Covers tag creation, normalization, uniqueness enforcement, idempotent
 * assignment, bulk assignment counts, cascade delete, and the per-page
 * tag map fetch.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\tag_manager
 */
class tag_manager_test extends \advanced_testcase {
    /**
     * Test: create_tag normalises the name and stores a shortname.
     */
    public function test_create_tag_normalises(): void {
        $this->resetAfterTest();
        global $DB;

        $id = tag_manager::create_tag('Newsletter');
        $row = $DB->get_record('local_mailwhistle_tag', ['id' => $id], '*', MUST_EXIST);

        $this->assertSame('Newsletter', $row->name);
        $this->assertSame('newsletter', $row->shortname);
    }

    /**
     * Test: creating 'Newsletter', 'newsletter', and ' Newsletter ' all yield
     * a single row in local_mailwhistle_tag (unique shortname enforcement).
     */
    public function test_create_tag_uniqueness(): void {
        $this->resetAfterTest();
        global $DB;

        $id1 = tag_manager::create_tag('Newsletter');
        $id2 = tag_manager::create_tag('newsletter');
        $id3 = tag_manager::create_tag(' Newsletter ');

        // All three calls must return the same id.
        $this->assertSame($id1, $id2);
        $this->assertSame($id1, $id3);

        // Exactly one row in the table for this shortname.
        $count = $DB->count_records('local_mailwhistle_tag', ['shortname' => 'newsletter']);
        $this->assertSame(1, $count);
    }

    /**
     * Test: get_or_create_tag returns the same id on a second call.
     */
    public function test_get_or_create_tag_idempotent(): void {
        $this->resetAfterTest();

        $id1 = tag_manager::get_or_create_tag('Region NL');
        $id2 = tag_manager::get_or_create_tag('region nl'); // Different case.

        $this->assertSame($id1, $id2);
    }

    /**
     * Test: assign_tag is idempotent — assigning the same (tag, user) twice
     * produces exactly one row in local_mailwhistle_tag_assign.
     */
    public function test_assign_tag_idempotent(): void {
        $this->resetAfterTest();
        global $DB;

        $tagid  = tag_manager::create_tag('Beta');
        $user   = $this->getDataGenerator()->create_user();

        tag_manager::assign_tag($tagid, $user->id);
        tag_manager::assign_tag($tagid, $user->id); // Duplicate — should not throw.

        $count = $DB->count_records('local_mailwhistle_tag_assign', [
            'tagid'  => $tagid,
            'userid' => $user->id,
        ]);
        $this->assertSame(1, $count);
    }

    /**
     * Test: assign_tag_to_users with 10 users where 3 are pre-tagged.
     *
     * After the call: 10 total assignments, return value = 7 (newly added).
     */
    public function test_assign_tag_to_users_count(): void {
        $this->resetAfterTest();
        global $DB;

        $tagid = tag_manager::create_tag('Batch');

        $users = [];
        for ($i = 0; $i < 10; $i++) {
            $users[] = $this->getDataGenerator()->create_user();
        }

        // Pre-assign 3 users.
        for ($i = 0; $i < 3; $i++) {
            tag_manager::assign_tag($tagid, $users[$i]->id);
        }

        $userids = array_column($users, 'id');
        $added   = tag_manager::assign_tag_to_users($tagid, $userids);

        // Return value = newly added (the other 7).
        $this->assertSame(7, $added);

        // Total assignments for this tag = 10.
        $total = $DB->count_records('local_mailwhistle_tag_assign', ['tagid' => $tagid]);
        $this->assertSame(10, $total);
    }

    /**
     * Test: delete_tag cascades — removes all assignments and the tag row itself.
     */
    public function test_delete_tag_cascades(): void {
        $this->resetAfterTest();
        global $DB;

        $tagid = tag_manager::create_tag('ToDelete');
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        tag_manager::assign_tag($tagid, $user1->id);
        tag_manager::assign_tag($tagid, $user2->id);

        // Confirm assignments exist before deletion.
        $this->assertSame(2, $DB->count_records('local_mailwhistle_tag_assign', ['tagid' => $tagid]));

        tag_manager::delete_tag($tagid);

        // Tag definition row is gone.
        $this->assertSame(0, $DB->count_records('local_mailwhistle_tag', ['id' => $tagid]));

        // All assignment rows for this tag are gone.
        $this->assertSame(0, $DB->count_records('local_mailwhistle_tag_assign', ['tagid' => $tagid]));
    }

    /**
     * Test: get_tags_for_users returns the correct userid => tag name map.
     */
    public function test_get_tags_for_users_returns_correct_map(): void {
        $this->resetAfterTest();

        $tag1  = tag_manager::create_tag('Alpha');
        $tag2  = tag_manager::create_tag('Beta');
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user(); // Untagged.

        tag_manager::assign_tag($tag1, $user1->id);
        tag_manager::assign_tag($tag2, $user1->id);
        tag_manager::assign_tag($tag1, $user2->id);

        $map = tag_manager::get_tags_for_users([$user1->id, $user2->id, $user3->id]);

        // User1 has two tags (Alpha, Beta — sorted).
        $this->assertArrayHasKey($user1->id, $map);
        $this->assertEqualsCanonicalizing(['Alpha', 'Beta'], $map[$user1->id]);

        // User2 has one tag.
        $this->assertArrayHasKey($user2->id, $map);
        $this->assertSame(['Alpha'], $map[$user2->id]);

        // User3 (untagged) not present in map.
        $this->assertArrayNotHasKey($user3->id, $map);
    }

    /**
     * Test: get_tags_for_users with an empty array returns an empty array.
     */
    public function test_get_tags_for_users_empty_input(): void {
        $this->resetAfterTest();

        $map = tag_manager::get_tags_for_users([]);
        $this->assertSame([], $map);
    }

    /**
     * Test: normalize collapses internal whitespace and lower-cases.
     */
    public function test_normalize(): void {
        $this->resetAfterTest();

        $this->assertSame('region nl', tag_manager::normalize('  Region   NL  '));
        $this->assertSame('newsletter', tag_manager::normalize('NEWSLETTER'));
    }
}
