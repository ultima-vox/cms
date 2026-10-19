import { Editor as TipTapEditor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { EditorState } from '@codemirror/state';
import { EditorView, keymap, lineNumbers, highlightActiveLine, drawSelection } from '@codemirror/view';
import { basicSetup } from 'codemirror';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { javascript } from '@codemirror/lang-javascript';
import { php } from '@codemirror/lang-php';

/**
 * Optional progressive enhancement: native textareas remain the form authority.
 * Do not enable automatically for legacy templates: TipTap normalizes arbitrary HTML.
 */
const language = (name) => ({
  html: html,
  css: css,
  javascript: javascript,
  php: php,
})[name]?.() ?? html();

const button = (label, action) => {
  const element = document.createElement('button');
  element.type = 'button';
  element.textContent = label;
  element.addEventListener('click', action);
  return element;
};

function mountCode(textarea) {
  const mount = document.createElement('div');
  mount.className = 'uv-cm-editor';
  textarea.after(mount);
  const view = new EditorView({
    state: EditorState.create({
      doc: textarea.value,
      extensions: [
        basicSetup,
        language(textarea.dataset.editorLanguage || 'html'),
        EditorView.updateListener.of(update => {
          if (!update.docChanged) return;
          textarea.value = update.state.doc.toString();
          textarea.dispatchEvent(new Event('input', { bubbles: true }));
        }),
      ],
    }),
    parent: mount,
  });
  textarea.hidden = true;
  const form = textarea.form;
  form?.addEventListener('reset', () => queueMicrotask(() => {
    view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: textarea.value } });
  }));
  return view;
}

function mountRich(textarea) {
  const mount = document.createElement('div');
  mount.className = 'uv-tiptap-editor';
  textarea.after(mount);
  const toolbar = document.createElement('div');
  toolbar.className = 'uv-tiptap-toolbar';
  const content = document.createElement('div');
  mount.append(toolbar, content);
  const editor = new TipTapEditor({
    element: content,
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
  textarea.hidden = true;
  return editor;
}

function mount() {
  for (const textarea of document.querySelectorAll('textarea[data-cms-editor]')) {
    if (textarea.dataset.cmsEditorMounted) continue;
    const kind = textarea.dataset.cmsEditor;
    if (kind !== 'code' && kind !== 'rich') continue;
    // Exclude already mounted legacy editor widgets.
    if (textarea.closest('.uv-rich')) continue;
    if (kind === 'rich' && textarea.dataset.allowHtmlNormalization !== 'true') continue;
    textarea.dataset.cmsEditorMounted = '1';
    kind === 'code' ? mountCode(textarea) : mountRich(textarea);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount, { once: true });
} else {
  mount();
}
