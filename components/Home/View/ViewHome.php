
<!-- Welcome Section -->
<section class="hero py-5 reveal">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-md-6">
        <h1 class="display-5 fw-bold mb-3">Welcome to our Community Portal</h1>
        <p class="lead">
          Welcome to the official resident portal of Barangay Bugo! This platform is designed to make our services more accessible and efficient for everyone.
        </p>
      </div>

      <div class="col-md-6 text-center">
        <?php if ($captain): ?>
          <?php
            // Default FB URL; uses $captain['facebook_url'] if present
            $fbUrl = $captain['facebook_url'] ?? 'https://www.facebook.com/spencer.cailing.323880';
          ?>

          <?php if (!empty($captain['photo'])): ?>
            <img src="data:image/jpeg;base64,<?= base64_encode($captain['photo']) ?>"
                 alt="<?= htmlspecialchars($captain['position']) ?>"
                 class="img-fluid rounded-circle"
                 loading="lazy" decoding="async"
                 style="max-height: 400px; width: 400px; object-fit: cover;">
          <?php else: ?>
            <img src="assets/logo/default-captain.jpg"
                 alt="<?= htmlspecialchars($captain['position']) ?>"
                 class="img-fluid rounded-circle"
                 loading="lazy" decoding="async"
                 style="max-height: 250px; width: 250px; object-fit: cover;">
          <?php endif; ?>

          <h5 class="fw-bold mt-2 mb-0">
            <?= htmlspecialchars(formatFullName(
              $captain['first_name'],
              $captain['middle_name'],
              $captain['last_name'],
              $captain['suffix_name']
            )) ?>
          </h5>
          <small class="text-muted"><?= htmlspecialchars($captain['position']) ?></small>

          <div class="mt-2">
            <a href="<?= htmlspecialchars($fbUrl, ENT_QUOTES, 'UTF-8') ?>"
               class="btn btn-sm btn-outline-primary rounded-pill text-nowrap"
               target="_blank" rel="noopener noreferrer"
               aria-label="View Facebook profile">
              <i class="bi bi-facebook me-1"></i> Facebook
            </a>
          </div>

        <?php else: ?>
          <img src="assets/logo/default-captain.jpg"
               alt="Barangay Captain"
               class="img-fluid rounded-circle"
               loading="lazy" decoding="async"
               style="max-height: 250px; width: 250px; object-fit: cover;">
          <h5 class="fw-bold mt-2 mb-0">No Active Captain</h5>
          <small class="text-muted">Position Unavailable</small>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>


<!-- Tutorial Section -->
<section id="tutorial" class="container py-5 reveal">
  <h4 class="section-title">
    <i class="bi bi-journal-text me-2"></i> How to Schedule an Appointment
  </h4>

  <div class="row g-4 align-items-center">
    <div class="col-md-6">
      <ol class="list-group list-group-numbered shadow-sm">
        <li class="list-group-item">
          <i class="bi bi-laptop me-2 text-primary"></i>
          Log in to the Barangay Bugo Resident Portal.
        </li>
        <li class="list-group-item">
          <i class="bi bi-calendar-plus me-2 text-success"></i>
          Go to the <strong> Schedule Appointment</strong> section.
        </li>
        <li class="list-group-item">
          <i class="bi bi-people me-2 text-info"></i>
          Choose whether the appointment is for <strong>yourself</strong> or for your <strong>child</strong>.
        </li>
        <li class="list-group-item">
          <i class="bi bi-clock-history me-2 text-info"></i>
          Select an available date and time slot.
        </li>
        <li class="list-group-item">
          <i class="bi bi-file-earmark-text me-2 text-warning"></i>
          Choose the type of certificate you need.
        </li>
        <li class="list-group-item">
          <i class="bi bi-check2-circle me-2 text-success"></i>
          Confirm your appointment and wait for approval.
        </li>
      </ol>
    </div>

    <div class="col-md-6 text-center">
      <div class="ratio ratio-16x9 shadow rounded">
        <!-- Replace with your actual tutorial/demo video or image -->
        <iframe src="https://www.youtube.com/embed/b9y4_EY-kQE"
                title="Appointment Tutorial"
                allowfullscreen></iframe>
      </div>
      <small class="text-muted d-block mt-2">
        Watch this quick video tutorial on how to book your appointment.
      </small>
    </div>
  </div>
</section>


<!-- Statistics -->
<!-- <section class="container py-5 reveal">
  <div class="row text-center justify-content-center g-4">
    <div class="col-md-4">
      <div class="p-4 bg-white stat-card h-100">
        <i class="bi bi-calendar-check icon-box text-primary"></i>
        <h2 class="fw-bold text-primary mb-1"><?= number_format($total_appointments) ?></h2>
        <p class="mb-0">Appointments Managed</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="p-4 bg-white stat-card h-100">
        <i class="bi bi-file-earmark-bar-graph icon-box text-success"></i>
        <h2 class="fw-bold text-success mb-1"><?= $certificates_percentage ?>%</h2>
        <p class="mb-0 text-muted">Percentage of Certificates</p>
      </div>
    </div>
    <div class="col-md-4">
      <div class="p-4 bg-white stat-card h-100">
        <i class="bi bi-people-fill icon-box text-warning"></i>
        <h2 class="fw-bold text-warning mb-1"><?= number_format($active_residents) ?></h2>
        <p class="mb-0">Active Residents</p>
      </div>
    </div>
  </div>
</section> -->

<!-- Guidelines -->
<section id="guidelines" class="container container-max my-5">
  <div class="d-flex align-items-center justify-content-between gap-3 mb-3 sticky-top bg-body pt-3" style="top:72px; z-index: 5;">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-info-circle-fill text-primary fs-4"></i>
      <h4 class="fw-bold mb-0">Resident Service Guidelines</h4>
      <?php if ($guide_result && $guide_result->num_rows > 0): ?>
        <span id="guideCount" class="badge text-bg-light border ms-1"><?=
          number_format($guide_result->num_rows)
        ?> total</span>
      <?php endif; ?>
    </div>

    <!-- Search -->
    <div class="ms-auto" style="min-width: 260px;">
      <div class="input-group input-group-sm shadow-sm">
        <span class="input-group-text bg-body"><i class="bi bi-search"></i></span>
        <input id="guideSearch" type="search" class="form-control" placeholder="Search guidelines…">
        <button id="clearSearch" class="btn btn-outline-secondary" type="button" aria-label="Clear" title="Clear"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
  </div>

  <?php if ($guide_result && $guide_result->num_rows > 0): ?>
    <div id="guideList" class="row g-3">
      <?php while ($guide = $guide_result->fetch_assoc()): ?>
        <?php
          $text = trim($guide['guide_description'] ?? '');
          // Create a short preview (first 180 chars, word-safe)
          $preview = mb_strimwidth($text, 0, 180, '…', 'UTF-8');
          $id = 'g'.substr(sha1($text), 0, 8);
        ?>
        <div class="col-12">
          <article class="card border-0 shadow-sm hover-lift h-100 rounded-4">
            <div class="card-body p-4">
              <div class="d-flex align-items-start gap-3">
                <div class="flex-shrink-0">
                  <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success"
                        style="width:36px;height:36px">
                    <i class="bi bi-check2 fw-bold"></i>
                  </span>
                </div>
                <div class="flex-grow-1">
                  <!-- Optional: derive a title by taking text up to first period/colon -->
                  <?php
                    $title = $text;
                    if (preg_match('/^(.{10,120}?)([.:•\-–]|$)/u', $text, $m)) { $title = $m[1]; }
                  ?>
                  <h6 class="card-title fw-semibold mb-1"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h6>
                  <p class="card-text text-secondary mb-0" data-full="#full-<?= $id ?>">
                    <span class="guide-preview"><?= htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (mb_strlen($text,'UTF-8') > 180): ?>
                      <button class="btn btn-link btn-sm px-1 ms-1 align-baseline guide-toggle"
                              data-target="#full-<?= $id ?>" aria-expanded="false">Show more</button>
                    <?php endif; ?>
                  </p>
                  <div id="full-<?= $id ?>" class="collapse mt-2">
                    <div class="bg-body-tertiary rounded-3 p-3 small">
                      <?= nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) ?>
                    </div>
                    <button class="btn btn-link btn-sm px-0 mt-1 guide-toggle" data-target="#full-<?= $id ?>">Show less</button>
                  </div>
                </div>
                <!-- Quick actions -->
                <div class="ms-auto d-none d-md-flex gap-2">
                  <button class="btn btn-outline-secondary btn-sm" onclick="copyText(`<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>`)" title="Copy">
                    <i class="bi bi-clipboard"></i>
                  </button>
                  <!--<button class="btn btn-outline-secondary btn-sm" onclick="printGuideline('full-<?= $id ?>')" title="Print">-->
                  <!--  <i class="bi bi-printer"></i>-->
                  <!--</button>-->
                </div>
              </div>
            </div>
          </article>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="text-center py-5">
      <div class="display-6 mb-2">🙋‍♀️</div>
      <h5 class="fw-semibold mb-1">No guidelines available</h5>
      <p class="text-secondary mb-3">Please check back later or contact the barangay office for assistance.</p>
      <a href="/contact" class="btn btn-primary">
        <i class="bi bi-chat-left-text me-1"></i> Contact us
      </a>
    </div>
  <?php endif; ?>
</section>

<!-- Minimal styles -->
<style>
  .hover-lift { transition: transform .15s ease, box-shadow .15s ease; }
  .hover-lift:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.08)!important; }
  .sticky-top.bg-body { box-shadow: 0 6px 12px -12px rgba(0,0,0,.25); }
  #guideList .card-title { line-height: 1.25; }
  #guideSearch::-webkit-search-cancel-button { display:none; }
  .guide-hit { background: var(--bs-warning-bg-subtle); border-radius: .3rem; padding: 0 .15rem; }
</style>

<!-- Tiny, dependency-free helpers -->
<script>
  // Live search/filter with highlight
  (function () {
    const q = document.getElementById('guideSearch');
    const clearBtn = document.getElementById('clearSearch');
    const list = document.getElementById('guideList');
    if (!q || !list) return;

    const items = [...list.querySelectorAll('.col-12')];
    const countBadge = document.getElementById('guideCount');

    function normalize(s){ return s.toLowerCase().normalize("NFKD"); }

    function highlight(textEl, query){
      // remove old marks
      textEl.innerHTML = textEl.textContent;
      if (!query) return;
      const t = textEl.textContent;
      const idx = t.toLowerCase().indexOf(query.toLowerCase());
      if (idx >= 0) {
        const before = t.slice(0, idx);
        const match = t.slice(idx, idx + query.length);
        const after = t.slice(idx + query.length);
        textEl.innerHTML = `${before}<mark class="guide-hit">${match}</mark>${after}`;
      }
    }

    function applyFilter() {
      const term = q.value.trim();
      const needle = normalize(term);
      let shown = 0;

      items.forEach(card => {
        const hay = normalize(card.textContent);
        const ok = !needle || hay.includes(needle);
        card.classList.toggle('d-none', !ok);
        if (ok) {
          shown++;
          const prev = card.querySelector('.guide-preview');
          if (prev) highlight(prev, term);
        }
      });

      if (countBadge) countBadge.textContent = `${shown} ${shown === 1 ? 'result' : 'results'}`;
    }

    q.addEventListener('input', applyFilter);
    clearBtn?.addEventListener('click', () => { q.value=''; q.focus(); applyFilter(); });

    // Show more/less toggles
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.guide-toggle');
      if (!btn) return;
      const target = document.querySelector(btn.dataset.target);
      if (!target) return;
      const isOpen = target.classList.contains('show');
      const collapse = new bootstrap.Collapse(target, { toggle: true });
      btn.setAttribute('aria-expanded', String(!isOpen));
      btn.textContent = isOpen ? 'Show more' : 'Show less';
    });

    // Init count on load
    applyFilter();
  })();

  // Copy full text
  async function copyText(txt){
    try {
      await navigator.clipboard.writeText(txt);
      toast('Copied guideline to clipboard');
    } catch { alert('Copy failed'); }
  }

  // Print a single guideline
  function printGuideline(id){
    const node = document.getElementById(id);
    const html = node ? node.innerText : '';
    const w = window.open('', '_blank', 'width=720,height=900');
    w.document.write(`<pre style="font: 14px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial">${html}</pre>`);
    w.document.close(); w.focus(); w.print();
  }

  // Lightweight toast (uses Bootstrap if available)
  function toast(msg){
    if (!window.bootstrap) { alert(msg); return; }
    const holder = document.getElementById('toasts') || (() => {
      const d = document.createElement('div'); d.id='toasts';
      d.className='position-fixed bottom-0 end-0 p-3'; d.style.zIndex='1080';
      document.body.appendChild(d); return d;
    })();
    const el = document.createElement('div');
    el.className='toast align-items-center text-bg-dark border-0';
    el.role='status'; el.ariaLive='polite';
    el.innerHTML=`<div class="d-flex"><div class="toast-body">${msg}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    holder.appendChild(el);
    const t = new bootstrap.Toast(el,{delay:1800}); t.show();
    el.addEventListener('hidden.bs.toast',()=>el.remove());
  }
</script>


<!-- Officials Carousel -->
<section id="officials" class="container my-5 reveal">
  <h4 class="section-title text-center"><i class="bi bi-people-fill me-2"></i> Barangay Officials</h4>
  <?php if (count($officials) > 0): ?>
    <div id="officialsCarousel" class="carousel slide" data-bs-ride="carousel">
      <div class="carousel-inner">
        <?php
          $chunks = array_chunk($officials, 4);
          foreach ($chunks as $index => $group):
        ?>
        <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
          <div class="row justify-content-center px-5">
            <?php foreach ($group as $official): ?>
              <div class="col-md-3 col-sm-6 mb-4">
                <div class="official-card text-center p-3 h-100 border border-light">
                  <?php
                    $imgSrc = !empty($official['photo'])
                      ? 'data:image/jpeg;base64,' . base64_encode($official['photo'])
                      : 'assets/officials/default.jpg';
                  ?>
                  <img src="<?= $imgSrc ?>"
                       class="img-fluid rounded-circle mb-3 border border-3"
                       alt="Official"
                       loading="lazy" decoding="async"
                       style="width: 120px; height: 120px; object-fit: cover;">
                  <h6 class="fw-bold mb-1"><?= htmlspecialchars($official['position']) ?></h6>
                  <small class="text-muted">
                    <?= htmlspecialchars(
                      $official['first_name'] . ' ' .
                      ($official['middle_name'] ? $official['middle_name'][0] . '.' : '') . ' ' .
                      $official['last_name'] . ' ' .
                      $official['suffix_name']
                    ) ?>
                  </small>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <button class="carousel-control-prev" type="button" data-bs-target="#officialsCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon bg-dark rounded-circle p-2"></span>
        <span class="visually-hidden">Previous</span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#officialsCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon bg-dark rounded-circle p-2"></span>
        <span class="visually-hidden">Next</span>
      </button>
    </div>
  <?php else: ?>
    <p class="text-center">No active officials found.</p>
  <?php endif; ?>
</section>

<section id="news" class="container py-5 reveal">
  <h4 class="section-title text-center mb-4">
    <i class="bi bi-newspaper me-2"></i>Latest News & Updates
  </h4>

  <div class="position-relative mb-4">
    <button class="btn btn-light position-absolute top-50 start-0 translate-middle-y shadow-sm" id="prevNews" style="z-index:10;">
      <i class="bi bi-chevron-left"></i>
    </button>
    <button class="btn btn-light position-absolute top-50 end-0 translate-middle-y shadow-sm" id="nextNews" style="z-index:10;">
      <i class="bi bi-chevron-right"></i>
    </button>

    <div id="newsScroll" class="d-flex overflow-auto gap-3 px-2">
      <?php if ($eventsResult && $eventsResult->num_rows > 0): ?>
        <?php while ($event = $eventsResult->fetch_assoc()): ?>
          <?php $eid = (int)$event['id']; ?>
          <div class="card shadow-sm h-100 news-card" style="min-width:600px; max-width:600px;">
            <?php if (!empty($event['event_image'])): ?>
              <img
                src="data:<?= $event['image_type']; ?>;base64,<?= base64_encode($event['event_image']); ?>"
                class="card-img-top" alt="Event Image" loading="lazy" decoding="async"
                style="height:300px; object-fit:cover;">
            <?php else: ?>
              <img
                src="https://via.placeholder.com/600x300?text=<?= urlencode($event['event_title']); ?>"
                class="card-img-top" alt="Placeholder Image" loading="lazy" decoding="async"
                style="height:300px; object-fit:cover;">
            <?php endif; ?>

            <div class="card-body">
              <h5 class="card-title mb-2"><?= htmlspecialchars($event['event_title']); ?></h5>
              <p class="text-muted small mb-2">
                <i class="bi bi-calendar-event"></i> <?= date("F j, Y", strtotime($event['event_date'])); ?>
                &nbsp; • &nbsp;
                <i class="bi bi-clock"></i>
                <?= !empty($event['event_time']) ? date("h:i A", strtotime($event['event_time'])) : '<em>No time set</em>'; ?>
              </p>
              <p class="card-text line-clamp-3 mb-3">
                <?= htmlspecialchars(mb_strimwidth($event['event_description'], 0, 220, "…")); ?>
              </p>
              <button type="button"
                      class="btn btn-soft-primary btn-sm"
                      data-bs-toggle="modal"
                      data-bs-target="#eventModal<?= $eid ?>">
                More Details
              </button>
            </div>
          </div>

          <!-- Modal for THIS event (keep inside loop) -->
          <div class="modal fade" id="eventModal<?= $eid ?>" tabindex="-1"
               aria-labelledby="eventModalLabel<?= $eid ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
              <div class="modal-content shadow modal-elevated rounded-4 border-0">

                <?php if (!empty($event['event_image'])): ?>
                  <div class="ratio ratio-21x9 modal-hero">
                    <img
                      src="data:<?= $event['image_type']; ?>;base64,<?= base64_encode($event['event_image']); ?>"
                      alt="Event banner" class="object-cover rounded-top-4" loading="lazy" decoding="async">
                  </div>
                <?php endif; ?>

                <div class="modal-header border-0 pb-0">
                  <div class="d-flex align-items-start w-100">
                    <div class="flex-grow-1">
                      <h5 class="modal-title fw-bold lh-sm" id="eventModalLabel<?= $eid ?>">
                        <?= htmlspecialchars($event['event_title']); ?>
                      </h5>
                      <div class="text-muted small mt-1 d-flex flex-wrap gap-2">
                        <span class="badge rounded-pill bg-light text-dark">
                          <i class="bi bi-calendar-event me-1"></i><?= date("F j, Y", strtotime($event['event_date'])); ?>
                        </span>
                        <span class="badge rounded-pill bg-light text-dark">
                          <i class="bi bi-clock me-1"></i><?= !empty($event['event_time']) ? date("h:i A", strtotime($event['event_time'])) : 'No time set'; ?>
                        </span>
                        <?php if (!empty($event['event_location'])): ?>
                          <a class="badge rounded-pill bg-light text-primary text-decoration-none"
                             target="_blank" rel="noopener"
                             href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($event['event_location']); ?>">
                            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($event['event_location']); ?>
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>
                    <button type="button" class="btn-close ms-3" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                </div>

                <div class="modal-body pt-3">
                  <p class="mb-0"><?= nl2br(htmlspecialchars($event['event_description'])); ?></p>
                </div>

                <div class="modal-footer border-0 pt-0">
                  <button type="button" class="btn btn-outline-secondary btn-sm" data-copy="#eventDetails<?= $eid ?>">
                    <i class="bi bi-clipboard-check me-1"></i> Copy details
                  </button>
                  <button type="button" class="btn btn-outline-primary btn-sm"
                          data-share
                          data-title="<?= htmlspecialchars($event['event_title']); ?>"
                          data-text="<?= htmlspecialchars(mb_strimwidth($event['event_description'],0,120,'…')); ?>">
                    <i class="bi bi-share me-1"></i> Share
                  </button>
                  <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Done</button>
                </div>

                <pre class="visually-hidden" id="eventDetails<?= $eid ?>"><?=
                  htmlspecialchars(
                    $event['event_title'] . PHP_EOL .
                    'Date: ' . date("F j, Y", strtotime($event['event_date'])) . PHP_EOL .
                    'Time: ' . (!empty($event['event_time']) ? date("h:i A", strtotime($event['event_time'])) : 'No time set') . PHP_EOL .
                    'Location: ' . ($event['event_location'] ?? '—') . PHP_EOL . PHP_EOL .
                    $event['event_description']
                  );
                ?></pre>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="w-100">
          <div class="alert alert-info mb-0 w-100 text-center">
            No news or events yet. Please check back later.
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>


<section class="container py-5 reveal">
  <h4 class="section-title"><i class="bi bi-question-circle-fill me-2"></i>Frequently Asked Questions</h4>
  <?php if (empty($faqs)): ?>
    <div class="alert alert-info mb-0">No FAQs yet. Please check back later.</div>
  <?php else: ?>
    <div class="accordion" id="faqAccordion">
      <?php foreach ($faqs as $f):
        $collapseId = 'faq' . (int)$f['faq_id'];
        $headingId  = 'heading' . (int)$f['faq_id'];
      ?>
      <div class="accordion-item">
        <h2 class="accordion-header" id="<?= h($headingId) ?>">
      <button class="accordion-button collapsed"
              type="button"
              data-bs-target="#<?= h($collapseId) ?>"
              aria-expanded="false"
              aria-controls="<?= h($collapseId) ?>">
            <?= h($f['faq_question']) ?>
          </button>
        </h2>
        <div id="<?= h($collapseId) ?>"
             class="accordion-collapse collapse"
             aria-labelledby="<?= h($headingId) ?>"
             data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            <?= safe_answer($f['faq_answer']) ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>