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

namespace local_mailwhistle\manager;

/**
 * Read-only delivery diagnostics from recipient activity and Moodle's task queue.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delivery_status_manager {
    /** @var int Allow the recovery task's ten-minute interval before flagging inactivity. */
    public const DELAY_THRESHOLD = 600;

    /**
     * Describe a sending campaign without changing or retrying its delivery.
     *
     * @param \stdClass $campaign Campaign record.
     * @param int|null $now Clock override for deterministic checks.
     * @return array Empty for inactive campaigns; queue state and activity otherwise.
     */
    public static function get_campaign_status(\stdClass $campaign, ?int $now = null): array {
        global $DB;

        if ($campaign->status !== campaign_manager::STATUS_SENDING) {
            return [];
        }
        $now = $now ?? time();
        $queuedat = (int) $campaign->timemodified;
        $lastactivity = (int) $DB->get_field_sql(
            'SELECT MAX(timemodified) FROM {local_mailwhistle_recipients}
              WHERE campaignid = :campaignid AND status <> :pending',
            ['campaignid' => $campaign->id, 'pending' => recipient_manager::STATUS_PENDING]
        );
        $tasks = $DB->get_recordset(
            'task_adhoc',
            ['classname' => '\\' . \local_mailwhistle\task\send_campaign::class],
            '',
            'id, customdata, timestarted, nextruntime, faildelay'
        );
        $queued = false;
        $started = 0;
        $retryat = 0;
        try {
            foreach ($tasks as $task) {
                $data = json_decode($task->customdata ?? '');
                if ((int) ($data->campaignid ?? 0) !== (int) $campaign->id) {
                    continue;
                }
                $queued = true;
                $started = max($started, (int) $task->timestarted);
                if (!empty($task->faildelay)) {
                    $retryat = $retryat ? min($retryat, (int) $task->nextruntime) : (int) $task->nextruntime;
                }
            }
        } finally {
            $tasks->close();
        }
        $idle = max(0, $now - max($queuedat, $lastactivity, $started));
        if (!$queued) {
            $state = 'missing';
        } else if ($retryat > $now) {
            $state = 'retry';
        } else if ($idle >= self::DELAY_THRESHOLD) {
            $state = 'delayed';
        } else {
            $state = $started ? 'started' : 'queued';
        }
        return [
            'state' => $state,
            'warning' => in_array($state, ['missing', 'retry', 'delayed'], true),
            'queuedat' => $queuedat,
            'age' => max(0, $now - $queuedat),
            'lastactivity' => $lastactivity,
            'retryat' => $retryat,
        ];
    }

    /**
     * Describe the two plugin scheduled tasks without assuming that a worker is alive.
     *
     * @param int|null $now Clock override for deterministic checks.
     * @return array Task rows, containing a localised name and recorded runtime state.
     */
    public static function get_task_statuses(?int $now = null): array {
        global $DB;

        $now = $now ?? time();
        $rows = [];
        foreach (['dispatch_scheduled_campaigns', 'requeue_stuck_sends'] as $name) {
            $task = $DB->get_record('task_scheduled', ['classname' => '\\local_mailwhistle\\task\\' . $name]);
            if (!$task) {
                $state = 'missing';
            } else if (!empty($task->disabled)) {
                $state = 'disabled';
            } else if (!empty($task->faildelay)) {
                $state = 'retry';
            } else if (!empty($task->timestarted) && $task->timestarted > $now - self::DELAY_THRESHOLD) {
                $state = 'started';
            } else if (!empty($task->nextruntime) && $task->nextruntime < $now - self::DELAY_THRESHOLD) {
                $state = 'delayed';
            } else {
                $state = 'scheduled';
            }
            $rows[] = [
                'name' => get_string('task_' . $name, 'local_mailwhistle'),
                'state' => $state,
                'warning' => in_array($state, ['missing', 'disabled', 'retry', 'delayed'], true),
                'lastrun' => (int) ($task->lastruntime ?? 0),
                'nextrun' => (int) ($task->nextruntime ?? 0),
            ];
        }
        return $rows;
    }
}
