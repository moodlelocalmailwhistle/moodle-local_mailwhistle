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
use local_mailwhistle\manager\template_manager;

/**
 * "Use a template" picker shown above the campaign content step.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_picker implements renderable, templatable {
    /** @var int Campaign being edited. */
    private int $campaignid;

    /** @var int Currently selected template id, if any. */
    private int $selectedid;

    /**
     * Create the campaign template picker.
     *
     * @param int $campaignid Campaign being edited.
     * @param int $selectedid Currently selected template id, if any.
     */
    public function __construct(int $campaignid, int $selectedid = 0) {
        $this->campaignid = $campaignid;
        $this->selectedid = $selectedid;
    }

    /**
     * Export this data so it can be used in a template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $templates = template_manager::get_list(template_manager::FILTER_ACTIVE);
        if (empty($templates)) {
            return ['hastemplates' => false];
        }

        $options = [];
        foreach ($templates as $template) {
            $options[] = [
                'id' => (int) $template->id,
                'name' => format_string($template->name),
                'selected' => (int) $template->id === $this->selectedid,
            ];
        }

        $actionurl = new \moodle_url('/local/mailwhistle/campaign_edit.php');

        return [
            'hastemplates' => true,
            'actionurl' => $actionurl->out(false),
            'campaignid' => $this->campaignid,
            'options' => $options,
        ];
    }
}
