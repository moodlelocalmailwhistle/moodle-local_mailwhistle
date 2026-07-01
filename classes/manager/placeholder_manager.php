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
 * Personalisation placeholder engine for Mail Whistle campaigns.
 *
 * Replaces {{token}} placeholders in a campaign's subject and body with each
 * recipient's snapshotted data at send time. Tokens are matched
 * case-insensitively; unknown tokens are replaced with an empty string so no
 * raw {{...}} ever reaches the recipient.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placeholder_manager {
    /**
     * Build the token => value map for one recipient.
     *
     * Values come from the recipient snapshot (frozen at send time), so a later
     * profile edit never changes an already-sent campaign.
     *
     * @param \stdClass $recipient A local_mailwhistle_recipients row.
     * @return array<string, string> Lower-cased token => replacement value.
     */
    public static function build_map(\stdClass $recipient): array {
        $firstname = trim((string) ($recipient->firstname ?? ''));
        $lastname  = trim((string) ($recipient->lastname ?? ''));
        $email     = (string) ($recipient->email ?? '');

        return [
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'fullname'  => trim($firstname . ' ' . $lastname),
            'email'     => $email,
        ];
    }

    /**
     * Replace {{token}} placeholders in text with a recipient's values.
     *
     * @param string $text Subject or body text containing {{tokens}}.
     * @param \stdClass $recipient A local_mailwhistle_recipients row.
     * @param bool $htmlescape Escape values for an HTML context (bodyhtml); pass
     *                         false for the plain-text subject / bodytext.
     * @return string Text with placeholders resolved.
     */
    public static function apply(string $text, \stdClass $recipient, bool $htmlescape = true): string {
        if ($text === '' || strpos($text, '{{') === false) {
            return $text;
        }

        $map = self::build_map($recipient);

        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z][a-zA-Z0-9_]*)\s*\}\}/',
            static function (array $matches) use ($map, $htmlescape): string {
                $key = \core_text::strtolower($matches[1]);
                $value = $map[$key] ?? '';
                return $htmlescape ? s($value) : $value;
            },
            $text
        );
    }
}
