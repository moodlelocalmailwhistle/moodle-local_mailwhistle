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
 * Report of campaigns
 *
 * @package   local_mailwhistle
 * @copyright 2026 Synergy Learning
 * @author    Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mailwhistle\reportbuilder\local\systemreports;

use core_reportbuilder\system_report;
use local_mailwhistle\reportbuilder\local\entities\campaign;

/**
 * Report of campaigns
 */
class campaigns extends system_report {

    /**
     * @inheritDoc
     */
    protected function initialise(): void {
        $this->set_main_table('local_mailwhistle_campaigns', 'campaigns');

        $entitycampaign = (new campaign())->set_table_alias('local_mailwhistle_campaigns', 'campaigns');
        $this->add_entity($entitycampaign);

        $this->add_columns();
    }

    protected function add_columns(): void {
        $this->add_column_from_entity('campaign:name');
        $this->add_column_from_entity('campaign:subject');
        $this->add_column_from_entity('campaign:audience');
        $this->add_column_from_entity('campaign:recipients');
        $this->add_column_from_entity('campaign:sentby');
        $this->add_column_from_entity('campaign:sentat');
        $this->add_column_from_entity('campaign:status');
    }

    /**
     * @inheritDoc
     */
    protected function can_view(): bool {
        return has_capability('local/mailwhistle:view', \context_system::instance());
    }
}
