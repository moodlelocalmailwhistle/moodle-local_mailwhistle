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

/**
 * Main plugin page dispatcher.
 *
 * Writes run before output (PRG). Each tab has its own controller.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class index {
    /** @var string[] Valid tab ids. */
    public const TABS = ['send', 'audience', 'templates', 'reports', 'resources'];

    /**
     * Fall back to the campaigns tab when the requested tab is unknown.
     *
     * @param string $tab Requested tab.
     * @return string
     */
    public static function normalise_tab(string $tab): string {
        return in_array($tab, self::TABS, true) ? $tab : 'send';
    }

    /**
     * Run the plugin index for the current request.
     *
     * @param string $tab Requested tab.
     * @return void
     */
    public static function execute(string $tab): void {
        global $OUTPUT;

        $tab = self::normalise_tab($tab);
        $action = optional_param('action', '', PARAM_ALPHA);

        self::setup_page($tab);

        match ($tab) {
            'audience' => audience::handle_write($action),
            'send' => send::handle_write($action),
            'resources' => resources::handle_write(),
            default => null,
        };

        $content = match ($tab) {
            'audience' => audience::render($action),
            'templates' => templates::execute(
                optional_param('action', 'list', PARAM_ALPHA),
                optional_param('id', 0, PARAM_INT)
            ),
            'reports' => reports::render(),
            'resources' => resources::render(),
            default => send::render(),
        };

        echo $OUTPUT->header();
        echo $OUTPUT->tabtree(self::tabtree(), $tab);
        echo $content;
        echo $OUTPUT->footer();
    }

    /**
     * Configure $PAGE for the active tab.
     *
     * @param string $tab Active tab.
     * @return void
     */
    private static function setup_page(string $tab): void {
        global $PAGE;

        $params = ['tab' => $tab];
        if ($tab === 'templates') {
            $params += templates::url_params();
        }

        $PAGE->set_url(new \moodle_url('/local/mailwhistle/index.php', $params));
        $PAGE->set_pagelayout('standard');
        $PAGE->set_title(get_string('pluginname', 'local_mailwhistle'));
        $PAGE->set_heading(get_string('pluginname', 'local_mailwhistle'));
        $PAGE->requires->css(new \moodle_url('/local/mailwhistle/styles.css'));
    }

    /**
     * Tab tree linking back to this page.
     *
     * @return \tabobject[]
     */
    private static function tabtree(): array {
        $tabs = [];
        foreach (self::TABS as $tabid) {
            $tabs[] = new \tabobject(
                $tabid,
                new \moodle_url('/local/mailwhistle/index.php', ['tab' => $tabid]),
                get_string('tab_' . $tabid, 'local_mailwhistle')
            );
        }
        return $tabs;
    }
}
