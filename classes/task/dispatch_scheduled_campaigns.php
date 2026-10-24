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

namespace local_mailwhistle\task;

use local_mailwhistle\manager\campaign_manager;

/**
 * Dispatch due campaigns without taking a recipient snapshot ahead of time.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dispatch_scheduled_campaigns extends \core\task\scheduled_task {
    /**
     * Name displayed in Moodle's scheduled-task administration.
     *
     * @return string Task name.
     */
    public function get_name(): string {
        return get_string('task_dispatch_scheduled_campaigns', 'local_mailwhistle');
    }

    /**
     * Start each due campaign, isolating failures so later campaigns can proceed.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $ids = $DB->get_fieldset_sql(
            'SELECT id FROM {local_mailwhistle_campaigns}
              WHERE status = :status AND timescheduled > 0 AND timescheduled <= :now
           ORDER BY timescheduled, id',
            [
                'status' => campaign_manager::STATUS_SCHEDULED,
                'now' => time(),
            ]
        );
        foreach ($ids as $campaignid) {
            try {
                // The manager rechecks status and time after acquiring the shared campaign lock.
                campaign_manager::begin_scheduled_sending((int) $campaignid);
            } catch (\Throwable $exception) {
                // An infrastructure failure leaves the schedule intact for a subsequent cron run.
                mtrace('Scheduled campaign ' . $campaignid . ': ' . $exception->getMessage());
            }
        }
    }
}
