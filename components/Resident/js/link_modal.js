(() => {
  const drop  = document.getElementById('bcDrop');
  const input = document.getElementById('birthCertificate');
  const prev  = document.getElementById('bcPreview');
  const nameEl= document.getElementById('bcName');
  const metaEl= document.getElementById('bcMeta');
  const thumb = document.getElementById('bcThumb');
  const repl  = document.getElementById('bcReplace');

  if (!drop || !input) return;

  const MAX_BYTES = 2 * 1024 * 1024;
  const ALLOWED = ['image/jpeg','image/png','image/webp','application/pdf'];

  const showPreview = (file) => {
    nameEl.textContent = file.name;
    metaEl.textContent = `${file.type || 'unknown'} • ${(file.size/1024).toFixed(0)} KB`;
    thumb.innerHTML = '';

    if (file.type.startsWith('image/')) {
      const img = document.createElement('img');
      img.alt = 'Preview';
      img.loading = 'lazy';
      img.src = URL.createObjectURL(file);
      thumb.appendChild(img);
    } else {
      thumb.textContent = 'PDF';
    }
    prev.classList.remove('d-none');
  };

  const validateFile = (file) => {
    if (!file) return false;
    if (!ALLOWED.includes(file.type)) {
      Swal.fire({ icon:'error', title:'Unsupported file', text:'Allowed: JPG, PNG, WEBP, PDF (max 2MB)' });
      return false;
    }
    if (file.size > MAX_BYTES) {
      Swal.fire({ icon:'error', title:'File too large', text:'Max size is 2MB.' });
      return false;
    }
    return true;
  };

  const pick = () => input.click();

  drop.addEventListener('click', pick);
  drop.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(); }
  });

  drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('drag'); });
  drop.addEventListener('dragleave', () => drop.classList.remove('drag'));
  drop.addEventListener('drop', (e) => {
    e.preventDefault(); drop.classList.remove('drag');
    const file = e.dataTransfer?.files?.[0];
    if (validateFile(file)) {
      input.files = e.dataTransfer.files;
      showPreview(file);
    }
  });

  input.addEventListener('change', () => {
    const file = input.files?.[0];
    if (validateFile(file)) showPreview(file);
  });

  repl?.addEventListener('click', pick);

  // Bootstrap client-side validation
  const form = drop.closest('form.needs-validation');
  if (form) {
    form.addEventListener('submit', (e) => {
      if (!form.checkValidity()) {
        e.preventDefault(); e.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  }
})();