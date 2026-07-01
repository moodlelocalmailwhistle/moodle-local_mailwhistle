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
 * Helper for formatting campaign fields
 *
 * @package   local_mailwhistle
 * @copyright 2026 Synergy Learning
 * @author    Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mailwhistle\reportbuilder\local\helper;

/**
 * Helper for formatting campaign fields
 */
class campaign {
    /**
     * Output the status
     *
     * @param string|null $status
     * @param \stdClass $row
     * @return string
     */
    public static function status(?string $status, \stdClass $row): string {
        global $CFG;
        require_once($CFG->dirroot . '/local/mailwhistle/lib.php');
        return local_mailwhistle_status_badge($status);
    }

    /**
     * Output the name, linked to the campaign
     *
     * @param string|null $name
     * @param \stdClass $row
     * @return string
     */
    public static function namelink(?string $name, \stdClass $row): string {
        $url = null;
        if (
            in_array(
                $row->status,
                [
                    \local_mailwhistle\manager\campaign_manager::STATUS_DRAFT,
                    \local_mailwhistle\manager\campaign_manager::STATUS_READY,
                ],
                true
            )
        ) {
            $url = new \moodle_url('/local/mailwhistle/campaign_edit.php', ['campaignid' => $row->id]);
        } else if ($row->status === \local_mailwhistle\manager\campaign_manager::STATUS_SENT) {
            $url = new \moodle_url('/local/mailwhistle/index.php', ['view' => $row->id, 'tab' => 'send']);
        }
        if ($url) {
            return \html_writer::link($url, $name);
        }
        return $name;
    }

    /**
     * Output action
     *
     * @param int|null $id
     * @param \stdClass $row
     * @return string
     */
    public static function actions(?int $id, \stdClass $row): string {
        global $OUTPUT;

        if ($row->status !== \local_mailwhistle\manager\campaign_manager::STATUS_READY) {
            return '';
        }

        $sendurl = new \moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'send',
            'action' => 'sendnow',
            'campaignid' => $id,
        ]);
        return $OUTPUT->single_button($sendurl, get_string('sendnow', 'local_mailwhistle'), 'post');
    }
}
