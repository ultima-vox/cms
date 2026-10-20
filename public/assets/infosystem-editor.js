/* Ultima Vox Infosystem editor: no network dependencies; content never executes scripts. */
(() => {
    'use strict';

    const panels = document.querySelectorAll('[data-tab-panel]');
    const nav = document.querySelector('[data-is-tabs]');
    if (nav && panels.length) {
        const tabs = [...nav.querySelectorAll('[data-tab]')];
        const activate = (name, focus = false) => {
            for (const tab of tabs) {
                const active = tab.dataset.tab === name;
                tab.setAttribute('role', 'tab');
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
                if (active && focus) tab.focus();
            }
            for (const panel of panels) {
                panel.setAttribute('role', 'tabpanel');
                panel.hidden = panel.dataset.tabPanel !== name;
            }
        };
        nav.setAttribute('role', 'tablist');
        const invalidField = document.querySelector('[aria-invalid="true"]');
        const requested = invalidField?.closest('[data-tab-panel]')?.dataset.tabPanel || location.hash.replace(/^#/, '');
        activate(tabs.some(tab => tab.dataset.tab === requested) ? requested : 'main');
        if (invalidField) invalidField.focus();
        nav.addEventListener('click', event => {
            const tab = event.target.closest('[data-tab]');
            if (tab && nav.contains(tab)) activate(tab.dataset.tab);
        });
        nav.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            const current = tabs.indexOf(document.activeElement);
            if (current < 0) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
                : (current + (event.key === 'ArrowLeft' ? -1 : 1) + tabs.length) % tabs.length;
            activate(tabs[next].dataset.tab, true);
        });
    }

    const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[char]);
    const make = (tag, cls = '', txt = '') => {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (txt) node.textContent = txt;
        return node;
    };
    const validMediaUrl = value => {
        try {
            const url = new URL(value, location.origin);
            return ['http:', 'https:'].includes(url.protocol)
                && (url.origin === location.origin || url.protocol === 'https:')
                && value.trim().length <= 2048;
        } catch { return false; }
    };
    const stylesheetList = textarea => {
        try {
            const value = JSON.parse(textarea.dataset.editorStyleList || '[]');
            return Array.isArray(value) ? value.filter(
                x => typeof x === 'string' && /^\/assets\/[a-zA-Z0-9_/.-]+\.css$/.test(x)
                    && !x.includes('..'),
            ).slice(0, 8) : [];
        } catch { return []; }
    };
    const frameHtml = (content, styles, editable) => {
        const links = styles.map(href => '<link rel="stylesheet" href="' + escapeHtml(href) + '">').join('');
        return '<!doctype html><html lang="ru"><head><meta charset="utf-8">'
            + '<meta http-equiv="Content-Security-Policy" content="default-src &apos;none&apos;; style-src &apos;self&apos; &apos;unsafe-inline&apos;; img-src &apos;self&apos; https: data:; font-src &apos;self&apos; data:">'
            + links + '<style>html{background:#fff;}body{box-sizing:border-box;min-height:100%;margin:0;padding:26px;font-family:inherit;overflow-wrap:anywhere;}body:focus{outline:0}img{max-width:100%;height:auto}</style></head>'
            + '<body' + (editable ? ' contenteditable="true" spellcheck="true"' : '') + '>' + content + '</body></html>';
    };
    const highlight = (source, language) => {
        // Conservative lexical highlighting; all user-supplied tokens are escaped first.
        // This is a viewer, not a JS/PHP interpreter.
        const re = language === 'html'
            ? /(<\/?[\w:-]+|\/?>|\b[\w:-]+(?==)|"[^"\n]*"|'[^'\n]*'|<!--[\s\S]*?-->)/g
            : language === 'css'
                ? /(\/\*[\s\S]*?\*\/|#[\da-fA-F]{3,8}\b|@[a-z-]+|\.[\w-]+|\b[\w-]+(?=\s*:)|"[^"\n]*"|'[^'\n]*')/g
                : /(\/\/[^\n]*|\/\*[\s\S]*?\*\/|#[^\n]*|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\b(?:function|return|const|let|var|class|public|private|protected|if|else|foreach|new|echo|use|namespace|readonly|async|await|true|false|null|try|catch|throw|final|interface)\b|<\?php|\?>)/g;
        let result = '', last = 0;
        for (const match of source.matchAll(re)) {
            const index = match.index;
            result += escapeHtml(source.slice(last, index));
            const token = match[0];
            const cls = /^(?:\/\/|\/\*|<!--|#(?![\da-fA-F]{3,8}\b))/.test(token) ? 'comment'
                : /^["']/.test(token) ? 'string'
                    : /^(?:<|\/?>)/.test(token) ? 'tag'
                        : 'keyword';
            result += '<span class="uv-hl-' + cls + '">' + escapeHtml(token) + '</span>';
            last = index + token.length;
        }
        return result + escapeHtml(source.slice(last)) + '\n';
    };

    for (const textarea of document.querySelectorAll('textarea[data-rich-editor]')) {
        const name = textarea.getAttribute('data-rich-editor');
        const styles = stylesheetList(textarea);
        const host = make('div', 'uv-rich');
        const toolbar = make('div', 'uv-rich__toolbar');
        const views = make('div', 'uv-rich__views');
        const source = make('div', 'uv-rich__source');
        const preview = make('iframe', 'uv-rich__frame');
        const visual = make('iframe', 'uv-rich__frame');
        const overlay = make('pre', 'uv-rich__highlight');
        const language = make('select', 'uv-rich__language');
        const notes = make('p', 'admin-field__hint', 'Стили текущего макета применяются в изолированном предпросмотре. PHP и JavaScript только подсвечиваются и не исполняются.');
        const sourceArea = make('div', 'uv-rich__source-area');
        const initial = textarea.value;
        let mode = 'source';
        let dirtyVisual = false;
        let loaded = false;
        let startValue = initial;

        for (const [value, label] of [['html', 'HTML'], ['css', 'CSS'], ['javascript', 'JavaScript'], ['php', 'PHP']]) {
            const option = make('option', '', label);
            option.value = value;
            language.append(option);
        }
        language.setAttribute('aria-label', 'Язык подсветки');
        language.value = 'html';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = highlight(textarea.value, language.value);
        textarea.classList.add('uv-rich__textarea');
        textarea.id ||= 'uv-rich-' + name;
        const target = textarea.parentNode;
        sourceArea.append(overlay);
        sourceArea.append(textarea);
        source.append(language, sourceArea);
        visual.setAttribute('title', 'Визуальное редактирование ' + name);
        visual.setAttribute('sandbox', 'allow-same-origin');
        preview.setAttribute('title', 'Предпросмотр ' + name);
        preview.setAttribute('sandbox', 'allow-same-origin');
        views.append(source, visual, preview);
        host.append(toolbar, views, notes);
        target.append(host);

        const syncOverlay = () => { overlay.innerHTML = highlight(textarea.value, language.value); };
        textarea.addEventListener('input', syncOverlay);
        textarea.addEventListener('scroll', () => {
            overlay.scrollTop = textarea.scrollTop;
            overlay.scrollLeft = textarea.scrollLeft;
        });
        language.addEventListener('change', syncOverlay);

        const setFrame = (frame, html, edit) => {
            frame.srcdoc = frameHtml(html, styles, edit);
            if (edit) {
                loaded = false;
                frame.addEventListener('load', () => {
                    const doc = frame.contentDocument;
                    if (!doc) return;
                    loaded = true;
                    doc.addEventListener('input', () => { dirtyVisual = true; });
                    doc.addEventListener('paste', event => {
                        // Paste plain text rather than executing clipboard-provided HTML.
                        const text = event.clipboardData?.getData('text/plain');
                        if (typeof text === 'string') {
                            event.preventDefault();
                            doc.execCommand('insertText', false, text);
                        }
                    });
                }, { once: true });
            }
        };
        const saveVisual = () => {
            if (mode !== 'visual' || !loaded || !dirtyVisual) return;
            const body = visual.contentDocument?.body;
            if (!body) return;
            textarea.value = body.innerHTML;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            dirtyVisual = false;
        };
        const showMode = next => {
            if (mode === next) return;
            saveVisual();
            mode = next;
            source.hidden = mode !== 'source';
            visual.hidden = mode !== 'visual';
            preview.hidden = mode !== 'preview';
            language.hidden = mode !== 'source';
            if (mode === 'visual') {
                setFrame(visual, textarea.value, true);
            } else if (mode === 'preview') {
                setFrame(preview, textarea.value, false);
            } else {
                syncOverlay();
            }
            for (const button of toolbar.querySelectorAll('[data-editor-mode]')) {
                const pressed = button.dataset.editorMode === mode;
                button.setAttribute('aria-pressed', String(pressed));
                button.classList.toggle('is-current', pressed);
            }
        };
        const modeButtons = [['visual', 'Визуально'], ['source', 'Код'], ['preview', 'Просмотр']];
        for (const [value, title] of modeButtons) {
            const button = make('button', 'uv-rich__mode', title);
            button.type = 'button';
            button.dataset.editorMode = value;
            button.addEventListener('click', () => showMode(value));
            toolbar.append(button);
        }

        const formatting = make('div', 'uv-rich__format');
        const command = (cmd, value = null) => {
            if (mode !== 'visual' || !loaded) showMode('visual');
            const doc = visual.contentDocument;
            if (!doc) return;
            visual.contentWindow?.focus();
            doc.body?.focus();
            doc.execCommand(cmd, false, value);
            dirtyVisual = true;
        };
        for (const [label, cmd, value] of [
            ['B', 'bold', null], ['I', 'italic', null], ['U', 'underline', null],
            ['H2', 'formatBlock', 'h2'], ['H3', 'formatBlock', 'h3'],
            ['¶', 'formatBlock', 'p'], ['• Список', 'insertUnorderedList', null],
            ['1. Список', 'insertOrderedList', null], ['Цитата', 'formatBlock', 'blockquote'],
            ['↶', 'undo', null], ['↷', 'redo', null],
        ]) {
            const button = make('button', 'uv-rich__tool', label);
            button.type = 'button';
            button.title = cmd;
            button.addEventListener('click', () => command(cmd, value));
            formatting.append(button);
        }

        const extra = (label, title, handler) => {
            const button = make('button', 'uv-rich__tool', label);
            button.type = 'button';
            button.title = title;
            button.addEventListener('click', handler);
            formatting.append(button);
        };
        extra('Ссылка', 'Вставить ссылку', () => {
            if (mode !== 'visual') { showMode('visual'); return; }
            const href = prompt('Адрес ссылки (https://, mailto: или /):', 'https://');
            if (!href) return;
            const safe = href.startsWith('/') && !href.startsWith('//') && !href.includes('..')
                || validMediaUrl(href) || /^mailto:[^\s@]+@[^\s@]+$/.test(href);
            if (!safe) { alert('Недопустимый адрес ссылки'); return; }
            command('createLink', href);
        });
        extra('Фото', 'Вставить изображение', () => {
            if (mode !== 'visual') { showMode('visual'); return; }
            const url = prompt('URL изображения (из медиабиблиотеки или HTTPS):', '/assets/');
            if (!url) return;
            if (!validMediaUrl(url)) { alert('Недопустимый URL изображения'); return; }
            const alt = prompt('Альтернативный текст изображения:', '') || '';
            // Insert as an encoded HTML node, never interpolate untrusted markup.
            const doc = visual.contentDocument;
            const img = doc.createElement('img');
            img.src = url;
            img.alt = alt;
            img.loading = 'lazy';
            img.style.maxWidth = '100%';
            const selection = doc.getSelection();
            if (selection && selection.rangeCount > 0) selection.getRangeAt(0).insertNode(img);
            else doc.body.append(img);
            dirtyVisual = true;
        });
        extra('Таблица', 'Вставить таблицу 3×3', () => {
            if (mode !== 'visual') { showMode('visual'); return; }
            const doc = visual.contentDocument;
            const table = doc.createElement('table');
            const tbody = doc.createElement('tbody');
            for (let row = 0; row < 3; row++) {
                const tr = doc.createElement('tr');
                for (let col = 0; col < 3; col++) {
                    const cell = doc.createElement(row === 0 ? 'th' : 'td');
                    cell.textContent = row === 0 ? 'Заголовок' : 'Текст';
                    tr.append(cell);
                }
                tbody.append(tr);
            }
            table.append(tbody);
            const selection = doc.getSelection();
            if (selection && selection.rangeCount > 0) selection.getRangeAt(0).insertNode(table);
            else doc.body.append(table);
            dirtyVisual = true;
        });
        const codeStatus = make('span', 'uv-rich__format-status');
        codeStatus.setAttribute('role', 'status');
        extra('Форматировать код', 'Форматировать исходный код без сохранения', () => {
            showMode('source'); // Writes dirty visual content before formatting.
            if (!window.UVCodeFormatter) {
                codeStatus.textContent = 'Форматтер не загружен.';
                return;
            }
            window.UVCodeFormatter.apply(textarea, language.value, codeStatus);
        });
        formatting.append(codeStatus);
        formatting.setAttribute('aria-label', 'Форматирование');
        toolbar.append(formatting);

        // Source remains a plain textarea without JS or if an embedded iframe is blocked.
        source.hidden = false;
        visual.hidden = true;
        preview.hidden = true;
        for (const button of toolbar.querySelectorAll('[data-editor-mode]')) {
            button.setAttribute('aria-pressed', String(button.dataset.editorMode === 'source'));
        }
        textarea.closest('form')?.addEventListener('submit', () => {
            saveVisual();
            // Preserve untouched source HTML exactly (including complex site blocks).
            if (textarea.value === startValue) textarea.value = initial;
        });
    }
})();
