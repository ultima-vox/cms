import { readPreference, writePreference } from '../core/storage.js';

const COLLAPSE_KEY = 'uv-admin-sidebar-collapsed';

export function initSidebar() {
    const shell = document.body;
    const collapseButton = document.querySelector('[data-admin-collapse]');

    if (!(collapseButton instanceof HTMLButtonElement)) {
        return;
    }

    const setCollapsed = (collapsed) => {
        shell.classList.toggle('admin-shell--collapsed', collapsed);
        collapseButton.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
    };

    setCollapsed(readPreference(COLLAPSE_KEY) === '1');

    collapseButton.addEventListener('click', () => {
        const collapsed = !shell.classList.contains('admin-shell--collapsed');
        setCollapsed(collapsed);
        writePreference(COLLAPSE_KEY, collapsed ? '1' : '0');
    });
}
