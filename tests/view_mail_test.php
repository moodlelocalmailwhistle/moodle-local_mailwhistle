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
use local_mailwhistle\output\campaign_preview;

/**
 * Tests for the sent-campaign preview renderer.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\output\campaign_preview
 */
final class view_mail_test extends \advanced_testcase {
    /**
     * Render a campaign preview through the plugin renderer.
     *
     * @param int $id Campaign id.
     * @return string
     */
    private function render_preview(int $id): string {
        global $PAGE;

        $PAGE->set_context(\context_system::instance());
        $renderer = $PAGE->get_renderer('local_mailwhistle');
        return $renderer->render(new campaign_preview($id));
    }

    /**
     * Sent campaigns render their real subject and body, not sample data.
     */
    public function test_render_view_mail_uses_campaign_record(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign([
            'name' => 'First campaign',
            'subject' => 'Read our exciting news',
            'bodyhtml' => '<p>Some important information</p>',
            'sendername' => 'Admin User',
            'status' => campaign_manager::STATUS_SENT,
            'timesent' => time(),
        ]);

        $html = $this->render_preview((int) $campaign->id);

        $this->assertStringContainsString('Read our exciting news', $html);
        $this->assertStringContainsString('Some important information', $html);
        $this->assertStringContainsString('Admin User', $html);
        $this->assertStringNotContainsString('Welcome to the Autumn term', $html);
    }

    /**
     * Draft campaigns and unknown ids show the not-found notice.
     */
    public function test_render_view_mail_rejects_draft_and_unknown(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $draft = $generator->create_campaign([
            'name' => 'Draft campaign',
            'subject' => 'Not yet sent',
            'status' => campaign_manager::STATUS_DRAFT,
        ]);

        $drafthtml = $this->render_preview((int) $draft->id);
        $this->assertStringContainsString(get_string('mailnotfound', 'local_mailwhistle'), $drafthtml);
        $this->assertStringNotContainsString('Not yet sent', $drafthtml);

        $missinghtml = $this->render_preview(999999);
        $this->assertStringContainsString(get_string('mailnotfound', 'local_mailwhistle'), $missinghtml);
    }
}
