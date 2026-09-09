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
 * @copyright 2024 Ldesign Media <developer@ldesignmedia.nl>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_manager {
    /** @var string List only templates that are not archived. */
    public const FILTER_ACTIVE = 'active';

    /** @var string List only archived templates. */
    public const FILTER_ARCHIVED = 'archived';

    /** @var string List every template. */
    public const FILTER_ALL = 'all';

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

        $checks = [
            'local_email_campaigns' => 'templateid',
        ];

        foreach ($checks as $table => $field) {
            if ($DB->get_manager()->table_exists(new \xmldb_table($table))) {
                $columns = $DB->get_columns($table);
                if (array_key_exists($field, $columns) && $DB->record_exists($table, [$field => $id])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Create a template from form data.
     *
     * @param \stdClass $data Form data.
     * @return int New template id.
     */
    public static function create(\stdClass $data): int {
        global $DB;

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

        $record = self::record_from_form($data);
        $record->id = $id;
        $record->timemodified = time();

        $DB->update_record('local_mailwhistle_templates', $record);
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
