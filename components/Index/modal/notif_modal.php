<ul class="navbar-nav ms-auto me-3 me-lg-4 align-items-center">

  <!-- Notifications -->
  <li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle position-relative text-white"
       id="notifDropdown" href="#" role="button"
       data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
      <i class="bi bi-bell fs-4"></i>
      <?php if ($cedulaNotifCount > 0): ?>
        <span class="notif-badge"><?= $cedulaNotifCount > 99 ? '99+' : $cedulaNotifCount ?></span>
      <?php endif; ?>
    </a>

    <!-- Right-aligned only on lg+; centered on mobile via your JS/CSS -->
    <ul class="dropdown-menu dropdown-menu-lg-end shadow-lg p-2 notif-dropdown" id="notifMenu" aria-labelledby="notifDropdown">

      <!-- 🔹 Header -->
      <li class="menu-head">
        <div class="d-flex align-items-center justify-content-between">
          <span class="title">Notifications</span>
          <?php if ($notifQuery->num_rows > 0): ?>
            <span class="small text-muted"><?= (int)$notifQuery->num_rows ?> new</span>
          <?php endif; ?>
        </div>
      </li>

      <?php if ($notifQuery->num_rows > 0): ?>
        <?php while ($row = $notifQuery->fetch_assoc()): ?>
          <?php
            $tracking = htmlspecialchars($row['tracking_number']);
            $status   = htmlspecialchars($row['status']);
            $reason   = htmlspecialchars($row['rejection_reason'] ?? '');
            $date     = $row['appointment_date'] ?? $row['selected_date'] ?? '';
            $timeRaw = $row['appointment_time'] ?? $row['selected_time'] ?? '';
            $endRaw  = $row['appointment_end_time'] ?? '';

            $formattedDate = $date ? date('F j, Y', strtotime($date)) : 'N/A';
            $formattedTime = ($timeRaw && $timeRaw !== '00:00:00') ? date('g:i A', strtotime($timeRaw)) : '';
            $formattedEnd  = ($endRaw  && $endRaw  !== '00:00:00') ? date('g:i A', strtotime($endRaw))  : '';
          ?>
          <li class="border-bottom mb-2 pb-2 notif-item"
              id="notif-<?= $tracking ?>"
              data-source="<?= htmlspecialchars($row['source']) ?>"
              data-status="<?= $status ?>"
              data-reason="<?= htmlspecialchars($row['rejection_reason'] ?? '') ?>"
              data-date="<?= $formattedDate ?>"
              data-time="<?= $formattedTime ?>" 
              data-end-time="<?= $formattedEnd ?>" 
              data-certificate="<?= htmlspecialchars($row['certificate'] ?? 'Cedula') ?>"
              data-payment="<?= number_format((float)($row['total_payment'] ?? 0), 2) ?>"
              data-tracking="<?= $tracking ?>">
            <div class="d-flex justify-content-between align-items-start">
              <div class="d-flex">
                <i class="bi bi-bell me-2 text-primary fs-5"></i>
                <div>
                  <div class="fw-bold text-dark">Status Update:</div>
                  <?php
                    $src = strtolower((string)($row['source'] ?? ''));
                    $isEvent = ($src === 'event');
                    $label   = $isEvent ? 'Event' : 'Certificate';
                  ?>
                  <div class="small">
                    <strong><?= $label ?>:</strong>
                    <?= htmlspecialchars($row['certificate'] ?? ($isEvent ? '' : 'Cedula')) ?><br>

                    <!--<?php if (!$isEvent): ?>-->
                    <!--  <span class="tracking-row"><strong>Tracking #:</strong> <?= $tracking ?></span><br>-->
                    <!--<?php endif; ?>-->

                    <strong>Status:</strong>
                    <span class="notif-status <?= strtolower($status) ?>"><?= $status ?></span>
                    <?php if ($status === 'Rejected' && !empty($reason)): ?>
                      <br><em>Reason: <?= strlen($reason) > 60 ? substr($reason, 0, 60) . '...' : $reason ?></em>
                    <?php endif; ?>
                  </div>
                  <div class="text-muted small"><?= date("F j, Y g:i A", strtotime($row['issued_time'])) ?></div>
                </div>
              </div>
              <button class="btn btn-sm btn-outline-danger p-1 delete-notif-btn"
                      data-tracking="<?= $tracking ?>"
                      data-source="<?= htmlspecialchars($row['source']) ?>"
                      title="Delete Notification">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </li>
        <?php endwhile; ?>
      <?php else: ?>
        <li class="text-muted text-center small py-2">No new notifications</li>
      <?php endif; ?>

      <li class="text-center mt-2">
        <button class="btn btn-link text-decoration-none small"
                data-bs-toggle="modal" data-bs-target="#allNotifModal">
          See all notifications
        </button>
      </li>
    </ul>
  </li>

  <!-- Profile -->
  <li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle text-white" id="navbarDropdown"
       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fas fa-user fa-fw me-1"></i> <span class="d-none d-sm-inline"></span>
    </a>
    <ul class="dropdown-menu dropdown-menu-end shadow-lg p-2" aria-labelledby="navbarDropdown">
      <li class="dropdown-header text-center fw-semibold">
        <?= htmlspecialchars($residentName) ?>
        <div class="small text-muted">Resident</div>
      </li>
      <li><hr class="dropdown-divider" /></li>
      <li>
        <a class="dropdown-item d-flex align-items-center" href="auth/settings/settings.php">
          <i class="fas fa-cog me-2 text-secondary"></i> Settings
        </a>
      </li>
      <!-- <li>
        <a class="dropdown-item d-flex align-items-center" href="#">
          <i class="fas fa-list me-2 text-secondary"></i> Activity Log
        </a>
      </li> -->
      <li><hr class="dropdown-divider" /></li>
      <li>
        <a class="dropdown-item d-flex align-items-center text-danger" href="logout.php" onclick="return confirmLogout();">
          <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
      </li>
    </ul>
  </li>

</ul>
