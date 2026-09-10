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
 * Report of sent and sending campaigns for the Reports tab.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mailwhistle\reportbuilder\local\systemreports;

use core_reportbuilder\local\helpers\database;
use core_reportbuilder\system_report;
use lang_string;
use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\reportbuilder\local\entities\campaign;

/**
 * Report of sent and sending campaigns for the Reports tab.
 */
class campaign_reports extends system_report {
    #[\Override]
    protected function initialise(): void {
        $this->set_main_table('local_mailwhistle_campaigns', 'campaigns');

        $sent = database::generate_param_name();
        $sending = database::generate_param_name();
        $this->add_base_condition_sql(
            "campaigns.status IN (:$sent, :$sending)",
            [
                $sent => campaign_manager::STATUS_SENT,
                $sending => campaign_manager::STATUS_SENDING,
            ]
        );

        $templateid = $this->get_parameter('templateid', 0, PARAM_INT);
        if ($templateid > 0) {
            $this->add_base_condition_simple('campaigns.templateid', $templateid);
        }

        $entitycampaign = (new campaign())->set_table_alias('local_mailwhistle_campaigns', 'campaigns');
        $this->add_entity($entitycampaign);

        $this->add_columns();

        $this->set_default_no_results_notice(new lang_string('nosentmails', 'local_mailwhistle'));
    }

    /**
     * Add columns to the report.
     *
     * @return void
     */
    protected function add_columns(): void {
        $this->add_column_from_entity('campaign:reportnamelink');
        $this->add_column_from_entity('campaign:audience');
        $this->add_column_from_entity('campaign:template');
        $this->add_column_from_entity('campaign:recipients');
        $this->add_column_from_entity('campaign:uniqueopens');
        $this->add_column_from_entity('campaign:uniqueclicks');
        $this->add_column_from_entity('campaign:status');
    }

    #[\Override]
    protected function can_view(): bool {
        return has_capability('local/mailwhistle:view', \context_system::instance());
    }
}
