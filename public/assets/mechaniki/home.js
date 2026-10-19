(() => {
  const menu = document.querySelector('#main-menu');
  const toggle = document.querySelector('.menu-button');
  const dialog = document.querySelector('.booking-dialog');
  let opener = null;
  const closeMenu = () => { menu.classList.remove('is-open'); toggle.setAttribute('aria-expanded','false'); toggle.setAttribute('aria-label','Открыть меню'); };
  toggle.addEventListener('click', () => { const open = toggle.getAttribute('aria-expanded') !== 'true'; menu.classList.toggle('is-open',open); toggle.setAttribute('aria-expanded',String(open)); toggle.setAttribute('aria-label',open?'Закрыть меню':'Открыть меню'); });
  menu.addEventListener('click', event => { if(event.target.closest('a')) closeMenu(); });
  document.querySelectorAll('[data-book]').forEach(button => button.addEventListener('click', () => {opener=button; dialog.showModal();}));
  document.querySelector('.dialog-close').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', event => { if(event.target===dialog){const box=dialog.getBoundingClientRect();if(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom)dialog.close();} });
  dialog.addEventListener('close', () => { if(opener) opener.focus(); });
  document.addEventListener('keydown', event => { if(event.key==='Escape') closeMenu(); });
})();
