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
 * Local plugin "Mail Whistle" - Plugin capabilities.
 *
 * Defines capabilities required for this plugin to function correctly.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin capabilities.
//
// Define all capabilities required by this plugin.
// Capabilities control what roles can perform specific actions.
//
// Capability naming convention: local/pluginname:capability.
// Types: 'read' (view only), 'write' (modify), 'admin' (configure).
$capabilities = [

    // Capability: View plugin content and functionality.
    'local/mailwhistle:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW, // All logged-in users can view.
            'manager' => CAP_ALLOW, // Site managers can view.
        ],
        'clonepermissionsfrom' => '',
    ],

    // Capability: Manage plugin functionality and data.
    'local/mailwhistle:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW, // Only managers can manage.
        ],
        'clonepermissionsfrom' => '',
    ],

    // Capability: Configure plugin settings in admin panel.
    'local/mailwhistle:configure' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW, // Only managers can configure.
        ],
        'clonepermissionsfrom' => '',
    ],
];
