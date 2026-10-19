(() => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.getElementById('site-nav');
  if (toggle && nav) {
    const close = () => { toggle.setAttribute('aria-expanded', 'false'); nav.classList.remove('is-open'); };
    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      nav.classList.toggle('is-open', open);
    });
    nav.addEventListener('click', event => { if (event.target.closest('a')) close(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
    document.addEventListener('click', event => { if (!nav.contains(event.target) && !toggle.contains(event.target)) close(); });
  }

  const search = document.getElementById('service-search');
  const status = document.getElementById('search-status');
  if (search && status) {
    const rows = [...document.querySelectorAll('#price-list .price-row')];
    search.addEventListener('input', () => {
      const query = search.value.trim().toLocaleLowerCase('ru');
      let count = 0;
      rows.forEach(row => {
        row.hidden = !row.dataset.search.includes(query);
        if (!row.hidden) count++;
      });
      status.textContent = query ? (count ? `Найдено направлений: ${count}` : 'Совпадений нет. Опишите нужную работу по телефону.') : '';
    });
  }
})();
