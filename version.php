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
 * Local plugin "Mail Whistle" - Version file.
 *
 * @package   local_mailwhistle
 * @copyright 2024 Your Name/Organization
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_mailwhistle';
$plugin->version = 2026070122;      // YYYYMMDDvv format.
$plugin->release = '1.2.9';         // Semantic versioning.
$plugin->requires = 2025041400;     // Moodle 5.0 LTS minimum.
$plugin->maturity = MATURITY_ALPHA; // Development stability level.
$plugin->supported = [500, 502];    // Supported branch range: Moodle 5.0 to 5.2.

// Declare dependencies on other plugins via $plugin->dependencies when required.
