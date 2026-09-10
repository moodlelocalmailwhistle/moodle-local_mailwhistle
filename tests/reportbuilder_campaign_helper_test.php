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

use local_mailwhistle\manager\audience_manager;
use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\manager\tag_manager;
use local_mailwhistle\reportbuilder\local\helper\campaign as campaignhelper;

/**
 * Tests for campaign report-builder column callbacks.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\reportbuilder\local\helper\campaign
 */
final class reportbuilder_campaign_helper_test extends \advanced_testcase {
    /**
     * Audience callback lists tag names and never returns the old TODO stub.
     */
    public function test_audience_lists_tag_names(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign(['status' => campaign_manager::STATUS_SENT]);
        $tagid = tag_manager::create_tag('Newsletter');
        audience_manager::set_campaign_tags((int) $campaign->id, [$tagid]);

        $html = campaignhelper::audience((int) $campaign->id, $campaign);
        $this->assertStringContainsString('Newsletter', $html);
        $this->assertStringNotContainsString('TODO', $html);
    }

    /**
     * Report name links point at the Reports detail page.
     */
    public function test_reportnamelink_uses_reports_tab(): void {
        $this->resetAfterTest();

        $row = (object) ['id' => 42, 'status' => campaign_manager::STATUS_SENT];
        $html = campaignhelper::reportnamelink('Spring', $row);
        $this->assertStringContainsString('tab=reports', $html);
        $this->assertStringContainsString('campaignid=42', $html);
        $this->assertStringContainsString('Spring', $html);
    }

    /**
     * Template name falls back when none was stored.
     */
    public function test_templatename_fallbacks(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $row = (object) ['id' => 1];
        $this->assertSame(
            get_string('notemplaterecorded', 'local_mailwhistle'),
            campaignhelper::templatename(0, $row)
        );
        $this->assertSame(
            get_string('unknowntemplate', 'local_mailwhistle'),
            campaignhelper::templatename(999999, $row)
        );

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $template = $generator->create_template(['name' => 'Welcome layout']);
        $this->assertSame('Welcome layout', campaignhelper::templatename((int) $template->id, $row));
    }
}
