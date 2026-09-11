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

/**
 * Template create/edit form.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_form extends \moodleform {
    /**
     * Define the form fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $submitlabel = $this->_customdata['submitlabel'] ?? get_string('savechanges');

        $mform->addElement('text', 'name', get_string('template_name', 'local_mailwhistle'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'previewtext', get_string('template_previewtext', 'local_mailwhistle'), ['size' => 60]);
        $mform->setType('previewtext', PARAM_TEXT);
        $mform->addRule('previewtext', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'background', get_string('template_background', 'local_mailwhistle'), [
            'size' => 10,
            'maxlength' => 7,
            'class' => 'local-mailwhistle-template-background',
        ]);
        $mform->setType('background', PARAM_TEXT);
        $mform->setDefault('background', '#ffffff');

        $mform->addElement('select', 'editormode', get_string('template_editormode', 'local_mailwhistle'), [
            'builder' => get_string('template_editormode_builder', 'local_mailwhistle'),
            'html' => get_string('template_editormode_html', 'local_mailwhistle'),
        ]);
        $mform->setType('editormode', PARAM_ALPHA);
        $mform->setDefault('editormode', 'builder');

        $mform->addElement(
            'static',
            'placeholdershint',
            '',
            \html_writer::div(
                \local_mailwhistle\manager\placeholder_manager::cheatsheet_text(),
                'local-mailwhistle-placeholders-hint'
            )
        );

        $mform->addElement('hidden', 'builderjson');
        $mform->setType('builderjson', PARAM_RAW);

        $resourcelist = [];
        foreach (resources::get_available_files() as $filename => $url) {
            $resourcelist[] = [
                'filename' => $filename,
                'url' => $url->out(false),
            ];
        }
        $config = [
            'builderjson' => $this->_customdata['builderjson'] ?? '',
            'strings' => $this->_customdata['builderstrings'] ?? [],
            'resources' => $resourcelist,
            'resourcesurl' => (new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'resources']))->out(false),
        ];
        $configjson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($configjson === false) {
            $configjson = '{}';
        }
        $builderid = 'local-mailwhistle-template-builder';
        // Resource URLs make this config larger than js_call_amd's 1024-character argument warning.
        $builderhtml = \html_writer::div(
            \html_writer::div(
                get_string('template_builder_loading', 'local_mailwhistle'),
                'local-mailwhistle-builder-empty'
            ),
            'local-mailwhistle-builder',
            [
                'id' => $builderid,
                'data-config' => $configjson,
            ]
        );
        $mform->addElement('html', $builderhtml);

        global $PAGE;
        $PAGE->requires->js_call_amd('local_mailwhistle/template_builder', 'init');

        $editoroptions = [
            'maxfiles' => 0,
            'noclean' => false,
            'trusttext' => false,
        ];
        $mform->addElement('editor', 'bodyhtml_editor', get_string('template_bodyhtml', 'local_mailwhistle'), null, $editoroptions);
        $mform->setType('bodyhtml_editor', PARAM_RAW);
        $mform->addHelpButton('bodyhtml_editor', 'placeholders', 'local_mailwhistle');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, $submitlabel);
    }

    /**
     * Validate submitted template fields.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = get_string('required');
        }

        $background = trim((string) ($data['background'] ?? ''));
        if ($background !== '' && !preg_match('/^#[0-9a-f]{6}$/i', $background)) {
            $errors['background'] = get_string('template_background_invalid', 'local_mailwhistle');
        }

        $mode = $data['editormode'] ?? '';
        if (!in_array($mode, ['builder', 'html'], true)) {
            $errors['editormode'] = get_string('invaliddata');
        }

        if ($mode === 'builder') {
            $builderjson = trim((string) ($data['builderjson'] ?? ''));
            $buildererrors = \local_mailwhistle\builder\document::validate_json($builderjson);
            if (!empty($buildererrors)) {
                $errors['editormode'] = reset($buildererrors);
            }
        }

        return $errors;
    }
}
