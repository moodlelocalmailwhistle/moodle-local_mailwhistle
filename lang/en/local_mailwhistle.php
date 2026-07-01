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
 * Local plugin "Mail Whistle" - Language strings (English).
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Let codechecker ignore some sniffs for this file as it is perfectly well ordered, just not alphabetically.
// phpcs:disable moodle.Files.LangFilesOrdering.UnexpectedComment
// phpcs:disable moodle.Files.LangFilesOrdering.IncorrectOrder

defined('MOODLE_INTERNAL') || die();

// Plugin metadata.
$string['campaign'] = 'Campaign';
$string['campaigns'] = 'Campaigns';
$string['pluginname']  = 'Mail Whistle';
$string['plugindesc']  = 'A reusable boilerplate for creating Moodle local plugins on Moodle 5 LTS and above.';

// Settings page heading and description.
$string['report:audience'] = 'Audience';
$string['report:name'] = 'Name';
$string['report:recipients'] = 'Recipients';
$string['report:sentat'] = 'Sent at';
$string['report:sentby'] = 'Sent by';
$string['report:status'] = 'Status';
$string['report:subject'] = 'Subject';
$string['setting_heading']      = 'Mail Whistle Settings';
$string['setting_heading_desc']  = 'Configure the Mail Whistle plugin settings.';

// Feature toggle setting (checkbox configuration).
$string['enable']        = 'Enable Feature';
$string['enable_desc']   = 'Enable or disable the main feature of this plugin.';

// API key setting (text input configuration).
$string['api_key']               = 'API Key';
$string['api_key_desc']          = 'Enter your API key for external service integration.';

// Select option setting (select menu configuration).
$string['select_option']         = 'Select Option';
$string['select_option_desc']    = 'Choose one of the available options.';
$string['option1']               = 'Option 1';
$string['option2']               = 'Option 2';
$string['option3']               = 'Option 3';

// Description setting (textarea configuration).
$string['description']           = 'Description';
$string['description_desc']      = 'Enter a description for this plugin configuration.';

// Capability descriptions for role-based access control.
$string['mailwhistle:view']        = 'View Mail Whistle plugin';
$string['mailwhistle:manage']      = 'Manage Mail Whistle plugin';
$string['mailwhistle:configure']   = 'Configure Mail Whistle plugin settings';
$string['mailwhistle:managetags']  = 'Manage audience tags (create, assign, unassign)';

// User-facing messages for operation feedback and error handling.
$string['success_message']       = 'Operation completed successfully.';
$string['error_message']         = 'An error occurred. Please try again.';

// Event names.
$string['event_data_created']    = 'Data record created';

// Tab labels for the main plugin page.
$string['tab_send']         = 'Campaigns';
$string['tab_audience']     = 'Audience';
$string['tab_templates']    = 'Templates';
$string['tab_reports']      = 'Reports';
$string['tab_resources']      = 'Resources';

// Headings and labels for the sent newsletters history table.
$string['sentmails_heading']    = 'Previously sent newsletters';
$string['col_subject']          = 'Subject';
$string['col_audience']         = 'Audience';
$string['col_recipients']       = 'Recipients';
$string['col_sentby']           = 'Sent by';
$string['col_sentat']           = 'Sent at';
$string['col_status']           = 'Status';
$string['status_draft']         = 'Draft';
$string['status_sent']          = 'Sent';
$string['status_sending']       = 'Sending';
$string['status_scheduled']     = 'Scheduled';
$string['status_failed']        = 'Failed';
$string['nosentmails']          = 'No newsletters have been sent yet.';

// Sent newsletter detail view.
$string['backtolist']           = '&laquo; Back to sent newsletters';
$string['mailnotfound']         = 'The requested newsletter could not be found.';
$string['mailpreview']          = 'Newsletter preview';

// Placeholder messages for tabs not yet implemented.
$string['templates_placeholder']    = 'Template management is coming soon.';
$string['reports_placeholder']      = 'Reporting and analytics are coming soon.';

// Campaign creation.
$string['createcampaign']          = 'Create a campaign';
$string['internalname']            = 'Internal email name';
$string['untitledcampaign']        = 'Untitled campaign';

// Campaign audience-tag selection (audience wizard step).
$string['audiencetags_desc']       = 'The campaign will be sent to everyone tagged with any of the selected tags.';
$string['audiencetags_label']      = 'Audience tags';
$string['audiencetags_none']       = 'No tags selected';
$string['audiencetags_submit']     = 'Save audience';
$string['audiencetags_notags']     = 'No audience tags exist yet. Create tags first, then set the campaign audience.';
$string['audiencetags_managetags'] = 'Manage audience tags';

// Draft campaign edit wizard.
$string['editcampaign']            = 'Edit campaign';
$string['editcampaign_notdraft']   = 'This campaign has left draft status and can no longer be edited here.';
$string['editcampaign_completed']  = 'Campaign marked as ready.';
$string['editcampaign_markcomplete'] = 'Mark campaign complete';
$string['editcampaign_incomplete'] = 'Add a name, subject, body and at least one audience tag before this campaign can be completed.';
$string['campaignincomplete']      = 'The campaign is not complete yet.';
$string['status_ready']            = 'Ready';
$string['wizardstep_details']      = '1. Details';
$string['wizardstep_content']      = '2. Content';
$string['wizardstep_audience']     = '3. Audience';
$string['wizardstep_review']       = '4. Review';
$string['wizard_savecontinue']     = 'Save and continue';
$string['subject']                 = 'Subject';
$string['body']                    = 'Email body';
$string['sendername']              = 'Sender name';
$string['senderemail']             = 'Sender email';

// Sending.
$string['col_actions']             = 'Actions';
$string['sendnow']                 = 'Send now';
$string['sendqueued']              = 'Campaign queued for sending.';
$string['sendnotready']            = 'This campaign is not ready to send.';
$string['sendbatchsize']           = 'Send batch size';
$string['sendbatchsize_desc']      = 'How many recipients each send task run delivers before re-queueing. Keep within your mail server limits.';
$string['task_requeue_stuck_sends'] = 'Resume stalled campaign sends';

// Draft campaigns section (Option D2).
$string['draftcampaigns_heading']  = 'Draft campaigns';
$string['nodraftcampaigns']        = 'No draft campaigns yet.';
$string['col_name']                = 'Internal name';
$string['col_created']             = 'Created';

// Privacy (GDPR) metadata.
$string['privacy:metadata:local_mailwhistle_campaigns']             = 'Information about email campaigns created in the plugin.';
$string['privacy:metadata:local_mailwhistle_campaigns:name']        = 'The internal name of the campaign.';
$string['privacy:metadata:local_mailwhistle_campaigns:subject']     = 'The subject line of the campaign.';
$string['privacy:metadata:local_mailwhistle_campaigns:status']      = 'The delivery status of the campaign.';
$string['privacy:metadata:local_mailwhistle_campaigns:createdby']   = 'The ID of the user who created the campaign.';
$string['privacy:metadata:local_mailwhistle_campaigns:timecreated'] = 'The time the campaign was created.';

$string['settings'] = 'Settings';
$string['resources'] = 'Resources';

// Audience tab strings.

// Table column headers.
$string['col_user']             = 'Full name';
$string['col_email']            = 'Email';
$string['col_tags']             = 'Tags';

// Filter form labels and options.
$string['filter_search']        = 'Search name / email';
$string['filter_tag']           = 'Tag';
$string['filter_suspended']     = 'Status';
$string['filter_auth']          = 'Authentication';
$string['filter_any']           = 'Any';
$string['filter_active']        = 'Active only';
$string['filter_suspended_only'] = 'Suspended only';

// Apply-tag form controls.
$string['apply_tag']            = 'Apply tag';
$string['apply_tag_choose']     = 'Choose a tag…';
$string['new_tag']              = 'New tag name';
$string['applybtn']             = 'Apply';

// Per-row tag chip controls.
$string['remove_tag']           = 'Remove';
$string['confirm_remove_tag']   = 'Remove this tag from the user';
$string['no_tags']              = 'No tags';

// Action success / feedback messages.
$string['tag_created']          = 'Tag created.';
$string['tag_assigned_n']       = '{$a} user(s) tagged successfully.';
$string['tag_removed']          = 'Tag removed.';
$string['noselection']          = 'Select at least one user and a tag to apply.';

// Empty state.
$string['noaudience']           = 'No users match the current filter.';

// Audience event strings.

$string['event_tag_created']    = 'Audience tag created';
$string['event_tag_assigned']   = 'Audience tag assigned to user';
$string['event_tag_unassigned'] = 'Audience tag removed from user';

// Privacy / GDPR metadata strings.

// Table: local_mailwhistle_tag_assign.
$string['privacy:metadata:local_mailwhistle_tag_assign']             = 'Stores which users have been assigned each audience tag, and who performed the assignment.';
$string['privacy:metadata:local_mailwhistle_tag_assign:tagid']       = 'The ID of the audience tag that was assigned.';
$string['privacy:metadata:local_mailwhistle_tag_assign:userid']      = 'The ID of the user who was tagged.';
$string['privacy:metadata:local_mailwhistle_tag_assign:usermodified'] = 'The ID of the user who performed the tag assignment.';
$string['privacy:metadata:local_mailwhistle_tag_assign:timecreated'] = 'The date and time when the tag was assigned.';

// Table: local_mailwhistle_tag.
$string['privacy:metadata:local_mailwhistle_tag']                    = 'Stores audience tag definitions; records who created or last modified each tag.';
$string['privacy:metadata:local_mailwhistle_tag:name']               = 'The display name of the tag.';
$string['privacy:metadata:local_mailwhistle_tag:usermodified']       = 'The ID of the user who created or last modified the tag definition.';
$string['privacy:metadata:local_mailwhistle_tag:timecreated']        = 'The date and time when the tag definition was created.';
$string['privacy:metadata:local_mailwhistle_tag:timemodified']       = 'The date and time when the tag definition was last modified.';

// Export path labels (used by the privacy export writer).
$string['privacy:path:tag_assignments']  = 'Audience tag assignments';
$string['privacy:path:authored_tags']    = 'Authored tag definitions';

// Privacy API metadata descriptions (campaign recipients and unsubscribes).
$string['privacy:metadata:local_mailwhistle_recipients'] = 'Per-recipient delivery records for email campaigns.';
$string['privacy:metadata:local_mailwhistle_recipients:userid'] = 'The ID of the recipient user.';
$string['privacy:metadata:local_mailwhistle_recipients:email'] = 'The email address the campaign was sent to.';
$string['privacy:metadata:local_mailwhistle_recipients:firstname'] = 'The recipient first name captured at send time.';
$string['privacy:metadata:local_mailwhistle_recipients:lastname'] = 'The recipient last name captured at send time.';
$string['privacy:metadata:local_mailwhistle_recipients:status'] = 'The delivery status for the recipient.';
$string['privacy:metadata:local_mailwhistle_recipients:timesent'] = 'The time the campaign was sent to the recipient.';
$string['privacy:metadata:local_mailwhistle_tracking'] = 'Open and click tracking events recorded for a recipient.';
$string['privacy:metadata:local_mailwhistle_tracking:recipientid'] = 'The recipient the tracking event belongs to.';
$string['privacy:metadata:local_mailwhistle_tracking:eventtype'] = 'The type of event (open or click).';
$string['privacy:metadata:local_mailwhistle_tracking:targeturl'] = 'The URL that was clicked (for click events).';
$string['privacy:metadata:local_mailwhistle_tracking:timecreated'] = 'The time the event was recorded.';
$string['privacy:metadata:local_mailwhistle_unsubscribes'] = 'Records of users who unsubscribed from campaigns.';
$string['privacy:metadata:local_mailwhistle_unsubscribes:userid'] = 'The ID of the user who unsubscribed.';
$string['privacy:metadata:local_mailwhistle_unsubscribes:email'] = 'The email address that unsubscribed.';
$string['privacy:metadata:local_mailwhistle_unsubscribes:timecreated'] = 'The time the unsubscribe was recorded.';

// Template management.
$string['templates_heading'] = 'Email templates';
$string['templates_empty'] = 'No templates have been created yet.';
$string['templates_empty_active'] = 'No active templates have been created yet.';
$string['templates_empty_archived'] = 'No archived templates.';
$string['templates_empty_all'] = 'No templates have been created yet.';
$string['template_create'] = 'Create template';
$string['template_create_heading'] = 'Create template';
$string['template_edit_heading'] = 'Edit template';
$string['template_name'] = 'Template name';
$string['template_previewtext'] = 'Preview text';
$string['template_background'] = 'Email background';
$string['template_background_invalid'] = 'Use a hex color such as #ffffff.';
$string['template_bodyhtml'] = 'HTML body';
$string['template_editormode'] = 'Editor mode';
$string['template_editormode_builder'] = 'Builder mode';
$string['template_editormode_html'] = 'HTML mode';
$string['template_save'] = 'Save template';
$string['template_saved'] = 'Template saved.';
$string['template_preview'] = 'Preview';
$string['template_edit'] = 'Edit';
$string['template_actions'] = 'Actions';
$string['template_export'] = 'Export JSON';
$string['template_archive'] = 'Archive';
$string['template_archived'] = 'Template archived.';
$string['template_restore'] = 'Restore';
$string['template_restored'] = 'Template restored.';
$string['template_deleted'] = 'Template deleted.';
$string['template_archived_badge'] = 'Archived';
$string['template_filter_active'] = 'Active';
$string['template_filter_archived'] = 'Archived';
$string['template_filter_all'] = 'All';
$string['template_delete_confirm'] = 'Delete template "{$a}" permanently? This is only allowed for templates that have never been used.';
$string['template_delete_used_blocked'] = 'This template has been used and cannot be deleted. Archive it instead.';
$string['templatecannotdeleteused'] = 'This template has been used and cannot be deleted.';
$string['template_no_body'] = 'No design yet.';
$string['template_previewtext_label'] = 'Preview text:';
$string['template_lastedited_label'] = 'Last edited:';
$string['backtotemplates'] = '&laquo; Back to templates';
$string['templatenotfound'] = 'The requested template could not be found.';
$string['template_builder_heading'] = 'Template builder';
$string['template_builder_add_header'] = 'Header';
$string['template_builder_add_logo'] = 'Logo';
$string['template_builder_add_text'] = 'Text';
$string['template_builder_add_button'] = 'Button';
$string['template_builder_add_image'] = 'Image';
$string['template_builder_add_highlight'] = 'Highlight';
$string['template_builder_add_social'] = 'Social links';
$string['template_builder_add_columns'] = 'Two columns';
$string['template_builder_add_divider'] = 'Divider';
$string['template_builder_add_footer'] = 'Footer';
$string['template_builder_drag'] = 'Drag to reorder';
$string['template_builder_title'] = 'Title';
$string['template_builder_subtitle'] = 'Subtitle';
$string['template_builder_content'] = 'Content';
$string['template_builder_label'] = 'Label';
$string['template_builder_url'] = 'URL';
$string['template_builder_alt'] = 'Alt text';
$string['template_builder_background'] = 'Background';
$string['template_builder_color'] = 'Text color';
$string['template_builder_fontfamily'] = 'Font';
$string['template_builder_fontsize'] = 'Size';
$string['template_builder_align'] = 'Align';
$string['template_builder_padding'] = 'Padding';
$string['template_builder_width'] = 'Width';
$string['template_builder_bordercolor'] = 'Border color';
$string['template_builder_label1'] = 'Label 1';
$string['template_builder_label2'] = 'Label 2';
$string['template_builder_label3'] = 'Label 3';
$string['template_builder_url1'] = 'URL 1';
$string['template_builder_url2'] = 'URL 2';
$string['template_builder_url3'] = 'URL 3';
$string['template_builder_lefttitle'] = 'Left title';
$string['template_builder_leftcontent'] = 'Left content';
$string['template_builder_righttitle'] = 'Right title';
$string['template_builder_rightcontent'] = 'Right content';
$string['template_builder_empty'] = 'Add a block to start building this template.';
$string['template_builder_invalid'] = 'The builder content could not be read.';
$string['template_builder_invalid_block'] = 'Builder block {$a} is not valid.';
$string['template_builder_invalid_colour'] = 'Builder block {$a} has an invalid color value.';
$string['template_builder_invalid_url'] = 'Builder block {$a} has an invalid URL.';
$string['template_builder_invalid_font'] = 'Builder block {$a} has an invalid font.';
$string['template_builder_invalid_align'] = 'Builder block {$a} has an invalid alignment.';
$string['template_builder_invalid_number'] = 'Builder block {$a} has a number outside the allowed range.';
$string['template_builder_too_many_blocks'] = 'The builder contains too many blocks.';
$string['template_builder_button_default'] = 'Learn more';
$string['template_builder_loading'] = 'Template builder is loading. If this message stays visible, purge Moodle caches and reload the page.';
$string['template_builder_image_placeholder'] = 'Image placeholder';
$string['template_builder_logo_placeholder'] = 'Logo placeholder';

// Campaign: use an email template for the content.
$string['campaign_usetemplate'] = 'Use a template';
$string['campaign_template_choose'] = 'Choose a template…';
$string['campaign_template_load'] = 'Load into body';
