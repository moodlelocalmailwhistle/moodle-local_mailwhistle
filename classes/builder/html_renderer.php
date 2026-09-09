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
 * Render a builder JSON document to email-friendly HTML.
 *
 * Output is table/div markup with inline styles so it survives common
 * mail clients. This is not Moodle Mustache — those templates are for the
 * admin UI.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class html_renderer {
    /**
     * Render builder JSON to email-friendly HTML.
     *
     * @param string $json Normalised builder JSON.
     * @param string $background Template background color.
     * @return string Rendered email HTML.
     */
    public static function render(string $json, string $background = '#ffffff'): string {
        $builder = json_decode($json, true);
        if (!is_array($builder) || empty($builder['blocks']) || !is_array($builder['blocks'])) {
            return '';
        }

        $background = document::normalise_background($background);
        $output = \html_writer::start_tag('div', [
            'style' => 'max-width:640px;margin:0 auto;background:' . $background
                . ';font-family:Arial,Helvetica,sans-serif;color:#1f2933;',
        ]);

        foreach ($builder['blocks'] as $block) {
            if (is_array($block)) {
                $output .= self::render_block($block);
            }
        }

        $output .= \html_writer::end_tag('div');

        return $output;
    }

    /**
     * Render one builder block to HTML.
     *
     * @param array $block Normalised block.
     * @return string Rendered block HTML.
     */
    public static function render_block(array $block): string {
        return match ($block['type'] ?? '') {
            'header' => self::render_header($block),
            'text' => self::render_text($block),
            'button' => self::render_button($block),
            'image' => self::render_image($block),
            'logo' => self::render_logo($block),
            'highlight' => self::render_highlight($block),
            'social' => self::render_social($block),
            'columns' => self::render_columns($block),
            'divider' => self::render_divider(),
            'footer' => self::render_footer($block),
            default => '',
        };
    }

    /**
     * Render a header block.
     *
     * @param array $block Normalised block.
     * @return string
     */
    private static function render_header(array $block): string {
        $background = $block['background'] ?? '#1f4f82';
        $color = $block['color'] ?? '#ffffff';
        $title = format_string($block['title'] ?? '');
        $subtitle = format_string($block['subtitle'] ?? '');
        $padding = schema::bounded_int($block, 'padding', 32, 8, 56);
        $fontsize = schema::bounded_int($block, 'fontsize', 28, 16, 42);
        $align = schema::align($block, 'center');
        $fontfamily = schema::font_family($block);
        $content = \html_writer::tag('h1', $title, [
            'style' => 'margin:0 0 8px;font-size:' . $fontsize . 'px;line-height:1.25;color:' . $color . ';',
        ]);
        if ($subtitle !== '') {
            $content .= \html_writer::tag('p', $subtitle, [
                'style' => 'margin:0;font-size:16px;line-height:1.5;color:' . $color . ';',
            ]);
        }

        return \html_writer::div($content, '', [
            'style' => 'padding:' . $padding . 'px 28px;background:' . $background . ';color:' . $color
                . ';font-family:' . $fontfamily . ';text-align:' . $align . ';',
        ]);
    }

    /**
     * Render a text block.
     *
     * @param array $block Normalised block.
     * @return string
     */
    private static function render_text(array $block): string {
        $content = nl2br(s(format_string($block['content'] ?? '')));
        $padding = schema::bounded_int($block, 'padding', 24, 8, 48);
        $fontsize = schema::bounded_int($block, 'fontsize', 16, 11, 28);
        $align = schema::align($block);
        $fontfamily = schema::font_family($block);
        $color = $block['color'] ?? '#1f2933';

        return \html_writer::div($content, '', [
            'style' => 'padding:' . $padding . 'px 28px;font-family:' . $fontfamily . ';font-size:' . $fontsize
                . 'px;line-height:1.6;text-align:' . $align . ';color:' . $color . ';',
        ]);
    }

    /**
     * Render a button block.
     *
     * @param array $block Normalised block.
     * @return string
     */
    private static function render_button(array $block): string {
        $background = $block['background'] ?? '#1f4f82';
        $color = $block['color'] ?? '#ffffff';
        $label = format_string($block['label'] ?? get_string('template_builder_button_default', 'local_mailwhistle'));
        $url = !empty($block['url']) ? $block['url'] : '#';
        $padding = schema::bounded_int($block, 'padding', 24, 8, 48);
        $align = schema::align($block, 'center');
        $button = \html_writer::link($url, $label, [
            'style' => 'display:inline-block;padding:12px 20px;background:' . $background . ';color:' . $color
                . ';text-decoration:none;border-radius:4px;font-weight:bold;',
        ]);

        return \html_writer::div($button, '', [
            'style' => 'padding:8px 28px ' . $padding . 'px;text-align:' . $align . ';',
        ]);
    }

    /**
     * Render an image block.
     *
     * @param array $block Normalised block.
     * @return string
     */
    private static function render_image(array $block): string {
        $url = !empty($block['url']) ? $block['url'] : '';
        $padding = schema::bounded_int($block, 'padding', 24, 0, 48);
        $width = schema::bounded_int($block, 'width', 100, 10, 100);
        $align = schema::align($block, 'center');
        if ($url === '') {
            return \html_writer::div(
                s(get_string('template_builder_image_placeholder', 'local_mailwhistle')),
                '',
                [
                    'style' => 'margin:8px 28px ' . $padding . 'px;padding:48px 20px;border:1px dashed #c8d0da;'
                        . 'background:#f3f5f8;color:#52616f;text-align:center;',
                ]
            );
        }

        return \html_writer::div(
            \html_writer::empty_tag('img', [
                'src' => $url,
                'alt' => $block['alt'] ?? '',
                'width' => $width . '%',
                'style' => 'display:inline-block;width:' . $width . '%;max-width:100%;height:auto;border:0;',
            ]),
            '',
            ['style' => 'padding:8px 28px ' . $padding . 'px;text-align:' . $align . ';']
        );
    }

    /**
     * Render a logo block.
     *
     * @param array $block Normalised block.
     * @return string
     */
    private static function render_logo(array $block): string {
        $url = !empty($block['url']) ? $block['url'] : '';
        $padding = schema::bounded_int($block, 'padding', 18, 0, 40);
        $width = schema::bounded_int($block, 'width', 35, 10, 60);
        $align = schema::align($block, 'center');
        if ($url === '') {
            return \html_writer::div(
                s(get_string('template_builder_logo_placeholder', 'local_mailwhistle')),
                '',
                [
                    'style' => 'margin:8px 28px ' . $padding . 'px;padding:22px 20px;border:1px dashed #c8d0da;'
                        . 'background:#f8fafc;color:#52616f;text-align:center;',
                ]
            );
        }

        return \html_writer::div(
            \html_writer::empty_tag('img', [
                'src' => $url,
                'alt' => $block['alt'] ?? '',
                'width' => $width . '%',
                'style' => 'display:inline-block;width:' . $width . '%;max-width:100%;height:auto;border:0;',
            ]),
            '',
            ['style' => 'padding:8px 28px ' . $padding . 'px;text-align:' . $align . ';']
        );
    }

    /**
     * Render a highlight block.
     *
     * @param array $block Normalised block.
     * @return string
     */
    private static function render_highlight(array $block): string {
        $background = $block['background'] ?? '#f3f7fb';
        $color = $block['color'] ?? '#1f2933';
        $bordercolor = $block['bordercolor'] ?? '#1f4f82';
        $padding = schema::bounded_int($block, 'padding', 20, 8, 40);
        $fontsize = schema::bounded_int($block, 'fontsize', 16, 12, 24);
        $fontfamily = schema::font_family($block);
        $title = format_string($block['title'] ?? '');
        $content = nl2br(s(format_string($block['content'] ?? '')));
        $inner = '';
        if ($title !== '') {
            $inner .= \html_writer::tag('strong', $title, [
                'style' => 'display:block;margin-bottom:6px;font-size:' . $fontsize . 'px;',
            ]);
        }
        $inner .= \html_writer::div($content, '', [
            'style' => 'font-size:' . $fontsize . 'px;line-height:1.5;',
        ]);

        return \html_writer::div(
            \html_writer::div($inner, '', [
                'style' => 'padding:' . $padding . 'px;border-left:5px solid ' . $bordercolor
                    . ';background:' . $background . ';font-family:' . $fontfamily . ';color:' . $color . ';',
            ]),
            '',
            ['style' => 'padding:8px 28px 24px;']
        );
    }

    /**
     * Render a social-links block.
     *
     * @param array $block Normalised block.
     * @return string
     */
    private static function render_social(array $block): string {
        $padding = schema::bounded_int($block, 'padding', 20, 8, 40);
        $align = schema::align($block, 'center');
        $links = '';
        foreach ([1, 2, 3] as $number) {
            $label = trim((string) ($block['label' . $number] ?? ''));
            $url = trim((string) ($block['url' . $number] ?? ''));
            if ($label === '' && $url === '') {
                continue;
            }
            $links .= \html_writer::span(
                \html_writer::link($url !== '' ? $url : '#', $label !== '' ? format_string($label) : s($url), [
                    'style' => 'color:#1f4f82;text-decoration:underline;',
                ]),
                '',
                ['style' => 'display:inline-block;margin:0 8px 8px;']
            );
        }

        return \html_writer::div($links, '', [
            'style' => 'padding:8px 28px ' . $padding . 'px;text-align:' . $align
                . ';font-family:Arial,Helvetica,sans-serif;font-size:14px;',
        ]);
    }

    /**
     * Render a two-column block.
     *
     * @param array $block Normalised block.
     * @return string
     */
    private static function render_columns(array $block): string {
        $background = $block['background'] ?? '#ffffff';
        $color = $block['color'] ?? '#1f2933';
        $padding = schema::bounded_int($block, 'padding', 20, 8, 40);
        $fontsize = schema::bounded_int($block, 'fontsize', 15, 11, 22);
        $fontfamily = schema::font_family($block);
        $cellstyle = 'padding:' . $padding . 'px;font-family:' . $fontfamily . ';font-size:' . $fontsize
            . 'px;line-height:1.5;color:' . $color . ';vertical-align:top;';
        $left = \html_writer::tag('strong', format_string($block['lefttitle'] ?? ''), [
            'style' => 'display:block;margin-bottom:6px;',
        ]) . nl2br(s(format_string($block['leftcontent'] ?? '')));
        $right = \html_writer::tag('strong', format_string($block['righttitle'] ?? ''), [
            'style' => 'display:block;margin-bottom:6px;',
        ]) . nl2br(s(format_string($block['rightcontent'] ?? '')));
        $row = \html_writer::tag(
            'tr',
            \html_writer::tag('td', $left, ['width' => '50%', 'style' => $cellstyle])
                . \html_writer::tag('td', $right, ['width' => '50%', 'style' => $cellstyle])
        );

        return \html_writer::tag('table', $row, [
            'role' => 'presentation',
            'width' => '100%',
            'cellspacing' => '0',
            'cellpadding' => '0',
            'style' => 'background:' . $background . ';',
        ]);
    }

    /**
     * Render a divider block.
     *
     * @return string
     */
    private static function render_divider(): string {
        return \html_writer::div('', '', [
            'style' => 'height:1px;margin:8px 28px;background:#d8dee6;',
        ]);
    }

    /**
     * Render a footer block.
     *
     * @param array $block Normalised block.
     * @return string
     */
    private static function render_footer(array $block): string {
        $content = nl2br(s(format_string($block['content'] ?? '')));
        $padding = schema::bounded_int($block, 'padding', 22, 8, 48);
        $fontsize = schema::bounded_int($block, 'fontsize', 13, 10, 18);
        $align = schema::align($block, 'center');
        $fontfamily = schema::font_family($block);
        $background = $block['background'] ?? '#f3f5f8';
        $color = $block['color'] ?? '#52616f';

        return \html_writer::div($content, '', [
            'style' => 'padding:' . $padding . 'px 28px;background:' . $background . ';color:' . $color
                . ';font-family:' . $fontfamily . ';font-size:' . $fontsize . 'px;line-height:1.5;text-align:'
                . $align . ';',
        ]);
    }
}
