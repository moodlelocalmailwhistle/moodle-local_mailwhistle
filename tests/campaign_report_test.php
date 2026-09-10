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

use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\output\campaign_report;

/**
 * Tests for the campaign report page.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\output\campaign_report
 */
final class campaign_report_test extends \advanced_testcase {
    /**
     * Render a campaign report through the plugin renderer.
     *
     * @param int $id Campaign id.
     * @return string
     */
    private function render_report(int $id): string {
        global $PAGE;

        $PAGE->set_url(new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'reports']));
        $PAGE->set_context(\context_system::instance());
        $renderer = $PAGE->get_renderer('local_mailwhistle');
        return $renderer->render(new campaign_report($id));
    }

    /**
     * A sent campaign lists snapshotted recipients.
     */
    public function test_render_lists_recipients(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign([
            'name' => 'Spring',
            'subject' => 'Spring news',
            'status' => campaign_manager::STATUS_SENT,
            'timesent' => time(),
        ]);
        $generator->create_recipient([
            'campaignid' => (int) $campaign->id,
            'userid' => (int) $this->getDataGenerator()->create_user()->id,
            'email' => 'alice@example.com',
            'firstname' => 'Alice',
            'lastname' => 'Rowe',
            'status' => 'sent',
        ]);

        $html = $this->render_report((int) $campaign->id);
        $this->assertStringContainsString('alice@example.com', $html);
        $this->assertStringContainsString('Alice Rowe', $html);
        $this->assertStringContainsString('Spring news', $html);
        $this->assertStringNotContainsString('Reporting and analytics are coming soon.', $html);
    }

    /**
     * Draft campaigns show the not-found notice.
     */
    public function test_render_rejects_draft(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $draft = $generator->create_campaign(['status' => campaign_manager::STATUS_DRAFT]);

        $html = $this->render_report((int) $draft->id);
        $this->assertStringContainsString(get_string('mailnotfound', 'local_mailwhistle'), $html);
    }
}
