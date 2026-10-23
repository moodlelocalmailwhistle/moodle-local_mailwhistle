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
     * Legacy unnamed drafts have a visible link so they can be edited.
     */
    public function test_namelink_labels_unnamed_drafts(): void {
        $this->resetAfterTest();

        $row = (object) ['id' => 42, 'status' => campaign_manager::STATUS_DRAFT];
        foreach ([null, '', " \t\r\n"] as $name) {
            $html = campaignhelper::namelink($name, $row);
            $this->assertStringContainsString('campaign_edit.php?campaignid=42', $html);
            $this->assertStringContainsString(get_string('untitledcampaign', 'local_mailwhistle') . '</a>', $html);
        }
    }

    /**
     * Existing names retain their content and HTML escaping.
     */
    public function test_namelink_preserves_named_drafts(): void {
        $this->resetAfterTest();

        $row = (object) ['id' => 42, 'status' => campaign_manager::STATUS_DRAFT];
        $html = campaignhelper::namelink('Spring & Summer', $row);
        $this->assertStringContainsString('Spring &amp; Summer</a>', $html);
        $this->assertStringNotContainsString(get_string('untitledcampaign', 'local_mailwhistle'), $html);
        $this->assertStringContainsString('>0</a>', campaignhelper::namelink('0', $row));
    }

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
     * Overview summaries retain escaped names and subjects with a usable unnamed fallback.
     */
    public function test_reportsummary_escapes_content_and_labels_unnamed_campaigns(): void {
        $this->resetAfterTest();
        $row = (object) ['id' => 42, 'subject' => 'News & updates <script>alert(1)</script>'];
        $html = campaignhelper::reportsummary('Spring & summer <img src=x onerror="alert(1)">', $row);
        $this->assertStringContainsString('Spring &amp; summer', $html);
        $this->assertStringContainsString('News &amp; updates', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('campaignid=42', $html);
        $this->assertStringContainsString('local-mailwhistle-report-subject', $html);
        $this->assertStringContainsString(
            get_string('untitledcampaign', 'local_mailwhistle'),
            campaignhelper::reportsummary(' ', $row)
        );
        $this->assertStringContainsString('>0</a>', campaignhelper::reportsummary('0', $row));
    }

    /**
     * Unknown send timestamps do not turn into the Unix epoch.
     */
    public function test_reportsentat_uses_short_dates_and_unknown_fallback(): void {
        $this->resetAfterTest();
        $row = new \stdClass();
        $this->assertSame(
            get_string('reports_nosentdate', 'local_mailwhistle'),
            strip_tags(campaignhelper::reportsentat(0, $row))
        );
        $this->assertSame(
            userdate(1700000000, get_string('strftimedatetimeshort', 'langconfig')),
            strip_tags(campaignhelper::reportsentat(1700000000, $row))
        );
    }

    /**
     * Overview rates use sent recipients and avoid division by zero.
     */
    public function test_report_metrics_use_sent_denominator(): void {
        $this->resetAfterTest();
        $row = (object) ['sentcount' => 3, 'totalcount' => 5, 'failedcount' => 1, 'pendingcount' => 1];
        $delivery = campaignhelper::reportdelivery(3, $row);
        $this->assertStringContainsString('3 of 5 sent', $delivery);
        $this->assertStringContainsString(get_string('stat_failedcount', 'local_mailwhistle', 1), $delivery);
        $this->assertStringContainsString(get_string('stat_pendingcount', 'local_mailwhistle', 1), $delivery);
        $this->assertStringContainsString('33.3% of sent', campaignhelper::reportopens(1, $row));
        $this->assertStringContainsString('66.7% of sent', campaignhelper::reportclicks(2, $row));
        $row->sentcount = 0;
        $this->assertStringContainsString('0.0% of sent', campaignhelper::reportopens(0, $row));
        $this->assertStringContainsString('0.0% of sent', campaignhelper::reportclicks(0, $row));
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
