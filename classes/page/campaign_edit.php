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

use local_mailwhistle\form\campaign_audience_form;
use local_mailwhistle\form\campaign_content_form;
use local_mailwhistle\form\campaign_details_form;
use local_mailwhistle\helper;
use local_mailwhistle\manager\audience_manager;
use local_mailwhistle\manager\campaign_manager;
use local_mailwhistle\manager\send_manager;
use local_mailwhistle\manager\tag_manager;
use local_mailwhistle\manager\template_manager;
use local_mailwhistle\output\resources;
use local_mailwhistle\output\template_picker;

/**
 * Draft campaign edit wizard.
 *
 * @package   local_mailwhistle
 * @copyright 2026 onwards MoodleDach project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_edit {
    /** @var string[] Ordered wizard steps. */
    public const STEPS = ['details', 'content', 'audience', 'review'];

    /**
     * Run the wizard for the current request.
     *
     * @return void
     */
    public static function execute(): void {
        global $DB, $OUTPUT, $PAGE, $USER;

        require_login();
        $context = \context_system::instance();
        require_capability('local/mailwhistle:manage', $context);

        $campaignid = optional_param('campaignid', 0, PARAM_INT);
        if (empty($campaignid)) {
            helper::require_post();
            require_sesskey();
            $campaignid = helper::create_campaign('');
            redirect(new \moodle_url('/local/mailwhistle/campaign_edit.php', [
                'campaignid' => $campaignid,
                'step' => 'details',
            ]));
        }

        $campaign = $DB->get_record('local_mailwhistle_campaigns', ['id' => $campaignid], '*', MUST_EXIST);
        $step = optional_param('step', 'details', PARAM_ALPHA);
        if (!in_array($step, self::STEPS, true)) {
            $step = 'details';
        }

        $returnurl = new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'send']);
        $baseurl = new \moodle_url('/local/mailwhistle/campaign_edit.php', ['campaignid' => $campaignid]);
        $stepurl = self::step_url($baseurl, $step);

        $PAGE->set_context($context);
        $PAGE->set_url($stepurl);
        $PAGE->set_pagelayout('standard');
        $PAGE->set_title(get_string('editcampaign', 'local_mailwhistle'));
        $PAGE->set_heading(get_string('pluginname', 'local_mailwhistle'));
        $PAGE->requires->css(new \moodle_url('/local/mailwhistle/styles.css'));

        if ($campaign->status !== campaign_manager::STATUS_DRAFT) {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('editcampaign', 'local_mailwhistle'));
            echo $OUTPUT->notification(
                get_string('editcampaign_notdraft', 'local_mailwhistle'),
                \core\output\notification::NOTIFY_INFO
            );
            echo \html_writer::div(
                \html_writer::link($returnurl, get_string('backtolist', 'local_mailwhistle'))
            );
            echo $OUTPUT->footer();
            return;
        }

        match ($step) {
            'details' => self::details($campaign, $baseurl, $returnurl),
            'content' => self::content($campaign, $baseurl, $returnurl),
            'audience' => self::audience($campaignid, $baseurl, $returnurl),
            default => self::review($campaign, $baseurl, $returnurl, $context, $USER),
        };
    }

    /**
     * Build the URL for a given wizard step.
     *
     * @param \moodle_url $baseurl The campaign base URL.
     * @param string $step The step key.
     * @return \moodle_url
     */
    public static function step_url(\moodle_url $baseurl, string $step): \moodle_url {
        return new \moodle_url($baseurl, ['step' => $step]);
    }

    /**
     * Return the next wizard step, staying on review when already there.
     *
     * @param string $current Current step key.
     * @return string
     */
    public static function next_step(string $current): string {
        $pos = array_search($current, self::STEPS, true);
        if ($pos === false) {
            return self::STEPS[0];
        }
        return self::STEPS[min($pos + 1, count(self::STEPS) - 1)];
    }

    /**
     * Render the wizard progress tabs.
     *
     * @param string $active Active step key.
     * @param \moodle_url $baseurl Campaign base URL.
     * @return string
     */
    private static function render_tabs(string $active, \moodle_url $baseurl): string {
        global $OUTPUT;

        $tabs = [];
        foreach (self::STEPS as $step) {
            $tabs[] = new \tabobject(
                $step,
                self::step_url($baseurl, $step),
                get_string('wizardstep_' . $step, 'local_mailwhistle')
            );
        }
        return $OUTPUT->tabtree($tabs, $active);
    }

    /**
     * Details step: name and sender.
     *
     * @param \stdClass $campaign Campaign record.
     * @param \moodle_url $baseurl Campaign base URL.
     * @param \moodle_url $returnurl Send-tab URL.
     * @return void
     */
    private static function details(\stdClass $campaign, \moodle_url $baseurl, \moodle_url $returnurl): void {
        global $OUTPUT;

        $mform = new campaign_details_form(self::step_url($baseurl, 'details')->out(false));
        if ($mform->is_cancelled()) {
            redirect($returnurl);
        } else if ($data = $mform->get_data()) {
            campaign_manager::update_fields((int) $campaign->id, [
                'name' => $data->name,
                'sendername' => $data->sendername,
                'senderemail' => $data->senderemail,
            ]);
            redirect(self::step_url($baseurl, self::next_step('details')));
        }
        $mform->set_data([
            'campaignid' => $campaign->id,
            'name' => $campaign->name,
            'sendername' => $campaign->sendername,
            'senderemail' => $campaign->senderemail,
        ]);

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('editcampaign', 'local_mailwhistle'));
        echo self::render_tabs('details', $baseurl);
        $mform->display();
        echo $OUTPUT->footer();
    }

    /**
     * Content step: subject, body, optional template picker.
     *
     * @param \stdClass $campaign Campaign record.
     * @param \moodle_url $baseurl Campaign base URL.
     * @param \moodle_url $returnurl Send-tab URL.
     * @return void
     */
    private static function content(
        \stdClass $campaign,
        \moodle_url $baseurl,
        \moodle_url $returnurl
    ): void {
        global $OUTPUT;

        $templateid = optional_param('templateid', 0, PARAM_INT);
        $mform = new campaign_content_form(self::step_url($baseurl, 'content')->out(false));
        if ($mform->is_cancelled()) {
            redirect($returnurl);
        } else if ($data = $mform->get_data()) {
            $bodyhtml = $data->body['text'] ?? '';
            $attachments = $data->attachments ?? [];
            if (!is_array($attachments)) {
                $attachments = $attachments === '' || $attachments === null ? [] : [$attachments];
            }
            $attachments = array_values(array_intersect($attachments, array_keys(resources::get_all_files())));
            campaign_manager::update_fields((int) $campaign->id, [
                'subject' => $data->subject,
                'bodyhtml' => $bodyhtml,
                'bodytext' => html_to_text($bodyhtml),
                'attachmentsjson' => campaign_manager::encode_attachments($attachments),
            ]);
            $postedtemplateid = (int) ($data->templateid ?? 0);
            if ($postedtemplateid > 0) {
                campaign_manager::set_templateid((int) $campaign->id, $postedtemplateid);
            }
            redirect(self::step_url($baseurl, self::next_step('content')));
        }

        $prefillsubject = (string) $campaign->subject;
        $prefillbody = (string) $campaign->bodyhtml;
        $prefilltemplateid = (int) ($campaign->templateid ?? 0);
        if ($templateid) {
            $template = template_manager::get($templateid);
            if ($template) {
                $prefillbody = (string) $template->bodyhtml;
                $prefilltemplateid = $templateid;
                if (trim($prefillsubject) === '') {
                    $prefillsubject = (string) $template->name;
                }
            }
        }
        $mform->set_data([
            'campaignid' => $campaign->id,
            'templateid' => $prefilltemplateid,
            'subject' => $prefillsubject,
            'body' => ['text' => $prefillbody, 'format' => FORMAT_HTML],
            'attachments' => campaign_manager::decode_attachments($campaign->attachmentsjson ?? ''),
        ]);

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('editcampaign', 'local_mailwhistle'));
        echo self::render_tabs('content', $baseurl);
        echo $OUTPUT->render(new template_picker((int) $campaign->id, $templateid));
        $mform->display();
        echo $OUTPUT->footer();
    }

    /**
     * Audience step: tag selection.
     *
     * @param int $campaignid Campaign id.
     * @param \moodle_url $baseurl Campaign base URL.
     * @param \moodle_url $returnurl Send-tab URL.
     * @return void
     */
    private static function audience(int $campaignid, \moodle_url $baseurl, \moodle_url $returnurl): void {
        global $OUTPUT;

        $tags = audience_manager::get_campaign_tagids($campaignid);
        $hastags = !empty(tag_manager::get_all_tags());
        if (!$hastags) {
            $audienceurl = new \moodle_url('/local/mailwhistle/index.php', ['tab' => 'audience']);
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('editcampaign', 'local_mailwhistle'));
            echo self::render_tabs('audience', $baseurl);
            echo $OUTPUT->notification(
                get_string('audiencetags_notags', 'local_mailwhistle'),
                \core\output\notification::NOTIFY_INFO
            );
            echo \html_writer::div(
                \html_writer::link(
                    $audienceurl,
                    get_string('audiencetags_managetags', 'local_mailwhistle'),
                    ['class' => 'btn btn-secondary mb-3']
                )
            );
            echo \html_writer::div(
                \html_writer::link(
                    self::step_url($baseurl, 'review'),
                    get_string('wizard_savecontinue', 'local_mailwhistle'),
                    ['class' => 'btn btn-primary']
                )
            );
            echo $OUTPUT->footer();
            return;
        }

        $mform = new campaign_audience_form(self::step_url($baseurl, 'audience')->out(false));
        if ($mform->is_cancelled()) {
            redirect($returnurl);
        } else if ($data = $mform->get_data()) {
            audience_manager::set_campaign_tags($campaignid, campaign_audience_form::get_checked_tagids($data));
            redirect(self::step_url($baseurl, self::next_step('audience')));
        }
        $mform->set_data(['campaignid' => $campaignid]);
        $mform->set_selected_tags($tags);

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('editcampaign', 'local_mailwhistle'));
        echo self::render_tabs('audience', $baseurl);
        $mform->display();
        echo $OUTPUT->footer();
    }

    /**
     * Review step: summary, test mail, mark complete.
     *
     * @param \stdClass $campaign Campaign record.
     * @param \moodle_url $baseurl Campaign base URL.
     * @param \moodle_url $returnurl Send-tab URL.
     * @param \context $context System context.
     * @param \stdClass $user Current user.
     * @return void
     */
    private static function review(
        \stdClass $campaign,
        \moodle_url $baseurl,
        \moodle_url $returnurl,
        \context $context,
        \stdClass $user
    ): void {
        global $OUTPUT;

        $campaignid = (int) $campaign->id;
        $action = optional_param('action', '', PARAM_ALPHA);
        if ($action === 'complete') {
            helper::require_post();
            require_sesskey();
            campaign_manager::mark_complete($campaignid);
            redirect(
                $returnurl,
                get_string('editcampaign_completed', 'local_mailwhistle'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        if ($action === 'sendtest') {
            helper::require_post();
            require_sesskey();
            $sent = send_manager::send_test($campaignid, $user);
            if ($sent) {
                $testmsg = get_string('testmail_sent', 'local_mailwhistle', s($user->email));
                $testtype = \core\output\notification::NOTIFY_SUCCESS;
            } else {
                $testmsg = get_string('testmail_failed', 'local_mailwhistle');
                $testtype = \core\output\notification::NOTIFY_ERROR;
            }
            redirect(self::step_url($baseurl, 'review'), $testmsg, null, $testtype);
        }

        $iscomplete = campaign_manager::is_complete($campaignid);
        $tagids = audience_manager::get_campaign_tagids($campaignid);

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('editcampaign', 'local_mailwhistle'));
        echo self::render_tabs('review', $baseurl);

        $summary = new \html_table();
        $summary->attributes['class'] = 'generaltable table w-auto local-mailwhistle-review mb-3';
        $summary->data = [
            [get_string('internalname', 'local_mailwhistle'), format_string($campaign->name, true, ['context' => $context])],
            [get_string('subject', 'local_mailwhistle'), format_string($campaign->subject, true, ['context' => $context])],
            [get_string('sendername', 'local_mailwhistle'), format_string($campaign->sendername, true, ['context' => $context])],
            [get_string('senderemail', 'local_mailwhistle'), s($campaign->senderemail)],
            [get_string('audiencetags_label', 'local_mailwhistle'), count($tagids)],
            [get_string('campaign_attachments', 'local_mailwhistle'), self::format_attachment_names($campaign)],
            [get_string('body', 'local_mailwhistle'), format_text(
                (string) $campaign->bodyhtml,
                FORMAT_HTML,
                ['context' => $context]
            )],
        ];
        echo \html_writer::table($summary);
        echo \html_writer::start_tag('div', ['class' => 'd-flex gap-2']);

        $testurl = new \moodle_url($baseurl, ['step' => 'review', 'action' => 'sendtest', 'sesskey' => sesskey()]);
        echo \html_writer::div(
            $OUTPUT->single_button($testurl, get_string('testmail_send', 'local_mailwhistle'), 'post'),
            'local-mailwhistle-testmail mb-3'
        );

        if ($iscomplete) {
            $completeurl = new \moodle_url($baseurl, [
                'step' => 'review',
                'action' => 'complete',
                'sesskey' => sesskey(),
            ]);
            echo \html_writer::div(
                $OUTPUT->single_button(
                    $completeurl,
                    get_string('editcampaign_markcomplete', 'local_mailwhistle'),
                    'post'
                ),
                'local-mailwhistle-complete mb-3'
            );
        } else {
            echo $OUTPUT->notification(
                get_string('editcampaign_incomplete', 'local_mailwhistle'),
                \core\output\notification::NOTIFY_WARNING
            );
        }

        echo \html_writer::div(
            \html_writer::link(
                $returnurl,
                get_string('backtolist', 'local_mailwhistle'),
                ['class' => 'btn btn-secondary mb-3']
            )
        );
        echo \html_writer::end_div();
        echo $OUTPUT->footer();
    }

    /**
     * Comma-separated attachment filenames for the review table.
     *
     * @param \stdClass $campaign Campaign record.
     * @return string Escaped filename list, or a none-selected string.
     */
    private static function format_attachment_names(\stdClass $campaign): string {
        $names = campaign_manager::decode_attachments($campaign->attachmentsjson ?? '');
        if (!$names) {
            return get_string('campaign_attachments_none', 'local_mailwhistle');
        }
        return s(implode(', ', $names));
    }
}
