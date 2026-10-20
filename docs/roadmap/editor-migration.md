# Editors migration — incremental implementation

Branch: `feature/editor-tiptap-codemirror`. This is the **foundation**, not a production editor switch.

## Architecture

- `frontend/editors/index.js`: optional enhancement of native textarea form fields.
- CodeMirror 6: HTML, CSS, JavaScript, PHP source editing.
- TipTap: structured rich text only; requires explicit `data-allow-html-normalization="true"`.
- Existing textarea remains the form submission authority; all existing CSRF, authorization, validation, revision and publication logic stays server-side.
- Existing editor assets stay in place until editor-specific round-trip regression tests pass.

## Build

```bash
npm install
npm run check:editors
npm run build:editors
```

The build produces `public/assets/cms-editors.js`, to be deployed as a locally hosted, fingerprinted asset. No CDN dependency.

## Rollout blockers (do not switch forms before closing)

1. Pin and commit `package-lock.json` using a network-enabled environment; vulnerability/license review.
2. Complete tests for round-trip source HTML, complex site templates, CSS, JS, PHP, form errors, focus, clipboard, undo, keyboard and mobile.
3. Keep document HTML source editing in CodeMirror until TipTap schemas/extensions are defined and content migration is explicitly approved.
4. Wire the bundle through the admin asset pipeline, with CSP validation and reliable fallback.
5. Decide how to replace the existing custom contenteditable/iframe editor without breaking sanitization, publication, and preview.
6. Provide a language-specific formatter via verified libraries; editor integration alone is **not** autoformatting.
7. Add rich editor support for links, tables, images, headings and context-specific blocks with server-side validation.

## Risks

- TipTap/ProseMirror parses and normalizes HTML; it may discard unsupported tags and attributes. It must never be used with arbitrary PHP templates.
- Never infer rich-text consent from field name alone.
- CodeMirror only changes client editing experience and must not bypass server-side permissions, sanitization or audit.
- The existing custom editor will be removed only after safe migration and browser tests.

## Progress (19 October 2026)

- CodeMirror is opt-in in Documents source and Layouts source forms through `data-cms-editor="code"`.
- HTML/PHP form templates only emit the bundled editor asset and editor stylesheet if `public/assets/cms-editors.js` exists on disk; without the build, the existing editor and formatter remain active.
- New source edits update the original textarea and dispatch input events; source input, reset, and the no-save format action synchronize back to CodeMirror.
- `Shift+Alt+F` invokes the existing conservative source formatter. It is **not** a full-featured language formatter.
- The TipTap adapter remains disabled in live forms pending explicit content compatibility tests.
- Added `tests/editor_integration_smoke.cjs` static checks.

Run after installing dependencies:
```bash
npm install
npm run check:editors
node tests/editor_integration_smoke.cjs
node tests/code_formatter_smoke.cjs
npm run build:editors
```

**Do not commit the generated bundle without producing a lockfile and running browser tests.** This environment did not have npm registry access, so package installation/build and browser acceptance remain unverified.

## Follow-up integration check — 21 October 2026

- Fixed the project-root check for `templates/admin/layouts/form.php` (`dirname(__DIR__, 3)`).
- Added `.github/workflows/editor-bundle.yml` to install npm dependencies, validate source, build the browser bundle and upload it as an artifact.
- This workflow does **not** commit or deploy compiled assets. A passing run will verify bundle compilation, but a release process must still install or package the asset.
- Infosystem TipTap integration remains gated: current rich content may include attributes/HTML structures that StarterKit does not preserve. Do not substitute TipTap until lossless conversion or explicit per-field migration is implemented.
- The new workflow has not yet reported a result at the time this note was written; do not assert editor-build success.
