'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const source = fs.readFileSync('frontend/editors/index.js', 'utf8');
const bundle = fs.readFileSync('scripts/build-editors.mjs', 'utf8');
assert.match(source, /textarea\.dispatchEvent\(new Event\('input'/);
assert.match(source, /textarea\.form\?\.addEventListener\('reset'/);
assert.match(source, /window\.UVCodeFormatter\.apply\(textarea, language, status\)/);
assert.match(source, /data-cms-editor/);
assert.match(source, /data\.allowHtmlNormalization !== 'true'/);
assert.match(bundle, /bundle: true/);
for (const filename of [
    'modules/documents/templates/admin/documents/form.php',
    'templates/admin/layouts/form.php',
]) {
    const form = fs.readFileSync(filename, 'utf8');
    assert.match(form, /data-cms-editor="code"/, filename);
    assert.match(form, /is_file\(dirname\(__DIR__, [25]\) \. '\/public\/assets\/cms-editors\.js'\)/, filename);
    assert.match(form, /\/assets\/code-formatter\.js/, filename);
}
console.log('Editor progressive integration static checks passed');
