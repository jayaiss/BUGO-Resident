<div class="modal fade" id="purposeModal" tabindex="-1" aria-labelledby="purposeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="purposeModalLabel">
          <i class="bi bi-clipboard-check"></i> Enter Purpose
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <!-- Certificates picker -->
        <div class="section-card mb-3">
          <label class="form-label mb-2">Certificates</label>
          <div class="input-group input-sm">
            <span class="input-group-text"><i class="bi bi-patch-check"></i></span>
            <select id="CertificateSelect" class="form-select" aria-label="Choose certificate">
              <option value="">-- Choose Certificate --</option>
            </select>
            <button class="btn btn-success" id="addCertificateBtn">
              <i class="bi bi-plus-circle"></i> Add
            </button>
          </div>
          <div class="form-hint mt-1">Tip: BESO Application must be scheduled alone.</div>
          <!-- Requirements -->
          <div id="requirementsContainer" class="section-card mb-3" style="display:none;" aria-live="polite">
            <label class="form-label">Requirements to bring</label>
            <ul id="requirementsList" class="mb-0"></ul>
            <div class="form-hint mt-1">Bring originals and photocopies if available.</div>
          </div>
          <!-- Chips -->
          <ul id="selectedCertificatesList" class="mt-3" aria-live="polite"></ul>
        </div>

        <!-- Cedula options -->
        <div id="cedulaContainer" class="section-card mb-3 hidden" aria-live="polite">
          <label for="cedulaOptionSelect" class="form-label">Cedula Option</label>
          <select id="cedulaOptionSelect" class="form-select" required>
            <option value="" disabled selected>-- Select Cedula Mode --</option>
            <option value="request">Request Cedula</option>
            <option value="upload">Upload Existing Cedula</option>
          </select>
          <div class="form-hint mt-1">
            Choose how you want to handle your Cedula. “Request” will compute the estimated fee; “Upload” requires an image/PDF.
          </div>
        </div>

        <!-- Request Cedula Fields -->
        <div id="cedulaRequestFields" class="section-card mb-3 hidden">
          <label for="cedulaIncome" class="form-label">Declared Income (₱)</label>
          <div class="input-group input-sm">
            <span class="input-group-text"><i class="bi bi-cash-coin"></i></span>
            <input type="number" id="cedulaIncome" class="form-control" min="0" step="0.01" placeholder="e.g. 10000.00" inputmode="decimal">
          </div>
          <div class="form-hint">We’ll estimate your Cedula payment before you submit.</div>
        </div>

        <!-- Upload Cedula Fields -->
        <div id="cedulaUploadFields" class="section-card mb-3 hidden">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="cedula_number" class="form-label">Cedula Number</label>
              <input type="text" id="cedula_number" class="form-control" placeholder="Enter cedula number">
            </div>
            <div class="col-md-6">
              <label for="date_issued" class="form-label">Date Issued</label>
              <input type="date" id="date_issued" class="form-control">
            </div>
            <div class="col-md-6">
              <label for="issued_at" class="form-label">Issued At</label>
              <input type="text" id="issued_at" class="form-control" placeholder="e.g. Barangay Hall">
            </div>
            <div class="col-md-6">
              <label for="cedula_upload_income" class="form-label">Declared Income (₱)</label>
              <input type="number" id="cedula_upload_income" class="form-control" min="0" step="0.01" placeholder="e.g. 10000.00" inputmode="decimal">
            </div>
            <div class="col-12">
              <label for="upload_path" class="form-label">Upload Cedula (PDF/JPG/PNG)</label>
              <input type="file" class="form-control" id="upload_path" accept=".pdf,.jpg,.jpeg,.png">
              <div class="file-hint"><i class="bi bi-info-circle"></i> Max 5MB. Make sure the text is readable.</div>
            </div>
          </div>
        </div>

        <!-- Purpose -->
        <div id="purposeContainer" class="section-card mb-3 hidden">
          <label for="purposeSelect" class="form-label">Select Purpose</label>
          <select id="purposeSelect" class="form-select" required>
            <option value="">-- Choose Purpose --</option>
            <option value="others">Others</option>
          </select>
          <div class="form-hint">Choose the most accurate purpose.</div>
        </div>

        <!-- Custom Purpose Input (kept for compatibility) -->
        <div id="customPurposeContainer" class="section-card mb-3 hidden">
          <label for="purposeInput" class="form-label">Custom Purpose</label>
          <input type="text" id="purposeInput" class="form-control" placeholder="Enter custom purpose">
        </div>

        <!-- Extra fields (BESO) -->
        <div id="additionalFieldsContainer" class="section-card mb-1 hidden">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="education" class="form-label">Educational Attainment</label>
              <input type="text" id="education" class="form-control" placeholder="e.g. College Graduate">
            </div>
            <div class="col-md-6">
              <label for="course" class="form-label">Course</label>
              <input type="text" id="course" class="form-control" placeholder="e.g. BS Information Technology">
            </div>
          </div>
          <div class="form-hint mt-1">Required for BESO Application.</div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
          <i class="bi bi-x-circle"></i> Close
        </button>
        <button type="button" class="btn btn-primary" id="submitScheduleBtn" onclick="submitschedule()">
          <span class="btn-text"><i class="bi bi-send-check"></i> Submit Schedule</span>
          <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
        </button>
      </div>

    </div>
  </div>
</div>
