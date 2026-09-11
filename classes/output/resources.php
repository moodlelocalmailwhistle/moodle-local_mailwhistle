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

/**
 * File resources
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mailwhistle\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;

/**
 * File resources
 */
class resources implements renderable, templatable {
    /**
     * The file area name.
     */
    public const FILEAREA = 'resources';

    /**
     * Temporary zip of multiple campaign attachments.
     */
    public const ZIP_FILEAREA = 'attachmentzips';

    /**
     * Image MIME types that may be embedded in outgoing mail.
     */
    public const PUBLIC_IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * File picker types: images for templates, documents for email attachments.
     */
    public const ACCEPTED_TYPES = [
        '.jpg', '.jpeg', '.png', '.gif', '.webp',
        '.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx',
        '.odt', '.ods', '.odp', '.txt', '.csv', '.zip',
    ];

    /**
     * The constructor.
     *
     * @param \local_mailwhistle\form\resources_form $form The form.
     */
    public function __construct(
        /** @var \local_mailwhistle\form\resources_form The resources form. */
        protected \local_mailwhistle\form\resources_form $form
    ) {
    }

    /**
     * All non-directory files in the resources area, keyed by filename.
     *
     * @return \stored_file[] $filename => $file
     */
    public static function get_all_files(): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            \context_system::instance()->id,
            'local_mailwhistle',
            self::FILEAREA,
            false,
            false
        );
        $ret = [];
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $ret[$file->get_filename()] = $file;
        }
        ksort($ret, SORT_NATURAL | SORT_FLAG_CASE);
        return $ret;
    }

    /**
     * Public image URLs for template embedding.
     *
     * @return \moodle_url[] $filename => $url
     */
    public static function get_available_files(): array {
        $ret = [];
        foreach (self::get_all_files() as $filename => $file) {
            if (self::is_public_image($file)) {
                $ret[$filename] = self::public_url($file);
            }
        }
        return $ret;
    }

    /**
     * Resource files matching the given filenames, preserving request order.
     *
     * @param string[] $filenames Stored filenames.
     * @return \stored_file[] $filename => $file
     */
    public static function get_files_by_filenames(array $filenames): array {
        $all = self::get_all_files();
        $ret = [];
        foreach ($filenames as $filename) {
            if (isset($all[$filename])) {
                $ret[$filename] = $all[$filename];
            }
        }
        return $ret;
    }

    /**
     * Whether this stored file can be fetched by an email client with no login.
     *
     * @param \stored_file $file File from the plugin file area.
     * @return bool
     */
    public static function is_public_image(\stored_file $file): bool {
        if ($file->is_directory()) {
            return false;
        }
        if ($file->get_component() !== 'local_mailwhistle') {
            return false;
        }
        if ($file->get_filearea() !== self::FILEAREA) {
            return false;
        }
        return in_array($file->get_mimetype(), self::PUBLIC_IMAGE_TYPES, true);
    }

    /**
     * Absolute pluginfile URL for a resource image (no user token).
     *
     * @param \stored_file $file File from the resources area.
     * @return \moodle_url
     */
    public static function public_url(\stored_file $file): \moodle_url {
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            false,
            false
        );
    }

    /**
     * Export the data for the resources template.
     *
     * @param renderer_base $output The renderer.
     * @return array Template context.
     */
    public function export_for_template(renderer_base $output) {
        $files = [];
        foreach (self::get_available_files() as $filename => $url) {
            $files[] = [
                'filename' => $filename,
                'url' => $url->out(false),
            ];
        }
        $documents = [];
        foreach (self::get_all_files() as $filename => $file) {
            if (!self::is_public_image($file)) {
                $documents[] = ['filename' => $filename];
            }
        }
        return [
            'form' => $this->form->render(),
            'hasfiles' => !empty($files),
            'files' => $files,
            'hasdocuments' => !empty($documents),
            'documents' => $documents,
        ];
    }
}
