(() => {
    'use strict';

    const shell = document.body;
    const collapseButton = document.querySelector('[data-admin-collapse]');
    const collapseKey = 'uv-admin-sidebar-collapsed';

    const readPreference = (key) => {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    };

    const writePreference = (key, value) => {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            // UI preferences are optional; storage restrictions must not break admin navigation.
        }
    };

    const setCollapsed = (collapsed) => {
        shell.classList.toggle('admin-shell--collapsed', collapsed);
        collapseButton?.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
    };

    if (collapseButton) {
        setCollapsed(readPreference(collapseKey) === '1');
        collapseButton.addEventListener('click', () => {
            const collapsed = !shell.classList.contains('admin-shell--collapsed');
            setCollapsed(collapsed);
            writePreference(collapseKey, collapsed ? '1' : '0');
        });
    }

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
