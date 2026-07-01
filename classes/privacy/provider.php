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
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;
use context;
use context_system;

/**
 * Privacy provider for local_mailwhistle plugin.
 *
 * The plugin stores email campaigns in the local_email_campaigns table,
 * linked to the user who created them via the `createdby` field. This
 * plugin is a site-level plugin, so all personal data lives in the
 * system context.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin_provider {
    /**
     * Declare the metadata for data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_email_campaigns', [
            'name' => 'privacy:metadata:local_email_campaigns:name',
            'subject' => 'privacy:metadata:local_email_campaigns:subject',
            'status' => 'privacy:metadata:local_email_campaigns:status',
            'createdby' => 'privacy:metadata:local_email_campaigns:createdby',
            'timecreated' => 'privacy:metadata:local_email_campaigns:timecreated',
        ], 'privacy:metadata:local_email_campaigns');

        return $collection;
    }

    /**
     * Return contexts that contain user information for the provided user id.
     *
     * @param int $userid The user to search.
     * @return contextlist The list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_email_campaigns} c ON c.createdby = :userid
                 WHERE ctx.contextlevel = :ctxlevel";
        $params = [
            'userid' => $userid,
            'ctxlevel' => CONTEXT_SYSTEM,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Populate a userlist with users who have data in the given context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof context_system) {
            return;
        }

        $userlist->add_from_sql('createdby', "SELECT createdby FROM {local_email_campaigns}", []);
    }

    /**
     * Export user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_system) {
                continue;
            }

            $records = $DB->get_records('local_email_campaigns', ['createdby' => $user->id]);

            $campaigns = [];
            foreach ($records as $record) {
                $campaigns[] = (object) [
                    'name' => $record->name,
                    'subject' => $record->subject,
                    'status' => $record->status,
                    'timecreated' => transform::datetime($record->timecreated),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('privacy:metadata:local_email_campaigns', 'local_mailwhistle')],
                (object) ['campaigns' => $campaigns]
            );
        }
    }

    /**
     * Delete all user data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if ($context instanceof context_system) {
            $DB->delete_records('local_email_campaigns');
        }
    }

    /**
     * Delete user data for the specified users/contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $DB->delete_records('local_email_campaigns', ['createdby' => $userid]);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     * @return void
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

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_email_campaigns', "createdby $insql", $params);
    }
}
