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

namespace local_mailwhistle\page;

use local_mailwhistle\helper;
use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\manager\recipient_manager;
use local_mailwhistle\output\campaign_preview;
use local_mailwhistle\output\draft_campaigns;
use local_mailwhistle\output\sent_campaigns;
use local_mailwhistle\task\send_campaign;

/**
 * Campaigns tab: drafts, send now, and sent-mail preview.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send {
    /**
     * Queue a ready campaign. Must run before page output.
     *
     * @param string $action Requested action.
     * @return void
     */
    public static function handle_write(string $action): void {
        if ($action !== 'sendnow') {
            return;
        }

        helper::require_post();
        require_sesskey();
        require_capability('local/mailwhistle:manage', \context_system::instance());

        $campaignid = required_param('campaignid', PARAM_INT);
        $sendurl = new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'send']);

        if (campaign_manager::begin_sending($campaignid)) {
            recipient_manager::snapshot_recipients($campaignid);
            $task = new send_campaign();
            $task->set_custom_data(['campaignid' => $campaignid]);
            \core\task\manager::queue_adhoc_task($task, true);
            redirect(
                $sendurl,
                get_string('sendqueued', 'local_mailwhistle'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        redirect(
            $sendurl,
            get_string('sendnotready', 'local_mailwhistle'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    /**
     * Render drafts, the sent list, or a single sent preview.
     *
     * @return string
     */
    public static function render(): string {
        global $OUTPUT;

        $viewid = optional_param('view', 0, PARAM_INT);
        if ($viewid > 0) {
            return $OUTPUT->render(new campaign_preview($viewid));
        }

        $html = '';
        if (has_capability('local/mailwhistle:manage', \context_system::instance())) {
            $html .= $OUTPUT->render(new draft_campaigns());
        }
        $html .= $OUTPUT->render(new sent_campaigns());
        return $html;
    }
}
