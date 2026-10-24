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

/**
 * Access to the plugin must follow its capabilities rather than site configuration access.
 *
 * @package local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class campaign_access_test extends \advanced_testcase {
    /**
     * The default system Manager can reach the external page without site:config.
     */
    public function test_default_manager_can_access_mailings(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        $this->setUser($user);
        $this->assertFalse(has_capability('moodle/site:config', \context_system::instance()));
        $this->assertTrue(has_capability('local/mailwhistle:manage', \context_system::instance()));
        $page = admin_get_root(true, true)->locate('local_mailwhistle_mailings');
        $this->assertInstanceOf(\admin_externalpage::class, $page);
        $this->assertTrue($page->check_access());
    }

    /**
     * Registering the page does not grant access to users without the plugin capability.
     */
    public function test_ordinary_user_cannot_access_mailings(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $page = admin_get_root(true, true)->locate('local_mailwhistle_mailings');
        $this->assertInstanceOf(\admin_externalpage::class, $page);
        $this->assertFalse($page->check_access());
    }
}
