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

use local_mailwhistle\output\resources;

/**
 * Tests for public resource image URLs used in templates and mail.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\output\resources
 */
final class resources_test extends \advanced_testcase {
    /**
     * Create a stored file in the resources area.
     *
     * @param string $filename Stored filename.
     * @param string $content File bytes.
     * @return \stored_file
     */
    private function create_resource(string $filename, string $content): \stored_file {
        $fs = get_file_storage();
        return $fs->create_file_from_string(
            [
                'contextid' => \context_system::instance()->id,
                'component' => 'local_mailwhistle',
                'filearea' => resources::FILEAREA,
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $filename,
            ],
            $content
        );
    }

    /**
     * JPEG/PNG in the resources area are treated as public email images.
     */
    public function test_jpeg_is_public_image(): void {
        $this->resetAfterTest();
        $file = $this->create_resource('hero.jpg', file_get_contents(__DIR__ . '/fixtures/image_640x480px.jpg'));
        $this->assertTrue(resources::is_public_image($file));
        $url = resources::public_url($file)->out(false);
        $this->assertStringContainsString('pluginfile.php', $url);
        $this->assertStringContainsString('/local_mailwhistle/resources/', $url);
        $this->assertStringContainsString('hero.jpg', $url);
        $this->assertStringNotContainsString('tokenpluginfile.php', $url);
    }

    /**
     * Directories and non-image files are not public email images.
     */
    public function test_non_image_is_not_public(): void {
        $this->resetAfterTest();
        $file = $this->create_resource('notes.txt', 'not an image');
        $this->assertFalse(resources::is_public_image($file));
    }

    /**
     * Uploaded images appear in the copyable URL list.
     */
    public function test_available_files_lists_public_urls(): void {
        $this->resetAfterTest();
        $this->create_resource('logo.jpg', file_get_contents(__DIR__ . '/fixtures/image_640x480px.jpg'));
        $files = resources::get_available_files();
        $this->assertArrayHasKey('logo.jpg', $files);
        $this->assertStringContainsString('logo.jpg', $files['logo.jpg']->out(false));
    }
}
