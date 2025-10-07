<!-- Hero -->
<header id="home" class="hero py-5 py-lg-6 position-relative">
  <div class="hero-brand"></div>
  <div class="blob b1" aria-hidden="true"></div>
  <div class="blob b2" aria-hidden="true"></div>

  <div class="container container-max position-relative">
    <div class="row align-items-center g-5">
      <div class="col-lg-6" data-reveal>
        <h1 class="hero-title mb-3">
          Effortless <span class="gradient-text">Barangay Services</span>, every step of the way.
        </h1>
        <p class="lead">Book appointments, request documents, and stay updated — all online, all in one place.</p>
        <div class="d-flex gap-3 mt-3">
          <a href="https://www.facebook.com/profile.php?id=61553016369368" target="_blank" rel="noopener"
             class="btn btn-primary btn-lg shadow-soft focus-ring">
            <i class="bi bi-chat-dots me-2"></i>Get in touch
          </a>
          <a class="btn btn-ghost btn-lg focus-ring"
             data-bs-toggle="modal"
             data-bs-target="#login"
             data-intent="book-now"
             href="#">
            <i class="bi bi-calendar-plus me-2"></i>Book now
          </a>
        </div>
      </div>

      <div class="col-lg-6 text-center" data-reveal>
        <?php $fbUrl = 'https://www.facebook.com/spencer.cailing.323880'; ?>
        <?php if ($captain && !empty($captain['photo'])): ?>
          <img
            src="data:image/jpeg;base64,<?= base64_encode($captain['photo']) ?>"
            alt="Barangay Captain"
            class="img-fluid rounded-circle shadow-soft border-gradient"
            style="max-height:380px"
            loading="lazy">
          <h5 class="fw-bold mt-3 mb-0">
            <?= htmlspecialchars(
              $captain['first_name'] . ' ' .
              ($captain['middle_name'] ? $captain['middle_name'][0] . '.' : '') . ' ' .
              $captain['last_name'] . ' ' .
              $captain['suffix_name']
            , ENT_QUOTES, 'UTF-8'); ?>
          </h5>
          <p class="text-muted">Punong Barangay</p>
          <div class="mt-1">
            <a href="<?= htmlspecialchars($fbUrl, ENT_QUOTES, 'UTF-8') ?>"
               class="btn btn-sm btn-outline-primary rounded-pill text-nowrap"
               target="_blank" rel="noopener noreferrer" aria-label="View Facebook profile">
              <i class="bi bi-facebook me-1"></i> Facebook
            </a>
          </div>
        <?php else: ?>
          <img
            src="assets/logo/default-captain.png"
            alt="Barangay Captain"
            class="img-fluid rounded-circle shadow-soft border-gradient"
            style="max-height:380px;object-fit:cover"
            loading="lazy">
          <h5 class="fw-bold mt-3 mb-0">Punong Barangay</h5>
          <p class="text-muted">Punong Barangay</p>
          <div class="mt-1">
            <a href="<?= htmlspecialchars($fbUrl, ENT_QUOTES, 'UTF-8') ?>"
               class="btn btn-sm btn-outline-primary rounded-pill text-nowrap"
               target="_blank" rel="noopener noreferrer" aria-label="View Facebook profile">
              <i class="bi bi-facebook me-1"></i> Facebook
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>