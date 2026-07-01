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
 * Audience-rule manager for Mail Whistle campaigns.
 *
 * Centralises access to the local_mailwhistle_audrules table. Currently only
 * tag-type rules are supported: each selected tag becomes one audrules row with
 * type 'tag' and instanceid set to the tag id. All writes use portable Moodle
 * DML (bound params, {table} placeholders) and run inside a transaction so a
 * campaign's tag set is replaced atomically.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audience_manager {
    /** @var string Audience-rule type for a tag-based rule. */
    public const TYPE_TAG = 'tag';

    /**
     * Replace the tag-based audience rules for a campaign.
     *
     * Deletes all existing tag rules for the campaign and inserts one row per
     * valid tag id. Tag ids that do not exist are ignored so posted data is
     * never trusted blindly. Passing an empty array clears the campaign's tag
     * audience. The whole operation runs in a transaction.
     *
     * @param int $campaignid The campaign to set the audience for.
     * @param int[] $tagids Tag ids to link to the campaign.
     * @return void
     */
    public static function set_campaign_tags(int $campaignid, array $tagids): void {
        global $DB;

        // Keep only distinct, positive ids that refer to a real tag.
        $tagids = array_values(array_unique(array_filter(array_map('intval', $tagids))));
        if (!empty($tagids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($tagids);
            $validids = $DB->get_fieldset_select('local_mailwhistle_tag', 'id', "id $insql", $inparams);
            $tagids = array_map('intval', $validids);
        }

        $transaction = $DB->start_delegated_transaction();

        // Remove the existing tag rules for this campaign, then re-add the set.
        $DB->delete_records('local_mailwhistle_audrules', [
            'campaignid' => $campaignid,
            'type' => self::TYPE_TAG,
        ]);

        $now = time();
        foreach ($tagids as $tagid) {
            $DB->insert_record('local_mailwhistle_audrules', (object) [
                'campaignid' => $campaignid,
                'type' => self::TYPE_TAG,
                'instanceid' => $tagid,
                'roleid' => 0,
                'timecreated' => $now,
            ]);
        }

        $transaction->allow_commit();
    }

    /**
     * Get the tag ids currently linked to a campaign as an audience.
     *
     * @param int $campaignid The campaign to read.
     * @return int[] Tag ids, ordered by insertion.
     */
    public static function get_campaign_tagids(int $campaignid): array {
        global $DB;

        $records = $DB->get_records('local_mailwhistle_audrules', [
            'campaignid' => $campaignid,
            'type' => self::TYPE_TAG,
        ], 'id ASC', 'id, instanceid');

        return array_map(static fn($record) => (int) $record->instanceid, array_values($records));
    }
}
