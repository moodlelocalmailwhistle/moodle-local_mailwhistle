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

use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\output\campaign_report;

/**
 * Tests for the campaign report page.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\output\campaign_report
 */
final class campaign_report_test extends \advanced_testcase {
    /**
     * Render a campaign report through the plugin renderer.
     *
     * @param int $id Campaign id.
     * @param int $recipientid Optional recipient snapshot id.
     * @return string
     */
    private function render_report(int $id, int $recipientid = 0): string {
        global $PAGE;

        $PAGE->set_url(new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'reports']));
        $PAGE->set_context(\context_system::instance());
        $renderer = $PAGE->get_renderer('local_mailwhistle');
        return $renderer->render(new campaign_report($id, $recipientid));
    }

    /**
     * Export report data without escaping the preview document into its frame.
     *
     * @param int $id Campaign id.
     * @param int $recipientid Optional recipient snapshot id.
     * @return array Template data.
     */
    private function export_report(int $id, int $recipientid = 0): array {
        global $PAGE;

        $PAGE->set_url(new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'reports']));
        $PAGE->set_context(\context_system::instance());
        return (new campaign_report($id, $recipientid))->export_for_template($PAGE->get_renderer('local_mailwhistle'));
    }

    /**
     * Create an independent recipient snapshot for a campaign.
     *
     * @param int $campaignid Campaign id.
     * @param array $fields Snapshot field overrides.
     * @return \stdClass Recipient snapshot.
     */
    private function make_recipient(int $campaignid, array $fields = []): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        return $generator->create_recipient($fields + [
            'campaignid' => $campaignid,
            'userid' => $user->id,
            'firstname' => 'Snapshot',
            'lastname' => 'Person',
            'email' => 'snapshot@example.com',
            'status' => 'sent',
        ]);
    }

    /**
     * A sent campaign lists snapshotted recipients.
     */
    public function test_render_lists_recipients(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign([
            'name' => 'Spring',
            'subject' => 'Spring news',
            'status' => campaign_manager::STATUS_SENT,
            'timesent' => time(),
        ]);
        $generator->create_recipient([
            'campaignid' => (int) $campaign->id,
            'userid' => (int) $this->getDataGenerator()->create_user()->id,
            'email' => 'alice@example.com',
            'firstname' => 'Alice',
            'lastname' => 'Rowe',
            'status' => 'sent',
        ]);

        $html = $this->render_report((int) $campaign->id);
        $this->assertStringContainsString('alice@example.com', $html);
        $this->assertStringContainsString('Alice Rowe', $html);
        $this->assertStringContainsString('Spring news', $html);
        $this->assertStringNotContainsString('Reporting and analytics are coming soon.', $html);
    }

    /**
     * Draft campaigns show the not-found notice.
     */
    public function test_render_rejects_draft(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = self::getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $draft = $generator->create_campaign(['status' => campaign_manager::STATUS_DRAFT]);

        $html = $this->render_report((int) $draft->id);
        $this->assertStringContainsString(get_string('mailnotfound', 'local_mailwhistle'), $html);
    }

    /**
     * Default preview uses the first sent snapshot and the campaign's saved content.
     */
    public function test_default_preview_uses_saved_campaign_and_recipient(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $template = $generator->create_template(['bodyhtml' => '<p>Changed template content</p>']);
        $campaign = $generator->create_campaign([
            'name' => 'Saved newsletter',
            'subject' => 'Hello {{firstname}} & friends',
            'bodyhtml' => '<p>Saved for {{fullname}} at {{email}}</p>',
            'bodytext' => "Saved for {{firstname}}\nPlain alternative",
            'templateid' => $template->id,
            'status' => 'sent',
            'attachmentsjson' => '["handbook.pdf","notes.txt"]',
        ]);
        $this->make_recipient($campaign->id, ['status' => 'pending', 'firstname' => 'Pending']);
        $first = $this->make_recipient($campaign->id, ['firstname' => 'Alice', 'lastname' => 'Rowe']);
        $this->make_recipient($campaign->id, ['firstname' => 'Bob']);
        $DB->set_field('user', 'firstname', 'Changed profile', ['id' => $first->userid]);

        $data = $this->export_report($campaign->id);
        $email = $data['emailpreview'];
        $this->assertFalse($data['previewunavailable']);
        $this->assertSame('Saved newsletter', $data['campaignname']);
        $this->assertSame('Hello Alice & friends', $email['subject']);
        $this->assertSame('Alice Rowe <snapshot@example.com>', $email['recipientlabel']);
        $this->assertTrue($email['hasrecipient']);
        $this->assertStringContainsString('Saved for Alice Rowe at snapshot@example.com', $email['previewhtml']);
        $this->assertStringNotContainsString('Changed template content', $email['previewhtml']);
        $this->assertStringNotContainsString('Changed profile', $email['previewhtml']);
        $this->assertSame("Saved for Alice\nPlain alternative", $email['bodytext']);
        $this->assertTrue($email['hasplaintext']);
        $this->assertSame([['filename' => 'handbook.pdf'], ['filename' => 'notes.txt']], $email['attachments']);
        $this->assertTrue($email['hasattachments']);
        $this->assertSame(0, $DB->count_records('local_mailwhistle_tracking'));
        $this->assertSame(0, $DB->count_records('local_mailwhistle_sendlogs'));
    }

    /**
     * Choosing a sent recipient changes personalisation and survives table sorting links.
     */
    public function test_explicit_preview_selects_snapshot_and_preserves_selection(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign([
            'subject' => 'For {{firstname}}', 'bodyhtml' => '<p>Hello {{firstname}}</p>', 'status' => 'sent',
        ]);
        $this->make_recipient($campaign->id, ['firstname' => 'Alice']);
        $bob = $this->make_recipient($campaign->id, ['firstname' => 'Bob', 'email' => 'bob@example.com']);

        $data = $this->export_report($campaign->id, $bob->id);
        $this->assertSame('For Bob', $data['emailpreview']['subject']);
        $this->assertSame('Bob Person <bob@example.com>', $data['emailpreview']['recipientlabel']);
        $this->assertStringContainsString('Hello Bob', $data['emailpreview']['previewhtml']);
        $dom = new \DOMDocument();
        $dom->loadHTML($data['table'], LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = (new \DOMXPath($dom))->query('//th//a[contains(@href,"tsort=")]');
        $this->assertGreaterThan(0, $links->length);
        foreach ($links as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $params);
            $this->assertSame((string) $bob->id, $params['recipientid']);
        }
    }

    /**
     * Explicit foreign, pending, failed, and nonexistent recipient ids cannot be previewed.
     */
    public function test_explicit_preview_rejects_unavailable_recipients(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign(['status' => 'sent', 'bodyhtml' => '<p>Saved body</p>']);
        $other = $generator->create_campaign(['status' => 'sent']);
        $sent = $this->make_recipient($campaign->id);
        $foreign = $this->make_recipient($other->id);
        $pending = $this->make_recipient($campaign->id, ['status' => 'pending']);
        $failed = $this->make_recipient($campaign->id, ['status' => 'failed']);

        foreach ([$foreign->id, $pending->id, $failed->id, -1, $sent->id + 100000] as $recipientid) {
            $data = $this->export_report($campaign->id, $recipientid);
            $this->assertTrue($data['previewunavailable']);
            $this->assertArrayNotHasKey('emailpreview', $data);
        }
    }

    /**
     * A campaign without sent recipients shows saved generic text and no invented send time.
     */
    public function test_generic_preview_and_sending_metadata(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign([
            'name' => ' ', 'subject' => 'Hello {{firstname}}', 'bodyhtml' => '<p>Hello {{firstname}}</p>',
            'status' => 'sending', 'timesent' => 0, 'sendername' => 'News team',
        ]);
        $this->make_recipient($campaign->id, ['status' => 'pending']);

        $data = $this->export_report($campaign->id);
        $this->assertFalse($data['emailpreview']['hasrecipient']);
        $this->assertSame('Hello {{firstname}}', $data['emailpreview']['subject']);
        $this->assertStringContainsString('Hello {{firstname}}', $data['emailpreview']['previewhtml']);
        $this->assertSame(get_string('untitledcampaign', 'local_mailwhistle'), $data['campaignname']);
        $metadata = array_column($data['metarows'], 'value', 'label');
        $this->assertSame('', $metadata[get_string('col_completedat', 'local_mailwhistle')]);
        $this->assertSame('News team', $metadata[get_string('sendername', 'local_mailwhistle')]);
        $this->assertSame(get_string('stat_pendingcount', 'local_mailwhistle', 1), $data['stats'][0]['detail']);
    }

    /**
     * Plain text is preserved and escaped in the email document when HTML is unavailable.
     */
    public function test_plain_text_fallback_and_empty_body(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $text = "Hello {{firstname}}\n<script>Text, not code</script> & more";
        $campaign = $generator->create_campaign(['status' => 'sent', 'bodytext' => $text]);
        $this->make_recipient($campaign->id, ['firstname' => 'Ada']);
        $email = $this->export_report($campaign->id)['emailpreview'];
        $this->assertTrue($email['hasbody']);
        $this->assertTrue($email['hasplaintext']);
        $this->assertSame(str_replace('{{firstname}}', 'Ada', $text), $email['bodytext']);
        $this->assertStringContainsString(
            "Hello Ada\n&lt;script&gt;Text, not code&lt;/script&gt; &amp; more",
            $email['previewhtml']
        );
        $this->assertStringNotContainsString('<script>', $email['previewhtml']);
        $DB->set_field('local_mailwhistle_campaigns', 'bodytext', '', ['id' => $campaign->id]);
        $email = $this->export_report($campaign->id)['emailpreview'];
        $this->assertFalse($email['hasbody']);
        $this->assertFalse($email['hasplaintext']);
    }

    /**
     * Email HTML is cleaned, links disabled, and safely isolated in the rendered frame.
     */
    public function test_preview_is_sanitized_sandboxed_and_has_no_tracking(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_mailwhistle');
        $campaign = $generator->create_campaign([
            'status' => 'sent',
            'subject' => 'News & events',
            'bodyhtml' => '<html><head><title>Saved mail</title></head><body><p onclick="alert(1)">Hello {{firstname}}</p>' .
                '<script>alert(1)</script><a href="https://example.com/news?a=1&amp;b=2">Read more</a>' .
                '<img src="https://example.com/image.png" onerror="alert(2)">' .
                '<img src="pixel.php?t=existing"><img src="https://example.com/local/mailwhistle/pixel.php?t=existing">' .
                '<img src="pixel.php/preview-review?t=existing">' .
                '<img srcset="//example.com/local/mailwhistle/pixel.php?t=existing 1x">' .
                '<p style="background-image:url(/local/mailwhistle/pixel.php?t=existing)">Copied pixel</p></body></html>',
        ]);
        $recipient = $this->make_recipient($campaign->id, ['firstname' => 'Ada & friends']);
        $html = $this->render_report($campaign->id, $recipient->id);
        $outer = new \DOMDocument();
        $outer->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $frames = $outer->getElementsByTagName('iframe');
        $this->assertSame(1, $frames->length);
        $frame = $frames->item(0);
        $this->assertTrue($frame->hasAttribute('sandbox'));
        $this->assertSame('', $frame->getAttribute('sandbox'));
        $document = $frame->getAttribute('srcdoc');
        $this->assertStringContainsString('Hello Ada &amp; friends', $document);
        $this->assertStringNotContainsString('<script', $document);
        $this->assertStringNotContainsString('onclick', $document);
        $this->assertStringNotContainsString('onerror', $document);
        $this->assertStringNotContainsString('href=', $document);
        $this->assertStringNotContainsString('pixel.php', $document);
        $this->assertStringNotContainsString('click.php', $document);
        $inner = new \DOMDocument();
        $inner->loadHTML($document, LIBXML_NOERROR | LIBXML_NOWARNING);
        $meta = (new \DOMXPath($inner))->query('//meta[@http-equiv="Content-Security-Policy"]')->item(0);
        $this->assertNotNull($meta);
        $this->assertSame("default-src 'none'; img-src http: https: data:; style-src 'unsafe-inline'; " .
            "base-uri 'none'; form-action 'none'", $meta->getAttribute('content'));
        $this->assertSame(0, $outer->getElementsByTagName('script')->length);
        $this->assertSame(0, $DB->count_records('local_mailwhistle_tracking'));
    }
}
