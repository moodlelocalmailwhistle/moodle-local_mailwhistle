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
     * format_string() names must not be escaped a second time in Mustache.
     */
    public function test_render_does_not_double_escape_names(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $PAGE;
        $PAGE->set_url(new \moodle_url('/local/mailwhistle/campaign_edit.php'));
        $PAGE->set_context(\context_system::instance());

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $generator->create_template(['name' => 'News & Views']);

        $html = $PAGE->get_renderer('local_mailwhistle')->render(new template_picker(1));
        $this->assertStringContainsString('News &amp; Views', $html);
        $this->assertStringNotContainsString('News &amp;amp; Views', $html);
    }
}
