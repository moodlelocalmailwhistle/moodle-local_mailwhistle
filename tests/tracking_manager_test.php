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

use local_mailwhistle\manager\tracking_manager;

/**
 * Unit tests for the tracking_manager class.
 *
 * Covers HMAC token round-trips (including target binding), idempotent
 * first-open recording, click recording, and link rewriting.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\tracking_manager
 */
class tracking_manager_test extends \advanced_testcase {
    /**
     * Insert a recipient row and return [campaignid, recipientid].
     *
     * @return array{0:int,1:int}
     */
    private function make_recipient(): array {
        global $DB, $USER;
        $now = time();
        $campaignid = (int) $DB->insert_record('local_mailwhistle_campaigns', (object) [
            'name' => 'C', 'subject' => 'S', 'bodyhtml' => '<p>b</p>',
            'status' => 'sending', 'createdby' => (int) $USER->id,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $recipientid = (int) $DB->insert_record('local_mailwhistle_recipients', (object) [
            'campaignid' => $campaignid, 'userid' => (int) $USER->id,
            'email' => 'r@t.co', 'status' => 'sent',
            'attempts' => 1, 'timesent' => $now, 'timemodified' => $now,
        ]);
        return [$campaignid, $recipientid];
    }

    /**
     * Test: an open token verifies and a tampered one is rejected.
     */
    public function test_open_token_roundtrip(): void {
        $this->resetAfterTest();

        [$campaignid, $recipientid] = $this->make_recipient();
        $token = tracking_manager::make_open_token($campaignid, $recipientid);

        $verified = tracking_manager::verify_open_token($token);
        $this->assertNotNull($verified);
        $this->assertSame($campaignid, (int) $verified->campaignid);
        $this->assertSame($recipientid, (int) $verified->recipientid);

        // Tampered token fails.
        $this->assertNull(tracking_manager::verify_open_token($token . 'x'));
    }

    /**
     * Test: a click token binds the target; a swapped target is rejected.
     */
    public function test_click_token_binds_target(): void {
        $this->resetAfterTest();

        [$campaignid, $recipientid] = $this->make_recipient();
        $target = 'https://example.com/page';
        $token = tracking_manager::make_click_token($campaignid, $recipientid, $target);

        $verified = tracking_manager::verify_click_token($token, $target);
        $this->assertNotNull($verified);
        $this->assertSame($recipientid, (int) $verified->recipientid);

        // Same token but a different target must fail (no open redirect).
        $this->assertNull(tracking_manager::verify_click_token($token, 'https://evil.example/phish'));
    }

    /**
     * Test: record_open is idempotent (first open only).
     */
    public function test_record_open_idempotent(): void {
        $this->resetAfterTest();
        global $DB;

        [$campaignid, $recipientid] = $this->make_recipient();

        tracking_manager::record_open($campaignid, $recipientid);
        tracking_manager::record_open($campaignid, $recipientid);

        $count = $DB->count_records('local_mailwhistle_tracking', [
            'campaignid' => $campaignid, 'recipientid' => $recipientid, 'eventtype' => 'open',
        ]);
        $this->assertSame(1, $count, 'first open only');
    }

    /**
     * Test: record_click stores the target url and dedupes per link.
     */
    public function test_record_click(): void {
        $this->resetAfterTest();
        global $DB;

        [$campaignid, $recipientid] = $this->make_recipient();
        $target = 'https://example.com/a';

        tracking_manager::record_click($campaignid, $recipientid, $target);
        tracking_manager::record_click($campaignid, $recipientid, $target);
        tracking_manager::record_click($campaignid, $recipientid, 'https://example.com/b');

        $rows = $DB->get_records('local_mailwhistle_tracking', [
            'campaignid' => $campaignid, 'recipientid' => $recipientid, 'eventtype' => 'click',
        ]);
        $this->assertCount(2, $rows, 'one row per distinct link');
    }

    /**
     * Test: rewrite_links wraps http(s) anchors and injects a pixel once.
     */
    public function test_rewrite_links_and_pixel(): void {
        $this->resetAfterTest();

        [$campaignid, $recipientid] = $this->make_recipient();
        $html = '<p>Visit <a href="https://example.com/x">here</a> and '
            . '<a href="mailto:a@b.co">mail</a>.</p>';

        $out = tracking_manager::prepare_body($html, $campaignid, $recipientid);

        // The http link is rewritten through click.php; the mailto is left alone.
        $this->assertStringContainsString('click.php', $out);
        $this->assertStringContainsString('mailto:a@b.co', $out);
        $this->assertStringNotContainsString('href="https://example.com/x"', $out);
        // Exactly one tracking pixel injected.
        $this->assertSame(1, substr_count($out, 'pixel.php'));
    }
}
