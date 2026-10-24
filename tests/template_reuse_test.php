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

namespace local_mailwhistle;

use local_mailwhistle\builder\document;
use local_mailwhistle\builder\html_renderer;
use local_mailwhistle\builder\schema;
use local_mailwhistle\form\template_import_form;
use local_mailwhistle\manager\template_manager;
use local_mailwhistle\page\templates;

/**
 * Template duplication and native JSON upload regressions.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\template_manager
 * @covers    \local_mailwhistle\form\template_import_form
 * @covers    \local_mailwhistle\page\templates
 */
final class template_reuse_test extends \advanced_testcase {
    /**
     * Isolate templates and prepare the native Moodle form context.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/formslib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates']));
    }

    /**
     * Duplicate preserves both saved representations, resets archive status and generates distinct names.
     */
    public function test_duplicate_preserves_saved_content_and_original(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        foreach (['html', 'builder'] as $mode) {
            $original = $generator->create_template([
                'name' => 'Original ' . $mode,
                'previewtext' => 'Hello {{firstname}}',
                'background' => '#123456',
                'editormode' => $mode,
                'builderjson' => '{ "blocks": [{"type":"text","content":"Saved block"}] }',
                'bodyhtml' => '<div style="color: #123456">Saved HTML &amp; spacing</div>',
                'archived' => 1,
                'timecreated' => 1,
                'timemodified' => 2,
            ]);
            $copy = template_manager::get(template_manager::duplicate($original->id));
            $this->assertNotEquals($original->id, $copy->id);
            $this->assertSame('Copy of Original ' . $mode, $copy->name);
            foreach (['previewtext', 'background', 'editormode', 'builderjson', 'bodyhtml'] as $field) {
                $this->assertSame($original->$field, $copy->$field);
            }
            $this->assertSame(0, (int) $copy->archived);
            $this->assertGreaterThan(2, $copy->timecreated);
            $this->assertSame($copy->timecreated, $copy->timemodified);
            $another = template_manager::get(template_manager::duplicate($original->id));
            $this->assertSame('Copy of Original ' . $mode . ' (2)', $another->name);
            template_manager::update($copy->id, (object) [
                'name' => 'Edited copy',
                'editormode' => $mode,
                'builderjson' => $copy->builderjson,
                'bodyhtml_editor' => ['text' => '<p>Changed copy</p>'],
            ]);
            $this->assertEquals($original, template_manager::get($original->id));
        }
    }

    /**
     * Uploading the actual export preserves content, ignores record identity and never overwrites.
     */
    public function test_export_import_round_trip_in_both_modes(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $builderjson = document::normalise_json(json_encode(['blocks' => [
            ['type' => 'text', 'content' => 'Hello {{firstname}}'],
            ['type' => 'image', 'url' => 'https://original.example/pluginfile.php/1/logo.png'],
        ]]));
        foreach (['html', 'builder'] as $mode) {
            $original = $generator->create_template([
                'name' => 'Exported ' . $mode,
                'previewtext' => 'Preview {{firstname}}',
                'background' => '#123456',
                'editormode' => $mode,
                'builderjson' => $builderjson,
                'bodyhtml' => $mode === 'builder' ? html_renderer::render($builderjson, '#123456')
                    : '<p>HTML {{firstname}}</p><img src="https://original.example/pluginfile.php/1/logo.png" alt="Logo">',
            ]);
            $payload = template_manager::export_payload($original);
            $payload['template'] += ['id' => $original->id, 'createdby' => 999, 'archived' => 1, 'timecreated' => 1];
            $json = json_encode($payload);
            $this->assertNull(template_manager::validate_import_json($json));
            $copy = template_manager::get(template_manager::import_uploaded_json($json));
            $this->assertNotEquals($original->id, $copy->id);
            $this->assertSame('Copy of Exported ' . $mode, $copy->name);
            foreach (['previewtext', 'background', 'editormode', 'builderjson', 'bodyhtml'] as $field) {
                $this->assertSame($original->$field, $copy->$field);
            }
            $this->assertSame(0, (int) $copy->archived);
            $this->assertGreaterThan(1, $copy->timecreated);
            $this->assertSame('Copy of Exported ' . $mode . ' (2)', template_manager::get(
                template_manager::import_uploaded_json($json)
            )->name);
            $this->assertEquals($original, template_manager::get($original->id));
        }
    }

    /**
     * Malformed uploads are rejected before any write, including nested values that would cause cast warnings.
     */
    public function test_invalid_exports_do_not_create_templates(): void {
        global $DB;
        $original = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_template();
        $valid = template_manager::export_payload($original);
        $invalid = ['', '   ', 'not JSON', str_repeat(' ', template_manager::MAX_IMPORT_BYTES + 1)];
        foreach (['format' => 'other', 'formatversion' => 2, 'template' => 'not an object'] as $field => $value) {
            $payload = $valid;
            $payload[$field] = $value;
            $invalid[] = json_encode($payload);
        }
        foreach (
            [
            ['name', ['nested']], ['name', '   '], ['name', str_repeat('a', 256)],
            ['previewtext', str_repeat('a', 256)], ['html', ['nested']], ['editormode', 'other'],
            ['background', 'red'], ['builder', ['blocks' => [['type' => 'text', 'content' => ['nested']]]]],
            ['builder', ['blocks' => ['named' => ['type' => 'text']]]],
            ['builder', ['blocks' => [['type' => 'unsupported']]]],
            ['builder', ['blocks' => [['type' => 'image', 'url' => 'javascript:alert(1)']]]],
            ] as [$field, $value]
        ) {
            $payload = $valid;
            $payload['template'][$field] = $value;
            $invalid[] = json_encode($payload);
        }
        $before = $DB->count_records('local_mailwhistle_templates');
        foreach ($invalid as $json) {
            $this->assertNotNull(template_manager::validate_import_json($json));
            try {
                template_manager::import_uploaded_json($json);
                $this->fail('Invalid JSON must not create a template.');
            } catch (\moodle_exception $exception) {
                $this->assertContains($exception->errorcode, [
                    'template_import_invalid', 'template_import_version', 'template_builder_invalid',
                ]);
            }
            $this->assertSame($before, $DB->count_records('local_mailwhistle_templates'));
        }
    }

    /**
     * Create a native filepicker upload in the current user's draft area.
     *
     * @param string $json File content.
     * @return int Draft item id.
     */
    private function upload(string $json): int {
        global $USER;
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid,
            'contextid' => \context_user::instance($USER->id)->id,
            'filename' => 'template.json', 'filepath' => '/',
        ], $json);
        return $draftid;
    }

    /**
     * The real upload form creates a new record and redirects to its editor.
     */
    public function test_native_upload_creates_copy(): void {
        global $DB;
        $template = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_template([
            'name' => 'Uploaded original', 'bodyhtml' => '<p>Uploaded HTML</p>',
        ]);
        $draftid = $this->upload(json_encode(template_manager::export_payload($template)));
        template_import_form::mock_submit(['templatefile' => $draftid, 'submitbutton' => 1, 'sesskey' => sesskey()]);
        ob_start();
        try {
            templates::execute('import');
            $this->fail('A successful upload must redirect.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('redirecterrordetected', $exception->errorcode);
        } finally {
            ob_end_clean();
        }
        $copy = $DB->get_record('local_mailwhistle_templates', ['name' => 'Copy of Uploaded original'], '*', MUST_EXIST);
        $this->assertSame($template->bodyhtml, $copy->bodyhtml);
        $this->assertEquals($template, template_manager::get($template->id));
    }

    /**
     * Invalid uploaded JSON remains on the upload form with an actionable field error.
     */
    public function test_native_upload_shows_validation_error_without_writes(): void {
        global $DB;
        $before = $DB->count_records('local_mailwhistle_templates');
        template_import_form::mock_submit(['templatefile' => $this->upload('{invalid'), 'submitbutton' => 1]);
        $html = templates::execute('import');
        $this->assertStringContainsString('Choose a valid Mail Whistle template JSON export', $html);
        $this->assertSame($before, $DB->count_records('local_mailwhistle_templates'));
    }

    /**
     * A submitted draft id cannot import a file belonging to another user.
     */
    public function test_upload_cannot_read_another_users_draft(): void {
        global $DB;
        $original = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_template();
        $this->setUser($this->getDataGenerator()->create_user());
        $draftid = $this->upload(json_encode(template_manager::export_payload($original)));
        $this->setAdminUser();
        $before = $DB->count_records('local_mailwhistle_templates');
        template_import_form::mock_submit(['templatefile' => $draftid, 'submitbutton' => 1]);
        $form = new template_import_form();
        $this->assertNull($form->get_data());
        $this->assertArrayHasKey('templatefile', $form->validation(['templatefile' => $draftid], []));
        $this->assertSame($before, $DB->count_records('local_mailwhistle_templates'));
    }

    /**
     * Duplicate requests require a POST, a valid session key and manage capability.
     */
    public function test_duplicate_requires_authorised_post(): void {
        global $DB;
        $template = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_template();
        $before = $DB->count_records('local_mailwhistle_templates');
        $previous = [$_GET, $_POST];
        try {
            $_GET = ['sesskey' => sesskey()];
            $_POST = [];
            try {
                templates::execute('duplicate', $template->id);
                $this->fail('GET cannot duplicate a template.');
            } catch (\moodle_exception $exception) {
                $this->assertSame('invalidrequest', $exception->errorcode);
            }
            $_GET = [];
            $_POST = ['sesskey' => 'invalid'];
            try {
                templates::execute('duplicate', $template->id);
                $this->fail('A valid session key is required.');
            } catch (\moodle_exception $exception) {
                $this->assertSame('invalidsesskey', $exception->errorcode);
            }
            $this->setUser($this->getDataGenerator()->create_user());
            $_POST = ['sesskey' => sesskey()];
            foreach (['duplicate', 'import'] as $action) {
                try {
                    templates::execute($action, $template->id);
                    $this->fail('Manage capability is required.');
                } catch (\required_capability_exception $exception) {
                    $this->assertSame('nopermissions', $exception->errorcode);
                }
            }
            $this->assertSame($before, $DB->count_records('local_mailwhistle_templates'));
        } finally {
            [$_GET, $_POST] = $previous;
        }
    }
}
