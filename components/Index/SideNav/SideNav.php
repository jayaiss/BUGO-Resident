<?php
// Safety default in case the variable isn't set.
$canScheduleAppointment = isset($canScheduleAppointment) ? (bool)$canScheduleAppointment : true;
?>
<div id="layoutSidenav_nav">
  <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion" aria-label="Resident navigation">
    <div class="sb-sidenav-menu">
      <div class="nav">
        <div class="sb-sidenav-menu-heading">Core</div>

        <a class="nav-link <?= ($currentPage === 'homepage') ? 'active' : 'collapsed'; ?>"
           href="<?= enc_page('homepage'); ?>"
           <?= ($currentPage === 'homepage') ? 'aria-current="page"' : ''; ?>>
          <div class="sb-nav-link-icon"><i class="fas fa-home-alt"></i></div>
          Home
        </a>

        <a class="nav-link <?= ($currentPage === 'admin_dashboard') ? 'active' : 'collapsed'; ?>"
           href="<?= enc_page('admin_dashboard'); ?>"
           <?= ($currentPage === 'admin_dashboard') ? 'aria-current="page"' : ''; ?>>
          <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
          Dashboard
        </a>

        <a class="nav-link <?= ($currentPage === 'resident_profile') ? 'active' : 'collapsed'; ?>"
           href="<?= enc_page('resident_profile'); ?>"
           <?= ($currentPage === 'resident_profile') ? 'aria-current="page"' : ''; ?>>
          <div class="sb-nav-link-icon"><i class="fas fa-user"></i></div>
          Profile
        </a>

        <a class="nav-link <?= ($currentPage === 'resident_appointment') ? 'active' : 'collapsed'; ?>"
           href="<?= enc_page('resident_appointment'); ?>"
           <?= ($currentPage === 'resident_appointment') ? 'aria-current="page"' : ''; ?>>
          <div class="sb-nav-link-icon"><i class="fas fa-calendar-check"></i></div>
          My Appointments
        </a>

 <?php if ($canScheduleAppointment): ?>
          <!-- Active / enabled link -->
          <a class="nav-link <?= ($currentPage === 'schedule_appointment') ? 'active' : 'collapsed'; ?>"
             href="<?= enc_page('schedule_appointment'); ?>"
             <?= ($currentPage === 'schedule_appointment') ? 'aria-current="page"' : ''; ?>>
            <div class="sb-nav-link-icon"><i class="fas fa-calendar-plus"></i></div>
            Schedule Appointment
          </a>
        <?php else: ?>
          <!-- Disabled link for under 18 -->
          <a class="nav-link disabled text-muted"
             href="#"
             title="Available for residents 18 and above"
             aria-disabled="true"
             onclick="return false;">
            <div class="sb-nav-link-icon"><i class="fas fa-calendar-plus"></i></div>
            Schedule Appointment <span class="small">(18+ only)</span>
          </a>
        <?php endif; ?>

        <a class="nav-link <?= ($currentPage === 'event_calendar') ? 'active' : 'collapsed'; ?>"
           href="<?= enc_page('event_calendar'); ?>"
           <?= ($currentPage === 'event_calendar') ? 'aria-current="page"' : ''; ?>>
          <div class="sb-nav-link-icon"><i class="fas fa-calendar-alt"></i></div>
          Event Calendar
        </a>
        
        <!--<a class="nav-link <?php echo ($page === 'audit') ? '' : 'collapsed'; ?>" href="index_Admin.php?page=<?php echo urlencode(encrypt('audit')); ?>">-->
        <!--<div class="sb-nav-link-icon"><i class="fas fa-user-secret"></i></div> Audit Logs-->
        <!--</a>             -->
      </div>
    </div>

    <div class="sb-sidenav-footer user-footer">
      <div class="user-info d-flex align-items-center">
        <div class="user-avatar me-2">
          <img src="<?= htmlspecialchars($profileUrl) ?>"
               alt="Profile picture of <?= htmlspecialchars($residentName) ?>"
               class="avatar-img">
        </div>
        <div class="user-text">
          <div class="fw-semibold text-truncate"><i>Logged in as</i>:</div>
          <div class="fw-semibold text-truncate" title="<?= htmlspecialchars($residentName) ?>">
            <?= htmlspecialchars($residentName) ?>
          </div>
        </div>
      </div>
    </div>
  </nav>
</div>
