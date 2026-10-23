(function ($) {
    'use strict';

    $(function () {
        var config = window.CodeCanvasPageEditor || {};
        var editorSettings = config.editorSettings || {};
        var previewConfig = config.preview || {};
        var editorInstances = {};
        var textareas = {
            html: document.getElementById('cc-page-html'),
            css: document.getElementById('cc-page-css'),
            js: document.getElementById('cc-page-js')
        };
        var previewFrame = document.getElementById('cc-live-preview');
        var previewShell = document.querySelector('.cc-preview-frame-wrap');
        var previewStatus = document.getElementById('cc-preview-status');
        var editorState = document.getElementById('cc-editor-state');
        var form = document.getElementById('cc-page-editor-form');
        var updateTimer = null;
        var dirty = false;
        var saving = false;

        if (!textareas.html || !textareas.css || !textareas.js || !previewFrame || !previewShell) {
            return;
        }

        function getValue(key) {
            if (editorInstances[key] && editorInstances[key].getValue) {
                return editorInstances[key].getValue();
            }
            return textareas[key] ? textareas[key].value : '';
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>\"']/g, function (character) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', "'": '&#039;' }[character];
            });
        }

        function replacePreviewTokens(value) {
            var titleField = document.getElementById('cc-page-title');
            var data = {
                'site.name': previewConfig.siteName || '',
                'site.description': previewConfig.siteDescription || '',
                'site.url': previewConfig.siteUrl || '',
                'post.title': titleField ? titleField.value || 'Your page title' : 'Your page title',
                'post.url': previewConfig.pageUrl || previewConfig.siteUrl || '#'
            };
            return String(value || '').replace(/\{\{\s*(site\.(?:name|description|url)|post\.(?:title|url))\s*\}\}/g, function (all, token) {
                return Object.prototype.hasOwnProperty.call(data, token) ? escapeHtml(data[token]) : all;
            });
        }

        function setEditorState(message, state) {
            if (!editorState) { return; }
            editorState.setAttribute('data-state', state || 'saved');
            var stateWrap = document.getElementById('cc-ide-save-status');
            if (stateWrap) { stateWrap.setAttribute('data-state', state || 'saved'); }
            var dot = editorState.querySelector('.cc-state-dot');
            editorState.textContent = '';
            if (dot) { editorState.appendChild(dot); }
            editorState.appendChild(document.createTextNode(message));
        }

        function markDirty() {
            if (saving) { return; }
            dirty = true;
            setEditorState('Unsaved changes', 'dirty');
        }

        function buildPreviewDocument() {
            var html = replacePreviewTokens(getValue('html'));
            var css = String(previewConfig.baseCss || '') + '\n' + String(previewConfig.globalCss || '') + '\n' + getValue('css');
            var js = String(previewConfig.globalJs || '') + '\n' + getValue('js');
            var layout = document.getElementById('cc-page-layout').value;
            var header = layout === 'blank' ? '' : replacePreviewTokens(previewConfig.headerHtml || '');
            var footer = layout === 'blank' ? '' : replacePreviewTokens(previewConfig.footerHtml || '');

            // The preview is deliberately sandboxed and denies outbound connections.
            // Escape closing tags so source entered in a textarea cannot break the wrapper.
            css = css.replace(/<\/style/gi, '<\\/style');
            js = js.replace(/<\/script/gi, '<\\/script');
            var siteOrigin = String(previewConfig.siteOrigin || '');
            var sameSite = siteOrigin ? ' ' + siteOrigin : '';
            var csp = "default-src 'none'; img-src data: blob:" + sameSite + "; font-src data:" + sameSite + "; style-src 'unsafe-inline' data:" + sameSite + "; script-src 'unsafe-inline'; connect-src 'none'; form-action 'none'; frame-src 'none'; object-src 'none'; media-src data: blob:" + sameSite + "; base-uri 'none'";
            return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="' + csp + '"><title>CodeCanvas Preview</title><style>html,body{margin:0;padding:0;min-height:100%;}*{box-sizing:border-box;} ' + css + '</style></head><body>' + header + html + footer + '<script>' + js + '<\/script></body></html>';
        }

        function updatePreview() {
            detectPossibleSecret();
            if (previewStatus) { previewStatus.textContent = 'Updating preview…'; }
            window.clearTimeout(updateTimer);
            updateTimer = window.setTimeout(function () {
                previewFrame.srcdoc = buildPreviewDocument();
            }, 220);
        }

        function detectPossibleSecret() {
            var warning = document.getElementById('cc-secret-warning');
            if (!warning) { return; }
            var source = [getValue('html'), getValue('css'), getValue('js')].join('\n');
            var patterns = [
                /\bsk-(?:proj-)?[A-Za-z0-9_-]{16,}\b/,
                /\b(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{16,}\b/,
                /\bgh[pousr]_[A-Za-z0-9]{20,}\b/,
                /\bgithub_pat_[A-Za-z0-9_]{22,}\b/,
                /\bglpat-[A-Za-z0-9_-]{20,}\b/,
                /\bxox[baprs]-[A-Za-z0-9-]{10,}\b/,
                /(?:api[_-]?key|client[_-]?secret|private[_-]?key|access[_-]?token|bearer)\s*[:=]\s*["']?[A-Za-z0-9._~+\/-]{12,}/i,
                /-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----/
            ];
            warning.hidden = !patterns.some(function (pattern) { return pattern.test(source); });
        }

        var currentEditorFontSize = 14;

        function setEditorFontSize(size) {
            currentEditorFontSize = Math.max(12, Math.min(20, Number(size) || 14));
            Object.keys(textareas).forEach(function (key) {
                if (textareas[key]) {
                    textareas[key].style.fontSize = currentEditorFontSize + 'px';
                }
                if (editorInstances[key]) {
                    var wrapper = editorInstances[key].getWrapperElement();
                    if (wrapper) { wrapper.style.fontSize = currentEditorFontSize + 'px'; }
                    editorInstances[key].refresh();
                }
            });
            var minus = document.querySelector('[data-editor-font="-1"]');
            var plus = document.querySelector('[data-editor-font="1"]');
            if (minus) { minus.disabled = currentEditorFontSize <= 12; }
            if (plus) { plus.disabled = currentEditorFontSize >= 20; }
        }

        document.querySelectorAll('[data-editor-font]').forEach(function (button) {
            button.addEventListener('click', function () {
                setEditorFontSize(currentEditorFontSize + Number(button.getAttribute('data-editor-font')));
            });
        });

        var focusToggle = document.getElementById('cc-focus-toggle');
        function setFocusMode(enabled) {
            document.body.classList.toggle('codecanvas-focus-mode', !!enabled);
            if (!focusToggle) { return; }
            focusToggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            var icon = focusToggle.querySelector('.dashicons');
            var label = focusToggle.querySelector('.cc-focus-label');
            if (icon) {
                icon.classList.toggle('dashicons-editor-expand', !enabled);
                icon.classList.toggle('dashicons-editor-contract', !!enabled);
            }
            if (label) {
                label.textContent = enabled ? 'Exit IDE' : 'WordPress Admin';
            }
            window.dispatchEvent(new Event('resize'));
            Object.keys(editorInstances).forEach(function (key) {
                if (editorInstances[key]) { editorInstances[key].refresh(); }
            });
        }
        if (focusToggle) {
            focusToggle.addEventListener('click', function () {
                setFocusMode(!document.body.classList.contains('codecanvas-focus-mode'));
            });
        }

        function initializeCodeEditors() {
            if (!window.wp || !wp.codeEditor || !wp.codeEditor.initialize) {
                return;
            }
            Object.keys(textareas).forEach(function (key) {
                if (!textareas[key] || !editorSettings[key]) {
                    return;
                }
                var instance = wp.codeEditor.initialize(textareas[key], editorSettings[key]);
                if (instance && instance.codemirror) {
                    editorInstances[key] = instance.codemirror;
                    instance.codemirror.on('change', function () {
                        markDirty();
                        setFileDirty(key, true);
                        updatePreview();
                    });
                    instance.codemirror.on('cursorActivity', function () {
                        var cursor = instance.codemirror.getCursor();
                        var cursorStatus = document.getElementById('cc-ide-cursor');
                        if (cursorStatus && cursor) { cursorStatus.textContent = 'Ln ' + (cursor.line + 1) + ', Col ' + (cursor.ch + 1); }
                    });
                }
            });
        }

        function setFileDirty(key, isDirty) {
            document.querySelectorAll('[data-file-state="' + key + '"]').forEach(function (marker) {
                marker.classList.toggle('is-dirty', !!isDirty);
            });
        }

        function activateCodeTab(target, focusEditor) {
            if (['html', 'css', 'js'].indexOf(target) === -1) { target = 'html'; }
            document.querySelectorAll('[data-code-tab]').forEach(function (item) {
                var active = item.getAttribute('data-code-tab') === target;
                item.classList.toggle('is-active', active);
                if (item.hasAttribute('aria-selected')) {
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                }
                if (item.classList.contains('cc-ide-editor-tab')) { item.tabIndex = active ? 0 : -1; }
            });
            document.querySelectorAll('[data-code-pane]').forEach(function (pane) {
                var active = pane.getAttribute('data-code-pane') === target;
                pane.hidden = !active;
                pane.classList.toggle('is-active', active);
            });
            var currentFile = document.getElementById('cc-ide-current-file');
            if (currentFile) { currentFile.textContent = { html: 'index.html', css: 'style.css', js: 'script.js' }[target]; }
            var status = document.getElementById('cc-ide-status-message');
            if (status) { status.textContent = { html: 'HTML', css: 'CSS', js: 'JavaScript' }[target]; }
            if (editorInstances[target]) {
                window.setTimeout(function () {
                    editorInstances[target].refresh();
                    if (focusEditor) { editorInstances[target].focus(); }
                }, 20);
            }
        }

        document.querySelectorAll('[data-code-tab]').forEach(function (button) {
            button.addEventListener('click', function () {
                activateCodeTab(button.getAttribute('data-code-tab'), false);
            });
            button.addEventListener('keydown', function (event) {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key) || !button.classList.contains('cc-ide-editor-tab')) { return; }
                event.preventDefault();
                var tabs = Array.prototype.slice.call(document.querySelectorAll('.cc-ide-editor-tab[data-code-tab]'));
                var current = tabs.indexOf(button);
                var next = current;
                if (event.key === 'ArrowLeft') { next = (current - 1 + tabs.length) % tabs.length; }
                else if (event.key === 'ArrowRight') { next = (current + 1) % tabs.length; }
                else if (event.key === 'Home') { next = 0; }
                else if (event.key === 'End') { next = tabs.length - 1; }
                tabs[next].focus();
                activateCodeTab(tabs[next].getAttribute('data-code-tab'), false);
            });
        });

        // VS Code-style activity bar and sidebar panels.
        function activateSidebarView(view) {
            var valid = ['explorer', 'search', 'settings'];
            if (valid.indexOf(view) === -1) { view = 'explorer'; }
            document.querySelectorAll('[data-ide-sidebar-view]').forEach(function (panel) {
                var active = panel.getAttribute('data-ide-sidebar-view') === view;
                panel.hidden = !active;
                panel.classList.toggle('is-active', active);
            });
            document.querySelectorAll('[data-ide-activity]').forEach(function (button) {
                var active = button.getAttribute('data-ide-activity') === view;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            var workbench = document.querySelector('.cc-ide-workbench');
            if (workbench) { workbench.classList.remove('sidebar-collapsed'); }
            var settingsToggle = document.getElementById('cc-toggle-settings');
            if (settingsToggle) { settingsToggle.setAttribute('aria-pressed', view === 'settings' ? 'true' : 'false'); }
        }
        document.querySelectorAll('[data-ide-activity]').forEach(function (button) {
            button.addEventListener('click', function () {
                var view = button.getAttribute('data-ide-activity');
                var panel = document.querySelector('[data-ide-sidebar-view="' + view + '"]');
                if (panel && !panel.hidden && view === 'explorer') {
                    var workbench = document.querySelector('.cc-ide-workbench');
                    if (workbench) { workbench.classList.toggle('sidebar-collapsed'); }
                    return;
                }
                activateSidebarView(view);
                if (view === 'search') {
                    var searchField = document.getElementById('cc-ide-search-input');
                    if (searchField) { window.setTimeout(function () { searchField.focus(); }, 20); }
                }
            });
        });
        document.querySelectorAll('[data-ide-collapse-sidebar]').forEach(function (button) {
            button.addEventListener('click', function () {
                var workbench = document.querySelector('.cc-ide-workbench');
                if (workbench) { workbench.classList.toggle('sidebar-collapsed'); }
            });
        });

        var previewPanel = document.getElementById('cc-preview-panel');
        var workbench = document.querySelector('.cc-ide-workbench');
        var previewToggle = document.getElementById('cc-toggle-preview');
        function setPreviewOpen(open) {
            if (!previewPanel || !workbench) { return; }
            previewPanel.hidden = !open;
            workbench.classList.toggle('preview-open', !!open);
            if (previewToggle) { previewToggle.setAttribute('aria-pressed', open ? 'true' : 'false'); }
            if (open) {
                updatePreview();
                window.setTimeout(function () {
                    Object.keys(editorInstances).forEach(function (key) {
                        if (editorInstances[key]) { editorInstances[key].refresh(); }
                    });
                }, 50);
            }
        }
        if (previewToggle) { previewToggle.addEventListener('click', function () { setPreviewOpen(!previewPanel || previewPanel.hidden); }); }
        var closePreview = document.getElementById('cc-close-preview');
        if (closePreview) { closePreview.addEventListener('click', function () { setPreviewOpen(false); }); }

        var settingsToggle = document.getElementById('cc-toggle-settings');
        if (settingsToggle) { settingsToggle.addEventListener('click', function () { activateSidebarView('settings'); }); }

        // Search the three current virtual source files and open a match at its line.
        var searchField = document.getElementById('cc-ide-search-input');
        var searchResults = document.getElementById('cc-ide-search-results');
        if (searchField && searchResults) {
            searchField.addEventListener('input', function () {
                var query = searchField.value.trim().toLowerCase();
                searchResults.textContent = '';
                if (!query) {
                    var hint = document.createElement('p');
                    hint.textContent = 'Search HTML, CSS and JavaScript in this page.';
                    searchResults.appendChild(hint);
                    return;
                }
                var matches = [];
                ['html', 'css', 'js'].forEach(function (key) {
                    var lines = getValue(key).split('\n');
                    lines.forEach(function (line, index) {
                        if (line.toLowerCase().indexOf(query) !== -1 && matches.length < 100) {
                            matches.push({ key: key, line: index, text: line.trim().slice(0, 180) });
                        }
                    });
                });
                if (!matches.length) {
                    var empty = document.createElement('p');
                    empty.textContent = 'No results found.';
                    searchResults.appendChild(empty);
                    return;
                }
                var summary = document.createElement('p');
                summary.textContent = matches.length + (matches.length === 100 ? '+ matches' : ' matches');
                searchResults.appendChild(summary);
                matches.forEach(function (match) {
                    var result = document.createElement('button');
                    result.type = 'button';
                    result.className = 'cc-ide-search-result';
                    var fileName = document.createElement('strong');
                    fileName.textContent = ({ html: 'index.html', css: 'style.css', js: 'script.js' })[match.key] + ':' + (match.line + 1);
                    var snippet = document.createElement('span');
                    snippet.textContent = match.text || '(empty line)';
                    result.appendChild(fileName);
                    result.appendChild(snippet);
                    result.addEventListener('click', function () {
                        activateCodeTab(match.key, true);
                        if (editorInstances[match.key]) {
                            editorInstances[match.key].setCursor({ line: match.line, ch: Math.max(0, linesStart(match.key, match.line, query)) });
                            editorInstances[match.key].scrollIntoView({ line: match.line, ch: 0 }, 100);
                        }
                    });
                    searchResults.appendChild(result);
                });
            });
        }
        function linesStart(key, lineNumber, query) {
            var lines = getValue(key).split('\n');
            var index = (lines[lineNumber] || '').toLowerCase().indexOf(query);
            return index < 0 ? 0 : index;
        }

        var deviceButtons = Array.prototype.slice.call(document.querySelectorAll('[data-preview-width]'));
        function setPreviewDevice(mode) {
            var validModes = ['desktop', 'tablet', 'mobile'];
            if (validModes.indexOf(mode) === -1) { mode = 'desktop'; }
            previewShell.setAttribute('data-device', mode);
            deviceButtons.forEach(function (item) {
                var active = item.getAttribute('data-preview-width') === mode;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            if (previewFrame && previewFrame.contentWindow) {
                // Force layout recalculation after the preview width changes.
                window.requestAnimationFrame(function () {
                    try { previewFrame.contentWindow.dispatchEvent(new Event('resize')); } catch (ignore) {}
                });
            }
        }
        deviceButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                setPreviewDevice(button.getAttribute('data-preview-width'));
            });
        });
        // Begin in desktop mode so a newly opened editor always uses the full preview canvas.
        setPreviewDevice('desktop');

        var refresh = document.getElementById('cc-refresh-preview');
        if (refresh) {
            refresh.addEventListener('click', function () {
                if (previewStatus) { previewStatus.textContent = 'Refreshing preview…'; }
                previewFrame.srcdoc = buildPreviewDocument();
            });
        }

        if (previewFrame) {
            previewFrame.addEventListener('load', function () {
                if (previewStatus) { previewStatus.textContent = 'Preview ready'; }
            });
        }

        if (form) {
            Array.prototype.slice.call(form.querySelectorAll('input:not([type="hidden"]), select, textarea')).concat([document.getElementById('cc-page-title')].filter(Boolean)).forEach(function (field) {
                field.addEventListener('input', function () {
                    markDirty();
                    if (field.id === 'cc-page-title' || field.id === 'cc-page-layout') { updatePreview(); }
                });
                field.addEventListener('change', function () {
                    markDirty();
                    if (field.id === 'cc-page-title' || field.id === 'cc-page-layout') { updatePreview(); }
                });
            });
            form.addEventListener('submit', function () {
                if (!form.checkValidity()) { return; }
                saving = true;
                dirty = false;
                setEditorState('Saving page…', 'saving');
                Array.prototype.slice.call(form.querySelectorAll('button[type="submit"]')).concat(Array.prototype.slice.call(document.querySelectorAll('[form="cc-page-editor-form"][type="submit"]'))).forEach(function (button) {
                    button.disabled = true;
                    button.setAttribute('aria-disabled', 'true');
                });
            });
        }
        Object.keys(textareas).forEach(function (key) {
            textareas[key].addEventListener('input', function () {
                markDirty();
                setFileDirty(key, true);
                updatePreview();
            });
        });

        window.addEventListener('beforeunload', function (event) {
            if (!dirty || saving) { return; }
            event.preventDefault();
            event.returnValue = '';
        });

        // Keyboard shortcut: save the current page, not the editor document.
        document.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                var form = document.getElementById('cc-page-editor-form');
                if (form && form.requestSubmit) {
                    form.requestSubmit();
                } else if (form) {
                    form.submit();
                }
            }
        });

        initializeCodeEditors();
        activateCodeTab('html', false);
        activateSidebarView('explorer');
        setPreviewOpen(false);
        setEditorFontSize(14);
        // Always start in desktop preview mode. The versioned script URL makes this
        // reliable after theme upgrades, avoiding stale cached device-state code.
        setPreviewDevice('desktop');
        // Open in focus mode on desktop screens: the admin menu can be restored
        // from the same top bar at any time, but the workspace gets the full width.
        setFocusMode(false);
        if (window.matchMedia && window.matchMedia('(min-width: 1101px)').matches) {
            setFocusMode(true);
        }
        updatePreview();
    });
})(jQuery);
