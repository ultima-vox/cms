import { Editor as TipTapEditor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { EditorState } from '@codemirror/state';
import { EditorView, keymap, lineNumbers, highlightActiveLine, drawSelection } from '@codemirror/view';
import { defaultKeymap, history, historyKeymap, indentWithTab } from '@codemirror/commands';
import { syntaxHighlighting, defaultHighlightStyle, indentOnInput, bracketMatching } from '@codemirror/language';
import { searchKeymap, highlightSelectionMatches } from '@codemirror/search';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { javascript } from '@codemirror/lang-javascript';
import { php } from '@codemirror/lang-php';

// CMS source fields are authoritative; editor instances never post data independently.
const languages = { html, css, javascript, php };
const button = (label, action) => {
    const node = document.createElement('button');
    node.type = 'button';
    node.textContent = label;
    node.addEventListener('click', action);
    return node;
};

function mountCode(textarea) {
    const wrap = document.createElement('div');
    wrap.className = 'uv-cm-editor';
    textarea.after(wrap);
    let synchronizing = false;
    const language = textarea.dataset.editorLanguage || textarea.dataset.codeLanguage || 'html';
    const view = new EditorView({
        state: EditorState.create({
            doc: textarea.value,
            extensions: [
                lineNumbers(), highlightActiveLine(), drawSelection(), history(),
                indentOnInput(), bracketMatching(),
                syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
                highlightSelectionMatches(),
                keymap.of([...defaultKeymap, ...historyKeymap, ...searchKeymap, indentWithTab]),
                (languages[language] || html)(),
                EditorView.lineWrapping,
                EditorView.updateListener.of(update => {
                    if (!update.docChanged || synchronizing) return;
                    textarea.value = update.state.doc.toString();
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                }),
            ],
        }),
        parent: wrap,
    });
    // Hide the original source only after CodeMirror was constructed successfully.
    // Keep the textarea in the form so native submission and server validation stay unchanged.
    textarea.classList.add('uv-cm-original');
    const syncFromTextarea = () => {
        if (textarea.value === view.state.doc.toString()) return;
        synchronizing = true;
        view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: textarea.value } });
        synchronizing = false;
    };
    textarea.addEventListener('input', syncFromTextarea);
    textarea.form?.addEventListener('reset', () => queueMicrotask(syncFromTextarea));
    textarea.addEventListener('invalid', () => view.focus());

    const toolbar = document.createElement('div');
    toolbar.className = 'uv-cm-toolbar';
    const status = document.createElement('span');
    status.className = 'uv-cm-status';
    status.setAttribute('role', 'status');
    const format = () => {
        if (!window.UVCodeFormatter) {
            status.textContent = 'Форматирование недоступно.';
            return;
        }
        window.UVCodeFormatter.apply(textarea, language, status);
        syncFromTextarea();
        view.focus();
    };
    const formatButton = button('Форматировать код', format);
    formatButton.setAttribute('aria-label', 'Форматировать код без сохранения');
    toolbar.append(formatButton, status);
    wrap.before(toolbar);
    // Remove the legacy duplicate toolbar, but retain the shared formatter API.
    const legacyToolbar = textarea.previousElementSibling;
    if (legacyToolbar?.classList.contains('uv-code-format__toolbar')) legacyToolbar.remove();
    wrap.addEventListener('keydown', event => {
        if (event.shiftKey && event.altKey && event.key.toLowerCase() === 'f') {
            event.preventDefault();
            format();
        }
    });
    return view;
}

function mountRich(textarea) {
    const wrap = document.createElement('div');
    wrap.className = 'uv-tiptap-editor';
    textarea.after(wrap);
    const toolbar = document.createElement('div');
    toolbar.className = 'uv-tiptap-toolbar';
    const body = document.createElement('div');
    wrap.append(toolbar, body);
    const editor = new TipTapEditor({
        element: body,
        extensions: [StarterKit],
        content: textarea.value,
        onUpdate: ({ editor: current }) => {
            textarea.value = current.getHTML();
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        },
    });
    toolbar.append(
        button('Жирный', () => editor.chain().focus().toggleBold().run()),
        button('Курсив', () => editor.chain().focus().toggleItalic().run()),
        button('H2', () => editor.chain().focus().toggleHeading({ level: 2 }).run()),
        button('Список', () => editor.chain().focus().toggleBulletList().run()),
        button('Отменить', () => editor.chain().focus().undo().run()),
        button('Повторить', () => editor.chain().focus().redo().run()),
    );
    textarea.classList.add('uv-cm-original');
    textarea.form?.addEventListener('reset', () => queueMicrotask(() => {
        editor.commands.setContent(textarea.value, false);
    }));
    return editor;
}

function mount() {
    for (const textarea of document.querySelectorAll('textarea[data-cms-editor]')) {
        if (textarea.dataset.cmsEditorMounted || textarea.closest('.uv-rich')) continue;
        const kind = textarea.dataset.cmsEditor;
        if (kind !== 'code' && kind !== 'rich') continue;
        if (kind === 'rich' && textarea.dataset.allowHtmlNormalization !== 'true') continue;
        try {
            kind === 'code' ? mountCode(textarea) : mountRich(textarea);
            textarea.dataset.cmsEditorMounted = '1';
        } catch (error) {
            // An enhancement failure must never block editing through the original textarea.
            textarea.classList.remove('uv-cm-original');
            console.error('CMS editor initialization failed', error);
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount, { once: true });
} else {
    mount();
}
