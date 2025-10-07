<!-- Modal for Editing Profile -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content shadow-lg rounded-3">
      <div class="modal-header border-0 pb-0">
        <div>
          <h5 class="modal-title fw-semibold" id="editProfileModalLabel">
            <i class="bi bi-person-lines-fill me-2"></i>Edit Your Profile
          </h5>
          <p class="text-muted small mb-0">Fields marked <span class="text-danger">*</span> are required.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body pt-2">
        <form id="editProfileForm" method="POST" action="<?php echo enc_page('resident_profile'); ?>" enctype="multipart/form-data" class="needs-validation" novalidate>

          <!-- Quick grid tuning -->
          <style>
            #editProfileModal .form-label .req { color: #dc3545; }
            #editProfileModal .card-section { border: 1px solid rgba(0,0,0,.08); }
            #editProfileModal .input-group-text { background: #f8f9fa; }
            #editProfileModal .helper { font-size:.825rem; color:#6c757d; }
          </style>

          <!-- ✅ Profile Picture (click to change; will open cropper) -->
          <!-- <div class="card card-section mb-3">
            <div class="card-header bg-white border-0 pb-0">
              <h6 class="mb-1"><i class="bi bi-person-circle me-2"></i>Profile Picture</h6>
              <hr class="mt-2 mb-0">
            </div>
            <div class="card-body pt-3 text-center">
              <div class="mb-2">
                <img
                  id="profileTrigger"
                  src="<?php
                    if (!empty($profilePicture)) {
                      echo 'data:image/jpeg;base64,'.base64_encode($profilePicture);
                    } else {
                      echo 'assets/logo/defolt.jpg';
                    }
                  ?>"
                  class="rounded-circle border shadow-sm avatar-img"
                  alt="Profile Picture"
                  style="width:120px; height:120px; object-fit:cover; cursor:pointer;">
              </div>
              <input type="file" id="profileInput" accept="image/*" hidden>
              <div class="small text-muted">Click the photo to upload a new one. You can crop it before saving.</div>
            </div>
          </div> -->

          <!-- Personal Info -->
          <div class="card card-section mb-3">
            <div class="card-header bg-white border-0 pb-0">
              <h6 class="mb-1"><i class="bi bi-id-card me-2"></i>Personal Information</h6>
              <hr class="mt-2 mb-0">
            </div>
            <div class="card-body pt-3">
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">Gender <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-gender-ambiguous"></i></span>
                    <select name="gender" class="form-select form-select-sm" required>
                      <option value="">Select Gender</option>
                      <option value="Male"   <?php if ($gender === 'Male') echo 'selected'; ?>>Male</option>
                      <option value="Female" <?php if ($gender === 'Female') echo 'selected'; ?>>Female</option>
                      <option value="Other"  <?php if ($gender === 'Other') echo 'selected'; ?>>Other</option>
                    </select>
                    <div class="invalid-feedback">Please select your gender.</div>
                  </div>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Civil Status <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-heart"></i></span>
                    <select name="civil_status" class="form-select form-select-sm" required>
                      <option value="">Select Civil Status</option>
                      <option value="Single"    <?php if ($civilStatus === 'Single') echo 'selected'; ?>>Single</option>
                      <option value="Married"   <?php if ($civilStatus === 'Married') echo 'selected'; ?>>Married</option>
                      <option value="Widowed"   <?php if ($civilStatus === 'Widowed') echo 'selected'; ?>>Widowed</option>
                      <option value="Separated" <?php if ($civilStatus === 'Separated') echo 'selected'; ?>>Separated</option>
                    </select>
                    <div class="invalid-feedback">Please choose your civil status.</div>
                  </div>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Birth Date</label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                    <input type="date" class="form-control form-control-sm" name="birth_date"
                      value="<?php echo htmlspecialchars((string)($birthDate ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                  </div>
                  <div class="helper">Contact admin to update if incorrect.</div>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Birth Place <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                    <input type="text" class="form-control form-control-sm" name="birth_place"
                      value="<?php echo htmlspecialchars((string)($birthPlace ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100" autocomplete="birthplace">
                    <div class="invalid-feedback">Please enter your birth place.</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Contact & Address -->
          <div class="card card-section mb-3">
            <div class="card-header bg-white border-0 pb-0">
              <h6 class="mb-1"><i class="bi bi-envelope-paper me-2"></i>Contact & Address</h6>
              <hr class="mt-2 mb-0">
            </div>
            <div class="card-body pt-3">
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">Contact Number <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                    <input type="tel" class="form-control form-control-sm" name="contact_number"
                      inputmode="numeric" pattern="^[0-9+\-\s]{7,20}$"
                      value="<?php echo htmlspecialchars((string)($contactNumber ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required placeholder="e.g., 09XXXXXXXXX">
                    <div class="invalid-feedback">Enter a valid contact number.</div>
                  </div>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Email <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-at"></i></span>
                    <input type="email" class="form-control form-control-sm" name="email" id="editEmail"
                      value="<?php echo htmlspecialchars((string)($email ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="email">
                  </div>
                  <small class="form-text email-feedback" id="editEmailFeedback"></small>
                  <div class="invalid-feedback">Please provide a valid email.</div>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Address <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-house-door"></i></span>
                    <input type="text" class="form-control form-control-sm" name="res_street_address"
                      value="<?php echo htmlspecialchars($resStreetAddress ?? '', ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="street-address" maxlength="140">
                    <div class="invalid-feedback">Please enter your address.</div>
                  </div>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Citizenship <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-flag"></i></span>
                    <input type="text" class="form-control form-control-sm" name="citizenship"
                      value="<?php echo htmlspecialchars((string)($citizenship ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="40">
                    <div class="invalid-feedback">Please enter your citizenship.</div>
                  </div>
                </div>
              </div>

              <div class="row g-3 mt-0">
                <div class="col-md-6">
                  <label class="form-label">Religion <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-peace"></i></span>
                    <input type="text" class="form-control form-control-sm" name="religion"
                      value="<?php echo htmlspecialchars((string)($religion ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="60">
                    <div class="invalid-feedback">Please enter your religion.</div>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Occupation <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-briefcase"></i></span>
                    <input type="text" class="form-control form-control-sm" name="occupation"
                      value="<?php echo htmlspecialchars((string)($occupation ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="60">
                    <div class="invalid-feedback">Please enter your occupation.</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Emergency Contact -->
          <div class="card card-section mb-2">
            <div class="card-header bg-white border-0 pb-0">
              <h6 class="mb-1"><i class="bi bi-shield-plus me-2"></i>Emergency Contact</h6>
              <hr class="mt-2 mb-0">
            </div>
            <div class="card-body pt-3">
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label">Relationship <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-people"></i></span>
                    <input type="text" class="form-control form-control-sm" name="relationship"
                      value="<?php echo htmlspecialchars((string)($emergencyContactRelationship ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required maxlength="40">
                    <div class="invalid-feedback">Enter your relationship to the contact.</div>
                  </div>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Contact Name <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control form-control-sm" name="emergency_contact_name"
                      value="<?php echo htmlspecialchars($emergencyContactName ?? ''); ?>" required maxlength="100" autocomplete="name">
                    <div class="invalid-feedback">Please enter a contact name.</div>
                  </div>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Contact Phone <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                    <input type="tel" class="form-control form-control-sm" name="emergency_contact_phone"
                      value="<?php echo htmlspecialchars($emergencyContactPhone ?? ''); ?>" required
                      inputmode="numeric" pattern="^[0-9+\-\s]{7,20}$" placeholder="e.g., 09XXXXXXXXX" autocomplete="tel">
                    <div class="invalid-feedback">Enter a valid phone number.</div>
                  </div>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Contact Email <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control form-control-sm" name="emergency_contact_email" id="emergencyContactEmail"
                      value="<?php echo htmlspecialchars($emergencyContactEmail ?? ''); ?>" required autocomplete="email">
                  </div>
                  <small class="form-text email-feedback" id="emergencyContactEmailFeedback"></small>
                  <div class="invalid-feedback">Please provide a valid email.</div>
                </div>

                <div class="col-md-12">
                  <label class="form-label">Emergency Address <span class="req">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-geo"></i></span>
                    <input type="text" class="form-control form-control-sm" name="emergency_contact_address"
                      value="<?php echo htmlspecialchars($emergencyContactAddress ?? ''); ?>" required maxlength="160" autocomplete="street-address">
                    <div class="invalid-feedback">Please enter the emergency address.</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Save -->
          <div class="d-flex align-items-center justify-content-between mt-3">
            <div class="form-text">
              <i class="bi bi-lock-fill me-1"></i>Your information is protected and only visible to authorized staff.
            </div>
            <button type="submit" class="btn btn-primary btn-sm px-3" id="saveBtn">
              <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
              Save Changes
            </button>
          </div>
        </form>
      </div>

      <!-- Optional footer removed in your snippet -->
    </div>
  </div>
</div>
