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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Template create/edit form.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
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

        $mform->addElement('hidden', 'builderjson');
        $mform->setType('builderjson', PARAM_RAW);

        $config = [
            'builderjson' => $this->_customdata['builderjson'] ?? '',
            'strings' => $this->_customdata['builderstrings'] ?? [],
        ];
        $builderid = 'local-mailwhistle-template-builder';
        $builderhtml = \html_writer::div(
            \html_writer::div(
                get_string('template_builder_loading', 'local_mailwhistle'),
                'local-mailwhistle-builder-empty'
            ),
            'local-mailwhistle-builder',
            [
                'id' => $builderid,
                'data-config' => json_encode($config),
            ]
        );
        $builderhtml .= \html_writer::script(
            "(function() {"
                . "var root = document.getElementById('" . $builderid . "');"
                . "if (!root) { return; }"
                . "var readConfig = function() {"
                . "try { return root.dataset.config ? JSON.parse(root.dataset.config) : {}; } catch (error) { return {}; }"
                . "};"
                . "var start = function() {"
                . "if (window.localMailwhistleTemplateBuilder) {"
                . "window.localMailwhistleTemplateBuilder.init(readConfig());"
                . "}"
                . "};"
                . "if (window.localMailwhistleTemplateBuilder) { start(); return; }"
                . "var script = document.createElement('script');"
                . "script.src = M.cfg.wwwroot + '/local/mailwhistle/js/template_builder.js?v=2026070109';"
                . "script.onload = start;"
                . "document.head.appendChild(script);"
                . "}());"
        );
        $mform->addElement('html', $builderhtml);
        $mform->addElement('html', \html_writer::script(
            "(function() {"
                . "var input = document.getElementById('id_background');"
                . "if (!input) { return; }"
                . "input.setAttribute('type', 'color');"
                . "if (!/^#[0-9a-f]{6}$/i.test(input.value)) { input.value = '#ffffff'; }"
                . "}());"
        ));

        $editoroptions = [
            'maxfiles' => 0,
            'noclean' => false,
            'trusttext' => false,
        ];
        $mform->addElement('editor', 'bodyhtml_editor', get_string('template_bodyhtml', 'local_mailwhistle'), null, $editoroptions);
        $mform->setType('bodyhtml_editor', PARAM_RAW);

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
            $buildererrors = \local_mailwhistle_validate_builder_json($builderjson);
            if (!empty($buildererrors)) {
                $errors['editormode'] = reset($buildererrors);
            }
        }

        return $errors;
    }
}
