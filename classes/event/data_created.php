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

namespace local_mailwhistle\event;

/**
 * Example event class for Mail Whistle plugin.
 *
 * Triggered when plugin data is created. Events are important for
 * audit trails and integrations with other plugins.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_created extends \core\event\base {
    /**
     * Initialize event metadata.
     *
     * Sets the CRUD type (Create/Read/Update/Delete), education level,
     * and the database table associated with this event.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'c';                        // CRUD: Create operation.
        $this->data['edulevel'] = self::LEVEL_OTHER;      // Education level.
        $this->data['objecttable'] = 'local_mailwhistle_data'; // Associated table.
    }

    /**
     * Get a human-readable description of the event.
     *
     * @return string Event description.
     */
    public function get_description(): string {
        return 'A data record was created in the Mail Whistle plugin.';
    }

    /**
     * Get the localized event name.
     *
     * @return string Event name from language strings.
     */
    public static function get_name(): string {
        return get_string('event_data_created', 'local_mailwhistle');
    }

    /**
     * Returns the URL relevant to this event.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/mailwhistle/index.php');
    }
}
