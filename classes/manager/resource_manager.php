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

use local_mailwhistle\output\resources;

/**
 * Protect shared campaign assets and validate requested attachments.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource_manager {
    /**
     * Explain why selected attachments cannot currently be sent.
     *
     * @param \stdClass $campaign Campaign content.
     * @return string[] Plain-text errors, to be escaped when rendered.
     */
    public static function get_attachment_errors(\stdClass $campaign): array {
        global $CFG;

        $filenames = campaign_manager::decode_attachments($campaign->attachmentsjson ?? '');
        if (!$filenames) {
            return [];
        }
        $errors = [];
        if (empty($CFG->allowattachments)) {
            $errors[] = get_string('attachments_disabled', 'local_mailwhistle');
        }
        $missing = array_diff($filenames, array_keys(resources::get_all_files()));
        if ($missing) {
            $errors[] = get_string('attachments_missing', 'local_mailwhistle', implode(', ', $missing));
        }
        return $errors;
    }

    /**
     * Prevent a message from silently losing selected attachments.
     *
     * @param \stdClass $campaign Campaign content.
     * @return void
     */
    public static function assert_attachments_available(\stdClass $campaign): void {
        $errors = self::get_attachment_errors($campaign);
        if ($errors) {
            throw new \moodle_exception('attachments_unavailable', 'local_mailwhistle', '', s(implode(' ', $errors)));
        }
    }

    /**
     * List saved campaigns and templates which still reference each resource.
     *
     * Sent campaigns retain their references so existing inbox images keep working.
     *
     * @return array<string, string[]> Resource filename => plain-text usage labels.
     */
    public static function get_usage(): array {
        global $DB;

        $files = resources::get_all_files();
        if (!$files) {
            return [];
        }
        $usage = [];
        $campaigns = $DB->get_recordset('local_mailwhistle_campaigns', null, '', 'id, name, bodyhtml, attachmentsjson');
        foreach ($campaigns as $campaign) {
            $label = get_string('resource_campaign', 'local_mailwhistle', trim($campaign->name) !== ''
                ? $campaign->name : get_string('untitledcampaign', 'local_mailwhistle'));
            $attachments = campaign_manager::decode_attachments($campaign->attachmentsjson ?? '');
            foreach ($files as $filename => $file) {
                if (in_array($filename, $attachments, true) || self::references_file((string) $campaign->bodyhtml, $file)) {
                    $usage[$filename][] = $label;
                }
            }
        }
        $campaigns->close();

        $templates = $DB->get_recordset('local_mailwhistle_templates', null, '', 'id, name, bodyhtml, builderjson');
        foreach ($templates as $template) {
            $content = (string) $template->bodyhtml . ' ' . (string) $template->builderjson;
            foreach ($files as $filename => $file) {
                if (self::references_file($content, $file)) {
                    $usage[$filename][] = get_string('resource_template', 'local_mailwhistle', $template->name);
                }
            }
        }
        $templates->close();
        return $usage;
    }

    /**
     * Recognise slash-argument, query-string and JSON-escaped resource references.
     *
     * @param string $content Stored HTML or builder JSON.
     * @param \stored_file $file Shared resource.
     * @return bool Whether the resource path is referenced.
     */
    private static function references_file(string $content, \stored_file $file): bool {
        $content = rawurldecode(html_entity_decode(str_replace('\\/', '/', $content), ENT_QUOTES, 'UTF-8'));
        $path = '/' . $file->get_contextid() . '/local_mailwhistle/' . resources::FILEAREA . '/' .
            $file->get_itemid() . $file->get_filepath() . $file->get_filename();
        return (bool) preg_match('~' . preg_quote($path, '~') . '(?=$|[?&#\s\x22\x27<>\\\\)])~u', $content);
    }

    /**
     * Find referenced resources deleted, renamed or replaced in a user's draft.
     *
     * @param int $draftid Filemanager draft area id.
     * @return string[] Protected filenames changed by the submitted draft.
     */
    public static function get_protected_changes(int $draftid): array {
        global $USER;

        $draftfiles = get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $draftid,
            '',
            false
        );
        $draft = [];
        foreach ($draftfiles as $file) {
            if ($file->get_filepath() === '/') {
                $draft[$file->get_filename()] = $file;
            }
        }
        $usage = self::get_usage();
        $changed = [];
        foreach (resources::get_all_files() as $filename => $file) {
            if (empty($usage[$filename])) {
                continue;
            }
            if (!isset($draft[$filename]) || $draft[$filename]->get_contenthash() !== $file->get_contenthash()) {
                $changed[] = $filename;
            }
        }
        return $changed;
    }
}
