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
 * Open-tracking pixel endpoint for local_mailwhistle.
 *
 * Opened by an email client with no Moodle session, so cookies and login are
 * disabled; authorisation is the HMAC token only. Always returns a 1x1 gif so
 * the response never reveals whether the token matched; a valid token records a
 * (first) open.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');

$token = optional_param('t', '', PARAM_RAW);

$verified = \local_mailwhistle\manager\tracking_manager::verify_open_token($token);
if ($verified !== null) {
    \local_mailwhistle\manager\tracking_manager::record_open(
        $verified->campaignid,
        $verified->recipientid
    );
}

// Always emit a transparent 1x1 GIF, regardless of token validity.
$gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

header('Content-Type: image/gif');
header('Content-Length: ' . strlen($gif));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
echo $gif;
