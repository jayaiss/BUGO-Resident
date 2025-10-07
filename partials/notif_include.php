<?php
require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();

$user_id = $_SESSION['id'] ?? 0;

$notifQuery = $mysqli->query("
    SELECT tracking_number, status, issued_on, source, is_read
    FROM notifications 
    WHERE res_id = {$user_id}
    ORDER BY issued_on DESC
    LIMIT 10
");

$hasUnread = false;
while ($notifRow = $notifQuery->fetch_assoc()) {
    if ($notifRow['is_read'] == 0) {
        $hasUnread = true;
        break;
    }
}
$notifQuery->data_seek(0);
?>

<li class="nav-item dropdown">
  <a class="nav-link dropdown-toggle position-relative text-white" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="bi bi-bell fs-4"></i>
    <?php if ($hasUnread): ?>
      <span class="notif-badge"></span>
    <?php endif; ?>
  </a>

  <ul class="dropdown-menu dropdown-menu-end shadow-lg p-2 notif-dropdown" aria-labelledby="notifDropdown" style="width: 300px;">
    <?php if ($notifQuery->num_rows > 0): ?>
      <?php while ($row = $notifQuery->fetch_assoc()): ?>
        <li id="notif-<?= htmlspecialchars($row['tracking_number']) ?>" class="border-bottom mb-2 pb-2 d-flex justify-content-between align-items-start">
          <div class="d-flex">
            <i class="bi bi-bell me-2 text-primary fs-5"></i>
            <div>
              <div class="fw-bold text-dark">Status Update:</div>
              <div class="small">Tracking #<?= htmlspecialchars($row['tracking_number']) ?> — <?= htmlspecialchars($row['status']) ?></div>
              <div class="text-muted small"><?= date("F j, Y g:i A", strtotime($row['issued_on'])) ?></div>
            </div>
          </div>
          <button 
              type="button" 
              class="btn btn-sm btn-outline-danger p-1 delete-notif-btn" 
              data-tracking="<?= htmlspecialchars($row['tracking_number']) ?>" 
              data-source="<?= htmlspecialchars($row['source']) ?>"
              title="Delete Notification">
              <i class="bi bi-trash"></i>
          </button>
        </li>
      <?php endwhile; ?>
    <?php else: ?>
      <li class="text-muted small text-center p-2">No new notifications</li>
    <?php endif; ?>
    <li class="text-center text-muted small">End of notifications</li>
  </ul>
</li>
