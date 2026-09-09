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

use local_mailwhistle\manager\template_manager;

/**
 * Tests for template persistence.
 *
 * @package   local_mailwhistle
 * @copyright 2024 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\template_manager
 */
final class template_manager_test extends \advanced_testcase {
    /**
     * Create, fetch, archive, and restore a template.
     */
    public function test_create_get_archive_restore(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = (object) [
            'name' => 'Plain HTML',
            'previewtext' => 'A simple layout',
            'background' => '#ffffff',
            'editormode' => 'html',
            'builderjson' => json_encode(['blocks' => []]),
            'bodyhtml_editor' => ['text' => '<p>Hello from a template</p>', 'format' => FORMAT_HTML],
        ];
        $id = template_manager::create($data);
        $this->assertGreaterThan(0, $id);

        $template = template_manager::get($id);
        $this->assertNotNull($template);
        $this->assertSame('Plain HTML', $template->name);
        $this->assertStringContainsString('Hello from a template', (string) $template->bodyhtml);

        template_manager::archive($id);
        $this->assertSame(1, (int) template_manager::get($id)->archived);
        $activeids = array_map('intval', array_column(template_manager::get_list(template_manager::FILTER_ACTIVE), 'id'));
        $archivedids = array_map('intval', array_column(template_manager::get_list(template_manager::FILTER_ARCHIVED), 'id'));
        $this->assertNotContains($id, $activeids);
        $this->assertContains($id, $archivedids);

        template_manager::restore($id);
        $this->assertSame(0, (int) template_manager::get($id)->archived);
        $activeids = array_map('intval', array_column(template_manager::get_list(template_manager::FILTER_ACTIVE), 'id'));
        $this->assertContains($id, $activeids);
    }

    /**
     * Import skips invalid JSON and duplicate names.
     */
    public function test_import_json_skips_invalid_and_duplicates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(0, template_manager::import_json('{not json'));
        $this->assertSame(0, template_manager::import_json(json_encode(['format' => 'other'])));

        $payload = [
            'format' => 'local_mailwhistle_template',
            'template' => [
                'name' => 'Imported layout',
                'previewtext' => '',
                'background' => '#ffffff',
                'editormode' => 'html',
                'builder' => ['blocks' => []],
                'html' => '<p>Imported</p>',
            ],
        ];
        $first = template_manager::import_json(json_encode($payload));
        $this->assertGreaterThan(0, $first);
        $this->assertSame(0, template_manager::import_json(json_encode($payload)));
    }

    /**
     * Placeholders are extracted in first-seen order without duplicates.
     */
    public function test_extract_placeholders(): void {
        $template = (object) [
            'previewtext' => 'Hi {{firstname}}',
            'bodyhtml' => '<p>{{firstname}} {{lastname}} {{firstname}}</p>',
        ];
        $this->assertSame(['firstname', 'lastname'], template_manager::extract_placeholders($template));
        $this->assertSame([], template_manager::extract_placeholders(null));
    }
}
