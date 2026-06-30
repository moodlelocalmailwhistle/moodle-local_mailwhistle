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

// ===== PLUGIN METADATA =====

/**
 * Plugin name and description strings.
 */
$string['pluginname']  = 'Mail Whistle';
$string['plugindesc']  = 'A reusable boilerplate for creating Moodle local plugins on Moodle 5 LTS and above.';

// ===== SETTINGS PAGE CONFIGURATION =====

/**
 * Main settings page heading and description.
 */
$string['setting_heading']      = 'Mail Whistle Settings';
$string['setting_heading_desc']  = 'Configure the Mail Whistle plugin settings.';

/**
 * Feature toggle setting (checkbox configuration).
 */
$string['enable_feature']        = 'Enable Feature';
$string['enable_feature_desc']   = 'Enable or disable the main feature of this plugin.';

/**
 * API Key setting (text input configuration).
 */
$string['api_key']               = 'API Key';
$string['api_key_desc']          = 'Enter your API key for external service integration.';

/**
 * Select option setting (select menu configuration).
 */
$string['select_option']         = 'Select Option';
$string['select_option_desc']    = 'Choose one of the available options.';
$string['option1']               = 'Option 1';
$string['option2']               = 'Option 2';
$string['option3']               = 'Option 3';

/**
 * Description setting (textarea configuration).
 */
$string['description']           = 'Description';
$string['description_desc']      = 'Enter a description for this plugin configuration.';

// ===== PLUGIN CAPABILITIES =====

/**
 * Capability descriptions for role-based access control.
 * Used in db/access.php to define permissions for different user roles.
 */
$string['mailwhistle:view']        = 'View Mail Whistle plugin';
$string['mailwhistle:manage']      = 'Manage Mail Whistle plugin';
$string['mailwhistle:configure']   = 'Configure Mail Whistle plugin settings';
$string['mailwhistle:managetags']  = 'Manage audience tags (create, assign, unassign)';

// ===== USER MESSAGES AND NOTIFICATIONS =====

/**
 * User-facing messages for operation feedback and error handling.
 */
$string['success_message']       = 'Operation completed successfully.';
$string['error_message']         = 'An error occurred. Please try again.';

// Event names
$string['event_data_created']    = 'Data record created';

// Tab labels for the main plugin page.
$string['tab_send']         = 'Send newsletters';
$string['tab_audience']     = 'Audience';
$string['tab_templates']    = 'Templates';
$string['tab_reports']      = 'Reports';

// Headings and labels for the sent newsletters history table.
$string['sentmails_heading']    = 'Previously sent newsletters';
$string['col_subject']          = 'Subject';
$string['col_audience']         = 'Audience';
$string['col_recipients']       = 'Recipients';
$string['col_sentby']           = 'Sent by';
$string['col_sentat']           = 'Sent at';
$string['col_status']           = 'Status';
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

// ===== AUDIENCE TAB =====

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

// ===== AUDIENCE EVENTS =====

$string['event_tag_created']    = 'Audience tag created';
$string['event_tag_assigned']   = 'Audience tag assigned to user';
$string['event_tag_unassigned'] = 'Audience tag removed from user';

// ===== PRIVACY / GDPR METADATA =====

// Table: local_mailwhistle_tag_assign
$string['privacy:metadata:local_mailwhistle_tag_assign']             = 'Stores which users have been assigned each audience tag, and who performed the assignment.';
$string['privacy:metadata:local_mailwhistle_tag_assign:tagid']       = 'The ID of the audience tag that was assigned.';
$string['privacy:metadata:local_mailwhistle_tag_assign:userid']      = 'The ID of the user who was tagged.';
$string['privacy:metadata:local_mailwhistle_tag_assign:usermodified'] = 'The ID of the user who performed the tag assignment.';
$string['privacy:metadata:local_mailwhistle_tag_assign:timecreated'] = 'The date and time when the tag was assigned.';

// Table: local_mailwhistle_tag
$string['privacy:metadata:local_mailwhistle_tag']                    = 'Stores audience tag definitions; records who created or last modified each tag.';
$string['privacy:metadata:local_mailwhistle_tag:name']               = 'The display name of the tag.';
$string['privacy:metadata:local_mailwhistle_tag:usermodified']       = 'The ID of the user who created or last modified the tag definition.';
$string['privacy:metadata:local_mailwhistle_tag:timecreated']        = 'The date and time when the tag definition was created.';
$string['privacy:metadata:local_mailwhistle_tag:timemodified']       = 'The date and time when the tag definition was last modified.';

// Export path labels (used by the privacy export writer).
$string['privacy:path:tag_assignments']  = 'Audience tag assignments';
$string['privacy:path:authored_tags']    = 'Authored tag definitions';
