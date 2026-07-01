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
 * Local plugin "Mail Whistle" - Create campaign page.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Require user login.
require_login();

// Get system context and check user capability.
$context = context_system::instance();
require_capability('local/mailwhistle:manage', $context);

$returnurl = new moodle_url('/local/mailwhistle/index.php', ['tab' => 'send']);

// Configure the page (must be done before output).
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/mailwhistle/create.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('createcampaign', 'local_mailwhistle'));
$PAGE->set_heading(get_string('pluginname', 'local_mailwhistle'));

// Add plugin assets.
$PAGE->requires->css(new moodle_url('/local/mailwhistle/styles.css'));

$mform = new \local_mailwhistle\form\create_campaign_form();

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    \local_mailwhistle\helper::create_campaign($data->name);
    redirect($returnurl, get_string('campaigncreated', 'local_mailwhistle'), null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('createcampaign', 'local_mailwhistle'));
    echo html_writer::start_div('local-mailwhistle-create');
    $mform->display();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
}
