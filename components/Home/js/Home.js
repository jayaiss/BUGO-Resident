
(() => {
  const acc = document.getElementById('faqAccordion');
  if (!acc || !window.bootstrap) return;

  // Ensure each panel has its own instance (no auto toggle)
  acc.querySelectorAll('.accordion-collapse').forEach(panel => {
    bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false, parent: '#faqAccordion' });
  });

  // Our one and only click handler
  acc.querySelectorAll('.accordion-button').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault(); // block any other handler
      const sel = btn.getAttribute('data-bs-target');
      const panel = sel && document.querySelector(sel);
      if (!panel) return;

      const inst = bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false, parent: '#faqAccordion' });

      // If already shown, hide; else show (and Bootstrap will auto-close siblings due to parent)
      if (panel.classList.contains('show')) {
        inst.hide();
      } else {
        inst.show();
      }
    }, { passive: false });
  });

  // Keep aria/classes in sync (visual state)
  acc.addEventListener('shown.bs.collapse', (e) => {
    const btn = e.target.previousElementSibling?.querySelector('.accordion-button');
    if (btn) { btn.classList.remove('collapsed'); btn.setAttribute('aria-expanded', 'true'); }
  });
  acc.addEventListener('hidden.bs.collapse', (e) => {
    const btn = e.target.previousElementSibling?.querySelector('.accordion-button');
    if (btn) { btn.classList.add('collapsed'); btn.setAttribute('aria-expanded', 'false'); }
  });
})();

  // Reveal-on-scroll
  (() => {
    const prefersReduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const items = document.querySelectorAll('.reveal');
    if (prefersReduced) { items.forEach(el => el.classList.add('show')); return; }
    const io = new IntersectionObserver((entries, obs) => {
      entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('show'); obs.unobserve(e.target); } });
    }, { threshold: .14 });
    items.forEach(el => io.observe(el));
  })();

  // Count-up
  (() => {
    const prefersReduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const ease = t => 1 - Math.pow(1 - t, 3);
    document.querySelectorAll('.stat-card h2').forEach(node => {
      if (node.dataset.counted === '1') return;
      const isPercent = (node.textContent || '').includes('%');
      const rawAttr = node.getAttribute('data-target-value');
      const target = parseInt((rawAttr ?? (node.textContent || '').replace(/[^\d]/g,'')) || '0', 10);
      if (!Number.isFinite(target) || target <= 0) return;
      node.dataset.counted = '1';
      if (prefersReduced) { node.textContent = isPercent ? `${target}%` : target.toLocaleString(); return; }
      let start = null, dur = 900;
      function step(ts){ if(!start) start=ts; const p=Math.min((ts-start)/dur,1);
        const v=Math.floor(target*(1-Math.pow(1-p,3))); node.textContent=isPercent?`${v}%`:v.toLocaleString();
        if(p<1) requestAnimationFrame(step);
      }
      node.textContent = isPercent ? '0%' : '0';
      requestAnimationFrame(step);
    });
  })();

  // News scroller
  (() => {
    const scroll = document.getElementById('newsScroll');
    const prev = document.getElementById('prevNews');
    const next = document.getElementById('nextNews');
    if (!scroll || !prev || !next) return;
    const SCROLL_AMOUNT = 380;
    const setDis = (b,s)=>{ b.disabled=s; b.setAttribute('aria-disabled', String(s)); };
    const update = ()=>{ const max = scroll.scrollWidth - scroll.clientWidth - 2;
      setDis(prev, scroll.scrollLeft <= 0); setDis(next, scroll.scrollLeft >= max); };
    const throttle = (fn,w=100)=>{ let t=0; return (...a)=>{ const n=Date.now(); if(n-t>=w){ t=n; fn(...a);} }; };
    prev.addEventListener('click', ()=>scroll.scrollBy({left:-SCROLL_AMOUNT,behavior:'smooth'}));
    next.addEventListener('click', ()=>scroll.scrollBy({left: SCROLL_AMOUNT,behavior:'smooth'}));
    scroll.addEventListener('scroll', throttle(update,80), { passive:true });
    window.addEventListener('resize', throttle(update,120));
    update();
  })();

  // Carousel
  (() => {
    const el = document.getElementById('officialsCarousel');
    if (!el || !window.bootstrap) return;
    new bootstrap.Carousel(el, { interval: 5000, pause: 'hover', ride: false, touch: true, wrap: true });
  })();
// Copy details
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    const sel = btn.getAttribute('data-copy');
    const src = sel && document.querySelector(sel);
    if (!src) return;
    try {
      await navigator.clipboard.writeText(src.textContent.trim());
      btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Copied!';
      setTimeout(() => btn.innerHTML = '<i class="bi bi-clipboard-check me-1"></i> Copy details', 1400);
    } catch { /* ignore */ }
  });

  // Share (uses Web Share API if available)
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-share]');
    if (!btn || !navigator.share) return;
    const title = btn.getAttribute('data-title') || 'Event';
    const text  = btn.getAttribute('data-text')  || '';
    try { await navigator.share({ title, text }); } catch { /* user cancelled */ }
  });
