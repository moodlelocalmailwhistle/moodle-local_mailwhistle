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
$string['audience_placeholder']     = 'Audience management is coming soon.';
$string['templates_placeholder']    = 'Template management is coming soon.';
$string['reports_placeholder']      = 'Reporting and analytics are coming soon.';

$string['settings'] = 'Settings';
$string['resources'] = 'Resources';
