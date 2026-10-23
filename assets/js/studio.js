(function ($) {
    'use strict';

    const cfg = window.CodeCanvasStudioConfig || {};
    const state = {
        root: cfg.defaultRoot || 'themes',
        path: cfg.defaultPath || '',
        openFiles: {},
        tabs: [],
        activePath: '',
        editor: null,
        fallbackTextarea: null,
        activePanel: 'explorer',
        busy: false
    };

    const $id = (id) => document.getElementById(id);
    const $root = $('#cc-studio-root');
    const $fileList = $('#cc-studio-file-list');
    const $tabs = $('#cc-studio-tabs');
    const $host = $('#cc-studio-editor-host');
    const $status = $('#cc-studio-status-message');
    const $save = $('#cc-studio-save');
    const $wrap = $('.cc-studio-wrap');

    // Shared by the editor and the wrap toggle; must live outside init() so
    // activateTab() can read the current preference.
    let wrapEnabled = true;

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
    }

    function notify(message, type) {
        const toast = $id('cc-studio-toast');
        if (!toast) return;
        toast.textContent = message;
        toast.className = 'cc-studio-toast' + (type ? ' is-' + type : '');
        toast.hidden = false;
        window.clearTimeout(notify.timer);
        notify.timer = window.setTimeout(() => { toast.hidden = true; }, 5000);
    }

    function setStatus(message, stateClass) {
        $status.html('<span class="cc-studio-status-dot ' + esc(stateClass || '') + '"></span>' + esc(message));
    }

    function ajax(action, payload) {
        return $.ajax({
            url: cfg.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: Object.assign({ action: 'codecanvas_studio_' + action, nonce: cfg.nonce, root: state.root }, payload || {})
        }).then((response) => {
            if (!response || !response.success) {
                const data = response && response.data ? response.data : {};
                const error = new Error(data.message || (cfg.strings && cfg.strings.error) || 'Request failed.');
                error.data = data;
                throw error;
            }
            return response.data;
        });
    }

    function pathJoin(parent, child) {
        return [String(parent || '').replace(/^\/+|\/+$/g, ''), String(child || '').replace(/^\/+|\/+$/g, '')].filter(Boolean).join('/');
    }

    function pathBase(path) {
        const items = String(path || '').split('/').filter(Boolean);
        return items.length ? items[items.length - 1] : '';
    }

    function pathParent(path) {
        const items = String(path || '').split('/').filter(Boolean);
        items.pop();
        return items.join('/');
    }

    function fileIcon(item) {
        if (item.type === 'dir') return '<span class="dashicons dashicons-open-folder cc-file-icon is-folder"></span>';
        const ext = String(item.ext || '').toLowerCase();
        const cls = ['php', 'phtml'].includes(ext) ? 'is-php' : ['css', 'scss', 'sass'].includes(ext) ? 'is-css' : ['js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx'].includes(ext) ? 'is-js' : ['html', 'htm', 'twig', 'vue'].includes(ext) ? 'is-html' : ['json', 'yml', 'yaml', 'xml'].includes(ext) ? 'is-data' : 'is-file';
        const label = ['php','css','js','html','json','md','svg','sql','ts','tsx','jsx','vue'].includes(ext) ? ext.toUpperCase() : '•';
        return '<span class="cc-studio-ext-icon ' + cls + '">' + esc(label) + '</span>';
    }

    function renderBreadcrumbs() {
        const parts = String(state.path || '').split('/').filter(Boolean);
        let html = '<button type="button" class="cc-studio-crumb" data-path="">' + esc(state.root === 'themes' ? 'Themes' : 'Plugins') + '</button>';
        let accumulated = '';
        parts.forEach((part, index) => {
            accumulated = pathJoin(accumulated, part);
            html += '<span class="cc-studio-crumb-separator">/</span><button type="button" class="cc-studio-crumb" data-path="' + esc(accumulated) + '">' + esc(part) + '</button>';
        });
        $('#cc-studio-breadcrumbs').html(html);
        $('#cc-studio-root-path').text((state.root === 'themes' ? 'wp-content/themes/' : 'wp-content/plugins/') + (state.path || ''));
        let rootLabel = state.root === 'themes' ? 'THEMES' : 'PLUGINS';
        if (state.root === 'themes' && parts.length && parts[0] === cfg.childThemeSlug) rootLabel = 'WEBSITE THEME';
        if (state.root === 'plugins' && parts.length && parts[0] === cfg.frameworkThemeSlug) rootLabel = 'CODECANVAS CORE · READ ONLY';
        $('#cc-studio-root-label').text(rootLabel);
    }

    function renderFileList(items) {
        if (!items.length) {
            $fileList.html('<div class="cc-studio-empty-folder"><span class="dashicons dashicons-open-folder"></span><p>' + esc('This folder is empty or has no supported text files.') + '</p><button type="button" class="button button-small" id="cc-studio-empty-new-file">New file</button></div>');
            $('#cc-studio-empty-new-file').on('click', createFile);
            return;
        }
        const html = items.map((item) => {
            const active = item.path === state.activePath ? ' is-active' : '';
            const readOnly = item.writable === false ? ' is-readonly' : '';
            const kind = item.type === 'dir' ? 'cc-studio-tree-folder' : 'cc-studio-tree-file';
            const title = item.type === 'file' ? formatBytes(item.size) : 'Folder';
            const hint = state.root === 'plugins' && item.path.split('/')[0] === cfg.frameworkThemeSlug ? 'CodeCanvas plugin (read-only)' : item.writable === false ? 'Read-only or server permissions prevent editing' : item.path;
            return '<button type="button" class="cc-studio-tree-item ' + kind + active + readOnly + '" data-path="' + esc(item.path) + '" data-type="' + esc(item.type) + '" title="' + esc(hint) + '">' + fileIcon(item) + '<span class="cc-studio-tree-name">' + esc(item.name) + '</span>' + (item.writable === false ? '<span class="dashicons dashicons-lock cc-studio-tree-lock" aria-label="Read only"></span>' : '') + (item.type === 'file' ? '<span class="cc-studio-tree-size">' + esc(title) + '</span>' : '<span class="dashicons dashicons-arrow-right-alt2 cc-studio-tree-chevron"></span>') + '</button>';
        }).join('');
        $fileList.html(html);
        $fileList.find('.cc-studio-tree-folder').on('click', function () {
            state.path = $(this).data('path');
            refreshList();
        });
        $fileList.find('.cc-studio-tree-file').on('click', function () {
            openFile($(this).data('path'));
        });
    }

    function formatBytes(bytes) {
        bytes = Number(bytes) || 0;
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(2) + ' MB';
    }

    function refreshList() {
        renderBreadcrumbs();
        $fileList.html('<p class="cc-studio-loading">' + esc((cfg.strings && cfg.strings.loading) || 'Loading files…') + '</p>');
        return ajax('list', { path: state.path }).then((data) => {
            renderFileList(data.items || []);
            renderBreadcrumbs();
        }).catch((error) => {
            $fileList.html('<div class="cc-studio-error"><span class="dashicons dashicons-warning"></span><p>' + esc(error.message) + '</p><button type="button" class="button" id="cc-studio-retry">Retry</button></div>');
            $('#cc-studio-retry').on('click', refreshList);
            notify(error.message, 'error');
        });
    }

    function hasUnsavedFiles() {
        return Object.keys(state.openFiles).some((path) => state.openFiles[path] && state.openFiles[path].dirty);
    }

    function editorValue() {
        if (state.editor && state.editor.codemirror) return state.editor.codemirror.getValue();
        if (state.fallbackTextarea) return state.fallbackTextarea.value;
        return '';
    }

    function syncActiveBuffer() {
        if (!state.activePath || !state.openFiles[state.activePath]) return;
        const doc = state.openFiles[state.activePath];
        doc.content = editorValue();
        doc.dirty = doc.content !== doc.cleanContent;
        updateSaveButton();
        renderTabs();
    }

    function disposeEditor() {
        if (state.editor && state.editor.codemirror && typeof state.editor.codemirror.toTextArea === 'function') {
            try { state.editor.codemirror.toTextArea(); } catch (e) { /* ignore disposal errors */ }
        }
        state.editor = null;
        state.fallbackTextarea = null;
        $host.empty();
    }

    function mimeFor(path) {
        const ext = String(path).split('.').pop().toLowerCase();
        const map = { php: 'application/x-httpd-php', phtml: 'application/x-httpd-php', css: 'text/css', scss: 'text/x-scss', sass: 'text/x-sass', js: 'text/javascript', mjs: 'text/javascript', cjs: 'text/javascript', ts: 'text/typescript', tsx: 'text/typescript', jsx: 'text/jsx', html: 'text/html', htm: 'text/html', json: 'application/json', xml: 'application/xml', svg: 'image/svg+xml', yml: 'text/yaml', yaml: 'text/yaml', sql: 'text/x-sql', graphql: 'text/x-sql', gql: 'text/x-sql', twig: 'text/html', vue: 'text/html', sh: 'text/x-sh' };
        return map[ext] || 'text/plain';
    }

    function languageLabel(path) {
        const ext = String(path).split('.').pop().toLowerCase();
        const labels = { php:'PHP', phtml:'PHP', css:'CSS', scss:'SCSS', sass:'Sass', js:'JavaScript', mjs:'JavaScript', cjs:'JavaScript', ts:'TypeScript', tsx:'TSX', jsx:'JSX', html:'HTML', htm:'HTML', json:'JSON', md:'Markdown', txt:'Plain Text', xml:'XML', svg:'SVG', yml:'YAML', yaml:'YAML', sql:'SQL', graphql:'GraphQL', gql:'GraphQL', twig:'Twig', vue:'Vue', sh:'Shell', conf:'Config', ini:'INI', dist:'Text', sample:'Text' };
        return labels[ext] || 'Text';
    }

    function setDocStatus(doc) {
        if (doc && !doc.writable) {
            setStatus((cfg.strings && cfg.strings.readOnly) || 'Read only', 'is-readonly');
            return;
        }
        setStatus(doc && doc.dirty ? 'Unsaved changes' : 'Saved', doc && doc.dirty ? 'is-dirty' : '');
    }

    function updateCursor() {
        if (!state.editor || !state.editor.codemirror) return;
        const cur = state.editor.codemirror.getCursor();
        $('#cc-studio-cursor').text('Ln ' + (cur.line + 1) + ', Col ' + (cur.ch + 1));
    }

    function renderInspector() {
        const doc = state.openFiles[state.activePath];
        const path = state.activePath;
        if (!doc || !path) {
            $('#cc-studio-inspector-content').html('<div class="cc-studio-inspector-empty"><span class="dashicons dashicons-editor-code"></span><strong>Source inspector</strong><p>Select a file to see details and revisions.</p><a class="button" href="' + esc(cfg.siteUrl || '/') + '" target="_blank" rel="noopener noreferrer">Preview website</a></div>');
            return;
        }
        $('#cc-studio-inspector-content').html('<div class="cc-studio-file-details"><span class="cc-studio-detail-label">FILE</span><strong class="cc-studio-detail-name">' + esc(pathBase(path)) + '</strong><code>' + esc((state.root === 'themes' ? 'wp-content/themes/' : 'wp-content/plugins/') + path) + '</code><dl><dt>Language</dt><dd>' + esc(languageLabel(path)) + '</dd><dt>Size</dt><dd>' + esc(formatBytes(new Blob([doc.content]).size)) + '</dd><dt>Status</dt><dd>' + (doc.dirty ? '<span class="cc-studio-dirty-text">Unsaved changes</span>' : '<span class="cc-studio-clean-text">Saved</span>') + '</dd><dt>SHA-256</dt><dd><code class="cc-studio-hash">' + esc(doc.sha256 || 'Not saved') + '</code></dd></dl><div class="cc-studio-inspector-buttons"><button type="button" class="button" id="cc-studio-inspector-history">View History</button><a class="button" href="' + esc(cfg.siteUrl || '/') + '" target="_blank" rel="noopener noreferrer">Preview site ↗</a></div><p class="description">Saving changes writes them to the file on the server. PHP files can affect site availability. Keep a working backup and use staging for risky changes.</p></div>');
        $('#cc-studio-inspector-history').on('click', showHistory);
    }

    function renderTabs() {
        if (!state.tabs.length) {
            $tabs.html('<div class="cc-studio-empty-tab">No file open</div>');
            return;
        }
        const html = state.tabs.map((path) => {
            const doc = state.openFiles[path];
            if (!doc) return '';
            const active = state.activePath === path ? ' is-active' : '';
            return '<button type="button" class="cc-studio-tab' + active + '" data-path="' + esc(path) + '"><span class="cc-studio-ext-icon ' + esc(fileIcon({type:'file', ext:path.split('.').pop()}).match(/class="([^"]+)/)?.[1] || '') + '">' + esc((path.split('.').pop() || '').toUpperCase().slice(0, 4)) + '</span><span class="cc-studio-tab-name">' + esc(pathBase(path)) + '</span>' + (doc.dirty ? '<span class="cc-studio-tab-dirty" title="Unsaved changes"></span>' : '') + '<span class="cc-studio-tab-close" data-close="' + esc(path) + '" aria-label="Close tab">×</span></button>';
        }).join('');
        $tabs.html(html);
        $tabs.find('.cc-studio-tab').on('click', function (event) {
            const closePath = $(event.target).data('close');
            if (closePath) { event.stopPropagation(); closeTab(closePath); return; }
            activateTab($(this).data('path'));
        });
    }

    function closeTab(path) {
        const doc = state.openFiles[path];
        if (doc && doc.dirty && !window.confirm((cfg.strings && cfg.strings.confirmDiscard) || 'Discard unsaved changes?')) return;
        state.tabs = state.tabs.filter((item) => item !== path);
        delete state.openFiles[path];
        if (state.activePath === path) {
            state.activePath = '';
            disposeEditor();
            if (state.tabs.length) activateTab(state.tabs[state.tabs.length - 1]);
            else { updateEmptyState(); renderTabs(); updateSaveButton(); renderInspector(); }
        } else { renderTabs(); }
    }

    function updateEmptyState() {
        $host.html('<div class="cc-studio-welcome"><div class="cc-studio-welcome-mark">CC</div><h1>CodeCanvas Studio</h1><p>A real-file development workspace inside WordPress.</p><div class="cc-studio-shortcuts"><span><kbd>Ctrl</kbd> + <kbd>S</kbd> <small>Save file</small></span><span><kbd>Ctrl</kbd> + <kbd>P</kbd> <small>Quick open</small></span><span><kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>F</kbd> <small>Search files</small></span></div><p class="cc-studio-welcome-note">Select a source file from Explorer to begin.</p></div>');
        $('#cc-studio-current-file').text('Select a source file from Explorer');
        $('#cc-studio-language').text('No file');
        $('#cc-studio-cursor').text('Ln 1, Col 1');
        $('#cc-studio-rename, #cc-studio-delete').prop('disabled', true);
        setStatus('Ready');
    }

    function activateTab(path) {
        if (!state.openFiles[path]) return;
        if (state.activePath && state.activePath !== path) syncActiveBuffer();
        state.activePath = path;
        const doc = state.openFiles[path];
        disposeEditor();
        const $area = $('<div class="cc-studio-codemirror-wrap"><textarea id="cc-studio-source-editor" aria-label="Source code editor" spellcheck="false"></textarea></div>');
        $host.empty().append($area);
        const textarea = $id('cc-studio-source-editor');
        textarea.value = doc.content;
        const mime = mimeFor(path);
        const settings = cfg.editorSettings && cfg.editorSettings[mime] ? JSON.parse(JSON.stringify(cfg.editorSettings[mime])) : null;
        if (settings && window.wp && wp.codeEditor && typeof wp.codeEditor.initialize === 'function') {
            try {
                if (settings.codemirror) {
                    settings.codemirror.lineNumbers = true;
                    settings.codemirror.lineWrapping = wrapEnabled;
                    settings.codemirror.scrollPastEnd = true;
                    settings.codemirror.indentUnit = 4;
                    settings.codemirror.tabSize = 4;
                    settings.codemirror.styleActiveLine = true;
                    settings.codemirror.matchBrackets = true;
                    settings.codemirror.autoCloseBrackets = true;
                    settings.codemirror.theme = 'codecanvas-dark';
                }
                state.editor = wp.codeEditor.initialize(textarea, settings);
                if (state.editor && state.editor.codemirror) {
                    state.editor.codemirror.setSize('100%', '100%');
                    state.editor.codemirror.setOption('lineWrapping', wrapEnabled);
                    state.editor.codemirror.on('change', () => { syncActiveBuffer(); setDocStatus(doc); });
                    state.editor.codemirror.on('cursorActivity', updateCursor);
                    state.editor.codemirror.setValue(doc.content);
                    state.editor.codemirror.setOption('readOnly', !doc.writable);
                    state.editor.codemirror.refresh();
                    window.setTimeout(() => state.editor && state.editor.codemirror && state.editor.codemirror.refresh(), 60);
                }            } catch (e) {
                console.error('[CodeCanvas Studio] CodeMirror could not start; using the plain text editor.', e);
                state.editor = null;
            }
        }
        if (!state.editor || !state.editor.codemirror) {
            state.editor = null;
            textarea.className = 'cc-studio-fallback-textarea';
            textarea.readOnly = !doc.writable;
            textarea.addEventListener('input', () => { syncActiveBuffer(); setDocStatus(doc); });
            state.fallbackTextarea = textarea;
        }
        $('#cc-studio-current-file').text((state.root === 'themes' ? 'Themes / ' : 'Plugins / ') + path);
        $('#cc-studio-language').text(languageLabel(path));
        $('#cc-studio-rename, #cc-studio-delete').prop('disabled', !doc.writable);
        $fileList.find('.cc-studio-tree-item').removeClass('is-active');
        $fileList.find('.cc-studio-tree-item[data-path="' + path.replace(/"/g, '\\"') + '"]').addClass('is-active');
        renderTabs(); renderInspector(); updateSaveButton(); updateCursor();
        setDocStatus(doc);
        // On phones the explorer is a drawer; picking a file returns to the code.
        $('.cc-studio-wrap').removeClass('cc-mobile-explorer-open');
        $('.cc-studio-activity [data-cc-activity="explorer"]').attr('aria-expanded', 'false');
    }

    function openFile(path) {
        path = String(path || '').replace(/^\/+/, '');
        if (state.openFiles[path]) { activateTab(path); return; }
        setStatus('Opening file…');
        ajax('read', { path: path }).then((data) => {
            state.openFiles[path] = { path, content: data.content, cleanContent: data.content, dirty: false, sha256: data.sha256, mtime: data.mtime, writable: !!data.writable, backups: data.backups || [] };
            state.tabs.push(path); activateTab(path);
            notify(data.writable ? ('Opened ' + path) : ('Opened read-only: ' + path), data.writable ? 'success' : '');
        }).catch((error) => { setStatus('Open failed', 'is-error'); notify(error.message, 'error'); });
    }

    function updateSaveButton() {
        const doc = state.openFiles[state.activePath];
        const dirty = !!(doc && doc.dirty);
        $save.prop('disabled', !doc || !dirty || state.busy || !doc.writable);
        $('#cc-studio-current-file').toggleClass('is-dirty', dirty);
    }

    function saveCurrent() {
        const path = state.activePath;
        const doc = state.openFiles[path];
        if (!path || !doc || !doc.dirty || state.busy) return $.Deferred().resolve().promise();
        syncActiveBuffer();
        if (!doc.dirty) return $.Deferred().resolve().promise();
        state.busy = true; updateSaveButton(); setStatus('Saving…');
        return ajax('save', { path, content: doc.content, expected_sha256: doc.sha256 || '' }).then((data) => {
            doc.cleanContent = doc.content; doc.dirty = false; doc.sha256 = data.sha256; doc.mtime = data.mtime;
            setStatus('Saved', 'is-success'); notify(path + ' saved.', 'success'); renderTabs(); renderInspector(); refreshList();
        }).catch((error) => {
            setStatus(error.data && error.data.conflict ? 'File changed on server' : 'Save failed', 'is-error');
            notify(error.message, 'error');
            if (error.data && error.data.conflict && window.confirm('This file changed outside CodeCanvas. Reload the server copy and discard your unsaved changes?')) openFileReplacing(path);
        }).always(() => { state.busy = false; updateSaveButton(); });
    }

    function openFileReplacing(path) {
        ajax('read', { path }).then((data) => {
            if (!state.openFiles[path]) return;
            state.openFiles[path].content = data.content; state.openFiles[path].cleanContent = data.content; state.openFiles[path].dirty = false; state.openFiles[path].sha256 = data.sha256; state.openFiles[path].mtime = data.mtime; state.openFiles[path].writable = !!data.writable;
            activateTab(path); notify('Reloaded server copy.', 'success');
        }).catch((error) => notify(error.message, 'error'));
    }

    function createFile() {
        const name = window.prompt('New file name in the current folder (for example, custom.css):');
        if (!name) return;
        ajax('create', { path: state.path, name: name.trim(), kind: 'file' }).then((data) => {
            notify(data.message, 'success'); refreshList().then(() => openFile(data.path));
        }).catch((error) => notify(error.message, 'error'));
    }

    function createFolder() {
        const name = window.prompt('New folder name:');
        if (!name) return;
        ajax('create', { path: state.path, name: name.trim(), kind: 'folder' }).then((data) => { notify(data.message, 'success'); refreshList(); }).catch((error) => notify(error.message, 'error'));
    }

    function renameCurrent() {
        if (!state.activePath) return;
        const oldPath = state.activePath;
        const name = window.prompt('Rename file to:', pathBase(oldPath));
        if (!name || name === pathBase(oldPath)) return;
        ajax('rename', { path: oldPath, name: name.trim() }).then((data) => {
            const newPath = data.path;
            const doc = state.openFiles[oldPath];
            if (doc) { delete state.openFiles[oldPath]; doc.path = newPath; state.openFiles[newPath] = doc; }
            state.tabs = state.tabs.map((path) => path === oldPath ? newPath : path);
            state.activePath = newPath;
            notify(data.message, 'success'); refreshList(); activateTab(newPath);
        }).catch((error) => notify(error.message, 'error'));
    }

    function deleteCurrent() {
        if (!state.activePath || !window.confirm((cfg.strings && cfg.strings.confirmDelete) || 'Delete this file?')) return;
        const path = state.activePath;
        ajax('delete', { path }).then((data) => {
            state.tabs = state.tabs.filter((item) => item !== path); delete state.openFiles[path]; state.activePath = '';
            disposeEditor(); updateEmptyState(); renderTabs(); updateSaveButton(); renderInspector(); refreshList(); notify(data.message, 'success');
        }).catch((error) => notify(error.message, 'error'));
    }

    function showHistory() {
        if (!state.activePath) { notify('Open a file first to view its history.', 'error'); return; }
        switchPanel('history');
        $('#cc-studio-history-list').html('<p class="cc-studio-loading">Loading history…</p>');
        ajax('history', { path: state.activePath }).then((data) => {
            const list = data.history || [];
            if (!list.length) { $('#cc-studio-history-list').html('<p class="description">No snapshots have been recorded yet. CodeCanvas creates a snapshot before each save, rename, delete or restore.</p>'); return; }
            $('#cc-studio-history-list').html(list.map((item) => '<div class="cc-studio-history-item"><strong>' + esc(new Date(item.time * 1000).toLocaleString()) + '</strong><span>' + esc(item.reason) + ' · ' + esc((item.sha256 || '').slice(0, 12)) + '</span><button type="button" class="button button-small" data-restore-index="' + item.index + '">Restore this version</button></div>').join(''));
            $('#cc-studio-history-list [data-restore-index]').on('click', function () {
                if (!window.confirm('Restore this snapshot? Current contents will be backed up first.')) return;
                const index = Number($(this).data('restore-index'));
                ajax('restore', { path: state.activePath, index }).then((result) => {
                    const doc = state.openFiles[state.activePath];
                    if (doc) { doc.content = result.content; doc.cleanContent = result.content; doc.sha256 = result.sha256; doc.dirty = false; activateTab(state.activePath); }
                    notify(result.message, 'success'); showHistory();
                }).catch((error) => notify(error.message, 'error'));
            });
        }).catch((error) => { $('#cc-studio-history-list').html('<p class="cc-studio-error-text">' + esc(error.message) + '</p>'); notify(error.message, 'error'); });
    }

    function switchPanel(panel) {
        state.activePanel = panel;
        $('#cc-studio-explorer, #cc-studio-search-panel, #cc-studio-history-panel').prop('hidden', true);
        if (panel === 'explorer') $('#cc-studio-explorer').prop('hidden', false);
        if (panel === 'search') $('#cc-studio-search-panel').prop('hidden', false);
        if (panel === 'history') $('#cc-studio-history-panel').prop('hidden', false);
        $('.cc-studio-activity button').removeClass('is-active').filter('[data-cc-activity="' + panel + '"]').addClass('is-active');
    }

    function runSearch() {
        const query = $('#cc-studio-search-query').val().trim();
        const $results = $('#cc-studio-search-results');
        if (query.length < 2) { notify('Enter at least 2 characters to search.', 'error'); return; }
        $results.html('<p class="cc-studio-loading">Searching source files…</p>');
        ajax('search', { path: state.path, query }).then((data) => {
            const results = data.results || [];
            if (!results.length) { $results.html('<p class="description">No matches found in ' + esc(data.files_scanned || 0) + ' files.</p>'); return; }
            $results.html('<p class="description">' + esc(results.length) + ' matches in up to ' + esc(data.files_scanned) + ' files' + (data.truncated ? ' (limit reached)' : '') + '.</p>' + results.map((result) => '<button type="button" class="cc-studio-search-result" data-path="' + esc(result.path) + '" data-line="' + Number(result.line) + '"><strong>' + esc(result.path) + ':' + Number(result.line) + '</strong><span>' + esc(result.snippet) + '</span></button>').join(''));
            $results.find('.cc-studio-search-result').on('click', function () {
                const path = $(this).data('path'); const line = Number($(this).data('line')) - 1;
                openFile(path);
                const wait = window.setInterval(() => { if (state.activePath === path && state.editor && state.editor.codemirror) { window.clearInterval(wait); state.editor.codemirror.setCursor({ line, ch: 0 }); state.editor.codemirror.focus(); state.editor.codemirror.scrollIntoView({ line, ch: 0 }, 120); } }, 80);
                window.setTimeout(() => window.clearInterval(wait), 5000);
            });
        }).catch((error) => $results.html('<p class="cc-studio-error-text">' + esc(error.message) + '</p>'));
    }

    function setupSiteChildTheme() {
        if (cfg.childThemeActive) return;
        const action = cfg.childThemeExists ? 'activate the existing CodeCanvas Site theme' : 'create and activate the CodeCanvas Site theme';
        if (!window.confirm('This will ' + action + '. Website templates and assets will live in codecanvas-site/, while CodeCanvas framework files become read-only in this workspace. Your existing files in codecanvas-site will not be overwritten. Continue?')) return;
        const $buttons = $('#cc-studio-setup-child, #cc-studio-site-child');
        $buttons.prop('disabled', true);
        notify(cfg.childThemeExists ? 'Activating website theme…' : 'Creating website theme…');
        ajax('setup_site_child_theme', { root: 'themes' }).then((data) => {
            notify(data.message || 'Child theme activated.', 'success');
            window.location.href = data.redirect || window.location.href;
        }).catch((error) => {
            notify(error.message, 'error');
            $buttons.prop('disabled', false);
        });
    }

    function syncWorkspaceGate() {
        const needsThemeSetup = state.root === 'themes' && !cfg.childThemeActive;
        $('#cc-studio-child-setup').prop('hidden', !needsThemeSetup);
        $('#cc-studio-workbench').prop('hidden', needsThemeSetup);
        $('#cc-studio-new-file, #cc-studio-new-folder').prop('disabled', needsThemeSetup || !(cfg.editableRoots && cfg.editableRoots[state.root]));
        if (needsThemeSetup) {
            setStatus('Set up website theme', 'is-dirty');
        }
        return needsThemeSetup;
    }

    function scaffold() {
        const kind = window.prompt('Create a starter: enter "plugin" or "theme". This writes new source files but does not activate them.');
        if (!kind) return;
        const normalized = kind.trim().toLowerCase();
        if (!['plugin', 'theme'].includes(normalized)) { notify('Enter plugin or theme.', 'error'); return; }
        const slug = window.prompt((cfg.strings && cfg.strings.slugPrompt) || 'Enter a folder slug:');
        if (!slug) return;
        const title = window.prompt((cfg.strings && cfg.strings.titlePrompt) || 'Enter a display name:', slug.split('-').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' '));
        if (!title) return;
        const rootNeeded = normalized === 'theme' ? 'themes' : 'plugins';
        if (state.root !== rootNeeded) {
            if (!window.confirm('Switch the current source root to ' + rootNeeded + ' and create the starter there?')) return;
            state.root = rootNeeded; $root.val(rootNeeded); state.path = '';
        }
        ajax('scaffold', { kind: normalized, slug, title }).then((data) => {
            state.path = data.path; refreshList(); notify(data.message, 'success');
        }).catch((error) => notify(error.message, 'error'));
    }

    function quickOpen() {
        const path = window.prompt('Open source file by path relative to the selected root (for example, my-theme/functions.php):');
        if (path) openFile(path.trim());
    }

    function init() {
        $('#cc-studio-refresh').on('click', refreshList);
        $('#cc-studio-new-file').on('click', createFile);
        $('#cc-studio-empty-new-file').on('click', createFile);
        $('#cc-studio-new-folder').on('click', createFolder);
        $('#cc-studio-save').on('click', saveCurrent);
        $('#cc-studio-rename').on('click', renameCurrent);
        $('#cc-studio-delete').on('click', deleteCurrent);
        $('#cc-studio-history').on('click', showHistory);
        $('#cc-studio-scaffold').on('click', scaffold);
        $('#cc-studio-site-child, #cc-studio-setup-child').on('click', setupSiteChildTheme);
        $('#cc-studio-search-run').on('click', runSearch);
        $('#cc-studio-search-query').on('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); runSearch(); } });
        const $focus = $('#cc-studio-focus');
        const focusOn = () => $('html').hasClass('cc-focus-mode');
        function setFocusMode(on) {
            $('html, body').toggleClass('cc-focus-mode', !!on);
            if ($focus.length) {
                $focus.toggleClass('is-active', !!on).attr('aria-pressed', on ? 'true' : 'false').attr('aria-label', on ? 'Exit focus mode' : 'Focus mode');
                $focus.find('.cc-focus-label').text(on ? 'Exit focus' : 'Focus');
            }
            window.setTimeout(() => { if (state.editor && state.editor.codemirror) { state.editor.codemirror.refresh(); } }, 90);
        }
        $focus.on('click', function () { setDrawer(false); setFocusMode(!focusOn()); });

        try { wrapEnabled = window.localStorage.getItem('ccStudioWrap') !== '0'; } catch (e) { /* storage may be blocked */ }
        function applyWrap(on) {
            wrapEnabled = !!on;
            $('#cc-studio-wrap').attr('aria-pressed', wrapEnabled ? 'true' : 'false').toggleClass('is-active', wrapEnabled);
            $host.toggleClass('cc-nowrap', !wrapEnabled);
            if (state.editor && state.editor.codemirror) {
                state.editor.codemirror.setOption('lineWrapping', wrapEnabled);
                state.editor.codemirror.refresh();
            }
            try { window.localStorage.setItem('ccStudioWrap', wrapEnabled ? '1' : '0'); } catch (e) { /* storage may be blocked */ }
        }
        $('#cc-studio-wrap').on('click', function () { applyWrap(!wrapEnabled); });

        const $notice = $('#cc-studio-notice');
        try { if (window.localStorage.getItem('ccStudioNotice') === (cfg.version || '1')) { $notice.prop('hidden', true); } } catch (e) { /* storage may be blocked */ }
        $('#cc-studio-notice-close').on('click', function () {
            $notice.prop('hidden', true);
            try { window.localStorage.setItem('ccStudioNotice', cfg.version || '1'); } catch (e) { /* storage may be blocked */ }
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                if ($wrap.hasClass('cc-mobile-explorer-open')) { closeDrawer(); return; }
                if (focusOn()) { setFocusMode(false); }
                return;
            }
            if ((event.ctrlKey || event.metaKey) && event.shiftKey && String(event.key || '').toLowerCase() === 'm') { event.preventDefault(); setFocusMode(!focusOn()); return; }
            if (event.altKey && !event.ctrlKey && !event.metaKey && String(event.key || '').toLowerCase() === 'z') { event.preventDefault(); applyWrap(!wrapEnabled); }
        });
        let resizeTimer = null;
        $(window).on('resize', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(() => { if (state.editor && state.editor.codemirror) { state.editor.codemirror.refresh(); } }, 120);
        });
        applyWrap(wrapEnabled);

        const mobileQuery = window.matchMedia('(max-width: 782px)');
        const isMobile = () => mobileQuery.matches;
        function setDrawer(open) {
            if (open && isMobile()) {
                $wrap.addClass('cc-mobile-explorer-open');
                $('.cc-studio-activity [data-cc-activity="explorer"]').attr('aria-expanded', 'true');
                return;
            }
            $wrap.removeClass('cc-mobile-explorer-open');
            $('.cc-studio-activity [data-cc-activity="explorer"]').attr('aria-expanded', 'false');
        }
        const closeDrawer = () => { setDrawer(false); };
        $('#cc-studio-drawer-backdrop').on('click', closeDrawer);
        $('#cc-studio-drawer-close').on('click', closeDrawer);
        $wrap.on('click', '.cc-studio-tree-file, .cc-studio-search-result, .cc-studio-tab', function () { if (isMobile()) { closeDrawer(); } });
        const closeDrawerOnDesktop = () => { if (!isMobile()) { closeDrawer(); } };
        if (mobileQuery.addEventListener) { mobileQuery.addEventListener('change', closeDrawerOnDesktop); } else if (mobileQuery.addListener) { mobileQuery.addListener(closeDrawerOnDesktop); }

        $('.cc-studio-activity [data-cc-activity]').on('click', function () {
            const panel = $(this).data('cc-activity');
            const wasOpen = $wrap.hasClass('cc-mobile-explorer-open');
            const samePanel = panel === state.activePanel;
            switchPanel(panel);
            if (panel === 'history') { showHistory(); }
            if (panel === 'search') { $('#cc-studio-search-query').trigger('focus'); }
            if (isMobile()) { if (wasOpen && samePanel) { closeDrawer(); } else { setDrawer(true); } }
        });
        $('#cc-studio-breadcrumbs').on('click', '.cc-studio-crumb', function () {
            state.path = $(this).data('path') || ''; refreshList();
        });
        $root.on('change', function () {
            const selectedRoot = $(this).val();
            if (cfg.roots && cfg.roots[selectedRoot] === false) {
                notify(selectedRoot === 'plugins' ? 'Plugin editing is not allowed by this site’s WordPress role or server policy.' : 'Theme editing is not allowed by this site’s WordPress role or server policy.', 'error');
                $root.val(state.root);
                return;
            }
            if (hasUnsavedFiles() && !window.confirm('Changing source roots closes all open tabs. Unsaved changes will be discarded. Continue?')) { $root.val(state.root); return; }
            state.root = selectedRoot; state.path = state.root === 'themes' ? (cfg.defaultPath || '') : '';
            state.tabs = []; state.openFiles = {}; state.activePath = ''; disposeEditor(); updateEmptyState(); renderTabs(); updateSaveButton();
            if (syncWorkspaceGate()) return;
            refreshList();
        });
        $(document).on('keydown', function (event) {
            const mod = event.ctrlKey || event.metaKey;
            if (!mod) return;
            const key = String(event.key || '').toLowerCase();
            if (key === 's') { event.preventDefault(); saveCurrent(); }
            else if (key === 'p') { event.preventDefault(); quickOpen(); }
            else if (event.shiftKey && key === 'f') { event.preventDefault(); switchPanel('search'); $('#cc-studio-search-query').trigger('focus'); }
        });
        window.addEventListener('beforeunload', function (event) {
            const dirty = Object.keys(state.openFiles).some((path) => state.openFiles[path].dirty);
            if (dirty) { event.preventDefault(); event.returnValue = ''; }
        });
        if (!syncWorkspaceGate()) refreshList();
    }

    $(function () {
        try {
            init();
        } catch (error) {
            console.error('[CodeCanvas Studio] Initialisation failed', error);
            setStatus('Studio failed to start', 'is-error');
            notify('CodeCanvas Studio could not start: ' + (error && error.message ? error.message : error), 'error');
        }
    });
})(jQuery);
