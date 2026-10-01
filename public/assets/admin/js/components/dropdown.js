const ROOT_SELECTOR = '[data-dropdown]';
const TRIGGER_SELECTOR = '[data-dropdown-trigger]';
const MENU_SELECTOR = '[data-dropdown-menu]';
const ITEM_SELECTOR = '[data-dropdown-item]';

const initializedRoots = new WeakSet();
const openRoots = new Set();
let globalListenersBound = false;
let menuSequence = 0;

function findOwned(root, selector) {
    return [...root.querySelectorAll(selector)]
        .find((element) => element.closest(ROOT_SELECTOR) === root) ?? null;
}

function getEnabledItems(root) {
    const menu = findOwned(root, MENU_SELECTOR);
    if (!menu) {
        return [];
    }

    return [...menu.querySelectorAll(ITEM_SELECTOR)].filter((item) => {
        return !item.hidden
            && !item.hasAttribute('disabled')
            && item.getAttribute('aria-disabled') !== 'true';
    });
}

function focusItem(root, position) {
    const items = getEnabledItems(root);
    if (items.length === 0) {
        findOwned(root, MENU_SELECTOR)?.focus();
        return;
    }

    const activeIndex = items.indexOf(document.activeElement);
    let nextIndex = 0;

    if (position === 'first') {
        nextIndex = 0;
    } else if (position === 'last') {
        nextIndex = items.length - 1;
    } else if (position === 'next') {
        nextIndex = activeIndex < 0 ? 0 : (activeIndex + 1) % items.length;
    } else if (position === 'previous') {
        nextIndex = activeIndex < 0 ? items.length - 1 : (activeIndex - 1 + items.length) % items.length;
    }

    items[nextIndex]?.focus();
}

export function closeDropdown(root, { restoreFocus = false } = {}) {
    const trigger = findOwned(root, TRIGGER_SELECTOR);
    const menu = findOwned(root, MENU_SELECTOR);

    if (!trigger || !menu) {
        return;
    }

    menu.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    openRoots.delete(root);

    if (restoreFocus) {
        trigger.focus();
    }
}

export function openDropdown(root, { focus = null } = {}) {
    const trigger = findOwned(root, TRIGGER_SELECTOR);
    const menu = findOwned(root, MENU_SELECTOR);

    if (!trigger || !menu) {
        return;
    }

    for (const openRoot of [...openRoots]) {
        if (openRoot !== root) {
            closeDropdown(openRoot);
        }
    }

    menu.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    openRoots.add(root);

    if (focus === 'first' || focus === 'last') {
        focusItem(root, focus);
    }
}

function toggleDropdown(root) {
    const trigger = findOwned(root, TRIGGER_SELECTOR);
    if (!trigger) {
        return;
    }

    if (trigger.getAttribute('aria-expanded') === 'true') {
        closeDropdown(root);
    } else {
        openDropdown(root);
    }
}

function setupRoot(root) {
    if (initializedRoots.has(root)) {
        return;
    }

    const trigger = findOwned(root, TRIGGER_SELECTOR);
    const menu = findOwned(root, MENU_SELECTOR);
    if (!trigger || !menu) {
        return;
    }

    initializedRoots.add(root);

    if (!menu.id) {
        menu.id = `admin-dropdown-menu-${++menuSequence}`;
    }

    trigger.setAttribute('aria-haspopup', 'menu');
    trigger.setAttribute('aria-controls', menu.id);
    trigger.setAttribute('aria-expanded', 'false');
    menu.setAttribute('role', 'menu');
    menu.tabIndex = -1;
    menu.hidden = true;

    for (const item of menu.querySelectorAll(ITEM_SELECTOR)) {
        if (!item.hasAttribute('role')) {
            item.setAttribute('role', 'menuitem');
        }
        item.tabIndex = -1;
    }

    trigger.addEventListener('click', () => toggleDropdown(root));

    root.addEventListener('keydown', (event) => {
        if (event.target === trigger && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
            event.preventDefault();
            openDropdown(root, { focus: event.key === 'ArrowDown' ? 'first' : 'last' });
            return;
        }

        if (menu.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeDropdown(root, { restoreFocus: true });
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            focusItem(root, 'next');
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            focusItem(root, 'previous');
        } else if (event.key === 'Home') {
            event.preventDefault();
            focusItem(root, 'first');
        } else if (event.key === 'End') {
            event.preventDefault();
            focusItem(root, 'last');
        } else if (event.key === 'Tab') {
            closeDropdown(root);
        }
    });

    menu.addEventListener('click', (event) => {
        const item = event.target.closest(ITEM_SELECTOR);
        if (!item || item.closest(ROOT_SELECTOR) !== root) {
            return;
        }

        if (item.hasAttribute('disabled') || item.getAttribute('aria-disabled') === 'true') {
            event.preventDefault();
            return;
        }

        closeDropdown(root);
    });
}

function bindGlobalListeners() {
    if (globalListenersBound) {
        return;
    }

    globalListenersBound = true;

    document.addEventListener('click', (event) => {
        for (const root of [...openRoots]) {
            if (!root.contains(event.target)) {
                closeDropdown(root);
            }
        }
    });
}

export function initDropdowns(scope = document) {
    const roots = [];

    if (scope instanceof Element && scope.matches(ROOT_SELECTOR)) {
        roots.push(scope);
    }

    roots.push(...scope.querySelectorAll(ROOT_SELECTOR));
    roots.forEach(setupRoot);
    bindGlobalListeners();
}
