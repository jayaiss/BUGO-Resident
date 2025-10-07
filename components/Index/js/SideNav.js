(function () {
  'use strict';

  function updateBreadcrumb() {
    const breadcrumb = document.querySelector('.breadcrumb');
    if (!breadcrumb) return;

    // Try common sidenav containers (adjust selectors to match your markup)
    const sidenav =
      document.querySelector('#sidenav, .sidenav, #layoutSidenav_nav, [data-role="sidenav"]');
    if (!sidenav) return;

    // Find the active link
    const curr =
      sidenav.querySelector('.nav-link[aria-current="page"], .nav-link.active, .active > .nav-link');

    const label = (curr && curr.textContent ? curr.textContent.trim() : '') || document.title || 'Dashboard';

    // Build breadcrumb items safely (no innerHTML concat for the label)
    breadcrumb.innerHTML =
      '<li class="breadcrumb-item"><i class="fas fa-home-alt me-1"></i>Resident</li>';
    const li = document.createElement('li');
    li.className = 'breadcrumb-item active';
    li.setAttribute('aria-current', 'page');
    li.textContent = label;
    breadcrumb.appendChild(li);
  }

  // Run when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateBreadcrumb, { once: true });
  } else {
    updateBreadcrumb();
  }

  // Optional: keep breadcrumb in sync if user clicks around the sidenav
  const navRoot =
    document.querySelector('#sidenav, .sidenav, #layoutSidenav_nav, [data-role="sidenav"]');
  if (navRoot) {
    navRoot.addEventListener('click', (e) => {
      const link = e.target.closest('.nav-link');
      if (link) {
        // Defer so any "active" class/aria-current toggles can apply first
        setTimeout(updateBreadcrumb, 0);
      }
    });
  }
})();
