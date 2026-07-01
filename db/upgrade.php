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
    global $DB;

    if ($oldversion < 2026063004) {
        $dbman = $DB->get_manager();

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

    if ($oldversion < 2026070101) {
        $dbman = $DB->get_manager();

        // These tables are defined in db/install.xml but were only created on
        // fresh installs; existing installs never received them. Create any
        // that are missing from the install.xml definitions so the schema
        // matches for sites installed before these tables were added.
        $installtables = [
            'local_mailwhistle_campaigns',
            'local_mailwhistle_audrules',
            'local_mailwhistle_recipients',
            'local_mailwhistle_sendlogs',
            'local_mailwhistle_unsubscribes',
        ];
        foreach ($installtables as $tablename) {
            if (!$dbman->table_exists($tablename)) {
                $dbman->install_one_table_from_xmldb_file(
                    __DIR__ . '/install.xml',
                    $tablename
                );
            }
        }

        upgrade_plugin_savepoint(true, 2026070101, 'local', 'mailwhistle');
    }

    return true;
}
