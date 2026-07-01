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

/**
 * Wizard step 2: campaign content (subject and body).
 *
 * Collects the email subject and the HTML body. The body uses a Moodle editor
 * element; the controller stores the HTML in bodyhtml and a plain-text version
 * in bodytext.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_content_form extends \moodleform {
    /**
     * Define the form fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'campaignid');
        $mform->setType('campaignid', PARAM_INT);

        $mform->addElement('text', 'subject', get_string('subject', 'local_mailwhistle'), ['maxlength' => 255, 'size' => 50]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');

        // Plain HTML editor; no file areas needed for the draft body preview.
        $mform->addElement('editor', 'body', get_string('body', 'local_mailwhistle'), null, ['enable_filemanagement' => false]);
        $mform->setType('body', PARAM_RAW);

        $this->add_action_buttons(true, get_string('wizard_savecontinue', 'local_mailwhistle'));
    }

    /**
     * Server-side validation.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array<string, string> Validation errors, keyed by field name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (trim($data['subject'] ?? '') === '') {
            $errors['subject'] = get_string('required');
        }

        return $errors;
    }
}
