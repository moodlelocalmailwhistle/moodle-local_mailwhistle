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
 * Local plugin "Mail Whistle" - Campaign audience-tag selection page.
 *
 * Shown after a campaign is created so the user can choose which audience
 * tag(s) the campaign targets. Selections are stored as tag-type audience
 * rules against the campaign.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

// Require user login.
require_login();

// Get system context and check user capability.
$context = context_system::instance();
require_capability('local/mailwhistle:manage', $context);

// The campaign whose audience is being edited (must exist).
$campaignid = required_param('campaignid', PARAM_INT);
$campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], '*', MUST_EXIST);

$returnurl = new moodle_url('/local/mailwhistle/index.php', ['tab' => 'send']);
$pageurl = new moodle_url('/local/mailwhistle/campaign_audience.php', ['campaignid' => $campaignid]);

// Configure the page (must be done before output).
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('audiencetags_heading', 'local_mailwhistle'));
$PAGE->set_heading(get_string('pluginname', 'local_mailwhistle'));

// Add plugin assets.
$PAGE->requires->css(new moodle_url('/local/mailwhistle/styles.css'));

// If no tags exist yet, there is nothing to select: guide the user to create some.
$tags = \local_mailwhistle\manager\tag_manager::get_all_tags();
if (empty($tags)) {
    $audienceurl = new moodle_url('/local/mailwhistle/index.php', ['tab' => 'audience']);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('audiencetags_heading', 'local_mailwhistle'));
    echo $OUTPUT->notification(get_string('audiencetags_notags', 'local_mailwhistle'), \core\output\notification::NOTIFY_INFO);
    echo html_writer::div(
        html_writer::link($audienceurl, get_string('audiencetags_managetags', 'local_mailwhistle')),
        'local-mailwhistle-managetags mb-3'
    );
    echo html_writer::div(html_writer::link($returnurl, get_string('backtolist', 'local_mailwhistle')));
    echo $OUTPUT->footer();
    die;
}

$mform = new \local_mailwhistle\form\campaign_audience_form($pageurl->out(false));

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    $tagids = \local_mailwhistle\form\campaign_audience_form::get_checked_tagids($data);
    \local_mailwhistle\manager\audience_manager::set_campaign_tags($campaignid, $tagids);
    redirect($returnurl, get_string('audiencetags_saved', 'local_mailwhistle'), null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    // Pre-fill with the campaign's current tag selection.
    $mform->set_data(['campaignid' => $campaignid]);
    $mform->set_selected_tags(\local_mailwhistle\manager\audience_manager::get_campaign_tagids($campaignid));

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('audiencetags_heading', 'local_mailwhistle'));
    echo html_writer::tag('p', get_string('audiencetags_for', 'local_mailwhistle', format_string($campaign->name)));
    echo html_writer::start_div('local-mailwhistle-audiencetags');
    $mform->display();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
}
