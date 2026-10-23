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

        $audienceurl = self::return_url();

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
        global $CFG, $DB;

        $userids = optional_param_array('userids', [], PARAM_INT);
        $applytagid = optional_param('applytagid', 0, PARAM_INT);
        $newtagname = trim(optional_param('newtagname', '', PARAM_TEXT));
        $mode = optional_param('tagmode', 'existing', PARAM_ALPHA);

        // Check selection before creating a tag, including requests without JavaScript.
        $userids = array_values(array_unique(array_filter($userids, static fn($id) => $id > 0)));
        if ($userids) {
            [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $params['guestid'] = $CFG->siteguest;
            $userids = $DB->get_fieldset_select('user', 'id', "id $insql AND deleted = 0 AND id <> :guestid", $params);
        }
        if (!$userids) {
            redirect(
                $audienceurl,
                get_string('audience_selectpeople', 'local_mailwhistle'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }

        $validtag = $mode === 'new' ? $newtagname !== '' && \core_text::strlen($newtagname) <= 255
            : $mode === 'existing' && $DB->record_exists('local_mailwhistle_tag', ['id' => $applytagid]);
        if (!$validtag) {
            redirect(
                $audienceurl,
                get_string('audience_choosetag', 'local_mailwhistle'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }

        $tagid = $mode === 'new' ? tag_manager::get_or_create_tag($newtagname) : $applytagid;
        $count = tag_manager::assign_tag_to_users($tagid, $userids);
        $tagname = format_string($DB->get_field('local_mailwhistle_tag', 'name', ['id' => $tagid], MUST_EXIST));
        $message = $count ? get_string($count === 1 ? 'audience_tagadded_one' : 'audience_tagadded', 'local_mailwhistle', (object) [
            'tag' => $tagname,
            'count' => $count,
        ]) : get_string('audience_alreadytagged', 'local_mailwhistle', $tagname);
        redirect(
            $audienceurl,
            $message,
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
        $newtagname = trim(required_param('newtagname', PARAM_TEXT));
        if ($newtagname === '' || \core_text::strlen($newtagname) > 255) {
            redirect(
                $audienceurl,
                get_string('audience_choosetag', 'local_mailwhistle'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
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
        $cancel = self::return_url();
        $continue = new \single_button(
            new \moodle_url($cancel, [
                'filtertagid' => $cancel->get_param('tagid'),
                'action' => 'removetagconfirm',
                'tagid' => $tagid,
                'userid' => $userid,
                'sesskey' => sesskey(),
            ]),
            get_string('remove_tag', 'local_mailwhistle'),
            'post'
        );
        return $OUTPUT->confirm(get_string('confirm_remove_tag', 'local_mailwhistle'), $continue, $cancel);
    }

    /**
     * Keep the user's filters after a tag action.
     *
     * @return \moodle_url Audience listing URL.
     */
    private static function return_url(): \moodle_url {
        return new \moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'audience',
            'search' => optional_param('search', '', PARAM_TEXT),
            'tagid' => optional_param('filtertagid', 0, PARAM_INT),
            'suspended' => optional_param('suspended', 'any', PARAM_ALPHA),
            'auth' => optional_param('auth', 'any', PARAM_PLUGIN),
            'perpage' => min(max(optional_param('perpage', 25, PARAM_INT), 1), 100),
        ]);
    }

    /**
     * Filter form plus audience table (and apply-tag form when allowed).
     *
     * @return string
     */
    private static function listing(): string {
        global $CFG, $OUTPUT;

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
            ['tags' => $tags, 'auths' => $authplugins, 'suspended' => $suspended, 'auth' => $auth, 'perpage' => $perpage],
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
        echo $OUTPUT->heading(get_string('audience_build', 'local_mailwhistle'), 3);
        echo \html_writer::tag('p', get_string('audience_intro', 'local_mailwhistle'));
        echo $OUTPUT->heading(get_string('audience_findpeople', 'local_mailwhistle'), 4);
        $filterform->display();
        if ($canmanage) {
            echo self::apply_tag_form_start($baseurl);
            echo $OUTPUT->heading(get_string('audience_selectheading', 'local_mailwhistle'), 4);
            echo \html_writer::tag('p', get_string('audience_selectionhint', 'local_mailwhistle'), ['class' => 'text-muted']);
            $table->out($perpage, true);
            echo self::apply_tag_controls($tags);
            echo \html_writer::end_tag('form');
        } else {
            $table->out($perpage, true);
        }
        return (string) ob_get_clean();
    }

    /**
     * Open the POST form that wraps row checkboxes and apply-tag controls.
     *
     * @param \moodle_url $baseurl Current filters.
     * @return string
     */
    private static function apply_tag_form_start(\moodle_url $baseurl): string {
        $action = (new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'audience']))->out(false);
        $html = \html_writer::start_tag('form', [
            'method' => 'post', 'action' => $action, 'id' => 'local-mailwhistle-audience-form',
        ])
            . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
            . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'applytag']);
        foreach ($baseurl->params() as $name => $value) {
            $html .= \html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => $name === 'tagid' ? 'filtertagid' : $name, 'value' => $value,
            ]);
        }
        return $html;
    }

    /**
     * Existing-tag select plus new-tag name field.
     *
     * @param array $tags Tag records.
     * @return string
     */
    private static function apply_tag_controls(array $tags): string {
        global $OUTPUT;

        return $OUTPUT->render_from_template('local_mailwhistle/audience_tag_controls', [
            'hastags' => !empty($tags),
            'tags' => array_map(static fn($tag) => [
                'id' => (int) $tag->id,
                'name' => html_entity_decode(strip_tags(format_string($tag->name)), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ], $tags),
            'counttemplate' => get_string('audience_selectedcount', 'local_mailwhistle', '{$a}'),
        ]);
    }
}
