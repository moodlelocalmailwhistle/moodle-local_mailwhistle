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

use local_mailwhistle\manager\recipient_manager;
use local_mailwhistle\manager\audience_manager;
use local_mailwhistle\manager\tag_manager;

/**
 * Unit tests for the recipient_manager class.
 *
 * Covers audience-tag resolution into recipients, unsubscribe/suspended/deleted
 * exclusion, dedupe, idempotent snapshotting, and pending fetch.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\recipient_manager
 */
final class recipient_manager_test extends \advanced_testcase {
    /**
     * Insert a draft campaign and return its id.
     *
     * @return int Campaign id.
     */
    private function make_campaign(): int {
        global $DB, $USER;
        $now = time();
        return (int) $DB->insert_record('local_mailwhistle_campaigns', (object) [
            'name' => 'C',
            'subject' => 'S',
            'bodyhtml' => '<p>b</p>',
            'status' => 'draft',
            'createdby' => (int) $USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Test: resolve_recipients returns tagged, active, subscribed users only.
     */
    public function test_resolve_excludes_and_dedupes(): void {
        $this->resetAfterTest();
        global $DB;

        $campaignid = $this->make_campaign();
        $tag1 = tag_manager::create_tag('A');
        $tag2 = tag_manager::create_tag('B');
        audience_manager::set_campaign_tags($campaignid, [$tag1, $tag2]);

        $active = $this->getDataGenerator()->create_user();
        $both = $this->getDataGenerator()->create_user();       // In both tags -> once.
        $suspended = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $deleted = $this->getDataGenerator()->create_user();
        $unsub = $this->getDataGenerator()->create_user();

        foreach ([$active, $both, $suspended, $deleted, $unsub] as $u) {
            tag_manager::assign_tag($tag1, $u->id);
        }
        tag_manager::assign_tag($tag2, $both->id);

        // Globally unsubscribe one user.
        $DB->insert_record('local_mailwhistle_unsubscribes', (object) [
            'userid' => $unsub->id,
            'email' => $unsub->email,
            'scope' => 'global',
            'scopeid' => 0,
            'timecreated' => time(),
        ]);
        // Delete one user.
        delete_user($DB->get_record('user', ['id' => $deleted->id]));

        $recipients = recipient_manager::resolve_recipients($campaignid);
        $ids = array_map(static fn($r) => (int) $r->id, $recipients);

        $this->assertContains((int) $active->id, $ids);
        $this->assertContains((int) $both->id, $ids);
        $this->assertNotContains((int) $suspended->id, $ids, 'suspended excluded');
        $this->assertNotContains((int) $deleted->id, $ids, 'deleted excluded');
        $this->assertNotContains((int) $unsub->id, $ids, 'unsubscribed excluded');
        // Dedupe: $both counted once.
        $this->assertSame(count($ids), count(array_unique($ids)));
    }

    /**
     * Test: snapshot_recipients is idempotent and writes frozen snapshot data.
     */
    public function test_snapshot_idempotent(): void {
        $this->resetAfterTest();
        global $DB;

        $campaignid = $this->make_campaign();
        $tag = tag_manager::create_tag('A');
        audience_manager::set_campaign_tags($campaignid, [$tag]);
        $u = $this->getDataGenerator()->create_user(['firstname' => 'Snap', 'email' => 'snap@t.co']);
        tag_manager::assign_tag($tag, $u->id);

        $count1 = recipient_manager::snapshot_recipients($campaignid);
        $count2 = recipient_manager::snapshot_recipients($campaignid);

        $this->assertSame(1, $count1);
        $this->assertSame(0, $count2, 'second snapshot adds no rows');

        $rows = $DB->get_records('local_mailwhistle_recipients', ['campaignid' => $campaignid]);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame((int) $u->id, (int) $row->userid);
        $this->assertSame('snap@t.co', $row->email, 'email frozen at snapshot');
        $this->assertSame(recipient_manager::STATUS_PENDING, $row->status);
    }

    /**
     * Test: snapshot does not reset an already-sent recipient.
     */
    public function test_snapshot_preserves_sent(): void {
        $this->resetAfterTest();
        global $DB;

        $campaignid = $this->make_campaign();
        $tag = tag_manager::create_tag('A');
        audience_manager::set_campaign_tags($campaignid, [$tag]);
        $u = $this->getDataGenerator()->create_user();
        tag_manager::assign_tag($tag, $u->id);

        recipient_manager::snapshot_recipients($campaignid);
        $rid = $DB->get_field(
            'local_mailwhistle_recipients',
            'id',
            ['campaignid' => $campaignid, 'userid' => $u->id],
            MUST_EXIST
        );
        recipient_manager::mark_sent($rid);

        recipient_manager::snapshot_recipients($campaignid);

        $status = $DB->get_field('local_mailwhistle_recipients', 'status', ['id' => $rid], MUST_EXIST);
        $this->assertSame(recipient_manager::STATUS_SENT, $status);
    }

    /**
     * Test: get_pending_recipients returns only pending rows up to the limit.
     */
    public function test_get_pending_limit(): void {
        $this->resetAfterTest();

        $campaignid = $this->make_campaign();
        $tag = tag_manager::create_tag('A');
        audience_manager::set_campaign_tags($campaignid, [$tag]);
        for ($i = 0; $i < 3; $i++) {
            $u = $this->getDataGenerator()->create_user();
            tag_manager::assign_tag($tag, $u->id);
        }
        recipient_manager::snapshot_recipients($campaignid);

        $batch = recipient_manager::get_pending_recipients($campaignid, 2);
        $this->assertCount(2, $batch);
    }
}
