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
 * Static schema for the visual email builder.
 *
 * Holds block defaults, font stacks, and AMD strings. Parsing and validation
 * live in {@see document}; email HTML lives in {@see html_renderer}.
 *
 * @package   local_mailwhistle
 * @copyright 2024 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schema {
    /**
     * Empty builder document JSON.
     *
     * @return string JSON encoded builder document.
     */
    public static function default_json(): string {
        return json_encode(['blocks' => []]);
    }

    /**
     * Default values for all builder block types.
     *
     * @return array Builder defaults keyed by block type.
     */
    public static function block_defaults(): array {
        return [
            'header' => [
                'type' => 'header',
                'title' => 'Newsletter title',
                'subtitle' => 'Short supporting text',
                'background' => '#1f4f82',
                'color' => '#ffffff',
                'fontfamily' => 'arial',
                'fontsize' => 28,
                'align' => 'center',
                'padding' => 32,
            ],
            'logo' => [
                'type' => 'logo',
                'url' => '',
                'alt' => '',
                'width' => 35,
                'align' => 'center',
                'padding' => 18,
            ],
            'text' => [
                'type' => 'text',
                'content' => 'Write your message here.',
                'color' => '#1f2933',
                'fontfamily' => 'arial',
                'fontsize' => 16,
                'align' => 'left',
                'padding' => 24,
            ],
            'button' => [
                'type' => 'button',
                'label' => 'Learn more',
                'url' => '#',
                'background' => '#1f4f82',
                'color' => '#ffffff',
                'align' => 'center',
                'padding' => 24,
            ],
            'image' => [
                'type' => 'image',
                'url' => '',
                'alt' => '',
                'width' => 100,
                'align' => 'center',
                'padding' => 24,
            ],
            'highlight' => [
                'type' => 'highlight',
                'title' => 'Important update',
                'content' => 'Add the key message here.',
                'background' => '#f3f7fb',
                'color' => '#1f2933',
                'bordercolor' => '#1f4f82',
                'fontfamily' => 'arial',
                'fontsize' => 16,
                'padding' => 20,
            ],
            'social' => [
                'type' => 'social',
                'label1' => 'Website',
                'url1' => '#',
                'label2' => 'LinkedIn',
                'url2' => '#',
                'label3' => 'Instagram',
                'url3' => '#',
                'align' => 'center',
                'padding' => 20,
            ],
            'columns' => [
                'type' => 'columns',
                'lefttitle' => 'Left column',
                'leftcontent' => 'Add text here.',
                'righttitle' => 'Right column',
                'rightcontent' => 'Add text here.',
                'background' => '#ffffff',
                'color' => '#1f2933',
                'fontfamily' => 'arial',
                'fontsize' => 15,
                'padding' => 20,
            ],
            'divider' => [
                'type' => 'divider',
            ],
            'footer' => [
                'type' => 'footer',
                'content' => 'You are receiving this email from {{university}}.',
                'color' => '#52616f',
                'fontfamily' => 'arial',
                'fontsize' => 13,
                'align' => 'center',
                'padding' => 22,
                'background' => '#f3f5f8',
            ],
        ];
    }

    /**
     * Email-safe font family options used by the builder.
     *
     * @return array
     */
    public static function font_families(): array {
        return [
            'arial' => 'Arial,Helvetica,sans-serif',
            'verdana' => 'Verdana,Geneva,sans-serif',
            'georgia' => 'Georgia,serif',
            'times' => '"Times New Roman",Times,serif',
            'trebuchet' => '"Trebuchet MS",Arial,sans-serif',
        ];
    }

    /**
     * Email-safe font stack for a builder block.
     *
     * @param array $block Builder block.
     * @return string Font stack.
     */
    public static function font_family(array $block): string {
        $fonts = self::font_families();
        $key = clean_param((string) ($block['fontfamily'] ?? 'arial'), PARAM_ALPHA);

        return $fonts[$key] ?? $fonts['arial'];
    }

    /**
     * Safe text alignment value for a builder block.
     *
     * @param array $block Builder block.
     * @param string $default Default alignment.
     * @return string Alignment.
     */
    public static function align(array $block, string $default = 'left'): string {
        $align = clean_param((string) ($block['align'] ?? $default), PARAM_ALPHA);

        return in_array($align, ['left', 'center', 'right'], true) ? $align : $default;
    }

    /**
     * Bounded integer option from a builder block.
     *
     * @param array $block Builder block.
     * @param string $field Field name.
     * @param int $default Default value.
     * @param int $min Minimum value.
     * @param int $max Maximum value.
     * @return int Bounded value.
     */
    public static function bounded_int(array $block, string $field, int $default, int $min, int $max): int {
        $value = isset($block[$field]) ? (int) $block[$field] : $default;

        return min($max, max($min, $value));
    }

    /**
     * Localised strings for the builder JavaScript.
     *
     * @return array Builder strings.
     */
    public static function strings(): array {
        return [
            'builderheading' => get_string('template_builder_heading', 'local_mailwhistle'),
            'addheader' => get_string('template_builder_add_header', 'local_mailwhistle'),
            'addlogo' => get_string('template_builder_add_logo', 'local_mailwhistle'),
            'addtext' => get_string('template_builder_add_text', 'local_mailwhistle'),
            'addbutton' => get_string('template_builder_add_button', 'local_mailwhistle'),
            'addimage' => get_string('template_builder_add_image', 'local_mailwhistle'),
            'addhighlight' => get_string('template_builder_add_highlight', 'local_mailwhistle'),
            'addsocial' => get_string('template_builder_add_social', 'local_mailwhistle'),
            'addcolumns' => get_string('template_builder_add_columns', 'local_mailwhistle'),
            'adddivider' => get_string('template_builder_add_divider', 'local_mailwhistle'),
            'addfooter' => get_string('template_builder_add_footer', 'local_mailwhistle'),
            'remove' => get_string('delete'),
            'drag' => get_string('template_builder_drag', 'local_mailwhistle'),
            'title' => get_string('template_builder_title', 'local_mailwhistle'),
            'subtitle' => get_string('template_builder_subtitle', 'local_mailwhistle'),
            'content' => get_string('template_builder_content', 'local_mailwhistle'),
            'label' => get_string('template_builder_label', 'local_mailwhistle'),
            'url' => get_string('template_builder_url', 'local_mailwhistle'),
            'alt' => get_string('template_builder_alt', 'local_mailwhistle'),
            'background' => get_string('template_builder_background', 'local_mailwhistle'),
            'color' => get_string('template_builder_color', 'local_mailwhistle'),
            'fontfamily' => get_string('template_builder_fontfamily', 'local_mailwhistle'),
            'fontsize' => get_string('template_builder_fontsize', 'local_mailwhistle'),
            'align' => get_string('template_builder_align', 'local_mailwhistle'),
            'padding' => get_string('template_builder_padding', 'local_mailwhistle'),
            'width' => get_string('template_builder_width', 'local_mailwhistle'),
            'bordercolor' => get_string('template_builder_bordercolor', 'local_mailwhistle'),
            'label1' => get_string('template_builder_label1', 'local_mailwhistle'),
            'label2' => get_string('template_builder_label2', 'local_mailwhistle'),
            'label3' => get_string('template_builder_label3', 'local_mailwhistle'),
            'url1' => get_string('template_builder_url1', 'local_mailwhistle'),
            'url2' => get_string('template_builder_url2', 'local_mailwhistle'),
            'url3' => get_string('template_builder_url3', 'local_mailwhistle'),
            'lefttitle' => get_string('template_builder_lefttitle', 'local_mailwhistle'),
            'leftcontent' => get_string('template_builder_leftcontent', 'local_mailwhistle'),
            'righttitle' => get_string('template_builder_righttitle', 'local_mailwhistle'),
            'rightcontent' => get_string('template_builder_rightcontent', 'local_mailwhistle'),
            'empty' => get_string('template_builder_empty', 'local_mailwhistle'),
            'imageplaceholder' => get_string('template_builder_image_placeholder', 'local_mailwhistle'),
            'logoplaceholder' => get_string('template_builder_logo_placeholder', 'local_mailwhistle'),
        ];
    }
}
