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

namespace local_mailwhistle\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;

/**
 * Unit tests for the Mail Whistle privacy provider.
 *
 * Verifies that email campaigns created via helper::create_campaign() are
 * correctly described, exported and deleted by the privacy API.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Test: get_metadata() describes the local_mailwhistle_campaigns table.
     */
    public function test_get_metadata(): void {
        $this->resetAfterTest();

        $collection = new collection('local_mailwhistle');
        $collection = provider::get_metadata($collection);

        $items = $collection->get_collection();
        $tablenames = [];
        $campaignfields = [];
        foreach ($items as $item) {
            $tablenames[] = $item->get_name();
            if ($item->get_name() === 'local_mailwhistle_campaigns') {
                $campaignfields = array_keys($item->get_privacy_fields());
            }
        }

        $this->assertContains('local_mailwhistle_campaigns', $tablenames);
        // Every exported field must be declared in the metadata.
        $this->assertContains('name', $campaignfields);
        $this->assertContains('subject', $campaignfields);
        $this->assertContains('status', $campaignfields);
        $this->assertContains('createdby', $campaignfields);
        $this->assertContains('timecreated', $campaignfields);
    }

    /**
     * Test: get_contexts_for_userid() returns the system context when a user has campaigns.
     */
    public function test_get_contexts_for_userid(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        \local_mailwhistle\helper::create_campaign('X');

        $contextlist = provider::get_contexts_for_userid((int) $USER->id);
        $this->assertContains(\context_system::instance()->id, $contextlist->get_contextids());

        // A user with no campaigns should yield an empty contextlist.
        $otheruser = $this->getDataGenerator()->create_user();
        $emptycontextlist = provider::get_contexts_for_userid((int) $otheruser->id);
        $this->assertEmpty($emptycontextlist->get_contextids());
    }

    /**
     * Test: export_user_data() writes campaign data for the approved context.
     */
    public function test_export_user_data(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        \local_mailwhistle\helper::create_campaign('X');

        $context = \context_system::instance();
        $approvedcontextlist = new approved_contextlist($USER, 'local_mailwhistle', [$context->id]);
        provider::export_user_data($approvedcontextlist);

        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test: delete_data_for_user() removes only the approved user's campaigns.
     */
    public function test_delete_for_user(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        \local_mailwhistle\helper::create_campaign('X');
        $adminid = (int) $USER->id;

        $context = \context_system::instance();
        $approvedcontextlist = new approved_contextlist($USER, 'local_mailwhistle', [$context->id]);
        provider::delete_data_for_user($approvedcontextlist);

        $this->assertEquals(0, $DB->count_records('local_mailwhistle_campaigns', ['createdby' => $adminid]));
    }

    /**
     * Test: delete_data_for_all_users_in_context() removes all campaigns in the system context.
     */
    public function test_delete_for_all_users_in_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        \local_mailwhistle\helper::create_campaign('X');

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertEquals(0, $DB->count_records('local_mailwhistle_campaigns'));
    }
}
