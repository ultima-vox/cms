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
