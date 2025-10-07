<div class="container my-5">
  <div class="card page-card">
    <div class="page-head">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div>
          <h4 class="mb-1"><i class="bi bi-calendar-check-fill me-2"></i>My Appointments</h4>
          <div class="subtle">View your scheduled appointments and their status.</div>
        </div>
        <div class="d-flex gap-2">
          <input id="search" class="form-control form-control-sm" placeholder="Search tracking / status / certificate">
          <select id="filterStatus" class="form-select form-select-sm" style="max-width:200px">
            <option value="">All statuses</option>
            <option>Approved</option>
            <option>ApprovedCaptain</option>
            <option>Released</option>
            <option>Pending</option>
            <option>Rejected</option>
          </select>
          <select id="filterCert" class="form-select form-select-sm" style="max-width:200px">
            <option value="">All certificates</option>
            <option>Barangay Clearance</option>
            <option>Barangay Indigency</option>
            <option>Barangay Residency</option>
            <option>BESO Application</option>
            <option>Cedula</option>
          </select>
        </div>
      </div>
    </div>

    <div class="card-body bg-white">
      <?php if (!count($rows)): ?>
        <div class="empty">
          <div class="display-6 mb-3"><i class="bi bi-inbox"></i></div>
          <p class="mb-1">No appointments found.</p>
          <small>When you schedule an appointment, it will appear here.</small>
        </div>
      <?php else: ?>

<!-- mobile cards (FORMAL) -->
<div id="listMobile" class="d-lg-none">
  <?php foreach ($rows as $r): ?>
    <div class="appt-card"
         data-search="<?= htmlspecialchars(strtolower($r['tracking'].' '.$r['certificate'].' '.$r['status'])) ?>"
         data-status="<?= htmlspecialchars(strtolower($r['status'])) ?>"
         data-cert="<?= htmlspecialchars(strtolower($r['certificate'])) ?>">
<ul class="kv">
  <li class="kv-row">
    <span class="kv-key">Tracking Number</span><span class="kv-sep">:</span>
    <span class="kv-val">
      <span class="copyable" data-copy="<?= htmlspecialchars($r['tracking']) ?>" title="Copy tracking">
        <?= htmlspecialchars($r['tracking']) ?>
      </span>
    </span>
  </li>

  <!-- stack this on small screens -->
  <li class="kv-row kv-wrap">
    <span class="kv-key">Certificate</span><span class="kv-sep">:</span>
    <span class="kv-val">
      <span class="badge rounded-pill px-3 <?= cert_class($r['certificate']) ?>">
        <?= htmlspecialchars($r['certificate']) ?>
      </span>
    </span>
  </li>

  <li class="kv-row">
    <span class="kv-key">Appointment Date</span><span class="kv-sep">:</span>
    <span class="kv-val"><?= htmlspecialchars($r['date']) ?></span>
  </li>

  <li class="kv-row">
    <span class="kv-key">Time Slot</span><span class="kv-sep">:</span>
    <span class="kv-val"><?= htmlspecialchars($r['time']) ?></span>
  </li>

  <!-- stack this on small screens -->
  <li class="kv-row kv-wrap">
    <span class="kv-key">Status</span><span class="kv-sep">:</span>
    <span class="kv-val">
      <span class="badge px-3 <?= status_class($r['status']) ?>">
        <span class="dot"></span><?= htmlspecialchars(pretty_status($r['status'] ?? '')) ?>
      </span>
    </span>
  </li>

  <li class="kv-row">
    <span class="kv-key">When</span><span class="kv-sep">:</span>
    <span class="kv-val">
      <?php if ($r['is_future']): ?>
        <span class="text-success-emphasis"><i class="bi bi-clock me-1"></i>Upcoming</span>
      <?php else: ?>
        <span class="text-secondary"><i class="bi bi-check2-circle me-1"></i>Past</span>
      <?php endif; ?>
    </span>
  </li>
</ul>

  <!-- (3) Mobile View Progress button -->
  <div class="mt-2">
    <button type="button"
            class="btn btn-sm btn-outline-primary btn-track"
            data-bs-toggle="modal"
            data-bs-target="#apptTrackModal"
            data-tracking="<?= htmlspecialchars($r['tracking']) ?>"
            data-cert="<?= htmlspecialchars($r['certificate']) ?>"
            data-date="<?= htmlspecialchars($r['date']) ?>"
            data-time="<?= htmlspecialchars($r['time']) ?>"
            data-status="<?= htmlspecialchars($r['status']) ?>"
            data-when="<?= $r['is_future'] ? 'upcoming' : 'past' ?>">
      View Progress
    </button>
  </div>

    </div>
  <?php endforeach; ?>
</div>


        <!-- table (desktop) -->
        <div class="table-wrapper d-none d-lg-block">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead class="table-light">
                <tr>
                  <th class="text-nowrap">Tracking Number</th>
                  <th>Certificate</th>
                  <th>Appointment Date</th>
                  <th>Time Slot</th>
                  <th>Status</th>
                  <th class="text-end">When</th>
                  <th>Progress</th>
                </tr>
              </thead>
              <tbody id="tblBody">
                <?php foreach ($rows as $r): ?>
                  <tr data-search="<?= htmlspecialchars(strtolower($r['tracking'].' '.$r['certificate'].' '.$r['status'])) ?>"
                      data-status="<?= htmlspecialchars(strtolower($r['status'])) ?>"
                      data-cert="<?= htmlspecialchars(strtolower($r['certificate'])) ?>">
                    <td class="text-nowrap">
                      <span class="copyable" data-copy="<?= htmlspecialchars($r['tracking']) ?>" title="Copy tracking">
                        <i class="bi bi-clipboard me-1"></i><?= htmlspecialchars($r['tracking']) ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge rounded-pill px-3 <?= cert_class($r['certificate']) ?>">
                        <?= htmlspecialchars($r['certificate']) ?>
                      </span>
                    </td>
                    <td><?= htmlspecialchars($r['date']) ?></td>
                    <td><?= htmlspecialchars($r['time']) ?></td>
                    <td>
                      <span class="badge px-3 <?= status_class($r['status']) ?>">
                        <span class="dot"></span><?= htmlspecialchars(ucfirst(strtolower($r['status']))) ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <?php if ($r['is_future']): ?>
                        <span class="text-success-emphasis"><i class="bi bi-clock me-1"></i>Upcoming</span>
                      <?php else: ?>
                        <span class="text-secondary"><i class="bi bi-check2-circle me-1"></i>Past</span>
                      <?php endif; ?>
                    </td>

                    <!-- (2) Progress column with modal trigger -->
                    <td>
                      <button type="button"
                              class="btn btn-sm btn-outline-primary btn-track"
                              data-bs-toggle="modal"
                              data-bs-target="#apptTrackModal"
                              data-tracking="<?= htmlspecialchars($r['tracking']) ?>"
                              data-cert="<?= htmlspecialchars($r['certificate']) ?>"
                              data-date="<?= htmlspecialchars($r['date']) ?>"
                              data-time="<?= htmlspecialchars($r['time']) ?>"
                              data-status="<?= htmlspecialchars($r['status']) ?>"
                              data-when="<?= $r['is_future'] ? 'upcoming' : 'past' ?>">
                        View
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php
      // ----------------------------
      // Pagination base URL (preserve filters, drop only pagenum) and avoid ///// 
      // ----------------------------
      $qs = $_GET;
      unset($qs['pagenum']);

      $basePath = preg_replace('#/+#', '/', $_SERVER['PHP_SELF']); // e.g. /bugo-resident-side/index_admin.php
      $baseUrl  = $basePath . (empty($qs) ? '?' : '?' . http_build_query($qs) . '&');

      $start = max(1, $page - 2);
      $end   = min($total_pages, $page + 2);
    ?>
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Appointments pagination" class="mt-3">
      <ul class="pagination justify-content-end flex-wrap mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $page <= 1 ? '#' : $baseUrl . 'pagenum=' . ($page - 1) ?>">Previous</a>
        </li>

        <?php if ($start > 1): ?>
          <li class="page-item"><a class="page-link" href="<?= $baseUrl ?>pagenum=1">1</a></li>
          <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
          <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= $baseUrl . 'pagenum=' . $i ?>" <?= $i == $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
          </li>
        <?php endfor; ?>

        <?php if ($end < $total_pages): ?>
          <?php if ($end < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
          <li class="page-item"><a class="page-link" href="<?= $baseUrl . 'pagenum=' . $total_pages ?>"><?= $total_pages ?></a></li>
        <?php endif; ?>

        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= $page >= $total_pages ? '#' : $baseUrl . 'pagenum=' . ($page + 1) ?>">Next</a>
        </li>
      </ul>
      <div class="text-end small text-muted mt-1">
        Showing <?= $totalRows ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?>
      </div>
    </nav>
    <?php endif; ?>
  </div>
</div>

<!-- (4) Modal for order-style tracker -->
<div class="modal fade" id="apptTrackModal" tabindex="-1" aria-labelledby="apptTrackLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="apptTrackLabel">Appointment Progress</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="d-flex flex-wrap gap-3 mb-3 small text-muted">
          <div><strong>Tracking:</strong> <span id="trkVal"></span></div>
          <div><strong>Certificate:</strong> <span id="certVal"></span></div>
          <div><strong>Date:</strong> <span id="dateVal"></span></div>
          <div><strong>Time:</strong> <span id="timeVal"></span></div>
          <div><strong>Status:</strong> <span id="statusVal"></span></div>
        </div>

        <!-- Tracker: icons + labels; no separate legend -->
        <div class="tracker-wrap">
          <div class="tracker" id="trackerBar" aria-hidden="true">
            <div class="step" data-step="1">
              <i class="bi bi-calendar-event"></i>
              <small class="label">Scheduled</small>
            </div>
            <div class="bar"></div>

            <div class="step" data-step="2">
              <i class="bi bi-hourglass-split"></i>
              <small class="label">Pending</small>
            </div>
            <div class="bar"></div>

            <div class="step" data-step="3">
              <i class="bi bi-check-circle"></i>
              <small class="label">Approved / Rejected</small>
            </div>
            <div class="bar"></div>

            <div class="step" data-step="4">
              <i class="bi bi-person-badge"></i>
              <small class="label">Approved Captain</small>
            </div>
            <div class="bar"></div>

            <div class="step" data-step="5">
              <i class="bi bi-box-seam"></i>
              <small class="label">Released / Completed</small>
            </div>
          </div>
        </div>
        <!-- /Tracker -->
      </div>
    </div>
  </div>
</div>

