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
 * Local plugin "Mail Whistle" - Draft campaign edit wizard.
 *
 * Steps a user through the campaign details, content and audience, then lets
 * them mark the draft ready once every required part is present. A draft can
 * be re-opened and edited until it is completed.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_mailwhistle\form\campaign_details_form;
use local_mailwhistle\form\campaign_content_form;
use local_mailwhistle\form\campaign_audience_form;
use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\manager\audience_manager;

// Require user login.
require_login();

// Get system context and check user capability.
$context = context_system::instance();
require_capability('local/mailwhistle:manage', $context);

// The campaign being edited. With no id we are creating a new campaign: make an
// empty draft and re-enter the wizard on it, so create and edit share one path.
$campaignid = optional_param('campaignid', 0, PARAM_INT);
if (empty($campaignid)) {
    require_sesskey();
    $campaignid = \local_mailwhistle\helper::create_campaign('');
    redirect(new moodle_url('/local/mailwhistle/campaign_edit.php', [
        'campaignid' => $campaignid,
        'step' => 'details',
    ]));
}
$campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], '*', MUST_EXIST);

// Ordered wizard steps.
$steps = ['details', 'content', 'audience', 'review'];
$step = optional_param('step', 'details', PARAM_ALPHA);
if (!in_array($step, $steps, true)) {
    $step = 'details';
}

$returnurl = new moodle_url('/local/mailwhistle/index.php', ['tab' => 'send']);
$baseurl = new moodle_url('/local/mailwhistle/campaign_edit.php', ['campaignid' => $campaignid]);
$stepurl = new moodle_url($baseurl, ['step' => $step]);

// Configure the page (must be done before output).
$PAGE->set_context($context);
$PAGE->set_url($stepurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('editcampaign', 'local_mailwhistle'));
$PAGE->set_heading(get_string('pluginname', 'local_mailwhistle'));
$PAGE->requires->css(new moodle_url('/local/mailwhistle/styles.css'));

/**
 * Build the URL for a given wizard step.
 *
 * @param moodle_url $baseurl The campaign base URL.
 * @param string $step The step key.
 * @return moodle_url
 */
function local_mailwhistle_step_url(moodle_url $baseurl, string $step): moodle_url {
    return new moodle_url($baseurl, ['step' => $step]);
}

// A campaign that has left draft cannot be edited here.
if ($campaign->status !== campaign_manager::STATUS_DRAFT) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('editcampaign', 'local_mailwhistle'));
    echo $OUTPUT->notification(get_string('editcampaign_notdraft', 'local_mailwhistle'), \core\output\notification::NOTIFY_INFO);
    echo html_writer::div(html_writer::link($returnurl, get_string('backtolist', 'local_mailwhistle')));
    echo $OUTPUT->footer();
    die;
}

// Step index used for the progress tabs.
$next = static function (string $current) use ($steps): string {
    $pos = array_search($current, $steps, true);
    return $steps[min($pos + 1, count($steps) - 1)];
};

// Render helper: the step progress tabtree.
$rendertabs = static function (string $active) use ($steps, $baseurl): string {
    $tabs = [];
    foreach ($steps as $s) {
        $tabs[] = new tabobject(
            $s,
            local_mailwhistle_step_url($baseurl, $s),
            get_string('wizardstep_' . $s, 'local_mailwhistle')
        );
    }
    global $OUTPUT;
    return $OUTPUT->tabtree($tabs, $active);
};

// Step: details (name + sender).
if ($step === 'details') {
    $mform = new campaign_details_form(local_mailwhistle_step_url($baseurl, 'details')->out(false));

    if ($mform->is_cancelled()) {
        redirect($returnurl);
    } else if ($data = $mform->get_data()) {
        campaign_manager::update_fields($campaignid, [
            'name' => $data->name,
            'sendername' => $data->sendername,
            'senderemail' => $data->senderemail,
        ]);
        redirect(local_mailwhistle_step_url($baseurl, $next('details')));
    } else {
        $mform->set_data([
            'campaignid' => $campaignid,
            'name' => $campaign->name,
            'sendername' => $campaign->sendername,
            'senderemail' => $campaign->senderemail,
        ]);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('editcampaign', 'local_mailwhistle'));
    echo $rendertabs('details');
    $mform->display();
    echo $OUTPUT->footer();
    die;
}

// Step: content (subject + body).
if ($step === 'content') {
    require_once($CFG->dirroot . '/local/mailwhistle/lib.php');

    // When a template is chosen in the picker below, its HTML prefills the body.
    $templateid = optional_param('templateid', 0, PARAM_INT);

    $mform = new campaign_content_form(local_mailwhistle_step_url($baseurl, 'content')->out(false));

    if ($mform->is_cancelled()) {
        redirect($returnurl);
    } else if ($data = $mform->get_data()) {
        $bodyhtml = $data->body['text'] ?? '';
        campaign_manager::update_fields($campaignid, [
            'subject' => $data->subject,
            'bodyhtml' => $bodyhtml,
            'bodytext' => html_to_text($bodyhtml),
        ]);
        redirect(local_mailwhistle_step_url($baseurl, $next('content')));
    } else {
        // Default to the campaign's saved content; if a template was picked,
        // copy its HTML into the body (and its name into an empty subject).
        $prefillsubject = (string) $campaign->subject;
        $prefillbody = (string) $campaign->bodyhtml;
        if ($templateid) {
            $template = local_mailwhistle_get_template($templateid);
            if ($template) {
                $prefillbody = (string) $template->bodyhtml;
                if (trim($prefillsubject) === '') {
                    $prefillsubject = (string) $template->name;
                }
            }
        }
        $mform->set_data([
            'campaignid' => $campaignid,
            'subject' => $prefillsubject,
            'body' => ['text' => $prefillbody, 'format' => FORMAT_HTML],
        ]);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('editcampaign', 'local_mailwhistle'));
    echo $rendertabs('content');
    echo local_mailwhistle_render_campaign_template_picker($baseurl, $campaignid, $templateid);
    $mform->display();
    echo $OUTPUT->footer();
    die;
}

// Step: audience (reuse the audience-tag form).
if ($step === 'audience') {
    $tags = audience_manager::get_campaign_tagids($campaignid);
    $hastags = !empty(\local_mailwhistle\manager\tag_manager::get_all_tags());

    if (!$hastags) {
        $audienceurl = new moodle_url('/local/mailwhistle/index.php', ['tab' => 'audience']);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('editcampaign', 'local_mailwhistle'));
        echo $rendertabs('audience');
        echo $OUTPUT->notification(get_string('audiencetags_notags', 'local_mailwhistle'), \core\output\notification::NOTIFY_INFO);
        echo html_writer::div(
            html_writer::link(
                $audienceurl,
                get_string('audiencetags_managetags', 'local_mailwhistle'),
                ['class' => 'btn btn-secondary mb-3']
            )
        );
        $reviewurl = local_mailwhistle_step_url($baseurl, 'review');
        echo html_writer::div(
            html_writer::link(
                $reviewurl,
                get_string('wizard_savecontinue', 'local_mailwhistle'),
                ['class' => 'btn btn-primary']
            )
        );
        echo $OUTPUT->footer();
        die;
    }

    $mform = new campaign_audience_form(local_mailwhistle_step_url($baseurl, 'audience')->out(false));

    if ($mform->is_cancelled()) {
        redirect($returnurl);
    } else if ($data = $mform->get_data()) {
        audience_manager::set_campaign_tags($campaignid, campaign_audience_form::get_checked_tagids($data));
        redirect(local_mailwhistle_step_url($baseurl, $next('audience')));
    } else {
        $mform->set_data(['campaignid' => $campaignid]);
        $mform->set_selected_tags($tags);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('editcampaign', 'local_mailwhistle'));
    echo $rendertabs('audience');
    $mform->display();
    echo $OUTPUT->footer();
    die;
}

// Step: review (summary + mark complete).
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'complete' && confirm_sesskey()) {
    campaign_manager::mark_complete($campaignid);
    $donemsg = get_string('editcampaign_completed', 'local_mailwhistle');
    redirect($returnurl, $donemsg, null, \core\output\notification::NOTIFY_SUCCESS);
}

// Send a test copy of the current draft to the logged-in user.
if ($action === 'sendtest' && confirm_sesskey()) {
    $sent = \local_mailwhistle\manager\send_manager::send_test($campaignid, $USER);
    if ($sent) {
        $testmsg = get_string('testmail_sent', 'local_mailwhistle', s($USER->email));
        $testtype = \core\output\notification::NOTIFY_SUCCESS;
    } else {
        $testmsg = get_string('testmail_failed', 'local_mailwhistle');
        $testtype = \core\output\notification::NOTIFY_ERROR;
    }
    redirect(local_mailwhistle_step_url($baseurl, 'review'), $testmsg, null, $testtype);
}

$iscomplete = campaign_manager::is_complete($campaignid);
$tagids = audience_manager::get_campaign_tagids($campaignid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editcampaign', 'local_mailwhistle'));
echo $rendertabs('review');

// Summary table of the current values.
$summary = new html_table();
$summary->attributes['class'] = 'generaltable table w-auto local-mailwhistle-review mb-3';
$summary->data = [
    [get_string('internalname', 'local_mailwhistle'), format_string($campaign->name)],
    [get_string('subject', 'local_mailwhistle'), format_string($campaign->subject)],
    [get_string('sendername', 'local_mailwhistle'), format_string($campaign->sendername)],
    [get_string('senderemail', 'local_mailwhistle'), s($campaign->senderemail)],
    [get_string('audiencetags_label', 'local_mailwhistle'), count($tagids)],
    [get_string('body', 'local_mailwhistle'), format_text((string) $campaign->bodyhtml, FORMAT_HTML, ['noclean' => true])],
];
echo html_writer::table($summary);

echo html_writer::start_tag('div', ['class' => 'd-flex gap-2']);

// Test-mail: send a copy of the current draft to the logged-in user.
$testurl = new moodle_url($baseurl, ['step' => 'review', 'action' => 'sendtest', 'sesskey' => sesskey()]);
echo html_writer::div(
    $OUTPUT->single_button($testurl, get_string('testmail_send', 'local_mailwhistle'), 'get'),
    'local-mailwhistle-testmail mb-3'
);

if ($iscomplete) {
    $completeurl = new moodle_url($baseurl, ['step' => 'review', 'action' => 'complete', 'sesskey' => sesskey()]);
    echo html_writer::div(
        $OUTPUT->single_button($completeurl, get_string('editcampaign_markcomplete', 'local_mailwhistle'), 'get'),
        'local-mailwhistle-complete mb-3'
    );
} else {
    $incompletemsg = get_string('editcampaign_incomplete', 'local_mailwhistle');
    echo $OUTPUT->notification($incompletemsg, \core\output\notification::NOTIFY_WARNING);
}

echo html_writer::div(
    html_writer::link(
        $returnurl,
        get_string('backtolist', 'local_mailwhistle'),
        ['class' => 'btn btn-secondary mb-3']
    )
);

echo html_writer::end_div();

echo $OUTPUT->footer();
