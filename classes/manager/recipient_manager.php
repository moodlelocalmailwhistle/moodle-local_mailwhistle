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
 * Recipient manager for Mail Whistle campaigns.
 *
 * Resolves a campaign's audience tags into the set of users it will be sent to,
 * snapshots that set into the recipients table (frozen name/email at send time),
 * and provides the per-recipient status transitions the send task relies on.
 * All access uses portable Moodle DML (bound params, {table} placeholders).
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recipient_manager {
    /** @var string Recipient queued but not yet sent. */
    public const STATUS_PENDING = 'pending';

    /** @var string Recipient successfully sent. */
    public const STATUS_SENT = 'sent';

    /** @var string Recipient send failed. */
    public const STATUS_FAILED = 'failed';

    /** @var string Email transport is in progress. */
    public const STATUS_SENDING = 'sending';

    /** @var string Email deliberately skipped by account or notification policy. */
    public const STATUS_SKIPPED = 'skipped';

    /** @var string Transport was interrupted; acceptance cannot be established. */
    public const STATUS_UNCERTAIN = 'uncertain';

    /**
     * Resolve the users a campaign should be sent to.
     *
     * Starts from the users in the campaign's audience tags, then removes
     * deleted/suspended users and users unsubscribed at global scope. Returns
     * full user records, deduplicated by user id.
     *
     * @param int $campaignid The campaign to resolve for.
     * @return \stdClass[] User records keyed by user id.
     */
    public static function resolve_recipients(int $campaignid): array {
        global $DB;

        $tagids = audience_manager::get_campaign_tagids($campaignid);
        $userids = tag_manager::get_userids_for_tags($tagids);
        if (empty($userids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');

        // Active, non-deleted users only; exclude anyone unsubscribed globally.
        $sql = "SELECT u.id, u.email, u.firstname, u.lastname
                  FROM {user} u
                 WHERE u.id $insql
                   AND u.deleted = 0
                   AND u.suspended = 0
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {local_mailwhistle_unsubscribes} un
                        WHERE un.userid = u.id
                          AND un.scope = :globalscope
                   )";
        $params = $inparams + ['globalscope' => 'global'];

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Snapshot a campaign's resolved recipients into the recipients table.
     *
     * Idempotent: rows already present (by the UNIQUE campaignid,userid index)
     * are left untouched, so re-running never duplicates rows or resets a
     * recipient that has already been sent. The recipient's name/email are
     * frozen at snapshot time.
     *
     * @param int $campaignid The campaign to snapshot.
     * @return int Number of new recipient rows inserted.
     */
    public static function snapshot_recipients(int $campaignid): int {
        global $DB;

        $recipients = self::resolve_recipients($campaignid);
        if (empty($recipients)) {
            return 0;
        }

        $existing = $DB->get_fieldset_select(
            'local_mailwhistle_recipients',
            'userid',
            'campaignid = :campaignid',
            ['campaignid' => $campaignid]
        );
        $existing = array_flip(array_map('intval', $existing));

        $now = time();
        $inserted = 0;
        foreach ($recipients as $user) {
            if (isset($existing[(int) $user->id])) {
                continue;
            }
            $DB->insert_record('local_mailwhistle_recipients', (object) [
                'campaignid' => $campaignid,
                'userid' => (int) $user->id,
                'email' => (string) $user->email,
                'firstname' => (string) $user->firstname,
                'lastname' => (string) $user->lastname,
                'status' => self::STATUS_PENDING,
                'attempts' => 0,
                'timesent' => 0,
                'timemodified' => $now,
                'error' => null,
            ]);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Fetch a batch of pending recipients for a campaign.
     *
     * @param int $campaignid The campaign.
     * @param int $limit Maximum rows to return.
     * @return \stdClass[] Pending recipient records.
     */
    public static function get_pending_recipients(int $campaignid, int $limit): array {
        global $DB;

        return $DB->get_records(
            'local_mailwhistle_recipients',
            ['campaignid' => $campaignid, 'status' => self::STATUS_PENDING],
            'id ASC',
            '*',
            0,
            $limit
        );
    }

    /**
     * Count a campaign's recipients still pending.
     *
     * @param int $campaignid The campaign.
     * @return int Pending count.
     */
    public static function count_pending(int $campaignid): int {
        global $DB;

        return $DB->count_records('local_mailwhistle_recipients', [
            'campaignid' => $campaignid,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Mark a recipient as sent.
     *
     * @param int $recipientid The recipient row id.
     * @return void
     */
    public static function mark_sent(int $recipientid): void {
        global $DB;

        $now = time();
        $DB->update_record('local_mailwhistle_recipients', (object) [
            'id' => $recipientid,
            'status' => self::STATUS_SENT,
            'timesent' => $now,
            'timemodified' => $now,
            'error' => null,
        ]);
    }

    /**
     * Mark a recipient as failed, incrementing the attempt count.
     *
     * @param int $recipientid The recipient row id.
     * @param string $error The failure reason.
     * @return void
     */
    public static function mark_failed(int $recipientid, string $error): void {
        global $DB;

        $recipient = $DB->get_record('local_mailwhistle_recipients', ['id' => $recipientid], '*', MUST_EXIST);
        $now = time();
        $DB->update_record('local_mailwhistle_recipients', (object) [
            'id' => $recipientid,
            'status' => self::STATUS_FAILED,
            'attempts' => (int) $recipient->attempts + ($recipient->status === self::STATUS_SENDING ? 0 : 1),
            'timesent' => 0,
            'timemodified' => $now,
            'error' => $error,
        ]);
    }

    /**
     * Record entry into transport before an external system can accept email.
     *
     * @param int $recipientid Recipient row id.
     * @return void
     */
    public static function mark_sending(int $recipientid): void {
        global $DB;

        $recipient = $DB->get_record('local_mailwhistle_recipients', ['id' => $recipientid], '*', MUST_EXIST);
        $DB->update_record('local_mailwhistle_recipients', (object) [
            'id' => $recipientid,
            'status' => self::STATUS_SENDING,
            'attempts' => (int) $recipient->attempts + 1,
            'timemodified' => time(),
            'error' => null,
        ]);
    }

    /**
     * Record a deliberate skip without counting it as a transport attempt.
     *
     * @param int $recipientid Recipient row id.
     * @param string $reason Localised explanation.
     * @return void
     */
    public static function mark_skipped(int $recipientid, string $reason): void {
        global $DB;

        $DB->update_record('local_mailwhistle_recipients', (object) [
            'id' => $recipientid, 'status' => self::STATUS_SKIPPED,
            'timesent' => 0, 'timemodified' => time(), 'error' => $reason,
        ]);
    }

    /**
     * Record an unknown transport outcome which must never be retried blindly.
     *
     * @param int $recipientid Recipient row id.
     * @param string $reason Localised explanation.
     * @return void
     */
    public static function mark_uncertain(int $recipientid, string $reason): void {
        global $DB;

        $DB->update_record('local_mailwhistle_recipients', (object) [
            'id' => $recipientid, 'status' => self::STATUS_UNCERTAIN,
            'timesent' => 0, 'timemodified' => time(), 'error' => $reason,
        ]);
    }

    /**
     * Recover recipients left in transport by a previous, interrupted worker.
     *
     * The caller holds the campaign lock, so no live worker owns these rows.
     *
     * @param int $campaignid Campaign id.
     * @return void
     */
    public static function recover_interrupted(int $campaignid): void {
        global $DB;

        $DB->execute('UPDATE {local_mailwhistle_recipients}
                        SET status = :uncertain, error = :error, timemodified = :now
                      WHERE campaignid = :campaignid AND status = :sending', [
            'uncertain' => self::STATUS_UNCERTAIN,
            'error' => get_string('delivery_uncertain', 'local_mailwhistle'),
            'now' => time(), 'campaignid' => $campaignid, 'sending' => self::STATUS_SENDING,
        ]);
    }
}
