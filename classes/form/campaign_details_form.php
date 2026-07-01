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

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Wizard step 1: campaign details (name and sender).
 *
 * Collects the internal name and the sender identity for a draft campaign.
 * The sender fields are optional at draft stage; the email is validated only
 * when provided.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_details_form extends \moodleform {
    /**
     * Define the form fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'campaignid');
        $mform->setType('campaignid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('internalname', 'local_mailwhistle'), ['maxlength' => 255, 'size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'sendername', get_string('sendername', 'local_mailwhistle'), ['maxlength' => 255, 'size' => 50]);
        $mform->setType('sendername', PARAM_TEXT);

        $emaillabel = get_string('senderemail', 'local_mailwhistle');
        $mform->addElement('text', 'senderemail', $emaillabel, ['maxlength' => 255, 'size' => 50]);
        $mform->setType('senderemail', PARAM_RAW_TRIMMED);

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

        if (trim($data['name'] ?? '') === '') {
            $errors['name'] = get_string('required');
        }

        $email = trim($data['senderemail'] ?? '');
        if ($email !== '' && !validate_email($email)) {
            $errors['senderemail'] = get_string('invalidemail');
        }

        return $errors;
    }
}
