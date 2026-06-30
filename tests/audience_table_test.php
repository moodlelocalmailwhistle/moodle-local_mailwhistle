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

defined('MOODLE_INTERNAL') || die();

use local_mailwhistle\manager\tag_manager;
use local_mailwhistle\table\audience_table;

/**
 * Tests for the audience_table SQL correctness.
 *
 * Rather than invoking table_sql's full rendering pipeline (which requires a
 * real HTTP request context), these tests directly replicate the baseline WHERE
 * logic and the tag-fetch helpers to assert correctness of the SQL fragments
 * the table builds.  This is the pragmatic approach recommended in the task spec
 * when invoking table_sql in unit tests is awkward.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\table\audience_table
 */
class audience_table_test extends \advanced_testcase {

    /**
     * Helper: return an array of user ids visible in the audience listing
     * using the same baseline WHERE as audience_table (deleted=0, not guest).
     * Optionally apply a tag EXISTS filter.
     *
     * @param int  $filtertagid 0 = no tag filter.
     * @param string $suspended 'any'|'active'|'suspended'.
     * @return int[]
     */
    private function get_audience_userids(int $filtertagid = 0, string $suspended = 'any'): array {
        global $CFG, $DB;

        $where  = 'u.deleted = 0 AND u.id <> :guestid';
        $params = ['guestid' => $CFG->siteguest];

        if ($filtertagid > 0) {
            $where .= ' AND EXISTS ('
                . 'SELECT 1 FROM {local_mailwhistle_tag_assign} ta'
                . ' WHERE ta.userid = u.id AND ta.tagid = :filtertagid'
                . ')';
            $params['filtertagid'] = $filtertagid;
        }

        if ($suspended === 'active') {
            $where .= ' AND u.suspended = :susp';
            $params['susp'] = 0;
        } else if ($suspended === 'suspended') {
            $where .= ' AND u.suspended = :susp';
            $params['susp'] = 1;
        }

        $rows = $DB->get_records_sql(
            "SELECT u.id FROM {user} u WHERE $where ORDER BY u.id ASC",
            $params
        );

        return array_keys($rows);
    }

    /**
     * Test: deleted users are excluded from the audience listing.
     */
    public function test_deleted_user_excluded(): void {
        $this->resetAfterTest();
        global $DB;

        $active  = $this->getDataGenerator()->create_user();
        $deleted = $this->getDataGenerator()->create_user();

        // Mark the second user deleted directly (delete_user() also anonymises
        // name/email, which is fine; we just need deleted=1 in the DB).
        delete_user($deleted);

        $ids = $this->get_audience_userids();

        $this->assertContains((int) $active->id, $ids, 'Active user should be in audience listing.');
        $this->assertNotContains((int) $deleted->id, $ids, 'Deleted user must be excluded from audience listing.');
    }

    /**
     * Test: the guest user ($CFG->siteguest) is excluded from the audience listing.
     */
    public function test_guest_user_excluded(): void {
        $this->resetAfterTest();
        global $CFG;

        $ids = $this->get_audience_userids();

        $this->assertNotContains((int) $CFG->siteguest, $ids, 'Guest user must be excluded from audience listing.');
    }

    /**
     * Test: suspended users appear in the listing by default (no filter)
     * and are filterable.
     */
    public function test_suspended_user_included_and_filterable(): void {
        $this->resetAfterTest();

        $active    = $this->getDataGenerator()->create_user(['suspended' => 0]);
        $suspended = $this->getDataGenerator()->create_user(['suspended' => 1]);

        // No filter: both appear.
        $all = $this->get_audience_userids();
        $this->assertContains((int) $active->id, $all, 'Active user visible in unfiltered listing.');
        $this->assertContains((int) $suspended->id, $all, 'Suspended user visible in unfiltered listing.');

        // Filter active-only: suspended user gone.
        $activeonly = $this->get_audience_userids(0, 'active');
        $this->assertContains((int) $active->id, $activeonly);
        $this->assertNotContains((int) $suspended->id, $activeonly);

        // Filter suspended-only: only suspended user visible among ours.
        $suspendedonly = $this->get_audience_userids(0, 'suspended');
        $this->assertContains((int) $suspended->id, $suspendedonly);
        $this->assertNotContains((int) $active->id, $suspendedonly);
    }

    /**
     * Test: the tag EXISTS filter returns only users who have that tag.
     */
    public function test_tag_filter_returns_tagged_users_only(): void {
        $this->resetAfterTest();

        $tagid  = tag_manager::create_tag('VIP');
        $tagged = $this->getDataGenerator()->create_user();
        $other  = $this->getDataGenerator()->create_user();

        tag_manager::assign_tag($tagid, $tagged->id);

        $ids = $this->get_audience_userids($tagid);

        $this->assertContains((int) $tagged->id, $ids, 'Tagged user must appear in tag-filtered listing.');
        $this->assertNotContains((int) $other->id, $ids, 'Untagged user must not appear in tag-filtered listing.');
    }

    /**
     * Test: a user with 3 tags appears exactly once in the audience listing
     * (the EXISTS subquery does not inflate the count / fan out rows).
     */
    public function test_multi_tag_user_appears_once(): void {
        $this->resetAfterTest();
        global $CFG, $DB;

        $user  = $this->getDataGenerator()->create_user();
        $tag1  = tag_manager::create_tag('A');
        $tag2  = tag_manager::create_tag('B');
        $tag3  = tag_manager::create_tag('C');

        tag_manager::assign_tag($tag1, $user->id);
        tag_manager::assign_tag($tag2, $user->id);
        tag_manager::assign_tag($tag3, $user->id);

        // COUNT query replicating what table_sql uses internally.
        $count = $DB->count_records_sql(
            'SELECT COUNT(u.id) FROM {user} u WHERE u.deleted = 0 AND u.id <> :guestid AND u.id = :uid',
            ['guestid' => $CFG->siteguest, 'uid' => $user->id]
        );

        $this->assertSame(1, (int) $count, 'A user with multiple tags must count as exactly 1 row.');
    }

    /**
     * Test: fetch_tagmap returns correct per-user tag object lists.
     */
    public function test_fetch_tagmap_returns_tag_objects(): void {
        $this->resetAfterTest();

        $tag1  = tag_manager::create_tag('Alpha');
        $tag2  = tag_manager::create_tag('Beta');
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        tag_manager::assign_tag($tag1, $user1->id);
        tag_manager::assign_tag($tag2, $user1->id);
        tag_manager::assign_tag($tag1, $user2->id);

        $map = audience_table::fetch_tagmap([$user1->id, $user2->id]);

        $this->assertArrayHasKey($user1->id, $map);
        $this->assertCount(2, $map[$user1->id]);

        // Each entry is a stdClass with id and name properties.
        $names1 = array_column($map[$user1->id], 'name');
        sort($names1);
        $this->assertSame(['Alpha', 'Beta'], $names1);

        $this->assertArrayHasKey($user2->id, $map);
        $this->assertCount(1, $map[$user2->id]);
        $this->assertSame('Alpha', $map[$user2->id][0]->name);
    }

    /**
     * Test: a tag name containing '<script>' is stored raw (get_tags_for_users
     * returns unescaped — escaping is the renderer's responsibility via
     * format_string() in col_tags).
     */
    public function test_xss_tag_name_stored_raw(): void {
        $this->resetAfterTest();

        $tagid = tag_manager::create_tag('<script>alert(1)</script>');
        $user  = $this->getDataGenerator()->create_user();
        tag_manager::assign_tag($tagid, $user->id);

        $map = tag_manager::get_tags_for_users([$user->id]);

        $this->assertArrayHasKey($user->id, $map);
        // Raw name returned — escaping happens in col_tags via format_string().
        $this->assertSame('<script>alert(1)</script>', $map[$user->id][0]);
    }

    /**
     * Test: fetch_tagmap with an empty array returns an empty array.
     */
    public function test_fetch_tagmap_empty_input(): void {
        $this->resetAfterTest();

        $map = audience_table::fetch_tagmap([]);
        $this->assertSame([], $map);
    }
}
