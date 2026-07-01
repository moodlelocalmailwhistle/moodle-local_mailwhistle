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

use core_reportbuilder\local\helpers\database;
use core_reportbuilder\system_report;
use lang_string;
use local_mailwhistle\reportbuilder\local\entities\campaign;

/**
 * Report of campaigns
 */
class draft_campaigns extends system_report {

    /**
     * @inheritDoc
     */
    protected function initialise(): void {
        $this->set_main_table('local_mailwhistle_campaigns', 'campaigns');
        $draft = database::generate_param_name();
        $ready = database::generate_param_name();
        $this->add_base_condition_sql(
            "campaigns.status IN (:$draft, :$ready)",
            [
                $draft => \local_mailwhistle\manager\campaign_manager::STATUS_DRAFT,
                $ready => \local_mailwhistle\manager\campaign_manager::STATUS_READY,
            ]
        );

        $entitycampaign = (new campaign())->set_table_alias('local_mailwhistle_campaigns', 'campaigns');
        $this->add_entity($entitycampaign);

        $this->add_columns();

        $this->set_default_no_results_notice(new lang_string('nodraftcampaigns', 'local_mailwhistle'));
    }

    protected function add_columns(): void {
        $this->add_column_from_entity('campaign:namelink');
        $this->add_column_from_entity('campaign:status');
        $this->add_column_from_entity('campaign:timecreated');
        $this->add_column_from_entity('campaign:actions');
    }

    /**
     * @inheritDoc
     */
    protected function can_view(): bool {
        return has_capability('local/mailwhistle:view', \context_system::instance());
    }
}
