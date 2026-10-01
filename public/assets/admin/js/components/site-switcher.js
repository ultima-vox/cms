export function initSiteSwitcher() {
    document.querySelectorAll('[data-site-select]').forEach((select) => {
        if (!(select instanceof HTMLSelectElement)) {
            return;
        }

        select.addEventListener('change', () => {
            const form = select.form;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
                return;
            }

            form.submit();
        });
    });
}
