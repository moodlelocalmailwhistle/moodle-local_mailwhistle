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
 * @copyright 2026 onwards MoodleDach project
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

    /**
     * Blank names are rejected before creating a draft or logging activity.
     */
    public function test_create_campaign_rejects_blank_names(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $sink = $this->redirectEvents();

        foreach (['', '   ', "\t\r\n"] as $name) {
            $writes = $DB->perf_get_writes();
            try {
                helper::create_campaign($name);
                $this->fail('A blank campaign name must be rejected.');
            } catch (\invalid_parameter_exception $exception) {
                $this->assertSame('Campaign name must not be empty.', $exception->debuginfo);
            }

            $this->assertSame($writes, $DB->perf_get_writes());
            $this->assertFalse($DB->record_exists('local_mailwhistle_campaigns', []));
            $this->assertCount(0, $sink->get_events());
        }
        $sink->close();
    }

    /**
     * Valid names retain their content, campaign defaults, and explicit creator.
     */
    public function test_create_campaign_preserves_name_defaults_and_creator(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $creator = $this->getDataGenerator()->create_user();
        $sink = $this->redirectEvents();

        foreach (['Newsletter', '0', ' Padded name '] as $name) {
            $before = time();
            $id = helper::create_campaign($name, (int) $creator->id);
            $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $id], '*', MUST_EXIST);

            $this->assertSame($name, $campaign->name);
            $this->assertSame(get_string('untitledcampaign', 'local_mailwhistle'), $campaign->subject);
            $this->assertSame('draft', $campaign->status);
            $this->assertSame('moodle_smtp', $campaign->sendengine);
            $this->assertNull($campaign->bodyhtml);
            $this->assertNull($campaign->bodytext);
            $this->assertSame('', $campaign->sendername);
            $this->assertSame('', $campaign->senderemail);
            $this->assertSame((int) $creator->id, (int) $campaign->createdby);
            $this->assertGreaterThanOrEqual($before, (int) $campaign->timecreated);
            $this->assertLessThanOrEqual(time(), (int) $campaign->timecreated);
            $this->assertEquals($campaign->timecreated, $campaign->timemodified);
        }

        $events = $sink->get_events();
        $this->assertCount(3, $events);
        foreach ($events as $event) {
            $this->assertInstanceOf(event\data_created::class, $event);
            $this->assertSame((int) $creator->id, (int) $event->userid);
            $this->assertSame('campaign_created', $event->other['action']);
        }
        $sink->close();
    }

    /**
     * The current user is the default campaign creator.
     */
    public function test_create_campaign_uses_current_user(): void {
        global $DB;
        $this->resetAfterTest();
        $creator = $this->getDataGenerator()->create_user();
        $this->setUser($creator);

        $id = helper::create_campaign('Current user campaign');

        $this->assertEquals($creator->id, $DB->get_field('local_mailwhistle_campaigns', 'createdby', ['id' => $id]));
    }

    /**
     * Writes must be POST; an empty request is rejected.
     */
    public function test_require_post_rejects_get(): void {
        $this->resetAfterTest();
        $previous = $_POST;
        $_POST = [];
        try {
            $this->expectException(\moodle_exception::class);
            helper::require_post();
        } finally {
            $_POST = $previous;
        }
    }

    /**
     * A submitted POST body is accepted.
     */
    public function test_require_post_accepts_post(): void {
        $this->resetAfterTest();
        $previous = $_POST;
        $_POST = ['sesskey' => 'test'];
        try {
            helper::require_post();
        } finally {
            $_POST = $previous;
        }
        $this->assertTrue(true);
    }
}
