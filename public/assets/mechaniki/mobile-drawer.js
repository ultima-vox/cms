(() => {
  const nav=document.querySelector('#main-menu');
  const trigger=document.querySelector('.menu-button');
  const drawer=document.querySelector('.mobile-drawer');
  const slot=drawer.querySelector('.drawer-navigation');
  const close=drawer.querySelector('.drawer-close');
  const originalParent=nav.parentNode, next=nav.nextSibling;
  const mobile=matchMedia('(max-width: 900px)');
  let timer;
  const reset=()=>{clearTimeout(timer);drawer.classList.remove('is-closing');document.documentElement.classList.remove('drawer-active');trigger.setAttribute('aria-expanded','false');};
  const hide=(immediate=false)=>{
    if(!drawer.open)return;
    clearTimeout(timer);
    if(immediate||matchMedia('(prefers-reduced-motion: reduce)').matches){drawer.close();return;}
    drawer.classList.add('is-closing');timer=setTimeout(()=>drawer.close(),180);
  };
  const sync=()=>{
    hide(true);reset();
    if(mobile.matches){slot.append(nav);trigger.setAttribute('aria-controls','mobile-navigation');}
    else{originalParent.insertBefore(nav,next);trigger.setAttribute('aria-controls','main-menu');}
  };
  drawer.id='mobile-navigation';trigger.setAttribute('aria-haspopup','dialog');
  trigger.addEventListener('click',()=>{if(!mobile.matches)return;clearTimeout(timer);drawer.classList.remove('is-closing');drawer.showModal();document.documentElement.classList.add('drawer-active');trigger.setAttribute('aria-expanded','true');close.focus();});
  close.addEventListener('click',()=>hide());
  drawer.addEventListener('cancel',e=>{e.preventDefault();hide();});
  drawer.addEventListener('close',()=>{reset();if(mobile.matches)trigger.focus();});
  drawer.addEventListener('click',e=>{if(e.target===drawer)hide();});
  nav.addEventListener('click',e=>{if(e.target.closest('a'))hide(true);});
  mobile.addEventListener('change',sync);sync();
})();
