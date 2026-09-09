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

/**
 * Coloured campaign status badge.
 *
 * @package   local_mailwhistle
 * @copyright 2024 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_badge implements renderable, templatable {
    /** @var string Campaign status key. */
    private string $status;

    /**
     * Create a status badge.
     *
     * @param string $status Campaign status key.
     */
    public function __construct(string $status) {
        $this->status = $status;
    }

    /**
     * Export this data so it can be used in a template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $classes = [
            'draft' => 'badge bg-secondary text-white',
            'ready' => 'badge bg-primary text-white',
            'sent' => 'badge bg-success text-white',
            'sending' => 'badge bg-info text-white',
            'scheduled' => 'badge bg-secondary text-white',
            'failed' => 'badge bg-danger text-white',
        ];

        return [
            'label' => get_string('status_' . $this->status, 'local_mailwhistle'),
            'classes' => $classes[$this->status] ?? 'badge bg-secondary text-white',
        ];
    }
}
