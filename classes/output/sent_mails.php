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

/**
 * Output sent mails list
 */
class sent_mails implements renderable, templatable {
    /**
     * Export this data so it can be used in a template.
     *
     * @param renderer_base $output
     * @return array
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     */
    public function export_for_template(renderer_base $output) {
        $rows = local_mailwhistle_get_sample_sent_mails();
        if (!$rows) {
            return ['table' => \html_writer::div(
                get_string('nosentmails', 'local_mailwhistle'),
                'alert alert-info'
            )];
        }

        $table = new \html_table();
        $table->head = [
            get_string('col_subject', 'local_mailwhistle'),
            get_string('col_audience', 'local_mailwhistle'),
            get_string('col_recipients', 'local_mailwhistle'),
            get_string('col_sentby', 'local_mailwhistle'),
            get_string('col_sentat', 'local_mailwhistle'),
            get_string('col_status', 'local_mailwhistle'),
        ];
        $table->attributes['class'] = 'table generaltable local-mailwhistle-sentmails table-striped table-hover';

        foreach ($rows as $row) {
            $viewurl = new \moodle_url('/local/mailwhistle/index.php', [
                'tab' => 'send',
                'view' => $row['id'],
            ]);
            $subjectlink = \html_writer::link($viewurl, format_string($row['subject']));

            $table->data[] = [
                $subjectlink,
                format_string($row['audience']),
                number_format($row['recipients']),
                format_string($row['sentby']),
                userdate($row['sentat']),
                local_mailwhistle_status_badge($row['status']),
            ];
        }
        return ['table' => \html_writer::table($table)];
    }
}
