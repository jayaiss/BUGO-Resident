// UI helpers only (no business rules)
(() => {
  const $ = (s, p=document) => p.querySelector(s);
  const $$ = (s, p=document) => [...p.querySelectorAll(s)];

  // Toggle helpers
  const show = el => el && el.classList.remove('hidden');
  const hide = el => el && el.classList.add('pm-hidden');

  // Purpose “Others” live counter
  const other = $('#additionalDetails');
  const otherCounter = $('#otherCounter');
  if (other && otherCounter) {
    other.addEventListener('input', () => { otherCounter.textContent = String(other.value.length); });
  }

  // Cedula mode UX: reveal sections (keeps your IDs)
  const cedulaMode = $('#cedulaOptionSelect');
  const req = $('#cedulaRequestFields');
  const up  = $('#cedulaUploadFields');
  if (cedulaMode) {
    cedulaMode.addEventListener('change', () => {
      const v = (cedulaMode.value || '').toLowerCase();
      if (v === 'request') { show(req); hide(up); }
      else if (v === 'upload') { hide(req); show(up); }
      else { hide(req); hide(up); }
    });
  }

  // File input selected filename preview
  const upload = $('#upload_path');
  if (upload) {
    upload.addEventListener('change', () => {
      if (upload.files?.[0]) {
        const hint = upload.nextElementSibling;
        if (hint && hint.classList.contains('file-hint')) {
          hint.textContent = `Selected: ${upload.files[0].name}`;
        }
      }
    });
  }

  /* ------------------------------------------------------------------
     Make chips pretty for items your existing code adds to the list
     IMPORTANT: this version **does not replace nodes** so your original
     remove-button onclick keeps working.
  ------------------------------------------------------------------- */
  const list = $('#selectedCertificatesList');

  function restyleChips() {
    if (!list) return;
    $$('#selectedCertificatesList li').forEach(li => {
      // Style the <li> as a chip, but do not recreate its HTML
      li.classList.add('chip');

      // Try to find the existing remove button your other script appended
      const btn = li.querySelector('button');
      if (btn) {
        // Decorate (don’t replace!) so onclick remains intact
        btn.classList.add('remove','btn','btn-sm','p-0');
        btn.setAttribute('title','Remove');
        // You can re-skin the icon/text safely:
        btn.innerHTML = '<i class="bi bi-x-lg"></i>';
      }
      
      // Optional: wrap the remaining text for nicer layout (non-destructive)
      // If you want a label wrapper but there isn't one yet, add it safely.
      if (!li.querySelector('.chip-label')) {
        // Find the first non-button node and wrap it
        const span = document.createElement('span');
        span.className = 'chip-label';
        // Move all nodes except the button into the label span
        [...li.childNodes].forEach(n => {
          if (n !== btn) span.appendChild(n);
        });
        li.insertBefore(span, btn || null);
      }
    });
  }

  // Observe changes to re-style newly added chips
  if (list && 'MutationObserver' in window) {
    const mo = new MutationObserver(() => restyleChips());
    mo.observe(list, { childList: true });
    restyleChips();
  }

  // Safety net: if a chip somehow has no onclick, remove via delegation.
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('#selectedCertificatesList .remove');
    if (!btn) return;

    // If your main script already attached onclick, let it run
    if (btn.onclick) return;

    // Fallback removal to prevent a dead button
    const li = btn.closest('li');
    const strong = li?.querySelector('strong');
    const rawName =
      (strong?.textContent ||
       (li?.textContent || '').split('—')[0] || ''
      ).trim().toLowerCase();

    if (window.selectedCertificates) {
      window.selectedCertificates = window.selectedCertificates.filter(c => c !== rawName);
    }
    if (window.certificatePurposes) {
      try { delete window.certificatePurposes[rawName]; } catch {}
    }
    li?.remove();
    window.updateCertificateUI?.();
  });

  // Disable submit while “loading”
  const submitBtn = $('#submitScheduleBtn');
  if (submitBtn) {
    const spinner = submitBtn.querySelector('.spinner-border');
    const label = submitBtn.querySelector('.btn-text');
    // Hook into your existing XHR by listening globally to the SweetAlert load you trigger
    document.addEventListener('submitSchedule:start', () => {
      submitBtn.disabled = true; spinner?.classList.remove('d-none');
      label?.classList.add('opacity-75');
    });
    document.addEventListener('submitSchedule:end', () => {
      spinner?.classList.add('d-none'); submitBtn.disabled = false;
      label?.classList.remove('opacity-75');
    });
  }
})();

// --- Skeleton while loading slots (wraps your function) ---
(function(){
  if (typeof loadTimeSlots !== 'function') return;
  const orig = loadTimeSlots;
  window.loadTimeSlots = function(dateStr){
    const c = document.getElementById('timeSlotsContainer');
    if (c) {
      c.innerHTML = `
        <div class="time-slot"><span class="skeleton-line" style="width:60%"></span><span class="skeleton-line" style="width:90px"></span></div>
        <div class="time-slot"><span class="skeleton-line" style="width:40%"></span><span class="skeleton-line" style="width:90px"></span></div>
        <div class="time-slot"><span class="skeleton-line" style="width:70%"></span><span class="skeleton-line" style="width:90px"></span></div>
      `;
    }
    return orig.apply(this, arguments);
  };
})();

// --- Keyboard accessibility for days (Enter/Space) ---
(function(){
  if (typeof generateCalendar !== 'function') return;
  const orig = generateCalendar;
  window.generateCalendar = function(){
    orig.apply(this, arguments);
    document.querySelectorAll('#calendar .day:not(.disabled)').forEach(el=>{
      el.setAttribute('tabindex','0');
      el.addEventListener('keydown', e=>{
        if(e.key==='Enter'||e.key===' '){
          const d = el.getAttribute('data-date');
          if (d && typeof selectDate==='function') selectDate(d);
        }
      });
    });
  };
})();

// --- Reflect “Appointment for” choice as a chip ---
(function(){
  const personal = document.getElementById('personalAppointmentBtn');
  const child = document.getElementById('childAppointmentBtn');
  const wrap = document.getElementById('selectedForDisplay');
  const out = document.getElementById('selectedForText');
  function show(label){ if(!wrap||!out) return; out.textContent = label; wrap.style.display = 'flex'; }
  if (personal) personal.addEventListener('click', ()=>show('Personal'));
  if (child)    child.addEventListener('click', ()=>show('For My Child'));
})();
