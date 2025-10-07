<div class="container my-5">

  <div class="container page-intro">
    <h2 class="fw-bold mb-1">
      <i class="bi bi-person-lines-fill me-2"></i>PROFILE
    </h2>
    <p class="text-muted mb-0">Resident Profile Information</p>
  </div>

  <!-- PROFILE SHELL -->
  <div class="profile-card shadow-xl border-0 rounded-4 overflow-hidden">

    <!-- Banner (decorative only now) -->
    <div class="profile-banner position-relative">
      <div class="banner-overlay"></div>
      <!-- removed absolute-positioned avatar form from here -->
    </div>

    <!-- Header: avatar moved here in its own column -->
    <div class="profile-header px-4 py-3">
      <div class="row align-items-center g-4">

        <!-- Avatar column -->
        <div class="col-12 col-md-auto text-center text-md-start">
          <form id="profileForm"
                method="POST"
                action="<?= htmlspecialchars($redirects['upload_profile'], ENT_QUOTES, 'UTF-8'); ?>"
                enctype="multipart/form-data"
                class="avatar-form-inflow">
            <input type="file" name="profile_picture" id="profileInput" class="d-none" accept="image/*">
            <div id="profileTrigger" class="avatar-trigger" role="button" tabindex="0" aria-label="Update profile picture">
              <?php if (!empty($profilePicture)): ?>
                <img src="data:image/jpeg;base64,<?= base64_encode($profilePicture); ?>" alt="Profile Picture" class="avatar-img">
              <?php else: ?>
                <img src="assets/logo/default-captain.png" alt="Default Profile Picture" class="avatar-img">
              <?php endif; ?>
              <span class="avatar-edit-badge" data-bs-toggle="tooltip" title="Update photo">
                <i class="bi bi-camera-fill"></i>
              </span>
            </div>
          </form>
        </div>

        <!-- Name / email / role -->
        <div class="col-12 col-md">
          <div class="text-center text-md-start">
            <h4 class="fw-bold mb-1">
              <?= htmlspecialchars(trim(($firstName ?? '').' '.($middleName ?? '').' '.($lastName ?? '')), ENT_QUOTES, 'UTF-8'); ?>
            </h4>
            <div class="text-muted small d-flex align-items-center justify-content-center justify-content-md-start gap-2 flex-wrap">
              <span class="badge rounded-pill bg-primary-subtle text-primary">
                <i class="bi bi-person-badge me-1"></i>Resident
              </span>
              <?php if (!empty($email)): ?>
                <span class="text-body-secondary d-flex align-items-center">
                  <i class="bi bi-envelope-at me-1"></i><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Action button -->
        <div class="col-12 col-md-auto text-center text-md-end">
          <button type="button"
                  class="btn btn-primary btn-sm px-4 rounded-pill w-100 w-md-auto"
                  data-bs-toggle="modal"
                  data-bs-target="#editProfileModal">
            <i class="bi bi-pencil-square me-1"></i>Edit Profile
          </button>
        </div>

      </div>
    </div>

    <!-- Tabs -->
    <div class="px-3 px-md-4">
      <ul class="nav nav-pills profile-pills gap-2 mb-3 justify-content-center" id="profileTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="overviewTab" data-target="#overviewContent" type="button" role="tab">
            <i class="bi bi-layout-text-sidebar-reverse me-1"></i> Overview
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="emergencyContactTab" data-target="#emergencyContactContent" type="button" role="tab">
            <i class="bi bi-telephone-inbound me-1"></i> Emergency Contact
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="linkedFamilyTab" data-target="#linkedFamilyContent" type="button" role="tab">
            <i class="bi bi-people me-1"></i> Linked Family
          </button>
        </li>
      </ul>
    </div>

    <!-- Tab contents -->
    <div class="tab-contents px-3 px-md-4 pb-4">

      <!-- Overview -->
      <div id="overviewContent" class="tab-pane-card show">
        <h6 class="section-title"><i class="bi bi-info-circle me-2"></i>Profile Details</h6>
        <div class="row g-3 g-md-4">
          <div class="col-md-6">
            <div class="info-tile">
              <span class="label"><i class="bi bi-person-lines-fill me-1"></i>Full Name</span>
              <span class="value">
                <?= htmlspecialchars(trim(($firstName ?? '').' '.($middleName ?? '').' '.($lastName ?? '')), ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-tile">
              <span class="label"><i class="bi bi-heart-pulse-fill me-1"></i>Civil Status</span>
              <span class="value"><?= htmlspecialchars((string)($civilStatus ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>

          <div class="col-md-3">
            <div class="info-tile">
              <span class="label"><i class="bi bi-hourglass-split me-1"></i>Age</span>
              <span class="value">
                <?php
                  if (!empty($birthDate)) {
                    $birthDateObj = DateTime::createFromFormat('Y-m-d', $birthDate);
                    $today = new DateTime();
                    echo $birthDateObj ? $today->diff($birthDateObj)->y : 'N/A';
                  } else { echo 'N/A'; }
                ?>
              </span>
            </div>
          </div>
          <div class="col-md-3">
            <div class="info-tile">
              <span class="label"><i class="bi bi-gender-ambiguous me-1"></i>Gender</span>
              <span class="value"><?= htmlspecialchars((string)($gender ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
          <div class="col-md-3">
            <div class="info-tile">
              <span class="label"><i class="bi bi-calendar-event me-1"></i>Birth Date</span>
              <span class="value"><?= htmlspecialchars((string)($birthDate ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
          <div class="col-md-3">
            <div class="info-tile">
              <span class="label"><i class="bi bi-geo-alt me-1"></i>Birth Place</span>
              <span class="value"><?= htmlspecialchars((string)($birthPlace ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-tile">
              <span class="label"><i class="bi bi-telephone me-1"></i>Contact Number</span>
              <span class="value d-flex align-items-center gap-2">
                <span id="contact_number"><?= htmlspecialchars((string)($contactNumber ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if (!empty($contactNumber)): ?>
                  <button class="btn btn-light btn-clip btn-sm" data-copy="<?= htmlspecialchars($contactNumber, ENT_QUOTES, 'UTF-8'); ?>" title="Copy">
                    <i class="bi bi-clipboard"></i>
                  </button>
                <?php endif; ?>
              </span>
            </div>
          </div>

          <div class="col-md-6">
            <div class="info-tile">
              <span class="label"><i class="bi bi-house-door me-1"></i>Address</span>
              <span class="value">
                <?php $resStreetAddress = $resStreetAddress ?? "No address provided"; ?>
                <?= htmlspecialchars($resStreetAddress, ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </div>
          </div>

          <div class="col-md-4">
            <div class="info-tile">
              <span class="label"><i class="bi bi-shield-check me-1"></i>Citizenship</span>
              <span class="value"><?= htmlspecialchars((string)($citizenship ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-tile">
              <span class="label"><i class="bi bi-sunrise me-1"></i>Religion</span>
              <span class="value"><?= htmlspecialchars((string)($religion ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="info-tile">
              <span class="label"><i class="bi bi-briefcase me-1"></i>Occupation</span>
              <span class="value"><?= htmlspecialchars((string)($occupation ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Linked Family -->
      <div id="linkedFamilyContent" class="tab-pane-card" style="display:none;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="section-title mb-0"><i class="bi bi-people me-2"></i>Linked Family Members</h6>
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#linkFamilyModal">
            <i class="bi bi-link-45deg me-1"></i> Link Family Member
          </button>
        </div>

        <?php if (!empty($familyMembers)): ?>
          <div class="list-group list-group-flush rounded-3 overflow-hidden">
            <?php foreach ($familyMembers as $member): ?>
              <?php
                $fullName = htmlspecialchars($member['full_name']);
                $label    = htmlspecialchars($member['label']);
                $age      = htmlspecialchars($member['age']);
                $status   = htmlspecialchars($member['status']);
              ?>
              <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-medium"><?= $fullName; ?></div>
                  <div class="text-muted small"><?= "{$label} • Age {$age}"; ?></div>
                </div>
                <?php if ($status === 'pending'): ?>
                  <span class="badge rounded-pill bg-warning text-dark">Pending</span>
                <?php else: ?>
                  <span class="badge rounded-pill bg-success-subtle text-success">Verified</span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="bi bi-people fs-2 mb-2"></i>
            <p class="text-muted mb-0">No family members linked yet.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Emergency Contact -->
      <div id="emergencyContactContent" class="tab-pane-card" style="display:none;">
        <h6 class="section-title"><i class="bi bi-life-preserver me-2"></i>Emergency Contact</h6>
        <div class="row g-3 g-md-4">
          <div class="col-md-6">
            <div class="info-tile">
              <span class="label">Relationship</span>
              <span class="value"><?= htmlspecialchars((string)($emergencyContactRelationship ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-tile">
              <span class="label">Email</span>
              <span class="value"><?= htmlspecialchars((string)($emergencyContactEmail ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-tile">
              <span class="label">Name</span>
              <span class="value"><?= htmlspecialchars((string)($emergencyContactName ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-tile">
              <span class="label">Phone</span>
              <span class="value"><?= htmlspecialchars((string)($emergencyContactPhone ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
          <div class="col-12">
            <div class="info-tile">
              <span class="label">Address</span>
              <span class="value"><?= htmlspecialchars((string)($emergencyContactAddress ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /tab-contents -->
  </div><!-- /profile-card -->
</div>
