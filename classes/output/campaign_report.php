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

namespace local_mailwhistle\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use local_mailwhistle\builder\preview;
use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\manager\placeholder_manager;
use local_mailwhistle\manager\recipient_manager;
use local_mailwhistle\manager\report_manager;
use local_mailwhistle\reportbuilder\local\helper\campaign as campaignhelper;
use local_mailwhistle\table\recipient_table;

/**
 * Single-campaign report: stats plus recipient roster.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_report implements renderable, templatable {
    /** @var int Campaign id. */
    private int $id;

    /** @var int Explicit recipient snapshot to preview, or zero for the first sent recipient. */
    private int $recipientid;

    /**
     * Create a campaign report.
     *
     * @param int $id Campaign id.
     * @param int $recipientid Optional sent recipient snapshot id.
     */
    public function __construct(int $id, int $recipientid = 0) {
        $this->id = $id;
        $this->recipientid = $recipientid;
    }

    /**
     * Export this data so it can be used in a template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $listurl = new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'reports']);
        $data = [
            'listurl' => $listurl->out(false),
            'notfound' => true,
        ];

        $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $this->id]);
        if (!$campaign || !report_manager::campaign_is_reportable($this->id)) {
            return $data;
        }

        $systemcontext = \context_system::instance();
        $stats = report_manager::get_campaign_stats($this->id);
        $sentat = (int) $campaign->timesent;
        $templateid = (int) ($campaign->templateid ?? 0);
        $templatelabel = campaignhelper::templatename($templateid, $campaign);
        $templateurl = '';
        if ($templateid > 0) {
            $templateurl = (new \moodle_url('/local/mailwhistle/index.php', [
                'tab' => 'reports',
                'templateid' => $templateid,
            ]))->out(false);
        }

        $recipient = $this->get_preview_recipient();
        $previewunavailable = $this->recipientid !== 0 && !$recipient;
        $tableparams = ['tab' => 'reports', 'campaignid' => $this->id];
        if ($recipient && $this->recipientid !== 0) {
            $tableparams['recipientid'] = (int) $recipient->id;
        }
        $table = new recipient_table(
            'local-mailwhistle-recipients-' . $this->id,
            new \moodle_url('/local/mailwhistle/index.php', $tableparams),
            $this->id
        );
        $table->build_sql();
        ob_start();
        $table->out(50, false);
        $tablehtml = ob_get_clean();

        $deliverynotes = [];
        foreach (['failed', 'skipped', 'uncertain'] as $status) {
            if ($stats->$status > 0) {
                $deliverynotes[] = get_string('stat_' . $status . 'count', 'local_mailwhistle', $stats->$status);
            }
        }
        $statcards = [
            [
                'label' => get_string('stat_recipients', 'local_mailwhistle'),
                'value' => number_format($stats->recipients),
                'detail' => $stats->pending > 0
                    ? get_string('stat_pendingcount', 'local_mailwhistle', $stats->pending)
                    : '',
            ],
            [
                'label' => get_string('stat_sent', 'local_mailwhistle'),
                'value' => number_format($stats->sent),
                'detail' => implode('; ', $deliverynotes),
            ],
            [
                'label' => get_string('stat_uniqueopens', 'local_mailwhistle'),
                'value' => number_format($stats->uniqueopens),
                'detail' => get_string('stat_openrate', 'local_mailwhistle', $stats->openrate),
            ],
            [
                'label' => get_string('stat_uniqueclicks', 'local_mailwhistle'),
                'value' => number_format($stats->uniqueclicks),
                'detail' => get_string('stat_clickrate', 'local_mailwhistle', $stats->clickrate),
            ],
        ];

        $campaignname = trim((string) $campaign->name);
        $statushtml = $output->render(new status_badge($campaign->status));
        $data = [
            'listurl' => $listurl->out(false),
            'notfound' => false,
            'subject' => format_string($campaign->subject, true, ['context' => $systemcontext]),
            'campaignname' => format_string($campaignname !== '' ? $campaignname
                : get_string('untitledcampaign', 'local_mailwhistle'), true, ['context' => $systemcontext]),
            'statushtml' => $statushtml,
            'previewunavailable' => $previewunavailable,
            'stats' => $statcards,
            'metarows' => [
                [
                    'label' => get_string('col_audience', 'local_mailwhistle'),
                    'value' => campaignhelper::audience($this->id, $campaign),
                ],
                [
                    'label' => get_string('col_template', 'local_mailwhistle'),
                    'value' => $templateurl
                        ? \html_writer::link($templateurl, $templatelabel)
                        : $templatelabel,
                ],
                [
                    'label' => get_string('sendername', 'local_mailwhistle'),
                    'value' => format_string($campaign->sendername, true, ['context' => $systemcontext]),
                ],
                [
                    'label' => get_string('col_completedat', 'local_mailwhistle'),
                    'value' => $sentat > 0 ? userdate($sentat) : '',
                ],
                [
                    'label' => get_string('col_status', 'local_mailwhistle'),
                    'value' => $statushtml,
                ],
            ],
            'table' => $tablehtml,
        ];
        if (!$previewunavailable) {
            $data['emailpreview'] = $this->export_email_preview($campaign, $recipient ?: null);
        }
        if (
            $stats->failed > 0 && $campaign->status !== campaign_manager::STATUS_SENDING
                && has_capability('local/mailwhistle:manage', $systemcontext)
        ) {
            $retryurl = new \moodle_url('/local/mailwhistle/index.php', [
                'tab' => 'send', 'action' => 'retryfailed', 'campaignid' => $this->id,
            ]);
            $data['retrybutton'] = $output->single_button($retryurl, get_string('retryfailed', 'local_mailwhistle'), 'post');
        }
        return $data;
    }

    /**
     * Select only a sent snapshot belonging to this campaign.
     *
     * @return \stdClass|false Recipient snapshot, or false when none matches.
     */
    private function get_preview_recipient(): \stdClass|false {
        global $DB;

        $conditions = ['campaignid' => $this->id, 'status' => recipient_manager::STATUS_SENT];
        if ($this->recipientid !== 0) {
            $conditions['id'] = $this->recipientid;
            return $DB->get_record('local_mailwhistle_recipients', $conditions);
        }
        $recipients = $DB->get_records('local_mailwhistle_recipients', $conditions, 'id ASC', '*', 0, 1);
        return reset($recipients);
    }

    /**
     * Reconstruct the saved content without sending or generating engagement events.
     *
     * @param \stdClass $campaign Saved campaign content.
     * @param \stdClass|null $recipient Sent recipient snapshot, when available.
     * @return array Preview data for the isolated email frame or plain-text fallback.
     */
    private function export_email_preview(\stdClass $campaign, ?\stdClass $recipient): array {
        $subject = (string) $campaign->subject;
        $html = (string) $campaign->bodyhtml;
        $text = (string) $campaign->bodytext;
        $recipientlabel = '';
        if ($recipient) {
            $subject = placeholder_manager::apply($subject, $recipient, false);
            $html = placeholder_manager::apply($html, $recipient, true);
            $text = placeholder_manager::apply($text, $recipient, false);
            $name = trim($recipient->firstname . ' ' . $recipient->lastname);
            $recipientlabel = trim($name . ' <' . $recipient->email . '>');
        }

        $body = self::prepare_preview_body(preview::prepare_html($html));
        if ($body === '' && trim($text) !== '') {
            $body = \html_writer::tag('pre', s($text), ['style' => 'white-space:pre-wrap;font:inherit;']);
        }
        $policy = "default-src 'none'; img-src http: https: data:; style-src 'unsafe-inline'; " .
            "base-uri 'none'; form-action 'none'";
        $document = '<!doctype html><html><head><meta charset="utf-8">' .
            \html_writer::empty_tag('meta', ['http-equiv' => 'Content-Security-Policy', 'content' => $policy]) .
            '<style>body{margin:0;padding:16px;font-family:Arial,sans-serif;}' .
            'img{max-width:100%;height:auto;}table{max-width:100%;}</style>' .
            '</head><body>' . $body . '</body></html>';
        $attachments = array_map(
            static fn($filename) => ['filename' => $filename],
            campaign_manager::decode_attachments($campaign->attachmentsjson ?? '')
        );

        return [
            'subject' => html_entity_decode(strip_tags(format_string($subject)), ENT_QUOTES, 'UTF-8'),
            'recipientlabel' => $recipientlabel,
            'hasrecipient' => $recipient !== null,
            'previewhtml' => $document,
            'hasbody' => $body !== '',
            'bodytext' => $text,
            'hasplaintext' => trim($text) !== '',
            'attachments' => $attachments,
            'hasattachments' => !empty($attachments),
        ];
    }

    /**
     * Remove links and copied tracking images so previewing cannot record engagement.
     *
     * @param string $html Sanitized email body.
     * @return string Non-navigable email body.
     */
    private static function prepare_preview_body(string $html): string {
        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>',
            LIBXML_NOWARNING | LIBXML_NOERROR
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return '';
        }
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//*[@href]') as $node) {
            $node->removeAttribute('href');
        }
        // Saved campaign content normally has no tracking. A pasted previous
        // email may already contain pixel URLs, including CSS or srcset values.
        $trackingpattern = '~(?:^|[/,\s("\'=])(?:pixel|click)\.php(?:[/?#]|$)~i';
        foreach ($xpath->query('//*') as $node) {
            foreach (iterator_to_array($node->attributes) as $attribute) {
                if (preg_match($trackingpattern, rawurldecode($attribute->value))) {
                    $node->removeAttributeNode($attribute);
                }
            }
        }
        foreach ($xpath->query('//style') as $node) {
            if (preg_match($trackingpattern, rawurldecode($node->textContent))) {
                $node->parentNode->removeChild($node);
            }
        }
        $body = $dom->getElementsByTagName('body')->item(0);
        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }
        return trim($result);
    }
}
