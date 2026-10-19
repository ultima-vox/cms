(() => {
    const toggle = document.querySelector('.mk-menu-toggle');
    const header = document.querySelector('.mk-header');
    if (!toggle || !header) return;
    document.documentElement.classList.add('mk-js');
    const close = () => {
        header.classList.remove('is-menu-open');
        toggle.setAttribute('aria-expanded', 'false');
    };
    toggle.addEventListener('click', () => {
        const open = header.classList.toggle('is-menu-open');
        toggle.setAttribute('aria-expanded', String(open));
    });
    document.querySelectorAll('.mk-nav a').forEach(link => link.addEventListener('click', close));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && header.classList.contains('is-menu-open')) {
            close();
            toggle.focus();
        }
    });
})();
