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

use local_mailwhistle\builder\schema;
use local_mailwhistle\form\template_form;
use local_mailwhistle\form\template_import_form;
use local_mailwhistle\manager\template_manager;
use local_mailwhistle\output\template_form_page;
use local_mailwhistle\output\template_preview;
use local_mailwhistle\output\templates_overview;

/**
 * Templates tab controller.
 *
 * Handles mutating actions (must run before page output) and returns HTML
 * for the templates tab. Rendering is delegated to the plugin renderer.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class templates {
    /**
     * Dispatch a templates-tab action.
     *
     * @param string $action Requested action.
     * @param int $id Selected template id, if any.
     * @return string Rendered HTML.
     */
    public static function execute(string $action, int $id = 0): string {
        $validactions = ['list', 'create', 'edit', 'preview', 'export', 'import', 'duplicate', 'archive', 'restore', 'delete'];
        if (!in_array($action, $validactions, true)) {
            $action = 'list';
        }

        return match ($action) {
            'create' => self::form(0),
            'edit' => self::form($id),
            'preview' => self::preview($id),
            'export' => self::export($id),
            'import' => self::import(),
            'duplicate' => self::duplicate($id),
            'archive' => self::archive($id),
            'restore' => self::restore($id),
            'delete' => self::delete($id),
            default => self::overview(),
        };
    }

    /**
     * Extra URL params so pagination and the active action survive.
     *
     * @return array<string, int|string>
     */
    public static function url_params(): array {
        $action = optional_param('action', 'list', PARAM_ALPHA);
        $id = optional_param('id', 0, PARAM_INT);
        $filter = optional_param('filter', template_manager::FILTER_ACTIVE, PARAM_ALPHA);
        $params = [];
        if ($action !== 'list') {
            $params['action'] = $action;
            if ($id > 0) {
                $params['id'] = $id;
            }
            return $params;
        }
        if ($filter !== template_manager::FILTER_ACTIVE) {
            $params['filter'] = $filter;
        }
        return $params;
    }

    /**
     * Render the template cards overview.
     *
     * @return string
     */
    private static function overview(): string {
        global $PAGE;

        $context = \context_system::instance();
        $canmanage = has_capability('local/mailwhistle:manage', $context);
        $filter = optional_param('filter', template_manager::FILTER_ACTIVE, PARAM_ALPHA);
        $allowed = [
            template_manager::FILTER_ACTIVE,
            template_manager::FILTER_ARCHIVED,
            template_manager::FILTER_ALL,
        ];
        if (!in_array($filter, $allowed, true)) {
            $filter = template_manager::FILTER_ACTIVE;
        }

        $renderer = $PAGE->get_renderer('local_mailwhistle');
        return $renderer->render(new templates_overview($filter, $canmanage));
    }

    /**
     * Render and process the create/edit template form.
     *
     * @param int $id Existing template id, or 0 for create.
     * @return string
     */
    private static function form(int $id): string {
        global $CFG, $PAGE;

        require_capability('local/mailwhistle:manage', \context_system::instance());

        if ($id > 0) {
            $template = template_manager::get($id);
            if (!$template) {
                throw new \moodle_exception('templatenotfound', 'local_mailwhistle');
            }
        } else {
            $template = null;
        }

        $formurl = new \moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'templates',
            'action' => $id > 0 ? 'edit' : 'create',
            'id' => $id,
        ]);
        $listurl = new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates']);

        require_once($CFG->libdir . '/formslib.php');

        $mform = new template_form($formurl, [
            'submitlabel' => get_string('template_save', 'local_mailwhistle'),
            'builderjson' => $template->builderjson ?? schema::default_json(),
            'builderstrings' => schema::strings(),
            'template' => $template,
        ]);

        if ($mform->is_cancelled()) {
            redirect($listurl);
        }

        if ($data = $mform->get_data()) {
            $iscopy = $id > 0 && !empty($data->savehtmlcopy);
            if ($iscopy) {
                $id = template_manager::create_html_copy($id, $data);
            } else if ($id > 0) {
                template_manager::update($id, $data);
            } else {
                $id = template_manager::create($data);
            }

            redirect(
                new \moodle_url('/local/mailwhistle/index.php', [
                    'tab' => 'templates',
                    'action' => $iscopy ? 'edit' : 'preview',
                    'id' => $id,
                ]),
                get_string($iscopy ? 'template_html_copy_created' : 'template_saved', 'local_mailwhistle'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        if ($template) {
            $mform->set_data([
                'id' => $template->id,
                'name' => $template->name,
                'previewtext' => $template->previewtext,
                'background' => $template->background ?? '#ffffff',
                'editormode' => $template->editormode ?? 'html',
                'builderjson' => $template->builderjson ?? schema::default_json(),
                'bodyhtml_editor' => [
                    'text' => $template->bodyhtml,
                    'format' => FORMAT_HTML,
                ],
            ]);
        }

        $heading = $id > 0
            ? get_string('template_edit_heading', 'local_mailwhistle')
            : get_string('template_create_heading', 'local_mailwhistle');

        $renderer = $PAGE->get_renderer('local_mailwhistle');
        return $renderer->render(new template_form_page($heading, $mform->render()));
    }

    /**
     * Render a full template preview.
     *
     * @param int $id Template id.
     * @return string
     */
    private static function preview(int $id): string {
        global $PAGE;

        if ($id <= 0) {
            throw new \moodle_exception('templatenotfound', 'local_mailwhistle');
        }

        $template = template_manager::get($id);
        if (!$template) {
            throw new \moodle_exception('templatenotfound', 'local_mailwhistle');
        }

        $context = \context_system::instance();
        $canmanage = has_capability('local/mailwhistle:manage', $context);
        $renderer = $PAGE->get_renderer('local_mailwhistle');
        return $renderer->render(new template_preview($template, $canmanage));
    }

    /**
     * Export a template as portable JSON.
     *
     * @param int $id Template id.
     * @return string Never returns; sends a download and exits.
     */
    private static function export(int $id): string {
        require_capability('local/mailwhistle:view', \context_system::instance());

        $template = template_manager::get($id);
        if (!$template) {
            throw new \moodle_exception('templatenotfound', 'local_mailwhistle');
        }

        $payload = template_manager::export_payload($template);
        $filename = clean_filename($template->name . '-mailwhistle-template.json');
        @header('Content-Type: application/json; charset=utf-8');
        @header('Content-Disposition: attachment; filename="' . $filename . '"');
        @header('Cache-Control: private, max-age=0, must-revalidate');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Upload and validate a template export before creating a new editable copy.
     *
     * @return string Rendered upload form, or redirect after success/cancel.
     */
    private static function import(): string {
        global $CFG, $PAGE;

        require_capability('local/mailwhistle:manage', \context_system::instance());
        require_once($CFG->libdir . '/formslib.php');
        $url = new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates', 'action' => 'import']);
        $mform = new template_import_form($url);
        if ($mform->is_cancelled()) {
            redirect(new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates']));
        }
        if ($mform->get_data()) {
            \local_mailwhistle\helper::require_post();
            require_sesskey();
            $id = template_manager::import_uploaded_json($mform->get_file_content('templatefile'));
            redirect(
                new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates', 'action' => 'edit', 'id' => $id]),
                get_string('template_imported', 'local_mailwhistle'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        return $PAGE->get_renderer('local_mailwhistle')->render(new template_form_page(
            get_string('template_import', 'local_mailwhistle'),
            $mform->render()
        ));
    }

    /**
     * Duplicate a saved template after an authorised POST and open its editor.
     *
     * @param int $id Original template id.
     * @return string Never returns; redirects.
     */
    private static function duplicate(int $id): string {
        \local_mailwhistle\helper::require_post();
        require_capability('local/mailwhistle:manage', \context_system::instance());
        require_sesskey();
        $copyid = template_manager::duplicate($id);
        redirect(
            new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates', 'action' => 'edit', 'id' => $copyid]),
            get_string('template_duplicated', 'local_mailwhistle'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * Handle archive action.
     *
     * @param int $id Template id.
     * @return string Never returns; redirects.
     */
    private static function archive(int $id): string {
        \local_mailwhistle\helper::require_post();
        require_capability('local/mailwhistle:manage', \context_system::instance());
        require_sesskey();

        if ($id <= 0 || !template_manager::get($id)) {
            throw new \moodle_exception('templatenotfound', 'local_mailwhistle');
        }

        template_manager::archive($id);
        redirect(
            new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'templates']),
            get_string('template_archived', 'local_mailwhistle'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * Handle restore action.
     *
     * @param int $id Template id.
     * @return string Never returns; redirects.
     */
    private static function restore(int $id): string {
        \local_mailwhistle\helper::require_post();
        require_capability('local/mailwhistle:manage', \context_system::instance());
        require_sesskey();

        if ($id <= 0 || !template_manager::get($id)) {
            throw new \moodle_exception('templatenotfound', 'local_mailwhistle');
        }

        template_manager::restore($id);
        redirect(
            new \moodle_url('/local/mailwhistle/index.php', [
                'tab' => 'templates',
                'filter' => template_manager::FILTER_ARCHIVED,
            ]),
            get_string('template_restored', 'local_mailwhistle'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * Render and handle hard-delete confirmation.
     *
     * @param int $id Template id.
     * @return string
     */
    private static function delete(int $id): string {
        global $OUTPUT;

        if ($id <= 0) {
            throw new \moodle_exception('templatenotfound', 'local_mailwhistle');
        }

        require_capability('local/mailwhistle:manage', \context_system::instance());

        $template = template_manager::get($id);
        if (!$template) {
            throw new \moodle_exception('templatenotfound', 'local_mailwhistle');
        }

        if (template_manager::has_usage($id)) {
            return $OUTPUT->notification(
                get_string('template_delete_used_blocked', 'local_mailwhistle'),
                \core\output\notification::NOTIFY_WARNING
            );
        }

        $confirmed = optional_param('confirm', 0, PARAM_BOOL);
        if ($confirmed) {
            \local_mailwhistle\helper::require_post();
            require_sesskey();
            template_manager::delete($id);
            redirect(
                new \moodle_url('/local/mailwhistle/index.php', [
                    'tab' => 'templates',
                    'filter' => template_manager::FILTER_ARCHIVED,
                ]),
                get_string('template_deleted', 'local_mailwhistle'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        $confirmurl = new \moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'templates',
            'action' => 'delete',
            'id' => $id,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]);
        $cancelurl = new \moodle_url('/local/mailwhistle/index.php', [
            'tab' => 'templates',
            'filter' => !empty($template->archived)
                ? template_manager::FILTER_ARCHIVED
                : template_manager::FILTER_ACTIVE,
        ]);

        return $OUTPUT->confirm(
            get_string('template_delete_confirm', 'local_mailwhistle', format_string($template->name)),
            $confirmurl,
            $cancelurl
        );
    }
}
