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

/**
 * Unit tests for Mail Whistle plugin helper class.
 *
 * Tests core functionality of the helper class including
 * configuration management and user data processing.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\helper
 */
final class helper_test extends \advanced_testcase {
    /**
     * Test: Helper class can be instantiated.
     */
    public function test_helper_instantiation(): void {
        $helper = new \local_mailwhistle\helper();
        $this->assertInstanceOf(\local_mailwhistle\helper::class, $helper);
    }

    /**
     * Test: Can get configuration values.
     */
    public function test_get_config(): void {
        $this->resetAfterTest();

        // Set a test config value.
        set_config('test_setting', 'test_value', 'local_mailwhistle');

        // Retrieve and verify the value.
        $value = \local_mailwhistle\helper::get_config('test_setting');
        $this->assertEquals('test_value', $value);
    }

    /**
     * Test: Returns default value when config not found.
     */
    public function test_get_config_with_default(): void {
        $this->resetAfterTest();

        // Retrieve non-existent config with fallback.
        $value = \local_mailwhistle\helper::get_config('non_existent', 'default_value');
        $this->assertEquals('default_value', $value);
    }

    /**
     * Test: Can set configuration values.
     */
    public function test_set_config(): void {
        $this->resetAfterTest();

        // Set a config value.
        $result = \local_mailwhistle\helper::set_config('test_setting', 'test_value');
        $this->assertTrue($result);

        // Verify it was persisted.
        $value = get_config('local_mailwhistle', 'test_setting');
        $this->assertEquals('test_value', $value);
    }

    /**
     * Test: Can process data for valid user.
     */
    public function test_process_user_data_valid_user(): void {
        $this->resetAfterTest();

        // Create a test user.
        $user = $this->getDataGenerator()->create_user();

        // Process the user and verify success.
        $result = \local_mailwhistle\helper::process_user_data($user->id);
        $this->assertTrue($result);
    }

    /**
     * Test: Returns false for non-existent user.
     */
    public function test_process_user_data_invalid_user(): void {
        $this->resetAfterTest();

        // Process non-existent user - should return false.
        $result = \local_mailwhistle\helper::process_user_data(99999);
        $this->assertFalse($result);
    }
}
