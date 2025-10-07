// ================================
// 🔄 Reusable debounce utility
// ================================
function debounce(fn, delay = 1000) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(null, args), delay); };
}

// ================================
// 📅 Appointments Filtering & Copy
// ================================
(() => {
  const q  = document.getElementById('search');
  const fS = document.getElementById('filterStatus');
  const fC = document.getElementById('filterCert');

  // ——— hydrate inputs from URL on load
  const url = new URL(window.location.href);
  const params = url.searchParams;

  if (q)  q.value  = params.get('q')      ?? '';
  if (fS) fS.value = params.get('status') ?? '';
  if (fC) fC.value = params.get('cert')   ?? '';

  // ——— navigate with current filters (server-side filtering)
  const navigateWithFilters = () => {
    const qv  = (q?.value || '').trim();
    const sv  = (fS?.value || '').trim();
    const cv  = (fC?.value || '').trim();

    const next = new URL(window.location.href);
    const p = next.searchParams;

    // Set or clear params
    qv ? p.set('q', qv) : p.delete('q');
    sv ? p.set('status', sv) : p.delete('status');
    cv ? p.set('cert', cv) : p.delete('cert');

    // If any filter is active, show ALL matches and reset pagination
    if (qv || sv || cv) {
      p.set('showall', '1');
      p.delete('pagenum');
    } else {
      p.delete('showall');
    }

    // Only navigate if something actually changed
    if (next.toString() !== window.location.href) {
      window.location.assign(next.toString());
    }
  };

  const debouncedGo = debounce(navigateWithFilters, 450);

  // ——— inputs: reload (debounced) to fetch filtered data across pages
  [q, fS, fC].forEach(el => {
    if (!el) return;
    const evt = el.tagName === 'SELECT' ? 'change' : 'input';
    el.addEventListener(evt, debouncedGo);
  });

  // ================================
  // 📋 Copy tracking/certificate/status (single handler)
  // ================================
  document.addEventListener('click', e => {
    const el = e.target.closest('.copyable');
    if (!el) return;

    const text = el.getAttribute('data-copy') || el.textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
      const oldTitle = el.title;
      el.title = 'Copied!';
      try {
        if (window.bootstrap?.Tooltip) {
          const tip = bootstrap.Tooltip.getOrCreateInstance(el, { trigger: 'manual' });
          tip.show();
          setTimeout(() => { tip.hide(); el.title = oldTitle || 'Copy'; }, 900);
        } else {
          el.classList.add('text-success');
          setTimeout(() => { el.classList.remove('text-success'); el.title = oldTitle || 'Copy'; }, 900);
        }
      } catch {
        el.classList.add('text-success');
        setTimeout(() => { el.classList.remove('text-success'); el.title = oldTitle || 'Copy'; }, 900);
      }
    });
  });
})();

// ----- Appointment Progress Tracker -----
(function () {
  const modal   = document.getElementById('apptTrackModal');
  if (!modal) return;

  const trkVal   = modal.querySelector('#trkVal');
  const certVal  = modal.querySelector('#certVal');
  const dateVal  = modal.querySelector('#dateVal');
  const timeVal  = modal.querySelector('#timeVal');
  const statusEl = modal.querySelector('#statusVal');
  const bar      = modal.querySelector('#trackerBar');

  // Map status/date into a 1..5 step (1=Scheduled, 2=Pending, 3=Approved/Rejected, 4=Approved Captain, 5=Released/Completed)
  function computeStep(statusRaw, whenFlag) {
    const s      = (statusRaw || '').toLowerCase();
    const sNoSpc = s.replace(/\s+/g, ''); // normalize "approved captain" vs "approvedcaptain"
    let step = 1;

    if (s.includes('pending')) step = 2;

    if (s.includes('rejected')) return 3; // terminal branch, show rejected at step 3

    if (sNoSpc.includes('approvedcaptain')) return 4;

    if (s.includes('approved')) step = 3;

    if (s.includes('released') || s.includes('completed') || s.includes('done') || whenFlag === 'past') step = 5;

    return step;
  }

  function paintTracker(current, statusRaw, whenFlag) {
    const s      = (statusRaw || '').toLowerCase();
    const steps  = [...bar.querySelectorAll('.step')];
    const icons  = steps.map(st => st.querySelector('i'));
    const labels = steps.map(st => st.querySelector('.label'));
    const bars   = [...bar.querySelectorAll('.bar')];

    // reset all
    steps.forEach(st => st.classList.remove('active','done','rejected'));
    icons.forEach(ic => ic && ic.classList.remove('active','done','rejected'));
    labels.forEach(lb => lb && lb.classList.remove('active','done','rejected'));
    bars.forEach(b  => b.classList.remove('filled'));

    // helper to set state on a given step index (0-based)
    const mark = (idx, cls) => {
      if (!steps[idx]) return;
      steps[idx].classList.add(cls);
      if (icons[idx])  icons[idx].classList.add(cls);
      if (labels[idx]) labels[idx].classList.add(cls);
    };

    // Fill bars up to current-1
    bars.forEach((b, idx) => { b.classList.toggle('filled', idx + 1 < current); });

    // Rejected path: mark 1..(step-2) as done, step 3 as rejected
    if (s.includes('rejected')) {
      for (let i = 0; i < 2; i++) mark(i, 'done');   // scheduled & pending complete
      mark(2, 'rejected');                           // approved/rejected step -> red
      return;
    }

    // Normal path:
    // everything before current is done (green)
    for (let i = 0; i < current - 1; i++) mark(i, 'done');

    // current step:
    if (current === 5 || s.includes('released') || s.includes('completed') || s.includes('done') || whenFlag === 'past') {
      // final step should be green (done), not blue
      mark(current - 1, 'done');
    } else {
      // in-progress step (1..4) should be blue
      mark(current - 1, 'active');
    }
  }

  // Open from any .btn-track in table/cards
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-track');
    if (!btn) return;

    const tracking = btn.getAttribute('data-tracking') || '';
    const cert     = btn.getAttribute('data-cert') || '';
    const date     = btn.getAttribute('data-date') || '';
    const time     = btn.getAttribute('data-time') || '';
    const status   = btn.getAttribute('data-status') || '';
    const whenFlag = btn.getAttribute('data-when') || 'upcoming';

    trkVal.textContent   = tracking;
    certVal.textContent  = cert;
    dateVal.textContent  = date;
    timeVal.textContent  = time;
    statusEl.textContent = (status || '').trim();

    const step = computeStep(status, whenFlag);
    paintTracker(step, status, whenFlag);
  });
})();

