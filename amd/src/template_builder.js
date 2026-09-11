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
 * @copyright  2026 onwards MoodleDach project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    var blockTypes = ['header', 'logo', 'text', 'button', 'image', 'highlight', 'social', 'columns', 'divider', 'footer'];
    var defaults = {
        header: {
            type: 'header',
            title: 'Newsletter title',
            subtitle: 'Short supporting text',
            background: '#1f4f82',
            color: '#ffffff',
            fontfamily: 'arial',
            fontsize: 28,
            align: 'center',
            padding: 32
        },
        text: {
            type: 'text',
            content: 'Write your message here.',
            color: '#1f2933',
            fontfamily: 'arial',
            fontsize: 16,
            align: 'left',
            padding: 24
        },
        button: {
            type: 'button',
            label: 'Learn more',
            url: '#',
            background: '#1f4f82',
            color: '#ffffff',
            align: 'center',
            padding: 24
        },
        image: {
            type: 'image',
            url: '',
            alt: '',
            width: 100,
            align: 'center',
            padding: 24
        },
        logo: {
            type: 'logo',
            url: '',
            alt: '',
            width: 35,
            align: 'center',
            padding: 18
        },
        highlight: {
            type: 'highlight',
            title: 'Important update',
            content: 'Add the key message here.',
            background: '#f3f7fb',
            color: '#1f2933',
            bordercolor: '#1f4f82',
            fontfamily: 'arial',
            fontsize: 16,
            padding: 20
        },
        social: {
            type: 'social',
            label1: 'Website',
            url1: '#',
            label2: 'LinkedIn',
            url2: '#',
            label3: 'Instagram',
            url3: '#',
            align: 'center',
            padding: 20
        },
        columns: {
            type: 'columns',
            lefttitle: 'Left column',
            leftcontent: 'Add text here.',
            righttitle: 'Right column',
            rightcontent: 'Add text here.',
            background: '#ffffff',
            color: '#1f2933',
            fontfamily: 'arial',
            fontsize: 15,
            padding: 20
        },
        divider: {
            type: 'divider'
        },
        footer: {
            type: 'footer',
            content: 'You are receiving this email from {{university}}.',
            color: '#52616f',
            fontfamily: 'arial',
            fontsize: 13,
            align: 'center',
            padding: 22,
            background: '#f3f5f8'
        }
    };

    var fontOptions = [
        ['arial', 'Arial'],
        ['verdana', 'Verdana'],
        ['georgia', 'Georgia'],
        ['times', 'Times New Roman'],
        ['trebuchet', 'Trebuchet MS']
    ];
    var alignOptions = [
        ['left', 'Left'],
        ['center', 'Center'],
        ['right', 'Right']
    ];
    var fontFamilies = {
        arial: 'Arial,Helvetica,sans-serif',
        verdana: 'Verdana,Geneva,sans-serif',
        georgia: 'Georgia,serif',
        times: '"Times New Roman",Times,serif',
        trebuchet: '"Trebuchet MS",Arial,sans-serif'
    };

    var state = {blocks: []};
    var strings = {};
    var resourceFiles = [];
    var resourcesUrl = '';
    var resourceFieldSeq = 0;
    var root = null;
    var hiddenInput = null;
    var modeSelect = null;
    var backgroundInput = null;
    var draggedIndex = null;
    var initialized = false;

    var clone = function(value) {
        return JSON.parse(JSON.stringify(value));
    };

    var normaliseBlock = function(block) {
        if (!block || blockTypes.indexOf(block.type) === -1) {
            return null;
        }

        return Object.assign(clone(defaults[block.type]), block);
    };

    var getString = function(key) {
        return strings[key] || key;
    };

    var escapeHtml = function(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    var getNumber = function(value, fallback, min, max) {
        var number = parseInt(value, 10);
        if (isNaN(number)) {
            number = fallback;
        }
        return Math.max(min, Math.min(max, number));
    };

    var getAlign = function(value) {
        return ['left', 'center', 'right'].indexOf(value) === -1 ? 'left' : value;
    };

    var getFontFamily = function(value) {
        return fontFamilies[value] || fontFamilies.arial;
    };

    var createButton = function(label, className) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = className || 'btn btn-secondary btn-sm';
        button.textContent = label;
        return button;
    };

    var getBackgroundColor = function() {
        var value = backgroundInput ? backgroundInput.value : '';
        return /^#[0-9a-f]{6}$/i.test(value) ? value : '#ffffff';
    };

    var applyBackgroundPreview = function() {
        var previewWrap = root ? root.querySelector('[data-region="previewwrap"]') : null;
        if (previewWrap) {
            previewWrap.style.backgroundColor = getBackgroundColor();
        }
    };

    var elementIcons = {
        header: '<svg viewBox="0 0 48 40" focusable="false" aria-hidden="true">'
            + '<rect class="local-mailwhistle-icon-blue" x="6" y="8" width="36" height="24" rx="3"/>'
            + '<rect class="local-mailwhistle-icon-white" x="15" y="17" width="18" height="4" rx="1"/>'
            + '<rect class="local-mailwhistle-icon-white" x="18" y="24" width="12" height="3" rx="1"/>'
            + '</svg>',
        logo: '<svg viewBox="0 0 48 40" focusable="false" aria-hidden="true">'
            + '<rect class="local-mailwhistle-icon-blue" x="9" y="10" width="30" height="20" rx="3"/>'
            + '<rect class="local-mailwhistle-icon-white" x="13" y="14" width="22" height="12" rx="2"/>'
            + '<rect class="local-mailwhistle-icon-green" x="17" y="18" width="14" height="4" rx="1"/>'
            + '</svg>',
        text: '<svg viewBox="0 0 48 40" focusable="false" aria-hidden="true">'
            + '<rect class="local-mailwhistle-icon-dark" x="8" y="9" width="30" height="5" rx="2"/>'
            + '<rect class="local-mailwhistle-icon-dark" x="8" y="18" width="25" height="5" rx="2"/>'
            + '<rect class="local-mailwhistle-icon-dark" x="8" y="27" width="30" height="5" rx="2"/>'
            + '<rect class="local-mailwhistle-icon-green" x="36" y="8" width="4" height="25" rx="1"/>'
            + '</svg>',
        button: '<svg viewBox="0 0 48 40" focusable="false" aria-hidden="true">'
            + '<rect class="local-mailwhistle-icon-dark" x="8" y="13" width="32" height="14" rx="4"/>'
            + '<rect class="local-mailwhistle-icon-green" x="17" y="18" width="14" height="4" rx="1"/>'
            + '</svg>',
        image: '<svg viewBox="0 0 48 40" focusable="false" aria-hidden="true">'
            + '<rect class="local-mailwhistle-icon-dark" x="8" y="8" width="32" height="24" rx="3"/>'
            + '<rect class="local-mailwhistle-icon-white" x="12" y="12" width="24" height="16" rx="2"/>'
            + '<circle class="local-mailwhistle-icon-green" cx="17" cy="17" r="3"/>'
            + '<polygon class="local-mailwhistle-icon-green" points="14,27 22,19 28,25 32,21 36,27"/>'
            + '</svg>',
        highlight: '<svg viewBox="0 0 48 40" focusable="false" aria-hidden="true">'
            + '<rect class="local-mailwhistle-icon-dark" x="8" y="9" width="32" height="22" rx="3"/>'
            + '<rect class="local-mailwhistle-icon-green" x="8" y="9" width="7" height="22" rx="3"/>'
            + '<rect class="local-mailwhistle-icon-white" x="19" y="16" width="14" height="4" rx="1"/>'
            + '<rect class="local-mailwhistle-icon-white" x="19" y="23" width="10" height="3" rx="1"/>'
            + '</svg>',
        columns: '<svg viewBox="0 0 48 40" focusable="false" aria-hidden="true">'
            + '<rect class="local-mailwhistle-icon-dark" x="8" y="8" width="32" height="24" rx="3"/>'
            + '<rect class="local-mailwhistle-icon-white" x="12" y="12" width="10" height="16" rx="1"/>'
            + '<rect class="local-mailwhistle-icon-white" x="26" y="12" width="10" height="16" rx="1"/>'
            + '<rect class="local-mailwhistle-icon-green" x="22" y="10" width="4" height="20" rx="1"/>'
            + '</svg>',
        social: '<svg viewBox="0 0 48 40" focusable="false" aria-hidden="true">'
            + '<rect class="local-mailwhistle-icon-dark" x="18" y="12" width="14" height="4" rx="2" transform="rotate(-28 25 14)"/>'
            + '<rect class="local-mailwhistle-icon-dark" x="18" y="24" width="14" height="4" rx="2" transform="rotate(28 25 26)"/>'
            + '<circle class="local-mailwhistle-icon-green" cx="14" cy="20" r="5"/>'
            + '<circle class="local-mailwhistle-icon-dark" cx="34" cy="10" r="5"/>'
            + '<circle class="local-mailwhistle-icon-dark" cx="34" cy="30" r="5"/>'
            + '</svg>',
        divider: '<svg viewBox="0 0 48 40" focusable="false" aria-hidden="true">'
            + '<rect class="local-mailwhistle-icon-dark" x="10" y="11" width="28" height="5" rx="2"/>'
            + '<rect class="local-mailwhistle-icon-green" x="16" y="18" width="16" height="4" rx="2"/>'
            + '<rect class="local-mailwhistle-icon-dark" x="10" y="25" width="28" height="5" rx="2"/>'
            + '</svg>',
        footer: '<svg viewBox="0 0 48 40" focusable="false" aria-hidden="true">'
            + '<rect class="local-mailwhistle-icon-green" x="14" y="8" width="20" height="5" rx="2"/>'
            + '<rect class="local-mailwhistle-icon-dark" x="8" y="14" width="32" height="17" rx="3"/>'
            + '<rect class="local-mailwhistle-icon-white" x="15" y="21" width="18" height="4" rx="1"/>'
            + '</svg>'
    };

    var createElementButton = function(type, label) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'local-mailwhistle-builder-element local-mailwhistle-builder-element-' + type;

        var icon = document.createElement('span');
        icon.className = 'local-mailwhistle-builder-element-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = elementIcons[type] || '';
        button.appendChild(icon);

        var text = document.createElement('span');
        text.className = 'local-mailwhistle-builder-element-label';
        text.textContent = label;
        button.appendChild(text);

        return button;
    };

    var normaliseState = function(value) {
        try {
            var parsed = JSON.parse(value || '{}');
            if (parsed && Array.isArray(parsed.blocks)) {
                return {
                    blocks: parsed.blocks.map(normaliseBlock).filter(function(block) {
                        return block !== null;
                    })
                };
            }
        } catch (error) {
            // Use an empty document when stored JSON is missing or malformed.
        }

        return {
            blocks: []
        };
    };

    var saveState = function() {
        hiddenInput.value = JSON.stringify(state);
    };

    var createField = function(block, field, label, type, options) {
        options = options || {};
        // Allow "#", empty, and {{placeholder}} values. Native type=url rejects them
        // and blocks Save with HTML5 validation.
        if (type === 'url') {
            type = 'text';
        }
        var wrapper = document.createElement('div');
        wrapper.className = 'local-mailwhistle-builder-field';
        if (type === 'color') {
            wrapper.className += ' local-mailwhistle-builder-field-colour';
        }
        if (options.wide) {
            wrapper.className += ' local-mailwhistle-builder-field-wide';
        }

        var labelNode = document.createElement('label');
        labelNode.textContent = label;
        wrapper.appendChild(labelNode);

        var input;
        if (type === 'select') {
            input = document.createElement('select');
            (options.choices || []).forEach(function(choice) {
                var option = document.createElement('option');
                option.value = choice[0];
                option.textContent = choice[1];
                input.appendChild(option);
            });
        } else if (type === 'textarea') {
            input = document.createElement('textarea');
            input.rows = 3;
        } else {
            input = document.createElement('input');
            input.type = type || 'text';
            if (type === 'number') {
                if (typeof options.min !== 'undefined') {
                    input.min = options.min;
                }
                if (typeof options.max !== 'undefined') {
                    input.max = options.max;
                }
                input.step = options.step || 1;
            }
        }

        input.value = block[field] || '';
        var update = function() {
            block[field] = input.value;
            saveState();
            renderPreview();
        };
        input.addEventListener('input', update);
        input.addEventListener('change', update);
        wrapper.appendChild(input);

        return wrapper;
    };

    var createImageSourceField = function(block) {
        var fragment = document.createDocumentFragment();
        var urlField = createField(block, 'url', getString('url'), 'url', {wide: true});
        var urlInput = urlField.querySelector('input');

        if (resourceFiles.length) {
            var wrapper = document.createElement('div');
            wrapper.className = 'local-mailwhistle-builder-field local-mailwhistle-builder-field-wide';

            resourceFieldSeq += 1;
            var selectId = 'mw-resource-' + resourceFieldSeq;
            var labelNode = document.createElement('label');
            labelNode.setAttribute('for', selectId);
            labelNode.textContent = getString('chooseimage');
            wrapper.appendChild(labelNode);

            var select = document.createElement('select');
            select.id = selectId;
            select.className = 'local-mailwhistle-builder-resource';
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = getString('chooseimageempty');
            select.appendChild(empty);
            resourceFiles.forEach(function(item) {
                var option = document.createElement('option');
                option.value = item.url;
                option.textContent = item.filename;
                if (item.url === (block.url || '')) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
            select.addEventListener('change', function() {
                block.url = select.value;
                if (urlInput) {
                    urlInput.value = select.value;
                }
                saveState();
                renderPreview();
            });
            wrapper.appendChild(select);
            fragment.appendChild(wrapper);
        } else {
            var emptyNote = document.createElement('p');
            emptyNote.className = 'local-mailwhistle-builder-resource-empty';
            if (resourcesUrl) {
                var link = document.createElement('a');
                link.href = resourcesUrl;
                link.textContent = getString('uploadimages');
                emptyNote.appendChild(link);
            } else {
                emptyNote.textContent = getString('uploadimages');
            }
            var noteWrap = document.createElement('div');
            noteWrap.className = 'local-mailwhistle-builder-field local-mailwhistle-builder-field-wide';
            noteWrap.appendChild(emptyNote);
            fragment.appendChild(noteWrap);
        }

        fragment.appendChild(urlField);
        return fragment;
    };

    var renderBlockFields = function(block, body) {
        body.className = 'local-mailwhistle-builder-block-body';

        if (block.type === 'header') {
            body.appendChild(createField(block, 'title', getString('title'), 'text', {wide: true}));
            body.appendChild(createField(block, 'subtitle', getString('subtitle'), 'text', {wide: true}));
            body.appendChild(createField(block, 'fontfamily', getString('fontfamily'), 'select', {choices: fontOptions}));
            body.appendChild(createField(block, 'fontsize', getString('fontsize'), 'number', {min: 16, max: 42}));
            body.appendChild(createField(block, 'align', getString('align'), 'select', {choices: alignOptions}));
            body.appendChild(createField(block, 'padding', getString('padding'), 'number', {min: 8, max: 56}));
            body.appendChild(createField(block, 'background', getString('background'), 'color'));
            body.appendChild(createField(block, 'color', getString('color'), 'color'));
        } else if (block.type === 'text') {
            body.appendChild(createField(block, 'content', getString('content'), 'textarea', {wide: true}));
            body.appendChild(createField(block, 'fontfamily', getString('fontfamily'), 'select', {choices: fontOptions}));
            body.appendChild(createField(block, 'fontsize', getString('fontsize'), 'number', {min: 11, max: 28}));
            body.appendChild(createField(block, 'align', getString('align'), 'select', {choices: alignOptions}));
            body.appendChild(createField(block, 'padding', getString('padding'), 'number', {min: 8, max: 48}));
            body.appendChild(createField(block, 'color', getString('color'), 'color'));
        } else if (block.type === 'button') {
            body.appendChild(createField(block, 'label', getString('label'), 'text', {wide: true}));
            body.appendChild(createField(block, 'url', getString('url'), 'url', {wide: true}));
            body.appendChild(createField(block, 'align', getString('align'), 'select', {choices: alignOptions}));
            body.appendChild(createField(block, 'padding', getString('padding'), 'number', {min: 8, max: 48}));
            body.appendChild(createField(block, 'background', getString('background'), 'color'));
            body.appendChild(createField(block, 'color', getString('color'), 'color'));
        } else if (block.type === 'image') {
            body.appendChild(createImageSourceField(block));
            body.appendChild(createField(block, 'alt', getString('alt'), 'text', {wide: true}));
            body.appendChild(createField(block, 'width', getString('width'), 'number', {min: 10, max: 100}));
            body.appendChild(createField(block, 'align', getString('align'), 'select', {choices: alignOptions}));
            body.appendChild(createField(block, 'padding', getString('padding'), 'number', {min: 0, max: 48}));
        } else if (block.type === 'logo') {
            body.appendChild(createImageSourceField(block));
            body.appendChild(createField(block, 'alt', getString('alt'), 'text', {wide: true}));
            body.appendChild(createField(block, 'width', getString('width'), 'number', {min: 10, max: 60}));
            body.appendChild(createField(block, 'align', getString('align'), 'select', {choices: alignOptions}));
            body.appendChild(createField(block, 'padding', getString('padding'), 'number', {min: 0, max: 40}));
        } else if (block.type === 'highlight') {
            body.appendChild(createField(block, 'title', getString('title'), 'text', {wide: true}));
            body.appendChild(createField(block, 'content', getString('content'), 'textarea', {wide: true}));
            body.appendChild(createField(block, 'fontfamily', getString('fontfamily'), 'select', {choices: fontOptions}));
            body.appendChild(createField(block, 'fontsize', getString('fontsize'), 'number', {min: 12, max: 24}));
            body.appendChild(createField(block, 'padding', getString('padding'), 'number', {min: 8, max: 40}));
            body.appendChild(createField(block, 'background', getString('background'), 'color'));
            body.appendChild(createField(block, 'color', getString('color'), 'color'));
            body.appendChild(createField(block, 'bordercolor', getString('bordercolor'), 'color'));
        } else if (block.type === 'social') {
            body.appendChild(createField(block, 'label1', getString('label1'), 'text'));
            body.appendChild(createField(block, 'url1', getString('url1'), 'url'));
            body.appendChild(createField(block, 'label2', getString('label2'), 'text'));
            body.appendChild(createField(block, 'url2', getString('url2'), 'url'));
            body.appendChild(createField(block, 'label3', getString('label3'), 'text'));
            body.appendChild(createField(block, 'url3', getString('url3'), 'url'));
            body.appendChild(createField(block, 'align', getString('align'), 'select', {choices: alignOptions}));
            body.appendChild(createField(block, 'padding', getString('padding'), 'number', {min: 8, max: 40}));
        } else if (block.type === 'columns') {
            body.appendChild(createField(block, 'lefttitle', getString('lefttitle'), 'text', {wide: true}));
            body.appendChild(createField(block, 'leftcontent', getString('leftcontent'), 'textarea', {wide: true}));
            body.appendChild(createField(block, 'righttitle', getString('righttitle'), 'text', {wide: true}));
            body.appendChild(createField(block, 'rightcontent', getString('rightcontent'), 'textarea', {wide: true}));
            body.appendChild(createField(block, 'fontfamily', getString('fontfamily'), 'select', {choices: fontOptions}));
            body.appendChild(createField(block, 'fontsize', getString('fontsize'), 'number', {min: 11, max: 22}));
            body.appendChild(createField(block, 'padding', getString('padding'), 'number', {min: 8, max: 40}));
            body.appendChild(createField(block, 'background', getString('background'), 'color'));
            body.appendChild(createField(block, 'color', getString('color'), 'color'));
        } else if (block.type === 'footer') {
            body.appendChild(createField(block, 'content', getString('content'), 'textarea', {wide: true}));
            body.appendChild(createField(block, 'fontfamily', getString('fontfamily'), 'select', {choices: fontOptions}));
            body.appendChild(createField(block, 'fontsize', getString('fontsize'), 'number', {min: 10, max: 18}));
            body.appendChild(createField(block, 'align', getString('align'), 'select', {choices: alignOptions}));
            body.appendChild(createField(block, 'padding', getString('padding'), 'number', {min: 8, max: 48}));
            body.appendChild(createField(block, 'background', getString('background'), 'color'));
            body.appendChild(createField(block, 'color', getString('color'), 'color'));
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

    var escapeMultiline = function(value) {
        return escapeHtml(value).replace(/\n/g, '<br>');
    };

    var previewPlaceholder = function(padding, innerPadding, background, message) {
        return '<div style="margin:8px 20px ' + padding + 'px;padding:' + innerPadding
            + 'px 16px;text-align:center;border:1px dashed #c8d0da;background:' + background
            + ';color:#52616f;">' + escapeHtml(message) + '</div>';
    };

    var previewMedia = function(
        block,
        paddingDefault,
        paddingMax,
        widthDefault,
        widthMax,
        placeholderPadding,
        placeholderBg,
        placeholderKey
    ) {
        var padding = getNumber(block.padding, paddingDefault, 0, paddingMax);
        var width = getNumber(block.width, widthDefault, 10, widthMax);
        if (!block.url) {
            return previewPlaceholder(padding, placeholderPadding, placeholderBg, getString(placeholderKey));
        }
        return '<div style="padding:8px 20px ' + padding + 'px;text-align:'
            + escapeHtml(getAlign(block.align || 'center'))
            + ';"><img src="' + escapeHtml(block.url) + '" alt="' + escapeHtml(block.alt)
            + '" style="display:inline-block;width:' + width + '%;max-width:100%;height:auto;border:0;"></div>';
    };

    var previewHeader = function(block) {
        var padding = getNumber(block.padding, 32, 8, 56);
        var size = getNumber(block.fontsize, 28, 16, 42);
        return '<div style="padding:' + padding + 'px 20px;text-align:'
            + escapeHtml(getAlign(block.align || 'center'))
            + ';font-family:' + escapeHtml(getFontFamily(block.fontfamily))
            + ';background:' + escapeHtml(block.background || '#1f4f82')
            + ';color:' + escapeHtml(block.color || '#ffffff') + ';">'
            + '<h2 style="margin:0 0 6px;font-size:' + size + 'px;color:inherit;">'
            + escapeHtml(block.title) + '</h2>'
            + '<p style="margin:0;color:inherit;">' + escapeHtml(block.subtitle) + '</p></div>';
    };

    var previewText = function(block) {
        var padding = getNumber(block.padding, 24, 8, 48);
        var size = getNumber(block.fontsize, 16, 11, 28);
        return '<div style="padding:' + padding + 'px 20px;font-family:'
            + escapeHtml(getFontFamily(block.fontfamily))
            + ';font-size:' + size + 'px;line-height:1.55;text-align:'
            + escapeHtml(getAlign(block.align))
            + ';color:' + escapeHtml(block.color || '#1f2933') + ';">'
            + escapeMultiline(block.content) + '</div>';
    };

    var previewButton = function(block) {
        var padding = getNumber(block.padding, 24, 8, 48);
        return '<div style="padding:8px 20px ' + padding + 'px;text-align:'
            + escapeHtml(getAlign(block.align || 'center'))
            + ';"><span style="display:inline-block;padding:10px 16px;background:'
            + escapeHtml(block.background || '#1f4f82') + ';color:'
            + escapeHtml(block.color || '#ffffff')
            + ';border-radius:4px;font-weight:bold;">' + escapeHtml(block.label) + '</span></div>';
    };

    var previewHighlight = function(block) {
        var padding = getNumber(block.padding, 20, 8, 40);
        var size = getNumber(block.fontsize, 16, 12, 24);
        return '<div style="padding:8px 20px 20px;"><div style="padding:' + padding
            + 'px;border-left:5px solid ' + escapeHtml(block.bordercolor || '#1f4f82')
            + ';background:' + escapeHtml(block.background || '#f3f7fb')
            + ';font-family:' + escapeHtml(getFontFamily(block.fontfamily))
            + ';color:' + escapeHtml(block.color || '#1f2933') + ';">'
            + '<strong style="display:block;margin-bottom:6px;font-size:' + size + 'px;">'
            + escapeHtml(block.title) + '</strong>'
            + '<div style="font-size:' + size + 'px;line-height:1.5;">'
            + escapeMultiline(block.content) + '</div></div></div>';
    };

    var previewSocial = function(block) {
        var padding = getNumber(block.padding, 20, 8, 40);
        var links = [
            [block.label1, block.url1],
            [block.label2, block.url2],
            [block.label3, block.url3]
        ].filter(function(link) {
            return link[0] || link[1];
        }).map(function(link) {
            return '<span style="display:inline-block;margin:0 8px 8px;"><a href="'
                + escapeHtml(link[1] || '#') + '" style="color:#1f4f82;text-decoration:underline;">'
                + escapeHtml(link[0] || link[1]) + '</a></span>';
        }).join('');
        return '<div style="padding:8px 20px ' + padding + 'px;text-align:'
            + escapeHtml(getAlign(block.align || 'center'))
            + ';font-family:Arial,Helvetica,sans-serif;font-size:14px;">' + links + '</div>';
    };

    var previewColumns = function(block) {
        var padding = getNumber(block.padding, 20, 8, 40);
        var size = getNumber(block.fontsize, 15, 11, 22);
        var cellStyle = 'padding:' + padding + 'px;font-family:'
            + escapeHtml(getFontFamily(block.fontfamily))
            + ';font-size:' + size + 'px;line-height:1.5;color:'
            + escapeHtml(block.color || '#1f2933') + ';vertical-align:top;';
        return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:'
            + escapeHtml(block.background || '#ffffff') + ';"><tr><td width="50%" style="' + cellStyle + '">'
            + '<strong style="display:block;margin-bottom:6px;">' + escapeHtml(block.lefttitle) + '</strong>'
            + escapeMultiline(block.leftcontent) + '</td><td width="50%" style="' + cellStyle + '">'
            + '<strong style="display:block;margin-bottom:6px;">' + escapeHtml(block.righttitle) + '</strong>'
            + escapeMultiline(block.rightcontent) + '</td></tr></table>';
    };

    var previewFooter = function(block) {
        var padding = getNumber(block.padding, 22, 8, 48);
        var size = getNumber(block.fontsize, 13, 10, 18);
        return '<div style="padding:' + padding + 'px 20px;text-align:'
            + escapeHtml(getAlign(block.align || 'center'))
            + ';font-family:' + escapeHtml(getFontFamily(block.fontfamily))
            + ';background:' + escapeHtml(block.background || '#f3f5f8')
            + ';color:' + escapeHtml(block.color || '#52616f')
            + ';font-size:' + size + 'px;line-height:1.5;">'
            + escapeMultiline(block.content) + '</div>';
    };

    var previewRenderers = {
        header: previewHeader,
        text: previewText,
        button: previewButton,
        image: function(block) {
            return previewMedia(block, 24, 48, 100, 100, 42, '#f3f5f8', 'imageplaceholder');
        },
        logo: function(block) {
            return previewMedia(block, 18, 40, 35, 60, 22, '#f8fafc', 'logoplaceholder');
        },
        highlight: previewHighlight,
        social: previewSocial,
        columns: previewColumns,
        divider: function() {
            return '<div style="height:1px;margin:8px 20px;background:#d8dee6;"></div>';
        },
        footer: previewFooter
    };

    var renderPreviewBlock = function(block) {
        var renderer = previewRenderers[block.type];
        return renderer ? renderer(block) : '';
    };

    var renderPreview = function() {
        var preview = root.querySelector('[data-region="preview"]');
        if (preview) {
            preview.innerHTML = state.blocks.map(renderPreviewBlock).join('');
        }
        applyBackgroundPreview();
    };

    var render = function() {
        renderList(root.querySelector('[data-region="list"]'));
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
        var textarea = document.getElementById('id_bodyhtml_editor');

        root.style.display = builderVisible ? '' : 'none';
        if (htmlEditor) {
            htmlEditor.style.display = builderVisible ? 'none' : '';
        }
        // Builder mode renders HTML from JSON server-side. Disable the hidden
        // editor so TinyMCE / HTML5 cannot block Save on an empty textarea.
        if (textarea) {
            textarea.disabled = builderVisible;
            textarea.removeAttribute('required');
        }
    };

    var buildUi = function() {
        root.innerHTML = '';

        var header = document.createElement('div');
        header.className = 'local-mailwhistle-builder-header';
        header.setAttribute('aria-label', getString('builderheading'));

        if (backgroundInput) {
            var originalBackgroundItem = document.getElementById('fitem_id_background');
            if (originalBackgroundItem) {
                originalBackgroundItem.style.display = 'none';
            }
            backgroundInput.setAttribute('type', 'color');
            if (!/^#[0-9a-f]{6}$/i.test(backgroundInput.value)) {
                backgroundInput.value = '#ffffff';
            }

            var design = document.createElement('div');
            design.className = 'local-mailwhistle-builder-design';

            var designLabel = document.createElement('label');
            designLabel.setAttribute('for', backgroundInput.id);
            designLabel.textContent = getString('background');
            design.appendChild(designLabel);

            design.appendChild(backgroundInput);
            backgroundInput.addEventListener('input', applyBackgroundPreview);
            backgroundInput.addEventListener('change', applyBackgroundPreview);
            header.appendChild(design);
        }

        var toolbar = document.createElement('div');
        toolbar.className = 'local-mailwhistle-builder-toolbar';

        [
            ['header', 'addheader'],
            ['logo', 'addlogo'],
            ['text', 'addtext'],
            ['button', 'addbutton'],
            ['image', 'addimage'],
            ['highlight', 'addhighlight'],
            ['columns', 'addcolumns'],
            ['social', 'addsocial'],
            ['divider', 'adddivider'],
            ['footer', 'addfooter']
        ].forEach(function(item) {
            var button = createElementButton(item[0], getString(item[1]));
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
        previewWrap.dataset.region = 'previewwrap';
        var preview = document.createElement('div');
        preview.className = 'local-mailwhistle-builder-preview-inner';
        preview.dataset.region = 'preview';
        previewWrap.appendChild(preview);
        body.appendChild(previewWrap);

        root.appendChild(body);
    };

    var readEmbeddedConfig = function() {
        var node = document.getElementById('local-mailwhistle-template-builder');
        if (!node) {
            return {};
        }
        var raw = node.getAttribute('data-config');
        if (!raw) {
            return {};
        }
        try {
            return JSON.parse(raw);
        } catch (error) {
            return {};
        }
    };

    var init = function(config) {
        if (initialized) {
            return;
        }

        root = document.getElementById('local-mailwhistle-template-builder');
        hiddenInput = document.getElementById('id_builderjson') || document.querySelector('[name="builderjson"]');
        modeSelect = document.getElementById('id_editormode') || document.querySelector('[name="editormode"]');
        backgroundInput = document.getElementById('id_background') || document.querySelector('[name="background"]');
        config = Object.assign({}, readEmbeddedConfig(), config || {});
        strings = config.strings || {};
        resourceFiles = Array.isArray(config.resources) ? config.resources : [];
        resourcesUrl = config.resourcesurl || '';

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

    return {
        init: init
    };
});
