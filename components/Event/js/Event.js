// Reusable debounce
function debounce(fn, delay = 300) {
  let t;
  return function (...args) {
    clearTimeout(t);
    t = setTimeout(() => fn.apply(this, args), delay);
  };
}

(() => {
  const q        = document.getElementById('search');
  const on       = document.getElementById('onDate');
  const when     = document.getElementById('when');
  const clearBtn = document.getElementById('clearFilters');

  const getDateStr = el => (el.dataset.date || '').slice(0, 10);

  function visible(el) {
    const txt = (q?.value || '').toLowerCase().trim();
    const hay = (el.dataset.search || '').toLowerCase();
    const okText = !txt || hay.includes(txt);

    const w = (when?.value || '').toLowerCase();
    const okWhen = !w || (el.dataset.when || '') === w;

    const d = getDateStr(el);
    const okOn = !on?.value || d === on.value;

    return okText && okWhen && okOn;
  }

  // Show "See more" only if text is actually longer than 3 lines
  const ensureSeeMore = () => {
    document.querySelectorAll('.kv-val .desc').forEach(desc => {
      const btn = desc.parentElement.querySelector('.see-more');
      if (!btn) return;

      // Measure clamped vs expanded heights
      const wasExpanded = desc.classList.contains('is-expanded');

      // collapsed height
      desc.classList.remove('is-expanded');
      const clampedH = desc.getBoundingClientRect().height;

      // expanded height
      desc.classList.add('is-expanded');
      const expandedH = desc.getBoundingClientRect().height;

      // restore original state
      if (!wasExpanded) desc.classList.remove('is-expanded');

      const hasOverflow = expandedH > clampedH + 1;
      btn.hidden = !hasOverflow;
      btn.setAttribute('aria-expanded', wasExpanded ? 'true' : 'false');
      btn.textContent = wasExpanded ? 'See less' : 'See more';
    });
  };

  const updateClearState = () => {
    if (!clearBtn) return;
    const active = !!((q?.value || '').trim() || (on?.value || '').trim() || (when?.value || '').trim());
    clearBtn.disabled = !active;
  };

  function apply() {
    document.querySelectorAll('#tblBody tr, #listMobile .event-card').forEach(el => {
      el.style.display = visible(el) ? '' : 'none';
    });
    updateClearState();
    ensureSeeMore();
  }

  const debouncedApply = debounce(apply, 250);

  [q, on, when].forEach(el => {
    if (!el) return;
    el.addEventListener('input', debouncedApply, { passive: true });
    el.addEventListener('change', debouncedApply);
  });

  // Clear filters handler
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      if (q) q.value = '';
      if (on) on.value = '';
      if (when) when.value = '';
      apply();
    });
  }

  // Copy-to-clipboard with safe tooltip feedback
  document.addEventListener('click', e => {
    // Toggle See more / See less
    const seeBtn = e.target.closest('.see-more');
    if (seeBtn) {
      const desc = seeBtn.previousElementSibling; // .desc
      if (desc) {
        const expand = !desc.classList.contains('is-expanded');
        desc.classList.toggle('is-expanded', expand);
        seeBtn.setAttribute('aria-expanded', expand ? 'true' : 'false');
        seeBtn.textContent = expand ? 'See less' : 'See more';
      }
      return; // don't treat as copyable click
    }

    // Copy location
    const t = e.target.closest('.copyable');
    if (!t) return;
    const txt = t.getAttribute('data-copy') || t.textContent.trim();

    // Clipboard
    navigator.clipboard.writeText(txt).then(() => {
      const oldTitle = t.title;
      t.title = 'Copied!';

      // Use Bootstrap tooltip if available; else just revert title
      try {
        if (window.bootstrap && bootstrap.Tooltip) {
          const tip = bootstrap.Tooltip.getOrCreateInstance(t, { trigger: 'manual' });
          tip.show();
          setTimeout(() => { tip.hide(); t.title = oldTitle || 'Copy location'; }, 900);
        } else {
          setTimeout(() => { t.title = oldTitle || 'Copy location'; }, 900);
        }
      } catch {
        setTimeout(() => { t.title = oldTitle || 'Copy location'; }, 900);
      }
    });
  });

  // Initial pass
  apply();
})();
