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

use local_mailwhistle\manager\send_manager;
use local_mailwhistle\manager\campaign_manager;

/**
 * Adhoc task that delivers a campaign to its recipients.
 *
 * Queued by the "Send now" action with the campaign id as custom data. Sends
 * one bounded batch (sendbatchsize) of pending recipients per run and re-queues
 * itself while recipients remain, so a large audience is delivered across
 * several runs without a single long-running task.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_campaign extends \core\task\adhoc_task {
    /**
     * Deliver a batch of the campaign, re-queueing if more remain.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $data = (array) $this->get_custom_data();
        $campaignid = (int) ($data['campaignid'] ?? 0);
        if (empty($campaignid)) {
            return;
        }

        $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid]);
        if (!$campaign || $campaign->status !== campaign_manager::STATUS_SENDING) {
            // Only a campaign the "Send now" action already flipped to sending is processed.
            return;
        }

        $batchsize = (int) get_config('local_mailwhistle', 'sendbatchsize');
        if ($batchsize <= 0) {
            $batchsize = 50;
        }

        $pending = send_manager::process_campaign($campaignid, $batchsize);

        if ($pending > 0) {
            // More recipients remain: queue another run.
            $next = new self();
            $next->set_custom_data(['campaignid' => $campaignid]);
            \core\task\manager::queue_adhoc_task($next);
        }
    }
}
