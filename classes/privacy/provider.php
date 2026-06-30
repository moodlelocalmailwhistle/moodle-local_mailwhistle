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

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the local_mailwhistle plugin.
 *
 * The plugin stores personal data in several tables, all under the system
 * context:
 *  - {local_mailwhistle_recipients}: per-recipient send state for campaigns.
 *  - {local_mailwhistle_unsubscribes}: unsubscribe records keyed on user id.
 *  - {local_mailwhistle_tag_assign}: which users have been assigned each
 *    audience tag, and who performed the assignment (usermodified).
 *  - {local_mailwhistle_tag}: tag definitions; usermodified records who created
 *    or last edited each definition.
 *
 * The campaigns table only records authorship via createdby, retained as an
 * audit field and not treated as deletable personal data. Tag definitions are
 * shared site-wide configuration and are never deleted: instead the author
 * identity (usermodified) is anonymised.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements core_userlist_provider, metadata_provider, request_provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_mailwhistle_recipients',
            [
                'userid' => 'privacy:metadata:local_mailwhistle_recipients:userid',
                'email' => 'privacy:metadata:local_mailwhistle_recipients:email',
                'firstname' => 'privacy:metadata:local_mailwhistle_recipients:firstname',
                'lastname' => 'privacy:metadata:local_mailwhistle_recipients:lastname',
                'status' => 'privacy:metadata:local_mailwhistle_recipients:status',
                'timesent' => 'privacy:metadata:local_mailwhistle_recipients:timesent',
            ],
            'privacy:metadata:local_mailwhistle_recipients'
        );

        $collection->add_database_table(
            'local_mailwhistle_unsubscribes',
            [
                'userid' => 'privacy:metadata:local_mailwhistle_unsubscribes:userid',
                'email' => 'privacy:metadata:local_mailwhistle_unsubscribes:email',
                'timecreated' => 'privacy:metadata:local_mailwhistle_unsubscribes:timecreated',
            ],
            'privacy:metadata:local_mailwhistle_unsubscribes'
        );

        $collection->add_database_table(
            'local_mailwhistle_tag_assign',
            [
                'tagid' => 'privacy:metadata:local_mailwhistle_tag_assign:tagid',
                'userid' => 'privacy:metadata:local_mailwhistle_tag_assign:userid',
                'usermodified' => 'privacy:metadata:local_mailwhistle_tag_assign:usermodified',
                'timecreated' => 'privacy:metadata:local_mailwhistle_tag_assign:timecreated',
            ],
            'privacy:metadata:local_mailwhistle_tag_assign'
        );

        $collection->add_database_table(
            'local_mailwhistle_tag',
            [
                'name' => 'privacy:metadata:local_mailwhistle_tag:name',
                'usermodified' => 'privacy:metadata:local_mailwhistle_tag:usermodified',
                'timecreated' => 'privacy:metadata:local_mailwhistle_tag:timecreated',
                'timemodified' => 'privacy:metadata:local_mailwhistle_tag:timemodified',
            ],
            'privacy:metadata:local_mailwhistle_tag'
        );

        return $collection;
    }

    /**
     * Return the contexts that contain personal data for the given user.
     *
     * A user appears in the plugin's data if they are a campaign recipient, have
     * an unsubscribe record, are tagged, performed a tag assignment, or authored
     * a tag definition. All such data lives under the system context.
     *
     * @param int $userid The user to search.
     * @return contextlist The contexts containing the user's data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $hasdata = $DB->record_exists('local_mailwhistle_recipients', ['userid' => $userid])
            || $DB->record_exists('local_mailwhistle_unsubscribes', ['userid' => $userid])
            || $DB->record_exists_select(
                'local_mailwhistle_tag_assign',
                'userid = :userid OR usermodified = :usermodified',
                ['userid' => $userid, 'usermodified' => $userid]
            )
            || $DB->record_exists('local_mailwhistle_tag', ['usermodified' => $userid]);

        if ($hasdata) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Add the users who have data in the given context to the userlist.
     *
     * Only the system context is relevant for this plugin.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof context_system) {
            return;
        }

        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_mailwhistle_recipients}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_mailwhistle_unsubscribes}', []);

        // Users who are tagged.
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_mailwhistle_tag_assign}', []);

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
     * Export all personal data for the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and target user.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_system) {
                continue;
            }

            $recipients = $DB->get_records('local_mailwhistle_recipients', ['userid' => $user->id]);
            foreach ($recipients as $recipient) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_mailwhistle'), 'recipients', $recipient->id],
                    (object) [
                        'email' => $recipient->email,
                        'firstname' => $recipient->firstname,
                        'lastname' => $recipient->lastname,
                        'status' => $recipient->status,
                        'timesent' => $recipient->timesent ? transform::datetime($recipient->timesent) : null,
                    ]
                );
            }

            $unsubscribes = $DB->get_records('local_mailwhistle_unsubscribes', ['userid' => $user->id]);
            foreach ($unsubscribes as $unsubscribe) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_mailwhistle'), 'unsubscribes', $unsubscribe->id],
                    (object) [
                        'email' => $unsubscribe->email,
                        'scope' => $unsubscribe->scope,
                        'timecreated' => transform::datetime($unsubscribe->timecreated),
                    ]
                );
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
                        'tag' => $row->tagname,
                        'timecreated' => transform::datetime($row->timecreated),
                        'usermodified' => transform::user($row->usermodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:tag_assignments', 'local_mailwhistle')],
                    (object) ['assignments' => $data]
                );
            }

            // Export tag definitions authored by this user.
            $tags = $DB->get_records('local_mailwhistle_tag', ['usermodified' => $user->id]);

            if ($tags) {
                $data = [];
                foreach ($tags as $row) {
                    $data[] = [
                        'name' => $row->name,
                        'timecreated' => transform::datetime($row->timecreated),
                        'timemodified' => transform::datetime($row->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:authored_tags', 'local_mailwhistle')],
                    (object) ['tags' => $data]
                );
            }
        }
    }

    /**
     * Delete all personal data for all users in the given context.
     *
     * For the system context this deletes all recipient, unsubscribe, and tag
     * assignment rows, and anonymises the usermodified author field on tag
     * definitions. Tag definition rows are NEVER deleted — they are shared
     * site-wide configuration; removing them would silently orphan data across
     * all users.
     *
     * @param context $context The context to delete data in.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_system) {
            return;
        }

        $DB->delete_records('local_mailwhistle_recipients');
        $DB->delete_records('local_mailwhistle_unsubscribes');

        // Remove all assignment rows (personal data: who is tagged).
        $DB->delete_records('local_mailwhistle_tag_assign');

        // Anonymise author identity on tag definitions; the definitions themselves survive.
        $DB->set_field('local_mailwhistle_tag', 'usermodified', 0, []);
    }

    /**
     * Delete personal data for the user in the approved contexts.
     *
     * Removes the user's recipient, unsubscribe, and tag-assignment rows, and
     * anonymises any rows the user authored in the tag tables. Tag definitions
     * are never deleted.
     *
     * @param approved_contextlist $contextlist The approved contexts and target user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_system) {
                continue;
            }

            $DB->delete_records('local_mailwhistle_recipients', ['userid' => $user->id]);
            $DB->delete_records('local_mailwhistle_unsubscribes', ['userid' => $user->id]);

            // Delete assignments where this user is the tagged subject.
            $DB->delete_records('local_mailwhistle_tag_assign', ['userid' => $user->id]);

            // Anonymise assignments this user authored (they performed the tagging).
            $DB->set_field('local_mailwhistle_tag_assign', 'usermodified', 0, ['usermodified' => $user->id]);

            // Anonymise tag definitions this user authored.
            $DB->set_field('local_mailwhistle_tag', 'usermodified', 0, ['usermodified' => $user->id]);
        }
    }

    /**
     * Delete personal data for the approved users in the given context.
     *
     * @param approved_userlist $userlist The approved users and context.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof context_system) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $DB->delete_records_select('local_mailwhistle_recipients', "userid {$insql}", $inparams);
        $DB->delete_records_select('local_mailwhistle_unsubscribes', "userid {$insql}", $inparams);

        // Delete assignment rows for these users as tagged subjects.
        $DB->delete_records_select('local_mailwhistle_tag_assign', "userid {$insql}", $inparams);

        // Anonymise assignment rows these users authored.
        $DB->set_field_select('local_mailwhistle_tag_assign', 'usermodified', 0, "usermodified {$insql}", $inparams);

        // Anonymise tag definitions these users authored.
        $DB->set_field_select('local_mailwhistle_tag', 'usermodified', 0, "usermodified {$insql}", $inparams);
    }
}
