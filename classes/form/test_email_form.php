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

use local_mailwhistle\manager\send_manager;
use local_mailwhistle\manager\test_email_manager;

/**
 * Native Moodle modal form for untracked campaign test copies.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_email_form extends \core_form\dynamic_form {
    /**
     * Define address entry and explain how the test is personalised.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'campaignid');
        $mform->setType('campaignid', PARAM_INT);
        $mform->addElement('static', 'description', '', get_string('testmail_description', 'local_mailwhistle'));
        $mform->addElement('textarea', 'addresses', get_string('testmail_addresses', 'local_mailwhistle'), ['rows' => 4]);
        $mform->setType('addresses', PARAM_RAW_TRIMMED);
        $mform->addRule('addresses', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('addresses', 'testmail_addresses', 'local_mailwhistle');
    }

    /**
     * Validate the complete list and available allowance before submission.
     *
     * @param array $data Submitted values.
     * @param array $files Submitted files.
     * @return array Field validation errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        try {
            $addresses = test_email_manager::parse_addresses((string) ($data['addresses'] ?? ''));
            $limiterror = test_email_manager::get_limit_error(count($addresses));
            if ($limiterror !== '') {
                $errors['addresses'] = $limiterror;
            }
        } catch (\moodle_exception $exception) {
            $errors['addresses'] = $exception->getMessage();
        }
        return $errors;
    }

    /**
     * Return the system context used by campaign management.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Enforce management capability and require an existing campaign.
     */
    protected function check_access_for_dynamic_submission(): void {
        global $DB;

        require_capability('local/mailwhistle:manage', $this->get_context_for_dynamic_submission());
        $campaignid = $this->optional_param('campaignid', 0, PARAM_INT);
        $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], 'id', MUST_EXIST);
    }

    /**
     * Send validated test copies and return individual outcomes to the modal caller.
     *
     * @return array Result data, with plain-text messages escaped by the template.
     */
    public function process_dynamic_submission(): array {
        $submittedkey = $this->optional_param('sesskey', '', PARAM_RAW);
        if ($submittedkey === '' || !confirm_sesskey($submittedkey)) {
            throw new \moodle_exception('invalidsesskey');
        }
        $this->check_access_for_dynamic_submission();
        $data = $this->get_data();
        if (!$data) {
            throw new \moodle_exception('invalidformdata');
        }
        return ['results' => test_email_manager::send((int) $data->campaignid, $data->addresses)];
    }

    /**
     * Default the list to the current user's effective email destination.
     */
    public function set_data_for_dynamic_submission(): void {
        global $USER;

        $this->set_data((object) [
            'campaignid' => $this->optional_param('campaignid', 0, PARAM_INT),
            'addresses' => send_manager::get_destination($USER),
        ]);
    }

    /**
     * Use the Review page as the form's originating URL.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/mailwhistle/campaign_edit.php', [
            'campaignid' => $this->optional_param('campaignid', 0, PARAM_INT),
            'step' => 'review',
        ]);
    }
}
