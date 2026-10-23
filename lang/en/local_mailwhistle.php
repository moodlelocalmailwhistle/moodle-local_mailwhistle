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
 * @copyright 2026 onwards MoodleDach project
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
$string['plugindesc']  = 'Send email campaigns to tagged audiences of Moodle users.';

// Settings page heading and description.
$string['report:actions'] = 'Actions';
$string['report:audience'] = 'Audience';
$string['report:clicks'] = 'Unique clicks';
$string['report:name'] = 'Name';
$string['report:opens'] = 'Unique opens';
$string['report:recipients'] = 'Recipients';
$string['report:sentat'] = 'Sent at';
$string['report:sentby'] = 'Sent by';
$string['report:status'] = 'Status';
$string['report:subject'] = 'Subject';
$string['report:template'] = 'Template';
$string['report:timecreated'] = 'Time created';
$string['setting_heading']      = 'Mail Whistle Settings';
$string['setting_heading_desc']  = 'Configure the Mail Whistle plugin settings.';

// Capability descriptions for role-based access control.
$string['mailwhistle:view']        = 'View Mail Whistle plugin';
$string['mailwhistle:manage']      = 'Manage Mail Whistle plugin';
$string['mailwhistle:configure']   = 'Configure Mail Whistle plugin settings';
$string['mailwhistle:managetags']  = 'Manage audience tags (create, assign, unassign)';

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
$string['status_pending']       = 'Pending';
$string['status_failed']        = 'Failed';
$string['nosentmails']          = 'No newsletters have been sent yet.';

// Sent newsletter detail view.
$string['backtolist']           = 'Back to sent newsletters';
$string['mailnotfound']         = 'The requested newsletter could not be found.';
$string['mailpreview']          = 'Newsletter preview';

// Reports tab.
$string['reports_heading']          = 'Campaign reports';
$string['report_view']              = 'View report';
$string['report_back']              = 'Back to reports';
$string['report_recipients']        = 'Who was sent this campaign';
$string['reports_intro']           = 'Sent and sending campaigns appear here. Select a campaign name to preview its emails, see audience and template details, and check individual recipient results.';
$string['reports_help_heading']    = 'How to read these figures';
$string['reports_help_delivery']   = 'Sending results';
$string['reports_help_delivery_desc'] = 'The total includes sent, failed and pending recipients. Sent means Moodle accepted the message; it does not confirm inbox delivery. Open a campaign to check individual results.';
$string['reports_help_engagement'] = 'Opens and clicks';
$string['reports_help_engagement_desc'] = 'Each recipient counts once for opens and once for clicks, even if they open repeatedly or click several links. Percentages are calculated from the sent count.';
$string['reports_help_status']     = 'Campaign status';
$string['reports_help_status_desc'] = 'Sending means processing is in progress. Sent means processing has finished, but the campaign may still have failed recipients. Check the sending results for failures.';
$string['reports_help_tracking']   = 'Email apps may block images, preload them or scan links automatically, so opens and clicks are estimates rather than proof that someone read the email.';
$string['reports_sort_hint']       = 'Select a column heading to sort the table.';
$string['reports_column_campaign'] = 'Campaign';
$string['reports_column_delivery'] = 'Sending results';
$string['reports_sentcount']       = '{$a->sent} of {$a->total} sent';
$string['reports_rate']            = '{$a}% of sent';
$string['reports_nosentdate']      = 'Not recorded';
$string['report_details']          = 'Campaign details';
$string['report_email']            = 'Message';
$string['report_emailpreview']     = 'Email preview';
$string['report_viewemail']        = 'View email';
$string['report_viewemailfor']     = 'View email for {$a}';
$string['report_previewrecipient'] = 'Preview for';
$string['report_previewhint']      = 'Choose View email beside a recipient below to see their personalised message. Previewing does not record opens or clicks.';
$string['report_previewgeneric']   = 'No recipients have been marked as sent yet. Showing the saved campaign content.';
$string['report_previewunavailable'] = 'This recipient’s email preview is unavailable for this campaign.';
$string['report_previewempty']     = 'No email content was saved for this campaign.';
$string['report_plaintext']        = 'Plain-text version';
$string['report_attachments']      = 'Selected attachments';
$string['stat_recipients']          = 'Recipients';
$string['stat_sent']                = 'Sent';
$string['stat_failedcount']         = '{$a} failed';
$string['stat_pendingcount']        = '{$a} pending';
$string['stat_uniqueopens']         = 'Unique opens';
$string['stat_uniqueclicks']        = 'Unique clicks';
$string['stat_openrate']            = '{$a}% open rate';
$string['stat_clickrate']           = '{$a}% click rate';
$string['col_opened']               = 'Opened';
$string['col_clicked']              = 'Clicked';
$string['col_error']                = 'Error';
$string['col_template']             = 'Template';
$string['notemplaterecorded']       = 'No template recorded';
$string['unknowntemplate']          = 'Unknown template';
$string['campaignsusingtemplate']   = 'Campaigns using {$a}';

// Campaign creation.
$string['createcampaign']          = 'Create a campaign';
$string['internalname']            = 'Internal email name';
$string['untitledcampaign']        = 'Untitled campaign';

// Campaign audience-tag selection (audience wizard step).
$string['audiencetags_desc']       = 'Choose the groups of people for this campaign. A person with any selected tag is included once, even if they have several of these tags.';
$string['audiencetags_label']      = 'Audience tags';
$string['audiencetags_label_help'] = 'Tags group Moodle users into reusable audiences. On the Audience tab, select people and add an existing or new tag. Then choose that tag here. Suspended, deleted and unsubscribed users are excluded when the campaign is sent.';
$string['audiencetags_none']       = 'No tags selected';
$string['audiencetags_submit']     = 'Save audience';
$string['audiencetags_notags']     = 'Your audience needs a tag first. Open the Audience tab, select the people you want to reach, and create a tag for them (for example, Newsletter). Then return here and choose that tag.';
$string['audiencetags_managetags'] = 'Add people to audience tags';

// Draft campaign edit wizard.
$string['editcampaign']            = 'Edit campaign';
$string['editcampaign_notdraft']   = 'This campaign has left draft status and can no longer be edited here.';
$string['editcampaign_completed']  = 'Campaign marked as ready.';
$string['editcampaign_markcomplete'] = 'Mark campaign complete';
$string['editcampaign_incomplete'] = 'Add a name, subject, body and at least one audience tag before this campaign can be completed.';
$string['testmail_send']           = 'Send test email to me';
$string['testmail_subjectprefix']  = '[Test]';
$string['testmail_sent']           = 'A test email has been sent to {$a}.';
$string['testmail_failed']         = 'The test email could not be sent. Please try again.';
$string['campaignincomplete']      = 'The campaign is not complete yet.';
$string['status_ready']            = 'Ready';
$string['wizardstep_details']      = '1. Details';
$string['wizardstep_content']      = '2. Content';
$string['wizardstep_audience']     = '3. Audience';
$string['wizardstep_review']       = '4. Review';
$string['wizard_savecontinue']     = 'Save and continue';
$string['subject']                 = 'Subject';
$string['body']                    = 'Email body';
$string['placeholders']            = 'Personalisation variables';
$string['placeholders_help']       = 'Type these in the subject or body. They are replaced when the campaign is sent (or in a test copy, using your profile for name and email).

Recipient: {{firstname}}, {{lastname}}, {{fullname}}, {{email}}.
Site: {{sitename}}, {{siteurl}}.

{{university}} is an older name for {{sitename}}. Unknown variables are removed so they never appear in the inbox. These are Mail Whistle variables, not Moodle text filters.';
$string['placeholders_cheatsheet'] = 'Use {{firstname}}, {{lastname}}, {{fullname}}, {{email}}, {{sitename}}, {{siteurl}} — replaced when the campaign is sent.';
$string['sendername']              = 'Sender name';
$string['senderemail']             = 'Sender email';

// Sending.
$string['col_actions']             = 'Actions';
$string['sendnow']                 = 'Send now';
$string['sendqueued']              = 'Campaign queued for sending.';
$string['sendnotready']            = 'This campaign is not ready to send.';
$string['sendbatchsize']           = 'Send batch size';
$string['sendbatchsize_desc']      = 'How many recipients each send task run delivers before re-queueing (1–200). Keep within your mail server limits.';
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
$string['resources_help_heading'] = 'How to use resources';
$string['resources_help_intro'] = 'Keep reusable files for your templates and campaign emails here. Add files below, then select Save changes to make them available.';
$string['resources_help_images'] = 'Images and logos:';
$string['resources_help_images_desc'] = 'Use a logo, banner or photo in a template. In the template builder, choose Uploaded image on an Image or Logo block. In HTML mode, use an image URL shown below. These image URLs work without a Moodle login.';
$string['resources_help_documents'] = 'Email attachments:';
$string['resources_help_documents_desc'] = 'Share files such as handbooks, agendas or worksheets. In a campaign, go to Content and select the files under Attachments. One file is attached directly; multiple files are sent together in a ZIP file. Uploading a file here does not attach it automatically.';
$string['resources_publicurls'] = 'Image URLs for templates';
$string['resources_publicurls_desc'] = 'In builder mode, pick a file from Uploaded image on an Image or Logo block. In HTML mode, paste a URL below. Email clients can load these without a Moodle login.';
$string['resources_attachments'] = 'Files for email attachments';
$string['resources_attachments_desc'] = 'PDFs and other documents can be attached to a campaign on the content step. They are sent with the email, not as public URLs.';

$string['campaign_attachments'] = 'Attachments';
$string['campaign_attachments_help'] = 'Choose files uploaded on the Resources tab. They are sent as email attachments (for example a PDF). Images can still be embedded in the HTML body.';
$string['campaign_attachments_none'] = 'None';
$string['campaign_attachments_empty'] = 'Upload a PDF or other document on the Resources tab, then choose it here.';
$string['campaign_attachments_invalid'] = 'One or more selected attachments are not available.';

// Audience tab strings.
$string['audience_build'] = 'Build your audience';
$string['audience_intro'] = 'Use tags to group Moodle users into reusable audiences, such as Staff or Newsletter. Add tags to people below, then choose those tags in a campaign’s Audience step. Adding a tag does not send an email.';
$string['audience_findpeople'] = '1. Find people';
$string['audience_filterusers'] = 'Filter users';
$string['audience_clearfilters'] = 'Clear filters';
$string['audience_selectheading'] = '2. Select people';
$string['audience_selectionhint'] = 'Select the people you want to tag. Selections apply to this page and are cleared when you change filters or pages.';
$string['audience_selectall'] = 'Select all people on this page';
$string['audience_selectperson'] = 'Select {$a}';
$string['audience_selectedcount'] = 'Selected people: {$a}';
$string['audience_noselected'] = 'No people selected';
$string['audience_addtag'] = '3. Add a tag';
$string['audience_existingtag'] = 'Use an existing tag';
$string['audience_newtag'] = 'Create a new tag';
$string['audience_tagexample'] = 'For example, Newsletter';
$string['audience_taghint'] = 'Choose a tag for the selected people, or create a new one and add it in the same step. People can have more than one tag.';
$string['audience_addtagbutton'] = 'Add tag to selected people';
$string['audience_selectpeople'] = 'Select at least one person from the list before adding a tag.';
$string['audience_choosetag'] = 'Choose an existing tag or enter a new tag name (up to 255 characters).';
$string['audience_tagadded'] = 'Added tag "{$a->tag}" to {$a->count} people. Choose this tag in a campaign’s Audience step to use this group.';
$string['audience_tagadded_one'] = 'Added tag "{$a->tag}" to one person. Choose this tag in a campaign’s Audience step to use this group.';
$string['audience_alreadytagged'] = 'Everyone selected already has the tag "{$a}".';

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
$string['privacy:metadata:local_mailwhistle_sendlogs'] = 'Per-recipient send attempt logs for campaigns.';
$string['privacy:metadata:local_mailwhistle_sendlogs:recipientid'] = 'The recipient the send log belongs to.';
$string['privacy:metadata:local_mailwhistle_sendlogs:level'] = 'The log level (info or error).';
$string['privacy:metadata:local_mailwhistle_sendlogs:message'] = 'The log message describing the send attempt.';
$string['privacy:metadata:local_mailwhistle_sendlogs:timecreated'] = 'The time the send attempt was logged.';

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
$string['template_builder_chooseimage'] = 'Uploaded image';
$string['template_builder_chooseimageempty'] = 'Choose an image';
$string['template_builder_uploadimages'] = 'Upload images on the Resources tab, then choose one here.';

// Campaign: use an email template for the content.
$string['campaign_usetemplate'] = 'Use a template';
$string['campaign_template_choose'] = 'Choose a template…';
$string['campaign_template_load'] = 'Load into body';
$string['campaign_template_hint'] = 'Choose a template to see a preview.';
$string['campaign_template_fullpreview'] = 'Open full preview (new tab)';
$string['campaign_template_replacehint'] = 'Loading a template replaces the email body. Your subject and attachments stay unchanged.';
$string['campaign_template_loaded'] = 'Template loaded into the email body.';
$string['campaign_template_requiresjs'] = 'Enable JavaScript to preview and load a template.';
$string['campaign_template_editorloading'] = 'The email editor is still loading. Please try again.';
