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
 * Scheduled reaper that resumes stalled campaign sends.
 *
 * If a send worker is killed mid-batch (timeout, deploy, OOM), its campaign can
 * be left in 'sending' with recipients still pending and no adhoc task to
 * resume it. This scheduled task finds such campaigns and queues a fresh
 * send_campaign adhoc task, making delivery self-healing. It is safe to run
 * repeatedly: send_campaign only touches 'pending' recipients.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class requeue_stuck_sends extends \core\task\scheduled_task {
    /**
     * Task name shown in the scheduled task admin screen.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_requeue_stuck_sends', 'local_mailwhistle');
    }

    /**
     * Queue a send task for each campaign stuck in sending with pending rows.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        // Sending campaigns that still have pending recipients.
        $sql = "SELECT DISTINCT c.id
                  FROM {local_mailwhistle_campaigns} c
                  JOIN {local_mailwhistle_recipients} r ON r.campaignid = c.id
                 WHERE c.status = :sending
                   AND r.status = :pending";
        $ids = $DB->get_fieldset_sql($sql, [
            'sending' => campaign_manager::STATUS_SENDING,
            'pending' => \local_mailwhistle\manager\recipient_manager::STATUS_PENDING,
        ]);

        foreach ($ids as $campaignid) {
            $task = new send_campaign();
            $task->set_custom_data(['campaignid' => (int) $campaignid]);
            \core\task\manager::queue_adhoc_task($task);
        }
    }
}
