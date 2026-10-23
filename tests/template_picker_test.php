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

use local_mailwhistle\output\template_picker;

/**
 * Tests for the campaign content-step template picker.
 *
 * @package   local_mailwhistle
 * @covers    \local_mailwhistle\output\template_picker
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class template_picker_test extends \advanced_testcase {
    /**
     * Configure a page renderer and start with no seeded templates.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB, $PAGE;
        $DB->delete_records('local_mailwhistle_templates');
        $PAGE->set_url(new \moodle_url('/local/mailwhistle/campaign_edit.php'));
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * An empty or entirely archived library does not render a picker.
     */
    public function test_no_active_templates_renders_nothing(): void {
        global $PAGE;
        $renderer = $PAGE->get_renderer('local_mailwhistle');

        $picker = new template_picker(1);
        $this->assertSame(['hastemplates' => false], $picker->export_for_template($renderer));
        $this->assertSame('', trim($renderer->render($picker)));

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $generator->create_template(['name' => 'Archived only', 'archived' => 1]);
        $this->assertSame(['hastemplates' => false], $picker->export_for_template($renderer));
        $this->assertSame('', trim($renderer->render($picker)));
    }

    /**
     * Archived templates cannot be selected or exposed in the preview payload.
     */
    public function test_export_excludes_archived_templates(): void {
        global $PAGE;
        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $active = $generator->create_template(['name' => 'Active layout']);
        $generator->create_template(['name' => 'Archived layout', 'archived' => 1]);

        $picker = new template_picker(1, (int) $active->id);
        $data = $picker->export_for_template($PAGE->get_renderer('local_mailwhistle'));

        $this->assertTrue($data['hastemplates']);
        $this->assertCount(1, $data['options']);
        $this->assertSame((int) $active->id, $data['options'][0]['id']);
        $this->assertSame('Active layout', $data['options'][0]['name']);
        $this->assertTrue($data['options'][0]['selected']);
    }

    /**
     * Previews are sanitised and isolated, while raw editor HTML remains inert data.
     */
    public function test_preview_is_sanitised_and_sandboxed(): void {
        global $PAGE;
        $body = '<p onclick="alert(1)">Hello &amp; welcome</p><script>alert(2)</script>' .
            '<img src="missing.png" onerror="alert(3)"><a href="javascript:alert(4)">Link</a>' .
            '"><img id="escaped-payload" src="missing.png" onerror="alert(5)">';
        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $generator->create_template([
            'name' => 'Preview layout',
            'bodyhtml' => $body,
            'background' => '#eeeeee',
        ]);
        $renderer = $PAGE->get_renderer('local_mailwhistle');
        $picker = new template_picker(1);
        $data = $picker->export_for_template($renderer);
        $preview = $data['options'][0]['preview'];

        $this->assertSame($body, $data['options'][0]['body']);
        $this->assertStringContainsString('Hello &amp; welcome', $preview);
        $this->assertStringContainsString('background:#eeeeee', $preview);
        $this->assertStringNotContainsString('<script', $preview);
        $this->assertStringNotContainsString('onclick=', $preview);
        $this->assertStringNotContainsString('onerror=', $preview);
        $this->assertStringNotContainsString('javascript:', $preview);

        $dom = new \DOMDocument();
        $dom->loadHTML($renderer->render($picker), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);
        $frames = $xpath->query('//iframe[@data-region="preview-frame"]');
        $this->assertSame(1, $frames->length);
        $frame = $frames->item(0);
        $this->assertTrue($frame->hasAttribute('sandbox'));
        $this->assertSame('', $frame->getAttribute('sandbox'));
        $this->assertFalse($frame->hasAttribute('srcdoc'));
        $this->assertSame(0, $xpath->query('//script | //img | //*[@onclick or @onerror]')->length);

        $payloads = $xpath->query('//*[@data-template-id]');
        $this->assertSame(1, $payloads->length);
        $this->assertSame($body, $payloads->item(0)->getAttribute('data-body'));
        $this->assertSame($preview, $payloads->item(0)->getAttribute('data-preview'));
    }

    /**
     * format_string() names must not be escaped a second time in Mustache.
     */
    public function test_render_does_not_double_escape_names(): void {
        global $PAGE;
        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $generator->create_template(['name' => 'News & Views']);

        $html = $PAGE->get_renderer('local_mailwhistle')->render(new template_picker(1));
        $this->assertStringContainsString('News &amp; Views', $html);
        $this->assertStringNotContainsString('News &amp;amp; Views', $html);
    }
}
