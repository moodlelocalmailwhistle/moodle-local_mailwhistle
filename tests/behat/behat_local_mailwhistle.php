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

// NOTE: this file is a Behat context and is intentionally outside the PSR-4
// classes/ tree, so it does not use a namespace (Moodle Behat convention).

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat step definitions for local_mailwhistle.
 *
 * @package   local_mailwhistle
 * @category  test
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_mailwhistle extends behat_base {
    /**
     * Get a campaign id by its name.
     *
     * @param string $name Campaign name.
     * @return int Campaign id.
     */
    protected function get_campaign_id(string $name): int {
        global $DB;
        return (int) $DB->get_field('local_mailwhistle_campaigns', 'id', ['name' => $name], MUST_EXIST);
    }

    /**
     * Set an audience tag on a campaign (data setup shortcut).
     *
     * @Given /^I set the audience tag "(?P<tag_string>(?:[^"]|\\")*)" for campaign "(?P<campaign_string>(?:[^"]|\\")*)"$/
     * @param string $tag The tag name.
     * @param string $campaign The campaign name.
     * @return void
     */
    public function i_set_the_audience_tag_for_campaign(string $tag, string $campaign): void {
        global $DB;
        $tagid = (int) $DB->get_field('local_mailwhistle_tag', 'id', ['name' => $tag], MUST_EXIST);
        $campaignid = $this->get_campaign_id($campaign);
        \local_mailwhistle\manager\audience_manager::set_campaign_tags($campaignid, [$tagid]);
    }

    /**
     * Visit the review step of the edit wizard for a campaign.
     *
     * @Given /^I visit the review step for campaign "(?P<campaign_string>(?:[^"]|\\")*)"$/
     * @param string $campaign The campaign name.
     * @return void
     */
    public function i_visit_the_review_step_for_campaign(string $campaign): void {
        $campaignid = $this->get_campaign_id($campaign);
        $url = new moodle_url('/local/mailwhistle/campaign_edit.php', [
            'campaignid' => $campaignid,
            'step' => 'review',
        ]);
        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Assert a campaign has an expected status.
     *
     * @Then /^campaign "(?P<campaign_string>(?:[^"]|\\")*)" should have status "(?P<status_string>(?:[^"]|\\")*)"$/
     * @param string $campaign The campaign name.
     * @param string $status The expected status.
     * @return void
     */
    public function campaign_should_have_status(string $campaign, string $status): void {
        global $DB;
        $actual = $DB->get_field('local_mailwhistle_campaigns', 'status', ['name' => $campaign], MUST_EXIST);
        if ($actual !== $status) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Campaign '$campaign' status is '$actual', expected '$status'",
                $this->getSession()
            );
        }
    }
}
