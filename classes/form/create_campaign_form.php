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
 * Form to create a new draft email campaign.
 *
 * Collects only the internal campaign name; everything else can be
 * configured later once the campaign exists as a draft record.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_campaign_form extends \moodleform {
    /**
     * Define the form fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('static', 'createdesc', '', get_string('createcampaign_desc', 'local_mailwhistle'));

        $mform->addElement('text', 'name', get_string('internalname', 'local_mailwhistle'), ['maxlength' => 255, 'size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->add_action_buttons(true, get_string('createcampaign_submit', 'local_mailwhistle'));
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

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $errors['name'] = get_string('required');
        } else if (\core_text::strlen($name) > 255) {
            $errors['name'] = get_string('maximumchars', '', 255);
        }

        return $errors;
    }
}
