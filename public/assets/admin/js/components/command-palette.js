export function initCommandPalette() {
    const dialog = document.getElementById('admin-command-palette');
    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    const input = dialog.querySelector('[data-command-input]');
    const empty = dialog.querySelector('[data-command-empty]');
    const allItems = Array.from(dialog.querySelectorAll('[data-command-item]'));
    let visibleItems = allItems;
    let selectedIndex = 0;

    const renderSelection = () => {
        visibleItems.forEach((item, index) => {
            item.classList.toggle('admin-command__item--selected', index === selectedIndex);
        });
    };

    const filterItems = () => {
        const query = input instanceof HTMLInputElement
            ? input.value.trim().toLocaleLowerCase()
            : '';

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
        if (empty instanceof HTMLElement) {
            empty.hidden = visibleItems.length !== 0;
        }

        renderSelection();
    };

    const openPalette = () => {
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

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    dialog.addEventListener('keydown', (event) => {
        if (visibleItems.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            selectedIndex = (selectedIndex + 1) % visibleItems.length;
            renderSelection();
            visibleItems[selectedIndex]?.scrollIntoView({ block: 'nearest' });
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            selectedIndex = (selectedIndex - 1 + visibleItems.length) % visibleItems.length;
            renderSelection();
            visibleItems[selectedIndex]?.scrollIntoView({ block: 'nearest' });
            return;
        }

        if (event.key === 'Enter') {
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
}
