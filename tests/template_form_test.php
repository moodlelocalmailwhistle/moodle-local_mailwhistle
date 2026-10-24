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

use local_mailwhistle\builder\schema;
use local_mailwhistle\form\template_form;
use local_mailwhistle\manager\template_manager;
use local_mailwhistle\page\templates;

/**
 * Regression tests for template form content preservation.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\form\template_form
 * @covers    \local_mailwhistle\page\templates
 */
final class template_form_test extends \advanced_testcase {
    /**
     * Load the Moodle forms library.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/formslib.php');
        $PAGE->set_url(new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates']));
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * Validation errors must redisplay submitted blocks instead of saved defaults.
     */
    public function test_invalid_edit_preserves_submitted_blocks(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $template = $generator->create_template([
            'editormode' => 'builder',
            'builderjson' => schema::default_json(),
        ]);
        $json = json_encode(['blocks' => [['type' => 'header', 'title' => 'My unsaved title']]]);
        template_form::mock_submit([
            'id' => $template->id,
            'name' => '   ',
            'editormode' => 'builder',
            'builderjson' => $json,
            'background' => '#ffffff',
        ]);

        $html = templates::execute('edit', $template->id);

        $this->assertStringContainsString(s($json), $html);
        $this->assertEquals($template, template_manager::get($template->id));
    }

    /**
     * Failed HTML validation must retain the current editor contents.
     */
    public function test_invalid_edit_preserves_submitted_html(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $template = $generator->create_template(['bodyhtml' => '<p>Saved HTML</p>']);
        $body = '<p>Unsaved HTML with {{firstname}}</p>';
        template_form::mock_submit([
            'id' => $template->id,
            'name' => '   ',
            'editormode' => 'html',
            'builderjson' => schema::default_json(),
            'background' => '#ffffff',
            'bodyhtml_editor' => ['text' => $body, 'format' => FORMAT_HTML],
        ]);

        $html = templates::execute('edit', $template->id);

        $this->assertStringContainsString(s($body), $html);
        $this->assertEquals($template, template_manager::get($template->id));
    }

    /**
     * The explicit copy submission creates an HTML template and opens it for editing.
     */
    public function test_save_html_copy_does_not_update_original(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $template = $generator->create_template([
            'name' => 'Builder original',
            'editormode' => 'builder',
            'builderjson' => schema::default_json(),
        ]);
        template_form::mock_submit([
            'id' => $template->id,
            'name' => 'Builder original',
            'editormode' => 'builder',
            'builderjson' => json_encode(['blocks' => [['type' => 'text', 'content' => 'Current copy content']]]),
            'background' => '#ffffff',
            'savehtmlcopy' => 1,
        ]);
        ob_start();
        try {
            templates::execute('edit', $template->id);
            $this->fail('Saving a copy must redirect to its edit form.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('redirecterrordetected', $exception->errorcode);
        } finally {
            ob_end_clean();
        }

        $copy = $DB->get_record('local_mailwhistle_templates', ['name' => 'HTML copy of Builder original'], '*', MUST_EXIST);
        $this->assertSame('html', $copy->editormode);
        $this->assertStringContainsString('Current copy content', $copy->bodyhtml);
        $this->assertEquals($template, template_manager::get($template->id));
    }
}
