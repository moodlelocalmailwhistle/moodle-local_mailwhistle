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

namespace local_mailwhistle\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the local_mailwhistle plugin.
 *
 * This plugin stores personal data in two tables:
 *  - {local_mailwhistle_tag_assign}: records which users have been assigned
 *    each audience tag, and who performed the assignment (usermodified).
 *  - {local_mailwhistle_tag}: tag definitions; usermodified records who
 *    created or last edited each tag definition.
 *
 * All personal data lives under the system context because audience tags are
 * site-wide (not course-scoped).
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin_provider {
    /**
     * Describe all personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_mailwhistle_tag_assign',
            [
                'tagid'        => 'privacy:metadata:local_mailwhistle_tag_assign:tagid',
                'userid'       => 'privacy:metadata:local_mailwhistle_tag_assign:userid',
                'usermodified' => 'privacy:metadata:local_mailwhistle_tag_assign:usermodified',
                'timecreated'  => 'privacy:metadata:local_mailwhistle_tag_assign:timecreated',
            ],
            'privacy:metadata:local_mailwhistle_tag_assign'
        );

        $collection->add_database_table(
            'local_mailwhistle_tag',
            [
                'name'         => 'privacy:metadata:local_mailwhistle_tag:name',
                'usermodified' => 'privacy:metadata:local_mailwhistle_tag:usermodified',
                'timecreated'  => 'privacy:metadata:local_mailwhistle_tag:timecreated',
                'timemodified' => 'privacy:metadata:local_mailwhistle_tag:timemodified',
            ],
            'privacy:metadata:local_mailwhistle_tag'
        );

        return $collection;
    }

    /**
     * Return the list of contexts that contain personal data for the given user.
     *
     * A user appears in our data if they:
     *  - are tagged (tag_assign.userid), or
     *  - authored/modified a tag assignment (tag_assign.usermodified), or
     *  - authored/modified a tag definition (tag.usermodified).
     *
     * All such data lives under the system context.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $hasdata = $DB->record_exists_select(
            'local_mailwhistle_tag_assign',
            'userid = :userid OR usermodified = :usermodified',
            ['userid' => $userid, 'usermodified' => $userid]
        );

        if (!$hasdata) {
            $hasdata = $DB->record_exists('local_mailwhistle_tag', ['usermodified' => $userid]);
        }

        if ($hasdata) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Populate the userlist with all users who have data in the given context.
     *
     * Only the system context is relevant for this plugin.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!($context instanceof \context_system)) {
            return;
        }

        // Users tagged.
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {local_mailwhistle_tag_assign}',
            []
        );

        // Users who performed assignments.
        $userlist->add_from_sql(
            'usermodified',
            'SELECT usermodified FROM {local_mailwhistle_tag_assign} WHERE usermodified <> 0',
            []
        );

        // Users who authored tag definitions.
        $userlist->add_from_sql(
            'usermodified',
            'SELECT usermodified FROM {local_mailwhistle_tag} WHERE usermodified <> 0',
            []
        );
    }

    /**
     * Export personal data for the user in the approved contexts.
     *
     * Exports the tag assignments where the user is the tagged subject, and
     * any tag definitions the user authored/modified.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof \context_system)) {
                continue;
            }

            // Export tag assignments where this user is the tagged subject.
            $assignments = $DB->get_records_sql(
                'SELECT ta.id, t.name AS tagname, ta.timecreated, ta.usermodified
                   FROM {local_mailwhistle_tag_assign} ta
                   JOIN {local_mailwhistle_tag} t ON t.id = ta.tagid
                  WHERE ta.userid = :userid',
                ['userid' => $user->id]
            );

            if ($assignments) {
                $data = [];
                foreach ($assignments as $row) {
                    $data[] = [
                        'tag'          => $row->tagname,
                        'timecreated'  => transform::datetime($row->timecreated),
                        'usermodified' => transform::user($row->usermodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:tag_assignments', 'local_mailwhistle')],
                    (object)['assignments' => $data]
                );
            }

            // Export tag definitions authored by this user.
            $tags = $DB->get_records('local_mailwhistle_tag', ['usermodified' => $user->id]);

            if ($tags) {
                $data = [];
                foreach ($tags as $row) {
                    $data[] = [
                        'name'         => $row->name,
                        'timecreated'  => transform::datetime($row->timecreated),
                        'timemodified' => transform::datetime($row->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:authored_tags', 'local_mailwhistle')],
                    (object)['tags' => $data]
                );
            }
        }
    }

    /**
     * Delete all personal data for all users in the given context.
     *
     * For the system context this deletes ALL tag assignment rows and
     * anonymises the usermodified field on tag definitions.
     *
     * Tag definition rows are NEVER deleted — they are shared site-wide
     * configuration; removing them would silently orphan data across all users.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!($context instanceof \context_system)) {
            return;
        }

        // Remove all assignment rows (personal data: who is tagged).
        $DB->delete_records('local_mailwhistle_tag_assign', []);

        // Anonymise author identity on tag definitions; the definitions themselves survive.
        $DB->set_field('local_mailwhistle_tag', 'usermodified', 0, []);
    }

    /**
     * Delete all personal data for the specified user.
     *
     * Removes the user's tag assignment rows (as tagged subject) and
     * anonymises any rows they authored in both tables.
     * Tag definitions are never deleted.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof \context_system)) {
                continue;
            }

            // Delete assignments where this user is the tagged subject.
            $DB->delete_records('local_mailwhistle_tag_assign', ['userid' => $user->id]);

            // Anonymise assignments this user authored (they performed the tagging).
            $DB->set_field('local_mailwhistle_tag_assign', 'usermodified', 0, ['usermodified' => $user->id]);

            // Anonymise tag definitions this user authored.
            $DB->set_field('local_mailwhistle_tag', 'usermodified', 0, ['usermodified' => $user->id]);
        }
    }

    /**
     * Delete personal data for a list of users in a given context.
     *
     * Required by core_userlist_provider. Applies the same logic as
     * delete_data_for_user but for a batch of users.
     * Tag definitions are never deleted.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if (!($context instanceof \context_system)) {
            return;
        }

        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete assignment rows for these users as tagged subjects.
        $DB->delete_records_select('local_mailwhistle_tag_assign', "userid $insql", $inparams);

        // Anonymise assignment rows these users authored.
        $DB->set_field_select('local_mailwhistle_tag_assign', 'usermodified', 0, "usermodified $insql", $inparams);

        // Anonymise tag definitions these users authored.
        $DB->set_field_select('local_mailwhistle_tag', 'usermodified', 0, "usermodified $insql", $inparams);
    }
}
