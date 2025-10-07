document.addEventListener('DOMContentLoaded', function () {
  // ✅ Mark all notifications as read when opening the dropdown
  const notifBell = document.getElementById('notifDropdown');
  notifBell.addEventListener('click', function () {
    fetch(window.location.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'mark_read=1'
    }).then(() => {
      const badge = document.querySelector('.notif-badge');
      if (badge) badge.remove();
    });
  });

  // ✅ Delete notification
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
      }).then(() => {
        const notifItem = document.getElementById('notif-' + trackingNumber);
        if (notifItem) notifItem.remove();
      });
    });
  });

  // ✅ Open correct modal for each notif (including Events)
  document.querySelectorAll('.notif-item').forEach(item => {
    item.addEventListener('click', function (e) {
      // ignore when clicking the trash button/icon
      if (e.target.closest('.delete-notif-btn')) return;

      const status = (this.getAttribute('data-status') || '').toLowerCase();
      const source = (this.getAttribute('data-source') || '').toLowerCase();

      // 🔹 Events
if (source === 'event') {
  document.getElementById('eventTitle').textContent = this.getAttribute('data-certificate') || 'N/A';
  document.getElementById('eventDate').textContent  = this.getAttribute('data-date') || 'N/A';

  const start = this.getAttribute('data-time') || '';
  const end   = this.getAttribute('data-end-time') || '';
  const hasStart = !!start;
  const hasEnd   = !!end;

  document.getElementById('eventTime').textContent =
    (hasStart && hasEnd) ? `${start} – ${end}` :
    hasStart ? start :
    hasEnd ? end : 'N/A';

  // if you keep the separate "End Time" row, set or hide it:
  const endSpan = document.getElementById('eventEndTime');
  const endRow  = endSpan ? endSpan.closest('.kv') : null;
  if (endSpan) {
    if (hasEnd) {
      endSpan.textContent = end;
      if (endRow) endRow.style.removeProperty('display');
    } else if (endRow) {
      endRow.style.display = 'none';
    }
  }

  new bootstrap.Modal(document.getElementById('eventNotifModal')).show();
  // (mark read fetch unchanged)
  return;
}



      // 🔹 Certificates/Appointments
      if (status === 'approved') {
        document.getElementById('modalAppTrackingNumber').textContent = this.getAttribute('data-tracking') || 'N/A';
        document.getElementById('modalAppClaimDate').textContent      = this.getAttribute('data-date') || 'N/A';
        document.getElementById('modalAppClaimTime').textContent      = this.getAttribute('data-time') || 'N/A';
        document.getElementById('modalAppCertificate').textContent    = this.getAttribute('data-certificate') || 'Cedula';
        document.getElementById('modalAppPaymentAmount').textContent  = this.getAttribute('data-payment') || '0.00';
        new bootstrap.Modal(document.getElementById('approvedNotifModal')).show();
        return;
      }

      if (status === 'released') {
        document.getElementById('releasedTrackingNumber').textContent = this.getAttribute('data-tracking') || 'N/A';
        document.getElementById('releasedCertificate').textContent    = this.getAttribute('data-certificate') || 'Cedula';
        new bootstrap.Modal(document.getElementById('releasedNotifModal')).show();
        return;
      }

      if (status === 'rejected') {
        document.getElementById('rejectionReasonText').textContent = this.getAttribute('data-reason') || 'No reason provided.';
        new bootstrap.Modal(document.getElementById('rejectionReasonModal')).show();
        return;
      }

      if (status === 'approvedcaptain') {
        document.getElementById('modalClaimDate').textContent    = this.getAttribute('data-date') || 'N/A';
        document.getElementById('modalClaimTime').textContent    = this.getAttribute('data-time') || 'N/A';
        document.getElementById('modalCertificate').textContent  = this.getAttribute('data-certificate') || 'Cedula';
        document.getElementById('modalPaymentAmount').textContent= this.getAttribute('data-payment') || '0.00';
        new bootstrap.Modal(document.getElementById('approvedCaptainModal')).show();
        return;
      }

      // Fallback → Pending
      new bootstrap.Modal(document.getElementById('pendingNotifModal')).show();
    });
  });

  // ✅ "See more" link for full rejection reason
  document.querySelectorAll('.see-full-reason').forEach(link => {
    link.addEventListener('click', function (e) {
      e.stopPropagation();
      const fullReason = this.getAttribute('data-reason');
      document.getElementById('rejectionReasonText').textContent = fullReason;
    });
  });
});

// ✅ Close "All Notification History" modal before opening another modal
document.querySelectorAll('#allNotifModal .notif-item').forEach(item => {
  item.addEventListener('click', function () {
    const allNotifModalEl = document.getElementById('allNotifModal');
    const allNotifModal = bootstrap.Modal.getInstance(allNotifModalEl);
    if (allNotifModal) allNotifModal.hide();
  });
});

    (() => {
  const isDesktop = () => window.matchMedia('(min-width: 992px)').matches;

  function placeNotifMenu(){
    const menu = document.getElementById('notifMenu');
    const dropdown = document.getElementById('notifDropdown');
    if (!menu || !dropdown) return;

    if (isDesktop()){
      // Let Bootstrap/Popper handle desktop placement
      menu.style.position = '';
      menu.style.left = '';
      menu.style.right = '';
      menu.style.top = '';
      menu.style.transform = '';
      menu.style.width = '';
      return;
    }

    // Mobile: center under the navbar (not the bell)
    const navbar = document.querySelector('.navbar');
    const navBottom = navbar ? navbar.getBoundingClientRect().bottom : 0;

    const vw = window.innerWidth;
    const menuW = Math.min(420, vw * 0.94);

    menu.style.position = 'fixed';
    menu.style.top = Math.round(navBottom + 8) + 'px'; // 8px gap under navbar
    menu.style.left = '50%';
    menu.style.right = 'auto';
    menu.style.transform = 'translateX(-50%)';
    menu.style.width = menuW + 'px';
  }

  document.addEventListener('shown.bs.dropdown', e => {
    if (e.target.id === 'notifDropdown') placeNotifMenu();
  });
  window.addEventListener('resize', placeNotifMenu, { passive: true });
  window.addEventListener('scroll', placeNotifMenu,  { passive: true });
})();