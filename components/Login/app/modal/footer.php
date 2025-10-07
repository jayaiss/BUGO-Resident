<footer class="mt-auto pt-5 pb-3 border-top bg-light" role="contentinfo">
  <div class="container-fluid px-4">

    <div class="row g-4">
      <!-- Brand / About -->
      <div class="col-12 col-md-4">
        <div class="d-flex align-items-center gap-2 mb-2">
          <img src="assets/logo/bugo_logo.png" alt="<?php echo htmlspecialchars($barangayName ?? 'Logo'); ?> logo" width="40" height="40" class="rounded-2">
          <strong class="fs-5 mb-0"><?php echo htmlspecialchars($barangayName ?? 'Barangay Name'); ?></strong>
        </div>
        <p class="text-muted small mb-3">
          Official community portal. Services, certificates, and announcements—made simple.
        </p>

        <div class="d-flex align-items-center gap-3">
          <a class="text-muted" href="https://facebook.com/" target="_blank" rel="noopener" aria-label="Facebook">
            <i class="bi bi-facebook fs-5"></i>
          </a>
          <a class="text-muted" href="https://www.messenger.com/t/" target="_blank" rel="noopener" aria-label="Messenger">
            <i class="bi bi-messenger fs-5"></i>
          </a>
          <a class="text-muted" href="mailto:opb.bugocdo@gmail.com" aria-label="Email">
            <i class="bi bi-envelope fs-5"></i>
          </a>
          <a class="text-muted" href="tel:+63XXXXXXXXXX" aria-label="Phone">
            <i class="bi bi-telephone fs-5"></i>
          </a>
        </div>
      </div>

      <!-- Quick Links -->
      <!-- <div class="col-6 col-md-2">
        <h6 class="text-uppercase small fw-semibold mb-2">Quick links</h6>
        <nav aria-label="Footer quick links">
          <ul class="list-unstyled small mb-0">
            <li><a class="link-secondary text-decoration-none" href="index.php">Home</a></li>
            <li><a class="link-secondary text-decoration-none" href="about.php">About</a></li>
            <li><a class="link-secondary text-decoration-none" href="services.php">Services</a></li>
            <li><a class="link-secondary text-decoration-none" href="appointments.php">Appointments</a></li>
            <li><a class="link-secondary text-decoration-none" href="faq.php">FAQs</a></li>
            <li><a class="link-secondary text-decoration-none" href="contact.php">Contact</a></li>
          </ul>
        </nav>
      </div> -->

      <!-- Resources / Legal -->
      <div class="col-6 col-md-3">
        <h6 class="text-uppercase small fw-semibold mb-2">Resources</h6>
        <nav aria-label="Footer resources">
          <ul class="list-unstyled small mb-0">
            <li><a class="link-secondary text-decoration-none" href="#">Privacy Policy</a></li>
            <li><a class="link-secondary text-decoration-none" href="#">Terms &amp; Conditions</a></li>
            <li><a class="link-secondary text-decoration-none" href="#">Accessibility</a></li>
            <li><a class="link-secondary text-decoration-none" href="#">Sitemap</a></li>
            <li><a class="link-secondary text-decoration-none" href="#">Data Protection</a></li>
            <li><a class="link-secondary text-decoration-none" href="#">FOI Request</a></li>
          </ul>
        </nav>
      </div>

      <!-- Contact / Hours / Newsletter -->
      <div class="col-12 col-md-3">
        <h6 class="text-uppercase small fw-semibold mb-2">Contact</h6>
        <address class="small text-muted mb-3">
          Santan St, Bugo, Cagayan De Oro City, Misamis Oriental<br>
          <!-- <p>Tel No.: <?php echo htmlspecialchars($telephoneNumber); ?>; </p> -->
          <a class="d-block link-secondary text-decoration-none"><i class="bi bi-telephone me-1"></i> Cell: <?php echo htmlspecialchars($mobileNumber); ?></a>
          <a class="d-block link-secondary text-decoration-none" href="mailto:opb.bugocdo@gmail.com"><i class="bi bi-envelope me-1"></i> opb.bugocdo@gmail.com</a>
          <span class="d-block"><i class="bi bi-clock me-1"></i> Mon–Fri, 8:00 AM – 5:00 PM</span>
        </address>

        <!-- <form class="needs-validation" novalidate action="#" method="post" aria-label="Subscribe for updates">
          <div class="input-group input-group-sm">
            <input type="email" class="form-control" placeholder="Email address" aria-label="Email address" required>
            <button class="btn btn-primary" type="submit">Subscribe</button>
          </div>
          <div class="form-text">By subscribing you agree to our <a href="#">Privacy Policy</a>.</div>
        </form> -->
      </div>
    </div>

    <hr class="my-4">

    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 small text-muted">
      <div class="text-center text-sm-start">
        &copy; <?php echo htmlspecialchars($barangayName ?? 'Barangay'); ?> <?php echo date('Y'); ?>. All rights reserved.
        <span class="d-none d-sm-inline"> • </span>
        <a class="link-secondary text-decoration-none" href="#">Privacy</a>
        <span class="mx-1">&middot;</span>
        <a class="link-secondary text-decoration-none" href="#">Terms</a>
        <span class="mx-1">&middot;</span>
        <a class="link-secondary text-decoration-none" href="#">Accessibility</a>
      </div>
    </div>
  </div>
</footer>
<!-- Back to top -->
<button id="backToTop" class="btn btn-dark rounded-circle p-3" aria-label="Back to top" title="Back to top">
  <i class="bi bi-arrow-up"></i>
</button>