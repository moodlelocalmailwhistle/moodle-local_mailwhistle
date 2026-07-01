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

use local_mailwhistle\manager\send_manager;
use local_mailwhistle\manager\recipient_manager;
use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\manager\audience_manager;
use local_mailwhistle\manager\tag_manager;

/**
 * Unit tests for the send_manager and the campaign send lifecycle.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\send_manager
 */
final class send_manager_test extends \advanced_testcase {
    /**
     * Build a complete, ready campaign with one tagged recipient.
     *
     * @return array{0:int,1:\stdClass} [campaignid, recipient user]
     */
    private function make_ready_campaign_with_recipient(): array {
        global $DB, $USER;
        $now = time();
        $campaignid = (int) $DB->insert_record('local_mailwhistle_campaigns', (object) [
            'name' => 'C',
            'subject' => 'Hello subject',
            'bodyhtml' => '<p>Body html</p>',
            'bodytext' => 'Body text',
            'sendername' => 'Sender Name',
            'senderemail' => 'sender@example.com',
            'status' => campaign_manager::STATUS_READY,
            'createdby' => (int) $USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $tag = tag_manager::create_tag('A');
        audience_manager::set_campaign_tags($campaignid, [$tag]);
        $user = $this->getDataGenerator()->create_user(['email' => 'rcpt@example.com']);
        tag_manager::assign_tag($tag, $user->id);
        recipient_manager::snapshot_recipients($campaignid);
        return [$campaignid, $user];
    }

    /**
     * Test: process_campaign sends to each recipient and completes the campaign.
     */
    public function test_process_sends_and_completes(): void {
        $this->resetAfterTest();
        global $DB;

        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        [$campaignid, $user] = $this->make_ready_campaign_with_recipient();

        campaign_manager::begin_sending($campaignid);
        send_manager::process_campaign($campaignid, 50);

        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertSame('Hello subject', $messages[0]->subject);

        $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
        $this->assertSame(campaign_manager::STATUS_SENT, $campaign->status);
        $this->assertGreaterThan(0, (int) $campaign->timesent);

        $recipient = $DB->get_record(
            'local_mailwhistle_recipients',
            ['campaignid' => $campaignid, 'userid' => $user->id],
            '*',
            MUST_EXIST
        );
        $this->assertSame(recipient_manager::STATUS_SENT, $recipient->status);

        // A sendlog row was written for the recipient.
        $this->assertTrue($DB->record_exists(
            'local_mailwhistle_sendlogs',
            ['campaignid' => $campaignid, 'recipientid' => $recipient->id]
        ));
    }

    /**
     * Test: re-processing does not re-send an already-sent recipient.
     */
    public function test_process_idempotent(): void {
        $this->resetAfterTest();

        [$campaignid] = $this->make_ready_campaign_with_recipient();
        campaign_manager::begin_sending($campaignid);
        send_manager::process_campaign($campaignid, 50);

        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        send_manager::process_campaign($campaignid, 50);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages, 'no re-send on second run');
    }

    /**
     * Test: begin_sending only transitions a ready campaign once.
     */
    public function test_begin_sending_guard(): void {
        $this->resetAfterTest();

        [$campaignid] = $this->make_ready_campaign_with_recipient();

        $this->assertTrue(campaign_manager::begin_sending($campaignid), 'first wins');
        $this->assertFalse(campaign_manager::begin_sending($campaignid), 'second is a no-op');
    }

    /**
     * Test: batch limit leaves remaining recipients pending across runs.
     */
    public function test_batch_limit_requeues(): void {
        $this->resetAfterTest();
        global $DB, $USER;

        $now = time();
        $campaignid = (int) $DB->insert_record('local_mailwhistle_campaigns', (object) [
            'name' => 'C', 'subject' => 'S', 'bodyhtml' => '<p>b</p>',
            'status' => campaign_manager::STATUS_READY,
            'createdby' => (int) $USER->id, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $tag = tag_manager::create_tag('A');
        audience_manager::set_campaign_tags($campaignid, [$tag]);
        for ($i = 0; $i < 3; $i++) {
            $u = $this->getDataGenerator()->create_user();
            tag_manager::assign_tag($tag, $u->id);
        }
        recipient_manager::snapshot_recipients($campaignid);

        campaign_manager::begin_sending($campaignid);

        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        send_manager::process_campaign($campaignid, 2); // Batch of 2.
        $sink->close();

        // 1 recipient still pending; campaign not yet sent.
        $this->assertSame(1, recipient_manager::count_pending($campaignid));
        $status = $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $campaignid], MUST_EXIST);
        $this->assertSame(campaign_manager::STATUS_SENDING, $status);
    }
}
