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
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_manager {
    /** @var string Status of a campaign that is still being edited. */
    public const STATUS_DRAFT = 'draft';

    /** @var string Status of a campaign that has all required parts and is ready. */
    public const STATUS_READY = 'ready';

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
    ];

    /**
     * Update whitelisted campaign fields and bump the modified time.
     *
     * Any key in $fields that is not an editable column is ignored, so posted
     * data can never set arbitrary columns (status, createdby, timesent, ...).
     *
     * @param int $campaignid The campaign to update.
     * @param array<string, mixed> $fields Field => value map.
     * @return void
     */
    public static function update_fields(int $campaignid, array $fields): void {
        global $DB;

        $record = (object) ['id' => $campaignid];
        foreach (self::EDITABLE_FIELDS as $field) {
            if (array_key_exists($field, $fields)) {
                $record->$field = $fields[$field];
            }
        }

        // Nothing to do if only the id is present.
        if (count((array) $record) <= 1) {
            return;
        }

        $record->timemodified = time();
        $DB->update_record('local_mailwhistle_campaigns', $record);
    }

    /**
     * Determine whether a draft campaign has all required parts.
     *
     * Requires a name, a subject, a body (HTML or text), and at least one
     * audience tag.
     *
     * @param int $campaignid The campaign to check.
     * @return bool True when the campaign is complete enough to be marked ready.
     */
    public static function is_complete(int $campaignid): bool {
        global $DB;

        $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid]);
        if (!$campaign) {
            return false;
        }

        $hasname = trim((string) $campaign->name) !== '';
        $hassubject = trim((string) $campaign->subject) !== '';
        $hasbody = trim((string) $campaign->bodyhtml) !== '' || trim((string) $campaign->bodytext) !== '';
        $hasaudience = !empty(audience_manager::get_campaign_tagids($campaignid));

        return $hasname && $hassubject && $hasbody && $hasaudience;
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

        if (!self::is_complete($campaignid)) {
            throw new \moodle_exception('campaignincomplete', 'local_mailwhistle');
        }

        $DB->update_record('local_mailwhistle_campaigns', (object) [
            'id' => $campaignid,
            'status' => self::STATUS_READY,
            'timemodified' => time(),
        ]);
    }
}
