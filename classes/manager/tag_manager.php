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

namespace local_mailwhistle\manager;

/**
 * Tag manager for Mail Whistle audience segmentation.
 *
 * All database access for tag definitions and user-tag assignments is
 * centralised here.  Every mutating method triggers the matching audit
 * event (tag_created / tag_assigned / tag_unassigned) after the DB
 * write succeeds, and uses only portable Moodle DML (bound params,
 * {table} placeholders, no DB-specific literals).
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tag_manager {
    /**
     * Maximum number of user IDs accepted in a single bulk-assign call.
     *
     * Defense-in-depth only; unreachable in v1 because selectable rows are
     * bounded by the perpage clamp (≤ 100).
     */
    private const MAX_BULK_ASSIGN = 1000;

    /**
     * Normalise a tag display name to a deduplication key.
     *
     * Trims leading/trailing whitespace, collapses internal whitespace to a
     * single space, then lowercases using core_text so multi-byte characters
     * are handled correctly.
     *
     * @param string $name Raw display name.
     * @return string Normalised shortname.
     */
    public static function normalize(string $name): string {
        $trimmed = trim($name);
        $collapsed = preg_replace('/\s+/', ' ', $trimmed);
        return \core_text::strtolower($collapsed);
    }

    /**
     * Create a new tag definition.
     *
     * If another request races to insert the same shortname concurrently, the
     * unique index on shortname causes a dml_write_exception.  We catch that,
     * re-query by shortname, and return the winning row's id — the correct
     * Moodle idiom under READ COMMITTED isolation.
     *
     * @param string $name Display name (will be trimmed).
     * @return int ID of the newly created (or pre-existing) tag.
     * @throws \invalid_parameter_exception When $name is empty after trimming.
     */
    public static function create_tag(string $name): int {
        global $DB, $USER;

        $displayname = trim($name);
        if ($displayname === '') {
            throw new \invalid_parameter_exception('Tag name must not be empty.');
        }

        // Cap to the char(255) column limit before computing the shortname.
        if (\core_text::strlen($displayname) > 255) {
            $displayname = \core_text::substr($displayname, 0, 255);
        }

        $shortname = self::normalize($displayname);
        // Shortname is derived from the capped name but cap defensively too.
        if (\core_text::strlen($shortname) > 255) {
            $shortname = \core_text::substr($shortname, 0, 255);
        }

        $now = time();

        $record = (object) [
            'name'         => $displayname,
            'shortname'    => $shortname,
            'description'  => null,
            'usermodified' => (int) $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];

        try {
            $id = $DB->insert_record('local_mailwhistle_tag', $record);
        } catch (\dml_write_exception $e) {
            // Check whether a row with this shortname now exists:
            // - If yes: a concurrent insert raced us and won; return the winner's id.
            // - If no: a real DB failure (disk, lock, unexpected constraint); re-throw.
            $existing = $DB->get_record('local_mailwhistle_tag', ['shortname' => $shortname], 'id');
            if ($existing) {
                return (int) $existing->id;
            }
            throw $e;
        }

        // Trigger audit event.
        \local_mailwhistle\event\tag_created::create([
            'context'  => \context_system::instance(),
            'objectid' => $id,
        ])->trigger();

        return (int) $id;
    }

    /**
     * Return an existing tag id for the given name, creating one if needed.
     *
     * @param string $name Display name.
     * @return int Tag id.
     */
    public static function get_or_create_tag(string $name): int {
        global $DB;

        $shortname = self::normalize($name);
        $existing = $DB->get_record('local_mailwhistle_tag', ['shortname' => $shortname], 'id');
        if ($existing) {
            return (int) $existing->id;
        }

        return self::create_tag($name);
    }

    /**
     * Rename an existing tag definition.
     *
     * @param int    $tagid ID of the tag to rename.
     * @param string $name  New display name.
     * @return void
     */
    public static function rename_tag(int $tagid, string $name): void {
        global $DB, $USER;

        $displayname = trim($name);
        if ($displayname === '') {
            throw new \invalid_parameter_exception('Tag name must not be empty.');
        }

        $DB->update_record('local_mailwhistle_tag', (object) [
            'id'           => $tagid,
            'name'         => $displayname,
            'shortname'    => self::normalize($displayname),
            'usermodified' => (int) $USER->id,
            'timemodified' => time(),
        ]);
    }

    /**
     * Delete a tag definition and all its assignments (transactional).
     *
     * @param int $tagid ID of the tag to delete.
     * @return void
     */
    public static function delete_tag(int $tagid): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_mailwhistle_tag_assign', ['tagid' => $tagid]);
        $DB->delete_records('local_mailwhistle_tag', ['id' => $tagid]);
        $transaction->allow_commit();
    }

    /**
     * Assign a tag to a single user (idempotent).
     *
     * If the assignment already exists (unique index on tagid+userid) the
     * dml_write_exception is caught and silently swallowed — the post-
     * condition (user has the tag) is already satisfied.
     *
     * @param int      $tagid   Tag id.
     * @param int      $userid  User id to tag.
     * @param int|null $actorid Actor user id; defaults to current $USER->id.
     * @return void
     */
    public static function assign_tag(int $tagid, int $userid, ?int $actorid = null): void {
        global $DB, $USER;

        $actor = $actorid ?? (int) $USER->id;

        $record = (object) [
            'tagid'        => $tagid,
            'userid'       => $userid,
            'usermodified' => $actor,
            'timecreated'  => time(),
        ];

        try {
            $assignid = $DB->insert_record('local_mailwhistle_tag_assign', $record);
        } catch (\dml_write_exception $e) {
            // Check whether this (tagid, userid) pair now exists:
            // - If yes: a concurrent or duplicate insert; idempotent — nothing to do.
            // - If no: a real DB failure (lock, disk, unexpected constraint); re-throw.
            if ($DB->record_exists('local_mailwhistle_tag_assign', ['tagid' => $tagid, 'userid' => $userid])) {
                return;
            }
            throw $e;
        }

        \local_mailwhistle\event\tag_assigned::create([
            'context'  => \context_system::instance(),
            'objectid' => $assignid,
        ])->trigger();
    }

    /**
     * Bulk-assign a tag to multiple users (idempotent, transactional).
     *
     * @param int   $tagid   Tag id.
     * @param array $userids Array of user ids (int).
     * @return int Number of assignments newly created (pre-existing skipped).
     */
    public static function assign_tag_to_users(int $tagid, array $userids): int {
        global $DB;

        // Defense-in-depth cap; unreachable via the page-bounded checkbox list.
        $userids = array_slice($userids, 0, self::MAX_BULK_ASSIGN);

        $count = 0;
        $transaction = $DB->start_delegated_transaction();

        foreach ($userids as $userid) {
            $existing = $DB->record_exists('local_mailwhistle_tag_assign', [
                'tagid'  => (int) $tagid,
                'userid' => (int) $userid,
            ]);

            if (!$existing) {
                self::assign_tag($tagid, (int) $userid);
                $count++;
            }
        }

        $transaction->allow_commit();

        return $count;
    }

    /**
     * Remove a tag assignment from a user.
     *
     * The assignment row id is captured before the delete so it can be
     * recorded in the tag_unassigned event objectid.
     *
     * @param int $tagid  Tag id.
     * @param int $userid User id.
     * @return void
     */
    public static function unassign_tag(int $tagid, int $userid): void {
        global $DB;

        $row = $DB->get_record('local_mailwhistle_tag_assign', [
            'tagid'  => $tagid,
            'userid' => $userid,
        ], 'id');

        if (!$row) {
            // Assignment does not exist — nothing to remove.
            return;
        }

        $assignid = (int) $row->id;
        $DB->delete_records('local_mailwhistle_tag_assign', ['id' => $assignid]);

        \local_mailwhistle\event\tag_unassigned::create([
            'context'  => \context_system::instance(),
            'objectid' => $assignid,
        ])->trigger();
    }

    /**
     * Return all tag definitions ordered by name.
     *
     * Used to populate the filter dropdown and the apply-tag select.
     *
     * @return \stdClass[] Array of tag records (id, name, shortname, …).
     */
    public static function get_all_tags(): array {
        global $DB;
        return array_values($DB->get_records('local_mailwhistle_tag', null, 'name ASC'));
    }

    /**
     * Return a map of userid => tag name list for a page of users.
     *
     * Designed for the two-query per-page tag fetch inside audience_table's
     * query_db() override.  Returns an empty array when $userids is empty.
     *
     * @param int[] $userids User ids visible on the current table page.
     * @return array<int, string[]> Map of userid => array of tag name strings.
     */
    public static function get_tags_for_users(array $userids): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');

        // Select ta.id first: get_records_sql() keys the returned array by the
        // first column, so it must be unique. Keying by userid would collapse
        // multiple tags per user (the second row would overwrite the first).
        $sql = "SELECT ta.id, ta.userid, t.name
                  FROM {local_mailwhistle_tag_assign} ta
                  JOIN {local_mailwhistle_tag} t ON t.id = ta.tagid
                 WHERE ta.userid $insql
              ORDER BY t.name ASC";

        $rows = $DB->get_records_sql($sql, $inparams);

        // Group tag names by userid in PHP (portable, avoids sql_group_concat).
        $map = [];
        foreach ($rows as $row) {
            $uid = (int) $row->userid;
            if (!isset($map[$uid])) {
                $map[$uid] = [];
            }
            $map[$uid][] = $row->name;
        }

        return $map;
    }
}
