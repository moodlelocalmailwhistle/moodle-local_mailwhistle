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
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026063002) {
        // Create local_mailwhistle_templates table.
        $table = new xmldb_table('local_mailwhistle_templates');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('previewtext', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('bodyhtml', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('bodytext', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('name', XMLDB_INDEX_NOTUNIQUE, ['name']);
        $table->add_index('timemodified', XMLDB_INDEX_NOTUNIQUE, ['timemodified']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026063002, 'local', 'mailwhistle');
    }

    if ($oldversion < 2026063003) {
        $table = new xmldb_table('local_mailwhistle_templates');

        $field = new xmldb_field('editormode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'html', 'previewtext');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('builderjson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'editormode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026063003, 'local', 'mailwhistle');
    }

    if ($oldversion < 2026063004) {
        // Create local_mailwhistle_tag table.
        $table = new xmldb_table('local_mailwhistle_tag');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified_fk', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        $table->add_index('shortname_uix', XMLDB_INDEX_UNIQUE, ['shortname']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Create local_mailwhistle_tag_assign table.
        $table = new xmldb_table('local_mailwhistle_tag_assign');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('tagid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('tagid_fk', XMLDB_KEY_FOREIGN, ['tagid'], 'local_mailwhistle_tag', ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index('tagid_userid_uix', XMLDB_INDEX_UNIQUE, ['tagid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026063004, 'local', 'mailwhistle');
    }

    if ($oldversion < 2026063014) {
        $table = new xmldb_table('local_mailwhistle_templates');

        $field = new xmldb_field('archived', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'bodytext');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('archived', XMLDB_INDEX_NOTUNIQUE, ['archived']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026063014, 'local', 'mailwhistle');
    }

    if ($oldversion < 2026070102) {
        $table = new xmldb_table('local_mailwhistle_templates');
        $field = new xmldb_field('subject');

        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026070102, 'local', 'mailwhistle');
    }

    if ($oldversion < 2026070103) {
        $table = new xmldb_table('local_mailwhistle_templates');

        $field = new xmldb_field('background', XMLDB_TYPE_CHAR, '7', null, XMLDB_NOTNULL, null, '#ffffff', 'previewtext');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('placeholdersjson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'builderjson');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('bodytext');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026070103, 'local', 'mailwhistle');
    }

    if ($oldversion < 2026070106) {
        $table = new xmldb_table('local_mailwhistle_templates');
        $field = new xmldb_field('placeholdersjson');

        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026070106, 'local', 'mailwhistle');
    }

    if ($oldversion < 2026070110) {
        // Seed the bundled default email template on existing installs.
        require_once($CFG->dirroot . '/local/mailwhistle/lib.php');
        local_mailwhistle_install_default_templates();

        upgrade_plugin_savepoint(true, 2026070110, 'local', 'mailwhistle');
    }

    if ($oldversion < 2026070111) {
        $dbman = $DB->get_manager();

        // Create the open/click tracking table if it is not already present.
        $table = new xmldb_table('local_mailwhistle_tracking');
        if (!$dbman->table_exists($table)) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_mailwhistle_tracking');
        }

        upgrade_plugin_savepoint(true, 2026070111, 'local', 'mailwhistle');
    }

    return true;
}
