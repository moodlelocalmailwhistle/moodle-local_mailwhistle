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
 * @copyright 2026 onwards MoodleDach project
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

    /**
     * The preview matches sending policy, explains exclusions and leaves snapshots untouched.
     */
    public function test_audience_preview_uses_current_delivery_policy(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $CFG->noemailever = false;
        $DB->set_field('message_processors', 'enabled', 1, ['name' => 'email']);
        $this->redirectEmails();
        $campaignid = $this->make_campaign();
        $tags = [tag_manager::create_tag('First'), tag_manager::create_tag('Second')];
        audience_manager::set_campaign_tags($campaignid, $tags);
        $eligible = $this->getDataGenerator()->create_user(['email' => 'eligible@example.com']);
        $suspended = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $nologin = $this->getDataGenerator()->create_user(['auth' => 'nologin']);
        $optedout = $this->getDataGenerator()->create_user(['emailstop' => 1]);
        $unsubscribed = $this->getDataGenerator()->create_user();
        $invalid = $this->getDataGenerator()->create_user(['email' => 'reader@example.invalid']);
        foreach ([$eligible, $suspended, $nologin, $optedout, $unsubscribed, $invalid] as $user) {
            tag_manager::assign_tag($tags[0], $user->id);
        }
        tag_manager::assign_tag($tags[1], $eligible->id);
        $DB->insert_record('local_mailwhistle_unsubscribes', (object) [
            'userid' => $unsubscribed->id, 'email' => $unsubscribed->email,
            'scope' => 'global', 'scopeid' => 0, 'timecreated' => time(),
        ]);

        $summary = recipient_manager::get_audience_summary($campaignid);
        $this->assertSame(6, $summary['total']);
        $this->assertSame(1, $summary['eligible']);
        $this->assertSame(5, $summary['excluded']);
        $this->assertSame(5, array_sum($summary['reasons']));
        $preview = recipient_manager::get_audience_page($campaignid);
        $this->assertSame('', $preview[$eligible->id]->eligibilityreason);
        foreach ([$suspended, $nologin, $optedout, $unsubscribed, $invalid] as $user) {
            $this->assertSame(
                \local_mailwhistle\manager\send_manager::get_skip_reason($user),
                $preview[$user->id]->eligibilityreason
            );
            $this->assertNotSame('', $preview[$user->id]->eligibilityreason);
        }
        $this->assertSame(1, \local_mailwhistle\manager\campaign_manager::get_readiness($campaignid)['recipientcount']);
        $this->assertSame(0, $DB->count_records('local_mailwhistle_recipients'));

        $CFG->noemailever = true;
        $this->assertSame(0, recipient_manager::get_audience_summary($campaignid)['eligible']);
        $this->assertContains(
            get_string('campaignemptyaudience', 'local_mailwhistle'),
            \local_mailwhistle\manager\campaign_manager::get_readiness($campaignid)['errors']
        );
    }

    /**
     * Notification preferences, administrator locks and effective addresses are respected.
     */
    public function test_audience_preview_respects_email_preferences_and_overrides(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $CFG->noemailever = false;
        $CFG->messagingallowemailoverride = true;
        $DB->set_field('message_processors', 'enabled', 1, ['name' => 'email']);
        $this->redirectEmails();
        $campaignid = $this->make_campaign();
        $tag = tag_manager::create_tag('Newsletter');
        audience_manager::set_campaign_tags($campaignid, [$tag]);
        $user = $this->getDataGenerator()->create_user(['email' => 'reader@example.com']);
        tag_manager::assign_tag($tag, $user->id);
        set_user_preference('message_provider_local_mailwhistle_campaign_enabled', 'none', $user);
        $this->assertSame(0, recipient_manager::get_audience_summary($campaignid)['eligible']);
        set_config('email_provider_local_mailwhistle_campaign_locked', 1, 'message');
        $this->assertSame(1, recipient_manager::get_audience_summary($campaignid)['eligible']);
        set_user_preference('message_processor_email_email', 'override@example.invalid', $user);
        $this->assertSame(0, recipient_manager::get_audience_summary($campaignid)['eligible']);
        set_user_preference('message_processor_email_email', 'override@example.com', $user);
        $preview = recipient_manager::get_audience_page($campaignid);
        $this->assertSame('override@example.com', $preview[$user->id]->destination);
        $this->assertSame('', $preview[$user->id]->eligibilityreason);
        $DB->set_field('message_processors', 'enabled', 0, ['name' => 'email']);
        $this->assertSame(0, recipient_manager::get_audience_summary($campaignid)['eligible']);
    }

    /**
     * A multi-tag audience is deduplicated and paginated without exposing deleted users.
     */
    public function test_audience_preview_paginates_and_omits_deleted_accounts(): void {
        $this->resetAfterTest();
        $campaignid = $this->make_campaign();
        $tags = [tag_manager::create_tag('First'), tag_manager::create_tag('Second')];
        audience_manager::set_campaign_tags($campaignid, $tags);
        for ($i = 0; $i < 27; $i++) {
            $user = $this->getDataGenerator()->create_user();
            foreach ($tags as $tag) {
                tag_manager::assign_tag($tag, $user->id);
            }
        }
        $deleted = $this->getDataGenerator()->create_user();
        tag_manager::assign_tag($tags[0], $deleted->id);
        delete_user($deleted);
        $first = recipient_manager::get_audience_page($campaignid);
        $second = recipient_manager::get_audience_page($campaignid, 1);
        $this->assertCount(25, $first);
        $this->assertCount(2, $second);
        $this->assertSame([], array_intersect(array_keys($first), array_keys($second)));
        $this->assertSame(27, recipient_manager::get_audience_summary($campaignid)['total']);
        $this->assertArrayNotHasKey($deleted->id, $first + $second);
    }
}
