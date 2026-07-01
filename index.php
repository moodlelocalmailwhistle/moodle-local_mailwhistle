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
 * Local plugin "Mail Whistle" - Main page.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir . '/adminlib.php');

$tab = optional_param('tab', 'send', PARAM_ALPHA);
admin_externalpage_setup('local_mailwhistle_mailings', extraurlparams: ['tab' => $tab]);

// System context used by capability checks below (audience tag management).
$context = context_system::instance();

// Determine which tab is active (defaults to the send newsletters tab).
$validtabs = ['send', 'audience', 'templates', 'reports', 'resources'];
if (!in_array($tab, $validtabs, true)) {
    $tab = 'send';
}

// PART A: POST-only write handler for the audience tab.
//
// This block MUST run before echo $OUTPUT->header() so that redirect()
// can send HTTP headers (PRG pattern — no resubmit on browser refresh).
//
// Only state-changing actions are handled here. The 'removetag' action
// is a GET that shows a confirmation page (rendered in PART B); the
// actual delete fires via 'removetagconfirm' (POST) handled below.
$action = optional_param('action', '', PARAM_ALPHA);
$writeactions = ['applytag', 'createtag', 'removetagconfirm'];
if ($tab === 'audience' && in_array($action, $writeactions, true)) {
    // Assert POST method — prefetchers/scanners must not trigger writes.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \moodle_exception('invalidrequest');
    }
    require_sesskey();
    require_capability('local/mailwhistle:managetags', $context);

    $audienceurl = new moodle_url('/local/mailwhistle/index.php', ['tab' => 'audience']);

    switch ($action) {
        case 'applytag':
            $userids    = optional_param_array('userids', [], PARAM_INT);
            $applytagid = optional_param('applytagid', 0, PARAM_INT);
            $newtagname = trim(optional_param('newtagname', '', PARAM_TEXT));

            $tagid = 0;
            if ($newtagname !== '') {
                $tagid = \local_mailwhistle\manager\tag_manager::get_or_create_tag($newtagname);
            } else if ($applytagid > 0) {
                $tagid = $applytagid;
            }

            // Guard: both a valid tag and at least one selected user are required.
            if ($tagid <= 0 || empty($userids)) {
                redirect(
                    $audienceurl,
                    get_string('noselection', 'local_mailwhistle'),
                    null,
                    \core\output\notification::NOTIFY_WARNING
                );
            }

            $n = \local_mailwhistle\manager\tag_manager::assign_tag_to_users($tagid, $userids);
            redirect(
                $audienceurl,
                get_string('tag_assigned_n', 'local_mailwhistle', $n),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'removetagconfirm':
            // Confirmed removal — POST, sesskey + capability already checked above.
            $tagid  = required_param('tagid', PARAM_INT);
            $userid = required_param('userid', PARAM_INT);
            \local_mailwhistle\manager\tag_manager::unassign_tag($tagid, $userid);

            redirect(
                $audienceurl,
                get_string('tag_removed', 'local_mailwhistle'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;

        case 'createtag':
            $newtagname = required_param('newtagname', PARAM_TEXT);
            \local_mailwhistle\manager\tag_manager::get_or_create_tag($newtagname);

            redirect(
                $audienceurl,
                get_string('tag_created', 'local_mailwhistle'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
            break;
    }
}

// Handle processing the 'resources' form.
$resourcesform = null;
if ($tab === 'resources') {
    $resourcesform = new local_mailwhistle\form\resources_form($PAGE->url);
    if ($resourcesform->process()) {
        redirect($PAGE->url);
    }
}

// PART A2: POST-only "Send now" handler for the send tab.
//
// Runs before output so redirect() can send headers (PRG pattern). Flips the
// campaign ready -> sending atomically (only one caller wins), snapshots its
// recipients, and queues the delivery adhoc task.
if ($tab === 'send' && $action === 'sendnow') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \moodle_exception('invalidrequest');
    }
    require_sesskey();
    require_capability('local/mailwhistle:manage', $context);

    $sendcampaignid = required_param('campaignid', PARAM_INT);
    $sendurl = new moodle_url('/local/mailwhistle/index.php', ['tab' => 'send']);

    if (\local_mailwhistle\manager\campaign_manager::begin_sending($sendcampaignid)) {
        \local_mailwhistle\manager\recipient_manager::snapshot_recipients($sendcampaignid);
        $task = new \local_mailwhistle\task\send_campaign();
        $task->set_custom_data(['campaignid' => $sendcampaignid]);
        \core\task\manager::queue_adhoc_task($task);
        redirect(
            $sendurl,
            get_string('sendqueued', 'local_mailwhistle'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    redirect(
        $sendurl,
        get_string('sendnotready', 'local_mailwhistle'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Optional: a specific sent newsletter to view (0 means show the list).
$viewid = optional_param('view', 0, PARAM_INT);
$templateaction = optional_param('action', 'list', PARAM_ALPHA);
$templateid = optional_param('id', 0, PARAM_INT);
$templatefilter = optional_param('filter', 'active', PARAM_ALPHA);

// Configure the page (must be done before output).
$pageparams = ['tab' => $tab];
if ($tab === 'templates' && $templateaction !== 'list') {
    $pageparams['action'] = $templateaction;
    if ($templateid > 0) {
        $pageparams['id'] = $templateid;
    }
} else if ($tab === 'templates' && $templatefilter !== 'active') {
    $pageparams['filter'] = $templatefilter;
}
$pageurl = new moodle_url('/local/mailwhistle/index.php', $pageparams);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_mailwhistle'));
$PAGE->set_heading(get_string('pluginname', 'local_mailwhistle'));

// Add plugin assets.
$PAGE->requires->css(new moodle_url('/local/mailwhistle/styles.css'));

$templatescontent = null;
if ($tab === 'templates') {
    $templatescontent = local_mailwhistle_render_templates_page($templateaction, $templateid);
}

// Build the tab tree. Each tab links back to this page with its own tab param.
$tabs = [];
foreach ($validtabs as $tabid) {
    $tabs[] = new tabobject(
        $tabid,
        new moodle_url('/local/mailwhistle/index.php', ['tab' => $tabid]),
        get_string('tab_' . $tabid, 'local_mailwhistle')
    );
}

// Start output.
echo $OUTPUT->header();
echo $OUTPUT->tabtree($tabs, $tab);

// PART B: Render the active tab's content.
switch ($tab) {
    case 'send':
        if ($viewid > 0) {
            echo local_mailwhistle_render_view_mail($viewid);
        } else {
            if (has_capability('local/mailwhistle:manage', $context)) {
                echo $OUTPUT->single_button(
                    new moodle_url('/local/mailwhistle/campaign_edit.php', ['sesskey' => sesskey()]),
                    get_string('createcampaign', 'local_mailwhistle'),
                    'get',
                    ['class' => 'mb-3']
                );
                echo local_mailwhistle_render_draft_campaigns();
            }
            echo $OUTPUT->render(new \local_mailwhistle\output\sent_mails());
        }
        break;

    case 'audience':
        // Confirm page for per-row tag removal (GET → confirm → POST removetagconfirm).
        // The remove link in col_tags is a GET link (safe to follow by scanners)
        // that lands here.  We render a confirmation page and do NO write.
        // The actual delete fires via 'removetagconfirm' (POST) handled in PART A.
        if ($action === 'removetag') {
            require_sesskey();
            require_capability('local/mailwhistle:managetags', $context);

            $tagid  = required_param('tagid', PARAM_INT);
            $userid = required_param('userid', PARAM_INT);

            $continue = new \single_button(
                new moodle_url('/local/mailwhistle/index.php', [
                    'tab'     => 'audience',
                    'action'  => 'removetagconfirm',
                    'tagid'   => $tagid,
                    'userid'  => $userid,
                    'sesskey' => sesskey(),
                ]),
                get_string('remove_tag', 'local_mailwhistle'),
                'post'
            );
            $cancel = new moodle_url('/local/mailwhistle/index.php', ['tab' => 'audience']);

            echo $OUTPUT->confirm(get_string('confirm_remove_tag', 'local_mailwhistle'), $continue, $cancel);
            echo $OUTPUT->footer();
            exit;
        }

        // Read filter values from GET params.  These thread into the table
        // baseurl so pagination and sort links preserve the active filter.
        $search       = optional_param('search', '', PARAM_TEXT);
        $filtertagid  = optional_param('tagid', 0, PARAM_INT);
        $suspended    = optional_param('suspended', 'any', PARAM_ALPHA);
        $auth         = optional_param('auth', 'any', PARAM_PLUGIN);
        $perpage      = min(max(optional_param('perpage', 25, PARAM_INT), 1), 100);

        $canmanage = has_capability('local/mailwhistle:managetags', $context);

        // Fetch tag definitions for the filter dropdown and apply select.
        $tags = \local_mailwhistle\manager\tag_manager::get_all_tags();

        // Build installed auth plugin list for the auth filter select.
        $authplugins = [];
        foreach (array_keys(core_component::get_plugin_list('auth')) as $authkey) {
            $authplugins[$authkey] = $authkey;
        }

        // Base URL carrying active filter params — used by the table for
        // pagination / sorting links so filter state is preserved.
        $baseurl = new moodle_url('/local/mailwhistle/index.php', [
            'tab'       => 'audience',
            'search'    => $search,
            'tagid'     => $filtertagid,
            'suspended' => $suspended,
            'auth'      => $auth,
            'perpage'   => $perpage,
        ]);

        // FORM 1: GET filter form (sibling, never nested).
        // moodleform(action, customdata, method, target, attributes, editable).
        $filterform = new \local_mailwhistle\form\audience_filter_form(
            $baseurl->out(false),
            ['tags' => $tags, 'auths' => $authplugins],
            'get'
        );
        $filterform->set_data([
            'search'    => $search,
            'tagid'     => $filtertagid,
            'suspended' => $suspended,
            'auth'      => $auth,
        ]);
        $filterform->display();

        // Build the table instance.
        $table = new \local_mailwhistle\table\audience_table(
            'local_mailwhistle_audience',
            $baseurl,
            [
                'search'    => $search,
                'tagid'     => $filtertagid,
                'suspended' => $suspended,
                'auth'      => $auth,
            ],
            $canmanage
        );
        $table->build_sql();

        if ($canmanage) {
            // FORM 2: POST apply-tag form (sibling, never nested inside Form 1).
            // The audience_table output is rendered INSIDE this form so the
            // row checkboxes submit together with the apply-tag controls.
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => (new moodle_url('/local/mailwhistle/index.php', ['tab' => 'audience']))->out(false),
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'applytag']);

            // Apply-tag controls: choose existing tag OR type a new one.
            $tagselectoptions = ['' => get_string('apply_tag_choose', 'local_mailwhistle')];
            foreach ($tags as $tag) {
                $tagselectoptions[(int) $tag->id] = format_string($tag->name);
            }
            echo html_writer::start_div('mw-apply-tag-controls d-flex align-items-center mb-3');
            echo html_writer::label(
                get_string('apply_tag', 'local_mailwhistle'),
                'applytagid',
                true,
                ['class' => 'me-1']
            );
            echo html_writer::select($tagselectoptions, 'applytagid', '', false, ['id' => 'applytagid', 'class' => 'me-1']);
            echo ' ' . get_string('new_tag', 'local_mailwhistle') . ' ';
            echo html_writer::empty_tag('input', [
                'type'        => 'text',
                'name'        => 'newtagname',
                'id'          => 'newtagname',
                'placeholder' => get_string('new_tag', 'local_mailwhistle'),
                'class'       => 'mx-1 form-control',
            ]);
            echo html_writer::empty_tag('input', [
                'type'  => 'submit',
                'value' => get_string('applybtn', 'local_mailwhistle'),
                'class' => 'btn btn-primary',
            ]);
            echo html_writer::end_div();

            // Table output inside the POST form — row checkboxes post here.
            $table->out($perpage, true);

            echo html_writer::end_tag('form');
        } else {
            // View-only: table with no checkboxes, no POST form.
            $table->out($perpage, true);
        }
        break;

    case 'templates':
        echo $templatescontent;
        break;

    case 'reports':
        echo $OUTPUT->notification(
            get_string('reports_placeholder', 'local_mailwhistle'),
            \core\output\notification::NOTIFY_INFO
        );
        break;
    case 'resources':
        echo $OUTPUT->render(new \local_mailwhistle\output\resources($resourcesform));
        break;
}

// Output the page footer.
echo $OUTPUT->footer();
