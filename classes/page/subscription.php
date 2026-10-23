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

namespace local_mailwhistle\page;

use local_mailwhistle\helper;
use local_mailwhistle\manager\subscription_manager;

/**
 * Anonymous unsubscribe confirmation and authenticated personal preferences.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscription {
    /**
     * Confirm an emailed link. Merely viewing the link never changes preferences.
     *
     * @return string Page content.
     */
    public static function unsubscribe(): string {
        global $OUTPUT;

        if (optional_param('preview', false, PARAM_BOOL)) {
            return $OUTPUT->notification(get_string('subscription_preview', 'local_mailwhistle'), 'info');
        }
        $token = optional_param('t', '', PARAM_RAW);
        $recipient = subscription_manager::verify_token($token);
        if (!$recipient) {
            http_response_code(400);
            return $OUTPUT->notification(get_string('subscription_invalid', 'local_mailwhistle'), 'error');
        }
        // A signed bearer token authorises this anonymous, cookie-free POST.
        // Requiring confirmation protects recipients from automatic link scanners.
        if (data_submitted() && optional_param('action', '', PARAM_ALPHA) === 'unsubscribe') {
            helper::require_post();
            if (!subscription_manager::unsubscribe($token)) {
                return $OUTPUT->notification(get_string('subscription_invalid', 'local_mailwhistle'), 'error');
            }
            return $OUTPUT->notification(get_string('subscription_stopped', 'local_mailwhistle'), 'success')
                . self::preferences_link();
        }
        $url = new \moodle_url('/local/mailwhistle/unsubscribe.php', ['t' => $token, 'action' => 'unsubscribe']);
        return \html_writer::tag('p', get_string('subscription_confirm', 'local_mailwhistle', s($recipient->email)))
            . \html_writer::tag('p', get_string('subscription_scope', 'local_mailwhistle'))
            . $OUTPUT->single_button($url, get_string('subscription_unsubscribe', 'local_mailwhistle'), 'post')
            . self::preferences_link();
    }

    /**
     * Let the logged-in owner change only their own preference.
     *
     * @return string Page content.
     */
    public static function preferences(): string {
        global $USER, $OUTPUT;

        require_login();
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        $url = new \moodle_url('/local/mailwhistle/preferences.php');
        if (data_submitted()) {
            helper::require_post();
            require_sesskey();
            $subscribed = required_param('subscribed', PARAM_BOOL);
            subscription_manager::set_subscription((int) $USER->id, $subscribed);
            redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
        $unsubscribed = subscription_manager::is_unsubscribed((int) $USER->id);
        $status = $unsubscribed ? 'subscription_stopped' : 'subscription_allowed';
        $action = $unsubscribed ? 'subscription_allow' : 'subscription_unsubscribe';
        $buttonurl = new \moodle_url($url, ['subscribed' => (int) $unsubscribed]);
        return \html_writer::tag('p', get_string($status, 'local_mailwhistle'))
            . \html_writer::tag('p', get_string('subscription_scope', 'local_mailwhistle'))
            . $OUTPUT->single_button($buttonurl, get_string($action, 'local_mailwhistle'), 'post');
    }

    /**
     * Provide a login-protected route for the owner to subscribe again.
     *
     * @return string Preferences link and explanation.
     */
    private static function preferences_link(): string {
        return \html_writer::tag('p', \html_writer::link(
            new \moodle_url('/local/mailwhistle/preferences.php'),
            get_string('subscription_manage', 'local_mailwhistle')
        ));
    }
}
