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
 * Read API for campaign send and tracking statistics.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_manager {
    /**
     * Whether a campaign can be shown on the Reports tab.
     *
     * @param int $campaignid Campaign id.
     * @return bool True for sent and sending campaigns.
     */
    public static function campaign_is_reportable(int $campaignid): bool {
        global $DB;

        $status = $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $campaignid]);
        return in_array($status, [
            campaign_manager::STATUS_SENT,
            campaign_manager::STATUS_SENDING,
        ], true);
    }

    /**
     * Aggregate recipient and tracking counts for a campaign.
     *
     * Rates are unique opens/clicks divided by sent recipients, as a
     * percentage with one decimal place. Zero sent yields 0% (no division).
     *
     * @param int $campaignid Campaign id.
     * @return \stdClass recipients, sent, failed, pending, uniqueopens,
     *         uniqueclicks, openrate, clickrate.
     */
    public static function get_campaign_stats(int $campaignid): \stdClass {
        global $DB;

        $stats = (object) [
            'recipients' => 0,
            'sent' => 0,
            'failed' => 0,
            'pending' => 0,
            'uniqueopens' => 0,
            'uniqueclicks' => 0,
            'openrate' => 0.0,
            'clickrate' => 0.0,
        ];

        $counts = $DB->get_records_sql(
            'SELECT status, COUNT(1) AS n
               FROM {local_mailwhistle_recipients}
              WHERE campaignid = :campaignid
           GROUP BY status',
            ['campaignid' => $campaignid]
        );
        foreach ($counts as $row) {
            $n = (int) $row->n;
            $stats->recipients += $n;
            switch ($row->status) {
                case recipient_manager::STATUS_SENT:
                    $stats->sent = $n;
                    break;
                case recipient_manager::STATUS_FAILED:
                    $stats->failed = $n;
                    break;
                case recipient_manager::STATUS_PENDING:
                    $stats->pending = $n;
                    break;
                default:
                    break;
            }
        }

        $stats->uniqueopens = (int) $DB->count_records('local_mailwhistle_tracking', [
            'campaignid' => $campaignid,
            'eventtype' => tracking_manager::EVENT_OPEN,
        ]);
        $stats->uniqueclicks = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT recipientid)
               FROM {local_mailwhistle_tracking}
              WHERE campaignid = :campaignid
                AND eventtype = :eventtype',
            [
                'campaignid' => $campaignid,
                'eventtype' => tracking_manager::EVENT_CLICK,
            ]
        );

        if ($stats->sent > 0) {
            $stats->openrate = round(($stats->uniqueopens / $stats->sent) * 100, 1);
            $stats->clickrate = round(($stats->uniqueclicks / $stats->sent) * 100, 1);
        }

        return $stats;
    }
}
