 // Bootstrap validation
  (function () {
    'use strict';
    const form = document.querySelector('#editProfileModal form.needs-validation');
    const saveBtn = document.getElementById('saveBtn');
    const spinner = saveBtn?.querySelector('.spinner-border');

    form?.addEventListener('submit', function (e) {
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      } else {
        // subtle loading state to signal submit
        spinner?.classList.remove('d-none');
        saveBtn?.setAttribute('disabled', 'disabled');
      }
      form.classList.add('was-validated');
    }, false);
  })();

  // Light email format hints (optional)
  const editEmail = document.getElementById('editEmail');
  const editEmailFeedback = document.getElementById('editEmailFeedback');
  const emerEmail = document.getElementById('emergencyContactEmail');
  const emerEmailFeedback = document.getElementById('emergencyContactEmailFeedback');
  function emailHint(input, hintEl){
    if(!input || !hintEl) return;
    input.addEventListener('input', () => {
      const v = input.value.trim();
      if(!v){ hintEl.textContent = ''; return; }
      hintEl.textContent = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? 'Looks good.' : 'Please enter a valid email format.';
      hintEl.className = 'form-text email-feedback ' + (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? 'text-success' : 'text-danger');
    });
  }
  emailHint(editEmail, editEmailFeedback);
  emailHint(emerEmail, emerEmailFeedback);