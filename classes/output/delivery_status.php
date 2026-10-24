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

namespace local_mailwhistle\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use local_mailwhistle\manager\delivery_status_manager;

/**
 * Delivery activity and task diagnostics for campaign managers.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delivery_status implements renderable, templatable {
    /** @var \stdClass|null Campaign record, or null for task status only. */
    private ?\stdClass $campaign;
    /**
     * Create task diagnostics, optionally including one sending campaign.
     *
     * @param \stdClass|null $campaign Optional campaign record.
     */
    public function __construct(?\stdClass $campaign = null) {
        $this->campaign = $campaign;
    }

    /**
     * Export recorded state with permission-aware links to task administration.
     *
     * @param renderer_base $output Renderer.
     * @return array Template context.
     */
    public function export_for_template(renderer_base $output): array {
        $data = ['tasks' => [], 'activity' => [], 'taskwarning' => false];
        if ($this->campaign) {
            $status = delivery_status_manager::get_campaign_status($this->campaign);
            if (!$status) {
                return [];
            }
            $data['campaign'] = true;
            $data['summary'] = get_string('delivery_state_' . $status['state'], 'local_mailwhistle');
            $data['warning'] = $status['warning'];
            $data['activity'] = [
                ['label' => get_string('delivery_queuedat', 'local_mailwhistle'),
                    'value' => userdate($status['queuedat'])],
                ['label' => get_string('delivery_queueage', 'local_mailwhistle'),
                    'value' => format_time($status['age'])],
                ['label' => get_string('delivery_lastactivity', 'local_mailwhistle'),
                    'value' => $status['lastactivity'] ? userdate($status['lastactivity'])
                        : get_string('delivery_noactivity', 'local_mailwhistle')],
            ];
            if ($status['retryat']) {
                $data['activity'][] = ['label' => get_string('delivery_retryat', 'local_mailwhistle'),
                    'value' => userdate($status['retryat'])];
            }
        }
        foreach (delivery_status_manager::get_task_statuses() as $task) {
            $data['taskwarning'] = $data['taskwarning'] || $task['warning'];
            $data['tasks'][] = [
                'name' => $task['name'],
                'state' => get_string('delivery_task_' . $task['state'], 'local_mailwhistle'),
                'warning' => $task['warning'],
                'lastrun' => $task['lastrun'] ? userdate($task['lastrun']) : get_string('never'),
                'nextrun' => $task['nextrun'] ? userdate($task['nextrun']) : get_string('notavailable'),
            ];
        }
        if (has_capability('moodle/site:config', \context_system::instance())) {
            $data['taskurl'] = (new \moodle_url('/admin/tool/task/scheduledtasks.php'))->out(false);
            $data['logurl'] = (new \moodle_url('/admin/tasklogs.php'))->out(false);
        }
        return $data;
    }
}
