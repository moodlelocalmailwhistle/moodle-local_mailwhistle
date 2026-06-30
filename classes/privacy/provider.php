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

/**
 * Privacy provider for local_mailwhistle plugin.
 *
 * Minimal stub implementation. If your plugin stores or processes personal
 * data (eg. `local_mailwhistle_data.userid`), implement the export/delete
 * logic below. This stub declares no exportable personal data by default.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin_provider {
    /**
     * Declare the metadata for data stored by this plugin.
     */
    public static function get_metadata(collection $collection): collection {
        // Add data descriptions to $collection if plugin stores personal data.
        return $collection;
    }

    /**
     * Return contexts that contain user information for the provided user id.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * Populate a userlist with users who have data in the given context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        // Add matching users to the userlist when this plugin stores user data.
    }

    /**
     * Export user data for the approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        // Implement export logic here if plugin stores personal data.
    }

    /**
     * Delete all user data for all users in the specified context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        // Implement deletion logic if plugin stores personal data.
    }

    /**
     * Delete user data for the specified users/contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // Implement targeted deletion logic here.
    }
}
