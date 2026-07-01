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

defined('MOODLE_INTERNAL') || die();

// Plugin metadata.
$string['pluginname']  = 'Mail Whistle';
$string['plugindesc']  = 'A reusable boilerplate for creating Moodle local plugins on Moodle 5 LTS and above.';

// Settings page heading and description.
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
$string['tab_send']         = 'Send newsletters';
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
$string['createcampaign_desc']     = 'Give your campaign an internal name. You can change everything else later.';
$string['createcampaign_submit']   = 'Create campaign';
$string['internalname']            = 'Internal email name';
$string['campaigncreated']         = 'Campaign created.';
$string['untitledcampaign']        = 'Untitled campaign';

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
$string['privacy:metadata:local_mailwhistle_unsubscribes'] = 'Records of users who unsubscribed from campaigns.';
$string['privacy:metadata:local_mailwhistle_unsubscribes:userid'] = 'The ID of the user who unsubscribed.';
$string['privacy:metadata:local_mailwhistle_unsubscribes:email'] = 'The email address that unsubscribed.';
$string['privacy:metadata:local_mailwhistle_unsubscribes:timecreated'] = 'The time the unsubscribe was recorded.';
