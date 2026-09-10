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
 * @copyright 2026 onwards MoodleDach project
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
        global $PAGE;
        $renderer = $PAGE->get_renderer('local_mailwhistle');
        return $renderer->status_badge((string) $status);
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
        $safe = format_string((string) $name, true, ['context' => \context_system::instance()]);
        if ($url) {
            return \html_writer::link($url, $safe);
        }
        return $safe;
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

    /**
     * Output audience tag names for a campaign.
     *
     * @param int|string|null $campaignid Campaign id from the report column.
     * @param \stdClass $row Report row.
     * @return string
     */
    public static function audience($campaignid, \stdClass $row): string {
        global $DB;

        $id = (int) $campaignid;
        if ($id <= 0) {
            $id = (int) ($row->id ?? 0);
        }
        $tagids = \local_mailwhistle\manager\audience_manager::get_campaign_tagids($id);
        if (empty($tagids)) {
            return get_string('audiencetags_none', 'local_mailwhistle');
        }

        $systemcontext = \context_system::instance();
        $names = [];
        foreach ($tagids as $tagid) {
            $tagname = $DB->get_field('local_mailwhistle_tag', 'name', ['id' => $tagid]);
            if ($tagname) {
                $names[] = format_string($tagname, true, ['context' => $systemcontext]);
            }
        }

        return $names ? implode(', ', $names) : get_string('audiencetags_none', 'local_mailwhistle');
    }

    /**
     * Output the campaign name linked to the Reports detail page.
     *
     * @param string|null $name Campaign name.
     * @param \stdClass $row Report row.
     * @return string
     */
    public static function reportnamelink(?string $name, \stdClass $row): string {
        $safe = format_string((string) $name, true, ['context' => \context_system::instance()]);
        $url = new \moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'reports',
            'campaignid' => $row->id,
        ]);
        return \html_writer::link($url, $safe);
    }

    /**
     * Output the stored template name, or a fallback when none was recorded.
     *
     * @param int|string|null $templateid Template id.
     * @param \stdClass $row Report row.
     * @return string
     */
    public static function templatename($templateid, \stdClass $row): string {
        global $DB;

        $id = (int) $templateid;
        if ($id <= 0) {
            return get_string('notemplaterecorded', 'local_mailwhistle');
        }

        $name = $DB->get_field('local_mailwhistle_templates', 'name', ['id' => $id]);
        if (!$name) {
            return get_string('unknowntemplate', 'local_mailwhistle');
        }

        return format_string($name, true, ['context' => \context_system::instance()]);
    }
}
