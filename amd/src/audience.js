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
 * Current-page audience selection and tag assignment controls.
 *
 * @module     local_mailwhistle/audience
 * @copyright  2026 onwards MoodleDach project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Enhance the audience POST form without changing its submission behavior.
 *
 * @param {string} rootId Tag controls region id.
 */
export const init = rootId => {
    const root = document.getElementById(rootId);
    const form = root?.closest('form');
    if (!form || root.dataset.initialized) {
        return;
    }

    const count = root.querySelector('[data-region="selection-count"]');
    const button = root.querySelector('[data-action="apply-tag"]');
    const existing = root.querySelector('[data-region="existing-tag"]');
    const newTag = root.querySelector('[data-region="new-tag"]');
    const tagSelect = root.querySelector('[name="applytagid"]');
    const tagName = root.querySelector('[name="newtagname"]');
    const selectAll = form.querySelector('[data-action="select-all"]');
    if (!count || !button || !newTag || !tagName) {
        return;
    }
    root.dataset.initialized = 'true';
    const noSelection = count.textContent.trim();

    const getCheckboxes = () => Array.from(form.querySelectorAll('input[type="checkbox"][name="userids[]"]'))
        .filter(checkbox => !checkbox.disabled);

    const update = () => {
        const checkboxes = getCheckboxes();
        const selected = checkboxes.filter(checkbox => checkbox.checked).length;
        const mode = root.querySelector('[name="tagmode"]:checked')?.value;
        const useExisting = mode === 'existing' && Boolean(existing);

        if (existing) {
            existing.hidden = !useExisting;
            tagSelect.disabled = !useExisting;
        }
        newTag.hidden = useExisting;
        tagName.disabled = useExisting;

        count.textContent = selected ? count.dataset.countTemplate.replace('{$a}', String(selected)) : noSelection;
        if (selectAll) {
            selectAll.checked = selected > 0 && selected === checkboxes.length;
            selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
            selectAll.disabled = checkboxes.length === 0;
        }

        const hasTag = useExisting ? Number(tagSelect.value) > 0 : tagName.value.trim() !== '';
        button.disabled = selected === 0 || !hasTag;
    };

    if (selectAll) {
        selectAll.hidden = false;
        selectAll.addEventListener('change', () => {
            getCheckboxes().forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            update();
        });
    }

    form.addEventListener('change', update);
    tagName.addEventListener('input', update);
    update();
    count.hidden = false;
};
