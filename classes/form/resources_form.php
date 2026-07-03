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
 * Form to pick resources
 *
 * @package   local_mailwhistle
 * @copyright 2026 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mailwhistle\form;

/**
 * Form to pick resources
 */
class resources_form extends \moodleform {
    /**
     * Define the resources selection form.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('filemanager', 'resources', get_string('resources', 'local_mailwhistle'));
        $this->add_action_buttons();
    }

    /**
     * Save any submitted files.
     *
     * @return bool true if we should reload the page.
     */
    public function process(): bool {
        if ($data = $this->get_data()) {
            file_save_draft_area_files(
                $data->resources,
                \context_system::instance()->id,
                'local_mailwhistle',
                \local_mailwhistle\output\resources::FILEAREA,
                0
            );
            return true;
        }
        $draftid = file_get_submitted_draft_itemid('resources');
        file_prepare_draft_area(
            $draftid,
            \context_system::instance()->id,
            'local_mailwhistle',
            \local_mailwhistle\output\resources::FILEAREA,
            0
        );
        $this->set_data((object)['resources' => $draftid]);
        return false;
    }
}
