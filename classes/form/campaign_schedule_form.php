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

/**
 * Native date/time selector for a one-off campaign schedule.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_schedule_form extends \moodleform {
    /**
     * Define the scheduling fields in the current user's effective timezone.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'campaignid');
        $mform->setType('campaignid', PARAM_INT);
        $mform->addElement(
            'static',
            'timezone',
            get_string('scheduletimezone', 'local_mailwhistle'),
            s(\core_date::get_user_timezone_object()->getName())
        );
        $mform->addElement('date_time_selector', 'timescheduled', get_string('scheduledfor', 'local_mailwhistle'), [
            'timezone' => \core_date::get_user_timezone(),
            'step' => 1,
            'optional' => false,
        ]);
        $this->add_action_buttons(true, get_string('schedulecampaign', 'local_mailwhistle'));
    }

    /**
     * Reject timestamps which are no longer in the future.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((int) ($data['timescheduled'] ?? 0) <= time()) {
            $errors['timescheduled'] = get_string('schedulefuture', 'local_mailwhistle');
        }
        return $errors;
    }
}
