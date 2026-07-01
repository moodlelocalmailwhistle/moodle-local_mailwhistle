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
 * File resources
 *
 * @package   local_mailwhistle
 * @copyright 2026 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mailwhistle\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;

/**
 * File resources
 */
class resources implements renderable, templatable {
    /**
     * The file area name.
     */
    public const FILEAREA = 'resources';

    /**
     * The constructor.
     *
     * @param \local_mailwhistle\form\resources_form $form The form.
     */
    public function __construct(
        /** @var \local_mailwhistle\form\resources_form The resources form. */
        protected \local_mailwhistle\form\resources_form $form
    ) {
    }

    /**
     * Get a list of available resources.
     *
     * @return string[] $filename => $url
     */
    public static function get_available_files(): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            \context_system::instance()->id,
            'local_mailwhistle',
            self::FILEAREA,
            false,
            false
        );
        $ret = [];
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $ret[$filename] = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $filename
            );
        }
        return $ret;
    }

    /**
     * Export the data for the resources template.
     *
     * @param renderer_base $output The renderer.
     * @return array Template context.
     */
    public function export_for_template(renderer_base $output) {
        return ['form' => $this->form->render()];
    }
}
