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
 * The plugin stores per-recipient send state ({local_mailwhistle_recipients})
 * and unsubscribe records ({local_mailwhistle_unsubscribes}), both keyed on the
 * Moodle user id. This provider declares that data and implements the export
 * and delete operations required by the privacy API. All plugin data lives at
 * the system context. The campaigns table only records authorship via
 * createdby, which is retained as an audit field and is not treated as
 * deletable personal data.
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

        return $collection;
    }

    /**
     * Return the contexts that contain personal data for the given user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contexts containing the user's data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $hasdata = $DB->record_exists('local_mailwhistle_recipients', ['userid' => $userid])
            || $DB->record_exists('local_mailwhistle_unsubscribes', ['userid' => $userid]);

        if ($hasdata) {
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

        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_mailwhistle_recipients}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_mailwhistle_unsubscribes}', []);
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

        $DB->delete_records('local_mailwhistle_recipients');
        $DB->delete_records('local_mailwhistle_unsubscribes');
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

            $DB->delete_records('local_mailwhistle_recipients', ['userid' => $user->id]);
            $DB->delete_records('local_mailwhistle_unsubscribes', ['userid' => $user->id]);
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
    }
}
