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
 * Helper class for Mail Whistle plugin utility functions.
 *
 * Provides static methods for common plugin operations like
 * configuration management and data processing.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Example: Process user data.
     *
     * Demonstrates a basic operation with user validation.
     * Add your business logic to this method.
     *
     * @param int $userid The user ID to process.
     * @return bool True if successful, false if user not found.
     */
    public static function process_user_data(int $userid): bool {
        global $DB;

        // Validate that the user exists.
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return false;
        }

        // Add your processing logic here.

        return true;
    }

    /**
     * Get a plugin configuration setting.
     *
     * Retrieves plugin configuration from Moodle settings.
     * Returns default value if setting not found.
     *
     * @param string $setting The setting name (without plugin prefix).
     * @param mixed $default The default value if setting not found.
     * @return mixed The setting value or default.
     */
    public static function get_config(string $setting, mixed $default = null): mixed {
        $value = get_config('local_mailwhistle', $setting);
        return $value !== false ? $value : $default;
    }

    /**
     * Set a plugin configuration setting.
     *
     * Stores plugin configuration in Moodle settings.
     *
     * @param string $setting The setting name (without plugin prefix).
     * @param mixed $value The value to store.
     * @return bool True if successful.
     */
    public static function set_config(string $setting, mixed $value): bool {
        return set_config($setting, $value, 'local_mailwhistle');
    }

    /**
     * Log plugin activity event.
     *
     * Records an event in the Moodle event system for audit trails.
     *
     * @param string $action The action being performed.
     * @param string $description A description of the action.
     * @param int|null $userid The user performing the action (uses current user if null).
     * @return void
     */
    public static function log_activity(string $action, string $description, ?int $userid = null): void {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        // Trigger a specific plugin event for activity logging.
        $eventdata = [
            'context' => \context_system::instance(),
            'objectid' => 0,
            'userid' => $userid,
            'other' => [
                'action' => $action,
                'description' => $description,
            ],
        ];

        $event = \local_mailwhistle\event\data_created::create($eventdata);
        $event->trigger();
    }

    /**
     * Create a new draft email campaign.
     *
     * Inserts a minimal campaign record with sensible defaults so it can
     * be edited further later. Logs the creation as plugin activity.
     *
     * @param string $name The internal campaign name.
     * @param int|null $userid The creating user (uses current user if null).
     * @return int The new campaign id.
     */
    public static function create_campaign(string $name, ?int $userid = null): int {
        global $DB, $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        // Only the NOT NULL columns without a schema default are set here; the
        // remaining columns (status, sendengine, sendername, senderemail,
        // timescheduled, timesent) take their db/install.xml DEFAULT values.
        $now = time();
        $record = new \stdClass();
        $record->name = $name;
        $record->subject = get_string('untitledcampaign', 'local_mailwhistle');
        $record->bodyhtml = null;
        $record->bodytext = null;
        $record->createdby = $userid;
        $record->timecreated = $now;
        $record->timemodified = $now;

        $campaignid = $DB->insert_record('local_email_campaigns', $record);

        self::log_activity('campaign_created', 'Created campaign: ' . $name, $userid);

        return $campaignid;
    }
}
