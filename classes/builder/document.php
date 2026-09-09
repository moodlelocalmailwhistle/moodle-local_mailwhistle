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
 * Validate and normalise visual-builder JSON documents.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document {
    /**
     * Normalise the template-level email background.
     *
     * @param string $background Submitted background color.
     * @return string Safe hex color.
     */
    public static function normalise_background(string $background): string {
        $background = trim($background);

        return preg_match('/^#[0-9a-f]{6}$/i', $background) ? $background : '#ffffff';
    }

    /**
     * Normalise the selected template editor mode.
     *
     * @param string $mode Submitted editor mode.
     * @return string Normalised mode.
     */
    public static function normalise_editor_mode(string $mode): string {
        return $mode === 'builder' ? 'builder' : 'html';
    }

    /**
     * Normalise submitted builder JSON.
     *
     * @param string $json Submitted builder JSON.
     * @return string Normalised JSON.
     */
    public static function normalise_json(string $json): string {
        $builder = json_decode($json, true);
        if (!is_array($builder) || !isset($builder['blocks']) || !is_array($builder['blocks'])) {
            return schema::default_json();
        }

        $blocks = [];
        foreach ($builder['blocks'] as $block) {
            if (!is_array($block)) {
                continue;
            }

            $normalised = self::normalise_block($block);
            if ($normalised !== null) {
                $blocks[] = $normalised;
            }

            if (count($blocks) >= 50) {
                break;
            }
        }

        return json_encode(['blocks' => $blocks]);
    }

    /**
     * Validate submitted builder JSON before saving.
     *
     * @param string $json Submitted builder JSON.
     * @return array Validation error messages.
     */
    public static function validate_json(string $json): array {
        $builder = json_decode($json, true);
        if (!is_array($builder) || !isset($builder['blocks']) || !is_array($builder['blocks'])) {
            return [get_string('template_builder_invalid', 'local_mailwhistle')];
        }

        if (count($builder['blocks']) > 50) {
            return [get_string('template_builder_too_many_blocks', 'local_mailwhistle')];
        }

        $errors = [];
        $defaults = schema::block_defaults();
        $fonts = schema::font_families();
        foreach ($builder['blocks'] as $index => $block) {
            if (!is_array($block)) {
                $errors[] = get_string('template_builder_invalid_block', 'local_mailwhistle', $index + 1);
                continue;
            }

            $type = clean_param((string) ($block['type'] ?? ''), PARAM_ALPHA);
            if (!array_key_exists($type, $defaults)) {
                $errors[] = get_string('template_builder_invalid_block', 'local_mailwhistle', $index + 1);
                continue;
            }

            foreach (['background', 'color', 'bordercolor'] as $field) {
                if (isset($block[$field]) && !preg_match('/^#[0-9a-f]{6}$/i', (string) $block[$field])) {
                    $errors[] = get_string('template_builder_invalid_colour', 'local_mailwhistle', $index + 1);
                }
            }

            foreach (['url', 'url1', 'url2', 'url3'] as $field) {
                if (isset($block[$field]) && !self::is_valid_url((string) $block[$field])) {
                    $errors[] = get_string('template_builder_invalid_url', 'local_mailwhistle', $index + 1);
                }
            }

            if (isset($block['fontfamily']) && !array_key_exists((string) $block['fontfamily'], $fonts)) {
                $errors[] = get_string('template_builder_invalid_font', 'local_mailwhistle', $index + 1);
            }

            if (isset($block['align']) && !in_array((string) $block['align'], ['left', 'center', 'right'], true)) {
                $errors[] = get_string('template_builder_invalid_align', 'local_mailwhistle', $index + 1);
            }

            $ranges = [
                'fontsize' => [10, 42],
                'padding' => [0, 56],
                'width' => [10, 100],
            ];
            foreach ($ranges as $field => $range) {
                if (isset($block[$field])) {
                    $value = filter_var($block[$field], FILTER_VALIDATE_INT);
                    if ($value === false || $value < $range[0] || $value > $range[1]) {
                        $errors[] = get_string('template_builder_invalid_number', 'local_mailwhistle', $index + 1);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a URL-like builder value.
     *
     * @param string $url URL value.
     * @return bool Whether the value is acceptable.
     */
    public static function is_valid_url(string $url): bool {
        $url = trim($url);
        if ($url === '' || $url === '#') {
            return true;
        }

        if (str_starts_with($url, '{{') && str_ends_with($url, '}}')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Normalise a single builder block.
     *
     * @param array $block Submitted block.
     * @return array|null Normalised block, or null when unsupported.
     */
    public static function normalise_block(array $block): ?array {
        $type = clean_param((string) ($block['type'] ?? ''), PARAM_ALPHA);
        $defaults = schema::block_defaults();
        if (!array_key_exists($type, $defaults)) {
            return null;
        }

        $normalised = $defaults[$type];

        $textfields = [
            'title', 'subtitle', 'content', 'label', 'alt', 'label1', 'label2', 'label3',
            'lefttitle', 'leftcontent', 'righttitle', 'rightcontent',
        ];
        foreach ($textfields as $field) {
            if (isset($block[$field])) {
                $normalised[$field] = shorten_text(clean_param((string) $block[$field], PARAM_TEXT), 2000);
            }
        }

        foreach (['url', 'url1', 'url2', 'url3'] as $field) {
            if (isset($block[$field])) {
                $normalised[$field] = clean_param((string) $block[$field], PARAM_URL);
            }
        }

        if (isset($block['fontfamily'])) {
            $fontfamily = clean_param((string) $block['fontfamily'], PARAM_ALPHA);
            if (array_key_exists($fontfamily, schema::font_families())) {
                $normalised['fontfamily'] = $fontfamily;
            }
        }

        if (isset($block['align'])) {
            $align = clean_param((string) $block['align'], PARAM_ALPHA);
            if (in_array($align, ['left', 'center', 'right'], true)) {
                $normalised['align'] = $align;
            }
        }

        foreach (['background', 'color', 'bordercolor'] as $field) {
            if (isset($block[$field]) && preg_match('/^#[0-9a-f]{6}$/i', (string) $block[$field])) {
                $normalised[$field] = (string) $block[$field];
            }
        }

        if (isset($block['fontsize'])) {
            $normalised['fontsize'] = min(42, max(10, (int) $block['fontsize']));
        }

        if (isset($block['padding'])) {
            $normalised['padding'] = min(56, max(0, (int) $block['padding']));
        }

        if (isset($block['width'])) {
            $normalised['width'] = min(100, max(10, (int) $block['width']));
        }

        return $normalised;
    }
}
