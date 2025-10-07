(function () {
  'use strict';

  // Shortcuts
  const $  = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  // Config from data-* on <body>
  const BODY            = document.body;
  const LOCK_REMAINING  = parseInt(BODY.dataset.lockRemaining || '0', 10);
  const HAS_ERROR       = BODY.dataset.hasError === '1';
  const ERROR_MESSAGE   = BODY.dataset.error || '';

  function smoothTop() { window.scrollTo({ top: 0, behavior: 'smooth' }); }

  function togglePassword() {
    const passInput = $('#password');
    const icon = $('#toggleIcon');
    if (!passInput || !icon) return;
    const isHidden = passInput.type === 'password';
    passInput.type = isHidden ? 'text' : 'password';
    icon.classList.toggle('bi-eye', isHidden);
    icon.classList.toggle('bi-eye-slash', !isHidden);
  }

  function showImmediateErrors() {
    if (!HAS_ERROR || !ERROR_MESSAGE) return;
    if (typeof Swal !== 'undefined') {
      Swal.fire({ icon:'error', title:'Login Failed', text: ERROR_MESSAGE, confirmButtonText:'OK' });
    } else {
      alert(ERROR_MESSAGE);
    }
  }

  function confirmLoginFlow() {
    const form = $('form.needs-validation');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      const username = ($('#id')?.value || '').trim();
      const password = ($('#password')?.value || '').trim();
      e.preventDefault();
      if (!username || !password) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({ icon:'warning', title:'Missing Input', text:'Please enter both Username and Password.' });
        } else {
          alert('Please enter both Username and Password.');
        }
        return;
      }
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title:'Confirm Login',
          text:'Are you sure you want to log in?',
          icon:'question',
          showCancelButton:true,
          confirmButtonColor:'#0d6efd',
          cancelButtonColor:'#aaa',
          confirmButtonText:'Yes, log me in'
        }).then((r)=> {
          if (r.isConfirmed) {
            Swal.fire({ title:'Signing in...', allowOutsideClick:false, allowEscapeKey:false, didOpen:()=> Swal.showLoading() });
            setTimeout(()=> form.submit(), 600);
          }
        });
      } else {
        if (confirm('Log in?')) form.submit();
      }
    });
  }

  function lockoutCountdown() {
    if (LOCK_REMAINING <= 0 || typeof Swal === 'undefined') return;
    const loginBtn = $('#loginBtn');
    if (loginBtn) loginBtn.disabled = true;

    let left = LOCK_REMAINING;
    const fmt = s => `${Math.floor(s/60)}:${String(s%60).padStart(2,'0')}`;

    Swal.fire({
      icon:'warning',
      title:'Too Many Attempts',
      html:`Please wait <b id="lockCountdown">${fmt(left)}</b> before trying again.`,
      allowOutsideClick:false,
      allowEscapeKey:false,
      showConfirmButton:false,
      didOpen:() => {
        const el = $('#lockCountdown');
        const t = setInterval(()=> {
          left--;
          if (el) el.textContent = fmt(Math.max(left, 0));
          if (left <= 0) {
            clearInterval(t);
            Swal.close();
            if (loginBtn) loginBtn.disabled = false;
          }
        }, 1000);
      }
    });
  }

  function revealOnScroll() {
    const els = $$('[data-reveal]');
    if (!('IntersectionObserver' in window) || els.length === 0) {
      els.forEach(el => el.classList.add('is-revealed'));
      return;
    }
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('is-revealed');
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.2 });
    els.forEach(el => io.observe(el));
  }

  function countUpOnReveal() {
    const counters = $$('[data-count]');
    if (counters.length === 0) return;

    const run = el => {
      const target = +el.dataset.count || 0;
      const t0 = performance.now();
      const dur = 1200;
      const step = t => {
        const p = Math.min(1, (t - t0) / dur);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = new Intl.NumberFormat().format(Math.floor(eased * target));
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    };

    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          run(e.target);
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.5 });
    counters.forEach(el => io.observe(el));
  }

  function stickyNavAndBackToTop() {
    const nav = $('.navbar');
    const topBtn = $('#backToTop');
    if (!nav || !topBtn) return;

    document.addEventListener('scroll', () => {
      nav.classList.toggle('stuck', window.scrollY > 12);
      topBtn.classList.toggle('show', window.scrollY > 300);
    });

    topBtn.addEventListener('click', (e) => { e.preventDefault(); smoothTop(); });
    const topLink = $('#backToTopLink');
    if (topLink) topLink.addEventListener('click', (e) => { e.preventDefault(); smoothTop(); });
  }

  function activeNavLink() {
    const navLinks = $$('.navbar .nav-link');
    const targets  = $$('header[id], section[id]');
    const setActiveById = id => navLinks.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + id));

    if (location.hash) {
      const id = location.hash.slice(1);
      if (document.getElementById(id)) setActiveById(id);
    } else if (targets.length) {
      setActiveById(targets[0].id);
    }

    navLinks.forEach(a => {
      a.addEventListener('click', () => {
        const href = a.getAttribute('href') || '';
        if (href.startsWith('#')) setActiveById(href.slice(1));
        const bsCollapseEl = $('#navbarNav');
        if (bsCollapseEl && bsCollapseEl.classList.contains('show')) {
          bootstrap.Collapse.getOrCreateInstance(bsCollapseEl).hide();
        }
      });
    });

    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver((entries) => {
        const visible = entries.filter(e => e.isIntersecting)
                               .sort((a,b) => b.intersectionRatio - a.intersectionRatio)[0];
        if (visible) setActiveById(visible.target.id);
      }, {
        root: null,
        rootMargin: "-72px 0px -60% 0px",
        threshold: [0.25, 0.5, 0.75]
      });
      targets.forEach(t => observer.observe(t));
    }

    window.addEventListener('hashchange', () => {
      const id = location.hash.replace("#", "");
      if (document.getElementById(id)) setActiveById(id);
    });
  }

  function trackNavHeightVar() {
    const setVar = () => {
      const nav = $('#mainNav');
      if (!nav) return;
      const h = nav.offsetHeight || 56;
      document.documentElement.style.setProperty('--nav-h', `${h}px`);
    };
    window.addEventListener('load', setVar);
    window.addEventListener('resize', setVar);
    document.addEventListener('shown.bs.collapse', setVar);
    document.addEventListener('hidden.bs.collapse', setVar);
  }

  function parallaxBlobs() {
    const b1 = $('.blob.b1');
    const b2 = $('.blob.b2');
    if (!b1 && !b2) return;

    const lerp = (a,b,t)=>a+(b-a)*t;
    let tx1=0, ty1=0, tx2=0, ty2=0, cx=0, cy=0;

    window.addEventListener('mousemove', (e)=> {
      cx = (e.clientX / window.innerWidth) - .5;
      cy = (e.clientY / window.innerHeight) - .5;
    });

    function loop(){
      tx1 = lerp(tx1,  cx * 14, .06); ty1 = lerp(ty1,  cy * 10, .06);
      tx2 = lerp(tx2, -cx * 16, .06); ty2 = lerp(ty2, -cy * 12, .06);
      if (b1) b1.style.transform = `translate(${tx1}px, ${ty1}px)`;
      if (b2) b2.style.transform = `translate(${tx2}px, ${ty2}px)`;
      requestAnimationFrame(loop);
    }
    loop();
  }

  function intentAwareRedirect() {
    const redirectField = $('#redirect_to');
    if (!redirectField) return;

    $$('[data-intent="book-now"]').forEach(btn => {
      btn.addEventListener('click', () => { redirectField.value = 'schedule_appointment'; });
    });
    $$('[data-intent="generic-login"]').forEach(btn => {
      btn.addEventListener('click', () => { redirectField.value = ''; });
    });
  }

  // Init
  document.addEventListener('DOMContentLoaded', () => {
    // Immediate error (from server) via data-* attributes
    showImmediateErrors();

    // Buttons/flows
    $('#togglePassBtn')?.addEventListener('click', togglePassword);
    confirmLoginFlow();
    lockoutCountdown();
    intentAwareRedirect();

    // UI behaviors
    stickyNavAndBackToTop();
    revealOnScroll();
    countUpOnReveal();
    activeNavLink();
    trackNavHeightVar();
    parallaxBlobs();
  });
})();
