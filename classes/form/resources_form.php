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
 * @copyright 2026 onwards MoodleDach project
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
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('filemanager', 'resources', get_string('resources', 'local_mailwhistle'), null, [
            'subdirs' => 0,
            'maxfiles' => 50,
            'accepted_types' => \local_mailwhistle\output\resources::ACCEPTED_TYPES,
        ]);
        $this->add_action_buttons();
    }

    /**
     * Protect resources which are used by saved campaigns or templates.
     *
     * @param array $data Submitted fields.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $changed = \local_mailwhistle\manager\resource_manager::get_protected_changes((int) ($data['resources'] ?? 0));
        if ($changed) {
            $errors['resources'] = get_string('resources_inuse_error', 'local_mailwhistle', s(implode(', ', $changed)));
        }
        return $errors;
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
        if ($this->is_submitted()) {
            return false;
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
