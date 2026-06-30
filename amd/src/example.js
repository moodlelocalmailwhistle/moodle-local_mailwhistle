/**
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Local plugin "Mail Whistle" - Example AMD module.
 *
 * Demonstrates Moodle AMD (Asynchronous Module Definition) JavaScript module
 * with best practices for DOM manipulation and event handling.
 *
 * @module     local_mailwhistle/example
 * @copyright  2024 Your Name/Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    return {
        /**
         * Initialize the module and bind event handlers.
         *
         * Called from PHP via $PAGE->requires->js_call_amd().
         * Performs DOM setup and event listener attachment.
         *
         * @param {string} selector CSS selector for the target element.
         * @returns {void}
         */
        init: function(selector) {
            var rootElement = document.querySelector(selector);
            if (!rootElement) {
                console.warn('Mail Whistle: Target element not found: ' + selector);
                return;
            }

            this.attachEventListeners(rootElement);
        },

        /**
         * Attach event listeners to DOM elements.
         *
         * Sets up click handlers and other event listeners.
         *
         * @param {HTMLElement} rootElement The root container element.
         * @returns {void}
         * @private
         */
        attachEventListeners: function(rootElement) {
            var button = rootElement.querySelector('#example-btn');
            if (button) {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    this.handleButtonClick();
                }.bind(this));
            }
        },

        /**
         * Handle button click event.
         *
         * Demonstrates event handling with scope binding.
         *
         * @returns {void}
         * @private
         */
        handleButtonClick: function() {
            console.log('Mail Whistle: Button clicked');
            // Add your click handling logic here.
        },

        /**
         * Fetch data from a URL via AJAX.
         *
         * Modern promise-based data fetching with error handling.
         * Gracefully handles network errors and invalid responses.
         *
         * @param {string} url The URL endpoint to fetch from.
         * @returns {Promise} Promise resolving to parsed JSON data.
         * @throws {Error} Rejects if network fails or response is invalid.
         */
        fetchData: function(url) {
            return fetch(url)
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                    return response.json();
                })
                .catch(function(error) {
                    console.error('Mail Whistle: Fetch error:', error.message);
                    throw error;
                });
        }
    };
});
