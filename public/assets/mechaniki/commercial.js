(() => {
  'use strict';
  const config = window.MECHANIKI_CONFIG || {};
  const query = (s, root = document) => root.querySelector(s);
  const all = (s, root = document) => [...root.querySelectorAll(s)];
  const emit = (event, extra = {}) => {
    const experiment = window.MECHANIKI_EXPERIMENT || {};
    const payload = {event, page_path: location.pathname, experiment_id:experiment.id || null, services_variant:experiment.variant || null, experiment_preview:Boolean(experiment.preview), ...extra};
    // Optional analytics must never block booking or change its server-confirmed state.
    try {(window.dataLayer = window.dataLayer || []).push(payload);}catch{ /* Unavailable tag manager. */ }
    try {window.dispatchEvent(new CustomEvent('mechaniki:analytics', {detail: payload}));}catch{ /* Unavailable event consumer. */ }
    try {if (Number.isInteger(config.metrikaId) && typeof window.ym === 'function') window.ym(config.metrikaId, 'reachGoal', event, payload);}catch{ /* Unavailable analytics provider. */ }
  };
  // First-touch campaign/referrer only; source_page always identifies the submitting page.
  const campaignKeys = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term'];
  const cleanText = value => typeof value === 'string' ? value.replace(/[\u0000-\u001f\u007f]/g,'').trim().slice(0,250) : '';
  const cleanUrl = value => {
    if(typeof value !== 'string' || value.length > 2048 || /[\u0000-\u001f\u007f]/.test(value))return '';
    try {const url=new URL(value);return ['http:','https:'].includes(url.protocol) && !url.username && !url.password ? url.origin+url.pathname : '';}catch{return '';}
  };
  const params = new URLSearchParams(location.search);
  const sanitizeAttribution = value => ({
    ...Object.fromEntries(campaignKeys.map(key=>[key,cleanText(value[key])])),
    referrer:cleanUrl(value.referrer), source_page:cleanUrl(location.origin+location.pathname)
  });
  let attribution=sanitizeAttribution({...Object.fromEntries(campaignKeys.map(key=>[key,params.get(key)])),referrer:document.referrer});
  try {
    const saved=JSON.parse(sessionStorage.getItem('mechaniki-attribution') || 'null');
    if(saved && typeof saved === 'object' && !Array.isArray(saved))attribution=sanitizeAttribution(saved);
  }catch{ /* Malformed/private storage: use current-page attribution. */ }
  // Rewrite legacy storage as well, dropping unknown keys and URL queries/fragments.
  try {sessionStorage.setItem('mechaniki-attribution',JSON.stringify(attribution));}catch{ /* Storage is optional. */ }
  const menu = query('#main-menu'), toggle = query('.menu-button'), mega = query('.service-menu');
  document.addEventListener('click', e => {if(mega && !mega.contains(e.target))mega.open=false;});
  document.addEventListener('keydown', e => {if(e.key==='Escape'){if(mega?.open){mega.open=false;query('summary',mega).focus();}}});
  const dialog=query('.booking-dialog'); let opener=null;
  all('[data-book]').forEach(button => button.addEventListener('click', () => {emit('click_booking');opener=button;dialog.showModal();}));
  query('.dialog-close').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => opener?.focus());
  dialog.addEventListener('click', e => {if(e.target===dialog){const b=dialog.getBoundingClientRect();if(e.clientX<b.left||e.clientX>b.right||e.clientY<b.top||e.clientY>b.bottom)dialog.close();}});
  document.addEventListener('click', e => {
    const a=e.target.closest('a');if(!a)return;
    let url;try{url=new URL(a.href,location.origin);}catch{return;}
    if(url.protocol==='tel:'){emit('click_phone');return;}
    if(url.protocol==='https:' && ['wa.me','api.whatsapp.com','web.whatsapp.com'].includes(url.hostname)){emit('click_whatsapp');return;}
    if(a.dataset.route){emit('route_click',{provider:['2gis','navigator','yandex'].includes(a.dataset.route)?a.dataset.route:'other'});return;}
    if(url.origin===location.origin){
      if(/^\/services\/[^/]+/.test(url.pathname))emit('service_click',{service_path:url.pathname});
      if(/^\/problems\/[^/]+/.test(url.pathname))emit('problem_click',{problem_path:url.pathname});
      if(url.pathname==='/reviews/' || url.hash==='#reviews')emit('review_click',{provider:'local'});
    }
    // Match our verified organization only, never arbitrary Yandex URLs or link text.
    if(url.protocol==='https:' && url.hostname==='yandex.ru' && !url.username && !url.password && (/^\/maps\/org\/mekhaniki\/4943088976\/(?:reviews\/)?$/.test(url.pathname) || url.pathname==='/profile/4943088976')){
      if(url.pathname.endsWith('/reviews/') || url.searchParams.get('tab')==='reviews')emit('review_click',{provider:'yandex'});
      else emit('route_click',{provider:'yandex'});
    }else if(url.protocol==='https:' && url.hostname==='yandex.ru' && url.pathname==='/maps/')emit('route_click',{provider:'yandex'});
  });
  document.addEventListener('mechaniki:price-search',e=>{
    const {count,category,query_length}=e.detail || {};
    if(!Number.isInteger(count) || count<0 || !Number.isInteger(query_length) || query_length<0)return;
    emit('price_search',{count,category:all('[data-catalog-row]').some(row=>row.dataset.category===category)?category:'',query_length});
  });
  const type=document.body.dataset.pageKind;
  if(['service','problem','case','price'].includes(type))emit(type+'_view');
  // Production only. The server must confirm durable acceptance with 2xx + request_id.
  const sameOriginEndpoint = value => {
    try {const url=new URL(value,location.origin);return value && url.origin===location.origin && url.protocol==='https:' ? url.href : null;}catch{return null;}
  };
  const endpoint=sameOriginEndpoint(config.bookingEndpoint), tokenEndpoint=sameOriginEndpoint(config.bookingTokenEndpoint);
  const ready=Boolean(document.body.dataset.environment==='production' && endpoint && tokenEndpoint && config.consentUrl && config.consentVersion && globalThis.crypto?.randomUUID);
  let tokenPromise=null, pendingBooking=false;
  const bookingKeys=new Map();
  const acquireToken=()=>{
    if(!tokenPromise)tokenPromise=(async()=>{
      const control=new AbortController(),timer=setTimeout(()=>control.abort(),5000);
      try {
        const response=await fetch(tokenEndpoint,{credentials:'same-origin',redirect:'error',cache:'no-store',signal:control.signal});
        const data=await response.json();
        if(!response.ok || data.ok!==true || typeof data.token!=='string' || data.token.length<20)throw new Error('token');
        return {value:data.token,received:Date.now()};
      }finally{clearTimeout(timer);}
    })().catch(error=>{tokenPromise=null;throw error;});
    return tokenPromise;
  };
  all('[data-booking-form]').forEach(form => {
    const submit=query('[type=submit]',form),status=query('.form-status',form),phone=query('[name=phone]',form),consent=query('[name=consent]',form);
    let requestKey=null,payloadIdentity=null,finished=false;
    if(ready){
      const link=document.createElement('a');link.href=config.consentUrl;link.textContent='Согласие на обработку данных';link.target='_blank';link.rel='noopener';query('[data-consent-link]',form).append(' · ',link);
      submit.disabled=false;status.textContent='Свяжемся для уточнения автомобиля, проблемы и удобного времени.';
      form.addEventListener('focusin',()=>{acquireToken().catch(()=>{});},{once:true});
    }
    phone.addEventListener('input',()=>{phone.setCustomValidity('');phone.removeAttribute('aria-invalid');});
    consent.addEventListener('change',()=>{consent.setCustomValidity('');consent.removeAttribute('aria-invalid');});
    form.addEventListener('submit',async e=>{
      e.preventDefault();if(!ready||submit.disabled||pendingBooking||finished)return;
      const digits=phone.value.replace(/\D/g,'');
      if(!/^\+?[0-9 ().-]+$/.test(phone.value.trim())||digits.length<10||digits.length>15){phone.setCustomValidity('Введите телефон: от 10 до 15 цифр.');phone.setAttribute('aria-invalid','true');phone.reportValidity();return;}
      if(!consent.checked){consent.setCustomValidity('Подтвердите согласие на обработку данных.');consent.setAttribute('aria-invalid','true');consent.reportValidity();return;}
      if(!form.reportValidity())return;
      const values=new FormData(form);
      const payload={phone:phone.value.trim(),car:String(values.get('car')||'').slice(0,120),problem:String(values.get('problem')||'').slice(0,2000),consent:true,consent_version:config.consentVersion,attribution:{...attribution,source_page:cleanUrl(location.origin+location.pathname)},website:String(values.get('website')||'')};
      const identity=JSON.stringify(payload);
      if(identity!==payloadIdentity){requestKey=bookingKeys.get(identity)||crypto.randomUUID();bookingKeys.set(identity,requestKey);payloadIdentity=identity;}
      pendingBooking=true;submit.disabled=true;submit.textContent='Отправляем…';form.setAttribute('aria-busy','true');status.textContent='Отправляем заявку…';status.dataset.state='loading';emit('booking_submit');
      let timer;
      try {
        let challenge=await acquireToken();
        if(Date.now()-challenge.received>25*60*1000){tokenPromise=null;challenge=await acquireToken();}
        const wait=Math.max(0,2200-(Date.now()-challenge.received));
        if(wait)await new Promise(resolve=>setTimeout(resolve,wait));
        const control=new AbortController();timer=setTimeout(()=>control.abort(),15000);
        const response=await fetch(endpoint,{method:'POST',credentials:'same-origin',redirect:'error',headers:{'Content-Type':'application/json','Idempotency-Key':requestKey},body:JSON.stringify({...payload,token:challenge.value}),signal:control.signal});
        const data=await response.json().catch(()=>null);
        if(!response.ok || data?.ok!==true || typeof data.request_id!=='string' || !/^[A-Za-z0-9][A-Za-z0-9_-]{7,127}$/.test(data.request_id)){
          const error=new Error('not_confirmed');error.status=response.status;throw error;
        }
        status.replaceChildren();const heading=document.createElement('strong');heading.textContent='Заявка получена';const message=document.createElement('span');message.textContent='Свяжемся с вами для уточнения автомобиля, проблемы и удобного времени.';status.append(heading,message);status.dataset.state='success';emit('booking_success');form.reset();finished=true;submit.textContent='Заявка получена';
      } catch(error) {
        const reason=error.status===429?'Слишком много попыток. Подождите и повторите позже.':error.status===400||error.status===422?'Проверьте данные формы и повторите отправку.':'Не удалось подтвердить получение заявки. Можно повторить попытку или связаться напрямую.';
        status.textContent=reason+' Телефон: +7 (926) 815-09-24. WhatsApp доступен под формой.';status.dataset.state='error';submit.disabled=false;submit.textContent='Повторить отправку';tokenPromise=null;emit('booking_error',{status:error.status||0});
      } finally {if(timer)clearTimeout(timer);pendingBooking=false;form.removeAttribute('aria-busy');}
    });
  });
  const services = query('#services');
  if (services && window.MECHANIKI_EXPERIMENT) {
    let exposed = false;
    const expose = () => { if(!exposed){exposed=true;emit(window.MECHANIKI_EXPERIMENT.active === false ? 'services_view' : 'services_experiment_view');} };
    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver(entries => {
        if(entries.some(entry => entry.isIntersecting)){expose();observer.disconnect();}
      }, {threshold:0.1});
      observer.observe(services);
    }
    services.addEventListener('click', e => {
      const card=e.target.closest('.service-card');
      if(card)expose();
    });
  }
  const priceSearch=query('[data-price-search]'), priceCategory=query('[data-price-category]');
  let priceSearchTimer;
  const filterPrices=()=>{
    const term=priceSearch.value.trim().toLocaleLowerCase('ru'),cat=priceCategory.value;let count=0;
    all('[data-price-row]').forEach(row=>{row.hidden=!(row.textContent.toLocaleLowerCase('ru').includes(term)&&(!cat||row.dataset.category===cat));if(!row.hidden)count++;});query('[data-price-empty]').hidden=count>0;
    clearTimeout(priceSearchTimer);
    priceSearchTimer=setTimeout(()=>emit('price_search',{count,category:all('[data-price-row]').some(row=>row.dataset.category===cat)?cat:'',query_length:priceSearch.value.trim().length}),300);
  };
  priceSearch?.addEventListener('input',filterPrices);priceCategory?.addEventListener('change',filterPrices);
  all('[data-case-filter]').forEach(select=>select.addEventListener('change',()=>{
    const filters=all('[data-case-filter]');let count=0;
    all('[data-case]').forEach(card=>{card.hidden=!filters.every(f=>!f.value||card.dataset[f.dataset.caseFilter]===f.value);if(!card.hidden)count++;});query('[data-cases-empty]').hidden=count>0;
  }));
})();

