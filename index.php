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

// Require user login.
require_login();

// Get system context and check user capability.
$context = context_system::instance();
require_capability('local/mailwhistle:view', $context);

// Determine which tab is active (defaults to the send newsletters tab).
$tab = optional_param('tab', 'send', PARAM_ALPHA);
$validtabs = ['send', 'audience', 'templates', 'reports', 'resources'];
if (!in_array($tab, $validtabs, true)) {
    $tab = 'send';
}

// Optional: a specific sent newsletter to view (0 means show the list).
$viewid = optional_param('view', 0, PARAM_INT);

// Configure the page (must be done before output).
$pageurl = new moodle_url('/local/mailwhistle/index.php', ['tab' => $tab]);
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_mailwhistle'));
$PAGE->set_heading(get_string('pluginname', 'local_mailwhistle'));

// Add plugin assets.
$PAGE->requires->css(new moodle_url('/local/mailwhistle/styles.css'));

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

// Render the active tab's content.
switch ($tab) {
    case 'send':
        if ($viewid > 0) {
            echo local_mailwhistle_render_view_mail($viewid);
        } else {
            echo local_mailwhistle_render_sent_mails();
        }
        break;
    case 'audience':
        echo $OUTPUT->notification(
            get_string('audience_placeholder', 'local_mailwhistle'),
            \core\output\notification::NOTIFY_INFO
        );
        break;
    case 'templates':
        echo $OUTPUT->notification(
            get_string('templates_placeholder', 'local_mailwhistle'),
            \core\output\notification::NOTIFY_INFO
        );
        break;
    case 'reports':
        echo $OUTPUT->notification(
            get_string('reports_placeholder', 'local_mailwhistle'),
            \core\output\notification::NOTIFY_INFO
        );
        break;
    case 'resources':
        echo local_mailwhistle_render_resources();
        break;
}

// Output the page footer.
echo $OUTPUT->footer();
