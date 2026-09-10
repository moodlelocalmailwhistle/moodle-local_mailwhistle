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
     * Image MIME types that may be embedded in outgoing mail.
     */
    public const PUBLIC_IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
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
     * Get a list of available resources.
     *
     * @return string[] $filename => $url
     */
    public static function get_available_files(): array {
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
            if ($file->is_directory() || !self::is_public_image($file)) {
                continue;
            }
            $filename = $file->get_filename();
            $ret[$filename] = self::public_url($file);
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
        return [
            'form' => $this->form->render(),
            'hasfiles' => !empty($files),
            'files' => $files,
        ];
    }
}
