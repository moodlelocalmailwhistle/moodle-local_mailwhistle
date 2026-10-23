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
 * Confirm a signed unsubscribe link without requiring a Moodle login.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../config.php');

$CFG->referrerpolicy = 'no-referrer';
$PAGE->set_cacheable(false);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/mailwhistle/unsubscribe.php'));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('subscription_title', 'local_mailwhistle'));
$PAGE->set_heading(get_string('subscription_title', 'local_mailwhistle'));
$content = \local_mailwhistle\page\subscription::unsubscribe();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('subscription_title', 'local_mailwhistle'));
echo $content;
echo $OUTPUT->footer();
