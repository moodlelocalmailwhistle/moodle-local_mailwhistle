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
 * Click-tracking redirect endpoint for local_mailwhistle.
 *
 * Opened by an email client with no Moodle session, so cookies and login are
 * disabled; authorisation is the HMAC token only. The token binds the target
 * URL, so a valid token cannot be reused to redirect to a different host (no
 * open redirect). A verified click is recorded, then the browser is redirected
 * to the target.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');

$token = optional_param('t', '', PARAM_RAW);
$target = optional_param('url', '', PARAM_RAW);

// Only ever redirect to an http(s) URL.
$scheme = strtolower((string) parse_url($target, PHP_URL_SCHEME));
if (!in_array($scheme, ['http', 'https'], true)) {
    throw new \moodle_exception('invalidrequest');
}

// The token must have been issued for exactly this target (prevents open redirect).
$verified = \local_mailwhistle\manager\tracking_manager::verify_click_token($token, $target);
if ($verified === null) {
    throw new \moodle_exception('invalidrequest');
}

\local_mailwhistle\manager\tracking_manager::record_click(
    $verified->campaignid,
    $verified->recipientid,
    $target
);

redirect(new moodle_url($target));
