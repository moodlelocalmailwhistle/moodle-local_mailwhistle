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

namespace local_mailwhistle\form;

use local_mailwhistle\manager\tag_manager;

/**
 * Form to choose the audience tags a campaign targets.
 *
 * Shown after a campaign is created. Each known tag is offered as a checkbox;
 * the selected tags become the campaign's tag-based audience rules. The tag
 * options are supplied by {@see tag_manager::get_all_tags()}.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_audience_form extends \moodleform {
    /**
     * Define the form fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'campaignid');
        $mform->setType('campaignid', PARAM_INT);

        $mform->addElement('static', 'audiencedesc', '', get_string('audiencetags_desc', 'local_mailwhistle'));

        // One checkbox per tag; grouped so the label appears once above them.
        $checkboxes = [];
        foreach (tag_manager::get_all_tags() as $tag) {
            $checkboxes[] = $mform->createElement(
                'advcheckbox',
                (int) $tag->id,
                '',
                format_string($tag->name),
                ['group' => 1],
                [0, 1]
            );
        }
        $mform->addGroup(
            $checkboxes,
            'tagids',
            get_string('audiencetags_label', 'local_mailwhistle'),
            '<br>',
            false
        );

        $this->add_action_buttons(true, get_string('audiencetags_submit', 'local_mailwhistle'));
    }

    /**
     * Pre-check the tags currently linked to the campaign.
     *
     * @param int[] $selectedtagids Tag ids to mark as checked.
     * @return void
     */
    public function set_selected_tags(array $selectedtagids): void {
        $mform = $this->_form;
        $defaults = [];
        foreach ($selectedtagids as $tagid) {
            $defaults['tagids[' . (int) $tagid . ']'] = 1;
        }
        $mform->setDefaults($defaults);
    }

    /**
     * Extract the checked tag ids from submitted data.
     *
     * The tagids group posts an id => 0|1 map; return only the ids set to 1.
     *
     * @param \stdClass $data Submitted form data.
     * @return int[] Checked tag ids.
     */
    public static function get_checked_tagids(\stdClass $data): array {
        $tagids = [];
        if (!empty($data->tagids) && is_array($data->tagids)) {
            foreach ($data->tagids as $tagid => $checked) {
                if ((int) $checked === 1) {
                    $tagids[] = (int) $tagid;
                }
            }
        }
        return $tagids;
    }
}
