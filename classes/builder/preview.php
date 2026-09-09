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

namespace local_mailwhistle\builder;

/**
 * Prepare stored template HTML for on-page Moodle previews.
 *
 * Full email documents often include doctype/html/head/body wrappers. The
 * Moodle page should preview the email body only, then let format_text() clean
 * the resulting HTML before it is displayed.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preview {
    /**
     * Prepare template HTML for on-page preview rendering.
     *
     * @param string $html Stored template HTML.
     * @return string Cleaned preview HTML.
     */
    public static function prepare_html(string $html): string {
        $html = self::normalise($html);
        if ($html === '') {
            return '';
        }

        if (preg_match('/&lt;\s*(?:!doctype|html|head|body)\b/i', $html)) {
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $body = self::extract_body($html);
        if ($body === '') {
            $body = self::remove_document_shell($html);
        }

        $body = self::normalise($body);
        if ($body === '') {
            return '';
        }

        return format_text($body, FORMAT_HTML, ['context' => \context_system::instance()]);
    }

    /**
     * Extract inner body HTML from a complete HTML document.
     *
     * @param string $html HTML document or fragment.
     * @return string Body inner HTML, or an empty string when no body tag exists.
     */
    public static function extract_body(string $html): string {
        if (!preg_match('/<\s*body\b/i', $html)) {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return '';
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }

        $innerhtml = '';
        foreach ($body->childNodes as $child) {
            $innerhtml .= $dom->saveHTML($child);
        }

        return $innerhtml;
    }

    /**
     * Remove document-level HTML wrappers from pasted email source.
     *
     * @param string $html HTML document or fragment.
     * @return string HTML without doctype/html/head/body wrappers where present.
     */
    public static function remove_document_shell(string $html): string {
        $html = preg_replace('/<!doctype[^>]*>/i', '', $html);
        $html = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $html);
        $html = preg_replace('/<\/?(?:html|body)\b[^>]*>/i', '', $html);

        return $html ?? '';
    }

    /**
     * Normalize HTML text before thumbnail rendering.
     *
     * @param string $html HTML document or fragment.
     * @return string Normalized HTML.
     */
    public static function normalise(string $html): string {
        $html = str_replace(["\xC2\xA0", "\xC3\x82"], [' ', ''], $html);
        $html = preg_replace('/\s+/', ' ', $html);

        return trim($html ?? '');
    }
}
