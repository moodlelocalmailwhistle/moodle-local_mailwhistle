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

namespace local_mailwhistle;

use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\manager\resource_manager;
use local_mailwhistle\manager\template_manager;
use local_mailwhistle\output\resources;

/**
 * Shared resource protection and attachment preflight tests.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_mailwhistle\manager\resource_manager
 * @covers    \local_mailwhistle\form\resources_form
 */
final class resource_manager_test extends \advanced_testcase {
    /**
     * Create a shared file.
     *
     * @param string $name Filename.
     * @param string $content File bytes.
     * @return \stored_file Shared resource.
     */
    private function create_file(string $name, string $content = 'original'): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_mailwhistle',
            'filearea' => resources::FILEAREA,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $name,
        ], $content);
    }

    /**
     * Prepare a draft area like the resource filemanager.
     *
     * @return int Draft area id.
     */
    private function prepare_draft(): int {
        $draftid = 0;
        file_prepare_draft_area($draftid, \context_system::instance()->id, 'local_mailwhistle', resources::FILEAREA, 0);
        return $draftid;
    }

    /**
     * Selected attachments must exist and be permitted by site configuration.
     */
    public function test_attachment_readiness(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->create_file('guide.pdf');
        $campaign = (object) ['attachmentsjson' => campaign_manager::encode_attachments(['guide.pdf', 'missing.pdf'])];
        $CFG->allowattachments = false;
        $errors = resource_manager::get_attachment_errors($campaign);
        $this->assertCount(2, $errors);
        $this->assertStringContainsString('missing.pdf', $errors[1]);
        $CFG->allowattachments = true;
        $this->assertCount(1, resource_manager::get_attachment_errors($campaign));
        $campaign->attachmentsjson = campaign_manager::encode_attachments(['guide.pdf']);
        $this->assertSame([], resource_manager::get_attachment_errors($campaign));
        $campaign->attachmentsjson = '[]';
        $CFG->allowattachments = false;
        $this->assertSame([], resource_manager::get_attachment_errors($campaign));
    }

    /**
     * Missing files fail explicitly instead of silently dropping attachments.
     */
    public function test_missing_attachment_throws(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        resource_manager::assert_attachments_available((object) ['attachmentsjson' => '["missing.pdf"]']);
    }

    /**
     * Shared files retain protection after a campaign has been sent.
     */
    public function test_sent_campaign_protects_image_and_attachment(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $image = $this->create_file('logo.jpg');
        $this->create_file('guide.pdf');
        $unused = $this->create_file('unused.txt');
        $id = helper::create_campaign('Sent newsletter');
        $DB->update_record('local_mailwhistle_campaigns', (object) [
            'id' => $id,
            'status' => campaign_manager::STATUS_SENT,
            'bodyhtml' => '<img src="' . resources::public_url($image)->out(false) . '">',
            'attachmentsjson' => '["guide.pdf"]',
        ]);
        $usage = resource_manager::get_usage();
        $this->assertArrayHasKey('logo.jpg', $usage);
        $this->assertArrayHasKey('guide.pdf', $usage);
        $this->assertArrayNotHasKey($unused->get_filename(), $usage);
        $draftid = $this->prepare_draft();
        $this->assertSame([], resource_manager::get_protected_changes($draftid));
        $fs = get_file_storage();
        $contextid = \context_user::instance($USER->id)->id;
        $fs->get_file($contextid, 'user', 'draft', $draftid, '/', 'logo.jpg')->delete();
        $guide = $fs->get_file($contextid, 'user', 'draft', $draftid, '/', 'guide.pdf');
        $record = (object) [
            'contextid' => $contextid, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftid, 'filepath' => '/', 'filename' => 'guide.pdf',
        ];
        $guide->delete();
        $fs->create_file_from_string($record, 'different version');
        $fs->get_file($contextid, 'user', 'draft', $draftid, '/', 'unused.txt')->delete();
        $this->assertEqualsCanonicalizing(['logo.jpg', 'guide.pdf'], resource_manager::get_protected_changes($draftid));
        $this->assertSame('original', resources::get_all_files()['guide.pdf']->get_content());
    }

    /**
     * Encoded query-string image URLs in templates are protected too.
     */
    public function test_template_reference_and_filename_boundary(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $file = $this->create_file('campus logo.jpg');
        $this->create_file('campus logo.jpg.backup');
        $path = '/' . $file->get_contextid() . '/local_mailwhistle/resources/0/campus logo.jpg';
        template_manager::create((object) [
            'name' => 'Campus template',
            'editormode' => 'html',
            'bodyhtml_editor' => ['text' => '<img src="' . $CFG->wwwroot . '/pluginfile.php?file=' . rawurlencode($path) . '">'],
        ]);
        $usage = resource_manager::get_usage();
        $this->assertArrayHasKey('campus logo.jpg', $usage);
        $this->assertArrayNotHasKey('campus logo.jpg.backup', $usage);
        $this->assertStringContainsString('Campus template', $usage['campus logo.jpg'][0]);
    }

    /**
     * The filemanager exposes a validation error without saving a destructive draft.
     */
    public function test_form_rejects_resource_deletion(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . '/formslib.php');
        $this->create_file('guide.pdf');
        $id = helper::create_campaign('Draft');
        $DB->set_field('local_mailwhistle_campaigns', 'attachmentsjson', '["guide.pdf"]', ['id' => $id]);
        $form = new \local_mailwhistle\form\resources_form();
        $errors = $form->validation(['resources' => file_get_unused_draft_itemid()], []);
        $this->assertArrayHasKey('resources', $errors);
        $this->assertStringContainsString('guide.pdf', $errors['resources']);
        $this->assertArrayHasKey('guide.pdf', resources::get_all_files());
    }
}
