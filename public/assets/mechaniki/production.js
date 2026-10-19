(() => {
  'use strict';
  const config = window.MECHANIKI_CONFIG || {};
  const query = (s, root = document) => root.querySelector(s);
  const all = (s, root = document) => [...root.querySelectorAll(s)];
  // Russian phone mask shared by inline and dialog booking forms.
  const phoneNationalDigits = value => {
    let digits=String(value).replace(/\D/g,'');
    if (/^\s*\+7/.test(value) || ((digits[0]==='7'||digits[0]==='8') && digits.length>10) || digits==='7' || digits==='8') digits=digits.slice(1);
    return digits.slice(0,10);
  };
  const formatBookingPhone = digits => {
    if (!digits) return '';
    let value='+7 ('+digits.slice(0,3);
    if(digits.length>=3)value+=') ';
    if(digits.length>3)value+=digits.slice(3,6);
    if(digits.length>=6)value+='-';
    if(digits.length>6)value+=digits.slice(6,8);
    if(digits.length>=8)value+='-';
    if(digits.length>8)value+=digits.slice(8,10);
    return value;
  };
  const installBookingPhoneMask = phone => {
    if(phone.dataset.bookingPhoneMask==='ready')return;
    phone.dataset.bookingPhoneMask='ready';
    phone.type='tel';
    phone.required=true;
    phone.placeholder='+7 (___) ___-__-__';
    phone.maxLength=18;
    phone.inputMode='tel';
    phone.autocomplete='tel';
    phone.pattern='\\+7 \\([0-9]{3}\\) [0-9]{3}-[0-9]{2}-[0-9]{2}';
    const normalize = () => {
      const raw=phone.value,position=phone.selectionStart??raw.length;
      const prefix=/^\s*\+7/.test(raw)?1:((raw.replace(/\D/g,'').length>10&&/^[78]/.test(raw.replace(/\D/g,'')))?1:0);
      const count=Math.max(0,raw.slice(0,position).replace(/\D/g,'').length-prefix);
      const digits=phoneNationalDigits(raw);
      phone.value=formatBookingPhone(digits);
      let caret=phone.value.length,seen=0;
      for(let i=4;i<phone.value.length;i++){
        if(/\d/.test(phone.value[i])&&++seen===count){caret=i+1;while(caret<phone.value.length&&!/\d/.test(phone.value[caret]))caret++;break;}
      }
      if(count===0)caret=Math.min(4,phone.value.length);
      if(document.activeElement===phone)phone.setSelectionRange(caret,caret);
    };
    phone.addEventListener('input',normalize);
    phone.addEventListener('change',normalize);
    phone.addEventListener('paste',event=>{
      const text=event.clipboardData?.getData('text');
      if(typeof text!=='string')return;
      event.preventDefault();
      if(phone.selectionStart===0&&phone.selectionEnd===phone.value.length || !phone.value){phone.value=text;}
      else {
        const start=phone.selectionStart??phone.value.length,end=phone.selectionEnd??start;
        const inserted=/^\s*\+7/.test(text)||text.replace(/\D/g,'').length===11?phoneNationalDigits(text):text.replace(/\D/g,'');
        phone.value=phone.value.slice(0,start)+inserted+phone.value.slice(end);
      }
      phone.setSelectionRange(phone.value.length,phone.value.length);
      phone.dispatchEvent(new Event('input',{bubbles:true}));
    });
    phone.addEventListener('beforeinput',event=>{
      if(!['deleteContentBackward','deleteContentForward'].includes(event.inputType))return;
      const start=phone.selectionStart,end=phone.selectionEnd;
      if(start===null||start!==end)return;
      const backward=event.inputType==='deleteContentBackward';
      let index=backward?start-1:start;
      while(index>=4&&index<phone.value.length&&!/\d/.test(phone.value[index]))index+=backward?-1:1;
      if(index<4||index>=phone.value.length){event.preventDefault();return;}
      event.preventDefault();
      phone.value=phone.value.slice(0,index)+phone.value.slice(index+1);
      phone.setSelectionRange(index,index);
      phone.dispatchEvent(new Event('input',{bubbles:true}));
    });
    normalize();
  };
  all('[data-booking-form] input[name="phone"]').forEach(installBookingPhoneMask);

  const emit = (event, extra = {}) => {
    const experiment = window.MECHANIKI_EXPERIMENT || {};
    const payload = {event, page_path: location.pathname, experiment_id:experiment.id || null, services_variant:experiment.variant || null, experiment_preview:Boolean(experiment.preview), ...extra};
    // Optional analytics must never block booking or change its server-confirmed state.
    try {(window.dataLayer = window.dataLayer || []).push(payload);}catch{ /* Unavailable tag manager. */ }
    try {window.dispatchEvent(new CustomEvent('mechaniki:analytics', {detail: payload}));}catch{ /* Unavailable event consumer. */ }
    try {if (Number.isInteger(config.metrikaId) && typeof window.ym === 'function') window.ym(config.metrikaId, 'reachGoal', event, payload);}catch{ /* Unavailable analytics provider. */ }
  };
  // First-touch campaign/referrer only; source_page always identifies the submitting page.
  const campaignKeys = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term','yclid','gclid'];
  const cleanText = value => typeof value === 'string' ? value.replace(/[\u0000-\u001f\u007f]/g,'').trim().slice(0,250) : '';
  const cleanUrl = value => {
    if(typeof value !== 'string' || value.length > 2048 || /[\u0000-\u001f\u007f]/.test(value))return '';
    try {const url=new URL(value);return ['http:','https:'].includes(url.protocol) && !url.username && !url.password ? url.origin+url.pathname : '';}catch{return '';}
  };
  const params = new URLSearchParams(location.search);
  const sanitizeAttribution = value => ({
    ...Object.fromEntries(campaignKeys.map(key=>[key,cleanText(value[key])])),
    referrer:cleanUrl(value.referrer), landing_page:cleanUrl(value.landing_page), source_page:cleanUrl(location.origin+location.pathname)
  });
  let attribution=sanitizeAttribution({...Object.fromEntries(campaignKeys.map(key=>[key,params.get(key)])),referrer:document.referrer,landing_page:location.origin+location.pathname});
  try {
    const saved=JSON.parse(sessionStorage.getItem('mechaniki-attribution') || 'null');
    if(saved && typeof saved === 'object' && !Array.isArray(saved))attribution=sanitizeAttribution(saved);
  }catch{ /* Malformed/private storage: use current-page attribution. */ }
  // Rewrite legacy storage as well, dropping unknown keys and URL queries/fragments.
  try {sessionStorage.setItem('mechaniki-attribution',JSON.stringify(attribution));}catch{ /* Storage is optional. */ }
  // CMS may add more dropdowns: close every other menu, not only the first.
  const menus = all('.service-menu');
  menus.forEach(menu => menu.addEventListener('toggle', () => {
    if(menu.open)menus.forEach(other => {if(other !== menu)other.open=false;});
  }));
  document.addEventListener('click', e => {
    menus.forEach(menu => {if(!menu.contains(e.target))menu.open=false;});
  });
  document.addEventListener('keydown', e => {
    if(e.key !== 'Escape')return;
    const opened=menus.find(menu => menu.open);
    if(opened){opened.open=false;query('summary',opened)?.focus();}
  });
  const dialog=query('.booking-dialog'); let opener=null;
  all('[data-book]').forEach(button => button.addEventListener('click', () => {emit('booking_click');opener=button;dialog.showModal();}));
  query('.dialog-close').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => opener?.focus());
  dialog.addEventListener('click', e => {if(e.target===dialog){const b=dialog.getBoundingClientRect();if(e.clientX<b.left||e.clientX>b.right||e.clientY<b.top||e.clientY>b.bottom)dialog.close();}});
  document.addEventListener('click', e => {
    const a=e.target.closest('a');if(!a)return;
    let url;try{url=new URL(a.href,location.origin);}catch{return;}
    if(url.protocol==='tel:'){emit('phone_click');return;}
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
  const type=document.body.dataset.pageKind || query('[data-page-kind]')?.dataset.pageKind;
  if(['service','problem','case','price'].includes(type))emit(type+'_view');
  const showBookingOutcome = (form,status,state) => {
    status.dataset.state=state;
    status.tabIndex=-1;
    status.setAttribute('role',state==='error'?'alert':'status');
    if(state==='success') {
      form.classList.add('booking-form--completed');
      [...form.children].forEach(child=>{if(child!==status && !child.classList.contains('booking-fallback'))child.hidden=true;});
    }
    form.prepend(status);
    status.focus({preventScroll:true});
    status.scrollIntoView({block:'nearest',behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
  };
  // Production only. The server must confirm durable acceptance with 2xx + request_id.
  const sameOriginEndpoint = value => {
    try {const url=new URL(value,location.origin);return value && url.origin===location.origin && url.protocol==='https:' ? url.href : null;}catch{return null;}
  };
  const endpoint=sameOriginEndpoint(config.bookingEndpoint), tokenEndpoint=sameOriginEndpoint(config.bookingTokenEndpoint);
  const recaptchaSiteKey=typeof config.recaptchaSiteKey==='string' && /^[A-Za-z0-9_-]{20,256}$/.test(config.recaptchaSiteKey) ? config.recaptchaSiteKey : null;
  const ready=Boolean(document.body.dataset.environment==='production' && endpoint && tokenEndpoint && recaptchaSiteKey && config.consentUrl && config.consentVersion && globalThis.crypto?.randomUUID);
  let tokenPromise=null, pendingBooking=false;
  const bookingKeys=new Map();
  let recaptchaPromise=null;
  const captchaTimeout=(operation)=>new Promise((resolve,reject)=>{
    const timer=setTimeout(()=>reject(new Error('recaptcha_timeout')),8000);
    Promise.resolve(operation).then(resolve,reject).finally(()=>clearTimeout(timer));
  });
  const acquireRecaptcha=(siteKey=recaptchaSiteKey)=>{
    if(!recaptchaPromise)recaptchaPromise=captchaTimeout(new Promise((resolve,reject)=>{
      const ready=()=>window.grecaptcha?.ready?window.grecaptcha.ready(()=>resolve(window.grecaptcha)):reject(new Error('recaptcha_unavailable'));
      if(window.grecaptcha?.ready){ready();return;}
      const script=document.createElement('script');
      script.src='https://www.google.com/recaptcha/api.js?render='+encodeURIComponent(siteKey);
      script.async=true;
      script.onload=ready;
      script.onerror=()=>{script.remove();reject(new Error('recaptcha_unavailable'));};
      document.head.append(script);
    })).catch(error=>{recaptchaPromise=null;throw error;});
    return recaptchaPromise;
  };
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
  // Native reviews form: no jQuery, one fresh token at submission.
  const installNativeReviewForm = () => {
    const form=query('[data-native-reviews]');
    if(!form)return;
    const submit=query('button[type="submit"][name="submit_question"]',form);
    const rating=query('#setreit',form),ratingFill=query('#reit',form),ratingInput=query('#reitInput',form);
    if(rating && ratingFill && ratingInput){
      const setRating=value=>{
        const current=Math.min(5,Math.max(1,Math.round(value)||1));
        ratingInput.value=String(current);ratingFill.style.width=current*20+'%';rating.setAttribute('aria-valuenow',String(current));
      };
      setRating(Number(ratingInput.value));
      rating.addEventListener('click',event=>{const rect=rating.getBoundingClientRect();if(rect.width>0)setRating(1+Math.floor(5*(event.clientX-rect.left)/rect.width));});
      rating.addEventListener('keydown',event=>{
        const changes={ArrowRight:1,ArrowUp:1,ArrowLeft:-1,ArrowDown:-1};
        if(event.key==='Home'||event.key==='End'||event.key in changes){event.preventDefault();setRating(event.key==='Home'?1:event.key==='End'?5:Number(ratingInput.value)+changes[event.key]);}
      });
    }
    if(!submit)return;
    const status=document.createElement('p');status.className='form-status';status.setAttribute('aria-live','polite');form.prepend(status);
    const key=form.dataset.reviewsSiteKey;
    if(typeof key!=='string'||!/^[A-Za-z0-9_-]{20,256}$/.test(key)){
      submit.disabled=true;status.dataset.state='error';status.textContent='Отправка отзывов временно недоступна. Связаться с сервисом можно по телефону в контактах.';return;
    }
    let busy=false,allowNativeSubmit=false;
    const label=submit.textContent;
    form.addEventListener('submit',async event=>{
      if(allowNativeSubmit){allowNativeSubmit=false;return;}
      event.preventDefault();if(busy||!form.reportValidity())return;
      busy=true;submit.disabled=true;form.setAttribute('aria-busy','true');status.dataset.state='loading';status.textContent='Проверяем защиту…';
      try{
        const captcha=await acquireRecaptcha(key);
        const token=await captchaTimeout(captcha.execute(key,{action:'send'}));
        if(typeof token!=='string'||token.length<20)throw new Error('captcha');
        if(!form.reportValidity())throw new Error('validation');
        all('[name="g-recaptcha-response"]',form).forEach(node=>node.remove());
        const input=document.createElement('input');input.type='hidden';input.name='g-recaptcha-response';input.value=token;form.append(input);
        status.textContent='Отправляем отзыв…';allowNativeSubmit=true;submit.disabled=false;
        form.requestSubmit(event.submitter||submit);
      }catch{
        busy=false;allowNativeSubmit=false;submit.disabled=false;submit.textContent=label;form.removeAttribute('aria-busy');status.dataset.state='error';status.textContent='Не удалось отправить отзыв. Проверьте поля и соединение, затем повторите отправку.';
      }
    });
  };
  installNativeReviewForm();
  // End native reviews form.

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
      if(!/^\+7 \([0-9]{3}\) [0-9]{3}-[0-9]{2}-[0-9]{2}$/.test(phone.value.trim())){phone.setCustomValidity('Введите телефон полностью: +7 (999) 123-45-67.');phone.setAttribute('aria-invalid','true');phone.reportValidity();return;}
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
        const grecaptcha=await acquireRecaptcha();
        const recaptchaToken=await captchaTimeout(grecaptcha.execute(recaptchaSiteKey,{action:'booking_submit'}));
        if(typeof recaptchaToken!=='string'||recaptchaToken.length<20)throw new Error('recaptcha_unavailable');
        const control=new AbortController();timer=setTimeout(()=>control.abort(),15000);
        const response=await fetch(endpoint,{method:'POST',credentials:'same-origin',redirect:'error',headers:{'Content-Type':'application/json','Idempotency-Key':requestKey},body:JSON.stringify({...payload,token:challenge.value,recaptcha_token:recaptchaToken}),signal:control.signal});
        const data=await response.json().catch(()=>null);
        if(!response.ok || data?.ok!==true || typeof data.request_id!=='string' || !/^[A-Za-z0-9][A-Za-z0-9_-]{7,127}$/.test(data.request_id)){
          const error=new Error('not_confirmed');error.status=response.status;error.code=data?.error?.code;throw error;
        }
        status.replaceChildren();const heading=document.createElement('strong');heading.textContent='Заявка получена';const message=document.createElement('span');message.textContent='Свяжемся с вами для уточнения автомобиля, проблемы и удобного времени.';status.append(heading,message);showBookingOutcome(form,status,'success');emit('booking_success');form.reset();finished=true;submit.textContent='Заявка получена';
      } catch(error) {
        const reason=error.code==='pending'?'Результат предыдущей отправки ещё не подтверждён. Свяжитесь с сервисом напрямую, чтобы избежать повторной заявки.':error.code==='disabled'?'Онлайн-запись пока недоступна. Свяжитесь с сервисом напрямую.':error.code==='recaptcha'||String(error.message).startsWith('recaptcha_')?'Не удалось проверить отправку. Повторите попытку.':error.status===429?'Слишком много попыток. Подождите и повторите позже.':error.status===400||error.status===422?'Проверьте данные формы и повторите отправку.':'Не удалось подтвердить получение заявки. Можно повторить попытку или связаться напрямую.';
        status.textContent=reason+' Телефон: +7 (926) 815-09-24. WhatsApp доступен под формой.';showBookingOutcome(form,status,'error');submit.disabled=error.code==='pending'||error.code==='disabled';submit.textContent=submit.disabled?'Свяжитесь с сервисом':'Повторить отправку';tokenPromise=null;emit('booking_error',{status:error.status||0});
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

/* Entrance enhancement only. No element is hidden while awaiting JavaScript. */
(() => {
  'use strict';
  const boot = () => {
    const media = typeof window.matchMedia === 'function'
      ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    const active = new Set();
    const seen = new WeakSet();
    let observer = null;
    let entrancesStopped = Boolean(media && media.matches);
    const reduce = () => Boolean(media && media.matches);
    const clear = node => {
      node.removeAttribute('data-motion-active');
      active.delete(node);
    };
    const enter = (node, type = 'reveal', index = 0) => {
      if (!node || reduce() || node.hidden || node.contains(document.activeElement)) return;
      node.dataset.motion = type;
      node.dataset.motionDelay = String(Math.min(Math.max(index, 0), 3) * 80);
      node.dataset.motionActive = 'true';
      active.add(node);
    };
    // CSS animations finish independently even if any subsequent script fails.
    document.addEventListener('animationend', event => {
      if (active.has(event.target) && event.animationName.startsWith('mechaniki-')) clear(event.target);
    });
    document.addEventListener('animationcancel', event => {
      if (active.has(event.target) && event.animationName.startsWith('mechaniki-')) clear(event.target);
    });
    const stopEntrances = () => {
      entrancesStopped = true;
      if (observer) observer.disconnect();
      for (const node of active) clear(node);
    };
    const preferenceChanged = () => {
      // Do not replay entrances after someone changes their accessibility setting.
      if (reduce()) stopEntrances();
    };
    if (media && typeof media.addEventListener === 'function') media.addEventListener('change', preferenceChanged);
    else if (media && typeof media.addListener === 'function') media.addListener(preferenceChanged);

    const hero = document.querySelector('#main > .hero, #main > .page-hero');
    if (!entrancesStopped && hero) {
      const copy = hero.querySelector('.hero__copy, .page-hero-copy');
      if (copy) {
        const parts = Array.from(copy.children).filter(node => node.matches('.eyebrow, h1, .hero__lead, .lead, .button-row'));
        parts.forEach((node, index) => enter(node, 'reveal', index));
      }
      enter(hero.querySelector('.hero__photo img, .ip-photo img'), 'photo');
    }

    const selector = [
      '#main .section-heading', '#main .section-title',
      '#main .services-grid > .service-card', '#main .ip-service-grid > .ip-service-card',
      '#main .symptoms > .symptom', '#main .ip-symptom-grid > .ip-symptom',
      '#main .process-list > li', '#main .ip-process > li',
      '#main .review-quotes > figure', '#main .verified-review', '#main .case-grid > .case-card'
    ].join(', ');
    if (!entrancesStopped && typeof window.IntersectionObserver === 'function') {
      try {
        const positions = new WeakMap();
        const groupCounts = new WeakMap();
        const candidates = Array.from(document.querySelectorAll(selector));
        for (const node of candidates) {
          const index = groupCounts.get(node.parentElement) || 0;
          positions.set(node, index);
          groupCounts.set(node.parentElement, index + 1);
        }
        observer = new window.IntersectionObserver(entries => {
          for (const entry of entries) {
            if (!entry.isIntersecting || seen.has(entry.target)) continue;
            seen.add(entry.target);
            observer.unobserve(entry.target);
            if (!entrancesStopped) enter(entry.target, 'reveal', positions.get(entry.target) || 0);
          }
        }, { threshold:0.08, rootMargin:'0px 0px -16px 0px' });
        candidates.forEach(node => observer.observe(node));
        // Cache refresh replaces review nodes after the initial page scan.
        document.addEventListener('mechaniki:reviews-updated', event => {
          const { previous = [], current = [] } = event.detail || {};
          previous.forEach(node => { observer.unobserve(node); clear(node); });
          if (entrancesStopped) return;
          current.forEach((node, index) => {
            positions.set(node, index);
            if (!seen.has(node)) observer.observe(node);
          });
        });
      } catch {
        // Missing or unavailable observer must never affect readability.
        if (observer) observer.disconnect();
      }
    }
    document.querySelectorAll('details.service-menu').forEach(details => {
      const menu = details.querySelector('.mega-menu');
      if (!menu) return;
      details.addEventListener('toggle', () => {
        if (details.open) enter(menu, 'menu');
        else clear(menu);
      });
    });
    // BFCache restoration keeps the already-read page still.
    window.addEventListener('pageshow', event => { if (event.persisted) stopEntrances(); });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once:true });
  else boot();
})();

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