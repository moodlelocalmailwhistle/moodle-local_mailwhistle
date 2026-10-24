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

namespace local_mailwhistle;

use local_mailwhistle\manager\audience_manager;
use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\manager\recipient_manager;
use local_mailwhistle\manager\send_manager;
use local_mailwhistle\manager\subscription_manager;
use local_mailwhistle\manager\tag_manager;
use local_mailwhistle\manager\tracking_manager;
use local_mailwhistle\page\subscription;

/**
 * Tests for recipient-authorised opt-outs and owner-only preferences.
 *
 * @package   local_mailwhistle
 * @covers    \local_mailwhistle\manager\subscription_manager
 * @covers    \local_mailwhistle\page\subscription
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class subscription_manager_test extends \advanced_testcase {
    /**
     * Isolate data and use committed writes for lock-protected operations.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
    }

    /**
     * Create a ready campaign and a frozen recipient.
     *
     * @return array Campaign, user, recipient and token.
     */
    private function make_delivery(): array {
        global $DB;

        $user = $this->getDataGenerator()->create_user(['email' => 'original@example.com']);
        $campaign = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_campaign([
            'bodyhtml' => '<p>Hello {{firstname}}</p>', 'bodytext' => 'Hello {{firstname}}',
        ]);
        $tagid = tag_manager::create_tag('Newsletter');
        audience_manager::set_campaign_tags($campaign->id, [$tagid]);
        tag_manager::assign_tag($tagid, $user->id);
        campaign_manager::mark_complete($campaign->id);
        recipient_manager::snapshot_recipients($campaign->id);
        $recipient = $DB->get_record('local_mailwhistle_recipients', ['campaignid' => $campaign->id], '*', MUST_EXIST);
        $token = subscription_manager::unsubscribe_url($recipient)->get_param('t');
        return [$campaign, $user, $recipient, $token];
    }

    /**
     * A token is bound to recipient identity and cannot be reused from tracking.
     */
    public function test_token_binding_and_purpose(): void {
        global $DB;
        [$campaign, $user, $recipient, $token] = $this->make_delivery();

        $this->assertSame((int) $recipient->id, (int) subscription_manager::verify_token($token)->id);
        $this->assertNull(subscription_manager::verify_token($token . '0'));
        $this->assertNull(subscription_manager::verify_token(str_repeat('9', 40) . '-' . str_repeat('a', 64)));
        $this->assertNull(subscription_manager::verify_token(tracking_manager::make_open_token($campaign->id, $recipient->id)));
        $this->assertFalse(subscription_manager::unsubscribe('not-a-token'));
        $DB->set_field('user', 'email', 'changed@example.com', ['id' => $user->id]);
        $this->assertNotNull(subscription_manager::verify_token($token), 'Previously delivered links remain valid.');
        $DB->set_field('local_mailwhistle_recipients', 'email', 'other@example.com', ['id' => $recipient->id]);
        $this->assertNull(subscription_manager::verify_token($token), 'Snapshot identity cannot be substituted.');
        $this->assertFalse(subscription_manager::is_unsubscribed($user->id));
    }

    /**
     * Privacy deletion revokes emailed links.
     */
    public function test_deleted_snapshot_invalidates_token(): void {
        global $DB;
        [, , $recipient, $token] = $this->make_delivery();
        $DB->delete_records('local_mailwhistle_recipients', ['id' => $recipient->id]);
        $this->assertNull(subscription_manager::verify_token($token));
    }

    /**
     * Confirming repeatedly records one opt-out, and only the owner can opt back in through preferences.
     */
    public function test_unsubscribe_is_idempotent_and_removes_from_future_audience(): void {
        global $DB;
        [$campaign, $user, , $token] = $this->make_delivery();
        $this->assertTrue(subscription_manager::unsubscribe($token));
        $this->assertTrue(subscription_manager::unsubscribe($token));
        $this->assertSame(1, $DB->count_records('local_mailwhistle_unsubscribes', ['userid' => $user->id]));
        $this->assertSame([], recipient_manager::resolve_recipients($campaign->id));
        subscription_manager::set_subscription($user->id, true);
        $this->assertArrayHasKey($user->id, recipient_manager::resolve_recipients($campaign->id));
    }

    /**
     * A queued recipient who opts out is skipped before transport.
     */
    public function test_optout_after_snapshot_prevents_delivery(): void {
        global $CFG, $DB;
        $CFG->noemailever = false;
        $DB->set_field('message_processors', 'enabled', 1, ['name' => 'email']);
        $sink = $this->redirectEmails();
        [$campaign, , $recipient, $token] = $this->make_delivery();
        $this->assertTrue(campaign_manager::begin_sending($campaign->id));
        subscription_manager::unsubscribe($token);
        send_manager::process_campaign($campaign->id, 50);
        $this->assertCount(0, $sink->get_messages());
        $this->assertSame('skipped', $DB->get_field('local_mailwhistle_recipients', 'status', ['id' => $recipient->id]));
        $sink->close();
    }

    /**
     * GET requests and inert previews never change preferences, while a confirmed POST works without login.
     */
    public function test_anonymous_confirmation_and_preview(): void {
        [, $user, , $token] = $this->make_delivery();
        global $PAGE;
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/local/mailwhistle/unsubscribe.php'));
        $this->setUser(null);
        $previous = [$_GET, $_POST];
        try {
            $_GET = ['t' => $token, 'action' => 'unsubscribe'];
            $_POST = [];
            $this->assertStringContainsString('original@example.com', subscription::unsubscribe());
            $this->assertFalse(subscription_manager::is_unsubscribed($user->id));
            $_GET = [];
            $_POST = ['t' => $token, 'action' => 'unsubscribe', 'preview' => 1];
            $this->assertStringContainsString('preview unsubscribe link', subscription::unsubscribe());
            $this->assertFalse(subscription_manager::is_unsubscribed($user->id));
            unset($_POST['preview']);
            $this->assertStringContainsString('You have opted out', subscription::unsubscribe());
            $this->assertTrue(subscription_manager::is_unsubscribed($user->id));
        } finally {
            [$_GET, $_POST] = $previous;
        }
    }

    /**
     * Authenticated preferences ignore a forged target user id.
     */
    public function test_preferences_only_change_logged_in_owner(): void {
        [, $owner] = $this->make_delivery();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $previous = [$_GET, $_POST];
        try {
            $_GET = [];
            $_POST = ['userid' => $other->id, 'subscribed' => 0, 'sesskey' => sesskey()];
            try {
                subscription::preferences();
                $this->fail('Preferences must redirect after saving.');
            } catch (\moodle_exception $exception) {
                $this->assertSame('redirecterrordetected', $exception->errorcode);
            }
            $this->assertTrue(subscription_manager::is_unsubscribed($owner->id));
            $this->assertFalse(subscription_manager::is_unsubscribed($other->id));
        } finally {
            [$_GET, $_POST] = $previous;
        }
    }

    /**
     * A signed-in preference change also requires its Moodle CSRF token.
     */
    public function test_preferences_reject_invalid_sesskey(): void {
        [, $owner] = $this->make_delivery();
        $this->setUser($owner);
        $previous = [$_GET, $_POST];
        try {
            $_GET = [];
            $_POST = ['subscribed' => 0, 'sesskey' => 'invalid'];
            try {
                subscription::preferences();
                $this->fail('An invalid session key must be rejected.');
            } catch (\moodle_exception $exception) {
                $this->assertSame('invalidsesskey', $exception->errorcode);
            }
            $this->assertFalse(subscription_manager::is_unsubscribed($owner->id));
        } finally {
            [$_GET, $_POST] = $previous;
        }
    }

    /**
     * HTML footer is inside the body and neither it nor an explicit placeholder is click-tracked.
     */
    public function test_footer_and_tracking_keep_unsubscribe_direct(): void {
        [$campaign, , $recipient] = $this->make_delivery();
        $url = subscription_manager::unsubscribe_url($recipient)->out(false);
        $body = subscription_manager::add_footer('<html><body>News</body></html>', $url, true);
        $this->assertStringContainsString('</a></p></body>', $body);
        $this->assertSame($body, subscription_manager::add_footer($body, $url, true));
        $tracked = tracking_manager::prepare_body($body, $campaign->id, $recipient->id);
        $this->assertStringContainsString($url, $tracked);
        $this->assertStringNotContainsString('click.php', $tracked);
        $this->assertStringContainsString('pixel.php', $tracked);
        $hidden = subscription_manager::add_footer('<p title="' . s($url) . '">News</p>', $url, true);
        $this->assertStringContainsString('<a href="' . s($url) . '">', $hidden);
        $empty = subscription_manager::add_footer('<a href="' . s($url) . '">&nbsp;</a>', $url, true);
        $this->assertStringContainsString('>' . get_string('subscription_unsubscribe', 'local_mailwhistle') . '</a>', $empty);
        foreach (
            [
            '<!-- <a href="' . s($url) . '">Comment only</a> -->',
            '<script>var link = \'<a href="' . s($url) . '">Script only</a>\';</script>',
            '<a href="' . s($url) . '"><span hidden>Hidden label</span></a>',
            '<div style="display: none"><a href="' . s($url) . '">Hidden link</a></div>',
            ] as $source
        ) {
            $withfooter = subscription_manager::add_footer($source, $url, true);
            $this->assertStringStartsWith($source, $withfooter, 'Parsing must not rewrite the original body.');
            $this->assertStringEndsWith(
                '<p><a href="' . s($url) . '">' . get_string('subscription_unsubscribe', 'local_mailwhistle') . '</a></p>',
                $withfooter
            );
        }
    }

    /**
     * Real message rendering issues a live link, while test copies can never change a preference.
     */
    public function test_live_and_test_messages_have_distinct_unsubscribe_links(): void {
        [$campaign, $user, $recipient, $token] = $this->make_delivery();
        $campaign->bodyhtml = '<p><a href="{{unsubscribe}}">Manage subscription</a></p>';
        $builder = new \ReflectionMethod(send_manager::class, 'make_message');
        $live = $builder->invoke(null, $campaign, $user, $recipient, false);
        $test = $builder->invoke(null, $campaign, $user, $recipient, true);
        $url = subscription_manager::unsubscribe_url($recipient)->out(false);
        $this->assertStringContainsString($url, $live->fullmessagehtml);
        $this->assertStringContainsString($url, $live->fullmessage);
        $this->assertStringNotContainsString('click.php', $live->fullmessagehtml);
        $this->assertStringContainsString('unsubscribe.php?preview=1', $test->fullmessagehtml);
        $this->assertStringNotContainsString($token, $test->fullmessagehtml);
        $this->assertStringNotContainsString('pixel.php', $test->fullmessagehtml);
        $this->assertStringNotContainsString('{{unsubscribe}}', $live->fullmessagehtml);
    }

    /**
     * Copied links target the current recipient, or an inert preview, without rewriting unrelated HTML.
     */
    public function test_copied_unsubscribe_links_are_reassigned(): void {
        [$campaign, $user, $recipient] = $this->make_delivery();
        $previoususer = $this->getDataGenerator()->create_user(['email' => 'previous@example.com']);
        $previous = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle')->create_recipient([
            'campaignid' => $campaign->id, 'userid' => $previoususer->id, 'email' => $previoususer->email,
        ]);
        $this->assertNotNull(subscription_manager::verify_token(subscription_manager::unsubscribe_url($previous)->get_param('t')));
        $oldurl = subscription_manager::unsubscribe_url($previous)->out(false);
        $oldurl .= '&source=copy';
        $external = 'https://example.com/?next=' . $oldurl;
        $unchanged = '<p title=\'href="' . s($oldurl) . '"\'>Original content &amp; formatting</p>'
            . '<a href="' . s($external) . '">External link</a>'
            . '<!-- <a href="' . s($oldurl) . '">Comment</a> -->';
        $campaign->bodyhtml = '<a class="footer" href = "' . s($oldurl) . '">Manage subscription</a>'
            . "<a href='" . str_replace('?', '&#63;', s($oldurl)) . "'>Stop email</a>" . $unchanged;
        $campaign->bodytext = 'Stop email: ' . $oldurl . ".\nExternal: " . $external;
        $builder = new \ReflectionMethod(send_manager::class, 'make_message');
        $test = $builder->invoke(null, $campaign, $user, $recipient, true);
        $previewurl = subscription_manager::preview_url()->out(false);
        $expectedhtml = '<a class="footer" href = "' . s($previewurl) . '">Manage subscription</a>'
            . "<a href='" . s($previewurl) . "'>Stop email</a>" . $unchanged;
        $this->assertSame($expectedhtml, $test->fullmessagehtml);
        $this->assertSame('Stop email: ' . $previewurl . ".\nExternal: " . $external, $test->fullmessage);
        $this->assertStringNotContainsString('pixel.php', $test->fullmessagehtml);

        // Remove unrelated links before checking that only the unsubscribe links bypass click tracking.
        $campaign->bodyhtml = '<a href="' . s($oldurl) . '">Manage subscription</a>';
        $campaign->bodytext = 'Stop email: ' . $oldurl;
        $live = $builder->invoke(null, $campaign, $user, $recipient, false);
        $currenturl = subscription_manager::unsubscribe_url($recipient)->out(false);
        $this->assertStringContainsString('href="' . s($currenturl) . '"', $live->fullmessagehtml);
        $this->assertSame('Stop email: ' . $currenturl, $live->fullmessage);
        $this->assertStringNotContainsString($oldurl, $live->fullmessagehtml);
        $this->assertStringNotContainsString('click.php', $live->fullmessagehtml);
        $this->assertSame(1, substr_count($live->fullmessagehtml, '<a '));

        // HTML-to-text conversion must also replace old URLs used as visible labels.
        $campaign->bodyhtml = '<a href="' . s($oldurl) . '">' . s($oldurl) . '</a>';
        $campaign->bodytext = '';
        foreach ([true, false] as $istest) {
            $message = $builder->invoke(null, $campaign, $user, $recipient, $istest);
            $this->assertStringNotContainsString($oldurl, $message->fullmessage);
            $this->assertStringContainsString($istest ? $previewurl : $currenturl, $message->fullmessage);
        }
    }

    /**
     * Dispatch freezes copied recipient links as placeholders rather than saving a second footer.
     */
    public function test_dispatch_freezes_copied_unsubscribe_links(): void {
        global $DB;
        [$campaign, , $recipient] = $this->make_delivery();
        $oldurl = subscription_manager::unsubscribe_url($recipient)->out(false);
        $DB->update_record('local_mailwhistle_campaigns', (object) [
            'id' => $campaign->id,
            'bodyhtml' => '<p>News</p><a href="' . s($oldurl) . '">Stop email</a>',
            'bodytext' => 'News. Stop email: ' . $oldurl,
        ]);
        $this->assertTrue(campaign_manager::begin_sending($campaign->id));
        $saved = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaign->id], '*', MUST_EXIST);
        $this->assertSame('<p>News</p><a href="{{unsubscribe}}">Stop email</a>', $saved->bodyhtml);
        $this->assertSame('News. Stop email: {{unsubscribe}}', $saved->bodytext);
    }

    /**
     * A footer must not replace the content when one message alternative is absent.
     */
    public function test_footer_preserves_plain_only_and_html_only_content(): void {
        [$campaign, $user, $recipient] = $this->make_delivery();
        $builder = new \ReflectionMethod(send_manager::class, 'make_message');
        $campaign->bodyhtml = '';
        $campaign->bodytext = 'Plain newsletter';
        $plain = $builder->invoke(null, $campaign, $user, $recipient, false);
        $this->assertSame('', $plain->fullmessagehtml);
        $this->assertStringContainsString('Plain newsletter', $plain->fullmessage);
        $this->assertStringContainsString('unsubscribe.php', $plain->fullmessage);

        $campaign->bodyhtml = '<p>HTML newsletter</p>';
        $campaign->bodytext = '';
        $html = $builder->invoke(null, $campaign, $user, $recipient, false);
        $this->assertStringContainsString('HTML newsletter', $html->fullmessage);
        $this->assertStringContainsString('HTML newsletter', $html->fullmessagehtml);
        $this->assertStringContainsString('unsubscribe.php', $html->fullmessage);
    }

    /**
     * Tracking preserves multi-parameter destinations and supports single-quoted links.
     */
    public function test_tracking_preserves_encoded_query_parameters(): void {
        [$campaign, , $recipient] = $this->make_delivery();
        $out = tracking_manager::prepare_body(
            "<a href='https://example.com/?one=1&amp;two=2'>Read more</a>",
            $campaign->id,
            $recipient->id
        );
        $dom = new \DOMDocument();
        $dom->loadHTML($out, LIBXML_NOERROR | LIBXML_NOWARNING);
        parse_str(parse_url($dom->getElementsByTagName('a')->item(0)->getAttribute('href'), PHP_URL_QUERY), $params);
        $this->assertSame('https://example.com/?one=1&two=2', $params['url']);
        $this->assertNotNull(tracking_manager::verify_click_token($params['t'], $params['url']));
    }
}
