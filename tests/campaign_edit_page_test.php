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

use local_mailwhistle\form\campaign_details_form;
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
     * Load the form library used by the wizard entry script.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
    }

    /**
     * Run a request, capturing output and the redirect exception used in CLI tests.
     *
     * @param bool $redirect Whether the request should redirect.
     * @return string Rendered page output.
     */
    private function execute_request(bool $redirect = false): string {
        $redirected = false;
        ob_start();
        try {
            campaign_edit::execute();
        } catch (\moodle_exception $exception) {
            if ($exception->errorcode !== 'redirecterrordetected') {
                throw $exception;
            }
            $redirected = true;
        } finally {
            $html = ob_get_clean();
        }
        $this->assertSame($redirect, $redirected);
        return $html;
    }

    /**
     * Opening a new campaign only displays details and does not persist a draft.
     */
    public function test_opening_new_campaign_does_not_create_a_draft(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST = ['sesskey' => sesskey(), 'step' => 'review'];

        $html = $this->execute_request();

        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringNotContainsString('name="subject"', $html);
        $this->assertSame(0, $DB->count_records('local_mailwhistle_campaigns'));
    }

    /**
     * Cancelling the first step does not leave an unnamed draft behind.
     */
    public function test_cancelling_new_campaign_does_not_create_a_draft(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        campaign_details_form::mock_submit(['campaignid' => 0, 'cancel' => 1]);

        $this->execute_request(true);

        $this->assertSame(0, $DB->count_records('local_mailwhistle_campaigns'));
    }

    /**
     * Invalid details cannot create a campaign.
     *
     * @dataProvider invalid_details_provider
     * @param string $name Submitted campaign name.
     * @param string $email Submitted sender email.
     */
    public function test_invalid_details_do_not_create_a_draft(string $name, string $email): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        campaign_details_form::mock_submit([
            'campaignid' => 0,
            'name' => $name,
            'senderemail' => $email,
        ]);

        $html = $this->execute_request();

        $this->assertStringContainsString('name="name"', $html);
        $this->assertSame(0, $DB->count_records('local_mailwhistle_campaigns'));
    }

    /**
     * Invalid first-step submissions.
     *
     * @return array
     */
    public static function invalid_details_provider(): array {
        return [
            'empty name' => ['', ''],
            'whitespace name' => ['   ', ''],
            'invalid sender email' => ['Newsletter', 'invalid-email'],
        ];
    }

    /**
     * Valid details create exactly one named draft with its sender information.
     */
    public function test_valid_details_create_a_named_draft(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        campaign_details_form::mock_submit([
            'campaignid' => 0,
            'name' => 'Newsletter',
            'sendername' => 'News team',
            'senderemail' => 'news@example.com',
        ]);

        $this->execute_request(true);

        $campaign = $DB->get_record('local_mailwhistle_campaigns', [], '*', MUST_EXIST);
        $this->assertSame(1, $DB->count_records('local_mailwhistle_campaigns'));
        $this->assertSame('Newsletter', $campaign->name);
        $this->assertSame('News team', $campaign->sendername);
        $this->assertSame('news@example.com', $campaign->senderemail);
        $this->assertSame('draft', $campaign->status);
        $this->assertEquals($USER->id, $campaign->createdby);
    }

    /**
     * Cancelling edits preserves the existing draft and its saved name.
     */
    public function test_cancelling_existing_campaign_preserves_it(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $campaignid = helper::create_campaign('Saved draft');
        campaign_details_form::mock_submit(['campaignid' => $campaignid, 'name' => 'Unsaved change', 'cancel' => 1]);

        $this->execute_request(true);

        $this->assertSame(1, $DB->count_records('local_mailwhistle_campaigns'));
        $this->assertSame('Saved draft', $DB->get_field('local_mailwhistle_campaigns', 'name', ['id' => $campaignid]));
    }

    /**
     * Saving an existing campaign updates it without creating another draft.
     */
    public function test_saving_existing_campaign_updates_it(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $campaignid = helper::create_campaign('Saved draft');
        campaign_details_form::mock_submit([
            'campaignid' => $campaignid,
            'name' => 'Renamed draft',
            'sendername' => 'News team',
            'senderemail' => '',
        ]);

        $this->execute_request(true);

        $this->assertSame(1, $DB->count_records('local_mailwhistle_campaigns'));
        $this->assertSame('Renamed draft', $DB->get_field('local_mailwhistle_campaigns', 'name', ['id' => $campaignid]));
    }

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

    /**
     * Reopening requires both a real POST and a valid session key.
     *
     * @dataProvider invalid_reopen_request_provider
     * @param bool $post Whether to use POST.
     * @param bool $validsesskey Whether the session key is valid.
     * @param string $errorcode Expected rejection.
     */
    public function test_reopening_rejects_unprotected_requests(bool $post, bool $validsesskey, string $errorcode): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $campaign = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_campaign(['status' => 'ready']);
        $params = [
            'campaignid' => $campaign->id,
            'action' => 'returntodraft',
            'sesskey' => $validsesskey ? sesskey() : 'invalid',
        ];
        $_POST = $post ? $params : [];
        $_GET = $post ? [] : $params;
        try {
            $this->execute_request();
            $this->fail('An unprotected reopen request must be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame($errorcode, $exception->errorcode);
        }
        $this->assertSame('ready', $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $campaign->id]));
    }

    /**
     * Requests without POST or a valid session key must not reopen campaigns.
     *
     * @return array
     */
    public static function invalid_reopen_request_provider(): array {
        return [
            'get' => [false, true, 'invalidrequest'],
            'invalid session key' => [true, false, 'invalidsesskey'],
        ];
    }

    /**
     * An authorised POST reopens a ready campaign and returns to its details.
     */
    public function test_reopening_ready_campaign(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $campaign = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_campaign(['status' => 'ready']);
        $_GET = [];
        $_POST = ['campaignid' => $campaign->id, 'action' => 'returntodraft', 'sesskey' => sesskey()];
        $this->execute_request(true);
        $this->assertSame('draft', $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $campaign->id]));
    }

    /**
     * A user without the manage capability cannot reopen an otherwise-ready campaign.
     */
    public function test_reopening_requires_manage_capability(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $campaign = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_campaign(['status' => 'ready']);
        $_GET = [];
        $_POST = ['campaignid' => $campaign->id, 'action' => 'returntodraft', 'sesskey' => sesskey()];
        try {
            $this->execute_request();
            $this->fail('Only a manager can reopen the campaign.');
        } catch (\required_capability_exception $exception) {
            $this->assertSame('nopermissions', $exception->errorcode);
        }
        $this->assertSame('ready', $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $campaign->id]));
    }

    /**
     * A submitted editor opened before completion cannot update a now-ready campaign.
     */
    public function test_stale_details_submission_preserves_ready_campaign(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $campaign = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_campaign([
            'name' => 'Saved name', 'status' => 'ready',
        ]);
        campaign_details_form::mock_submit(['campaignid' => $campaign->id, 'name' => 'Stale edit']);
        $html = $this->execute_request();
        $this->assertStringContainsString('Return to draft', $html);
        $this->assertSame('Saved name', $DB->get_field('local_mailwhistle_campaigns', 'name', ['id' => $campaign->id]));
        $this->assertSame('ready', $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $campaign->id]));
    }

    /**
     * Content form validation rejects empty editor markup before any write.
     */
    public function test_content_form_requires_meaningful_body(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/local/mailwhistle/campaign_edit.php'));
        $form = new \local_mailwhistle\form\campaign_content_form(null);
        $errors = $form->validation(['subject' => 'News', 'body' => ['text' => '<p>&nbsp;<br></p>']], []);
        $this->assertArrayHasKey('body', $errors);
        $errors = $form->validation([
            'subject' => 'News', 'body' => ['text' => '<img src="https://example.test/banner.png" alt="News">'],
        ], []);
        $this->assertArrayNotHasKey('body', $errors);
    }
}
