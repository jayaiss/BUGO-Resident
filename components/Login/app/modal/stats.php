<!-- Stats -->
<section class="py-5">
  <div class="container container-max">
    <div class="row g-4">
      <div class="col-md-4" data-reveal>
        <div class="stat-card hover-lift">
          <div class="stat-icon"><i class="bi bi-calendar-check fs-4" aria-hidden="true"></i></div>
          <div>
            <div class="stat-value" data-count="<?= (int)$total_appointments ?>">0</div>
            <div class="text-muted">Appointments Managed</div>
          </div>
        </div>
      </div>
      <div class="col-md-4" data-reveal>
        <div class="stat-card hover-lift">
          <div class="stat-icon"><i class="bi bi-graph-up-arrow fs-4" aria-hidden="true"></i></div>
          <div>
            <div class="stat-value" data-count="<?= (int)$certificates_percentage ?>">0</div>
            <div class="text-muted">% Certificates Issued</div>
          </div>
        </div>
      </div>
      <div class="col-md-4" data-reveal>
        <div class="stat-card hover-lift">
          <div class="stat-icon"><i class="bi bi-people-fill fs-4" aria-hidden="true"></i></div>
          <div>
            <div class="stat-value" data-count="<?= (int)$active_residents ?>">0</div>
            <div class="text-muted">Active Residents</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>