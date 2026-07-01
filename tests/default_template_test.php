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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/mailwhistle/lib.php');

/**
 * Tests for the bundled default template seed.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::local_mailwhistle_install_default_template
 */
final class default_template_test extends \advanced_testcase {
    /**
     * The seed inserts one usable builder template.
     */
    public function test_seed_creates_default_template(): void {
        $this->resetAfterTest();
        global $DB;

        $id = local_mailwhistle_install_default_template();

        $this->assertIsInt($id);
        $row = $DB->get_record('local_mailwhistle_templates', ['id' => $id], '*', MUST_EXIST);

        $this->assertSame(get_string('template_default_name', 'local_mailwhistle'), $row->name);
        $this->assertSame('builder', $row->editormode);
        $this->assertNotEmpty($row->builderjson);
        $this->assertNotEmpty($row->bodyhtml);
        $this->assertSame(0, (int) $row->archived);

        // The builderjson must be valid according to the plugin's own validator.
        $this->assertSame([], local_mailwhistle_validate_builder_json($row->builderjson));

        // The seeded document carries the expected default blocks.
        $document = json_decode($row->builderjson, true);
        $this->assertIsArray($document);
        $this->assertCount(4, $document['blocks']);
    }

    /**
     * The seed is idempotent: a second call is a no-op.
     */
    public function test_seed_is_idempotent(): void {
        $this->resetAfterTest();
        global $DB;

        $first = local_mailwhistle_install_default_template();
        $second = local_mailwhistle_install_default_template();

        $this->assertIsInt($first);
        $this->assertNull($second);
        $this->assertSame(
            1,
            $DB->count_records('local_mailwhistle_templates', [
                'name' => get_string('template_default_name', 'local_mailwhistle'),
            ])
        );
    }
}
