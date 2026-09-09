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
 * Templates tab card grid.
 *
 * @package   local_mailwhistle
 * @copyright 2024 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class templates_overview implements renderable, templatable {
    /** @var string Active visibility filter. */
    private string $filter;

    /** @var bool Whether the current user can manage templates. */
    private bool $canmanage;

    /**
     * Create the templates overview.
     *
     * @param string $filter Active visibility filter.
     * @param bool $canmanage Whether the current user can manage templates.
     */
    public function __construct(string $filter, bool $canmanage) {
        $this->filter = $filter;
        $this->canmanage = $canmanage;
    }

    /**
     * Export this data so it can be used in a template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $templates = template_manager::get_list($this->filter);
        $cards = [];
        foreach ($templates as $template) {
            $cards[] = (new template_card($template, $this->canmanage))->export_for_template($output);
        }

        $filters = [];
        foreach ([template_manager::FILTER_ACTIVE, template_manager::FILTER_ARCHIVED, template_manager::FILTER_ALL] as $filter) {
            $url = new \moodle_url('/local/mailwhistle/index.php', [
                'tab' => 'templates',
                'filter' => $filter,
            ]);
            $filters[] = [
                'url' => $url->out(false),
                'label' => get_string('template_filter_' . $filter, 'local_mailwhistle'),
                'active' => $this->filter === $filter,
            ];
        }

        $createurl = new \moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'templates',
            'action' => 'create',
        ]);

        return [
            'canmanage' => $this->canmanage,
            'createurl' => $createurl->out(false),
            'filters' => $filters,
            'hascards' => !empty($cards),
            'cards' => $cards,
            'emptynotification' => $output->notification(
                get_string('templates_empty_' . $this->filter, 'local_mailwhistle'),
                \core\output\notification::NOTIFY_INFO
            ),
        ];
    }
}
