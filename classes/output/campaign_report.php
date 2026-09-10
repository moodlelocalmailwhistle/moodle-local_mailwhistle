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
use local_mailwhistle\manager\report_manager;
use local_mailwhistle\reportbuilder\local\helper\campaign as campaignhelper;
use local_mailwhistle\table\recipient_table;

/**
 * Single-campaign report: stats plus recipient roster.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_report implements renderable, templatable {
    /** @var int Campaign id. */
    private int $id;

    /**
     * Create a campaign report.
     *
     * @param int $id Campaign id.
     */
    public function __construct(int $id) {
        $this->id = $id;
    }

    /**
     * Export this data so it can be used in a template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $listurl = new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'reports']);
        $data = [
            'listurl' => $listurl->out(false),
            'notfound' => true,
        ];

        $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $this->id]);
        if (!$campaign || !report_manager::campaign_is_reportable($this->id)) {
            return $data;
        }

        $systemcontext = \context_system::instance();
        $stats = report_manager::get_campaign_stats($this->id);
        $sentat = (int) $campaign->timesent > 0 ? (int) $campaign->timesent : (int) $campaign->timemodified;
        $templateid = (int) ($campaign->templateid ?? 0);
        $templatelabel = campaignhelper::templatename($templateid, $campaign);
        $templateurl = '';
        if ($templateid > 0) {
            $templateurl = (new \moodle_url('/local/mailwhistle/index.php', [
                'tab' => 'reports',
                'templateid' => $templateid,
            ]))->out(false);
        }

        $table = new recipient_table(
            'local-mailwhistle-recipients-' . $this->id,
            new \moodle_url('/local/mailwhistle/index.php', [
                'tab' => 'reports',
                'campaignid' => $this->id,
            ]),
            $this->id
        );
        $table->build_sql();
        ob_start();
        $table->out(50, false);
        $tablehtml = ob_get_clean();

        $statcards = [
            [
                'label' => get_string('stat_recipients', 'local_mailwhistle'),
                'value' => number_format($stats->recipients),
                'detail' => '',
            ],
            [
                'label' => get_string('stat_sent', 'local_mailwhistle'),
                'value' => number_format($stats->sent),
                'detail' => $stats->failed > 0
                    ? get_string('stat_failedcount', 'local_mailwhistle', $stats->failed)
                    : '',
            ],
            [
                'label' => get_string('stat_uniqueopens', 'local_mailwhistle'),
                'value' => number_format($stats->uniqueopens),
                'detail' => get_string('stat_openrate', 'local_mailwhistle', $stats->openrate),
            ],
            [
                'label' => get_string('stat_uniqueclicks', 'local_mailwhistle'),
                'value' => number_format($stats->uniqueclicks),
                'detail' => get_string('stat_clickrate', 'local_mailwhistle', $stats->clickrate),
            ],
        ];

        return [
            'listurl' => $listurl->out(false),
            'notfound' => false,
            'subject' => format_string($campaign->subject, true, ['context' => $systemcontext]),
            'stats' => $statcards,
            'metarows' => [
                [
                    'label' => get_string('col_audience', 'local_mailwhistle'),
                    'value' => campaignhelper::audience($this->id, $campaign),
                ],
                [
                    'label' => get_string('col_template', 'local_mailwhistle'),
                    'value' => $templateurl
                        ? \html_writer::link($templateurl, $templatelabel)
                        : $templatelabel,
                ],
                [
                    'label' => get_string('col_sentby', 'local_mailwhistle'),
                    'value' => format_string($campaign->sendername, true, ['context' => $systemcontext]),
                ],
                [
                    'label' => get_string('col_sentat', 'local_mailwhistle'),
                    'value' => userdate($sentat),
                ],
                [
                    'label' => get_string('col_status', 'local_mailwhistle'),
                    'value' => $output->render(new status_badge($campaign->status)),
                ],
            ],
            'table' => $tablehtml,
        ];
    }
}
