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

use local_mailwhistle\form\audience_filter_form;
use local_mailwhistle\helper;
use local_mailwhistle\manager\tag_manager;
use local_mailwhistle\table\audience_table;

/**
 * Audience tab: tag assign/remove and the filtered user table.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audience {
    /**
     * POST-only writes. Must run before page output.
     *
     * @param string $action Requested action.
     * @return void
     */
    public static function handle_write(string $action): void {
        $writeactions = ['applytag', 'createtag', 'removetagconfirm'];
        if (!in_array($action, $writeactions, true)) {
            return;
        }

        helper::require_post();
        require_sesskey();
        require_capability('local/mailwhistle:managetags', \context_system::instance());

        $audienceurl = new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'audience']);

        match ($action) {
            'applytag' => self::apply_tag($audienceurl),
            'removetagconfirm' => self::remove_tag($audienceurl),
            'createtag' => self::create_tag($audienceurl),
            default => null,
        };
    }

    /**
     * Render the confirm page or the filtered audience table.
     *
     * @param string $action Requested action.
     * @return string
     */
    public static function render(string $action): string {
        if ($action === 'removetag') {
            return self::confirm_remove();
        }
        return self::listing();
    }

    /**
     * Assign an existing or new tag to the selected users.
     *
     * @param \moodle_url $audienceurl Audience tab URL.
     * @return void
     */
    private static function apply_tag(\moodle_url $audienceurl): void {
        $userids = optional_param_array('userids', [], PARAM_INT);
        $applytagid = optional_param('applytagid', 0, PARAM_INT);
        $newtagname = trim(optional_param('newtagname', '', PARAM_TEXT));

        $tagid = 0;
        if ($newtagname !== '') {
            $tagid = tag_manager::get_or_create_tag($newtagname);
        } else if ($applytagid > 0) {
            $tagid = $applytagid;
        }

        if ($tagid <= 0 || empty($userids)) {
            redirect(
                $audienceurl,
                get_string('noselection', 'local_mailwhistle'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }

        $count = tag_manager::assign_tag_to_users($tagid, $userids);
        redirect(
            $audienceurl,
            get_string('tag_assigned_n', 'local_mailwhistle', $count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * Remove a tag from one user after confirm.
     *
     * @param \moodle_url $audienceurl Audience tab URL.
     * @return void
     */
    private static function remove_tag(\moodle_url $audienceurl): void {
        $tagid = required_param('tagid', PARAM_INT);
        $userid = required_param('userid', PARAM_INT);
        tag_manager::unassign_tag($tagid, $userid);
        redirect(
            $audienceurl,
            get_string('tag_removed', 'local_mailwhistle'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * Create a tag with no assignments.
     *
     * @param \moodle_url $audienceurl Audience tab URL.
     * @return void
     */
    private static function create_tag(\moodle_url $audienceurl): void {
        $newtagname = required_param('newtagname', PARAM_TEXT);
        tag_manager::get_or_create_tag($newtagname);
        redirect(
            $audienceurl,
            get_string('tag_created', 'local_mailwhistle'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * GET confirm page for per-row tag removal. No write happens here.
     *
     * @return string
     */
    private static function confirm_remove(): string {
        global $OUTPUT;

        require_sesskey();
        require_capability('local/mailwhistle:managetags', \context_system::instance());

        $tagid = required_param('tagid', PARAM_INT);
        $userid = required_param('userid', PARAM_INT);
        $continue = new \single_button(
            new \moodle_url('/local/mailwhistle/index.php', [
                'tab' => 'audience',
                'action' => 'removetagconfirm',
                'tagid' => $tagid,
                'userid' => $userid,
                'sesskey' => sesskey(),
            ]),
            get_string('remove_tag', 'local_mailwhistle'),
            'post'
        );
        $cancel = new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'audience']);

        return $OUTPUT->confirm(get_string('confirm_remove_tag', 'local_mailwhistle'), $continue, $cancel);
    }

    /**
     * Filter form plus audience table (and apply-tag form when allowed).
     *
     * @return string
     */
    private static function listing(): string {
        global $CFG;

        require_once($CFG->libdir . '/tablelib.php');
        require_once($CFG->libdir . '/formslib.php');

        $search = optional_param('search', '', PARAM_TEXT);
        $filtertagid = optional_param('tagid', 0, PARAM_INT);
        $suspended = optional_param('suspended', 'any', PARAM_ALPHA);
        $auth = optional_param('auth', 'any', PARAM_PLUGIN);
        $perpage = min(max(optional_param('perpage', 25, PARAM_INT), 1), 100);
        $canmanage = has_capability('local/mailwhistle:managetags', \context_system::instance());
        $tags = tag_manager::get_all_tags();

        $authplugins = [];
        foreach (array_keys(\core_component::get_plugin_list('auth')) as $authkey) {
            $authplugins[$authkey] = $authkey;
        }

        $baseurl = new \moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'audience',
            'search' => $search,
            'tagid' => $filtertagid,
            'suspended' => $suspended,
            'auth' => $auth,
            'perpage' => $perpage,
        ]);

        $filterform = new audience_filter_form(
            $baseurl->out(false),
            ['tags' => $tags, 'auths' => $authplugins],
            'get'
        );
        $filterform->set_data([
            'search' => $search,
            'tagid' => $filtertagid,
            'suspended' => $suspended,
            'auth' => $auth,
        ]);

        $table = new audience_table(
            'local_mailwhistle_audience',
            $baseurl,
            [
                'search' => $search,
                'tagid' => $filtertagid,
                'suspended' => $suspended,
                'auth' => $auth,
            ],
            $canmanage
        );
        $table->build_sql();

        ob_start();
        $filterform->display();
        if ($canmanage) {
            echo self::apply_tag_form_start();
            echo self::apply_tag_controls($tags);
            $table->out($perpage, true);
            echo \html_writer::end_tag('form');
        } else {
            $table->out($perpage, true);
        }
        return (string) ob_get_clean();
    }

    /**
     * Open the POST form that wraps row checkboxes and apply-tag controls.
     *
     * @return string
     */
    private static function apply_tag_form_start(): string {
        $action = (new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'audience']))->out(false);
        return \html_writer::start_tag('form', ['method' => 'post', 'action' => $action])
            . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
            . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'applytag']);
    }

    /**
     * Existing-tag select plus new-tag name field.
     *
     * @param array $tags Tag records.
     * @return string
     */
    private static function apply_tag_controls(array $tags): string {
        $tagselectoptions = ['' => get_string('apply_tag_choose', 'local_mailwhistle')];
        foreach ($tags as $tag) {
            $tagselectoptions[(int) $tag->id] = format_string($tag->name);
        }

        $html = \html_writer::start_div('mw-apply-tag-controls d-flex align-items-center mb-3');
        $html .= \html_writer::label(
            get_string('apply_tag', 'local_mailwhistle'),
            'applytagid',
            true,
            ['class' => 'me-1']
        );
        $html .= \html_writer::select($tagselectoptions, 'applytagid', '', false, [
            'id' => 'applytagid',
            'class' => 'me-1',
        ]);
        $html .= ' ' . get_string('new_tag', 'local_mailwhistle') . ' ';
        $html .= \html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'newtagname',
            'id' => 'newtagname',
            'placeholder' => get_string('new_tag', 'local_mailwhistle'),
            'class' => 'mx-1 form-control',
        ]);
        $html .= \html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('applybtn', 'local_mailwhistle'),
            'class' => 'btn btn-primary',
        ]);
        $html .= \html_writer::end_div();
        return $html;
    }
}
