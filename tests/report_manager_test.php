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
use local_mailwhistle\manager\report_manager;
use local_mailwhistle\manager\tracking_manager;

/**
 * Tests for campaign report aggregates.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\report_manager
 */
final class report_manager_test extends \advanced_testcase {
    /**
     * Sent and sending campaigns are reportable; drafts are not.
     */
    public function test_campaign_is_reportable(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $sent = $generator->create_campaign(['status' => campaign_manager::STATUS_SENT]);
        $sending = $generator->create_campaign(['status' => campaign_manager::STATUS_SENDING]);
        $draft = $generator->create_campaign(['status' => campaign_manager::STATUS_DRAFT]);

        $this->assertTrue(report_manager::campaign_is_reportable((int) $sent->id));
        $this->assertTrue(report_manager::campaign_is_reportable((int) $sending->id));
        $this->assertFalse(report_manager::campaign_is_reportable((int) $draft->id));
        $this->assertFalse(report_manager::campaign_is_reportable(999999));
    }

    /**
     * Stats count recipients by status and unique opens/clicks, with rates.
     */
    public function test_get_campaign_stats(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign(['status' => campaign_manager::STATUS_SENT]);
        $cid = (int) $campaign->id;

        $sentone = $generator->create_recipient([
            'campaignid' => $cid,
            'userid' => 1,
            'email' => 'a@example.com',
            'firstname' => 'A',
            'lastname' => 'One',
            'status' => 'sent',
        ]);
        $senttwo = $generator->create_recipient([
            'campaignid' => $cid,
            'userid' => 2,
            'email' => 'b@example.com',
            'firstname' => 'B',
            'lastname' => 'Two',
            'status' => 'sent',
        ]);
        $generator->create_recipient([
            'campaignid' => $cid,
            'userid' => 3,
            'email' => 'c@example.com',
            'firstname' => 'C',
            'lastname' => 'Three',
            'status' => 'failed',
            'timesent' => 0,
            'error' => 'bounce',
        ]);

        tracking_manager::record_open($cid, (int) $sentone->id);
        tracking_manager::record_open($cid, (int) $sentone->id);
        tracking_manager::record_click($cid, (int) $senttwo->id, 'https://example.com/one');
        tracking_manager::record_click($cid, (int) $senttwo->id, 'https://example.com/two');

        $stats = report_manager::get_campaign_stats($cid);
        $this->assertSame(3, $stats->recipients);
        $this->assertSame(2, $stats->sent);
        $this->assertSame(1, $stats->failed);
        $this->assertSame(1, $stats->uniqueopens);
        $this->assertSame(1, $stats->uniqueclicks);
        $this->assertSame(50.0, $stats->openrate);
        $this->assertSame(50.0, $stats->clickrate);
    }

    /**
     * A campaign with no recipients yields zero rates rather than dividing.
     */
    public function test_get_campaign_stats_empty(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign(['status' => campaign_manager::STATUS_SENT]);

        $stats = report_manager::get_campaign_stats((int) $campaign->id);
        $this->assertSame(0, $stats->recipients);
        $this->assertSame(0, $stats->sent);
        $this->assertSame(0.0, $stats->openrate);
        $this->assertSame(0.0, $stats->clickrate);
    }
}
