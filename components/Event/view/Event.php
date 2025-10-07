<?php
// Defensive defaults when this view is included from different places
$rows  = $rows  ?? [];
$dbErr = $dbErr ?? null;
?>
<div class="container my-5">
  <div class="card page-card">
    <div class="page-head">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div>
          <h4 class="mb-1"><i class="bi bi-calendar-event-fill me-2"></i>Event Calendar</h4>
          <div class="subtle">Browse upcoming barangay events and activities.</div>
        </div>
        <div class="filters d-flex flex-wrap">
          <input id="search" class="form-control form-control-sm" placeholder="Search title / location">
          <input id="onDate" type="date" class="form-control form-control-sm" title="On date">
          <select id="when" class="form-select form-select-sm">
            <option value="">All</option>
            <option>Today</option>
            <option>Tomorrow</option>
            <option>Upcoming</option>
            <option>Past</option>
          </select>
            <button id="clearFilters" type="button" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-circle"></i> Clear
          </button>
        </div>
      </div>
    </div>

    <div class="card-body bg-white">
      <?php if ($dbErr): ?>
        <div class="alert alert-warning">Unable to load events right now.</div>
      <?php endif; ?>

      <?php if (!count($rows)): ?>
        <div class="empty">
          <div class="display-6 mb-3"><i class="bi bi-inbox"></i></div>
          <p class="mb-1">No events found.</p>
          <small>Check back later for new activities.</small>
        </div>
      <?php else: ?>

<!-- Mobile cards (key:value layout) -->
<div id="listMobile" class="d-md-none">
  <?php foreach ($rows as $e): ?>
    <article class="event-card"
            data-search="<?= h(strtolower($e['title'].' '.$e['loc'])) ?>"
            data-when="<?= h(strtolower($e['label'])) ?>"
            data-date="<?= h($e['date']) ?>">

      <ul class="kv">
        <li class="kv-row">
          <span class="kv-key">Title</span><span class="kv-sep">:</span>
          <span class="kv-val"><?= h($e['title']) ?></span>
        </li>

        <li class="kv-row">
          <span class="kv-key">When</span><span class="kv-sep">:</span>
          <span class="kv-val">
            <span class="badge badge-round px-3 <?= badge_class($e['label']) ?>">
              <?= h($e['label']) ?>
            </span>
          </span>
        </li>

        <li class="kv-row">
          <span class="kv-key">Date</span><span class="kv-sep">:</span>
          <span class="kv-val"><?= h($e['dateFmt']) ?></span>
        </li>

        <li class="kv-row">
          <span class="kv-key">Time</span><span class="kv-sep">:</span>
          <span class="kv-val"><?= h($e['timeFmt']) ?></span>
        </li>

        <li class="kv-row">
          <span class="kv-key">Location</span><span class="kv-sep">:</span>
          <span class="kv-val">
            <span class="copyable" data-copy="<?= h($e['loc']) ?>" title="Copy location">
              <?= h($e['loc'] ?: '—') ?>
            </span>
          </span>
        </li>
        <?php if (trim($e['desc'])): ?>
          <li class="kv-row">
            <span class="kv-key">Details</span><span class="kv-sep">:</span>
            <span class="kv-val">
              <span class="desc"><?= h($e['desc']) ?></span>
              <button type="button" class="see-more" hidden aria-expanded="false">See more</button>
            </span>
          </li>
        <?php endif; ?>
      </ul>

    </article>
  <?php endforeach; ?>
</div>

        <!-- Desktop table -->
        <div class="table-wrapper d-none d-md-block">
          <div class="table-responsive">
            <table class="table table-hover align-middle text-center mb-0">
              <thead class="table-light">
                <tr>
                  <th>Title</th>
                  <th>Description</th>
                  <th>Location</th>
                  <th>Time</th>
                  <th>Date</th>
                  <th>When</th>
                </tr>
              </thead>
              <tbody id="tblBody">
                <?php foreach ($rows as $e): ?>
                  <tr
                    data-search="<?= h(strtolower($e['title'].' '.$e['loc'])) ?>"
                    data-when="<?= h(strtolower($e['label'])) ?>"
                    data-date="<?= h($e['date']) ?>">
                    <td class="text-dark fw-semibold text-start"><?= h($e['title']) ?></td>
                    <td class="text-start"><?= h($e['desc']) ?></td>
                    <td class="text-start">
                      <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                      <span class="copyable" data-copy="<?= h($e['loc']) ?>" title="Copy location">
                        <?= h($e['loc'] ?: '—') ?>
                      </span>
                    </td>
                    <td><span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle px-3 py-2 badge-round"><?= h($e['timeFmt']) ?></span></td>
                    <td><span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-3 py-2 badge-round"><?= h($e['dateFmt']) ?></span></td>
                    <td><span class="badge badge-round px-3 <?= badge_class($e['label']) ?>"><?= h($e['label']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; ?>
    </div>
    <?php
  $baseUrl = $redirects['event_calendar'] ?? strtok($_SERVER['REQUEST_URI'], '?') . '?';
  $baseUrl .= (str_contains($baseUrl, '?') && !str_ends_with($baseUrl, '?') && !str_ends_with($baseUrl, '&')) ? '&' : '';
  $start = max(1, $page - 2);
  $end   = min($total_pages, $page + 2);
?>
<nav aria-label="Events pagination" class="mt-3">
  <ul class="pagination justify-content-end flex-wrap">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $page <= 1 ? '#' : $baseUrl . 'pagenum=' . ($page - 1) ?>" tabindex="<?= $page <= 1 ? '-1' : '0' ?>">Previous</a>
    </li>

    <?php if ($start > 1): ?>
      <li class="page-item"><a class="page-link" href="<?= $baseUrl ?>pagenum=1">1</a></li>
      <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="<?= $baseUrl . 'pagenum=' . $i ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>

    <?php if ($end < $total_pages): ?>
      <?php if ($end < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
      <li class="page-item"><a class="page-link" href="<?= $baseUrl . 'pagenum=' . $total_pages ?>"><?= $total_pages ?></a></li>
    <?php endif; ?>

    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $page >= $total_pages ? '#' : $baseUrl . 'pagenum=' . ($page + 1) ?>" tabindex="<?= $page >= $total_pages ? '-1' : '0' ?>">Next</a>
    </li>
  </ul>
  <div class="text-end small text-muted mt-1">
    Showing <?= $totalRows ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?>
  </div>
</nav>

  </div>
  
</div>