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

use local_mailwhistle\output\resources;

/**
 * Send manager for Mail Whistle campaigns.
 *
 * Delivers a campaign to its snapshotted recipients in bounded batches. Each
 * recipient is sent via Moodle's messaging (message_send) using the plugin's
 * 'campaign' message provider; its per-recipient status is updated and a
 * sendlog row is written. When no pending recipients remain the campaign is
 * marked sent. The whole thing is idempotent: only recipients with status
 * 'pending' are ever processed, so re-running never double-sends.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_manager {
    /**
     * Process one batch of a campaign's pending recipients.
     *
     * Sends up to $batchsize pending recipients. If none remain pending after
     * the batch, the campaign is finalised as sent. Returns the number of
     * recipients still pending afterwards (0 means the campaign is complete),
     * so a task can decide whether to re-queue itself.
     *
     * @param int $campaignid The campaign to process.
     * @param int $batchsize Maximum recipients to send this run.
     * @return int Recipients still pending after this batch.
     */
    public static function process_campaign(int $campaignid, int $batchsize): int {
        global $DB;

        $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], '*', MUST_EXIST);

        $batch = recipient_manager::get_pending_recipients($campaignid, $batchsize);
        foreach ($batch as $recipient) {
            self::send_to_recipient($campaign, $recipient);
        }

        $pending = recipient_manager::count_pending($campaignid);
        if ($pending === 0 && $campaign->status === campaign_manager::STATUS_SENDING) {
            campaign_manager::mark_sent($campaignid);
        }

        return $pending;
    }

    /**
     * Send a single campaign message to one recipient and record the outcome.
     *
     * Delivery goes through Moodle's messaging (message_send) via the plugin's
     * 'campaign' message provider, so it respects the recipient's notification
     * output preferences and appears in their message area.
     *
     * @param \stdClass $campaign The campaign record.
     * @param \stdClass $recipient The recipient record (snapshotted email/name).
     * @return void
     */
    private static function send_to_recipient(\stdClass $campaign, \stdClass $recipient): void {
        global $DB;

        $to = $DB->get_record('user', ['id' => $recipient->userid]);
        if (!$to) {
            recipient_manager::mark_failed($recipient->id, 'recipient user not found');
            \local_mailwhistle\helper::log_send($campaign->id, $recipient->id, 'error', 'recipient user not found');
            return;
        }

        // Fill {{firstname}} etc. with this recipient's snapshot data, then
        // rewrite links and inject the open pixel for this specific recipient.
        $personalisedhtml = placeholder_manager::apply((string) $campaign->bodyhtml, $recipient, true);
        $bodyhtml = tracking_manager::prepare_body(
            $personalisedhtml,
            (int) $campaign->id,
            (int) $recipient->id
        );

        $message = new \core\message\message();
        $message->component = 'local_mailwhistle';
        $message->name = 'campaign';
        $message->userfrom = self::get_from_user($campaign);
        $message->userto = $to;
        $message->subject = format_string(placeholder_manager::apply((string) $campaign->subject, $recipient, false));
        $message->fullmessage = placeholder_manager::apply((string) $campaign->bodytext, $recipient, false);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $bodyhtml;
        $message->smallmessage = '';
        $message->notification = 1;
        self::apply_attachments($message, $campaign);

        try {
            $messageid = message_send($message);
        } catch (\Throwable $e) {
            $messageid = false;
            $error = $e->getMessage();
        }

        if (!empty($messageid)) {
            recipient_manager::mark_sent($recipient->id);
            \local_mailwhistle\helper::log_send(
                $campaign->id,
                $recipient->id,
                'info',
                'sent',
                ['messageid' => $messageid]
            );
        } else {
            $error = $error ?? 'message_send returned false';
            recipient_manager::mark_failed($recipient->id, $error);
            \local_mailwhistle\helper::log_send($campaign->id, $recipient->id, 'error', $error);
        }
    }

    /**
     * Send a one-off test copy of a campaign to a single user.
     *
     * Unlike a real send this creates no recipient row, writes no sendlog and
     * injects no open/click tracking, so it never touches campaign statistics.
     * Placeholders are filled from the reviewer's profile so the copy shows
     * how the newsletter will look. The subject is prefixed so a test copy is
     * obvious in the inbox. Delivery still goes through Moodle messaging so it
     * honours the recipient's output preferences exactly like a live send would.
     *
     * @param int $campaignid The draft campaign to preview.
     * @param \stdClass $to The user record to send the test copy to.
     * @return bool True when message_send accepted the message.
     */
    public static function send_test(int $campaignid, \stdClass $to): bool {
        global $DB;

        $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], '*', MUST_EXIST);

        $subject = get_string('testmail_subjectprefix', 'local_mailwhistle') . ' ' . format_string(
            placeholder_manager::apply((string) $campaign->subject, $to, false)
        );

        $message = new \core\message\message();
        $message->component = 'local_mailwhistle';
        $message->name = 'campaign';
        $message->userfrom = self::get_from_user($campaign);
        $message->userto = $to;
        $message->subject = $subject;
        $message->fullmessage = placeholder_manager::apply((string) $campaign->bodytext, $to, false);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = placeholder_manager::apply((string) $campaign->bodyhtml, $to, true);
        $message->smallmessage = '';
        $message->notification = 1;
        self::apply_attachments($message, $campaign);

        try {
            $messageid = message_send($message);
        } catch (\Throwable $e) {
            return false;
        }

        return !empty($messageid);
    }

    /**
     * Resolve the "from" user for a campaign message.
     *
     * Uses the campaign creator when available, else the site support user, so
     * message_send always has a valid sender. The campaign's own sender name/
     * email is display metadata carried in the body/subject; Moodle messaging
     * controls the actual transport from-address.
     *
     * @param \stdClass $campaign The campaign record.
     * @return \stdClass A user record suitable as userfrom.
     */
    private static function get_from_user(\stdClass $campaign): \stdClass {
        global $DB;

        if (!empty($campaign->createdby)) {
            $creator = $DB->get_record('user', ['id' => $campaign->createdby, 'deleted' => 0]);
            if ($creator) {
                return $creator;
            }
        }
        return \core_user::get_support_user();
    }

    /**
     * Attach selected resource files to a campaign message.
     *
     * Moodle's email processor accepts one stored_file. A single selection is
     * attached as-is; several files are zipped first.
     *
     * @param \core\message\message $message Message being sent.
     * @param \stdClass $campaign Campaign record.
     * @return void
     */
    private static function apply_attachments(\core\message\message $message, \stdClass $campaign): void {
        global $CFG;

        if (empty($CFG->allowattachments)) {
            return;
        }

        $files = resources::get_files_by_filenames(
            campaign_manager::decode_attachments($campaign->attachmentsjson ?? '')
        );
        if (!$files) {
            return;
        }

        if (count($files) === 1) {
            $file = reset($files);
            $message->attachment = $file;
            $message->attachname = $file->get_filename();
            return;
        }

        $zip = self::zip_attachment_files($files, (int) $campaign->id);
        if ($zip) {
            $message->attachment = $zip;
            $message->attachname = 'attachments.zip';
            return;
        }

        $file = reset($files);
        $message->attachment = $file;
        $message->attachname = $file->get_filename();
    }

    /**
     * Pack several resource files into one zip stored_file for this campaign.
     *
     * @param \stored_file[] $files Resource files.
     * @param int $campaignid Campaign id used as the zip itemid.
     * @return \stored_file|null Zip file, or null when packing fails.
     */
    private static function zip_attachment_files(array $files, int $campaignid): ?\stored_file {
        $fs = get_file_storage();
        $contextid = \context_system::instance()->id;
        $fs->delete_area_files($contextid, 'local_mailwhistle', resources::ZIP_FILEAREA, $campaignid);

        $pack = [];
        foreach ($files as $file) {
            $pack[$file->get_filename()] = $file;
        }

        $zippath = make_request_directory() . '/attachments.zip';
        $packer = get_file_packer('application/zip');
        if (!$packer->archive_to_pathname($pack, $zippath) || !file_exists($zippath)) {
            return null;
        }

        return $fs->create_file_from_pathname(
            [
                'contextid' => $contextid,
                'component' => 'local_mailwhistle',
                'filearea' => resources::ZIP_FILEAREA,
                'itemid' => $campaignid,
                'filepath' => '/',
                'filename' => 'attachments.zip',
            ],
            $zippath
        );
    }
}
