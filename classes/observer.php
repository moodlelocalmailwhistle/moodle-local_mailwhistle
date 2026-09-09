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

namespace local_mailwhistle;

/**
 * Event observer for the local_mailwhistle plugin.
 *
 * Listens for core Moodle events and keeps plugin tables consistent.
 *
 * @package   local_mailwhistle
 * @copyright 2024 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Handle the core user_deleted event.
     *
     * Removes all audience tag assignments for the deleted user so that
     * {local_mailwhistle_tag_assign} never accumulates orphaned rows.
     *
     * @param \core\event\user_deleted $event
     * @return void
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;

        $userid = (int) $event->objectid;

        $DB->delete_records_select(
            'local_mailwhistle_tracking',
            'recipientid IN (SELECT id FROM {local_mailwhistle_recipients} WHERE userid = :userid)',
            ['userid' => $userid]
        );
        $DB->delete_records_select(
            'local_mailwhistle_sendlogs',
            'recipientid IN (SELECT id FROM {local_mailwhistle_recipients} WHERE userid = :userid)',
            ['userid' => $userid]
        );
        $DB->delete_records('local_mailwhistle_recipients', ['userid' => $userid]);
        $DB->delete_records('local_mailwhistle_unsubscribes', ['userid' => $userid]);
        $DB->delete_records('local_mailwhistle_tag_assign', ['userid' => $userid]);
        $DB->set_field('local_mailwhistle_tag_assign', 'usermodified', 0, ['usermodified' => $userid]);
        $DB->set_field('local_mailwhistle_tag', 'usermodified', 0, ['usermodified' => $userid]);
    }
}
