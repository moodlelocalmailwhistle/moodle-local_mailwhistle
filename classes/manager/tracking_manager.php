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

namespace local_mailwhistle\manager;

/**
 * Tracking manager for Mail Whistle campaign opens and clicks.
 *
 * Builds and verifies HMAC-signed, per-recipient tokens for the anonymous
 * pixel and click endpoints (no Moodle session is available to an email
 * client). Open tokens bind campaign+recipient; click tokens additionally bind
 * the target URL so a valid token cannot be reused to redirect elsewhere (no
 * open redirect). Events are stored in local_mailwhistle_tracking; opens are
 * recorded once per recipient, clicks once per distinct link.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tracking_manager {
    /** @var string Open event type. */
    public const EVENT_OPEN = 'open';

    /** @var string Click event type. */
    public const EVENT_CLICK = 'click';

    /** @var int Signature length (hex chars) kept from the HMAC. */
    private const SIGLEN = 32;

    /**
     * Compute the HMAC signature over a payload using the site secret.
     *
     * @param string $payload The data to sign.
     * @return string Hex signature (truncated).
     */
    private static function sign(string $payload): string {
        return substr(hash_hmac('sha256', $payload, get_site_identifier()), 0, self::SIGLEN);
    }

    /**
     * Build an open-tracking token for a recipient.
     *
     * @param int $campaignid The campaign.
     * @param int $recipientid The recipient row id.
     * @return string Token of the form "campaignid-recipientid-signature".
     */
    public static function make_open_token(int $campaignid, int $recipientid): string {
        $payload = self::EVENT_OPEN . ":$campaignid:$recipientid";
        return "$campaignid-$recipientid-" . self::sign($payload);
    }

    /**
     * Verify an open token.
     *
     * @param string $token The token from the pixel URL.
     * @return \stdClass|null Object with campaignid/recipientid, or null if invalid.
     */
    public static function verify_open_token(string $token): ?\stdClass {
        $parts = explode('-', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$campaignid, $recipientid, $sig] = $parts;
        $campaignid = (int) $campaignid;
        $recipientid = (int) $recipientid;
        $payload = self::EVENT_OPEN . ":$campaignid:$recipientid";
        if (!hash_equals(self::sign($payload), (string) $sig)) {
            return null;
        }
        return (object) ['campaignid' => $campaignid, 'recipientid' => $recipientid];
    }

    /**
     * Build a click-tracking token bound to a target URL.
     *
     * @param int $campaignid The campaign.
     * @param int $recipientid The recipient row id.
     * @param string $target The destination URL.
     * @return string Token of the form "campaignid-recipientid-signature".
     */
    public static function make_click_token(int $campaignid, int $recipientid, string $target): string {
        $payload = self::EVENT_CLICK . ":$campaignid:$recipientid:$target";
        return "$campaignid-$recipientid-" . self::sign($payload);
    }

    /**
     * Verify a click token against the target it was issued for.
     *
     * @param string $token The token from the click URL.
     * @param string $target The target URL supplied in the same request.
     * @return \stdClass|null Object with campaignid/recipientid, or null if invalid.
     */
    public static function verify_click_token(string $token, string $target): ?\stdClass {
        $parts = explode('-', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$campaignid, $recipientid, $sig] = $parts;
        $campaignid = (int) $campaignid;
        $recipientid = (int) $recipientid;
        $payload = self::EVENT_CLICK . ":$campaignid:$recipientid:$target";
        if (!hash_equals(self::sign($payload), (string) $sig)) {
            return null;
        }
        return (object) ['campaignid' => $campaignid, 'recipientid' => $recipientid];
    }

    /**
     * Record an open event (first open per recipient only).
     *
     * @param int $campaignid The campaign.
     * @param int $recipientid The recipient row id.
     * @return void
     */
    public static function record_open(int $campaignid, int $recipientid): void {
        self::record_event($campaignid, $recipientid, self::EVENT_OPEN, '');
    }

    /**
     * Record a click event for a target URL (deduped per distinct link).
     *
     * @param int $campaignid The campaign.
     * @param int $recipientid The recipient row id.
     * @param string $target The clicked URL.
     * @return void
     */
    public static function record_click(int $campaignid, int $recipientid, string $target): void {
        self::record_event($campaignid, $recipientid, self::EVENT_CLICK, $target);
    }

    /**
     * Insert a tracking event, ignoring duplicates via the UNIQUE index.
     *
     * @param int $campaignid The campaign.
     * @param int $recipientid The recipient row id.
     * @param string $eventtype Open or click.
     * @param string $target The target URL (empty for opens).
     * @return void
     */
    private static function record_event(int $campaignid, int $recipientid, string $eventtype, string $target): void {
        global $DB;

        $targethash = $target === '' ? '' : sha1($target);

        // The UNIQUE (campaignid,recipientid,eventtype,targethash) index enforces
        // first-open / one-per-link; skip if already recorded.
        $exists = $DB->record_exists('local_mailwhistle_tracking', [
            'campaignid' => $campaignid,
            'recipientid' => $recipientid,
            'eventtype' => $eventtype,
            'targethash' => $targethash,
        ]);
        if ($exists) {
            return;
        }

        $DB->insert_record('local_mailwhistle_tracking', (object) [
            'campaignid' => $campaignid,
            'recipientid' => $recipientid,
            'eventtype' => $eventtype,
            'targethash' => $targethash,
            'targeturl' => $target === '' ? null : $target,
            'timecreated' => time(),
        ]);
    }

    /**
     * Rewrite a campaign body for tracking: wrap links and inject the pixel.
     *
     * Only http(s) anchors are rewritten (mailto:, anchors, etc. are left
     * alone). A single 1x1 tracking pixel is appended.
     *
     * @param string $html The campaign body HTML.
     * @param int $campaignid The campaign.
     * @param int $recipientid The recipient row id.
     * @return string The prepared HTML.
     */
    public static function prepare_body(string $html, int $campaignid, int $recipientid): string {
        $rewritten = preg_replace_callback(
            '/href="(https?:\/\/[^"]+)"/i',
            static function (array $m) use ($campaignid, $recipientid): string {
                $url = self::click_url($campaignid, $recipientid, $m[1]);
                return 'href="' . $url . '"';
            },
            $html
        );

        return $rewritten . self::pixel_img($campaignid, $recipientid);
    }

    /**
     * Build the open-tracking pixel URL.
     *
     * @param int $campaignid The campaign.
     * @param int $recipientid The recipient row id.
     * @return \moodle_url
     */
    public static function pixel_url(int $campaignid, int $recipientid): \moodle_url {
        return new \moodle_url('/local/mailwhistle/pixel.php', [
            't' => self::make_open_token($campaignid, $recipientid),
        ]);
    }

    /**
     * Build a click-tracking redirect URL for a target.
     *
     * @param int $campaignid The campaign.
     * @param int $recipientid The recipient row id.
     * @param string $target The destination URL.
     * @return string The click endpoint URL.
     */
    public static function click_url(int $campaignid, int $recipientid, string $target): string {
        $url = new \moodle_url('/local/mailwhistle/click.php', [
            't' => self::make_click_token($campaignid, $recipientid, $target),
            'url' => $target,
        ]);
        return $url->out(false);
    }

    /**
     * Build the tracking pixel <img> tag.
     *
     * @param int $campaignid The campaign.
     * @param int $recipientid The recipient row id.
     * @return string The img HTML.
     */
    private static function pixel_img(int $campaignid, int $recipientid): string {
        $src = self::pixel_url($campaignid, $recipientid)->out(false);
        return '<img src="' . $src . '" alt="" width="1" height="1" style="display:none" />';
    }
}
