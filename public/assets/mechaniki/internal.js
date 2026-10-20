/* Internal price catalogue. All rows remain available without JavaScript. */
(() => {
  'use strict';
  const search = document.querySelector('[data-catalog-search]');
  const category = document.querySelector('[data-catalog-category]');
  if (!search || !category) return;
  const rows = [...document.querySelectorAll('[data-catalog-row]')];
  const chips = [...document.querySelectorAll('[data-catalog-chip]')];
  const count = document.querySelector('[data-catalog-count]');
  const empty = document.querySelector('[data-catalog-empty]');
  const normalize = value => value.trim().toLocaleLowerCase('ru').replace(/ё/g, 'е');
  let searchTimer;
  const filter = (track = true) => {
    const words = normalize(search.value).split(/\s+/).filter(Boolean);
    let visible = 0;
    rows.forEach(row => {
      const match = words.every(word => normalize(row.dataset.search).includes(word));
      row.hidden = !(match && (!category.value || row.dataset.category === category.value));
      if (!row.hidden) visible++;
    });
    chips.forEach(chip => chip.setAttribute('aria-pressed', String(chip.dataset.catalogChip === category.value)));
    count.textContent = `Найдено работ: ${visible}`;
    empty.hidden = visible > 0;
    if(track){
      clearTimeout(searchTimer);
      const detail={count:visible,category:rows.some(row=>row.dataset.category===category.value)?category.value:'',query_length:search.value.trim().length};
      searchTimer=setTimeout(()=>document.dispatchEvent(new CustomEvent('mechaniki:price-search',{detail})),300);
    }
  };
  search.addEventListener('input', filter);
  category.addEventListener('change', filter);
  chips.forEach(chip => chip.addEventListener('click', () => {
    category.value = chip.dataset.catalogChip;
    filter();
  }));
  document.querySelector('[data-catalog-chips]').hidden = false;
  // Keep the native select available as a compact keyboard alternative.
  filter(false);
})();
