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

namespace local_mailwhistle\page;

use local_mailwhistle\form\resources_form;

/**
 * Resources tab: shared image file area.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resources {
    /** @var resources_form|null Prepared form instance. */
    private static ?resources_form $form = null;

    /**
     * Save uploaded files when posted. Must run before page output.
     *
     * @return void
     */
    public static function handle_write(): void {
        global $CFG, $PAGE;

        require_once($CFG->libdir . '/formslib.php');
        require_capability('local/mailwhistle:manage', \context_system::instance());

        self::$form = new resources_form($PAGE->url);
        if (self::$form->process()) {
            redirect($PAGE->url);
        }
    }

    /**
     * Render the resources filemanager form.
     *
     * @return string
     */
    public static function render(): string {
        global $OUTPUT;

        if (self::$form === null) {
            self::handle_write();
        }

        return $OUTPUT->render(new \local_mailwhistle\output\resources(self::$form));
    }
}
