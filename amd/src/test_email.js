// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Open Moodle's native test-email modal and display each destination's result.
 *
 * @module local_mailwhistle/test_email
 * @copyright 2026 onwards MoodleDach project
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {getString} from 'core/str';

/**
 * Attach one modal launcher to the saved campaign's Review page.
 *
 * @param {String} rootId Unique widget element id.
 */
export const init = rootId => {
    const root = document.getElementById(rootId);
    const trigger = root?.querySelector('[data-action="send-test-email"]');
    const results = root?.querySelector('[data-region="test-email-results"]');
    if (!trigger || !results || trigger.dataset.initialised) {
        return;
    }
    trigger.dataset.initialised = 'true';
    trigger.disabled = false;
    trigger.addEventListener('click', () => {
        const modal = new ModalForm({
            formClass: 'local_mailwhistle\\form\\test_email_form',
            args: {campaignid: Number(root.dataset.campaignid)},
            modalConfig: {title: getString('testmail_title', 'local_mailwhistle')},
            saveButtonText: getString('testmail_submit', 'local_mailwhistle'),
            returnFocus: trigger,
        });
        modal.addEventListener(modal.events.FORM_SUBMITTED, event => {
            Templates.renderForPromise('local_mailwhistle/test_email_results', event.detail)
                .then(({html, js}) => Templates.replaceNodeContents(results, html, js))
                .catch(Notification.exception);
        });
        modal.show().catch(Notification.exception);
    });
};
