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

use local_mailwhistle\manager\tag_manager;
use local_mailwhistle\form\audience_filter_form;
use local_mailwhistle\page\audience;

/**
 * Tests for audience tag assignment requests.
 *
 * @package   local_mailwhistle
 * @covers    \local_mailwhistle\page\audience
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class audience_page_test extends \advanced_testcase {
    /**
     * Start with an administrator and isolated test data.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Submit a tagging request and catch Moodle's CLI redirect exception.
     *
     * @param array $data Posted tag assignment values.
     */
    private function submit_assignment(array $data): void {
        $previouspost = $_POST;
        $previousget = $_GET;
        $_GET = [];
        $_POST = ['sesskey' => sesskey()] + $data;
        try {
            audience::handle_write('applytag');
            $this->fail('The tag assignment request must redirect back to the audience.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('redirecterrordetected', $exception->errorcode);
        } finally {
            $_POST = $previouspost;
            $_GET = $previousget;
        }
    }

    /**
     * A missing or invalid selection must not create a tag or an assignment.
     *
     * @dataProvider invalid_selection_provider
     * @param array $userids Submitted user ids.
     */
    public function test_invalid_selection_does_not_create_a_tag(array $userids): void {
        global $DB;
        $sink = $this->redirectEvents();

        $this->submit_assignment(['tagmode' => 'new', 'newtagname' => 'Unused group', 'userids' => $userids]);

        $this->assertSame(0, $DB->count_records('local_mailwhistle_tag'));
        $this->assertSame(0, $DB->count_records('local_mailwhistle_tag_assign'));
        $this->assertCount(0, $sink->get_events());
        $sink->close();
    }

    /**
     * Selections with no eligible user.
     *
     * @return array
     */
    public static function invalid_selection_provider(): array {
        return [
            'empty' => [[]],
            'unknown user' => [[999999]],
            'nonpositive ids' => [[0, -1]],
        ];
    }

    /**
     * Guest and deleted accounts cannot create a group through a forged selection.
     */
    public function test_guest_and_deleted_selection_does_not_create_a_tag(): void {
        global $CFG, $DB;
        $deleted = $this->getDataGenerator()->create_user();
        delete_user($deleted);

        $this->submit_assignment([
            'tagmode' => 'new',
            'newtagname' => 'Unused group',
            'userids' => [$CFG->siteguest, $deleted->id],
        ]);

        $this->assertSame(0, $DB->count_records('local_mailwhistle_tag'));
        $this->assertSame(0, $DB->count_records('local_mailwhistle_tag_assign'));
    }

    /**
     * New-tag validation happens before any tag or assignment is written.
     *
     * @dataProvider invalid_name_provider
     * @param string $name Submitted new tag name.
     */
    public function test_invalid_new_name_does_not_write(string $name): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();

        $this->submit_assignment(['tagmode' => 'new', 'newtagname' => $name, 'userids' => [$user->id]]);

        $this->assertSame(0, $DB->count_records('local_mailwhistle_tag'));
        $this->assertSame(0, $DB->count_records('local_mailwhistle_tag_assign'));
    }

    /**
     * Invalid tag names at the request boundary.
     *
     * @return array
     */
    public static function invalid_name_provider(): array {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
            'over maximum length' => [str_repeat('a', 256)],
        ];
    }

    /**
     * An invalid existing tag cannot fall back to creating a hidden new name.
     */
    public function test_invalid_existing_tag_does_not_write(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();

        $this->submit_assignment([
            'tagmode' => 'existing',
            'applytagid' => 999999,
            'newtagname' => 'Must not be created',
            'userids' => [$user->id],
        ]);

        $this->assertSame(0, $DB->count_records('local_mailwhistle_tag'));
        $this->assertSame(0, $DB->count_records('local_mailwhistle_tag_assign'));
    }

    /**
     * The chosen existing-tag mode takes priority over a stale new-name field.
     */
    public function test_existing_mode_ignores_new_name(): void {
        global $DB;
        $tagid = tag_manager::create_tag('Newsletter');
        $user = $this->getDataGenerator()->create_user();

        $this->submit_assignment([
            'tagmode' => 'existing',
            'applytagid' => $tagid,
            'newtagname' => 'Ignored new group',
            'userids' => [$user->id, $user->id],
        ]);

        $this->assertSame(1, $DB->count_records('local_mailwhistle_tag'));
        $this->assertSame(1, $DB->count_records('local_mailwhistle_tag_assign'));
        $this->assertTrue($DB->record_exists('local_mailwhistle_tag_assign', ['tagid' => $tagid, 'userid' => $user->id]));
    }

    /**
     * The chosen new-tag mode creates and assigns the named group.
     */
    public function test_new_mode_ignores_existing_tag(): void {
        global $DB;
        $existingid = tag_manager::create_tag('Newsletter');
        $user = $this->getDataGenerator()->create_user();

        $this->submit_assignment([
            'tagmode' => 'new',
            'applytagid' => $existingid,
            'newtagname' => '  New group  ',
            'userids' => [$user->id],
        ]);

        $newid = $DB->get_field('local_mailwhistle_tag', 'id', ['name' => 'New group'], MUST_EXIST);
        $this->assertTrue($DB->record_exists('local_mailwhistle_tag_assign', ['tagid' => $newid, 'userid' => $user->id]));
        $this->assertFalse($DB->record_exists('local_mailwhistle_tag_assign', ['tagid' => $existingid, 'userid' => $user->id]));
    }

    /**
     * Action redirects retain listing filters without confusing a removed tag with the tag filter.
     */
    public function test_return_url_preserves_filters(): void {
        $previouspost = $_POST;
        $previousget = $_GET;
        $_GET = [];
        $_POST = [
            'search' => 'alice@example.com',
            'filtertagid' => 17,
            'tagid' => 42,
            'suspended' => 'active',
            'auth' => 'manual',
            'perpage' => 50,
        ];
        try {
            $method = new \ReflectionMethod(audience::class, 'return_url');
            $url = $method->invoke(null);

            $this->assertStringEndsWith('/local/mailwhistle/index.php', $url->get_path());
            $this->assertSame('audience', $url->get_param('tab'));
            $this->assertSame('alice@example.com', $url->get_param('search'));
            $this->assertSame('17', $url->get_param('tagid'));
            $this->assertSame('active', $url->get_param('suspended'));
            $this->assertSame('manual', $url->get_param('auth'));
            $this->assertSame('50', $url->get_param('perpage'));
        } finally {
            $_POST = $previouspost;
            $_GET = $previousget;
        }
    }

    /**
     * Clear filters also resets initials that Moodle's table stores in the session.
     *
     * @covers \local_mailwhistle\form\audience_filter_form
     */
    public function test_clear_filters_link_resets_initials(): void {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/formslib.php');
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'audience']));
        $form = new audience_filter_form(null, ['tags' => [], 'auths' => []], 'get');
        $dom = new \DOMDocument();
        $dom->loadHTML($form->render(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//a[normalize-space(.)="Clear filters"]');

        $this->assertSame(1, $links->length);
        parse_str(parse_url($links->item(0)->getAttribute('href'), PHP_URL_QUERY), $params);
        $this->assertSame(['tab' => 'audience', 'tifirst' => '', 'tilast' => ''], $params);
    }
}
