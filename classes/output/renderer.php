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

/**
 * Plugin renderer for Mail Whistle admin UI.
 *
 * Renderables implement templatable and map to templates/*.mustache.
 * This class adds a few named helpers so callers do not construct
 * tiny renderables themselves.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Coloured campaign status badge.
     *
     * @param string $status One of: draft, ready, sent, sending, scheduled, failed.
     * @return string Rendered badge HTML.
     */
    public function status_badge(string $status): string {
        return $this->render(new status_badge($status));
    }
}
