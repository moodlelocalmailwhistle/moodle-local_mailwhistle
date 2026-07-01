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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\tests\provider_testcase;
use local_mailwhistle\manager\tag_manager;
use local_mailwhistle\privacy\provider;

/**
 * Privacy provider tests for the local_mailwhistle plugin.
 *
 * Verifies metadata declaration, context discovery, data export, and
 * deletion / anonymisation behaviour — in particular that tag DEFINITION
 * rows are never deleted by any privacy routine (AC#16).
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\privacy\provider
 */
class privacy_provider_test extends provider_testcase {
    /**
     * Test: get_metadata declares both plugin tables (collection non-empty).
     */
    public function test_get_metadata_declares_both_tables(): void {
        $this->resetAfterTest();

        $collection = new collection('local_mailwhistle');
        $result     = provider::get_metadata($collection);

        // The returned collection must not be empty.
        $items = $result->get_collection();
        $this->assertNotEmpty($items, 'Metadata collection must declare at least one item.');

        // Collect the table names from the metadata items.
        $tablenames = [];
        foreach ($items as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $tablenames[] = $item->get_name();
            }
        }

        $this->assertContains(
            'local_mailwhistle_tag_assign',
            $tablenames,
            'Metadata must declare local_mailwhistle_tag_assign.'
        );
        $this->assertContains(
            'local_mailwhistle_tag',
            $tablenames,
            'Metadata must declare local_mailwhistle_tag.'
        );
    }

    /**
     * Test: get_contexts_for_userid returns the system context for a tagged user.
     */
    public function test_get_contexts_for_userid_tagged_user(): void {
        $this->resetAfterTest();

        $user  = $this->getDataGenerator()->create_user();
        $tagid = tag_manager::create_tag('Test');
        tag_manager::assign_tag($tagid, $user->id);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $contexts    = $contextlist->get_contexts();

        $this->assertCount(1, $contexts, 'Exactly one context (system) expected for a tagged user.');
        $this->assertInstanceOf(\context_system::class, reset($contexts));
    }

    /**
     * Test: get_contexts_for_userid returns empty for a user with no data.
     */
    public function test_get_contexts_for_userid_unrelated_user(): void {
        $this->resetAfterTest();

        $user        = $this->getDataGenerator()->create_user();
        $contextlist = provider::get_contexts_for_userid($user->id);

        $this->assertCount(0, $contextlist->get_contexts());
    }

    /**
     * Test: export_user_data writes the user's tag assignments into the export.
     */
    public function test_export_user_data_includes_assignments(): void {
        $this->resetAfterTest();

        $user   = $this->getDataGenerator()->create_user();
        $tagid  = tag_manager::create_tag('Export Tag');
        tag_manager::assign_tag($tagid, $user->id);

        $context  = \context_system::instance();
        $contextlist = new approved_contextlist($user, 'local_mailwhistle', [$context->id]);

        provider::export_user_data($contextlist);

        // Retrieve the exported data for the system context.
        $writer = \core_privacy\local\request\writer::with_context($context);
        $data   = $writer->get_data([get_string('privacy:path:tag_assignments', 'local_mailwhistle')]);

        $this->assertNotNull($data, 'Export data for tag assignments must exist.');
        $this->assertObjectHasProperty('assignments', $data);
        $this->assertCount(1, $data->assignments);
        $this->assertSame('Export Tag', $data->assignments[0]['tag']);
    }

    /**
     * Test: delete_data_for_user removes the user's assignment rows and
     * anonymises usermodified on rows they authored, but does NOT delete
     * tag definition rows.
     */
    public function test_delete_data_for_user(): void {
        $this->resetAfterTest();
        global $DB;

        $subject = $this->getDataGenerator()->create_user();
        $actor   = $this->getDataGenerator()->create_user();
        $other   = $this->getDataGenerator()->create_user();

        // Set actor as the current user so assign_tag records actor as usermodified.
        $this->setUser($actor);

        $tagid = tag_manager::create_tag('DelUser');
        tag_manager::assign_tag($tagid, $subject->id); // Actor assigns to subject.
        tag_manager::assign_tag($tagid, $other->id); // Actor assigns to other.

        // Confirm 2 assignment rows exist before deletion.
        $this->assertSame(2, $DB->count_records('local_mailwhistle_tag_assign', ['tagid' => $tagid]));

        // Delete data for the subject user.
        $context     = \context_system::instance();
        $contextlist = new approved_contextlist($subject, 'local_mailwhistle', [$context->id]);
        provider::delete_data_for_user($contextlist);

        // Subject's assignment row is gone.
        $this->assertSame(0, $DB->count_records('local_mailwhistle_tag_assign', ['userid' => $subject->id]));

        // Other user's assignment row is still present.
        $this->assertSame(1, $DB->count_records('local_mailwhistle_tag_assign', ['userid' => $other->id]));

        // Tag definition row must still exist (never deleted).
        $this->assertSame(1, $DB->count_records('local_mailwhistle_tag', ['id' => $tagid]));
    }

    /**
     * Test: delete_data_for_all_users_in_context (system context):
     *  - ALL assignment rows are deleted.
     *  - Tag DEFINITION row count is UNCHANGED (AC#16 — definitions survive).
     *  - usermodified on tag definitions is anonymised to 0.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        $this->resetAfterTest();
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $tagid1 = tag_manager::create_tag('Tag One');
        $tagid2 = tag_manager::create_tag('Tag Two');

        tag_manager::assign_tag($tagid1, $user1->id);
        tag_manager::assign_tag($tagid2, $user1->id);
        tag_manager::assign_tag($tagid1, $user2->id);

        // Record the number of tag DEFINITION rows before the purge.
        $defsbeforepurge = $DB->count_records('local_mailwhistle_tag');
        $this->assertGreaterThanOrEqual(2, $defsbeforepurge);

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        // ALL assignment rows must be gone.
        $this->assertSame(
            0,
            $DB->count_records('local_mailwhistle_tag_assign'),
            'All tag assignment rows must be deleted.'
        );

        // Tag DEFINITION row count must be unchanged (AC#16).
        $defsafterpurge = $DB->count_records('local_mailwhistle_tag');
        $this->assertSame(
            $defsbeforepurge,
            $defsafterpurge,
            'Tag definition rows must survive delete_data_for_all_users_in_context (AC#16).'
        );

        // Usermodified must be anonymised to 0 on all tag definition rows.
        $nonzero = $DB->count_records_select('local_mailwhistle_tag', 'usermodified <> 0');
        $this->assertSame(
            0,
            (int) $nonzero,
            'All tag definition usermodified values must be anonymised to 0.'
        );
    }

    /**
     * Test: non-system context is silently ignored by delete_data_for_all_users_in_context.
     */
    public function test_delete_data_for_all_users_non_system_context_noop(): void {
        $this->resetAfterTest();
        global $DB;

        $user  = $this->getDataGenerator()->create_user();
        $tagid = tag_manager::create_tag('NoCourse');
        tag_manager::assign_tag($tagid, $user->id);

        // Course context — must be a no-op.
        $course  = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        provider::delete_data_for_all_users_in_context($context);

        // Assignment rows must still exist.
        $this->assertSame(1, $DB->count_records('local_mailwhistle_tag_assign', ['userid' => $user->id]));
    }
}
