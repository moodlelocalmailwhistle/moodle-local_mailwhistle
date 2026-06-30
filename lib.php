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

/**
 * Local plugin "Mail Whistle" - Main library file.
 *
 * This file contains hook implementations and callable functions for the plugin.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Plugin installation callback.
 *
 * Runs once during initial plugin installation.
 * Use this to set up initial data, database records, or configuration.
 *
 * @return void
 */
function local_mailwhistle_install(): void {
    // Add installation logic here if needed.
}

/**
 * Plugin upgrade callback.
 *
 * Runs when the plugin is upgraded to a new version.
 * Database schema changes should be handled in db/upgrade.php file.
 *
 * @param int $oldversion The previous plugin version code.
 * @return bool True if successful, false otherwise.
 */
function local_mailwhistle_upgrade(int $oldversion): bool {
    // Database upgrades are handled in db/upgrade.php.
    // This function can handle non-database upgrade tasks if needed.
    return true;
}

/**
 * Plugin uninstall callback.
 *
 * Runs when the plugin is being uninstalled.
 * Clean up plugin-specific data, files, and configuration.
 *
 * @return bool True if successful, false otherwise.
 */
function local_mailwhistle_uninstall(): bool {
    // Add cleanup logic here if needed.
    // Note: Database tables are automatically dropped after this runs.
    return true;
}

/**
 * Hook to extend the global site navigation.
 *
 * Adds navigation items to the main navigation menu.
 * Check user capabilities before adding sensitive menu items.
 *
 * @param global_navigation $navigation The global navigation object.
 * @return void
 */
function local_mailwhistle_extend_navigation(global_navigation $navigation): void {
    // Example: Add plugin menu item (uncomment to use)
    // if (has_capability('local/mailwhistle:view', context_system::instance())) {
    //     $url = new moodle_url('/local/mailwhistle/index.php');
    //     $node = $navigation->add(get_string('pluginname', 'local_mailwhistle'), $url);
    //     $node->showinflatnavigation = true;
    // }
}

/**
 * Hook to extend course-specific navigation.
 *
 * Adds course-related menu items when viewing a course.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course object.
 * @return void
 */
function local_mailwhistle_extend_navigation_course(navigation_node $navigation, stdClass $course): void {
    // Add course-specific navigation items here if needed.
}

/**
 * Hook to extend user-specific navigation.
 *
 * Adds user profile menu items when viewing a user profile.
 *
 * @param navigation_node $navigation The user navigation node.
 * @param stdClass $user The user object.
 * @return void
 */
function local_mailwhistle_extend_navigation_user(navigation_node $navigation, stdClass $user): void {
    // Add user-specific navigation items here if needed.
}

/**
 * Hook called after every page is initialized.
 *
 * Use this to add global CSS, JavaScript, or make page modifications.
 * This hook runs very early in page initialization.
 *
 * @return void
 */
function local_mailwhistle_page_init(): void {
    global $PAGE;
    // Example: Add custom CSS or JavaScript (uncomment to use)
    // $PAGE->requires->css(new moodle_url('/local/mailwhistle/styles.css'));
}
