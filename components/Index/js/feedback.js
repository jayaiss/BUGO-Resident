(() => {
  const group = document.querySelector('.rating');
  if (!group) return;
  const radios = [...group.querySelectorAll('input[name="rating"]')];
  const out = document.getElementById('ratingValue');

  function update() {
    const r = radios.find(x => x.checked)?.value || '';
    if (out) out.textContent = r ? `${r}/5` : 'Choose a rating';
  }
  radios.forEach(r => r.addEventListener('change', update));
  update();

  // Arrow-key support (left/right to change stars)
  group.addEventListener('keydown', (e) => {
    if (!['ArrowLeft','ArrowRight'].includes(e.key)) return;
    e.preventDefault();
    let idx = radios.findIndex(x => x.checked);
    if (idx === -1) idx = 2; // middle default
    idx += (e.key === 'ArrowRight' ? -1 : 1); // row-reverse layout
    idx = Math.max(0, Math.min(radios.length-1, idx));
    radios[idx].checked = true;
    radios[idx].dispatchEvent(new Event('change', {bubbles:true}));
    radios[idx].focus();
  });
})();
(() => {
  const form = document.getElementById('feedbackForm');
  const text = document.getElementById('feedbackText');
  const charCount = document.getElementById('charCount');
  const contactSwitch = document.getElementById('contactSwitch');
  const contactFields = document.getElementById('contactFields');
  const tagsInput = document.getElementById('selectedTags');
  const tagBtns = Array.from(document.querySelectorAll('.btn.tag'));
  const submitBtn = form?.querySelector('button[type="submit"]');
  const spinner = submitBtn?.querySelector('.spinner-border');
  const submitText = submitBtn?.querySelector('.submit-text');

  // live counter
  const updateCount = () => { charCount.textContent = (text.value || '').length; };
  text.addEventListener('input', updateCount); updateCount();

  // tag toggles -> hidden field (CSV)
  tagBtns.forEach(b=>{
    b.addEventListener('click',()=>{
      b.classList.toggle('active');
      const active = tagBtns.filter(x=>x.classList.contains('active')).map(x=>x.textContent.trim());
      tagsInput.value = active.join(',');
    });
  });

  // contact toggle
  contactSwitch.addEventListener('change',()=>{
    contactFields.classList.toggle('d-none', !contactSwitch.checked);
  });

  // Bootstrap validation + SweetAlert2 messages
  form.addEventListener('submit', (e) => {
    if (!form.checkValidity()) {
      e.preventDefault(); e.stopPropagation();
      form.classList.add('was-validated');
      return;
    }
    // Optional: inline loading state (works with normal POST too)
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    submitText.textContent = 'Submitting…';

    // If you want to keep normal POST, remove the next block.
    // If you prefer AJAX submit, replace with fetch() and on success show Swal then reset.
    // --- Keep default POST but show a quick confirmation after a short delay ---
    setTimeout(()=> {
      // let the server handle the redirect/flash; if staying on the page:
    //   if (window.Swal) {
    //     Swal.fire({
    //       icon: 'success',
    //       title: 'Thank you!',
    //       text: 'Your feedback was submitted.',
    //       timer: 1800, showConfirmButton: false
    //     });
    //   }
      // Reset UI (in case you stay on the same page)
      submitBtn.disabled = false;
      spinner.classList.add('d-none');
      submitText.textContent = 'Submit';
      form.reset();
      form.classList.remove('was-validated');
      document.querySelectorAll('.btn.tag.active').forEach(b=>b.classList.remove('active'));
      tagsInput.value='';
      contactFields.classList.add('d-none');
      const modal = bootstrap.Modal.getInstance(document.getElementById('feedbackModal'));
      modal?.hide();
    }, 600);
  }, false);
})();
