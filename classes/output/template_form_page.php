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
 * Create/edit template form wrapper.
 *
 * The moodleform HTML is produced by formslib and passed in already rendered.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_form_page implements renderable, templatable {
    /** @var string Page heading. */
    private string $heading;

    /** @var string Rendered moodleform HTML. */
    private string $formhtml;

    /**
     * Create the form page wrapper.
     *
     * @param string $heading Page heading.
     * @param string $formhtml Rendered moodleform HTML.
     */
    public function __construct(string $heading, string $formhtml) {
        $this->heading = $heading;
        $this->formhtml = $formhtml;
    }

    /**
     * Export this data so it can be used in a template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'heading' => $this->heading,
            'formhtml' => $this->formhtml,
        ];
    }
}
