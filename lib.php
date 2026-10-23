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
 * Local plugin "Mail Whistle" - Hook implementations.
 *
 * Callable plugin hooks live here. Business logic and UI rendering live in
 * classes/ (managers, builder, output renderables, Mustache templates).
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve files stored in the plugin's resources file area.
 *
 * @param stdClass $course Course object (unused; system-context plugin).
 * @param stdClass $cm Course module object (unused).
 * @param context $context The context the file was requested in.
 * @param string $filearea The requested file area.
 * @param array $args The remaining file path arguments.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options for file serving.
 * @return bool False if the file cannot be served, otherwise sends the file and exits.
 */
function local_mailwhistle_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
): bool {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }
    if ($filearea !== \local_mailwhistle\output\resources::FILEAREA) {
        return false;
    }
    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    if ($args) {
        $filepath = '/' . implode('/', $args) . '/';
    } else {
        $filepath = '/';
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_mailwhistle', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    // JPEG/PNG/GIF/WebP in this file area are meant for newsletters, so email
    // clients must fetch them with no Moodle session.
    if (\local_mailwhistle\output\resources::is_public_image($file)) {
        \core\session\manager::write_close();
        send_stored_file($file, DAYSECS, 0, false, $options);
        return true;
    }

    require_login();
    require_capability('local/mailwhistle:view', $context);
    send_stored_file($file, 0, 0, true, $options);
    return true;
}

/**
 * Add the owner's campaign email preference to Moodle's user preferences.
 *
 * @param navigation_node $usersetting User settings navigation node.
 * @param stdClass $user User whose settings are displayed.
 * @param context_user $context User context.
 * @param stdClass $course Current course.
 * @param context $coursecontext Current course context.
 * @return void
 */
function local_mailwhistle_extend_navigation_user_settings(
    navigation_node $usersetting,
    stdClass $user,
    context_user $context,
    stdClass $course,
    context $coursecontext
): void {
    global $USER;

    if ((int) $user->id !== (int) $USER->id || !isloggedin() || isguestuser()) {
        return;
    }
    $usersetting->add(
        get_string('subscription_title', 'local_mailwhistle'),
        new moodle_url('/local/mailwhistle/preferences.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_mailwhistle_subscription'
    );
}
