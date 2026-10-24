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
 * Recipient-controlled campaign email preferences.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscription_manager {
    /**
     * Build a purpose-specific signature bound to an immutable recipient identity.
     *
     * @param \stdClass $recipient Recipient snapshot, including effective email.
     * @return string HMAC signature.
     */
    private static function signature(\stdClass $recipient): string {
        $payload = implode(':', [
            'mailwhistle:unsubscribe:v1', $recipient->id, $recipient->campaignid, $recipient->userid, $recipient->email,
        ]);
        return hash_hmac('sha256', $payload, get_site_identifier());
    }

    /**
     * Build the unsubscribe confirmation URL for a real delivery.
     *
     * @param \stdClass $recipient Recipient snapshot.
     * @return \moodle_url Signed URL.
     */
    public static function unsubscribe_url(\stdClass $recipient): \moodle_url {
        return new \moodle_url('/local/mailwhistle/unsubscribe.php', [
            't' => $recipient->id . '-' . self::signature($recipient),
        ]);
    }

    /**
     * Preview links cannot opt anyone out, even when followed or submitted.
     *
     * @return \moodle_url Inert preview URL.
     */
    public static function preview_url(): \moodle_url {
        return new \moodle_url('/local/mailwhistle/unsubscribe.php', ['preview' => 1]);
    }

    /**
     * Validate a link without changing preferences or revealing account data.
     *
     * Old delivered links still work after the user's current email changes.
     * Removing the recipient snapshot, campaign, or user invalidates the link.
     *
     * @param string $token Signed recipient token.
     * @return \stdClass|null Valid recipient snapshot, or null.
     */
    public static function verify_token(string $token): ?\stdClass {
        global $DB;

        if (!preg_match('/^([1-9][0-9]*)-([a-f0-9]{64})$/D', $token, $matches)) {
            return null;
        }
        if ((string) (int) $matches[1] !== $matches[1]) {
            return null;
        }
        $recipient = $DB->get_record('local_mailwhistle_recipients', ['id' => $matches[1]]);
        if (!$recipient || !hash_equals(self::signature($recipient), $matches[2])) {
            return null;
        }
        if (
            !$DB->record_exists('local_mailwhistle_campaigns', ['id' => $recipient->campaignid])
                || !$DB->record_exists('user', ['id' => $recipient->userid, 'deleted' => 0])
        ) {
            return null;
        }
        return $recipient;
    }

    /**
     * Check the explicit global campaign opt-out, independent of notification settings.
     *
     * @param int $userid Moodle user id.
     * @return bool Whether the user has opted out.
     */
    public static function is_unsubscribed(int $userid): bool {
        global $DB;
        return $DB->record_exists('local_mailwhistle_unsubscribes', ['userid' => $userid, 'scope' => 'global']);
    }

    /**
     * Apply a verified anonymous unsubscribe request idempotently.
     *
     * The controller must require POST. The purpose-specific signed token is the
     * authority; this operation deliberately does not require a Moodle session.
     *
     * @param string $token Signed recipient token.
     * @return bool Whether the request was valid and applied.
     */
    public static function unsubscribe(string $token): bool {
        $recipient = self::verify_token($token);
        if (!$recipient) {
            return false;
        }
        self::set_subscription((int) $recipient->userid, false);
        return true;
    }

    /**
     * Change an opt-out after authorisation by a signed token or the logged-in owner.
     *
     * Callers must never use this to override another user's choice. Subscribing
     * only removes this opt-out; Moodle notification preferences still apply.
     *
     * @param int $userid User owning the preference.
     * @param bool $subscribed Whether campaign email is allowed.
     * @return void
     */
    public static function set_subscription(int $userid, bool $subscribed): void {
        global $DB;

        $lock = \core\lock\lock_config::get_lock_factory('local_mailwhistle')->get_lock('subscription:' . $userid, 10);
        if (!$lock) {
            throw new \moodle_exception('subscription_busy', 'local_mailwhistle');
        }
        try {
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
            if (isguestuser($user)) {
                throw new \moodle_exception('invalidrequest');
            }
            if ($subscribed) {
                $DB->delete_records('local_mailwhistle_unsubscribes', ['userid' => $userid, 'scope' => 'global']);
            } else if (!self::is_unsubscribed($userid)) {
                $DB->insert_record('local_mailwhistle_unsubscribes', (object) [
                    'userid' => $userid,
                    'email' => $user->email,
                    'scope' => 'global',
                    'scopeid' => 0,
                    'timecreated' => time(),
                ]);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Do not track clicks on the recipient's privacy controls.
     *
     * @param string $url Decoded link target.
     * @return bool Whether this is the local unsubscribe endpoint.
     */
    public static function is_unsubscribe_url(string $url): bool {
        $base = (new \moodle_url('/local/mailwhistle/unsubscribe.php'))->out(false);
        return $url === $base || str_starts_with($url, $base . '?');
    }

    /**
     * Replace privacy links copied from an earlier delivery without rewriting the author's HTML.
     *
     * Only actual local link targets are replaced. External URLs containing a local
     * URL in their query, visible HTML text, comments and other attributes remain intact.
     *
     * @param string $body Saved HTML or plain text.
     * @param string $url Current recipient URL, inert preview URL or unsubscribe placeholder.
     * @param bool $html Whether the body contains HTML.
     * @return string Body with copied unsubscribe targets replaced.
     */
    public static function replace_unsubscribe_links(string $body, string $url, bool $html): string {
        if (!$html) {
            return (string) preg_replace_callback('~https?://[^\s<>"\']+~i', static function ($match) use ($url): string {
                $target = rtrim($match[0], '.,;:!?)]}');
                return self::is_unsubscribe_url($target) ? $url . substr($match[0], strlen($target)) : $match[0];
            }, $body);
        }

        // Tokenise complete tags so apparent anchors inside comments or quoted attributes are not changed.
        $tags = '~<!--.*?-->|<(script|style|textarea)\b[^>]*>.*?</\1\s*>|<(?:[^\'"<>]|"[^"]*"|\'[^\']*\')*>~is';
        $attributes = '~(\s+)([^\s=/>]+)(\s*=\s*)(?:(["\'])(.*?)\4|([^\s>"\']+))~s';
        return (string) preg_replace_callback($tags, static function ($tag) use ($url, $attributes): string {
            if (!preg_match('/^<a\s/i', $tag[0])) {
                return $tag[0];
            }
            return (string) preg_replace_callback($attributes, static function ($attribute) use ($url): string {
                if (strtolower($attribute[2]) !== 'href') {
                    return $attribute[0];
                }
                $quote = $attribute[4];
                $target = $quote !== '' ? $attribute[5] : $attribute[6];
                if (!self::is_unsubscribe_url(html_entity_decode($target, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return $attribute[0];
                }
                return $attribute[1] . $attribute[2] . $attribute[3] . $quote . s($url) . $quote;
            }, $tag[0]);
        }, $body);
    }

    /**
     * Include an unsubscribe link even when the author did not add a placeholder.
     *
     * @param string $body Personalised HTML or plain text.
     * @param string $url Real or inert preview link.
     * @param bool $html Whether to generate an HTML footer.
     * @return string Body with an accessible opt-out link.
     */
    public static function add_footer(string $body, string $url, bool $html): string {
        if (!$html && str_contains($body, $url)) {
            return $body;
        }
        if ($html && self::has_unsubscribe_link($body, $url)) {
            return $body;
        }
        $label = get_string('subscription_unsubscribe', 'local_mailwhistle');
        $footer = $html ? \html_writer::tag('p', \html_writer::link($url, $label)) : "\n\n" . $label . ': ' . $url;
        if ($html && preg_match('/<\/body\s*>/i', $body)) {
            return (string) preg_replace_callback('/<\/body\s*>/i', static fn($match) => $footer . $match[0], $body, 1);
        }
        return $body . $footer;
    }

    /**
     * Find a real labelled link, ignoring comments, scripts and explicitly hidden content.
     *
     * The parsed copy is never serialised back into the author's message.
     *
     * @param string $body Original HTML message.
     * @param string $url Exact decoded unsubscribe target.
     * @return bool Whether a usable unsubscribe link is already present.
     */
    private static function has_unsubscribe_link(string $body, string $url): bool {
        if ($body === '') {
            return false;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            if (!$document->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return false;
            }
            $xpath = new \DOMXPath($document);
            foreach ($xpath->query('//head|//script|//style|//template|//*[@hidden]|//*[@aria-hidden="true"]') as $node) {
                $node->parentNode?->removeChild($node);
            }
            foreach ($xpath->query('//*[@style]') as $node) {
                if (
                    preg_match(
                        '/(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*(?:hidden|collapse))\b/i',
                        $node->getAttribute('style')
                    )
                ) {
                    $node->parentNode?->removeChild($node);
                }
            }
            foreach ($document->getElementsByTagName('a') as $anchor) {
                $haslabel = preg_replace('/[\s\p{Z}\x{200B}\x{FEFF}]+/u', '', $anchor->textContent) !== '';
                // DOM has already decoded entities in the attribute once.
                if ($haslabel && $anchor->getAttribute('href') === $url) {
                    return true;
                }
            }
            return false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
