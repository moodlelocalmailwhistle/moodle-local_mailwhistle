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
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mailwhistle\reportbuilder\local\entities;

use core\lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use local_mailwhistle\manager\tracking_manager;
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

    /**
     * Add the entity's columns to the report.
     *
     * The base class only gained a default implementation of this method in
     * Moodle 5.2; declaring it here keeps the entity compatible with the 5.0
     * and 5.1 branches this plugin supports, where the method is abstract.
     * This entity only defines columns, so no filters or conditions are added.
     *
     * @return base
     */
    public function initialise(): base {
        foreach ($this->get_available_columns() as $column) {
            $this->add_column($column);
        }

        return $this;
    }

    /**
     * Return the columns available for this entity.
     *
     * No #[\Override] attribute is used here: get_available_columns() is not a
     * parent method on the Moodle 5.0/5.1 base entity (it was formalised in
     * 5.2), so the attribute would fatal on those branches.
     *
     * @return column[]
     */
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

        // Name link.
        $columns[] = (new column(
            'namelink',
            new lang_string('report:name', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.name")
            ->add_field("$campaignsalias.id")
            ->add_field("$campaignsalias.status")
            ->add_callback([campaignhelper::class, 'namelink']);

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
            ->add_field("$campaignsalias.id")
            ->add_callback([campaignhelper::class, 'audience']);

        // Recipients.
        $columns[] = (new column(
            'recipients',
            new lang_string('report:recipients', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field(
                "(SELECT COUNT(1) FROM {local_mailwhistle_recipients} WHERE campaignid = {$campaignsalias}.id)",
                'recipients'
            );

        // Template.
        $columns[] = (new column(
            'template',
            new lang_string('report:template', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.templateid")
            ->add_callback([campaignhelper::class, 'templatename']);

        // Unique opens.
        $open = tracking_manager::EVENT_OPEN;
        $columns[] = (new column(
            'uniqueopens',
            new lang_string('report:opens', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field(
                "(SELECT COUNT(1) FROM {local_mailwhistle_tracking}
                  WHERE campaignid = {$campaignsalias}.id AND eventtype = '{$open}')",
                'uniqueopens'
            );

        // Unique clickers.
        $click = tracking_manager::EVENT_CLICK;
        $columns[] = (new column(
            'uniqueclicks',
            new lang_string('report:clicks', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field(
                "(SELECT COUNT(DISTINCT recipientid) FROM {local_mailwhistle_tracking}
                  WHERE campaignid = {$campaignsalias}.id AND eventtype = '{$click}')",
                'uniqueclicks'
            );

        // Name linked to the Reports detail page.
        $columns[] = (new column(
            'reportnamelink',
            new lang_string('report:name', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.name")
            ->add_field("$campaignsalias.id")
            ->add_callback([campaignhelper::class, 'reportnamelink']);

        // Report-only columns keep the Campaigns tab's existing presentation unchanged.
        $columns[] = (new column(
            'reportsummary',
            new lang_string('reports_column_campaign', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.name")
            ->add_field("$campaignsalias.subject")
            ->add_field("$campaignsalias.id")
            ->set_is_sortable(true, ['name', 'id'])
            ->add_attributes(['class' => 'local-mailwhistle-report-col-campaign'])
            ->add_callback([campaignhelper::class, 'reportsummary']);

        $columns[] = (new column(
            'reportsentat',
            new lang_string('col_completedat', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.timesent")
            ->add_field("$campaignsalias.id")
            ->set_type(column::TYPE_INTEGER)
            ->set_is_sortable(true, ['timesent', 'id'])
            ->add_attributes(['class' => 'local-mailwhistle-report-col-date'])
            ->add_callback([campaignhelper::class, 'reportsentat']);

        $sentcount = "(SELECT COUNT(1) FROM {local_mailwhistle_recipients}
                       WHERE campaignid = {$campaignsalias}.id AND status = 'sent')";
        $columns[] = (new column(
            'reportdelivery',
            new lang_string('reports_column_delivery', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field($sentcount, 'sentcount')
            ->add_field("(SELECT COUNT(1) FROM {local_mailwhistle_recipients}
                          WHERE campaignid = {$campaignsalias}.id)", 'totalcount')
            ->add_field("(SELECT COUNT(1) FROM {local_mailwhistle_recipients}
                          WHERE campaignid = {$campaignsalias}.id AND status = 'failed')", 'failedcount')
            ->add_field("(SELECT COUNT(1) FROM {local_mailwhistle_recipients}
                          WHERE campaignid = {$campaignsalias}.id AND status IN ('pending', 'sending'))", 'pendingcount')
            ->add_field("(SELECT COUNT(1) FROM {local_mailwhistle_recipients}
                          WHERE campaignid = {$campaignsalias}.id AND status = 'skipped')", 'skippedcount')
            ->add_field("(SELECT COUNT(1) FROM {local_mailwhistle_recipients}
                          WHERE campaignid = {$campaignsalias}.id AND status = 'uncertain')", 'uncertaincount')
            ->set_is_sortable(true, ['sentcount'])
            ->add_attributes(['class' => 'local-mailwhistle-report-col-delivery'])
            ->add_callback([campaignhelper::class, 'reportdelivery']);

        $columns[] = (new column(
            'reportopens',
            new lang_string('report:opens', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("(SELECT COUNT(1) FROM {local_mailwhistle_tracking}
                          WHERE campaignid = {$campaignsalias}.id AND eventtype = '{$open}')", 'opencount')
            ->add_field($sentcount, 'sentcount')
            ->set_is_sortable(true, ['opencount'])
            ->add_attributes(['class' => 'local-mailwhistle-report-col-engagement'])
            ->add_callback([campaignhelper::class, 'reportopens']);

        $columns[] = (new column(
            'reportclicks',
            new lang_string('report:clicks', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("(SELECT COUNT(DISTINCT recipientid) FROM {local_mailwhistle_tracking}
                          WHERE campaignid = {$campaignsalias}.id AND eventtype = '{$click}')", 'clickcount')
            ->add_field($sentcount, 'sentcount')
            ->set_is_sortable(true, ['clickcount'])
            ->add_attributes(['class' => 'local-mailwhistle-report-col-engagement'])
            ->add_callback([campaignhelper::class, 'reportclicks']);

        // Sent by.
        $columns[] = (new column(
            'sentby',
            new lang_string('report:sentby', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.sendername");

        // Campaign completion time.
        $columns[] = (new column(
            'sentat',
            new lang_string('col_completedat', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.timesent")
            ->set_type(column::TYPE_INTEGER)
            ->add_callback([format::class, 'userdate']);

        // Planned delivery time is distinct from campaign completion.
        $columns[] = (new column(
            'timescheduled',
            new lang_string('scheduledfor', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.timescheduled")
            ->set_type(column::TYPE_INTEGER)
            ->set_is_sortable(true)
            ->add_callback([campaignhelper::class, 'timescheduled']);

        // Time created.
        $columns[] = (new column(
            'timecreated',
            new lang_string('report:timecreated', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("$campaignsalias.timecreated")
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

        // Actions.
        $columns[] = (new column(
            'actions',
            new lang_string('report:actions', 'local_mailwhistle'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("$campaignsalias.id")
            ->add_field("$campaignsalias.status")
            ->add_field("$campaignsalias.name")
            ->add_callback([campaignhelper::class, 'actions']);

        return $columns;
    }
}
