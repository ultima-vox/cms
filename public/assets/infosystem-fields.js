(() => {
    const container = document.getElementById('field-schema');
    const template = document.getElementById('field-template');
    const add = document.getElementById('add-field');
    const rename = () => {
        [...container.querySelectorAll('[data-field-row]')].forEach((row, index) => {
            row.querySelectorAll('[data-name]').forEach((input) => {
                const key = input.dataset.name.replace('-hidden', '');
                input.name = `fields[${index}][${key}]`;
            });
        });
    };
    add.addEventListener('click', () => { container.append(template.content.cloneNode(true)); rename(); });
    container.addEventListener('click', (event) => { const button = event.target.closest('[data-remove-field]'); if (!button) return; button.closest('[data-field-row]').remove(); rename(); });
    rename();
})();
