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
 * Campaign manager for Mail Whistle.
 *
 * Centralises campaign-record updates and the draft completeness gate used by
 * the edit wizard. Field writes are whitelisted so callers can never set
 * arbitrary columns from posted data. All access uses portable Moodle DML.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_manager {
    /** @var string Status of a campaign that is still being edited. */
    public const STATUS_DRAFT = 'draft';

    /** @var string Status of a campaign that has all required parts and is ready. */
    public const STATUS_READY = 'ready';

    /** @var string A campaign waiting for its scheduled delivery time. */
    public const STATUS_SCHEDULED = 'scheduled';

    /** @var string A scheduled campaign that could not pass the readiness checks. */
    public const STATUS_SCHEDULE_FAILED = 'schedulefailed';

    /** @var string Status of a campaign currently being delivered. */
    public const STATUS_SENDING = 'sending';

    /** @var string Every recipient email was accepted by the email transport. */
    public const STATUS_SENT = 'sent';

    /** @var string Delivery finished with failures, skips, or uncertain outcomes. */
    public const STATUS_COMPLETED_WITH_ERRORS = 'completedwitherrors';

    /** @var string Delivery finished without accepting any email. */
    public const STATUS_FAILED = 'failed';

    /**
     * Copy saved authoring data and audience rules into a new draft for the current user.
     *
     * The source lock provides one consistent view while authoring or sending is
     * in progress. Recipient snapshots, tracking, logs and tasks belong only to
     * the original campaign; the copy resolves its audience when it is sent.
     *
     * @param int $campaignid Source campaign in any lifecycle state.
     * @return int New draft campaign id.
     */
    public static function duplicate(int $campaignid): int {
        global $DB, $USER;

        $lock = \core\lock\lock_config::get_lock_factory('local_mailwhistle')->get_lock('campaign:' . $campaignid, 10);
        if (!$lock) {
            throw new \moodle_exception('campaignbusy', 'local_mailwhistle');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            $source = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
            $name = trim($source->name) !== '' ? $source->name : get_string('untitledcampaign', 'local_mailwhistle');
            $now = time();
            $copy = (object) [
                'name' => \core_text::substr(get_string('campaigncopyname', 'local_mailwhistle', $name), 0, 255),
                'subject' => $source->subject,
                'bodyhtml' => $source->bodyhtml,
                'bodytext' => $source->bodytext,
                'sendername' => $source->sendername,
                'senderemail' => $source->senderemail,
                'status' => self::STATUS_DRAFT,
                'templateid' => $source->templateid,
                'attachmentsjson' => $source->attachmentsjson,
                'sendengine' => $source->sendengine,
                'createdby' => $USER->id,
                'timecreated' => $now,
                'timemodified' => $now,
                'timescheduled' => 0,
                'timesent' => 0,
            ];
            $copyid = $DB->insert_record('local_mailwhistle_campaigns', $copy);
            foreach ($DB->get_records('local_mailwhistle_audrules', ['campaignid' => $campaignid], 'id ASC') as $rule) {
                $DB->insert_record('local_mailwhistle_audrules', (object) [
                    'campaignid' => $copyid,
                    'type' => $rule->type,
                    'instanceid' => $rule->instanceid,
                    'roleid' => $rule->roleid,
                    'timecreated' => $now,
                ]);
            }
            $transaction->allow_commit();
            return (int) $copyid;
        } catch (\Throwable $exception) {
            if (isset($transaction) && !$transaction->is_disposed()) {
                $transaction->rollback($exception);
            }
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /**
     * Atomically transition a campaign from ready to sending.
     *
     * The campaign lock serialises authoring, starting, retrying, and workers.
     * Snapshot creation and task insertion commit with the status change, so a
     * failed start leaves a ready campaign without a partial audience snapshot.
     *
     * @param int $campaignid The campaign to start sending.
     * @return bool True if this call transitioned ready -> sending.
     */
    public static function begin_sending(int $campaignid): bool {
        return self::start_sending($campaignid, false);
    }

    /**
     * Claim a due schedule and atomically snapshot and queue its recipients.
     *
     * @param int $campaignid Campaign to dispatch.
     * @return bool Whether delivery was queued.
     */
    public static function begin_scheduled_sending(int $campaignid): bool {
        return self::start_sending($campaignid, true);
    }

    /**
     * Start immediate or scheduled delivery under the shared campaign lock.
     *
     * Readiness failures stop a schedule for explicit review. Infrastructure
     * failures roll back the snapshot and leave the schedule available to cron.
     *
     * @param int $campaignid Campaign to start.
     * @param bool $scheduled Whether to claim a due schedule instead of a ready campaign.
     * @return bool Whether delivery was queued.
     */
    private static function start_sending(int $campaignid, bool $scheduled): bool {
        global $DB;

        $lock = \core\lock\lock_config::get_lock_factory('local_mailwhistle')
            ->get_lock('campaign:' . $campaignid, $scheduled ? 0 : 10);
        if (!$lock) {
            if ($scheduled) {
                return false;
            }
            throw new \moodle_exception('campaignbusy', 'local_mailwhistle');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
            $requiredstatus = $scheduled ? self::STATUS_SCHEDULED : self::STATUS_READY;
            if (
                $campaign->status !== $requiredstatus ||
                ($scheduled && ($campaign->timescheduled <= 0 || $campaign->timescheduled > time()))
            ) {
                $transaction->allow_commit();
                return false;
            }
            $readiness = self::get_readiness($campaignid);
            if ($scheduled && $DB->record_exists('local_mailwhistle_recipients', ['campaignid' => $campaignid])) {
                $readiness['errors'][] = get_string('campaignhassnapshot', 'local_mailwhistle');
            }
            if ($readiness['errors']) {
                if ($scheduled) {
                    self::fail_schedule($campaignid, $readiness['errors']);
                    $transaction->allow_commit();
                    return false;
                }
                throw new \moodle_exception('campaignincomplete', 'local_mailwhistle');
            }
            resource_manager::assert_attachments_available($campaign);
            recipient_manager::snapshot_recipients($campaignid);
            if (!recipient_manager::count_pending($campaignid)) {
                if ($scheduled) {
                    self::fail_schedule($campaignid, [get_string('sendnoeligible', 'local_mailwhistle')]);
                    $transaction->allow_commit();
                    return false;
                }
                throw new \moodle_exception('sendnoeligible', 'local_mailwhistle');
            }
            // Freeze the outgoing footer so future reports reflect this send without
            // retroactively adding it to historical campaigns. Tokens remain inert in previews.
            $html = $campaign->bodyhtml;
            $text = $campaign->bodytext;
            if (trim((string) $html) !== '') {
                $html = subscription_manager::replace_unsubscribe_links($html, '{{unsubscribe}}', true);
                $html = subscription_manager::add_footer($html, '{{unsubscribe}}', true);
            }
            if (trim((string) $text) !== '') {
                $text = subscription_manager::replace_unsubscribe_links($text, '{{unsubscribe}}', false);
                $text = subscription_manager::add_footer($text, '{{unsubscribe}}', false);
            }
            $DB->update_record('local_mailwhistle_campaigns', (object) [
                'id' => $campaignid,
                'status' => self::STATUS_SENDING,
                'bodyhtml' => $html,
                'bodytext' => $text,
                'timemodified' => time(),
            ]);
            send_manager::queue_campaign($campaignid);
            $transaction->allow_commit();
            return true;
        } catch (\Throwable $e) {
            if (isset($transaction) && !$transaction->is_disposed()) {
                $transaction->rollback($e);
            }
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Record a blocked schedule while holding the campaign lock and transaction.
     *
     * @param int $campaignid Campaign whose readiness failed.
     * @param string[] $errors Plain-text readiness errors.
     * @return void
     */
    private static function fail_schedule(int $campaignid, array $errors): void {
        global $DB;

        $DB->update_record('local_mailwhistle_campaigns', (object) [
            'id' => $campaignid,
            'status' => self::STATUS_SCHEDULE_FAILED,
            'timemodified' => time(),
        ]);
        \local_mailwhistle\helper::log_send($campaignid, 0, 'error', 'Scheduled campaign needs review', ['errors' => $errors]);
    }

    /**
     * Schedule a ready campaign without freezing its future audience membership.
     *
     * @param int $campaignid Ready campaign.
     * @param int $timescheduled Future Unix timestamp from the user's date selector.
     * @return bool Whether this call scheduled the campaign.
     */
    public static function schedule(int $campaignid, int $timescheduled): bool {
        global $DB;

        $lock = \core\lock\lock_config::get_lock_factory('local_mailwhistle')->get_lock('campaign:' . $campaignid, 10);
        if (!$lock) {
            throw new \moodle_exception('campaignbusy', 'local_mailwhistle');
        }
        try {
            if ($timescheduled <= time()) {
                throw new \moodle_exception('schedulefuture', 'local_mailwhistle');
            }
            $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
            if ($campaign->status !== self::STATUS_READY) {
                return false;
            }
            self::require_no_snapshot($campaignid);
            if (!self::is_complete($campaignid)) {
                throw new \moodle_exception('campaignincomplete', 'local_mailwhistle');
            }
            $DB->update_record('local_mailwhistle_campaigns', (object) [
                'id' => $campaignid,
                'status' => self::STATUS_SCHEDULED,
                'timescheduled' => $timescheduled,
                'timemodified' => time(),
            ]);
            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Cancel an unclaimed schedule, allowing the ready campaign to be reopened.
     *
     * @param int $campaignid Scheduled campaign.
     * @return bool Whether the schedule was cancelled before delivery started.
     */
    public static function cancel_schedule(int $campaignid): bool {
        global $DB;

        $lock = \core\lock\lock_config::get_lock_factory('local_mailwhistle')->get_lock('campaign:' . $campaignid, 10);
        if (!$lock) {
            throw new \moodle_exception('campaignbusy', 'local_mailwhistle');
        }
        try {
            $status = $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $campaignid], MUST_EXIST);
            if (!in_array($status, [self::STATUS_SCHEDULED, self::STATUS_SCHEDULE_FAILED], true)) {
                return false;
            }
            self::require_no_snapshot($campaignid);
            $DB->update_record('local_mailwhistle_campaigns', (object) [
                'id' => $campaignid,
                'status' => self::STATUS_READY,
                'timescheduled' => 0,
                'timemodified' => time(),
            ]);
            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Prevent authoring or changing a schedule after recipients have been frozen.
     *
     * The caller must hold the campaign lock.
     *
     * @param int $campaignid Campaign being changed.
     * @return void
     */
    private static function require_no_snapshot(int $campaignid): void {
        global $DB;

        if ($DB->record_exists('local_mailwhistle_recipients', ['campaignid' => $campaignid])) {
            throw new \moodle_exception('campaignhassnapshot', 'local_mailwhistle');
        }
    }

    /**
     * Finalise a campaign according to its recorded recipient outcomes.
     *
     * The caller must hold the campaign lock. Pending recipients prevent
     * completion. A campaign with no confirmed accepted emails is reported as failed.
     *
     * @param int $campaignid The campaign to finalise.
     * @return void
     */
    public static function mark_sent(int $campaignid): void {
        global $DB;

        $stats = report_manager::get_campaign_stats($campaignid);
        if ($stats->pending > 0) {
            return;
        }
        $status = self::STATUS_SENT;
        if ($stats->sent === 0) {
            $status = self::STATUS_FAILED;
        } else if ($stats->failed + $stats->skipped + $stats->uncertain > 0) {
            $status = self::STATUS_COMPLETED_WITH_ERRORS;
        }
        $now = time();
        $DB->update_record('local_mailwhistle_campaigns', (object) [
            'id' => $campaignid,
            'status' => $status,
            'timesent' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Campaign columns that update_fields() is allowed to write.
     *
     * @var string[]
     */
    private const EDITABLE_FIELDS = [
        'name',
        'subject',
        'bodyhtml',
        'bodytext',
        'sendername',
        'senderemail',
        'attachmentsjson',
    ];

    /**
     * Encode selected resource filenames for storage.
     *
     * @param string[] $filenames Resource filenames.
     * @return string JSON list.
     */
    public static function encode_attachments(array $filenames): string {
        return json_encode(self::decode_attachments_list($filenames));
    }

    /**
     * Decode stored attachment filenames.
     *
     * @param string|null $json Stored JSON.
     * @return string[] Filenames.
     */
    public static function decode_attachments(?string $json): array {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        return self::decode_attachments_list($decoded);
    }

    /**
     * Filter a posted filename list down to safe resource names.
     *
     * @param array $filenames Posted values.
     * @return string[] Filenames.
     */
    private static function decode_attachments_list(array $filenames): array {
        $clean = [];
        foreach ($filenames as $filename) {
            if (!is_string($filename)) {
                continue;
            }
            $filename = trim($filename);
            if ($filename === '' || $filename === '.' || $filename === '..' || str_contains($filename, '/')) {
                continue;
            }
            $clean[$filename] = $filename;
        }
        return array_values($clean);
    }

    /**
     * Update whitelisted campaign fields and bump the modified time.
     *
     * Any key in $fields that is not an editable column is ignored, so posted
     * data can never set arbitrary columns (status, createdby, timesent, ...).
     *
     * @param int $campaignid The campaign to update.
     * @param array $fields Field => value map.
     * @param int|null $templateid Applied template, or null to leave it unchanged.
     * @return void
     * @throws \invalid_parameter_exception When a supplied campaign name is blank.
     */
    public static function update_fields(int $campaignid, array $fields, ?int $templateid = null): void {
        global $DB;

        if (array_key_exists('name', $fields) && trim((string) $fields['name']) === '') {
            throw new \invalid_parameter_exception('Campaign name must not be empty.');
        }

        $record = (object) ['id' => $campaignid];
        foreach (self::EDITABLE_FIELDS as $field) {
            if (array_key_exists($field, $fields)) {
                $record->$field = $fields[$field];
            }
        }
        if ($templateid !== null) {
            $record->templateid = $templateid > 0 ? $templateid : null;
        }

        // Nothing to do if only the id is present.
        if (count((array) $record) <= 1) {
            return;
        }

        self::with_draft_lock($campaignid, static function () use ($DB, $record): void {
            $record->timemodified = time();
            $DB->update_record('local_mailwhistle_campaigns', $record);
        });
    }

    /**
     * Perform one draft write while excluding completion and sending transitions.
     *
     * Callbacks must not invoke another method that acquires the campaign lock.
     *
     * @param int $campaignid Campaign being edited.
     * @param callable $callback Write to perform after checking the current status.
     * @return mixed Callback result.
     */
    public static function with_draft_lock(int $campaignid, callable $callback): mixed {
        global $DB;

        $factory = \core\lock\lock_config::get_lock_factory('local_mailwhistle');
        $lock = $factory->get_lock('campaign:' . $campaignid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $status = $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $campaignid], MUST_EXIST);
            if ($status !== self::STATUS_DRAFT) {
                throw new \moodle_exception('campaignnoteditable', 'local_mailwhistle');
            }
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * Check that a message contains text or a usable image, rather than empty editor markup.
     *
     * @param string $html HTML message.
     * @param string $text Optional plain-text alternative.
     * @return bool Whether the message contains content.
     */
    public static function has_meaningful_body(string $html, string $text = ''): bool {
        $hastext = static fn(string $value): bool => preg_replace('/[\s\p{Z}\x{200B}\x{FEFF}]+/u', '', $value) !== '';
        if ($hastext($text)) {
            return true;
        }
        $html = \local_mailwhistle\builder\preview::remove_document_shell($html);
        $html = preg_replace('/<(script|style|template)\b[^>]*>.*?<\/\1>/is', '', $html);
        $html = clean_text($html, FORMAT_HTML);
        if ($hastext(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
            return true;
        }
        if (!str_contains(strtolower($html), '<img')) {
            return false;
        }
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ($document->getElementsByTagName('img') as $image) {
            if (trim($image->getAttribute('src')) !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * Read the current readiness errors and deduplicated eligible audience size.
     *
     * This method is read-only and may be called while holding the campaign lock.
     *
     * @param int $campaignid The campaign to check.
     * @return array Readiness errors and recipientcount.
     */
    public static function get_readiness(int $campaignid): array {
        global $DB;

        $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid]);
        if (!$campaign) {
            return ['errors' => [get_string('campaignincomplete', 'local_mailwhistle')], 'recipientcount' => 0];
        }
        $errors = [];
        foreach (['name', 'subject'] as $field) {
            if (trim((string) $campaign->$field) === '') {
                $errors[] = get_string('campaignmissing' . $field, 'local_mailwhistle');
            }
        }
        if (!self::has_meaningful_body((string) $campaign->bodyhtml, (string) $campaign->bodytext)) {
            $errors[] = get_string('campaignemptybody', 'local_mailwhistle');
        }
        $recipientcount = count(recipient_manager::resolve_recipients($campaignid));
        if ($recipientcount === 0) {
            $errors[] = get_string('campaignemptyaudience', 'local_mailwhistle');
        }
        $errors = array_merge($errors, resource_manager::get_attachment_errors($campaign));
        return ['errors' => $errors, 'recipientcount' => $recipientcount];
    }

    /**
     * Determine whether the campaign currently has valid content and eligible recipients.
     *
     * @param int $campaignid Campaign to check.
     * @return bool Whether the campaign can be sent.
     */
    public static function is_complete(int $campaignid): bool {
        return empty(self::get_readiness($campaignid)['errors']);
    }

    /**
     * Mark a campaign as ready once it passes the completeness gate.
     *
     * @param int $campaignid The campaign to mark ready.
     * @return void
     * @throws \moodle_exception When the campaign is not yet complete.
     */
    public static function mark_complete(int $campaignid): void {
        global $DB;

        self::with_draft_lock($campaignid, static function () use ($DB, $campaignid): void {
            if (!self::is_complete($campaignid)) {
                throw new \moodle_exception('campaignincomplete', 'local_mailwhistle');
            }
            $DB->update_record('local_mailwhistle_campaigns', (object) [
                'id' => $campaignid,
                'status' => self::STATUS_READY,
                'timemodified' => time(),
            ]);
        });
    }

    /**
     * Reopen a ready campaign without allowing delivery states to become editable.
     *
     * @param int $campaignid Campaign to reopen.
     * @return void
     */
    public static function return_to_draft(int $campaignid): void {
        global $DB;

        $factory = \core\lock\lock_config::get_lock_factory('local_mailwhistle');
        $lock = $factory->get_lock('campaign:' . $campaignid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $status = $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $campaignid], MUST_EXIST);
            if ($status !== self::STATUS_READY) {
                throw new \moodle_exception('campaigncannotreopen', 'local_mailwhistle');
            }
            self::require_no_snapshot($campaignid);
            $DB->update_record('local_mailwhistle_campaigns', (object) [
                'id' => $campaignid,
                'status' => self::STATUS_DRAFT,
                'timemodified' => time(),
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Record the template last loaded into a campaign.
     *
     * Pass 0 to clear the stored template id. The body is not rewritten here;
     * this is only the usage pointer used by reports and delete protection.
     *
     * @param int $campaignid The campaign to update.
     * @param int $templateid Template id, or 0 to clear.
     * @return void
     */
    public static function set_templateid(int $campaignid, int $templateid): void {
        self::update_fields($campaignid, [], $templateid);
    }
}
