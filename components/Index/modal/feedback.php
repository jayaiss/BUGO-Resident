<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <div class="w-100">
          <h5 class="modal-title d-flex align-items-center gap-2" id="feedbackModalLabel">
            <i class="bi bi-chat-dots"></i> Submit Feedback
          </h5>
          <small class="opacity-75">Tell us what worked and what we should improve.</small>
        </div>
        <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form method="POST" id="feedbackForm" class="needs-validation" novalidate>
        <input type="hidden" name="submit_feedback" value="1">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">

        <div class="modal-body">
          <!-- Rating -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Your experience</label>
            <div class="rating" role="radiogroup" aria-label="Rate your experience">
              <input type="radio" name="rating" id="rate5" value="5" required>
              <label for="rate5" aria-label="5 stars">★</label>
              <input type="radio" name="rating" id="rate4" value="4">
              <label for="rate4" aria-label="4 stars">★</label>
              <input type="radio" name="rating" id="rate3" value="3">
              <label for="rate3" aria-label="3 stars">★</label>
              <input type="radio" name="rating" id="rate2" value="2">
              <label for="rate2" aria-label="2 stars">★</label>
              <input type="radio" name="rating" id="rate1" value="1">
              <label for="rate1" aria-label="1 star">★</label>
            </div>
            <div class="invalid-feedback">Please select a rating.</div>
          </div>

          <!-- Quick tags -->
          <div class="mb-3">
            <label class="form-label fw-semibold">What best describes your feedback?</label>
            <div class="d-flex flex-wrap gap-2">
              <!-- These toggle and append to a hidden input -->
              <button type="button" class="btn btn-sm btn-outline-secondary tag">Services</button>
              <button type="button" class="btn btn-sm btn-outline-secondary tag">Performance</button>
              <button type="button" class="btn btn-sm btn-outline-secondary tag">Bug</button>
              <button type="button" class="btn btn-sm btn-outline-secondary tag">Feature Request</button>
              <button type="button" class="btn btn-sm btn-outline-secondary tag">Other</button>
            </div>
            <input type="hidden" name="tags" id="selectedTags">
          </div>

          <!-- Feedback box + counter -->
          <div class="mb-3">
            <label class="form-label fw-semibold" for="feedbackText">Details</label>
            <textarea id="feedbackText" class="form-control" name="feedback" rows="4"
                      placeholder="Be as specific as possible…" minlength="10" maxlength="800" required></textarea>
            <div class="d-flex justify-content-between mt-1 small">
              <div class="text-muted">Min 10 characters</div>
              <div class="text-muted"><span id="charCount">0</span>/800</div>
            </div>
            <div class="invalid-feedback">Please write at least 10 characters.</div>
          </div>

          <!-- Optional contact -->
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="contactSwitch">
            <label class="form-check-label" for="contactSwitch">Allow follow‑up</label>
          </div>
          <div id="contactFields" class="row g-2 d-none">
            <div class="col-12 col-md-6">
              <input type="text" class="form-control" name="name" placeholder="Your name (optional)">
            </div>
            <div class="col-12 col-md-6">
              <input type="email" class="form-control" name="email" placeholder="Email (optional)">
            </div>
          </div>
        </div>

        <div class="modal-footer bg-light-subtle">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">
            <span class="submit-text">Submit</span>
            <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
