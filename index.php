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

// Configure the page (must be done before output).
$pageurl = new moodle_url('/local/mailwhistle/index.php');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_mailwhistle'));
$PAGE->set_heading(get_string('pluginname', 'local_mailwhistle'));

// Add plugin assets.
$PAGE->requires->css(new moodle_url('/local/mailwhistle/styles.css'));
$PAGE->requires->js_call_amd('local_mailwhistle/example', 'init', ['.local-mailwhistle-example']);

// Start output.
echo $OUTPUT->header();

// Prepare template data.
$templatedata = [
    'title' => get_string('pluginname', 'local_mailwhistle'),
    'description' => get_string('plugindesc', 'local_mailwhistle'),
    'items' => [
        [
            'name' => 'Feature 1',
            'value' => 'Example feature description',
        ],
        [
            'name' => 'Feature 2',
            'value' => 'Another example feature',
        ],
    ],
    'buttonlabel' => get_string('success_message', 'local_mailwhistle'),
];

// Render the template with data.
echo $OUTPUT->render_from_template('local_mailwhistle/example', $templatedata);

// Output the page footer.
echo $OUTPUT->footer();
