<!-- Login Modal -->
<div class="modal fade" id="login" tabindex="-1" aria-hidden="true" aria-labelledby="loginTitle">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-xl shadow-soft">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold" id="loginTitle">Resident Login</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body pt-0">
        <form method="POST" action="" class="needs-validation mt-3" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="redirect_to" id="redirect_to" value="">

          <div class="form-floating mb-3">
            <input type="text" name="id" id="id" class="form-control" placeholder="Username" required autocomplete="username">
            <label for="id">Username</label>
          </div>

          <div class="form-floating mb-2 position-relative">
            <input type="password" name="password" id="password" class="form-control" placeholder="Password" required autocomplete="current-password">
            <label for="password">Password</label>
            <button type="button"
                    class="btn position-absolute top-50 end-0 translate-middle-y me-3 border-0 bg-transparent"
                    id="togglePassBtn" aria-label="Toggle password visibility">
              <i class="bi bi-eye-slash" id="toggleIcon" aria-hidden="true"></i>
            </button>
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="remember_me" name="remember_me">
            <label class="form-check-label small" for="remember_me">
              Remember this device for 30 days
            </label>
          </div>

          <!-- 👇 reCAPTCHA widget -->
          <div class="g-recaptcha mb-3"
               data-sitekey="<?= htmlspecialchars(RECAPTCHA_SITE, ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-primary rounded-pill py-2" id="loginBtn">Sign In</button>
          </div>
        </form>

        <div class="text-center mt-3">
          <a href="auth/forgot/forgot_password.php" class="small text-decoration-none">Forgot Password?</a>
        </div>
      </div>
    </div>
  </div>
</div>
