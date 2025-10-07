document.addEventListener('DOMContentLoaded', () => {
  // Enable tooltip on Feedback FAB
  const fbBtn = document.getElementById('feedbackButton');
  if (fbBtn) {
    new bootstrap.Tooltip(fbBtn, { placement: 'left' });
  }

  // Confirm before deleting a notification (SweetAlert)
// ✅ Delete notification and refresh page after success
document.querySelectorAll('.delete-notif-btn').forEach(btn => {
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const trackingNumber = this.getAttribute('data-tracking');
        const source = this.getAttribute('data-source');

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'delete_notif=1' +
                  '&tracking_number=' + encodeURIComponent(trackingNumber) +
                  '&source=' + encodeURIComponent(source)
        })
        .then(response => response.text())
        .then(() => {
            // Optionally show a success popup
            Swal.fire({
                icon: 'success',
                title: 'Notification deleted',
                timer: 1000,
                showConfirmButton: false
            }).then(() => {
                // Refresh after SweetAlert closes
                location.reload();
            });
        })
        .catch(err => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Could not delete the notification.'
            });
            console.error(err);
        });
    });
});


  // Keyboard navigation for dropdown notifications
  const dropdown = document.querySelector('.notif-dropdown');
  if (dropdown) {
    dropdown.addEventListener('keydown', (e) => {
      const items = Array.from(dropdown.querySelectorAll('.notif-item'));
      const current = document.activeElement;
      let idx = items.indexOf(current.closest('.notif-item'));
      if (e.key === 'ArrowDown') { e.preventDefault(); items[Math.min(idx+1, items.length-1)]?.focus(); }
      if (e.key === 'ArrowUp')   { e.preventDefault(); items[Math.max(idx-1, 0)]?.focus(); }
      if (e.key === 'Escape')    { const open = bootstrap.Dropdown.getInstance(document.getElementById('notifDropdown')); open?.hide(); }
    });

    // Make items focusable
    dropdown.querySelectorAll('.notif-item').forEach(el => el.setAttribute('tabindex','0'));
  }

  // Improve “See all notifications” modal scroll hint (optional)
  const list = document.querySelector('.scrollable-notif-list');
  if (list) {
    list.addEventListener('wheel', (e) => {
      // subtle momentum on desktop
      if (Math.abs(e.deltaY) < 12) return;
    }, { passive:true });
  }
});
// Delete notification (with confirm) + refresh on success
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.delete-notif-btn');
  if (!btn) return;

  e.stopPropagation();

  const trackingNumber = btn.getAttribute('data-tracking');
  const source = btn.getAttribute('data-source');

  // confirm first
  const res = await Swal.fire({
    title: 'Delete this notification?',
    text: `Tracking # ${trackingNumber}`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, delete it'
  });
  if (!res.isConfirmed) return;

  // prevent double submits
  btn.disabled = true;

  // show loading
  Swal.fire({
    title: 'Deleting…',
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading()
  });

  try {
    await fetch(window.location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        delete_notif: '1',
        tracking_number: trackingNumber,
        source: source
      })
    });

    Swal.fire({
      icon: 'success',
      title: 'Deleted',
      timer: 900,
      showConfirmButton: false
    }).then(() => {
      location.reload(); // full refresh so badge + lists sync
    });
  } catch (err) {
    console.error(err);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Could not delete the notification.'
    });
    btn.disabled = false;
  }
});
