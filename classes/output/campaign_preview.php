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
use local_mailwhistle\manager\audience_manager;
use local_mailwhistle\manager\campaign_manager;

/**
 * Sent-campaign detail preview.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_preview implements renderable, templatable {
    /** @var int Campaign id. */
    private int $id;

    /**
     * Create a sent-campaign preview.
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

        $listurl = new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'send']);
        $data = [
            'listurl' => $listurl->out(false),
            'notfound' => true,
        ];

        $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $this->id]);
        if (!$campaign || $campaign->status !== campaign_manager::STATUS_SENT) {
            return $data;
        }

        $systemcontext = \context_system::instance();
        $recipientcount = $DB->count_records('local_mailwhistle_recipients', ['campaignid' => $this->id]);
        $tagids = audience_manager::get_campaign_tagids($this->id);
        $tagnames = [];
        foreach ($tagids as $tagid) {
            $tagname = $DB->get_field('local_mailwhistle_tag', 'name', ['id' => $tagid]);
            if ($tagname) {
                $tagnames[] = format_string($tagname, true, ['context' => $systemcontext]);
            }
        }
        $audiencelabel = $tagnames
            ? implode(', ', $tagnames)
            : get_string('audiencetags_none', 'local_mailwhistle');
        $sentat = (int) $campaign->timesent > 0 ? (int) $campaign->timesent : (int) $campaign->timemodified;

        return [
            'listurl' => $listurl->out(false),
            'notfound' => false,
            'subject' => format_string($campaign->subject, true, ['context' => $systemcontext]),
            'metarows' => [
                [
                    'label' => get_string('col_audience', 'local_mailwhistle'),
                    'value' => $audiencelabel,
                ],
                [
                    'label' => get_string('col_recipients', 'local_mailwhistle'),
                    'value' => number_format($recipientcount),
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
            'bodyhtml' => format_text((string) $campaign->bodyhtml, FORMAT_HTML, ['context' => $systemcontext]),
        ];
    }
}
