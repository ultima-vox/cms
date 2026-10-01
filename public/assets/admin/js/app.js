import { initCommandPalette } from './components/command-palette.js';
import { initSidebar } from './components/sidebar.js';
import { initSiteSwitcher } from './components/site-switcher.js';

export function initAdmin() {
    initSidebar();
    initSiteSwitcher();
    initCommandPalette();
}

initAdmin();
