// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Template builder UI for Mail Whistle.
 *
 * @module     local_mailwhistle/template_builder
 * @copyright  2024 Your Name/Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    var blockTypes = ['header', 'text', 'button', 'divider', 'spacer', 'footer'];
    var defaults = {
        header: {
            type: 'header',
            title: 'Newsletter title',
            subtitle: 'Short supporting text',
            background: '#1f4f82',
            color: '#ffffff'
        },
        text: {
            type: 'text',
            content: 'Write your message here.'
        },
        button: {
            type: 'button',
            label: 'Learn more',
            url: '#',
            background: '#1f4f82',
            color: '#ffffff'
        },
        divider: {
            type: 'divider'
        },
        spacer: {
            type: 'spacer',
            height: 24
        },
        footer: {
            type: 'footer',
            content: 'You are receiving this email from {{University}}.'
        }
    };

    var state = {
        blocks: []
    };
    var strings = {};
    var root;
    var hiddenInput;
    var modeSelect;
    var draggedIndex = null;
    var initialized = false;

    var clone = function(value) {
        return JSON.parse(JSON.stringify(value));
    };

    var escapeHtml = function(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    var getString = function(key) {
        return strings[key] || key;
    };

    var normaliseState = function(value) {
        try {
            var parsed = JSON.parse(value || '{}');
            if (parsed && Array.isArray(parsed.blocks)) {
                return {
                    blocks: parsed.blocks.filter(function(block) {
                        return block && blockTypes.indexOf(block.type) !== -1;
                    })
                };
            }
        } catch (error) {
            // Fall back to default state below.
        }
        return {
            blocks: [clone(defaults.header), clone(defaults.text)]
        };
    };

    var saveState = function() {
        hiddenInput.value = JSON.stringify(state);
    };

    var createButton = function(label, className) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = className || 'btn btn-secondary btn-sm';
        button.textContent = label;
        return button;
    };

    var createField = function(block, field, label, type) {
        var wrapper = document.createElement('div');
        wrapper.className = 'local-mailwhistle-builder-field';

        var labelNode = document.createElement('label');
        labelNode.textContent = label;
        wrapper.appendChild(labelNode);

        var input;
        if (type === 'textarea') {
            input = document.createElement('textarea');
            input.rows = 3;
        } else {
            input = document.createElement('input');
            input.type = type || 'text';
        }

        input.value = block[field] || '';
        input.addEventListener('input', function() {
            block[field] = input.value;
            saveState();
            renderPreview();
        });
        wrapper.appendChild(input);

        return wrapper;
    };

    var renderBlockFields = function(block, body) {
        if (block.type === 'header') {
            body.appendChild(createField(block, 'title', getString('title')));
            body.appendChild(createField(block, 'subtitle', getString('subtitle')));
            body.appendChild(createField(block, 'background', getString('background'), 'color'));
            body.appendChild(createField(block, 'color', getString('color'), 'color'));
        } else if (block.type === 'text' || block.type === 'footer') {
            body.appendChild(createField(block, 'content', getString('content'), 'textarea'));
        } else if (block.type === 'button') {
            body.appendChild(createField(block, 'label', getString('label')));
            body.appendChild(createField(block, 'url', getString('url'), 'url'));
            body.appendChild(createField(block, 'background', getString('background'), 'color'));
            body.appendChild(createField(block, 'color', getString('color'), 'color'));
        } else if (block.type === 'spacer') {
            body.appendChild(createField(block, 'height', getString('height'), 'number'));
        }
    };

    var moveBlock = function(fromIndex, toIndex) {
        if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) {
            return;
        }
        var block = state.blocks.splice(fromIndex, 1)[0];
        state.blocks.splice(toIndex, 0, block);
        saveState();
        render();
    };

    var renderList = function(list) {
        list.innerHTML = '';

        if (!state.blocks.length) {
            var empty = document.createElement('div');
            empty.className = 'local-mailwhistle-builder-empty';
            empty.textContent = getString('empty');
            list.appendChild(empty);
            return;
        }

        state.blocks.forEach(function(block, index) {
            var item = document.createElement('div');
            item.className = 'local-mailwhistle-builder-block';
            item.draggable = true;
            item.dataset.index = index;

            item.addEventListener('dragstart', function() {
                draggedIndex = index;
                item.classList.add('is-dragging');
            });
            item.addEventListener('dragend', function() {
                draggedIndex = null;
                item.classList.remove('is-dragging');
            });
            item.addEventListener('dragover', function(event) {
                event.preventDefault();
            });
            item.addEventListener('drop', function(event) {
                event.preventDefault();
                moveBlock(draggedIndex, index);
            });

            var header = document.createElement('div');
            header.className = 'local-mailwhistle-builder-block-header';

            var drag = document.createElement('span');
            drag.className = 'local-mailwhistle-builder-drag';
            drag.textContent = getString('drag') + ': ' + block.type;
            header.appendChild(drag);

            var remove = createButton(getString('remove'), 'btn btn-link btn-sm text-danger');
            remove.addEventListener('click', function() {
                state.blocks.splice(index, 1);
                saveState();
                render();
            });
            header.appendChild(remove);
            item.appendChild(header);

            var body = document.createElement('div');
            renderBlockFields(block, body);
            item.appendChild(body);

            list.appendChild(item);
        });
    };

    var renderPreviewBlock = function(block) {
        if (block.type === 'header') {
            return '<div style="padding:24px 20px;text-align:center;background:' + escapeHtml(block.background || '#1f4f82')
                + ';color:' + escapeHtml(block.color || '#ffffff') + ';">'
                + '<h2 style="margin:0 0 6px;font-size:24px;color:inherit;">' + escapeHtml(block.title) + '</h2>'
                + '<p style="margin:0;color:inherit;">' + escapeHtml(block.subtitle) + '</p></div>';
        }
        if (block.type === 'text') {
            return '<div style="padding:20px;font-size:15px;line-height:1.55;">'
                + escapeHtml(block.content).replace(/\n/g, '<br>') + '</div>';
        }
        if (block.type === 'button') {
            return '<div style="padding:8px 20px 20px;text-align:center;"><span style="display:inline-block;padding:10px 16px;'
                + 'background:' + escapeHtml(block.background || '#1f4f82') + ';color:' + escapeHtml(block.color || '#ffffff')
                + ';border-radius:4px;font-weight:bold;">' + escapeHtml(block.label) + '</span></div>';
        }
        if (block.type === 'divider') {
            return '<div style="height:1px;margin:8px 20px;background:#d8dee6;"></div>';
        }
        if (block.type === 'spacer') {
            return '<div style="height:' + Math.max(8, Math.min(80, parseInt(block.height, 10) || 24)) + 'px;"></div>';
        }
        if (block.type === 'footer') {
            return '<div style="padding:18px 20px;text-align:center;background:#f3f5f8;'
                + 'color:#52616f;font-size:12px;line-height:1.5;">'
                + escapeHtml(block.content).replace(/\n/g, '<br>') + '</div>';
        }
        return '';
    };

    var renderPreview = function() {
        var preview = root.querySelector('[data-region="preview"]');
        if (!preview) {
            return;
        }
        preview.innerHTML = state.blocks.map(renderPreviewBlock).join('');
    };

    var render = function() {
        var list = root.querySelector('[data-region="list"]');
        renderList(list);
        renderPreview();
    };

    var addBlock = function(type) {
        state.blocks.push(clone(defaults[type]));
        saveState();
        render();
    };

    var toggleMode = function() {
        var builderVisible = modeSelect.value === 'builder';
        var htmlEditor = document.getElementById('fitem_id_bodyhtml_editor');

        root.style.display = builderVisible ? '' : 'none';
        if (htmlEditor) {
            htmlEditor.style.display = builderVisible ? 'none' : '';
        }
    };

    var buildUi = function() {
        root.innerHTML = '';

        var header = document.createElement('div');
        header.className = 'local-mailwhistle-builder-header';

        var title = document.createElement('h4');
        title.textContent = getString('builderheading');
        header.appendChild(title);

        var toolbar = document.createElement('div');
        toolbar.className = 'local-mailwhistle-builder-toolbar';

        [
            ['header', 'addheader'],
            ['text', 'addtext'],
            ['button', 'addbutton'],
            ['divider', 'adddivider'],
            ['spacer', 'addspacer'],
            ['footer', 'addfooter']
        ].forEach(function(item) {
            var button = createButton(getString(item[1]));
            button.addEventListener('click', function() {
                addBlock(item[0]);
            });
            toolbar.appendChild(button);
        });

        header.appendChild(toolbar);
        root.appendChild(header);

        var body = document.createElement('div');
        body.className = 'local-mailwhistle-builder-body';

        var list = document.createElement('div');
        list.className = 'local-mailwhistle-builder-list';
        list.dataset.region = 'list';
        body.appendChild(list);

        var previewWrap = document.createElement('div');
        previewWrap.className = 'local-mailwhistle-builder-preview';
        var preview = document.createElement('div');
        preview.className = 'local-mailwhistle-builder-preview-inner';
        preview.dataset.region = 'preview';
        previewWrap.appendChild(preview);
        body.appendChild(previewWrap);

        root.appendChild(body);
    };

    var readInlineConfig = function() {
        root = document.getElementById('local-mailwhistle-template-builder');
        if (!root || !root.dataset.config) {
            return {};
        }

        try {
            return JSON.parse(root.dataset.config);
        } catch (error) {
            return {};
        }
    };

    var init = function(config) {
        if (initialized) {
            return;
        }

        root = document.getElementById('local-mailwhistle-template-builder');
        hiddenInput = document.getElementById('id_builderjson');
        modeSelect = document.getElementById('id_editormode');
        config = config || readInlineConfig();
        strings = config.strings || {};

        if (!root || !hiddenInput || !modeSelect) {
            return;
        }

        initialized = true;
        state = normaliseState(hiddenInput.value || config.builderjson);
        saveState();
        buildUi();
        render();
        toggleMode();

        modeSelect.addEventListener('change', toggleMode);
        var form = root.closest('form');
        if (form) {
            form.addEventListener('submit', saveState);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init(readInlineConfig());
        });
    } else {
        init(readInlineConfig());
    }

    return {
        init: init
    };
});
