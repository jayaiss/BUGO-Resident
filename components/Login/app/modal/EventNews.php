<!-- News -->
<section id="news" class="container container-max my-5">
  <h4 class="fw-bold text-center mb-4" data-reveal>Latest News &amp; Updates</h4>

  <div class="row">
    <?php if ($events_result && $events_result->num_rows > 0): ?>
      <?php while ($row = $events_result->fetch_assoc()): ?>
        <?php
          $id       = (int)$row['id'];
          $title    = htmlspecialchars($row['event_name'], ENT_QUOTES, 'UTF-8');
          $desc     = htmlspecialchars($row['event_description'], ENT_QUOTES, 'UTF-8');
          $date     = date('F d, Y', strtotime($row['event_date']));
          $time     = date('h:i A', strtotime($row['event_time']));
          $location = htmlspecialchars($row['event_location'], ENT_QUOTES, 'UTF-8');
          $image    = !empty($row['event_image'])
                      ? 'data:' . $row['image_type'] . ';base64,' . base64_encode($row['event_image'])
                      : 'assets/images/placeholder.jpg';
        ?>
        <div class="col-md-4 mb-4" data-reveal>
          <article class="news-card hover-lift h-100">
            <img src="<?= $image ?>" alt="<?= $title ?> image" loading="lazy">
            <div class="p-3 d-flex flex-column">
              <h5 class="fw-bold mb-1"><?= $title ?></h5>
              <p class="text-muted small mb-2">
                <i class="bi bi-calendar-event me-1" aria-hidden="true"></i><?= $date ?>
                &middot;
                <i class="bi bi-clock ms-2 me-1" aria-hidden="true"></i><?= $time ?>
              </p>
              <p class="flex-grow-1"><?= mb_strimwidth($desc, 0, 120, '...') ?></p>
              <button class="btn btn-outline-dark mt-auto" data-bs-toggle="modal" data-bs-target="#eventModal<?= $id ?>">
                <i class="bi bi-info-circle" aria-hidden="true"></i> More Details
              </button>
            </div>
          </article>
        </div>

        <!-- Event Modal -->
        <div class="modal fade" id="eventModal<?= $id ?>" tabindex="-1" aria-labelledby="eventModalLabel<?= $id ?>" aria-hidden="true">
          <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content rounded-xl">
              <div class="modal-header border-0">
                <h5 class="modal-title" id="eventModalLabel<?= $id ?>"><?= $title ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p><strong>Date:</strong> <?= $date ?></p>
                <p><strong>Time:</strong> <?= $time ?></p>
                <p><strong>Location:</strong> <?= $location ?></p>
                <hr>
                <p><?= nl2br($desc) ?></p>
              </div>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="alert alert-light border" role="alert" data-reveal>No recent events to show.</div>
      </div>
    <?php endif; ?>
  </div>
</section>