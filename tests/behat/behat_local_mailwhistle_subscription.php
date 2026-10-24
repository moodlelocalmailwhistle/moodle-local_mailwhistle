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

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Browser steps for recipient-controlled subscription preferences.
 *
 * @package   local_mailwhistle
 * @category  test
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_mailwhistle_subscription extends behat_base {
    /**
     * Open a real signed link issued for a generated delivery snapshot.
     *
     * @When /^I open the unsubscribe link for "([^"]*)" in campaign "([^"]*)"$/
     * @param string $username Recipient username.
     * @param string $campaignname Campaign name.
     * @return void
     */
    public function open_unsubscribe_link(string $username, string $campaignname): void {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $campaignid = $DB->get_field('local_mailwhistle_campaigns', 'id', ['name' => $campaignname], MUST_EXIST);
        $recipient = $DB->get_record('local_mailwhistle_recipients', [
            'userid' => $userid, 'campaignid' => $campaignid,
        ], '*', MUST_EXIST);
        $url = \local_mailwhistle\manager\subscription_manager::unsubscribe_url($recipient);
        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Check that viewing a link and confirming it have different effects.
     *
     * @Then /^user "([^"]*)" should( not)? be opted out of campaign emails$/
     * @param string $username Recipient username.
     * @param string $not Whether the assertion is negated.
     * @return void
     */
    public function assert_preference(string $username, string $not = ''): void {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $actual = \local_mailwhistle\manager\subscription_manager::is_unsubscribed((int) $userid);
        if ($actual !== ($not === '')) {
            throw new \Behat\Mink\Exception\ExpectationException('Unexpected campaign email preference.', $this->getSession());
        }
    }
}
