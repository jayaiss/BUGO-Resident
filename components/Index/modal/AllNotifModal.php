<div class="modal fade" id="allNotifModal" tabindex="-1" aria-labelledby="allNotifModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable notif-modal-position">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="allNotifModalLabel">All Notification History</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body scrollable-notif-list">
        <ul class="list-group">
<?php if ($allNotifQuery->num_rows > 0): ?>
  <?php while ($row = $allNotifQuery->fetch_assoc()): ?>
    <?php
      $tracking = htmlspecialchars($row['tracking_number']);
      $status   = htmlspecialchars($row['status']);
      $reason   = htmlspecialchars($row['rejection_reason'] ?? '');
      $src      = strtolower((string)($row['source'] ?? ''));
      $isEvent  = ($src === 'event');
      $label    = $isEvent ? 'Event' : 'Certificate';

      // certificate/event title
      $certificate = isset($row['certificate']) && $row['certificate'] !== ''
        ? htmlspecialchars($row['certificate'])
        : ($isEvent ? 'Event' : 'Cedula');

      // date & time
      $date = $row['appointment_date'] ?? $row['selected_date'] ?? '';
      $timeRaw = $row['appointment_time'] ?? $row['selected_time'] ?? '';
      $endRaw  = $row['appointment_end_time'] ?? '';

      $formattedDate = $date ? date('F j, Y', strtotime($date)) : 'N/A';
      $startTime     = ($timeRaw && $timeRaw !== '00:00:00') ? date('g:i A', strtotime($timeRaw)) : '';
      $endTime       = ($endRaw  && $endRaw  !== '00:00:00') ? date('g:i A', strtotime($endRaw))  : '';
      $timeRange     = $startTime && $endTime ? "$startTime – $endTime" : ($startTime ?: ($endTime ?: 'N/A'));

      $payment = number_format((float)($row['total_payment'] ?? 0), 2);
      $notifDate = date("F j, Y g:i A", strtotime($row['issued_time']));
    ?>
    <li class="list-group-item d-flex justify-content-between align-items-start notif-item"
        data-source="<?= htmlspecialchars($row['source']) ?>"
        data-status="<?= $status ?>"
        data-tracking="<?= $tracking ?>"
        data-date="<?= $formattedDate ?>"
        data-time="<?= $startTime ?>"
        data-end-time="<?= $endTime ?>"
        data-certificate="<?= $certificate ?>"
        data-payment="<?= $payment ?>"
        data-reason="<?= $reason ?>">
      <div class="ms-2 me-auto">
        <div class="fw-bold text-dark">Status Update:</div>
        <div class="small">
          <strong><?= $label ?>:</strong> <?= $certificate ?><br>
          <?php if (!$isEvent): ?>
            <strong>Tracking #:</strong> <?= $tracking ?><br>
          <?php endif; ?>
          <strong>Status:</strong>
          <span class="notif-status <?= strtolower($status) ?>"><?= $status ?></span><br>
          <strong>Date:</strong> <?= $formattedDate ?><br>
          <strong>Time:</strong> <?= $timeRange ?>
          <?php if ($status === 'Rejected' && !empty($reason)): ?>
            <br><em>Reason: <?= strlen($reason) > 60 ? substr($reason, 0, 60) . '...' : $reason ?></em>
          <?php endif; ?>
        </div>
        <div class="text-muted small"><?= $notifDate ?></div>
      </div>
      <button type="button"
              class="btn btn-sm btn-outline-danger p-1 delete-notif-btn"
              data-tracking="<?= $tracking ?>"
              data-source="<?= htmlspecialchars($row['source']) ?>"
              title="Delete Notification">
        <i class="bi bi-trash"></i>
      </button>
    </li>
  <?php endwhile; ?>
<?php else: ?>
  <li class="list-group-item text-center text-muted">No past notifications found.</li>
<?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

      