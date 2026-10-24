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

use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\manager\placeholder_manager;
use local_mailwhistle\manager\template_manager;
use local_mailwhistle\builder\document;
use local_mailwhistle\builder\html_renderer;
use local_mailwhistle\builder\schema;

/**
 * Tests for template persistence.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\template_manager
 */
final class template_manager_test extends \advanced_testcase {
    /**
     * A saved HTML template cannot be replaced by stale builder blocks.
     */
    public function test_update_cannot_switch_saved_html_to_builder(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $template = $generator->create_template(['bodyhtml' => '<p>Keep this HTML</p>']);
        try {
            template_manager::update($template->id, (object) [
                'name' => 'Changed name',
                'editormode' => 'builder',
                'builderjson' => schema::default_json(),
            ]);
            $this->fail('Changing a saved editor mode must be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('template_mode_locked', $exception->errorcode);
        }
        $this->assertEquals($template, template_manager::get($template->id));
    }

    /**
     * A saved builder template cannot silently switch to an older HTML body.
     */
    public function test_update_cannot_switch_saved_builder_to_html(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $template = $generator->create_template(['editormode' => 'builder']);
        try {
            template_manager::update($template->id, (object) [
                'name' => 'Changed name',
                'editormode' => 'html',
                'bodyhtml_editor' => ['text' => '<p>Stale content</p>'],
            ]);
            $this->fail('Changing a saved editor mode must be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('template_mode_locked', $exception->errorcode);
        }
        $this->assertEquals($template, template_manager::get($template->id));
    }

    /**
     * An HTML copy uses the current blocks and preserves the original builder template.
     */
    public function test_html_copy_renders_unsaved_blocks_and_keeps_original(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $template = $generator->create_template(['name' => 'Original', 'editormode' => 'builder']);
        $json = json_encode(['blocks' => [['type' => 'text', 'content' => 'Unsaved block change']]]);
        $id = template_manager::create_html_copy($template->id, (object) [
            'name' => 'Original',
            'previewtext' => 'New preview',
            'background' => '#123456',
            'editormode' => 'builder',
            'builderjson' => $json,
            'bodyhtml_editor' => ['text' => '<p>Stale HTML</p>'],
        ]);
        $copy = template_manager::get($id);
        $this->assertNotEquals($template->id, $id);
        $this->assertEquals($template, template_manager::get($template->id));
        $this->assertSame('HTML copy of Original', $copy->name);
        $this->assertSame('html', $copy->editormode);
        $this->assertSame('New preview', $copy->previewtext);
        $this->assertSame('#123456', $copy->background);
        $this->assertSame(schema::default_json(), $copy->builderjson);
        $this->assertSame(html_renderer::render(document::normalise_json($json), '#123456'), $copy->bodyhtml);
        $this->assertStringContainsString('Unsaved block change', $copy->bodyhtml);
        $this->assertStringNotContainsString('Stale HTML', $copy->bodyhtml);

        template_manager::update($id, (object) [
            'name' => $copy->name,
            'editormode' => 'html',
            'bodyhtml_editor' => ['text' => '<p>Edited HTML copy</p>'],
        ]);
        $this->assertSame('<p>Edited HTML copy</p>', template_manager::get($id)->bodyhtml);
        $this->assertEquals($template, template_manager::get($template->id));
    }

    /**
     * Invalid submitted blocks cannot be normalised away during conversion.
     */
    public function test_invalid_builder_copy_does_not_write(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $template = $generator->create_template(['editormode' => 'builder']);
        $count = $DB->count_records('local_mailwhistle_templates');
        try {
            template_manager::create_html_copy($template->id, (object) [
                'name' => 'Invalid copy',
                'editormode' => 'builder',
                'builderjson' => '{invalid',
            ]);
            $this->fail('Invalid content must not create a copy.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('template_builder_invalid', $exception->errorcode);
        }
        $this->assertSame($count, $DB->count_records('local_mailwhistle_templates'));
        $this->assertEquals($template, template_manager::get($template->id));
    }

    /**
     * New templates cannot discard unsaved content from the unselected editor.
     *
     * @dataProvider conflicting_content_provider
     * @param string $mode Selected mode.
     * @param string $json Builder content.
     * @param string $html HTML content.
     */
    public function test_create_rejects_unsaved_mode_changes(string $mode, string $json, string $html): void {
        global $DB;
        $this->resetAfterTest();
        $count = $DB->count_records('local_mailwhistle_templates');
        try {
            template_manager::create((object) [
                'name' => 'Conflicting editors',
                'editormode' => $mode,
                'builderjson' => $json,
                'bodyhtml_editor' => ['text' => $html],
            ]);
            $this->fail('Content in the other editor must not be discarded.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('template_mode_unsaved', $exception->errorcode);
        }
        $this->assertSame($count, $DB->count_records('local_mailwhistle_templates'));
    }

    /**
     * Content that would be lost by selecting the other mode.
     *
     * @return array
     */
    public static function conflicting_content_provider(): array {
        return [
            'builder blocks' => ['html', '{"blocks":[{"type":"text","content":"Keep me"}]}', ''],
            'html text' => ['builder', '{"blocks":[]}', '<p>Keep me</p>'],
            'html image' => ['builder', '{"blocks":[]}', '<img src="https://example.com/image.png" alt="">'],
        ];
    }

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
     * Builder image URLs are written into the stored HTML body.
     */
    public function test_create_builder_template_keeps_image_url(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $url = 'https://example.com/pluginfile.php/1/local_mailwhistle/resources/0/hero.jpg';
        $id = template_manager::create((object) [
            'name' => 'With image',
            'previewtext' => '',
            'background' => '#ffffff',
            'editormode' => 'builder',
            'builderjson' => json_encode([
                'blocks' => [
                    ['type' => 'image', 'url' => $url, 'alt' => 'Hero'],
                ],
            ]),
            'bodyhtml_editor' => ['text' => '', 'format' => FORMAT_HTML],
        ]);
        $template = template_manager::get($id);
        $this->assertNotNull($template);
        $this->assertStringContainsString('hero.jpg', (string) $template->bodyhtml);
        $this->assertStringContainsString('local_mailwhistle/resources', (string) $template->bodyhtml);
    }

    /**
     * URL placeholders survive create/update and resolve in the stored email HTML.
     */
    public function test_builder_url_placeholders_survive_save_and_personalisation(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $id = template_manager::create((object) [
            'name' => 'Personalised links',
            'editormode' => 'builder',
            'builderjson' => json_encode(['blocks' => [
                ['type' => 'button', 'url' => '{{unsubscribe}}'],
                ['type' => 'social', 'url1' => '{{siteurl}}', 'url2' => 'https://example.com/?a=1&b=2', 'url3' => '#'],
                ['type' => 'text', 'content' => "Research & Development\nSecond line"],
            ]]),
        ]);
        $template = template_manager::get($id);
        $this->assertStringContainsString('href="{{unsubscribe}}"', $template->bodyhtml);
        $this->assertStringContainsString('href="{{siteurl}}"', $template->bodyhtml);
        $this->assertStringContainsString('Research &amp; Development<br', $template->bodyhtml);

        template_manager::update($id, $template);
        $updated = template_manager::get($id);
        $this->assertSame($template->builderjson, $updated->builderjson);
        $this->assertSame($template->bodyhtml, $updated->bodyhtml);
        $blocks = json_decode($updated->builderjson, true)['blocks'];
        $this->assertSame('{{unsubscribe}}', $blocks[0]['url']);
        $this->assertSame('{{siteurl}}', $blocks[1]['url1']);

        $recipient = (object) ['unsubscribeurl' => 'https://example.com/unsubscribe.php?token=sample'];
        $html = placeholder_manager::apply($updated->bodyhtml, $recipient);
        $this->assertStringContainsString('href="' . s($recipient->unsubscribeurl) . '"', $html);
        $this->assertStringContainsString('href="' . s(rtrim($CFG->wwwroot, '/')) . '"', $html);
        $this->assertStringContainsString('href="https://example.com/?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('href="#"', $html);
        $this->assertStringNotContainsString('{{', $html);
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

    /**
     * Usage is true only when a non-draft campaign stores the template id.
     */
    public function test_has_usage_for_non_draft_campaigns(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $template = $generator->create_template(['name' => 'Used layout']);
        $tid = (int) $template->id;

        $this->assertFalse(template_manager::has_usage($tid));

        $draft = $generator->create_campaign([
            'status' => campaign_manager::STATUS_DRAFT,
            'templateid' => $tid,
        ]);
        $this->assertFalse(template_manager::has_usage($tid));
        $this->assertNotEmpty($draft->id);

        $generator->create_campaign([
            'status' => campaign_manager::STATUS_SENT,
            'templateid' => $tid,
        ]);
        $this->assertTrue(template_manager::has_usage($tid));

        $this->expectException(\moodle_exception::class);
        template_manager::delete($tid);
    }
}
