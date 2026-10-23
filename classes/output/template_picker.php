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
use local_mailwhistle\builder\document;
use local_mailwhistle\builder\preview;
use local_mailwhistle\manager\template_manager;

/**
 * Preview data for the campaign content form's template picker.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_picker implements renderable, templatable {
    /** @var int Campaign being edited. */
    private int $campaignid;

    /** @var int Currently selected template id, if any. */
    private int $selectedid;

    /** @var array|null Active templates, when already loaded by the form. */
    private ?array $templates;

    /**
     * Create the campaign template picker.
     *
     * @param int $campaignid Campaign being edited.
     * @param int $selectedid Currently selected template id, if any.
     * @param array|null $templates Active templates, or null to load them.
     */
    public function __construct(int $campaignid, int $selectedid = 0, ?array $templates = null) {
        $this->campaignid = $campaignid;
        $this->selectedid = $selectedid;
        $this->templates = $templates;
    }

    /**
     * Export this data so it can be used in a template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG;

        require_once($CFG->libdir . '/editorlib.php');
        $templates = $this->templates ?? template_manager::get_list(template_manager::FILTER_ACTIVE);
        if (empty($templates)) {
            return ['hastemplates' => false];
        }

        $options = [];
        foreach ($templates as $template) {
            $body = preview::prepare_html((string) $template->bodyhtml);
            $background = document::normalise_background((string) ($template->background ?? ''));
            $previewhtml = '<!doctype html><html><head><meta charset="utf-8"><style>' .
                'body{margin:0;padding:16px;background:' . $background . ';font-family:Arial,sans-serif;}' .
                'img{max-width:100%;height:auto;}table{max-width:100%;}' .
                '</style></head><body>' . $body . '</body></html>';
            $previewurl = new \moodle_url('/local/mailwhistle/index.php', [
                'tab' => 'templates', 'action' => 'preview', 'id' => $template->id,
            ]);
            $options[] = [
                'id' => (int) $template->id,
                'name' => html_entity_decode(strip_tags(format_string($template->name)), ENT_QUOTES, 'UTF-8'),
                'selected' => (int) $template->id === $this->selectedid,
                'body' => (string) $template->bodyhtml,
                'preview' => $previewhtml,
                'summary' => trim((string) $template->previewtext),
                'previewurl' => $previewurl->out(false),
            ];
        }

        $editor = editors_get_preferred_editor(FORMAT_HTML);
        $editortype = match (true) {
            $editor instanceof \tiny_texteditor => 'tiny',
            $editor instanceof \atto_texteditor => 'atto',
            $editor instanceof \textarea_texteditor => 'textarea',
            default => 'unknown',
        };

        return [
            'hastemplates' => true,
            'pickerid' => 'local-mailwhistle-template-picker-' . $this->campaignid,
            'editor' => $editortype,
            'options' => $options,
        ];
    }
}
