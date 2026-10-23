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
 * Email delivery, recovery, and explicit retry for campaign recipient snapshots.
 *
 * The campaign lock serialises workers and state changes. SMTP cannot be made
 * atomic with database writes: an interrupted in-flight recipient is therefore
 * marked uncertain rather than automatically sending a possible duplicate.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_manager {
    /**
     * Queue one successor, ignoring only the currently executing task.
     *
     * The caller must hold the campaign lock. Inspecting decoded custom data
     * keeps this portable across databases with different text column types.
     *
     * @param int $campaignid Campaign id.
     * @param int $currenttaskid Running task id, or zero outside a worker.
     * @return void
     */
    public static function queue_campaign(int $campaignid, int $currenttaskid = 0): void {
        global $DB;

        $classname = '\\' . \local_mailwhistle\task\send_campaign::class;
        $tasks = $DB->get_records('task_adhoc', ['classname' => $classname], '', 'id, customdata');
        foreach ($tasks as $task) {
            $data = json_decode($task->customdata);
            if ((int) $task->id !== $currenttaskid && (int) ($data->campaignid ?? 0) === $campaignid) {
                return;
            }
        }
        $task = new \local_mailwhistle\task\send_campaign();
        $task->set_custom_data(['campaignid' => $campaignid]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Process one bounded batch, under the same lock as starting and retrying.
     *
     * @param int $campaignid Campaign id.
     * @param int $batchsize Maximum recipients to process.
     * @param int $currenttaskid Running task id; zero for direct invocations.
     * @return int Number of recipients still pending.
     */
    public static function process_campaign(int $campaignid, int $batchsize, int $currenttaskid = 0): int {
        global $DB;

        $lock = \core\lock\lock_config::get_lock_factory('local_mailwhistle')->get_lock('campaign:' . $campaignid, 10);
        if (!$lock) {
            throw new \moodle_exception('campaignbusy', 'local_mailwhistle');
        }
        try {
            $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid]);
            if (!$campaign || $campaign->status !== campaign_manager::STATUS_SENDING) {
                return 0;
            }
            recipient_manager::recover_interrupted($campaignid);
            foreach (recipient_manager::get_pending_recipients($campaignid, max(1, $batchsize)) as $recipient) {
                self::send_to_recipient($campaign, $recipient);
            }
            $pending = recipient_manager::count_pending($campaignid);
            if ($pending === 0) {
                campaign_manager::mark_sent($campaignid);
            } else if ($currenttaskid > 0) {
                self::queue_campaign($campaignid, $currenttaskid);
            }
            return $pending;
        } finally {
            $lock->release();
        }
    }

    /**
     * Recover missing workers and finalise sending campaigns with no work left.
     *
     * @param int $campaignid Campaign id.
     * @return void
     */
    public static function resume_campaign(int $campaignid): void {
        global $DB;

        $lock = \core\lock\lock_config::get_lock_factory('local_mailwhistle')->get_lock('campaign:' . $campaignid, 0);
        if (!$lock) {
            return;
        }
        try {
            if (
                $DB->get_field('local_mailwhistle_campaigns', 'status', ['id' => $campaignid])
                    !== campaign_manager::STATUS_SENDING
            ) {
                return;
            }
            recipient_manager::recover_interrupted($campaignid);
            if (recipient_manager::count_pending($campaignid) > 0) {
                self::queue_campaign($campaignid);
            } else {
                campaign_manager::mark_sent($campaignid);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Explicitly retry only known failed recipients of a completed campaign.
     *
     * Sent, skipped, and uncertain recipients are never reset. The queue and
     * status change commit together; current eligibility is checked per email.
     *
     * @param int $campaignid Campaign id.
     * @return bool Whether any failed recipients were queued.
     */
    public static function retry_failed(int $campaignid): bool {
        global $DB;

        $lock = \core\lock\lock_config::get_lock_factory('local_mailwhistle')->get_lock('campaign:' . $campaignid, 10);
        if (!$lock) {
            throw new \moodle_exception('campaignbusy', 'local_mailwhistle');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
            if (
                !in_array($campaign->status, [campaign_manager::STATUS_FAILED,
                    campaign_manager::STATUS_COMPLETED_WITH_ERRORS, campaign_manager::STATUS_SENT], true)
                    || !$DB->record_exists('local_mailwhistle_recipients', [
                        'campaignid' => $campaignid, 'status' => recipient_manager::STATUS_FAILED,
                    ])
            ) {
                $transaction->allow_commit();
                return false;
            }
            resource_manager::assert_attachments_available($campaign);
            $DB->set_field('local_mailwhistle_recipients', 'status', recipient_manager::STATUS_PENDING, [
                'campaignid' => $campaignid, 'status' => recipient_manager::STATUS_FAILED,
            ]);
            $DB->update_record('local_mailwhistle_campaigns', (object) [
                'id' => $campaignid,
                'status' => campaign_manager::STATUS_SENDING,
                'timemodified' => time(),
            ]);
            self::queue_campaign($campaignid);
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
     * Return why Moodle would not send this user's campaign email.
     *
     * Mirrors message_send's email provider/locked/default/user preferences.
     * It also rechecks opt-outs and account changes made after the snapshot.
     *
     * @param \stdClass $user Full current user record.
     * @return string Localised reason, or empty when email is permitted.
     */
    public static function get_skip_reason(\stdClass $user): string {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/message/lib.php');
        if (!empty($user->deleted) || !empty($user->suspended) || $user->auth === 'nologin') {
            return get_string('delivery_inactive', 'local_mailwhistle');
        }
        if ($DB->record_exists('local_mailwhistle_unsubscribes', ['userid' => $user->id, 'scope' => 'global'])) {
            return get_string('delivery_unsubscribed', 'local_mailwhistle');
        }
        if (!empty($CFG->noemailever)) {
            return get_string('delivery_disabled', 'local_mailwhistle');
        }
        $defaults = get_message_output_default_preferences();
        if (!empty($defaults->local_mailwhistle_campaign_disable)) {
            return get_string('delivery_disabled', 'local_mailwhistle');
        }
        $providerallowed = false;
        foreach (message_get_providers_for_user($user->id) as $provider) {
            if ($provider->component === 'local_mailwhistle' && $provider->name === 'campaign') {
                $providerallowed = true;
                break;
            }
        }
        if (!$providerallowed || (!\core_user::is_real_user($user->id) && !empty($user->emailstop))) {
            return get_string('delivery_disabled', 'local_mailwhistle');
        }
        if (!$DB->get_field('message_processors', 'enabled', ['name' => 'email'])) {
            return get_string('delivery_disabled', 'local_mailwhistle');
        }
        $processor = get_message_processor('email');
        if (!$processor->is_system_configured() || !$processor->is_user_configured($user)) {
            return get_string('delivery_disabled', 'local_mailwhistle');
        }
        $preference = 'message_provider_local_mailwhistle_campaign_enabled';
        $locked = !empty($defaults->email_provider_local_mailwhistle_campaign_locked);
        $enabled = (string) ($defaults->$preference ?? '');
        if (!$locked) {
            if (!empty($user->emailstop)) {
                return get_string('delivery_optedout', 'local_mailwhistle');
            }
            $userpreference = get_user_preferences($preference, null, $user);
            if ($userpreference) {
                $enabled = $userpreference;
            }
        }
        if (!in_array('email', explode(',', $enabled), true)) {
            return get_string('delivery_optedout', 'local_mailwhistle');
        }
        if (str_ends_with(strtolower(self::get_destination($user)), '.invalid')) {
            return get_string('delivery_invaliddomain', 'local_mailwhistle');
        }
        return '';
    }

    /**
     * Resolve the email processor's effective address, including site diversion.
     *
     * @param \stdClass $user Current user record.
     * @return string Effective destination address.
     */
    public static function get_destination(\stdClass $user): string {
        global $CFG;

        $email = $user->email;
        if (!empty($CFG->messagingallowemailoverride)) {
            $override = clean_param(get_user_preferences('message_processor_email_email', '', $user), PARAM_EMAIL);
            if ($override !== '') {
                $email = $override;
            }
        }
        if (email_should_be_diverted($email)) {
            $email = $CFG->divertallemailsto;
        }
        return $email;
    }

    /**
     * Send one email and persist its outcome without involving other processors.
     *
     * @param \stdClass $campaign Campaign record.
     * @param \stdClass $recipient Recipient snapshot.
     * @return void
     */
    private static function send_to_recipient(\stdClass $campaign, \stdClass $recipient): void {
        global $DB;

        $to = $DB->get_record('user', ['id' => $recipient->userid]);
        if (!$to) {
            recipient_manager::mark_skipped($recipient->id, get_string('delivery_inactive', 'local_mailwhistle'));
            return;
        }
        $reason = self::get_skip_reason($to);
        if ($reason !== '') {
            recipient_manager::mark_skipped($recipient->id, $reason);
            return;
        }
        try {
            resource_manager::assert_attachments_available($campaign);
            $recipient->email = self::get_destination($to);
            if (!validate_email($recipient->email)) {
                throw new \moodle_exception('invalidemail');
            }
            $DB->set_field('local_mailwhistle_recipients', 'email', $recipient->email, ['id' => $recipient->id]);
            $message = self::make_message($campaign, $to, $recipient, false);
        } catch (\Throwable $e) {
            recipient_manager::mark_failed($recipient->id, $e->getMessage());
            \local_mailwhistle\helper::log_send($campaign->id, $recipient->id, 'error', $e->getMessage());
            return;
        }

        // Persist before entering transport; a crash afterwards has an unknown outcome.
        recipient_manager::mark_sending($recipient->id);
        try {
            $accepted = static::deliver_message($message);
        } catch (\Throwable $e) {
            recipient_manager::mark_uncertain($recipient->id, get_string('delivery_uncertain', 'local_mailwhistle'));
            \local_mailwhistle\helper::log_send($campaign->id, $recipient->id, 'error', $e->getMessage());
            return;
        }
        if ($accepted) {
            recipient_manager::mark_sent($recipient->id);
            \local_mailwhistle\helper::log_send($campaign->id, $recipient->id, 'info', 'email accepted');
        } else {
            // A false result can mean the SMTP server's final acknowledgement
            // was lost after accepting DATA. Retrying could send a duplicate.
            $error = get_string('delivery_uncertain', 'local_mailwhistle');
            recipient_manager::mark_uncertain($recipient->id, $error);
            \local_mailwhistle\helper::log_send($campaign->id, $recipient->id, 'error', $error);
        }
    }

    /**
     * Ask Moodle's email processor to deliver a prepared message.
     *
     * A false result does not establish rejection: an SMTP acknowledgement
     * may have been lost after acceptance. Callers must treat it as uncertain.
     *
     * @param \core\message\message $message Prepared campaign message.
     * @return bool Whether acceptance was confirmed by the email processor.
     */
    protected static function deliver_message(\core\message\message $message): bool {
        return (bool) get_message_processor('email')->send_message($message->get_eventobject_for_processor('email'));
    }

    /**
     * Build a personalised message using the site sender and explicit Reply-To.
     *
     * @param \stdClass $campaign Campaign record.
     * @param \stdClass $to Current recipient user.
     * @param \stdClass $values Personalisation data, including effective email.
     * @param bool $test Whether this is an untracked test copy.
     * @return \core\message\message Message for the email processor.
     */
    private static function make_message(\stdClass $campaign, \stdClass $to, \stdClass $values, bool $test): \core\message\message {
        resource_manager::assert_attachments_available($campaign);
        $values = clone $values;
        $values->unsubscribeurl = ($test ? subscription_manager::preview_url()
            : subscription_manager::unsubscribe_url($values))->out(false);
        $message = new \core\message\message();
        $message->component = 'local_mailwhistle';
        $message->name = 'campaign';
        $message->userfrom = self::get_from_user($campaign);
        $message->userto = $to;
        $message->subject = format_string(placeholder_manager::apply((string) $campaign->subject, $values, false));
        if ($test) {
            $message->subject = get_string('testmail_subjectprefix', 'local_mailwhistle') . ' ' . $message->subject;
        }
        $text = placeholder_manager::apply((string) $campaign->bodytext, $values, false);
        $html = placeholder_manager::apply((string) $campaign->bodyhtml, $values, true);
        if ($text === '' && $html !== '') {
            $text = html_to_text($html);
        }
        $message->fullmessage = subscription_manager::add_footer($text, $values->unsubscribeurl, false);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = '';
        if ($html !== '') {
            $html = subscription_manager::add_footer($html, $values->unsubscribeurl, true);
            $message->fullmessagehtml = $test ? $html
                : tracking_manager::prepare_body($html, (int) $campaign->id, (int) $values->id);
        }
        $message->smallmessage = '';
        $message->notification = 1;
        if (trim((string) $campaign->senderemail) !== '') {
            if (!validate_email($campaign->senderemail)) {
                throw new \moodle_exception('invalidemail');
            }
            $message->replyto = $campaign->senderemail;
            $message->replytoname = $campaign->sendername;
        }
        self::apply_attachments($message, $campaign);
        return $message;
    }

    /**
     * Send an untracked test copy, respecting the same email policy as a campaign.
     *
     * @param int $campaignid Campaign id.
     * @param \stdClass $to Reviewer user record.
     * @return bool Whether the email transport accepted the test copy.
     */
    public static function send_test(int $campaignid, \stdClass $to): bool {
        global $DB;

        $lock = \core\lock\lock_config::get_lock_factory('local_mailwhistle')->get_lock('campaign:' . $campaignid, 10);
        if (!$lock) {
            return false;
        }
        try {
            $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
            $to = $DB->get_record('user', ['id' => $to->id], '*', MUST_EXIST);
            if (self::get_skip_reason($to) !== '') {
                return false;
            }
            $values = clone $to;
            $values->email = self::get_destination($to);
            if (!validate_email($values->email)) {
                return false;
            }
            $message = self::make_message($campaign, $to, $values, true);
            return static::deliver_message($message);
        } catch (\Throwable $e) {
            return false;
        } finally {
            $lock->release();
        }
    }

    /**
     * Send one untracked copy to an explicitly entered address with reviewer sample data.
     *
     * The authorised caller holds the campaign lock and reserves rate-limit allowance.
     * A synthetic destination avoids silently substituting a user's notification address.
     * Site-wide email diversion still applies and is reported as the effective destination.
     *
     * @param \stdClass $campaign Locked saved campaign record.
     * @param \stdClass $reviewer Current full reviewer record used for sample placeholders and email policy.
     * @param string $address Explicit destination address.
     * @return array Requested and effective address, outcome, and plain-text explanation.
     */
    public static function send_test_to_address(\stdClass $campaign, \stdClass $reviewer, string $address): array {
        global $CFG;

        $result = ['address' => $address, 'destination' => $address, 'diverted' => false, 'status' => 'failed'];
        $reason = self::get_skip_reason($reviewer);
        if ($reason !== '') {
            return $result + ['message' => $reason];
        }
        if (!validate_email($address)) {
            return $result + ['message' => get_string('testmail_invalidaddresses', 'local_mailwhistle')];
        }
        $destination = email_should_be_diverted($address) ? $CFG->divertallemailsto : $address;
        $result['destination'] = $destination;
        $result['diverted'] = $destination !== $address;
        if (!validate_email($destination) || str_ends_with(strtolower($destination), '.invalid')) {
            return $result + ['message' => get_string('delivery_invaliddomain', 'local_mailwhistle')];
        }

        try {
            $to = clone $reviewer;
            $to->id = \core_user::NOREPLY_USER;
            $to->email = $address;
            $to->emailstop = 0;
            $to->mailformat = 1;
            $to->auth = 'manual';
            $to->mnethostid = $CFG->mnet_localhost_id;
            unset($to->preference);
            $values = clone $reviewer;
            $values->email = self::get_destination($reviewer);
            $message = self::make_message($campaign, $to, $values, true);
        } catch (\Throwable $exception) {
            return $result + ['message' => get_string('testmail_preparationfailed', 'local_mailwhistle')];
        }

        try {
            $accepted = static::deliver_message($message);
        } catch (\Throwable $exception) {
            $accepted = false;
        }
        $result['status'] = $accepted ? 'accepted' : 'uncertain';
        $result['message'] = get_string($accepted ? 'testmail_accepted' : 'testmail_uncertain', 'local_mailwhistle');
        return $result;
    }

    /**
     * Use Moodle's no-reply address with the campaign's requested display name.
     *
     * @param \stdClass $campaign Campaign record.
     * @return \stdClass Sender identity; SMTP policy remains controlled by Moodle.
     */
    private static function get_from_user(\stdClass $campaign): \stdClass {
        $sender = clone \core_user::get_noreply_user();
        if (trim((string) $campaign->sendername) !== '') {
            $sender->firstname = $campaign->sendername;
            $sender->lastname = '';
            $sender->firstnamephonetic = '';
            $sender->lastnamephonetic = '';
            $sender->middlename = '';
            $sender->alternatename = '';
        }
        $sender->maildisplay = 0;
        return $sender;
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
        resource_manager::assert_attachments_available($campaign);

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

        throw new \moodle_exception('delivery_zipfailed', 'local_mailwhistle');
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
