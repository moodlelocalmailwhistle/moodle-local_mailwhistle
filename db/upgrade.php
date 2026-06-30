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
 * Local plugin "Mail Whistle" - Database upgrade file.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade callback for Mail Whistle plugin database schema.
 *
 * Handles database schema changes across plugin versions.
 * Each version increment should have upgrade code to migrate data.
 *
 * @param int $oldversion The previously installed version code.
 * @return bool True on success, false on failure.
 * @throws \Exception If upgrade fails.
 */
function xmldb_local_mailwhistle_upgrade(int $oldversion): bool {
    global $DB;

    // No upgrade steps yet. Add version-gated blocks here as the schema evolves, e.g.:
    //
    // if ($oldversion < 2025010100) {
    //     $dbman = $DB->get_manager();
    //     // Apply schema changes via $dbman, then record the savepoint.
    //     upgrade_plugin_savepoint(true, 2025010100, 'local', 'mailwhistle');
    // }

    return true;
}
