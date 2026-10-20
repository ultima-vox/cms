'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const handlers = {};
const input = {value: '', selectionStart: 3, selectionEnd: 3,
    addEventListener: (name, fn) => handlers[name] = fn,
    setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end; }};
vm.runInNewContext(fs.readFileSync('public/assets/infosystem-money.js', 'utf8'), {
    document: {querySelectorAll: () => [input]},
});
input.value = '100,05';
handlers.input();
assert.equal(input.value, '100.05');
assert.equal(input.selectionStart, 3);
for (const [raw, expected] of [['35000.50', '35 000.50'], ['100,05', '100.05'], ['800.00', '800'],
    ['001234,5', '1 234.50'], ['999999999999.99', '999 999 999 999.99'], ['100.005', '100.005'], ['1 23', '1 23'], ['', '']]) {
    input.value = raw; handlers.blur(); assert.equal(input.value, expected);
}
console.log('MONEY INPUT OK: immediate comma normalization/caret, exact grouping and malformed input retained for server validation');
