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

namespace local_mailwhistle\manager;

use local_mailwhistle\builder\document;
use local_mailwhistle\builder\html_renderer;
use local_mailwhistle\builder\schema;

/**
 * Template persistence for Mail Whistle.
 *
 * CRUD, archive/restore, default-template install, and JSON import/export.
 * Rendering stays in the builder and output layers.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_manager {
    /** @var string List only templates that are not archived. */
    public const FILTER_ACTIVE = 'active';

    /** @var string List only archived templates. */
    public const FILTER_ARCHIVED = 'archived';

    /** @var string List every template. */
    public const FILTER_ALL = 'all';

    /** @var int Maximum uploaded template JSON size (2 MiB). */
    public const MAX_IMPORT_BYTES = 2097152;

    /**
     * Get templates ordered by most recently edited.
     *
     * @param string $filter Template visibility filter.
     * @return array Template records.
     */
    public static function get_list(string $filter = self::FILTER_ACTIVE): array {
        global $DB;

        $conditions = [];
        if ($filter === self::FILTER_ACTIVE) {
            $conditions['archived'] = 0;
        } else if ($filter === self::FILTER_ARCHIVED) {
            $conditions['archived'] = 1;
        }

        return $DB->get_records('local_mailwhistle_templates', $conditions, 'timemodified DESC, name ASC');
    }

    /**
     * Get one template.
     *
     * @param int $id Template id.
     * @return \stdClass|null Template record or null.
     */
    public static function get(int $id): ?\stdClass {
        global $DB;

        return $DB->get_record('local_mailwhistle_templates', ['id' => $id]) ?: null;
    }

    /**
     * Archive a template.
     *
     * @param int $id Template id.
     * @return void
     */
    public static function archive(int $id): void {
        global $DB;

        $DB->set_field('local_mailwhistle_templates', 'archived', 1, ['id' => $id]);
        $DB->set_field('local_mailwhistle_templates', 'timemodified', time(), ['id' => $id]);
    }

    /**
     * Restore an archived template.
     *
     * @param int $id Template id.
     * @return void
     */
    public static function restore(int $id): void {
        global $DB;

        $DB->set_field('local_mailwhistle_templates', 'archived', 0, ['id' => $id]);
        $DB->set_field('local_mailwhistle_templates', 'timemodified', time(), ['id' => $id]);
    }

    /**
     * Hard-delete a template when it has never been used.
     *
     * @param int $id Template id.
     * @return void
     */
    public static function delete(int $id): void {
        global $DB;

        if (self::has_usage($id)) {
            throw new \moodle_exception('templatecannotdeleteused', 'local_mailwhistle');
        }

        $DB->delete_records('local_mailwhistle_templates', ['id' => $id]);
    }

    /**
     * Check whether a template has been used by sent/scheduled newsletters.
     *
     * This is intentionally centralized so future sending/history tables can
     * enforce hard-delete protection without changing UI code.
     *
     * @param int $id Template id.
     * @return bool True when the template is referenced by usage history.
     */
    public static function has_usage(int $id): bool {
        global $DB;

        return $DB->record_exists_select(
            'local_mailwhistle_campaigns',
            'templateid = :templateid AND status <> :draft',
            [
                'templateid' => $id,
                'draft' => campaign_manager::STATUS_DRAFT,
            ]
        );
    }

    /**
     * Create a template from form data.
     *
     * @param \stdClass $data Form data.
     * @return int New template id.
     */
    public static function create(\stdClass $data): int {
        global $DB;

        if ($error = self::validate_editor_content($data)) {
            throw new \moodle_exception($error, 'local_mailwhistle');
        }
        $now = time();
        $record = self::record_from_form($data);
        $record->timecreated = $now;
        $record->timemodified = $now;

        return $DB->insert_record('local_mailwhistle_templates', $record);
    }

    /**
     * Update a template from form data.
     *
     * @param int $id Template id.
     * @param \stdClass $data Form data.
     * @return void
     */
    public static function update(int $id, \stdClass $data): void {
        global $DB;

        $template = self::get($id);
        if (!$template) {
            throw new \moodle_exception('templatenotfound', 'local_mailwhistle');
        }
        if ($error = self::validate_editor_content($data, $template)) {
            throw new \moodle_exception($error, 'local_mailwhistle');
        }
        $record = self::record_from_form($data);
        $record->id = $id;
        $record->timemodified = time();

        $DB->update_record('local_mailwhistle_templates', $record);
    }

    /**
     * Create an HTML copy of the submitted builder content without changing the original.
     *
     * @param int $id Original builder template id.
     * @param \stdClass $data Current form data, including unsaved block edits.
     * @return int New HTML template id.
     */
    public static function create_html_copy(int $id, \stdClass $data): int {
        $template = self::get($id);
        if (!$template) {
            throw new \moodle_exception('templatenotfound', 'local_mailwhistle');
        }
        if (($template->editormode ?? 'html') !== 'builder' || ($data->editormode ?? '') !== 'builder') {
            throw new \moodle_exception('template_mode_locked', 'local_mailwhistle');
        }

        $record = self::record_from_form($data);
        $record->name = \core_text::substr(get_string('template_html_copy_name', 'local_mailwhistle', $record->name), 0, 255);
        $record->editormode = 'html';
        $record->builderjson = schema::default_json();
        $record->bodyhtml_editor = ['text' => $record->bodyhtml, 'format' => FORMAT_HTML];
        return self::create($record);
    }

    /**
     * Duplicate saved content into an independent active template in the same editor mode.
     *
     * @param int $id Original template id.
     * @return int New template id.
     */
    public static function duplicate(int $id): int {
        global $DB;

        $template = self::get($id);
        if (!$template) {
            throw new \moodle_exception('templatenotfound', 'local_mailwhistle');
        }
        $now = time();
        return (int) $DB->insert_record('local_mailwhistle_templates', (object) [
            'name' => self::copy_name($template->name),
            'previewtext' => $template->previewtext,
            'background' => $template->background,
            'editormode' => $template->editormode,
            'builderjson' => $template->builderjson,
            'bodyhtml' => $template->bodyhtml,
            'archived' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Choose a readable copy name without colliding with active or archived templates.
     *
     * @param string $name Original name.
     * @return string Available copy name, limited to the database field length.
     */
    private static function copy_name(string $name): string {
        global $DB;

        $base = get_string('template_copy_name', 'local_mailwhistle', $name);
        for ($number = 1;; $number++) {
            $suffix = $number > 1 ? ' (' . $number . ')' : '';
            $candidate = \core_text::substr($base, 0, 255 - \core_text::strlen($suffix)) . $suffix;
            if (!$DB->record_exists('local_mailwhistle_templates', ['name' => $candidate])) {
                return $candidate;
            }
        }
    }

    /**
     * Validate an uploaded export without creating a template.
     *
     * @param string $json Uploaded JSON.
     * @return string|null Localised validation error, or null when valid.
     */
    public static function validate_import_json(string $json): ?string {
        try {
            self::record_from_export($json);
        } catch (\moodle_exception $exception) {
            return $exception->getMessage();
        }
        return null;
    }

    /**
     * Import a user's export as a new active template, never overwriting an existing record.
     *
     * Uploaded ids, ownership, timestamps and archive flags are deliberately ignored.
     * Referenced files remain URLs; the JSON export does not bundle file contents.
     *
     * @param string $json Uploaded export.
     * @return int New template id.
     */
    public static function import_uploaded_json(string $json): int {
        global $DB;

        $record = self::record_from_export($json);
        if ($DB->record_exists('local_mailwhistle_templates', ['name' => $record->name])) {
            $record->name = self::copy_name($record->name);
        }
        $record->archived = 0;
        $record->timecreated = time();
        $record->timemodified = $record->timecreated;
        return (int) $DB->insert_record('local_mailwhistle_templates', $record);
    }

    /**
     * Parse and validate the existing version 1 export format before any database writes.
     *
     * @param string $json Uploaded export.
     * @return \stdClass Template content with only supported database fields.
     */
    private static function record_from_export(string $json): \stdClass {
        if (strlen($json) > self::MAX_IMPORT_BYTES) {
            throw new \moodle_exception('template_import_invalid', 'local_mailwhistle');
        }
        $data = json_decode($json, true);
        if (!is_array($data) || ($data['format'] ?? null) !== 'local_mailwhistle_template') {
            throw new \moodle_exception('template_import_invalid', 'local_mailwhistle');
        }
        if (($data['formatversion'] ?? null) !== 1) {
            throw new \moodle_exception('template_import_version', 'local_mailwhistle');
        }
        $template = $data['template'] ?? null;
        if (!is_array($template)) {
            throw new \moodle_exception('template_import_invalid', 'local_mailwhistle');
        }
        foreach (['name', 'previewtext', 'background', 'editormode', 'html'] as $field) {
            if (!isset($template[$field]) || !is_string($template[$field])) {
                throw new \moodle_exception('template_import_invalid', 'local_mailwhistle');
            }
        }
        if (
            trim($template['name']) === '' || \core_text::strlen($template['name']) > 255
            || \core_text::strlen($template['previewtext']) > 255
            || !preg_match('/^#[0-9a-f]{6}$/i', $template['background'])
            || !in_array($template['editormode'], ['html', 'builder'], true)
        ) {
            throw new \moodle_exception('template_import_invalid', 'local_mailwhistle');
        }
        $builder = $template['builder'] ?? null;
        if (
            !is_array($builder) || !isset($builder['blocks']) || !is_array($builder['blocks'])
                || !array_is_list($builder['blocks'])
        ) {
            throw new \moodle_exception('template_import_invalid', 'local_mailwhistle');
        }
        foreach ($builder['blocks'] as $block) {
            if (!is_array($block)) {
                throw new \moodle_exception('template_import_invalid', 'local_mailwhistle');
            }
            foreach ($block as $value) {
                if (!is_string($value) && !is_int($value) && !is_float($value)) {
                    throw new \moodle_exception('template_import_invalid', 'local_mailwhistle');
                }
            }
        }
        $builderjson = json_encode($builder);
        if (document::validate_json($builderjson)) {
            throw new \moodle_exception('template_builder_invalid', 'local_mailwhistle');
        }
        $builderjson = document::normalise_json($builderjson);
        return (object) [
            'name' => trim($template['name']),
            'previewtext' => $template['previewtext'],
            'background' => $template['background'],
            'editormode' => $template['editormode'],
            'builderjson' => $builderjson,
            'bodyhtml' => $template['editormode'] === 'builder'
                ? html_renderer::render($builderjson, $template['background']) : $template['html'],
        ];
    }

    /**
     * Reject editor changes that would discard a template's other representation.
     *
     * @param \stdClass $data Submitted form data.
     * @param \stdClass|null $template Saved template, or null for a new template.
     * @return string|null Error string identifier, or null when content is safe to save.
     */
    public static function validate_editor_content(\stdClass $data, ?\stdClass $template = null): ?string {
        $mode = $data->editormode ?? '';
        if (!in_array($mode, ['builder', 'html'], true)) {
            return 'template_mode_locked';
        }
        if ($template) {
            return $mode === ($template->editormode ?? 'html') ? null : 'template_mode_locked';
        }

        $builder = json_decode((string) ($data->builderjson ?? ''), true);
        $hasblocks = !empty($builder['blocks']);
        $html = (string) ($data->bodyhtml_editor['text'] ?? '');
        // Rich-text editors can submit an empty paragraph even when no content was entered.
        $hashtml = trim(str_replace("\xc2\xa0", ' ', html_to_text($html))) !== ''
            || preg_match('/<(?:img|iframe|video|audio|object|embed|hr|table|a)\b/i', $html);
        if (($mode === 'html' && $hasblocks) || ($mode === 'builder' && $hashtml)) {
            return 'template_mode_unsaved';
        }
        return null;
    }

    /**
     * Seed the default email template(s) bundled with the plugin.
     *
     * Reads every exported-template JSON file shipped under db/defaulttemplates
     * and creates it when a template of the same name does not already exist.
     *
     * @return int Number of templates created.
     */
    public static function install_defaults(): int {
        global $CFG;

        $created = 0;
        $pattern = $CFG->dirroot . '/local/mailwhistle/db/defaulttemplates/*.json';
        foreach ((array) glob($pattern) as $file) {
            $json = file_get_contents($file);
            if ($json !== false && self::import_json($json) > 0) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Create a template from an exported local_mailwhistle_template JSON document.
     *
     * Returns 0 (skips creation) when the JSON is not a valid template export,
     * or when a template with the same name already exists.
     *
     * @param string $json Exported template JSON.
     * @return int New template id, or 0 when skipped.
     */
    public static function import_json(string $json): int {
        global $DB;

        $data = json_decode($json, true);
        if (!is_array($data) || ($data['format'] ?? '') !== 'local_mailwhistle_template') {
            return 0;
        }

        $template = $data['template'] ?? null;
        if (!is_array($template)) {
            return 0;
        }

        $name = trim((string) ($template['name'] ?? ''));
        if ($name === '') {
            return 0;
        }

        if ($DB->record_exists('local_mailwhistle_templates', ['name' => $name])) {
            return 0;
        }

        $background = document::normalise_background((string) ($template['background'] ?? '#ffffff'));
        $editormode = document::normalise_editor_mode((string) ($template['editormode'] ?? 'html'));
        $builderjson = document::normalise_json(
            json_encode($template['builder'] ?? ['blocks' => []])
        );

        if ($editormode === 'builder') {
            $bodyhtml = html_renderer::render($builderjson, $background);
        } else {
            $bodyhtml = (string) ($template['html'] ?? '');
        }

        $now = time();
        $record = (object) [
            'name' => $name,
            'previewtext' => trim((string) ($template['previewtext'] ?? '')),
            'background' => $background,
            'editormode' => $editormode,
            'builderjson' => $builderjson,
            'bodyhtml' => $bodyhtml,
            'archived' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        return (int) $DB->insert_record('local_mailwhistle_templates', $record);
    }

    /**
     * Build a portable export document for a template.
     *
     * @param \stdClass $template Template record.
     * @return array Export payload.
     */
    public static function export_payload(\stdClass $template): array {
        return [
            'format' => 'local_mailwhistle_template',
            'formatversion' => 1,
            'component' => 'local_mailwhistle',
            'exportedat' => gmdate('c'),
            'template' => [
                'name' => $template->name,
                'previewtext' => $template->previewtext,
                'background' => $template->background ?? '#ffffff',
                'editormode' => $template->editormode ?? 'html',
                'builder' => json_decode((string) ($template->builderjson ?? ''), true) ?: ['blocks' => []],
                'html' => $template->bodyhtml ?? '',
                'placeholders' => self::extract_placeholders($template),
            ],
        ];
    }

    /**
     * Extract placeholder names from a template record.
     *
     * Placeholders are written directly in template text as {{name}}.
     *
     * @param \stdClass|null $template Template record.
     * @return array Unique placeholder names in first-seen order.
     */
    public static function extract_placeholders(?\stdClass $template): array {
        if (!$template) {
            return [];
        }

        return self::extract_placeholders_from_text(
            (string) ($template->previewtext ?? '') . "\n" . (string) ($template->bodyhtml ?? '')
        );
    }

    /**
     * Extract placeholder names from text.
     *
     * @param string $text Text that may contain {{name}} placeholders.
     * @return array Unique placeholder names in first-seen order.
     */
    public static function extract_placeholders_from_text(string $text): array {
        if (!preg_match_all('/\{\{\s*([a-zA-Z][a-zA-Z0-9_]*)\s*\}\}/', $text, $matches)) {
            return [];
        }

        $placeholders = [];
        $seen = [];
        foreach ($matches[1] as $placeholder) {
            $key = strtolower($placeholder);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $placeholders[] = $placeholder;
        }

        return $placeholders;
    }

    /**
     * Build a database record from submitted form data.
     *
     * @param \stdClass $data Form data.
     * @return \stdClass Template record.
     */
    private static function record_from_form(\stdClass $data): \stdClass {
        if (
            ($data->editormode ?? '') === 'builder'
            && document::validate_json((string) ($data->builderjson ?? ''))
        ) {
            throw new \moodle_exception('template_builder_invalid', 'local_mailwhistle');
        }
        $record = new \stdClass();
        $record->name = trim((string) $data->name);
        $record->previewtext = trim((string) ($data->previewtext ?? ''));
        $record->background = document::normalise_background((string) ($data->background ?? ''));
        $record->editormode = document::normalise_editor_mode((string) ($data->editormode ?? 'html'));
        $record->builderjson = document::normalise_json((string) ($data->builderjson ?? schema::default_json()));
        $record->bodyhtml = $record->editormode === 'builder'
            ? html_renderer::render($record->builderjson, $record->background)
            : ($data->bodyhtml_editor['text'] ?? '');

        return $record;
    }
}
