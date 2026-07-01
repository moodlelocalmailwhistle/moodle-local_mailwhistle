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
 * Tags are picked with a Moodle autocomplete element (multi-select), whose
 * options come from {@see tag_manager::get_all_tags()}. The selected tags
 * become the campaign's tag-based audience rules.
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

        // Multi-select autocomplete of all known tags (id => name).
        $options = [];
        foreach (tag_manager::get_all_tags() as $tag) {
            $options[(int) $tag->id] = format_string($tag->name);
        }
        $mform->addElement(
            'autocomplete',
            'tagids',
            get_string('audiencetags_label', 'local_mailwhistle'),
            $options,
            [
                'multiple' => true,
                'noselectionstring' => get_string('audiencetags_none', 'local_mailwhistle'),
            ]
        );
        $mform->setType('tagids', PARAM_INT);

        $this->add_action_buttons(true, get_string('audiencetags_submit', 'local_mailwhistle'));
    }

    /**
     * Pre-select the tags currently linked to the campaign.
     *
     * @param int[] $selectedtagids Tag ids to mark as selected.
     * @return void
     */
    public function set_selected_tags(array $selectedtagids): void {
        $this->_form->setDefault('tagids', array_map('intval', $selectedtagids));
    }

    /**
     * Extract the selected tag ids from submitted data.
     *
     * The autocomplete element posts a plain array of the selected ids.
     *
     * @param \stdClass $data Submitted form data.
     * @return int[] Selected tag ids.
     */
    public static function get_checked_tagids(\stdClass $data): array {
        if (empty($data->tagids) || !is_array($data->tagids)) {
            return [];
        }
        return array_values(array_map('intval', $data->tagids));
    }
}
