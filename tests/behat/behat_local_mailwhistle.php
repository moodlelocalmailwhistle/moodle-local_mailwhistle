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
 * @copyright 2026 onwards MoodleDach project
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

    /**
     * Open a Mail Whistle admin tab.
     *
     * @When /^I am on the Mail Whistle "(?P<tab_string>(?:[^"]|\\")*)" tab$/
     * @param string $tab Tab id (send, audience, templates, reports, resources).
     * @return void
     */
    public function i_am_on_the_mail_whistle_tab(string $tab): void {
        $url = new moodle_url('/local/mailwhistle/index.php', ['tab' => $tab]);
        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Assert that a user currently has the named audience tag.
     *
     * @Then /^user "(?P<username_string>(?:[^"]|\\")*)" should have tag "(?P<tag_string>(?:[^"]|\\")*)"$/
     * @param string $username Moodle username.
     * @param string $tag Tag display name.
     * @return void
     */
    public function user_should_have_tag(string $username, string $tag): void {
        global $DB;
        $userid = (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $tagid = (int) $DB->get_field('local_mailwhistle_tag', 'id', ['name' => $tag], MUST_EXIST);
        $exists = $DB->record_exists('local_mailwhistle_tag_assign', [
            'userid' => $userid,
            'tagid' => $tagid,
        ]);
        if (!$exists) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "User '$username' does not have tag '$tag'",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that a user does not have the named audience tag.
     *
     * @Then /^user "(?P<username_string>(?:[^"]|\\")*)" should not have tag "(?P<tag_string>(?:[^"]|\\")*)"$/
     * @param string $username Moodle username.
     * @param string $tag Tag display name.
     * @return void
     */
    public function user_should_not_have_tag(string $username, string $tag): void {
        global $DB;
        $userid = (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $tagid = $DB->get_field('local_mailwhistle_tag', 'id', ['name' => $tag]);
        if (!$tagid) {
            return;
        }
        $exists = $DB->record_exists('local_mailwhistle_tag_assign', [
            'userid' => $userid,
            'tagid' => (int) $tagid,
        ]);
        if ($exists) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "User '$username' still has tag '$tag'",
                $this->getSession()
            );
        }
    }

    /**
     * Assert an image inside a CSS region uses a Mail Whistle pluginfile URL.
     *
     * @Then /^the image in "(?P<selector_string>(?:[^"]|\\")*)" should use a Mail Whistle resource URL$/
     * @param string $selector CSS selector for the region that contains the image.
     * @return void
     */
    public function the_image_should_use_a_resource_url(string $selector): void {
        $src = $this->get_preview_image_src($selector);
        if (!str_contains($src, 'pluginfile.php') || !str_contains($src, 'local_mailwhistle')) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Image src is not a Mail Whistle pluginfile URL: $src",
                $this->getSession()
            );
        }
    }

    /**
     * Fetch the template preview image with no session cookie.
     *
     * @Then /^the preview image should load without a Moodle login$/
     * @return void
     */
    public function the_preview_image_should_load_without_login(): void {
        $src = $this->get_preview_image_src('.local-mailwhistle-email-preview');
        if (!preg_match('#^https?://#', $src)) {
            $src = $this->locate_path($src);
        }

        $client = new \curl();
        $client->setopt([
            'CURLOPT_FOLLOWLOCATION' => 0,
            'CURLOPT_TIMEOUT' => 10,
        ]);
        $body = $client->get($src);
        $info = $client->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        $contenttype = (string) ($info['content_type'] ?? '');

        if ($httpcode !== 200 || $body === '' || $body === false) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Public image fetch failed: HTTP $httpcode from $src",
                $this->getSession()
            );
        }
        if (!str_contains(strtolower($contenttype), 'image/')) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Expected image content-type, got '$contenttype' from $src",
                $this->getSession()
            );
        }
    }

    /**
     * Read the first image src from a CSS region.
     *
     * @param string $selector CSS selector for the region.
     * @return string Image src attribute.
     */
    protected function get_preview_image_src(string $selector): string {
        $node = $this->find('css', $selector);
        $img = $node->find('css', 'img');
        if (!$img) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "No image found in $selector",
                $this->getSession()
            );
        }

        return trim((string) $img->getAttribute('src'));
    }
}
