
<!-- Officials -->
<section id="officials" class="container container-max my-5">
  <h4 class="fw-bold text-center mb-4" data-reveal>
    <i class="bi bi-people-fill me-2"></i> Barangay Officials
  </h4>

  <?php if (count($officials) > 0): ?>
    <div id="officialsCarousel" class="carousel slide" data-bs-ride="carousel" data-reveal>
      <div class="carousel-inner">
        <?php $chunks = array_chunk($officials, 4); foreach ($chunks as $index => $group): ?>
          <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
            <div class="row g-4 justify-content-center">
              <?php foreach ($group as $official): ?>
                <?php
                  $imgSrc = !empty($official['photo'])
                    ? 'data:image/jpeg;base64,' . base64_encode($official['photo'])
                    : 'assets/logo/default-captain.png';
                ?>
                <div class="col-6 col-md-3">
                  <div class="official-card hover-lift h-100 border-gradient">
                    <img src="<?= $imgSrc ?>" alt="Official photo" loading="lazy">
                    <h6 class="fw-bold mt-3 mb-1"><?= htmlspecialchars($official['position'], ENT_QUOTES, 'UTF-8') ?></h6>
                    <small class="text-muted">
                      <?= htmlspecialchars(
                        $official['first_name'] . ' ' .
                        ($official['middle_name'] ? $official['middle_name'][0] . '.' : '') . ' ' .
                        $official['last_name'] . ' ' .
                        $official['suffix_name']
                      , ENT_QUOTES, 'UTF-8') ?>
                    </small>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button class="carousel-control-prev" type="button" data-bs-target="#officialsCarousel" data-bs-slide="prev" aria-label="Previous slide">
        <span class="carousel-control-prev-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#officialsCarousel" data-bs-slide="next" aria-label="Next slide">
        <span class="carousel-control-next-icon bg-dark rounded-circle p-2" aria-hidden="true"></span>
      </button>
    </div>
  <?php else: ?>
    <p class="text-center" data-reveal>No active officials found.</p>
  <?php endif; ?>
</section>