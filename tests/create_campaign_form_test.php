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

namespace local_mailwhistle\tests;

/**
 * Unit tests for campaign creation: helper::create_campaign, the create
 * campaign form, and the draft campaigns render function.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\helper
 */
final class create_campaign_form_test extends \advanced_testcase {
    /**
     * Test: create_campaign() inserts a valid draft record with every
     * NOT NULL column populated.
     */
    public function test_create_campaign_inserts_valid_draft(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = \local_mailwhistle\helper::create_campaign('My first campaign');
        $this->assertGreaterThan(0, $id);

        global $DB, $USER;
        $rec = $DB->get_record('local_email_campaigns', ['id' => $id]);

        $this->assertSame('My first campaign', $rec->name);
        $this->assertSame('draft', $rec->status);
        // NOT NULL guard - the core regression check (subject has no DB default).
        $this->assertNotEmpty($rec->subject);
        $this->assertSame('moodle_smtp', $rec->sendengine);
        $this->assertGreaterThan(0, $rec->createdby);
        $this->assertEquals($USER->id, $rec->createdby);
        $this->assertGreaterThan(0, $rec->timecreated);
        $this->assertEquals($rec->timecreated, $rec->timemodified);
    }

    /**
     * Test: create_campaign() fires a data_created log event cleanly,
     * even though its objecttable points at a non-existent table - this
     * works because log_activity() always sets objectid to 0, so
     * core\event\base does not attempt to validate object existence.
     */
    public function test_create_campaign_fires_log_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $sink = $this->redirectEvents();
        \local_mailwhistle\helper::create_campaign('Audit me');
        $events = $sink->get_events();
        $sink->close();

        $found = null;
        foreach ($events as $event) {
            if ($event instanceof \local_mailwhistle\event\data_created) {
                $found = $event;
                break;
            }
        }

        $this->assertNotNull($found);
        $this->assertSame('campaign_created', $found->other['action']);
        $this->assertEquals(0, $found->objectid);
    }

    /**
     * Test: the create campaign form's validation() method rejects an
     * empty or whitespace-only name and accepts a valid one.
     */
    public function test_create_campaign_form_validation(): void {
        $this->resetAfterTest();

        $form = new \local_mailwhistle\form\create_campaign_form();

        $errors = $form->validation(['name' => ''], []);
        $this->assertArrayHasKey('name', $errors);

        $errors = $form->validation(['name' => '   '], []);
        $this->assertArrayHasKey('name', $errors);

        $errors = $form->validation(['name' => 'Valid'], []);
        $this->assertArrayNotHasKey('name', $errors);

        // A name longer than the 255-char column must be rejected server-side.
        $errors = $form->validation(['name' => str_repeat('a', 256)], []);
        $this->assertArrayHasKey('name', $errors);

        // Exactly 255 chars is allowed.
        $errors = $form->validation(['name' => str_repeat('a', 255)], []);
        $this->assertArrayNotHasKey('name', $errors);
    }

    /**
     * Test: local_mailwhistle_render_draft_campaigns() includes a freshly
     * created draft campaign in its output.
     */
    public function test_render_draft_campaigns_shows_created_draft(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        require_once($CFG->dirroot . '/local/mailwhistle/lib.php');

        // Plain alphanumeric name so format_string() does not alter it.
        \local_mailwhistle\helper::create_campaign('Render me Alpha');

        $html = local_mailwhistle_render_draft_campaigns();
        $this->assertStringContainsString('Render me Alpha', $html);
        // The draft status badge must resolve the status_draft string, not a
        // missing-string placeholder.
        $this->assertStringContainsString(get_string('status_draft', 'local_mailwhistle'), $html);
        $this->assertStringNotContainsString('[[status_draft]]', $html);
    }

    /**
     * Test: local_mailwhistle_render_draft_campaigns() shows the empty
     * state when there are no draft campaigns.
     */
    public function test_render_draft_campaigns_empty(): void {
        global $CFG;
        $this->resetAfterTest();

        require_once($CFG->dirroot . '/local/mailwhistle/lib.php');

        $html = local_mailwhistle_render_draft_campaigns();
        $this->assertStringContainsString(get_string('nodraftcampaigns', 'local_mailwhistle'), $html);
    }

    /**
     * Test: the local/mailwhistle:manage capability is enforced - a plain
     * user does not have it, while an admin does.
     */
    public function test_create_capability_enforced(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertFalse(has_capability('local/mailwhistle:manage', \context_system::instance()));

        $this->setAdminUser();
        $this->assertTrue(has_capability('local/mailwhistle:manage', \context_system::instance()));
    }
}
