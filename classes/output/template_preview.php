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

/**
 * Full-page template preview.
 *
 * @package   local_mailwhistle
 * @copyright 2024 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_preview implements renderable, templatable {
    /** @var \stdClass Template record. */
    private \stdClass $template;

    /** @var bool Whether the current user can manage templates. */
    private bool $canmanage;

    /**
     * Create a full-page template preview.
     *
     * @param \stdClass $template Template record.
     * @param bool $canmanage Whether the current user can manage templates.
     */
    public function __construct(\stdClass $template, bool $canmanage) {
        $this->template = $template;
        $this->canmanage = $canmanage;
    }

    /**
     * Export this data so it can be used in a template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $listurl = new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates']);
        $editurl = new \moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'templates',
            'action' => 'edit',
            'id' => $this->template->id,
        ]);
        $exporturl = new \moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'templates',
            'action' => 'export',
            'id' => $this->template->id,
        ]);
        $bodyhtml = preview::prepare_html((string) $this->template->bodyhtml);
        $previewtext = trim((string) $this->template->previewtext);

        return [
            'name' => format_string($this->template->name),
            'listurl' => $listurl->out(false),
            'canmanage' => $this->canmanage,
            'editurl' => $editurl->out(false),
            'exporturl' => $exporturl->out(false),
            'haspreviewtext' => $previewtext !== '',
            'previewtext' => format_string($previewtext),
            'hasbody' => $bodyhtml !== '',
            'bodyhtml' => $bodyhtml,
            'background' => document::normalise_background((string) ($this->template->background ?? '')),
        ];
    }
}
