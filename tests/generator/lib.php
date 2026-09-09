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

/**
 * Test data generator for local_mailwhistle.
 *
 * @package   local_mailwhistle
 * @category  test
 * @copyright 2024 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_mailwhistle_generator extends \component_generator_base {
    /** @var int */
    protected int $campaignnum = 1;

    /**
     * Reset campaign number.
     *
     * @return void
     */
    public function reset() {
        parent::reset();
        $this->campaignnum = 1;
    }

    /**
     * Create a campaign record and return it.
     *
     * Accepts optional fields: name, subject, bodyhtml, bodytext, sendername,
     * senderemail, status. Missing required fields fall back to sensible
     * defaults so a "complete" campaign can be created in one call.
     *
     * @param array|\stdClass $record Campaign fields.
     * @return \stdClass The created campaign record.
     */
    public function create_campaign($record = []): \stdClass {
        global $DB, $USER;

        $record = (array) $record;
        $now = time();
        $defaults = [
            'name' => "Test campaign {$this->campaignnum}",
            'subject' => "Subject {$this->campaignnum}",
            'bodyhtml' => '',
            'bodytext' => '',
            'sendername' => '',
            'senderemail' => '',
            'status' => \local_mailwhistle\manager\campaign_manager::STATUS_DRAFT,
            'sendengine' => 'moodle_smtp',
            'createdby' => (int) $USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'timescheduled' => 0,
            'timesent' => 0,
        ];
        $record = array_merge($defaults, $record);
        $this->campaignnum++;

        $record['id'] = $DB->insert_record('local_mailwhistle_campaigns', (object) $record);

        return (object) $record;
    }

    /**
     * Create a tag and return its record.
     *
     * @param array|\stdClass $record Tag fields (name required).
     * @return \stdClass The created tag record.
     */
    public function create_tag($record = []): \stdClass {
        global $DB;

        $record = (array) $record;
        $name = $record['name'] ?? 'Test tag';
        $id = \local_mailwhistle\manager\tag_manager::create_tag($name);

        return $DB->get_record('local_mailwhistle_tag', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Assign an existing tag to a user.
     *
     * Accepts either tagid, or tag (display name). userid is required.
     *
     * @param array|\stdClass $record Assignment fields.
     * @return \stdClass Object with tagid and userid.
     */
    public function create_tag_assignment($record = []): \stdClass {
        global $DB;

        $record = (array) $record;
        $tagid = (int) ($record['tagid'] ?? 0);
        if ($tagid <= 0 && !empty($record['tag'])) {
            $tagid = (int) $DB->get_field('local_mailwhistle_tag', 'id', ['name' => $record['tag']], MUST_EXIST);
        }
        $userid = (int) ($record['userid'] ?? 0);
        if ($userid <= 0) {
            throw new \coding_exception('create_tag_assignment requires userid');
        }

        \local_mailwhistle\manager\tag_manager::assign_tag($tagid, $userid);

        return (object) ['tagid' => $tagid, 'userid' => $userid];
    }

    /**
     * Create a reusable email template.
     *
     * @param array|\stdClass $record Template fields.
     * @return \stdClass The created template record.
     */
    public function create_template($record = []): \stdClass {
        global $DB;

        $record = (array) $record;
        $now = time();
        $defaults = [
            'name' => 'Test template',
            'previewtext' => '',
            'background' => '#ffffff',
            'editormode' => 'html',
            'builderjson' => '',
            'bodyhtml' => '<p>Template body</p>',
            'archived' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record = array_merge($defaults, $record);
        $record['id'] = $DB->insert_record('local_mailwhistle_templates', (object) $record);

        return (object) $record;
    }
}
