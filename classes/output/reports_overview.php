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
use core_reportbuilder\system_report_factory;
use local_mailwhistle\reportbuilder\local\systemreports\campaign_reports as campaignsreport;

/**
 * Reports tab campaign list.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reports_overview implements renderable, templatable {
    /** @var int Optional template id to filter by. */
    private int $templateid;

    /**
     * Create the reports list.
     *
     * @param int $templateid Template id, or 0 for all campaigns.
     */
    public function __construct(int $templateid = 0) {
        $this->templateid = $templateid;
    }

    /**
     * Export this data so it can be used in a template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $heading = get_string('reports_heading', 'local_mailwhistle');
        if ($this->templateid > 0) {
            $templatename = $DB->get_field('local_mailwhistle_templates', 'name', ['id' => $this->templateid]);
            if (!$templatename) {
                $templatename = get_string('unknowntemplate', 'local_mailwhistle');
            } else {
                $templatename = format_string($templatename, true, ['context' => \context_system::instance()]);
            }
            $heading = get_string('campaignsusingtemplate', 'local_mailwhistle', $templatename);
        }

        $report = system_report_factory::create(
            campaignsreport::class,
            \context_system::instance(),
            '',
            '',
            0,
            ['templateid' => $this->templateid]
        );

        return [
            'heading' => $heading,
            'showallurl' => $this->templateid > 0
                ? (new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'reports']))->out(false)
                : '',
            'table' => $report->output(),
        ];
    }
}
