(() => {
    'use strict';
    // Formatting only: no floating point conversion, no hidden alternate value.
    function format(value) {
        const match = /^(\d+|\d{1,3}(?:[ \u00a0\u202f]\d{3})+)(?:[.,](\d{1,2}))?$/.exec(value.trim());
        if (!match) return value;
        const whole = match[1].replace(/[ \u00a0\u202f]/g, '').replace(/^0+(?=\d)/, '');
        if (whole.length > 12) return value;
        const fraction = (match[2] || '').padEnd(2, '0');
        return whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + (fraction === '00' ? '' : '.' + fraction);
    }
    document.querySelectorAll('[data-money-input]').forEach(input => {
        input.addEventListener('input', () => {
            if (!input.value.includes(',')) return;
            const start = input.selectionStart;
            const end = input.selectionEnd;
            input.value = input.value.replace(/,/g, '.');
            if (start !== null && end !== null) input.setSelectionRange(start, end);
        });
        input.addEventListener('blur', () => { input.value = format(input.value); });
    });
})();
