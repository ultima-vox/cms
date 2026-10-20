(() => {
    'use strict';

    const restorePending = form => {
        delete form.dataset.pending;
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[data-pending-disabled]').forEach(button => {
            button.removeAttribute('aria-disabled');
            delete button.dataset.pendingDisabled;
        });
        const status = form.querySelector('[role="status"]');
        if (status) status.textContent = '';
    };
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            return;
        }
        if (form.hasAttribute('data-submit-pending')) {
            if (form.dataset.pending === 'true') { event.preventDefault(); return; }
            form.dataset.pending = 'true';
            form.setAttribute('aria-busy', 'true');
            // Keep the successful submitter enabled: its name/value is part of the native POST.
            form.querySelectorAll('button[type="submit"]').forEach(button => {
                if (!button.disabled && button.getAttribute('aria-disabled') !== 'true') {
                    button.setAttribute('aria-disabled', 'true');
                    button.dataset.pendingDisabled = 'true';
                }
            });
            const status = form.querySelector('[role="status"]');
            if (status) status.textContent = form.dataset.submitPending;
            // A task runs after every submit listener; microtasks can run between native callbacks.
            setTimeout(() => { if (event.defaultPrevented) restorePending(form); }, 0);
        }
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submit-pending]').forEach(restorePending);
    });

    document.querySelectorAll('.admin-form--actions').forEach(form => {
        const bar = form.querySelector('.admin-actions--sticky');
        if (!bar) return;
        const fitActions = () => {
            const height = bar.getBoundingClientRect().height;
            const available = window.visualViewport?.height ?? window.innerHeight;
            const flow = available < Math.max(360, height * 3);
            form.classList.toggle('admin-form--actions-flow', flow);
            form.style.setProperty('--uv-action-space', flow ? '0px' : `${height + 24}px`);
        };
        fitActions();
        if ('ResizeObserver' in window) new ResizeObserver(fitActions).observe(bar);
        window.addEventListener('resize', fitActions);
        window.visualViewport?.addEventListener('resize', fitActions);
        const revealField = event => window.requestAnimationFrame(() => {
            const field = event.target;
            if (!(field instanceof HTMLElement) || bar.contains(field)
                || form.classList.contains('admin-form--actions-flow')) return;
            const bounds = field.getBoundingClientRect();
            const barBounds = bar.getBoundingClientRect();
            if (bounds.bottom > barBounds.top - 12 && bounds.top < barBounds.bottom) {
                field.scrollIntoView({block: 'nearest'});
            }
        });
        form.addEventListener('focusin', revealField);
        form.addEventListener('invalid', revealField, true);
    });

    const disclosures = document.querySelectorAll('[data-admin-disclosure]');
    const narrowNavigation = window.matchMedia('(max-width: 800px)');
    const adaptNavigation = () => disclosures.forEach(panel => { panel.open = !narrowNavigation.matches; });
    adaptNavigation();
    narrowNavigation.addEventListener('change', adaptNavigation);
    disclosures.forEach(panel => {
        panel.addEventListener('keydown', event => {
            if (event.key === 'Escape' && narrowNavigation.matches && panel.open) {
                event.preventDefault();
                panel.open = false;
                panel.querySelector('summary')?.focus();
            }
        });
    });

    document.querySelectorAll('[data-admin-section]').forEach(section => {
        section.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !narrowNavigation.matches && section.open) {
                event.preventDefault();
                section.open = false;
                section.querySelector('summary')?.focus();
            }
        });
    });

    document.querySelectorAll('[data-site-select]').forEach((select) => {
        select.addEventListener('change', () => select.form?.submit());
    });

    const dialog = document.getElementById('admin-command-palette');
    const input = dialog?.querySelector('[data-command-input]');
    const empty = dialog?.querySelector('[data-command-empty]');
    const allItems = dialog ? Array.from(dialog.querySelectorAll('[data-command-item]')) : [];
    let visibleItems = allItems;
    let selectedIndex = 0;

    const renderSelection = () => {
        visibleItems.forEach((item, index) => {
            item.classList.toggle('admin-command__item--selected', index === selectedIndex);
        });
    };

    const filterItems = () => {
        const query = String(input?.value ?? '').trim().toLocaleLowerCase();
        visibleItems = [];

        allItems.forEach((item) => {
            const haystack = `${item.textContent ?? ''} ${item.dataset.commandKeywords ?? ''}`.toLocaleLowerCase();
            const matches = query === '' || haystack.includes(query);
            item.hidden = !matches;
            if (matches) {
                visibleItems.push(item);
            }
        });

        selectedIndex = 0;
        if (empty) {
            empty.style.display = visibleItems.length === 0 ? 'block' : 'none';
        }
        renderSelection();
    };

    const openPalette = () => {
        if (!(dialog instanceof HTMLDialogElement)) {
            return;
        }

        if (!dialog.open) {
            dialog.showModal();
        }
        if (input instanceof HTMLInputElement) {
            input.value = '';
            filterItems();
            window.requestAnimationFrame(() => input.focus());
        }
    };

    document.querySelectorAll('[data-command-open]').forEach((button) => {
        button.addEventListener('click', openPalette);
    });

    input?.addEventListener('input', filterItems);

    dialog?.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    dialog?.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' && visibleItems.length > 0) {
            event.preventDefault();
            selectedIndex = (selectedIndex + 1) % visibleItems.length;
            renderSelection();
            visibleItems[selectedIndex]?.scrollIntoView({ block: 'nearest' });
            return;
        }

        if (event.key === 'ArrowUp' && visibleItems.length > 0) {
            event.preventDefault();
            selectedIndex = (selectedIndex - 1 + visibleItems.length) % visibleItems.length;
            renderSelection();
            visibleItems[selectedIndex]?.scrollIntoView({ block: 'nearest' });
            return;
        }

        if (event.key === 'Enter' && visibleItems.length > 0) {
            event.preventDefault();
            visibleItems[selectedIndex]?.click();
        }
    });

    window.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLocaleLowerCase() === 'k') {
            event.preventDefault();
            openPalette();
        }
    });
})();
