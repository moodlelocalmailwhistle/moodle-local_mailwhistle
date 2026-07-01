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
if (!$hassiteconfig) {
    return;
}

// Category to hold all settings.
$ADMIN->add('root', new admin_category('local_mailwhistle', get_string('pluginname', 'local_mailwhistle')));

// Create the main settings page for this plugin.
$settingspage = new admin_settingpage(
    'local_mailwhistle_settings',
    get_string('settings', 'local_mailwhistle')
);
$ADMIN->add('local_mailwhistle', $settingspage);

// Main entry point for Mailwhistle.
$page = new admin_externalpage(
    name: 'local_mailwhistle_mailings',
    visiblename: get_string('campaigns', 'local_mailwhistle'),
    url: new moodle_url('/local/mailwhistle/index.php'),
    req_capability: 'local/mailwhistle:view',
);
$ADMIN->add('local_mailwhistle', $page);

if (!$ADMIN->fulltree) {
    return;
}

// Add a heading section to organize plugin settings.
$settingspage->add(
    new admin_setting_heading(
        'local_mailwhistle_settings',
        get_string('setting_heading', 'local_mailwhistle'),
        get_string('setting_heading_desc', 'local_mailwhistle')
    )
);

// Example: Boolean setting to enable/disable a feature.
$settingspage->add(
    new admin_setting_configcheckbox(
        'local_mailwhistle/enable',
        get_string('enable', 'local_mailwhistle'),
        get_string('enable_desc', 'local_mailwhistle'),
        1
    )
);

// Number of recipients delivered per send task run.
$settingspage->add(
    new admin_setting_configtext(
        'local_mailwhistle/sendbatchsize',
        get_string('sendbatchsize', 'local_mailwhistle'),
        get_string('sendbatchsize_desc', 'local_mailwhistle'),
        50,
        PARAM_INT
    )
);
