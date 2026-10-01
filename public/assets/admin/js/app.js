import { initCommandPalette } from './components/command-palette.js';
import { initDialogs } from './components/dialog.js';
import { initDropdowns } from './components/dropdown.js';
import { initSidebar } from './components/sidebar.js';
import { initSiteSwitcher } from './components/site-switcher.js';

export function initAdmin() {
    initSidebar();
    initSiteSwitcher();
    initCommandPalette();
    initDialogs();
    initDropdowns();
}

initAdmin();
