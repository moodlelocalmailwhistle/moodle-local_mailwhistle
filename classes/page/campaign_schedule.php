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

namespace local_mailwhistle\page;

use local_mailwhistle\form\campaign_schedule_form;
use local_mailwhistle\helper;
use local_mailwhistle\manager\campaign_manager;

/**
 * Schedule confirmation and cancellation page for campaign managers.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_schedule {
    /**
     * Run the schedule page, handling writes before any output.
     *
     * @return void
     */
    public static function execute(): void {
        global $DB, $OUTPUT, $PAGE;

        require_login();
        $context = \context_system::instance();
        require_capability('local/mailwhistle:manage', $context);
        $campaignid = required_param('campaignid', PARAM_INT);
        $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
        $url = new \moodle_url('/local/mailwhistle/campaign_schedule.php', ['campaignid' => $campaignid]);
        $returnurl = new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'send']);
        $PAGE->set_context($context);
        $PAGE->set_url($url);
        $PAGE->set_pagelayout('standard');
        $PAGE->set_title(get_string('schedulecampaign', 'local_mailwhistle'));
        $PAGE->set_heading(get_string('pluginname', 'local_mailwhistle'));
        $PAGE->navbar->add(get_string('campaigns', 'local_mailwhistle'), $returnurl);
        $PAGE->navbar->add(get_string('schedulecampaign', 'local_mailwhistle'));

        if (optional_param('action', '', PARAM_ALPHA) === 'cancelschedule') {
            helper::require_post();
            require_sesskey();
            $cancelled = campaign_manager::cancel_schedule($campaignid);
            redirect(
                $returnurl,
                get_string($cancelled ? 'schedulecancelled' : 'schedulechanged', 'local_mailwhistle'),
                null,
                $cancelled ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
            );
        }

        $form = null;
        $readiness = campaign_manager::get_readiness($campaignid);
        $hassnapshot = $DB->record_exists('local_mailwhistle_recipients', ['campaignid' => $campaignid]);
        if ($hassnapshot) {
            $readiness['errors'][] = get_string('campaignhassnapshot', 'local_mailwhistle');
        }
        if ($campaign->status === campaign_manager::STATUS_READY && !$readiness['errors']) {
            $form = new campaign_schedule_form($url);
            $form->set_data(['campaignid' => $campaignid, 'timescheduled' => time() + HOURSECS]);
            if ($form->is_cancelled()) {
                redirect($returnurl);
            }
            if ($form->is_submitted()) {
                helper::require_post();
                require_sesskey();
            }
            if ($data = $form->get_data()) {
                if (campaign_manager::schedule($campaignid, (int) $data->timescheduled)) {
                    redirect(
                        $url,
                        get_string('scheduleconfirmed', 'local_mailwhistle', self::format_time($data->timescheduled)),
                        null,
                        \core\output\notification::NOTIFY_SUCCESS
                    );
                }
                redirect(
                    $url,
                    get_string('schedulechanged', 'local_mailwhistle'),
                    null,
                    \core\output\notification::NOTIFY_WARNING
                );
            }
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('schedulecampaign', 'local_mailwhistle'));
        echo $OUTPUT->heading(format_string($campaign->name, true, ['context' => $context]), 3);
        echo \html_writer::tag('p', get_string('schedulehelp', 'local_mailwhistle'));
        echo \html_writer::tag('p', get_string('scheduleaudiencehelp', 'local_mailwhistle'));
        if ($campaign->status === campaign_manager::STATUS_SCHEDULE_FAILED) {
            echo $OUTPUT->notification(
                get_string('schedulefailedhelp', 'local_mailwhistle'),
                \core\output\notification::NOTIFY_WARNING
            );
        }
        if ($readiness['errors']) {
            echo $OUTPUT->notification(
                \html_writer::alist(array_map('s', $readiness['errors'])),
                \core\output\notification::NOTIFY_WARNING
            );
        }
        if (in_array($campaign->status, [campaign_manager::STATUS_SCHEDULED, campaign_manager::STATUS_SCHEDULE_FAILED], true)) {
            echo \html_writer::tag('p', s(get_string(
                'scheduleconfirmed',
                'local_mailwhistle',
                self::format_time((int) $campaign->timescheduled)
            )));
            if (!$hassnapshot) {
                echo \html_writer::tag('p', get_string('schedulecancelhelp', 'local_mailwhistle'));
                $cancelurl = new \moodle_url($url, ['action' => 'cancelschedule']);
                echo $OUTPUT->single_button($cancelurl, get_string('cancelschedule', 'local_mailwhistle'), 'post');
            }
        } else if ($form) {
            $form->display();
        } else {
            echo $OUTPUT->notification(
                get_string('schedulenotready', 'local_mailwhistle'),
                \core\output\notification::NOTIFY_INFO
            );
            if ($campaign->status === campaign_manager::STATUS_READY) {
                echo \html_writer::link(
                    new \moodle_url('/local/mailwhistle/campaign_edit.php', ['campaignid' => $campaignid]),
                    get_string('editcampaign', 'local_mailwhistle'),
                    ['class' => 'btn btn-secondary']
                );
            }
        }
        echo \html_writer::div(\html_writer::link($returnurl, get_string('backtocampaigns', 'local_mailwhistle')), 'mt-3');
        echo $OUTPUT->footer();
    }

    /**
     * Format the scheduled instant in the viewer's effective timezone.
     *
     * @param int $timestamp Scheduled Unix timestamp.
     * @return string Local date, time, and timezone.
     */
    public static function format_time(int $timestamp): string {
        return userdate($timestamp) . ' (' . \core_date::get_user_timezone_object()->getName() . ')';
    }
}
