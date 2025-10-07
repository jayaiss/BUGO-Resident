
(() => {
  const modal = document.getElementById('linkFamilyModal');
  if (!modal) return;

  // ✅ scope EVERYTHING to the modal to avoid duplicate-ID bugs
  const form               = modal.querySelector('form');
  const searchInput        = modal.querySelector('#residentSearch');
  const searchResults      = modal.querySelector('#searchResults');
  const selectedResidentId = modal.querySelector('#selectedResidentId'); // <input name="child_id">
  const submitButton       = form ? form.querySelector('button[type="submit"]') : null;

  console.log('linkFamilyModal counts', {
    searchInputs: document.querySelectorAll('#residentSearch').length,
    searchResults: document.querySelectorAll('#searchResults').length,
    selectedInputs: document.querySelectorAll('#selectedResidentId').length
  });

  if (!form || !searchInput || !searchResults || !selectedResidentId || !submitButton) return;

  submitButton.disabled = true;

  const errorMessage = document.createElement('div');
  errorMessage.textContent = 'Invalid child.';
  errorMessage.style.color = 'red';
  errorMessage.style.display = 'none';
  searchInput.parentNode.appendChild(errorMessage);

  let highlightedIndex = -1;

  const norm = (s) => String(s ?? '')
    .normalize('NFKD').replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, ' ').trim().toLowerCase();

  const getDisplayName = (r) => r?.name ??
    [r?.first_name, r?.middle_name, r?.last_name].filter(Boolean).join(' ');

  const displayResults = (list = []) => {
    searchResults.innerHTML = '';
    highlightedIndex = -1;

    if (!list.length) {
      searchResults.innerHTML = '<div class="no-results">No resident found</div>';
    } else {
      list.forEach((r) => {
        const item = document.createElement('div');
        const displayName = getDisplayName(r);
        item.className = 'search-result-item';
        item.textContent = displayName;
        item.dataset.id = r.id;
        item.dataset.name = displayName;
        item.dataset.age = r.age ?? '';
        item.addEventListener('click', () => selectResident(item));
        searchResults.appendChild(item);
      });
    }
    searchResults.classList.add('show');
  };

  const hideResults = () => { searchResults.classList.remove('show'); highlightedIndex = -1; };

  const updateHighlight = () => {
    const items = searchResults.querySelectorAll('.search-result-item');
    items.forEach((it, i) => it.classList.toggle('highlighted', i === highlightedIndex));
  };

  const validateAgeGap = (selectedResidentAge) => {
    const myAge = Number.parseInt((window.loggedInResidentAge ?? loggedInResidentAge ?? 0), 10);
    const other = Number.parseInt(selectedResidentAge ?? 0, 10);
    const ageGap = Math.abs(myAge - other);
    if (Number.isNaN(ageGap) || ageGap < 12) {
      searchInput.classList.add('error-border');
      errorMessage.style.display = 'block';
      submitButton.disabled = true;
    } else {
      searchInput.classList.remove('error-border');
      errorMessage.style.display = 'none';
      if (selectedResidentId.value) submitButton.disabled = false;
    }
  };

  const selectResident = (item) => {
    const residentId   = item.dataset.id;
    const residentName = item.dataset.name;
    const residentAge  = item.dataset.age;

    selectedResidentId.value = String(residentId); // ✅ sets child_id for POST
    searchInput.value = residentName;
    searchInput.classList.add('selected-resident');

    hideResults();
    validateAgeGap(residentAge);
    if (errorMessage.style.display === 'none') submitButton.disabled = false;
  };

  // Show-only-on-exact-match
  const onType = function () {
    const q = norm(this.value);
    const pool = Array.isArray(window.residents) ? window.residents : [];
    const exactMatch = pool.find((r) => norm(getDisplayName(r)) === q);

    if (exactMatch) {
      displayResults([exactMatch]);
    } else {
      hideResults();
      selectedResidentId.value = '';
      submitButton.disabled = true;
    }
  };
  searchInput.addEventListener('input', onType);

  searchInput.addEventListener('keydown', (e) => {
    const items = searchResults.querySelectorAll('.search-result-item');
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
      updateHighlight();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      highlightedIndex = Math.max(highlightedIndex - 1, -1);
      updateHighlight();
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (highlightedIndex >= 0 && items[highlightedIndex]) items[highlightedIndex].click();
    } else if (e.key === 'Escape') {
      hideResults();
    }
  });

  document.addEventListener('click', (e) => { if (!e.target.closest('.search-dropdown')) hideResults(); });
  searchInput.addEventListener('blur', () => setTimeout(hideResults, 200));

  // Block submit if child_id empty
  form.addEventListener('submit', (e) => {
    if (!selectedResidentId.value) {
      e.preventDefault();
      if (window.Swal) {
        Swal.fire({ icon: 'error', title: 'Select a resident', text: 'Please pick a child from the list before linking.' });
      } else {
        alert('Please pick a child from the list before linking.');
      }
    }
  });

  // --------------------------------------------------------------------
  //  Avatar upload → MANUAL CROP (replaces your auto-submit-on-change)
  // --------------------------------------------------------------------
  const profileForm = document.getElementById('profileForm');     // your existing avatar upload form (outside this modal)
  const trigger     = document.getElementById('profileTrigger');  // clickable avatar <img>
  const fileInput   = document.getElementById('profileInput');    // <input type="file">

  // Cropper modal elements (ensure they exist in DOM)
  const cropperModalEl = document.getElementById('cropperModal');
  const cropperImage   = document.getElementById('cropperImage');
  const cropperSaveBtn = document.getElementById('cropperSaveBtn');
  const cropperModal   = cropperModalEl ? new bootstrap.Modal(cropperModalEl, { backdrop: 'static' }) : null;

  let cropper = null;
  let objectUrl = null;

  if (trigger && fileInput) {
    if (trigger.dataset.bound !== '1') {
      trigger.dataset.bound = '1';
      const openPicker = () => fileInput.click();
      trigger.addEventListener('click', openPicker);
      trigger.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPicker(); }
      });
    }

    // ❗ remove/bypass auto-submit on change; open cropper instead
    fileInput.addEventListener('change', () => {
      if (!fileInput.files || !fileInput.files.length) return;
      const file = fileInput.files[0];
      if (!/^image\//i.test(file.type)) { alert('Please select an image file.'); fileInput.value=''; return; }

      if (!cropperModal) {
        // Fallback: if no cropper modal found, just submit as before
        if (profileForm) (profileForm.requestSubmit?.() || profileForm.submit());
        return;
      }

      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = URL.createObjectURL(file);
      cropperImage.src = objectUrl;
      cropperModal.show();
    });
  }

  if (cropperModalEl) {
    cropperModalEl.addEventListener('shown.bs.modal', () => {
      // init cropper
      cropper = new Cropper(cropperImage, {
        aspectRatio: 1,
        viewMode: 1,
        autoCropArea: 1,
        dragMode: 'move',
        background: false,
        preview: '.avatar-preview'
      });
    });

    cropperModalEl.addEventListener('hidden.bs.modal', () => {
      if (cropper) { cropper.destroy(); cropper = null; }
      // do not clear fileInput here (user might reopen)
    });

    // Save cropped image → upload to your avatar endpoint
if (cropperSaveBtn) {
  cropperSaveBtn.addEventListener('click', async () => {
    if (!cropper) return;

    const canvas = cropper.getCroppedCanvas({ width: 512, height: 512, imageSmoothingQuality: 'high' });
    if (!canvas) { alert('Could not crop the image.'); return; }

    // optimistic preview (optional)
    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
    if (trigger?.tagName === 'IMG') trigger.src = dataUrl;

    try {
      const blob = await new Promise(res => canvas.toBlob(res, 'image/jpeg', 0.92));
      const fd = new FormData();
      fd.append('profile_picture', blob, 'avatar.jpg');

      const uploadUrl = profileForm?.action || "<?= htmlspecialchars($redirects['upload_profile'] ?? '', ENT_QUOTES, 'UTF-8'); ?>";
      const resp = await fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
      if (!resp.ok) throw new Error('Upload failed');

      // Optional: parse JSON for a message
      let msg = 'Profile photo updated.';
      try { const data = await resp.clone().json(); if (data?.message) msg = data.message; } catch {}

      // Feedback
      if (window.Swal) Swal.fire({ icon: 'success', title: 'Saved', text: msg, timer: 900, showConfirmButton: false });

      // Close modal now
      cropperModal.hide();
      fileInput.value = '';

      // 🔁 Hard refresh so ALL avatars update and caches clear
      setTimeout(() => {
        // Force-reload from server (bypass cache)
        window.location.reload();
      }, 950);

    } catch (err) {
      console.error(err);
      if (window.Swal) Swal.fire({ icon: 'error', title: 'Upload failed', text: 'Please try again.' });
      else alert('Upload failed. Please try again.');
    }
  });
}

  }
  // --------------------------------------------------------------------

  // ------- Tabs -------
  const tabButtons = document.querySelectorAll('#profileTabs .nav-link');
  const panes = ['#overviewContent', '#emergencyContactContent', '#linkedFamilyContent']
    .map((sel) => document.querySelector(sel))
    .filter(Boolean);

  if (tabButtons.length && panes.length) {
    const showPane = (sel) => {
      panes.forEach((p) => (p.style.display = 'none'));
      const target = document.querySelector(sel);
      if (target) target.style.display = 'block';
    };

    tabButtons.forEach((btn) => {
      if (btn.dataset.bound === '1') return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', () => {
        tabButtons.forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        showPane(btn.dataset.target);
      });
    });

    const active = document.querySelector('#profileTabs .nav-link.active');
    showPane(active ? active.dataset.target : '#overviewContent');
  }

  // ------- Tooltips -------
  [...document.querySelectorAll('[data-bs-toggle="tooltip"]')]
    .forEach((el) => new bootstrap.Tooltip(el));

  // ------- Copy phone -------
  document.querySelectorAll('.btn-clip').forEach((btn) => {
    if (btn.dataset.bound === '1') return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', () => {
      const val = btn.getAttribute('data-copy') || '';
      navigator.clipboard.writeText(val).then(() => {
        if (window.Swal) {
          Swal.fire({ icon: 'success', title: 'Copied!', text: 'Number copied to clipboard', timer: 1200, showConfirmButton: false });
        }
      });
    });
  });

  // Debug helper
  window.__dumpLinkForm = () => console.log({
    child_id: document.getElementById('selectedResidentId')?.value,
    typed: document.getElementById('residentSearch')?.value
  });
})();
