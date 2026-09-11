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

use local_mailwhistle\manager\placeholder_manager;

/**
 * Tests for the campaign personalisation placeholder engine.
 *
 * @package   local_mailwhistle
 * @covers    \local_mailwhistle\manager\placeholder_manager
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class placeholder_manager_test extends \advanced_testcase {
    /**
     * A recipient snapshot stub.
     *
     * @param string $first First name.
     * @param string $last Last name.
     * @param string $email Email.
     * @return \stdClass
     */
    private function recipient(string $first, string $last, string $email): \stdClass {
        return (object) ['firstname' => $first, 'lastname' => $last, 'email' => $email];
    }

    /**
     * Known tokens are replaced from the recipient snapshot.
     */
    public function test_known_tokens_are_replaced(): void {
        $r = $this->recipient('Sofia', 'Ng', 'sofia@example.com');
        $this->assertSame(
            'Hi Sofia Ng <sofia@example.com>',
            placeholder_manager::apply('Hi {{firstname}} {{lastname}} <{{email}}>', $r, false)
        );
    }

    /**
     * fullname combines first and last name.
     */
    public function test_fullname_token(): void {
        $r = $this->recipient('Sofia', 'Ng', 'x@y.z');
        $this->assertSame('Sofia Ng', placeholder_manager::apply('{{fullname}}', $r, false));
    }

    /**
     * Unknown tokens are blanked so no raw {{...}} reaches the recipient.
     */
    public function test_unknown_tokens_are_blanked(): void {
        $r = $this->recipient('Sofia', 'Ng', 'x@y.z');
        $this->assertSame(
            'Hi Sofia, welcome to ',
            placeholder_manager::apply('Hi {{firstname}}, welcome to {{notatoken}}', $r, false)
        );
    }

    /**
     * Site tokens come from the current Moodle site; university aliases sitename.
     */
    public function test_site_tokens(): void {
        global $CFG, $SITE;

        $this->resetAfterTest();
        $r = $this->recipient('Sofia', 'Ng', 'x@y.z');
        $sitename = format_string((string) $SITE->fullname, true, ['context' => \context_system::instance()]);
        $this->assertSame($sitename, placeholder_manager::apply('{{sitename}}', $r, false));
        $this->assertSame($sitename, placeholder_manager::apply('{{university}}', $r, false));
        $this->assertSame(rtrim((string) $CFG->wwwroot, '/'), placeholder_manager::apply('{{siteurl}}', $r, false));
        $this->assertContains('sitename', placeholder_manager::supported_tokens());
        $this->assertStringContainsString('{{firstname}}', placeholder_manager::cheatsheet_text());
        $this->assertStringContainsString('{{sitename}}', placeholder_manager::cheatsheet_text());
    }

    /**
     * Matching is case-insensitive and tolerates inner whitespace.
     */
    public function test_case_insensitive_and_trimmed(): void {
        $r = $this->recipient('Sofia', 'Ng', 'x@y.z');
        $this->assertSame('Sofia Sofia Ng', placeholder_manager::apply('{{FirstName}} {{ fullname }}', $r, false));
    }

    /**
     * HTML context escapes values to prevent broken markup / injection.
     */
    public function test_html_context_escapes_values(): void {
        $r = $this->recipient('<b>Sof</b>', 'A&B', 'x@y.z');
        $out = placeholder_manager::apply('Hi {{firstname}} {{lastname}}', $r, true);
        $this->assertStringContainsString('&lt;b&gt;Sof&lt;/b&gt;', $out);
        $this->assertStringContainsString('A&amp;B', $out);
        $this->assertStringNotContainsString('<b>Sof</b>', $out);
    }

    /**
     * Text with no tokens is returned unchanged.
     */
    public function test_text_without_tokens_is_unchanged(): void {
        $r = $this->recipient('A', 'B', 'x@y.z');
        $this->assertSame('No tokens here.', placeholder_manager::apply('No tokens here.', $r));
    }

    /**
     * Single-brace text is not treated as a token.
     */
    public function test_single_brace_is_not_a_token(): void {
        $r = $this->recipient('Sofia', 'Ng', 'x@y.z');
        $this->assertSame('Visit {siteurl} now', placeholder_manager::apply('Visit {siteurl} now', $r, false));
    }
}
