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
 * Report entity for campaigns
 *
 * @package   local_mailwhistle
 * @copyright 2026 Synergy Learning
 * @author    Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mailwhistle\reportbuilder\local\entities;

use core\lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use local_mailwhistle\reportbuilder\local\helper\campaign as campaignhelper;

/**
 * Report entity for campaigns
 */
class campaign extends base {

    /**
     * Get the list of tables used by this entity.
     */
    protected function get_default_tables(): array {
        return [
            'local_mailwhistle_campaigns',
        ];
    }

    /**
     * Default title for this entity.
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('campaign', 'local_mailwhistle');
    }

    protected function get_available_columns(): array {
        $campaignsalias = $this->get_table_alias('local_mailwhistle_campaigns');

        $columns = [];

        // Name.
        $columns[] = (new column(
            'name',
            new lang_string('report:name', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.name");

        // Subject.
        $columns[] = (new column(
            'subject',
            new lang_string('report:subject', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.subject");

        // Audience.
        $columns[] = (new column(
            'audience',
            new lang_string('report:audience', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("'TODO'", 'audience');

        // Recipients.
        $columns[] = (new column(
            'recipients',
            new lang_string('report:recipients', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field(
                "(SELECT COUNT(1) FROM {local_mailwhistle_recipients} WHERE campaignid = {$campaignsalias}.id)",
                'recipents'
            );

        // Sent by.
        $columns[] = (new column(
            'sentby',
            new lang_string('report:sentby', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.sendername");

        // Sent at.
        $columns[] = (new column(
            'sentat',
            new lang_string('report:sentat', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.timesent")
            ->set_type(column::TYPE_INTEGER)
            ->add_callback([format::class, 'userdate']);

        // Status.
        $columns[] = (new column(
            'status',
            new lang_string('report:status', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.status")
            ->add_callback([campaignhelper::class, 'status']);

        return $columns;
    }
}
