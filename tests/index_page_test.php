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

use local_mailwhistle\page\index;

/**
 * Tests for the main plugin page dispatcher.
 *
 * @package   local_mailwhistle
 * @covers    \local_mailwhistle\page\index
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class index_page_test extends \advanced_testcase {
    /**
     * Unknown tabs fall back to campaigns.
     */
    public function test_normalise_tab(): void {
        $this->assertSame('send', index::normalise_tab('send'));
        $this->assertSame('audience', index::normalise_tab('audience'));
        $this->assertSame('templates', index::normalise_tab('templates'));
        $this->assertSame('reports', index::normalise_tab('reports'));
        $this->assertSame('resources', index::normalise_tab('resources'));
        $this->assertSame('send', index::normalise_tab('nope'));
        $this->assertSame('send', index::normalise_tab(''));
    }

    /**
     * The entry script only bootstraps Moodle and dispatches.
     */
    public function test_entry_script_is_thin(): void {
        $src = file_get_contents(__DIR__ . '/../index.php');
        $this->assertStringContainsString('page\\index::execute', $src);
        $this->assertStringNotContainsString('$_SERVER', $src);
        $this->assertDoesNotMatchRegularExpression('/^function\s+/m', $src);
        $this->assertLessThan(45, substr_count($src, "\n"));
    }
}
