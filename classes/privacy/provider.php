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
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the local_mailwhistle plugin.
 *
 * The plugin stores records in {local_mailwhistle_data}, each owned by a user
 * via the userid column, so this provider declares that table and implements
 * the export and delete operations required by the Moodle privacy API.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
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
            'local_mailwhistle_data',
            [
                'userid' => 'privacy:metadata:local_mailwhistle_data:userid',
                'title' => 'privacy:metadata:local_mailwhistle_data:title',
                'description' => 'privacy:metadata:local_mailwhistle_data:description',
                'status' => 'privacy:metadata:local_mailwhistle_data:status',
                'created' => 'privacy:metadata:local_mailwhistle_data:created',
                'modified' => 'privacy:metadata:local_mailwhistle_data:modified',
            ],
            'privacy:metadata:local_mailwhistle_data'
        );

        return $collection;
    }

    /**
     * Return the contexts that contain personal data for the given user.
     *
     * All plugin data lives at the system context, so return that context when
     * the user owns any record.
     *
     * @param int $userid The user to search.
     * @return contextlist The contexts containing the user's data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        if ($DB->record_exists('local_mailwhistle_data', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Add the users who have data in the given context to the userlist.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof context_system) {
            return;
        }

        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_mailwhistle_data}', []);
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

            $records = $DB->get_records('local_mailwhistle_data', ['userid' => $user->id]);
            foreach ($records as $record) {
                $data = (object) [
                    'title' => $record->title,
                    'description' => $record->description,
                    'status' => $record->status,
                    'created' => \core_privacy\local\request\transform::datetime($record->created),
                    'modified' => \core_privacy\local\request\transform::datetime($record->modified),
                ];

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_mailwhistle'), $record->id],
                    $data
                );
            }
        }
    }

    /**
     * Delete all personal data for all users in the given context.
     *
     * @param context $context The context to delete data in.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_system) {
            return;
        }

        $DB->delete_records('local_mailwhistle_data');
    }

    /**
     * Delete personal data for the user in the approved contexts.
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

            $DB->delete_records('local_mailwhistle_data', ['userid' => $user->id]);
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
        $DB->delete_records_select('local_mailwhistle_data', "userid {$insql}", $inparams);
    }
}
