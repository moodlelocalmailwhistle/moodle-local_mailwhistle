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
 * Output sent mails list
 *
 * @package   local_mailwhistle
 * @copyright 2026 Synergy Learning
 * @author    Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mailwhistle\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use core_reportbuilder\system_report_factory;
use local_mailwhistle\reportbuilder\local\systemreports\draft_campaigns as campaignsreport;

/**
 * Output sent mails list
 */
class draft_campaigns implements renderable, templatable {
    /**
     * Export this data so it can be used in a template.
     *
     * @param renderer_base $output
     * @return array
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     */
    public function export_for_template(renderer_base $output) {
        $button = $output->single_button(
            new \moodle_url('/local/mailwhistle/campaign_edit.php', ['sesskey' => sesskey()]),
            get_string('createcampaign', 'local_mailwhistle'),
            'get',
            ['class' => 'mb-3']
        );
        $report = system_report_factory::create(campaignsreport::class, \context_system::instance());
        return [
            'button' => $button,
            'table' => $report->output(),
        ];
    }
}
