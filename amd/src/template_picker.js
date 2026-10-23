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
 * Preview and load campaign templates without leaving the content form.
 *
 * @module     local_mailwhistle/template_picker
 * @copyright  2026 onwards MoodleDach project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from 'core/pending';

/**
 * Notify Moodle's form change checker after updating a form control.
 *
 * @param {HTMLElement} element Updated control.
 */
const notifyChange = element => {
    element.dispatchEvent(new Event('input', {bubbles: true}));
    element.dispatchEvent(new Event('change', {bubbles: true}));
};

/**
 * Wait for the configured editor, without treating its hidden textarea as editable.
 *
 * @param {HTMLTextAreaElement} textarea Body control.
 * @param {string} editorType Configured Moodle editor.
 * @returns {Promise<object|null>} Ready editor adapter, or null after a timeout.
 */
const waitForEditor = async(textarea, editorType) => {
    if (!['tiny', 'atto', 'textarea'].includes(editorType)) {
        return null;
    }

    const tiny = editorType === 'tiny' ? await import('editor_tiny/editor') : null;
    const deadline = Date.now() + 10000;
    while (textarea.isConnected && !textarea.disabled && !textarea.readOnly) {
        if (tiny) {
            const editor = tiny.getInstanceForElement(textarea);
            if (editor) {
                return {
                    load: template => {
                        editor.undoManager.transact(() => editor.setContent(template.body));
                        editor.save();
                    },
                };
            }
        } else if (editorType === 'atto') {
            const editable = document.getElementById(`${textarea.id}editable`);
            if (editable?.isContentEditable && textarea.form.contains(editable)) {
                return {
                    load: template => {
                        // Only sanitized HTML may be inserted into the parent document.
                        const preview = new DOMParser().parseFromString(template.preview, 'text/html');
                        editable.innerHTML = preview.body.innerHTML;
                        textarea.value = editable.innerHTML;
                        // Atto synchronizes its textarea and change handlers on keyup.
                        editable.dispatchEvent(new Event('keyup', {bubbles: true}));
                        notifyChange(editable);
                    },
                };
            }
        } else if (textarea.getClientRects().length) {
            return {load: template => {
                textarea.value = template.body;
            }};
        }

        if (Date.now() >= deadline) {
            break;
        }
        await Pending.Promise(resolve => window.setTimeout(resolve, 100), 'local_mailwhistle/template_picker:editor');
    }

    return null;
};

/**
 * Initialise one picker within its campaign content form.
 *
 * @param {string} rootId Preview region id.
 */
export const init = rootId => {
    const root = document.getElementById(rootId);
    const form = root?.closest('form');
    if (!form || root.dataset.initialized) {
        return;
    }

    const choice = form.querySelector('[name="templatechoice"]');
    const button = form.querySelector('[name="loadtemplate"]');
    const applied = form.querySelector('[name="templateid"]');
    const body = form.querySelector('textarea[name="body[text]"]');
    if (!choice || !button || !applied || !body) {
        return;
    }
    root.dataset.initialized = 'true';

    const templates = new Map(Array.from(root.querySelectorAll('[data-template-id]'), element => [
        element.dataset.templateId, element.dataset,
    ]));
    const panel = root.querySelector('[data-region="template-preview"]');
    const frame = root.querySelector('[data-region="preview-frame"]');
    const name = root.querySelector('[data-region="template-name"]');
    const summary = root.querySelector('[data-region="template-summary"]');
    const fullPreview = root.querySelector('[data-region="full-preview"]');
    const hint = root.querySelector('[data-region="preview-hint"]');
    const status = root.querySelector('[data-region="template-status"]');
    let loading = false;

    const updatePreview = () => {
        const template = templates.get(choice.value);
        panel.hidden = !template;
        hint.hidden = Boolean(template);
        button.disabled = loading || !template;
        status.textContent = '';
        if (template) {
            frame.srcdoc = template.preview;
            name.textContent = template.name;
            summary.textContent = template.summary;
            summary.hidden = !template.summary;
            fullPreview.href = template.previewurl;
        } else {
            frame.removeAttribute('srcdoc');
            name.textContent = '';
            summary.textContent = '';
            fullPreview.removeAttribute('href');
        }
    };

    choice.addEventListener('change', updatePreview);
    button.addEventListener('click', async() => {
        const template = templates.get(choice.value);
        if (loading || !template) {
            return;
        }

        const pending = new Pending('local_mailwhistle/template_picker:load');
        loading = true;
        button.disabled = true;
        choice.disabled = true;
        status.textContent = '';
        root.setAttribute('aria-busy', 'true');
        try {
            const editor = await waitForEditor(body, root.dataset.editor);
            if (!editor || !root.isConnected) {
                throw new Error('Editor unavailable');
            }
            editor.load(template);
            applied.value = template.templateId;
            notifyChange(body);
            notifyChange(applied);
            status.classList.remove('text-danger');
            status.classList.add('text-success');
            status.textContent = status.dataset.loadedMessage;
        } catch (error) {
            status.classList.remove('text-success');
            status.classList.add('text-danger');
            status.textContent = status.dataset.editorError;
        } finally {
            loading = false;
            choice.disabled = false;
            button.disabled = !templates.has(choice.value);
            root.removeAttribute('aria-busy');
            pending.resolve();
        }
    });

    updatePreview();
};
