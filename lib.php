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
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
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
    require_login();

    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }
    require_capability('local/mailwhistle:view', $context);
    if ($filearea !== \local_mailwhistle\output\resources::FILEAREA) {
        return false;
    }
    $filename = array_pop($args);
    if ($args) {
        $filepath = '/' . implode('/', $args);
    } else {
        $filepath = '/';
    }

    $fs = get_file_storage();
    if (!$file = $fs->get_file($context->id, 'local_mailwhistle', $filearea, 0, $filepath, $filename)) {
        return false;
    }

    $mimetype = $file->get_mimetype();
    $safeinline = in_array($mimetype, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    send_stored_file($file, 0, 0, !$safeinline || $forcedownload, $options);
    return true;
}
