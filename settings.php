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
 * Local plugin "Mail Whistle" - Settings page.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Only define settings if we're in the site administration context.
if ($hassiteconfig) {
    // Create the main settings page for this plugin.
    $settingspage = new admin_settingpage(
        'local_mailwhistle',
        get_string('pluginname', 'local_mailwhistle')
    );

    // Add a heading section to organize plugin settings.
    $settingspage->add(new admin_setting_heading(
        'local_mailwhistle_settings',
        get_string('setting_heading', 'local_mailwhistle'),
        get_string('setting_heading_desc', 'local_mailwhistle')
    ));

    // Example: Boolean setting to enable/disable a feature.
    $settingspage->add(new admin_setting_configcheckbox(
        'local_mailwhistle/enable_feature',
        get_string('enable_feature', 'local_mailwhistle'),
        get_string('enable_feature_desc', 'local_mailwhistle'),
        1
    ));

    // Example: Text input setting with validation.
    $settingspage->add(new admin_setting_configtext(
        'local_mailwhistle/api_key',
        get_string('api_key', 'local_mailwhistle'),
        get_string('api_key_desc', 'local_mailwhistle'),
        ''
    ));

    // Example: Dropdown selection setting.
    $selectoptions = [
        'option1' => get_string('option1', 'local_mailwhistle'),
        'option2' => get_string('option2', 'local_mailwhistle'),
        'option3' => get_string('option3', 'local_mailwhistle'),
    ];
    $settingspage->add(new admin_setting_configselect(
        'local_mailwhistle/select_option',
        get_string('select_option', 'local_mailwhistle'),
        get_string('select_option_desc', 'local_mailwhistle'),
        'option1',
        $selectoptions
    ));

    // Example: Textarea for longer configuration text.
    $settingspage->add(new admin_setting_configtextarea(
        'local_mailwhistle/description',
        get_string('description', 'local_mailwhistle'),
        get_string('description_desc', 'local_mailwhistle'),
        ''
    ));

    // Register the settings page with the admin interface.
    $ADMIN->add('localplugins', $settingspage);

    // Admin page for Mailwhistle.
    $page = new admin_externalpage(
        name: 'local_mailwhistle_mailings',
        visiblename: get_string('pluginname', 'local_mailwhistle'),
        url: new moodle_url('/local/mailwhistle/index.php'),
        req_capability: 'moodle/site:config',
        hidden: false,
    );
    $ADMIN->add('root', $page);
}
