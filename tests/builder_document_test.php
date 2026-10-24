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
     * Valid URL values survive every builder URL field and repeated normalisation.
     *
     * @dataProvider valid_url_provider
     * @param string $url Submitted URL.
     * @param string $expected Normalised URL.
     */
    public function test_normalise_preserves_valid_urls(string $url, string $expected): void {
        $this->resetAfterTest();
        $json = json_encode(['blocks' => [
            ['type' => 'button', 'url' => $url],
            ['type' => 'social', 'url1' => $url, 'url2' => $url, 'url3' => $url],
        ]]);

        $this->assertEmpty(document::validate_json($json));
        $normalised = document::normalise_json($json);
        $blocks = json_decode($normalised, true)['blocks'];
        $this->assertSame($expected, $blocks[0]['url']);
        foreach (['url1', 'url2', 'url3'] as $field) {
            $this->assertSame($expected, $blocks[1][$field]);
        }
        $this->assertSame($normalised, document::normalise_json($normalised));
    }

    /**
     * URL values accepted by the builder.
     *
     * @return array
     */
    public static function valid_url_provider(): array {
        return [
            'site placeholder' => ['{{siteurl}}', '{{siteurl}}'],
            'unsubscribe placeholder' => ['{{unsubscribe}}', '{{unsubscribe}}'],
            'placeholder spacing and case' => [' {{ SITEURL }} ', '{{ SITEURL }}'],
            'http' => ['http://example.com/news', 'http://example.com/news'],
            'https query' => ['https://example.com/?a=1&b=2', 'https://example.com/?a=1&b=2'],
            'trimmed https' => [' https://example.com/news ', 'https://example.com/news'],
            'hash' => ['#', '#'],
            'empty' => ['', ''],
        ];
    }

    /**
     * Normalisation cannot introduce unsafe URLs or non-URL placeholder values.
     *
     * @dataProvider invalid_url_provider
     * @param string $url Rejected URL.
     */
    public function test_unsafe_urls_are_rejected_and_removed(string $url): void {
        $this->resetAfterTest();
        $json = json_encode(['blocks' => [
            ['type' => 'button', 'url' => $url],
            ['type' => 'social', 'url1' => $url, 'url2' => $url, 'url3' => $url],
        ]]);

        $this->assertFalse(document::is_valid_url($url));
        $this->assertNotEmpty(document::validate_json($json));
        $blocks = json_decode(document::normalise_json($json), true)['blocks'];
        $this->assertSame('', $blocks[0]['url']);
        foreach (['url1', 'url2', 'url3'] as $field) {
            $this->assertSame('', $blocks[1][$field]);
        }
    }

    /**
     * Unsafe schemes and values which cannot produce supported URL placeholders.
     *
     * @return array
     */
    public static function invalid_url_provider(): array {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'ftp' => ['ftp://example.com'],
            'protocol relative' => ['//example.com'],
            'recipient text' => ['{{firstname}}'],
            'unknown token' => ['{{unknown}}'],
            'malformed token' => ['{{siteurl" onmouseover="alert(1)}}'],
            'multiple tokens' => ['{{siteurl}}{{unsubscribe}}'],
        ];
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
     * Multiline blocks render text and existing entities once without activating encoded HTML.
     *
     * @dataProvider multiline_block_provider
     * @param string $type Block type.
     * @param string $field Text field.
     */
    public function test_multiline_text_is_escaped_once(string $type, string $field): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $content = "Research & Development\nPartners &amp; friends\n"
            . '&lt;strong&gt;Plain &quot;quoted&quot; text&lt;/strong&gt;';
        $json = json_encode(['blocks' => [['type' => $type, $field => $content]]]);
        $html = html_renderer::render(document::normalise_json($json));

        $this->assertStringContainsString('Research &amp; Development<br', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $document = new \DOMDocument();
        $document->loadHTML($html);
        $this->assertStringContainsString(
            "Research & Development\nPartners & friends\n<strong>Plain \"quoted\" text</strong>",
            $document->textContent
        );
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//strong[contains(., "Plain")]')->length);
        $this->assertSame(2, $document->getElementsByTagName('br')->length);
    }

    /**
     * Submitted HTML cannot become active markup in multiline block text.
     *
     * @dataProvider multiline_block_provider
     * @param string $type Block type.
     * @param string $field Text field.
     */
    public function test_multiline_text_removes_submitted_html(string $type, string $field): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $content = '<img src="x" onerror="alert(1)"><script>alert(1)</script><strong>Safe text</strong>';
        $json = json_encode(['blocks' => [['type' => $type, $field => $content]]]);
        $html = html_renderer::render(document::normalise_json($json));

        $this->assertStringContainsString('Safe text', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<strong>Safe text', $html);
    }

    /**
     * Multiline fields still apply Moodle string filters before rendering.
     *
     * @dataProvider multiline_block_provider
     * @param string $type Block type.
     * @param string $field Text field.
     */
    public function test_multiline_text_keeps_multilang_filtering(string $type, string $field): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->filterall = true;
        $CFG->stringfilters = 'multilang';
        filter_set_global_state('multilang', TEXTFILTER_ON);
        $content = '<span lang="en" class="multilang">English & research</span>'
            . '<span lang="fr" class="multilang">French text</span>';
        $json = json_encode(['blocks' => [['type' => $type, $field => $content]]]);
        $html = html_renderer::render(document::normalise_json($json));

        $this->assertStringContainsString('English &amp; research', $html);
        $this->assertStringNotContainsString('French text', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * Every multiline content field in the visual builder.
     *
     * @return array
     */
    public static function multiline_block_provider(): array {
        return [
            'text' => ['text', 'content'],
            'highlight' => ['highlight', 'content'],
            'left column' => ['columns', 'leftcontent'],
            'right column' => ['columns', 'rightcontent'],
            'footer' => ['footer', 'content'],
        ];
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
