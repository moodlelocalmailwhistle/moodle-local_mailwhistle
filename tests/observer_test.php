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

use local_mailwhistle\manager\tag_manager;

/**
 * Tests for the local_mailwhistle event observer.
 *
 * Verifies that when a Moodle user is deleted the observer removes all
 * local_mailwhistle_tag_assign rows for that user (D6 / AC#11).
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Test: deleting a user via delete_user() triggers the user_deleted event,
     * which the observer catches to remove that user's tag-assign rows.
     */
    public function test_user_deleted_removes_tag_assignments(): void {
        $this->resetAfterTest();
        global $DB;

        // Create the user to be deleted and a bystander who keeps their tags.
        $user      = $this->getDataGenerator()->create_user();
        $bystander = $this->getDataGenerator()->create_user();

        $tagid = tag_manager::create_tag('Observer');
        tag_manager::assign_tag($tagid, $user->id);
        tag_manager::assign_tag($tagid, $bystander->id);

        // Confirm both assignments exist.
        $this->assertSame(2, $DB->count_records('local_mailwhistle_tag_assign', ['tagid' => $tagid]));

        // Delete the user — this triggers \core\event\user_deleted which the
        // observer listens to and removes tag-assign rows.
        delete_user($user);

        // The deleted user's assignment must be gone.
        $this->assertSame(
            0,
            $DB->count_records('local_mailwhistle_tag_assign', ['userid' => $user->id]),
            'Tag assignments for the deleted user must be removed by the observer.'
        );

        // The bystander's assignment must be untouched.
        $this->assertSame(
            1,
            $DB->count_records('local_mailwhistle_tag_assign', ['userid' => $bystander->id]),
            'Tag assignments for other users must not be affected.'
        );
    }

    /**
     * Test: deleting a user who has assignments across multiple tags removes
     * all of their assignment rows regardless of which tag.
     */
    public function test_user_deleted_removes_all_tag_assignments(): void {
        $this->resetAfterTest();
        global $DB;

        $user  = $this->getDataGenerator()->create_user();
        $tag1  = tag_manager::create_tag('Multi One');
        $tag2  = tag_manager::create_tag('Multi Two');
        $tag3  = tag_manager::create_tag('Multi Three');

        tag_manager::assign_tag($tag1, $user->id);
        tag_manager::assign_tag($tag2, $user->id);
        tag_manager::assign_tag($tag3, $user->id);

        // Confirm 3 assignments.
        $this->assertSame(3, $DB->count_records('local_mailwhistle_tag_assign', ['userid' => $user->id]));

        delete_user($user);

        // All 3 must be gone.
        $this->assertSame(
            0,
            $DB->count_records('local_mailwhistle_tag_assign', ['userid' => $user->id]),
            'All tag assignments for the deleted user must be removed.'
        );

        // The tag definitions themselves must still exist.
        $this->assertSame(1, $DB->count_records('local_mailwhistle_tag', ['id' => $tag1]));
        $this->assertSame(1, $DB->count_records('local_mailwhistle_tag', ['id' => $tag2]));
        $this->assertSame(1, $DB->count_records('local_mailwhistle_tag', ['id' => $tag3]));
    }

    /**
     * Test: deleting a user who has NO tag assignments is handled gracefully
     * (no exception, no side effects).
     */
    public function test_user_deleted_no_assignments_no_error(): void {
        $this->resetAfterTest();
        global $DB;

        $user = $this->getDataGenerator()->create_user();

        // No assignments — delete should not throw.
        delete_user($user);

        $this->assertSame(0, $DB->count_records('local_mailwhistle_tag_assign', ['userid' => $user->id]));
    }
}
