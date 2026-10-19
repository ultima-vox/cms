/* Safe source formatting for CMS editors. No eval, downloads or remote code. */
(() => {
    'use strict';
    const BLOCK = new Set(('address article aside blockquote body caption dd details dialog div dl dt fieldset ' +
        'figcaption figure footer form h1 h2 h3 h4 h5 h6 head header hr html li main nav ol p ' +
        'section summary table tbody td tfoot th thead tr ul').split(' '));
    const RAW = new Set(['script', 'style', 'pre', 'textarea', 'code', 'svg', 'math']);
    const VOID = new Set('area base br col embed hr img input link meta param source track wbr'.split(' '));
    const fail = message => { throw new Error(message); };

    const readTag = (source, start) => {
        if (source.startsWith('<!--', start)) {
            const end = source.indexOf('-->', start + 4);
            return end < 0 ? null : {start, end: end + 3, name: '', kind: 'comment'};
        }
        if (source.startsWith('<?', start)) return {start, end: start + 2, name: '', kind: 'php'};
        const match = /^<(\/?)([a-z][a-z0-9:-]*)\b/i.exec(source.slice(start));
        if (!match) return null;
        let quote = '', end = -1;
        for (let i = start + match[0].length; i < source.length; i++) {
            const char = source[i];
            if (quote) {
                if (char === quote) quote = '';
            } else if (char === '"' || char === "'") {
                quote = char;
            } else if (char === '>') {
                end = i + 1;
                break;
            }
        }
        if (end < 0) return null;
        const name = match[2].toLowerCase();
        const kind = match[1] ? 'close' : VOID.has(name) || /\/\s*>$/.test(source.slice(start, end)) ? 'void' : 'open';
        return {start, end, name, kind};
    };

    const html = source => {
        // PHP string literals can contain apparent tags; never rewrite mixed templates.
        if (/<\?/.test(source)) fail('Смешанный HTML/PHP нельзя безопасно форматировать: код не изменён.');
        const tokens = [];
        let inside = 0;
        for (let i = 0; i < source.length;) {
            const next = source.indexOf('<', i);
            if (next < 0) break;
            const tag = readTag(source, next);
            if (!tag) { i = next + 1; continue; }
            if (tag.kind === 'php') fail('PHP-вставка: исходный код сохранён.');
            if (inside > 0 && !(tag.kind === 'close' && RAW.has(tag.name))) {
                i = tag.end;
                continue;
            }
            tokens.push({...tag, protected: inside > 0});
            if (tag.kind === 'open' && RAW.has(tag.name)) inside++;
            if (tag.kind === 'close' && RAW.has(tag.name)) inside = Math.max(0, inside - 1);
            i = tag.end;
        }

        let result = '', cursor = 0, depth = 0, previous = null;
        for (const tag of tokens) {
            const gap = source.slice(cursor, tag.start);
            const indent = tag.kind === 'close' && BLOCK.has(tag.name) ? Math.max(0, depth - 1) : depth;
            const safe = previous && !previous.protected && !tag.protected
                && !RAW.has(previous.name) && !RAW.has(tag.name)
                && BLOCK.has(previous.name) && BLOCK.has(tag.name) && /^\s*$/.test(gap);
            result += safe ? '\n' + '    '.repeat(Math.min(indent, 32)) : gap;
            result += source.slice(tag.start, tag.end);
            if (tag.kind === 'open' && BLOCK.has(tag.name)) depth++;
            if (tag.kind === 'close' && BLOCK.has(tag.name)) depth = Math.max(0, depth - 1);
            cursor = tag.end;
            previous = tag;
        }
        return result + source.slice(cursor);
    };

    const indentation = (source, language) => {
        // Do not alter whitespace-sensitive strings (JS templates / PHP heredocs).
        if ((language === 'javascript' && source.includes(String.fromCharCode(96)))
            || (language === 'php' && /<<<|<\?(?!php\b)/i.test(source))
            || /\\\r?\n/.test(source)) {
            fail('Обнаружена конструкция с чувствительными отступами. Код не изменён.');
        }
        if (language === 'php' && /<\?php[\s\S]*\?>[\s\S]*<[\w!/]/i.test(source)) {
            fail('Смешанный PHP/HTML оставлен без изменений.');
        }
        let depth = 0, blockComment = false, quote = '';
        const newline = source.includes('\r\n') ? '\r\n' : '\n';
        const output = [];
        for (const line of source.split(/\r?\n/)) {
            if (line.trim() === '') { output.push(''); continue; }
            let delta = 0, beforeClose = 0, firstCode = true;
            for (let i = 0; i < line.length; i++) {
                const char = line[i], next = line[i + 1];
                if (blockComment) {
                    if (char === '*' && next === '/') { blockComment = false; i++; }
                    continue;
                }
                if (quote) {
                    if (char === '\\') { i++; continue; }
                    if (char === quote) quote = '';
                    continue;
                }
                if (char === '"' || char === "'") { quote = char; firstCode = false; continue; }
                if (char === '/' && next === '*') { blockComment = true; i++; continue; }
                if ((char === '/' && next === '/') || (language === 'php' && char === '#')) break;
                if (/\s/.test(char) && firstCode) continue;
                if (char === '{') delta++;
                if (char === '}') { delta--; if (firstCode) beforeClose++; }
                firstCode = false;
            }
            if (quote) fail('Многостроковая строка: форматирование отключено.');
            output.push('    '.repeat(Math.min(32, Math.max(0, depth - beforeClose)))
                + line.replace(/^[\t ]*/, ''));
            depth = Math.max(0, depth + delta);
        }
        return output.join(newline);
    };

    const format = (source, language = 'html') => {
        if (typeof source !== 'string') fail('Неверное содержимое.');
        if (source.length > 1_000_000) fail('Файл слишком большой для форматирования.');
        if (language === 'html') return html(source);
        if (['css', 'javascript', 'php'].includes(language)) return indentation(source, language);
        fail('Неизвестный язык форматирования.');
    };

    const cursorAfter = (before, after, offset) => {
        const significant = (before.slice(0, offset).match(/\S/g) || []).length;
        if (significant === 0) return 0;
        let found = 0;
        for (let i = 0; i < after.length; i++) {
            if (/\S/.test(after[i]) && ++found === significant) return i + 1;
        }
        return after.length;
    };

    const apply = (textarea, language, status = null) => {
        const previous = textarea.value;
        try {
            const result = format(previous, language);
            if (result === previous) {
                if (status) status.textContent = 'Код уже отформатирован.';
                return false;
            }
            const start = cursorAfter(previous, result, textarea.selectionStart);
            const end = cursorAfter(previous, result, textarea.selectionEnd);
            const top = textarea.scrollTop, left = textarea.scrollLeft;
            textarea.value = result;
            textarea.setSelectionRange(start, end);
            textarea.scrollTop = top;
            textarea.scrollLeft = left;
            textarea.dispatchEvent(new Event('input', {bubbles: true}));
            textarea.focus();
            if (status) status.textContent = 'Код отформатирован. Сохраните изменения.';
            return true;
        } catch (exception) {
            if (status) status.textContent = exception.message;
            return false;
        }
    };

    const mount = () => {
        for (const textarea of document.querySelectorAll('textarea[data-code-format]')) {
            if (textarea.dataset.codeFormatReady) continue;
            textarea.dataset.codeFormatReady = '1';
            const tools = document.createElement('div');
            tools.className = 'uv-code-format__toolbar';
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'uv-code-format__button';
            button.textContent = 'Форматировать код';
            button.setAttribute('aria-label', 'Форматировать код без сохранения');
            const status = document.createElement('span');
            status.className = 'uv-code-format__status';
            status.setAttribute('role', 'status');
            button.addEventListener('click', () => apply(
                textarea, textarea.dataset.codeLanguage || 'html', status,
            ));
            tools.append(button, status);
            textarea.before(tools);
        }
    };
    const api = {format, apply, mount};
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    if (typeof window !== 'undefined') {
        window.UVCodeFormatter = api;
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, {once: true});
        else mount();
    }
})();
