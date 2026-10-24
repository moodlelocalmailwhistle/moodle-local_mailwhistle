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

use local_mailwhistle\output\resources;
use local_mailwhistle\output\template_picker;
use local_mailwhistle\manager\template_manager;

/**
 * Wizard step 2: campaign content (subject and body).
 *
 * Collects the email subject and the HTML body. The body uses a Moodle editor
 * element; the controller stores the HTML in bodyhtml and a plain-text version
 * in bodytext.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_content_form extends \moodleform {
    /**
     * Define the form fields.
     *
     * @return void
     */
    protected function definition(): void {
        global $PAGE;
        $mform = $this->_form;

        $mform->addElement('hidden', 'campaignid');
        $mform->setType('campaignid', PARAM_INT);

        $mform->addElement('hidden', 'templateid');
        $mform->setType('templateid', PARAM_INT);

        $templates = template_manager::get_list(template_manager::FILTER_ACTIVE);
        if ($templates) {
            $label = get_string('campaign_usetemplate', 'local_mailwhistle');
            $choices = [0 => get_string('campaign_template_choose', 'local_mailwhistle')];
            foreach ($templates as $template) {
                $choices[$template->id] = format_string($template->name);
            }
            $controls = [
                $mform->createElement('select', 'templatechoice', $label, $choices, [
                    'aria-label' => $label,
                    'class' => 'ignoredirty',
                ]),
                $mform->createElement('button', 'loadtemplate', get_string('campaign_template_load', 'local_mailwhistle'), [
                    'disabled' => 'disabled',
                ]),
            ];
            $mform->addGroup($controls, 'templatepickercontrols', $label, ' ', false);
            $mform->setType('templatechoice', PARAM_INT);
            $renderer = $PAGE->get_renderer('local_mailwhistle');
            $picker = new template_picker((int) ($this->_customdata['campaignid'] ?? 0), 0, $templates);
            $mform->addElement('static', 'templatepreview', '', $renderer->render($picker));
        }

        $mform->addElement('text', 'subject', get_string('subject', 'local_mailwhistle'), ['maxlength' => 255, 'size' => 50]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('subject', 'placeholders', 'local_mailwhistle');

        // Plain HTML editor; no file areas needed for the draft body preview.
        $mform->addElement('editor', 'body', get_string('body', 'local_mailwhistle'), null, ['enable_filemanagement' => false]);
        $mform->setType('body', PARAM_RAW);
        $mform->addHelpButton('body', 'placeholders', 'local_mailwhistle');
        $mform->addElement(
            'static',
            'placeholdershint',
            '',
            \html_writer::div(
                \local_mailwhistle\manager\placeholder_manager::cheatsheet_text(),
                'local-mailwhistle-placeholders-hint'
            )
        );

        $options = [];
        foreach (array_keys(resources::get_all_files()) as $filename) {
            $options[$filename] = $filename;
        }
        $mform->addElement(
            'autocomplete',
            'attachments',
            get_string('campaign_attachments', 'local_mailwhistle'),
            $options,
            [
                'multiple' => true,
                'noselectionstring' => get_string('campaign_attachments_none', 'local_mailwhistle'),
            ]
        );
        $mform->addHelpButton('attachments', 'campaign_attachments', 'local_mailwhistle');
        if (empty($options)) {
            $mform->addElement('static', 'attachmentsempty', '', get_string('campaign_attachments_empty', 'local_mailwhistle'));
        }

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
        if (!\local_mailwhistle\manager\campaign_manager::has_meaningful_body($data['body']['text'] ?? '')) {
            $errors['body'] = get_string('campaignemptybody', 'local_mailwhistle');
        }

        $posted = $data['attachments'] ?? [];
        if (!is_array($posted)) {
            $posted = ($posted === '' || $posted === null) ? [] : [$posted];
        }
        $allowed = array_keys(resources::get_all_files());
        foreach ($posted as $filename) {
            if ($filename !== '' && !in_array($filename, $allowed, true)) {
                $errors['attachments'] = get_string('campaign_attachments_invalid', 'local_mailwhistle');
                break;
            }
        }

        return $errors;
    }
}
