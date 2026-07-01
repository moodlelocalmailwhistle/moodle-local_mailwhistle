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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * GET filter form for the Mail Whistle audience listing.
 *
 * Submits via GET so filter state is carried in the URL and pagination /
 * sorting links can thread the same params through the table baseurl.
 * No sesskey is added here — sesskey() only applies to state-changing POSTs.
 *
 * Expected _customdata keys:
 *   - tags   array  id => display name map for the tag filter select.
 *   - auths  array  auth plugin => label map for the auth filter select.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audience_filter_form extends \moodleform {
    /**
     * Define the filter form elements.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        // Name / email search.
        $mform->addElement(
            'text',
            'search',
            get_string('filter_search', 'local_mailwhistle'),
            ['size' => 30]
        );
        $mform->setType('search', PARAM_TEXT);

        // Tag filter.
        $tagoptions = [0 => get_string('filter_any', 'local_mailwhistle')];
        $tags = $this->_customdata['tags'] ?? [];
        foreach ($tags as $tag) {
            $tagoptions[(int) $tag->id] = format_string($tag->name);
        }

        $mform->addElement(
            'select',
            'tagid',
            get_string('filter_tag', 'local_mailwhistle'),
            $tagoptions
        );
        $mform->setType('tagid', PARAM_INT);
        $mform->setDefault('tagid', 0);

        // Suspended status filter.
        $suspendoptions = [
            'any'       => get_string('filter_any', 'local_mailwhistle'),
            'active'    => get_string('filter_active', 'local_mailwhistle'),
            'suspended' => get_string('filter_suspended_only', 'local_mailwhistle'),
        ];

        $mform->addElement(
            'select',
            'suspended',
            get_string('filter_suspended', 'local_mailwhistle'),
            $suspendoptions
        );
        $mform->setType('suspended', PARAM_ALPHA);
        $mform->setDefault('suspended', 'any');

        // Auth plugin filter.
        $authoptions = ['any' => get_string('filter_any', 'local_mailwhistle')];
        $auths = $this->_customdata['auths'] ?? [];
        foreach ($auths as $authkey => $authlabel) {
            $authoptions[$authkey] = $authlabel;
        }

        $mform->addElement(
            'select',
            'auth',
            get_string('filter_auth', 'local_mailwhistle'),
            $authoptions
        );
        $mform->setType('auth', PARAM_PLUGIN);
        $mform->setDefault('auth', 'any');

        // Rows per page.
        $mform->addElement('hidden', 'tab', 'audience');
        $mform->setType('tab', PARAM_ALPHA);

        // Submit button.
        $mform->addElement('submit', 'submitbutton', get_string('applybtn', 'local_mailwhistle'), ['class' => 'ms-0']);
    }
}
