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
use local_mailwhistle\manager\tracking_manager;
use local_mailwhistle\table\recipient_table;

/**
 * Tests for the campaign recipient roster table.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\table\recipient_table
 */
final class recipient_table_test extends \advanced_testcase {
    /**
     * The roster table is not downloadable.
     */
    public function test_not_downloadable(): void {
        $this->resetAfterTest();

        $table = new recipient_table(
            'mw-recipients-test',
            new \moodle_url('/local/mailwhistle/index.php'),
            1
        );
        $this->assertFalse($table->is_downloadable());
    }

    /**
     * Tracking map records first open and any click per recipient.
     */
    public function test_fetch_tracking_map(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign(['status' => campaign_manager::STATUS_SENT]);
        $cid = (int) $campaign->id;
        $opened = $generator->create_recipient([
            'campaignid' => $cid,
            'userid' => 1,
            'email' => 'open@example.com',
        ]);
        $clicked = $generator->create_recipient([
            'campaignid' => $cid,
            'userid' => 2,
            'email' => 'click@example.com',
        ]);
        $neither = $generator->create_recipient([
            'campaignid' => $cid,
            'userid' => 3,
            'email' => 'none@example.com',
        ]);

        tracking_manager::record_open($cid, (int) $opened->id);
        tracking_manager::record_click($cid, (int) $clicked->id, 'https://example.com/x');

        $map = recipient_table::fetch_tracking_map($cid, [
            (int) $opened->id,
            (int) $clicked->id,
            (int) $neither->id,
        ]);
        $this->assertTrue($map['opened'][(int) $opened->id]);
        $this->assertArrayNotHasKey((int) $clicked->id, $map['opened']);
        $this->assertTrue($map['clicked'][(int) $clicked->id]);
        $this->assertArrayNotHasKey((int) $neither->id, $map['opened']);
        $this->assertArrayNotHasKey((int) $neither->id, $map['clicked']);
    }

    /**
     * Preview actions identify sent snapshots and never offer unsent recipients.
     */
    public function test_email_preview_links_are_scoped_and_sent_only(): void {
        $this->resetAfterTest();
        $table = new recipient_table('mw-preview-links', new \moodle_url('/local/mailwhistle/index.php'), 42);
        $row = (object) [
            'id' => 123,
            'firstname' => 'Ada & friends',
            'lastname' => 'Lovelace',
            'email' => 'ada@example.com',
            'status' => 'sent',
        ];

        $dom = new \DOMDocument();
        $dom->loadHTML($table->col_emailpreview($row), LIBXML_NOERROR | LIBXML_NOWARNING);
        $link = $dom->getElementsByTagName('a')->item(0);
        $this->assertNotNull($link);
        $url = parse_url($link->getAttribute('href'));
        parse_str($url['query'], $params);
        $this->assertSame(['tab' => 'reports', 'campaignid' => '42', 'recipientid' => '123'], $params);
        $this->assertSame('local-mailwhistle-report-email', $url['fragment']);
        $this->assertSame(get_string(
            'report_viewemailfor',
            'local_mailwhistle',
            'Ada & friends Lovelace <ada@example.com>'
        ), $link->getAttribute('aria-label'));
        foreach (['pending', 'failed'] as $status) {
            $row->status = $status;
            $this->assertSame('', $table->col_emailpreview($row));
        }
    }
}
