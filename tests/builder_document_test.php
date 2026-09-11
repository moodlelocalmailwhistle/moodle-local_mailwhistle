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
use local_mailwhistle\builder\preview;
use local_mailwhistle\builder\schema;

/**
 * Tests for builder document validation and email HTML rendering.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\builder\document
 * @covers    \local_mailwhistle\builder\html_renderer
 * @covers    \local_mailwhistle\builder\preview
 * @covers    \local_mailwhistle\builder\schema
 */
final class builder_document_test extends \advanced_testcase {
    /**
     * Background colours are clamped to a safe hex value.
     */
    public function test_normalise_background_and_editor_mode(): void {
        $this->resetAfterTest();
        $this->assertSame('#ffffff', document::normalise_background('not-a-colour'));
        $this->assertSame('#1f4f82', document::normalise_background('#1f4f82'));
        $this->assertSame('builder', document::normalise_editor_mode('builder'));
        $this->assertSame('html', document::normalise_editor_mode('other'));
    }

    /**
     * javascript: URLs are rejected; http(s), hash, and placeholders are allowed.
     */
    public function test_url_validation(): void {
        $this->resetAfterTest();
        $this->assertTrue(document::is_valid_url('https://example.com'));
        $this->assertTrue(document::is_valid_url('#'));
        $this->assertTrue(document::is_valid_url('{{unsubscribe}}'));
        $this->assertFalse(document::is_valid_url('javascript:alert(1)'));
        $this->assertFalse(document::is_valid_url('ftp://example.com'));
    }

    /**
     * Invalid JSON is replaced with an empty document; valid blocks are kept.
     */
    public function test_normalise_and_validate_json(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(schema::default_json(), document::normalise_json('not-json'));
        $this->assertNotEmpty(document::validate_json('not-json'));

        $json = json_encode([
            'blocks' => [
                ['type' => 'header', 'title' => 'Hello', 'background' => '#1f4f82'],
                ['type' => 'unknown'],
            ],
        ]);
        $normalised = json_decode(document::normalise_json($json), true);
        $this->assertCount(1, $normalised['blocks']);
        $this->assertSame('header', $normalised['blocks'][0]['type']);
        $this->assertSame('Hello', $normalised['blocks'][0]['title']);
        $this->assertNotEmpty(document::validate_json($json));
        $this->assertEmpty(document::validate_json(json_encode($normalised)));
    }

    /**
     * Builder strings include the uploaded-image picker labels.
     */
    public function test_schema_strings_include_image_picker(): void {
        $this->resetAfterTest();
        $strings = schema::strings();
        $this->assertSame(get_string('template_builder_chooseimage', 'local_mailwhistle'), $strings['chooseimage']);
        $this->assertSame(
            get_string('template_builder_chooseimageempty', 'local_mailwhistle'),
            $strings['chooseimageempty']
        );
        $this->assertSame(get_string('template_builder_uploadimages', 'local_mailwhistle'), $strings['uploadimages']);
    }

    /**
     * Builder HTML includes the header title.
     */
    public function test_html_renderer_outputs_header(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $json = json_encode([
            'blocks' => [
                ['type' => 'header', 'title' => 'Campus news', 'subtitle' => 'Week 1'],
            ],
        ]);
        $html = html_renderer::render(document::normalise_json($json));
        $this->assertStringContainsString('Campus news', $html);
        $this->assertStringContainsString('Week 1', $html);
    }

    /**
     * Image blocks keep a public pluginfile URL in the rendered HTML.
     */
    public function test_html_renderer_outputs_image_src(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $url = 'https://example.com/pluginfile.php/1/local_mailwhistle/resources/0/hero.jpg';
        $json = json_encode([
            'blocks' => [
                ['type' => 'image', 'url' => $url, 'alt' => 'Hero'],
            ],
        ]);
        $html = html_renderer::render(document::normalise_json($json));
        $this->assertStringContainsString('hero.jpg', $html);
        $this->assertStringContainsString('local_mailwhistle/resources', $html);
        $this->assertStringContainsString('alt="Hero"', $html);
    }

    /**
     * Preview strips document wrappers and sanitises the body.
     */
    public function test_preview_strips_document_shell(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = preview::prepare_html(
            '<!doctype html><html><head><title>x</title></head><body><p>Hello body</p></body></html>'
        );
        $this->assertStringContainsString('Hello body', $html);
        $this->assertStringNotContainsString('<!doctype', strtolower($html));
    }
}
