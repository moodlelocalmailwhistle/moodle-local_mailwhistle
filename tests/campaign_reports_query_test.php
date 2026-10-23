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

use core_reportbuilder\external\system_report_data_exporter;
use core_reportbuilder\system_report_factory;
use local_mailwhistle\manager\tracking_manager;
use local_mailwhistle\reportbuilder\local\systemreports\campaign_reports;

/**
 * Integration tests for Reports overview queries and native sorting/filtering.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\reportbuilder\local\systemreports\campaign_reports
 * @covers    \local_mailwhistle\reportbuilder\local\entities\campaign
 */
final class campaign_reports_query_test extends \advanced_testcase {
    /**
     * Run the actual report query and format its rows through the report exporter.
     *
     * @param int $templateid Optional template filter.
     * @param int $perpage Native page size.
     * @param int $page Native page index.
     * @return \stdClass Exported report data.
     */
    private function export_report(int $templateid = 0, int $perpage = 50, int $page = 0): \stdClass {
        global $PAGE;

        $PAGE->set_url('/local/mailwhistle/index.php', ['tab' => 'reports']);
        $PAGE->set_context(\context_system::instance());
        $report = system_report_factory::create(
            campaign_reports::class,
            \context_system::instance(),
            '',
            '',
            0,
            ['templateid' => $templateid]
        );
        $exporter = new system_report_data_exporter(null, ['report' => $report, 'page' => $page, 'perpage' => $perpage]);
        return $exporter->export($PAGE->get_renderer('core_reportbuilder'));
    }

    /**
     * SQL aggregates separate outcomes and count each clicker once across multiple links.
     */
    public function test_query_aggregates_delivery_and_distinct_engagement(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign([
            'name' => 'Mixed results', 'subject' => 'Saved subject', 'status' => 'sent', 'timesent' => 1700000000,
        ]);
        $recipients = [];
        foreach (['sent', 'sent', 'failed', 'pending'] as $index => $status) {
            $recipients[] = $generator->create_recipient([
                'campaignid' => $campaign->id, 'userid' => $index + 1, 'status' => $status,
            ]);
        }
        tracking_manager::record_open($campaign->id, $recipients[0]->id);
        tracking_manager::record_open($campaign->id, $recipients[0]->id);
        tracking_manager::record_click($campaign->id, $recipients[0]->id, 'https://example.com/one');
        tracking_manager::record_click($campaign->id, $recipients[0]->id, 'https://example.com/two');
        tracking_manager::record_click($campaign->id, $recipients[1]->id, 'https://example.com/one');

        $data = $this->export_report();
        $this->assertSame(1, $data->totalrowcount);
        $this->assertCount(6, $data->headers);
        [$summary, $date, $delivery, $opens, $clicks, $status] = $data->rows[0]['columns'];
        $this->assertStringContainsString('Mixed results', $summary);
        $this->assertStringContainsString('Saved subject', $summary);
        $this->assertStringContainsString('2 of 4 sent', $delivery);
        $this->assertStringContainsString(get_string('stat_failedcount', 'local_mailwhistle', 1), $delivery);
        $this->assertStringContainsString(get_string('stat_pendingcount', 'local_mailwhistle', 1), $delivery);
        $this->assertStringContainsString('>1</div>', $opens);
        $this->assertStringContainsString('50.0% of sent', $opens);
        $this->assertStringContainsString('>2</div>', $clicks);
        $this->assertStringContainsString('100.0% of sent', $clicks);
        $this->assertStringContainsString(userdate(1700000000, get_string('strftimedatetimeshort', 'langconfig')), $date);
    }

    /**
     * Empty or entirely unsent audiences have zero rates and no fabricated sent date.
     */
    public function test_query_handles_no_sent_recipients(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign(['name' => 'Queued', 'status' => 'sending', 'timesent' => 0]);
        $generator->create_recipient(['campaignid' => $campaign->id, 'userid' => 1, 'status' => 'pending']);

        $data = $this->export_report();
        $columns = $data->rows[0]['columns'];
        $this->assertSame(get_string('reports_nosentdate', 'local_mailwhistle'), strip_tags($columns[1]));
        $this->assertStringContainsString('0 of 1 sent', $columns[2]);
        $this->assertStringContainsString('0.0% of sent', $columns[3]);
        $this->assertStringContainsString('0.0% of sent', $columns[4]);
    }

    /**
     * The report preserves template filtering, excludes drafts, and pages newest sends first.
     */
    public function test_query_filters_and_orders_with_native_pagination(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $template = $generator->create_template();
        $generator->create_campaign([
            'name' => 'Oldest', 'status' => 'sent', 'timesent' => 1500000000, 'templateid' => $template->id,
        ]);
        $generator->create_campaign([
            'name' => 'Newest first id', 'status' => 'sent', 'timesent' => 1700000000, 'templateid' => $template->id,
        ]);
        $generator->create_campaign([
            'name' => 'Newest last id', 'status' => 'sent', 'timesent' => 1700000000, 'templateid' => $template->id,
        ]);
        $generator->create_campaign(['name' => 'Unfiltered', 'status' => 'sent', 'timesent' => 1800000000]);
        $generator->create_campaign([
            'name' => 'Draft', 'status' => 'draft', 'timesent' => 1900000000, 'templateid' => $template->id,
        ]);

        $firstpage = $this->export_report($template->id, 2);
        $this->assertSame(3, $firstpage->totalrowcount);
        $this->assertCount(2, $firstpage->rows);
        $this->assertStringContainsString('Newest last id', $firstpage->rows[0]['columns'][0]);
        $this->assertStringContainsString('Newest first id', $firstpage->rows[1]['columns'][0]);
        $secondpage = $this->export_report($template->id, 2, 1);
        $this->assertCount(1, $secondpage->rows);
        $this->assertStringContainsString('Oldest', $secondpage->rows[0]['columns'][0]);
    }
}
