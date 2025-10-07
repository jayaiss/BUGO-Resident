 <!-- QUICK STATS -->
  <div class="row g-3 mb-4">
    <!-- <div class="col-md-4">
      <div class="card h-100 border-start border-4 border-primary reveal">
        <div class="card-body">
          <div class="stat-pill">
            <i class="bi bi-people-fill text-primary stat-icon"></i>
            <div>
              <div class="text-muted small">Barangay Population</div>
              <div class="h4 mb-0"><?php echo number_format($resCount); ?></div>
            </div>
          </div>
        </div>
      </div>
    </div> -->
    <div class="col-md-4">
      <div class="card h-100 border-start border-4 border-success reveal">
        <div class="card-body">
          <div class="stat-pill">
            <i class="bi bi-megaphone-fill text-success stat-icon"></i>
            <div>
              <div class="text-muted small">Events (next 7 days)</div>
              <div class="h4 mb-0"><?php echo (int)$eventsNext7; ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 border-start border-4 border-warning reveal">
        <div class="card-body">
          <div class="stat-pill">
            <i class="bi bi-calendar2-check-fill text-warning stat-icon"></i>
            <div>
              <div class="text-muted small">My Upcoming Appointments</div>
              <div class="h4 mb-0"><?php echo (int)$myUpcomingCount; ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- NEXT APPOINTMENT + WEATHER -->
  <div class="row gx-4 gy-3 align-items-start mb-4">
    <!-- My Next Appointment -->
<!-- My Next Appointment -->
<div class="col-md-6">
  <div class="card h-100 next-appt-card reveal">
    <div class="card-body p-0">
      <?php
        // ---- SAFE TIME STRING ----
        $displayTime = 'Time: TBA';
        $d = null;
        if ($myNextAppt) {
          $d = DateTime::createFromFormat('Y-m-d', $myNextAppt['selected_date'] ?? $today);

          $timeRaw = trim((string)($myNextAppt['selected_time'] ?? '')); // e.g. "8:00AM-9:00AM"
          if ($timeRaw !== '') {
            $parts = array_map('trim', preg_split('/\s*-\s*/', $timeRaw, 2)); // [start, end?]

            $norm = function(string $s): string {
              // "09:00AM" -> "09:00 AM"
              return preg_replace('/\s*(AM|PM)$/i', ' $1', trim($s));
            };
            $fmt = function(string $s) use ($norm) {
              $n = $norm($s);
              $dt = DateTime::createFromFormat('h:i A', $n)
                 ?: DateTime::createFromFormat('H:i', $n)
                 ?: DateTime::createFromFormat('H:i:s', $n);
              return $dt ? $dt->format('h:i A') : htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
            };

            $start = $parts[0] !== '' ? $fmt($parts[0]) : null;
            $end   = (isset($parts[1]) && $parts[1] !== '') ? $fmt($parts[1]) : null;
            if ($start) $displayTime = $end ? "$start – $end" : $start;
          }
        }

        // ---- STATUS CHIP COLOR ----
        $status = strtolower(trim($myNextAppt['status'] ?? 'pending'));
        $statusClass = 'badge-soft';
        if ($status === 'approved' || $status === 'approvedcaptain') $statusClass = 'badge-approved';
        if ($status === 'rejected' || $status === 'declined') $statusClass = 'badge-rejected';

        // ---- COUNTDOWN BAR ----
        $daysLeft = null; $pct = 0;
        if ($myNextAppt && $d) {
          $todayDT = new DateTime('today');
          $daysLeft = (int)$todayDT->diff($d)->format('%r%a');   // negative if past
          $clamped  = max(0, min(30, $daysLeft));                // 0..30 days
          $pct      = 100 - round(($clamped/30)*100);            // nearer date = fuller bar
        }
      ?>

      <div class="next-appt-head d-flex align-items-center justify-content-between px-3 py-3">
        <div class="d-flex align-items-center gap-3">
          <div class="next-appt-icon"><i class="bi bi-calendar-event"></i></div>
          <div class="fw-bold">My Next Appointment</div>
        </div>
        <span class="badge rounded-pill <?php echo $statusClass; ?>">
          <?php echo htmlspecialchars($myNextAppt['status'] ?? ''); ?>
        </span>
      </div>

      <?php if ($myNextAppt): ?>
        <div class="px-4 py-4 position-relative">
          <?php $month = $d ? strtoupper($d->format('M')) : ''; $day = $d ? $d->format('d') : ''; ?>
          <div class="calendar-badge shadow-sm">
            <div class="cal-month"><?php echo htmlspecialchars($month); ?></div>
            <div class="cal-day"><?php echo htmlspecialchars($day); ?></div>
          </div>

          <div class="ps-md-5 ps-4">
            <div class="h6 mb-1">
              <?php echo htmlspecialchars($d ? $d->format('F j, Y') : ($myNextAppt['selected_date'] ?? '')); ?>
            </div>
            <div class="text-muted mb-2"><?php echo $displayTime; ?></div>

            <div class="d-flex flex-wrap gap-2 mb-3">
              <!-- <span class="chip"><i class="bi bi-geo-alt me-1"></i>On-site</span> -->
              <span class="chip"><i class="bi bi-ticket-perforated me-1"></i><?php echo htmlspecialchars($myNextAppt['status']); ?></span>
            </div>

            <div class="countdown"><div class="countdown-bar" style="width: <?php echo $pct; ?>%"></div></div>
            <div class="small text-muted mt-1">
              <?php
                if ($daysLeft !== null) {
                  echo $daysLeft > 1 ? "$daysLeft days left" :
                       ($daysLeft === 1 ? "Tomorrow" :
                       ($daysLeft === 0 ? "Today" : "Overdue"));
                }
              ?>
            </div>

            <div class="mt-3 d-flex gap-2">
              <a href="index_Admin.php?page=<?php echo urlencode(encrypt('resident_appointment')); ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-eye me-1"></i> View
              </a>
              <a href="#" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil-square me-1"></i> Reschedule
              </a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="px-4 py-4">
          <div class="text-muted">No upcoming appointment.</div>
          <a href="index_Admin.php?page=<?php echo urlencode(encrypt('schedule_appointment')); ?>" class="btn btn-primary btn-sm mt-3">
            <i class="bi bi-plus-circle me-1"></i> Book Appointment
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Weather — Barangay Bugo -->
<div class="col-md-6">
  <div class="card h-100 weather-card reveal">
    <div class="card-body p-0">
      
      <!-- top gradient header -->
      <div class="weather-head d-flex align-items-center justify-content-between px-3 py-3">
        <div class="d-flex align-items-center gap-3">
          <div class="weather-icon display-6" id="wxIcon">⛅</div>
          <div class="fw-bold">Weather — Barangay Bugo</div>
        </div>
        <span class="badge weather-badge" id="wxBadge">
          <?php echo strtoupper($weather['condition'] ?? '--'); ?>
        </span>
      </div>

      <div class="px-4 py-4 position-relative">
        <!-- floating temp badge -->
        <div class="temp-badge shadow-sm">
          <div class="temp-value big" id="wxTemp">
            <?php echo round($weather['temp_c'] ?? 0); ?>°C
          </div>
        </div>

        <div class="ps-md-5 ps-4">
          <div class="h6 mb-2" id="wxDesc">
            <?php echo ucfirst(strtolower($weather['condition'] ?? '--')); ?>
          </div>

          <!-- chips -->
          <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="chip">
              <i class="bi bi-thermometer-half me-1"></i>
              Feels <span id="wxFeels"><?php echo round($weather['feelslike_c'] ?? 0); ?></span>°C
            </span>
            <span class="chip">
              <i class="bi bi-droplet-half me-1"></i>
              <span id="wxHum"><?php echo $weather['humidity'] ?? '--'; ?></span>%
            </span>
          </div>

          <div class="small text-muted mb-1" id="wxWind">
            Wind <?php echo $weather['wind_kph'] ?? '--'; ?> km/h
          </div>
          <div class="small text-muted" id="wxUpdated">
            Updated <?php echo date('g:i A'); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>



  <!-- QUICK ACTIONS -->
  <div class="card quick-actions mb-4 reveal">
    <div class="card-body">
      <h5 class="fw-bold mb-3"><i class="bi bi-lightning-charge-fill me-2"></i>Quick Actions</h5>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="index_Admin.php?page=<?php echo urlencode(encrypt('schedule_appointment')); ?>">
          <i class="bi bi-calendar-plus me-1"></i> Schedule Appointment
        </a>
        <a class="btn btn-outline-secondary" href="index_Admin.php?page=<?php echo urlencode(encrypt('resident_appointment')); ?>">
          <i class="bi bi-file-earmark-text me-1"></i> My Appointments
        </a>
        <a class="btn btn-outline-success" href="index_Admin.php?page=<?php echo urlencode(encrypt('event_calendar')); ?>">
          <i class="bi bi-megaphone me-1"></i> Events
        </a>
        <a class="btn btn-outline-dark" href="index_Admin.php?page=<?php echo urlencode(encrypt('resident_profile')); ?>">
          <i class="bi bi-person-badge me-1"></i> Update Profile
        </a>
      </div>
    </div>
  </div>