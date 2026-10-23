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
        } else if (
            in_array($row->status, [
            \local_mailwhistle\manager\campaign_manager::STATUS_COMPLETED_WITH_ERRORS,
            \local_mailwhistle\manager\campaign_manager::STATUS_FAILED,
            ], true)
        ) {
            $url = new \moodle_url('/local/mailwhistle/index.php', ['campaignid' => $row->id, 'tab' => 'reports']);
        }
        if (trim((string) $name) === '') {
            $name = get_string('untitledcampaign', 'local_mailwhistle');
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
     * Campaign name and subject for the Reports overview.
     *
     * @param string|null $name Stored campaign name.
     * @param \stdClass $row Report row containing id and subject.
     * @return string Linked name with its secondary subject.
     */
    public static function reportsummary(?string $name, \stdClass $row): string {
        if (trim((string) $name) === '') {
            $name = get_string('untitledcampaign', 'local_mailwhistle');
        }
        $context = \context_system::instance();
        $url = new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'reports', 'campaignid' => $row->id]);
        $link = \html_writer::link($url, format_string($name, true, ['context' => $context]), [
            'class' => 'local-mailwhistle-report-name',
        ]);
        $subject = \html_writer::div(
            format_string((string) $row->subject, true, ['context' => $context]),
            'local-mailwhistle-report-subject'
        );
        return \html_writer::div($link . $subject, 'local-mailwhistle-report-campaign');
    }

    /**
     * Compact sent date, without inventing a date for unknown timestamps.
     *
     * @param int|string|null $timesent Recorded send timestamp.
     * @param \stdClass $row Report row.
     * @return string Date cell content.
     */
    public static function reportsentat($timesent, \stdClass $row): string {
        $date = (int) $timesent > 0
            ? userdate((int) $timesent, get_string('strftimedatetimeshort', 'langconfig'))
            : get_string('reports_nosentdate', 'local_mailwhistle');
        return \html_writer::span(s($date), 'local-mailwhistle-report-date');
    }

    /**
     * Sending results from counts selected in the report's SQL query.
     *
     * @param int|string|null $sentcount Number of sent recipients.
     * @param \stdClass $row Aggregate total, failed, and pending counts.
     * @return string Sent count and any outstanding delivery details.
     */
    public static function reportdelivery($sentcount, \stdClass $row): string {
        $counts = (object) ['sent' => format_float((int) $sentcount, 0), 'total' => format_float((int) $row->totalcount, 0)];
        $html = \html_writer::div(
            s(get_string('reports_sentcount', 'local_mailwhistle', $counts)),
            'local-mailwhistle-report-count'
        );
        if ((int) $row->failedcount > 0) {
            $html .= \html_writer::div(
                s(get_string('stat_failedcount', 'local_mailwhistle', $row->failedcount)),
                'local-mailwhistle-report-metric-note local-mailwhistle-report-failed'
            );
        }
        if ((int) $row->pendingcount > 0) {
            $html .= \html_writer::div(
                s(get_string('stat_pendingcount', 'local_mailwhistle', $row->pendingcount)),
                'local-mailwhistle-report-metric-note'
            );
        }
        foreach (['skipped', 'uncertain'] as $status) {
            $count = (int) ($row->{$status . 'count'} ?? 0);
            if ($count > 0) {
                $html .= \html_writer::div(
                    s(get_string('stat_' . $status . 'count', 'local_mailwhistle', $count)),
                    'local-mailwhistle-report-metric-note'
                );
            }
        }
        return \html_writer::div($html, 'local-mailwhistle-report-delivery');
    }

    /**
     * Unique opens and the share of sent recipients.
     *
     * @param int|string|null $opencount Unique opens.
     * @param \stdClass $row Aggregate sent count.
     * @return string Formatted engagement cell.
     */
    public static function reportopens($opencount, \stdClass $row): string {
        return self::report_engagement((int) $opencount, (int) $row->sentcount, 'opens');
    }

    /**
     * Unique clickers and the share of sent recipients.
     *
     * @param int|string|null $clickcount Unique clickers.
     * @param \stdClass $row Aggregate sent count.
     * @return string Formatted engagement cell.
     */
    public static function reportclicks($clickcount, \stdClass $row): string {
        return self::report_engagement((int) $clickcount, (int) $row->sentcount, 'clicks');
    }

    /**
     * Format an engagement count with the same denominator as campaign detail reports.
     *
     * @param int $count Unique engagement count.
     * @param int $sentcount Sent recipients.
     * @param string $metric CSS suffix for the metric.
     * @return string Metric markup.
     */
    private static function report_engagement(int $count, int $sentcount, string $metric): string {
        $rate = $sentcount > 0 ? round(($count / $sentcount) * 100, 1) : 0;
        $html = \html_writer::div(s(format_float($count, 0)), 'local-mailwhistle-report-count');
        $html .= \html_writer::div(
            s(get_string('reports_rate', 'local_mailwhistle', format_float($rate, 1))),
            'local-mailwhistle-report-metric-note'
        );
        return \html_writer::div($html, 'local-mailwhistle-report-' . $metric);
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
