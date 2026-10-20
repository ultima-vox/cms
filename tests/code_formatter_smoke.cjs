// Run with Node, no browser or external formatter packages needed.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {format} = require('../public/assets/code-formatter.js');

const html = '<section><div><h2>Заголовок</h2><p>Текст <strong>важный</strong>.</p></div></section>';
const expected = '<section>\n    <div>\n        <h2>Заголовок</h2>\n        <p>Текст <strong>важный</strong>.</p>\n    </div>\n</section>';
assert.equal(format(html, 'html'), expected, 'Only block boundaries should receive indentation');
assert.equal(format(expected, 'html'), expected, 'HTML formatter must be idempotent');

const preserve = '<section><p>A <b>bold</b> B</p><div><svg><path d="M1 2" /></svg></div></section>';
const formatted = format(preserve, 'html');
assert(formatted.includes('<p>A <b>bold</b> B</p>'), 'Inline whitespace/text must be preserved');
assert(formatted.includes('<svg><path d="M1 2" /></svg>'), 'Inline SVG must be preserved');
assert(formatted.includes('</p>\n'), 'HTML block lines should be broken');

const pre = '<section><pre>  a\n  b</pre><p>Text</p></section>';
assert(format(pre, 'html').includes('<pre>  a\n  b</pre>'), 'PRE indentation must be preserved');
assert.equal(format('<p title="x > y">A</p>', 'html'), '<p title="x > y">A</p>',
    'Angle bracket in quoted HTML attribute should not be split');
assert.throws(() => format('<div><?= $unsafe ?></div>', 'html'), /PHP/,
    'Mixed PHP source must not be changed');
assert.equal(format('<section><p>One</p></section>', 'html').includes('<p>One</p>'), true);

const css = 'body {\ncolor: red;\n.widget {\npadding: 4px;\n}\n}';
const styled = 'body {\n    color: red;\n    .widget {\n        padding: 4px;\n    }\n}';
assert.equal(format(css, 'css'), styled);
assert.equal(format(styled, 'css'), styled);
assert.equal(format('a { color: red; }', 'css'), 'a { color: red; }',
    'One-line CSS must not be broken by indentation-only formatting');
assert.equal(
    format('function a() {\nconst x = "{";\nif (x) {\nreturn x;\n}\n}', 'javascript'),
    'function a() {\n    const x = "{";\n    if (x) {\n        return x;\n    }\n}',
    'Braces inside quotes must not affect JS indentation',
);
assert.throws(() => format('const s = ' + String.fromCharCode(96) + 'raw\ntext' + String.fromCharCode(96), 'javascript'),
    /чувствительными/);
const php = '<?php\nfunction output(): void {\necho "safe";\n}\n';
assert.equal(format(php, 'php'), '<?php\nfunction output(): void {\n    echo "safe";\n}\n');
assert.throws(() => format('<?php\n$x = <<<TXT\nraw\nTXT;\n', 'php'), /чувствительными/);

const root = path.join(__dirname, '..');
for (const file of ['modules/documents/templates/admin/documents/form.php',
    'templates/admin/layouts/form.php']) {
    const content = fs.readFileSync(path.join(root, file), 'utf8');
    assert(content.includes('data-code-format'), file + ' lacks formatter control');
    assert(content.includes('/assets/code-formatter.js'), file + ' lacks script');
}
const itemForm = fs.readFileSync(path.join(root,
    'modules/infosystem/templates/admin/infosystems/item_form.php'), 'utf8');
assert(itemForm.includes('/assets/code-formatter.js'));
const itemEditor = fs.readFileSync(path.join(root, 'public/assets/infosystem-editor.js'), 'utf8');
assert(itemEditor.includes("extra('Форматировать код'"));
assert(itemEditor.includes("window.UVCodeFormatter.apply(textarea, language.value, codeStatus)"));
console.log('CODE FORMATTER OK: safe HTML, CSS, JavaScript, PHP and four editor fields');
