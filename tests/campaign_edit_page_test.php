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

use local_mailwhistle\page\campaign_edit;

/**
 * Tests for the campaign edit wizard page controller.
 *
 * @package   local_mailwhistle
 * @covers    \local_mailwhistle\page\campaign_edit
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class campaign_edit_page_test extends \advanced_testcase {
    /**
     * Wizard steps advance in order and stay on review.
     */
    public function test_next_step_advances_then_stops(): void {
        $this->assertSame('content', campaign_edit::next_step('details'));
        $this->assertSame('audience', campaign_edit::next_step('content'));
        $this->assertSame('review', campaign_edit::next_step('audience'));
        $this->assertSame('review', campaign_edit::next_step('review'));
        $this->assertSame('details', campaign_edit::next_step('unknown'));
    }

    /**
     * Step URLs keep the campaign id and set the step param.
     */
    public function test_step_url_sets_step_param(): void {
        $base = new \moodle_url('/local/mailwhistle/campaign_edit.php', ['campaignid' => 7]);
        $url = campaign_edit::step_url($base, 'content');
        $this->assertSame('7', $url->get_param('campaignid'));
        $this->assertSame('content', $url->get_param('step'));
    }

    /**
     * The entry script must not declare helper functions.
     */
    public function test_entry_script_has_no_inline_functions(): void {
        $src = file_get_contents(__DIR__ . '/../campaign_edit.php');
        $this->assertDoesNotMatchRegularExpression(
            '/^function\s+/m',
            $src,
            'Wizard helpers belong in classes/page/campaign_edit.php'
        );
        $this->assertStringNotContainsString('$_SERVER', $src);
    }
}
