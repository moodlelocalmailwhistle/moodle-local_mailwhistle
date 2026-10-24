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

namespace local_mailwhistle\form;

use local_mailwhistle\manager\template_manager;

/**
 * Upload an exported template as a new independent copy.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_import_form extends \moodleform {
    /**
     * Define the native Moodle upload form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('static', 'importhelp', '', get_string('template_import_help', 'local_mailwhistle'));
        $mform->addElement('filepicker', 'templatefile', get_string('template_import_file', 'local_mailwhistle'), null, [
            'accepted_types' => ['.json'],
            'maxbytes' => template_manager::MAX_IMPORT_BYTES,
        ]);
        $mform->addRule('templatefile', get_string('required'), 'required', null, 'client');
        $this->add_action_buttons(true, get_string('template_import', 'local_mailwhistle'));
    }

    /**
     * Validate the user's draft upload without trusting its extension or embedded record ids.
     *
     * @param array $data Submitted fields.
     * @param array $files Submitted files.
     * @return array Field validation errors.
     */
    public function validation($data, $files): array {
        global $USER;

        $errors = parent::validation($data, $files);
        $uploads = get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            (int) ($data['templatefile'] ?? 0),
            'id DESC',
            false
        );
        if (count($uploads) !== 1) {
            $errors['templatefile'] = get_string('required');
            return $errors;
        }
        $file = reset($uploads);
        if (
            $file->get_filesize() > template_manager::MAX_IMPORT_BYTES
                || strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) !== 'json'
        ) {
            $errors['templatefile'] = get_string('template_import_invalid', 'local_mailwhistle');
        } else if ($error = template_manager::validate_import_json($file->get_content())) {
            $errors['templatefile'] = $error;
        }
        return $errors;
    }
}
