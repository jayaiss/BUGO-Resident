<?php
// Render only when a valid $event row is provided
if (!isset($event) || !is_array($event) || !isset($event['id'])) {
    return; // nothing to render
}

if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

// Normalize event fields
$id      = (string)$event['id'];
$title   = (string)($event['event_title'] ?? 'Untitled event');
$desc    = (string)($event['event_description'] ?? '');
$loc     = $event['event_location'] ?? null;
$date    = $event['event_date'] ?? null;
$time    = $event['event_time'] ?? null;
$img     = $event['event_image'] ?? null;
$imgType = (string)($event['image_type'] ?? 'image/jpeg');

// Pre-format labels (avoid strtotime on null)
$dateLabel = $date ? date('F j, Y', strtotime($date)) : 'No date';
$timeLabel = $time ? date('h:i A', strtotime($time)) : 'No time set';

// Short share text
$descShort = mb_strimwidth($desc, 0, 120, '…');
?>


<div class="modal fade" id="eventModal<?= h($id) ?>" tabindex="-1"
     aria-labelledby="eventModalLabel<?= h($id) ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content shadow modal-elevated rounded-4 border-0">

      <?php if (!empty($img)): ?>
        <div class="ratio ratio-21x9 modal-hero">
          <img src="data:<?= h($imgType) ?>;base64,<?= base64_encode($img) ?>"
               alt="Event banner"
               class="object-cover rounded-top-4"
               loading="lazy" decoding="async">
        </div>
      <?php endif; ?>

      <div class="modal-header border-0 pb-0">
        <div class="d-flex align-items-start w-100">
          <div class="flex-grow-1">
            <h5 class="modal-title fw-bold lh-sm" id="eventModalLabel<?= h($id) ?>">
              <?= h($title) ?>
            </h5>

            <div class="text-muted small mt-1 d-flex flex-wrap gap-2">
              <span class="badge rounded-pill bg-light text-dark">
                <i class="bi bi-calendar-event me-1"></i><?= h($dateLabel) ?>
              </span>
              <span class="badge rounded-pill bg-light text-dark">
                <i class="bi bi-clock me-1"></i><?= h($timeLabel) ?>
              </span>

              <?php if (!empty($loc)): ?>
                <a class="badge rounded-pill bg-light text-primary text-decoration-none"
                   target="_blank" rel="noopener"
                   href="https://www.google.com/maps/search/?api=1&query=<?= urlencode((string)$loc) ?>">
                  <i class="bi bi-geo-alt me-1"></i><?= h($loc) ?>
                </a>
              <?php endif; ?>
            </div>
          </div>

          <button type="button" class="btn-close ms-3" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>

      <div class="modal-body pt-3">
        <p class="mb-0"><?= nl2br(h($desc)) ?></p>
      </div>

      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-copy="#eventDetails<?= h($id) ?>">
          <i class="bi bi-clipboard-check me-1"></i> Copy details
        </button>

        <button type="button"
                class="btn btn-outline-primary btn-sm"
                data-share
                data-title="<?= h($title) ?>"
                data-text="<?= h($descShort) ?>">
          <i class="bi bi-share me-1"></i> Share
        </button>

        <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Done</button>
      </div>

      <pre class="visually-hidden" id="eventDetails<?= h($id) ?>"><?=
        h(
          $title . PHP_EOL .
          'Date: ' . $dateLabel . PHP_EOL .
          'Time: ' . $timeLabel . PHP_EOL .
          'Location: ' . ($loc !== null && $loc !== '' ? $loc : '—') . PHP_EOL . PHP_EOL .
          $desc
        )
      ?></pre>

    </div>
  </div>
</div>

