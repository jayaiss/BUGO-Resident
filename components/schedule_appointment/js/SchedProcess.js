let currentAge = 0;
let cedulaTargetResId = null;
let residentData = {};
let selectedCertificates = [];
let certificatePurposes = {};

// === DOM helpers ===
const $  = (id) => document.getElementById(id);
const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

function forceShow(el, fallbackDisplay = 'block') {
  if (!el) return;
  el.classList.remove('d-none', 'visually-hidden', 'invisible');
  el.hidden = false;
  el.style?.removeProperty?.('display');
  if (el.classList?.contains('collapse')) el.classList.add('show');
  if (getComputedStyle(el).display === 'none') {
    el.style.setProperty('display', fallbackDisplay, 'important');
  }
}

function forceHide(el) {
  if (!el) return;
  if (el.classList?.contains('collapse')) el.classList.remove('show');
  el.classList?.add('d-none');
  el.hidden = true;
  el.style?.setProperty?.('display', 'none', 'important');
}

function resetInputs(scope) {
  if (!scope) return;
  $$('.is-invalid', scope).forEach(n => n.classList.remove('is-invalid'));
  $$('input,select,textarea', scope).forEach(n => {
    if (n.type === 'file') n.value = '';
    else if (n.type === 'checkbox' || n.type === 'radio') n.checked = false;
    else n.value = '';
  });
}

function applyCedulaMode(mode) {
  const req = $('cedulaRequestFields');
  const up  = $('cedulaUploadFields');
  const inc = $('cedulaIncomeContainer');
  [req, up, inc].forEach(el => { resetInputs(el); forceHide(el); });

  if (mode === 'request') {
    forceShow(req);
    forceShow(inc);
    (req?.querySelector('input,select,textarea') || inc?.querySelector('input'))?.focus?.();
  } else if (mode === 'upload') {
    forceShow(up);
    up?.querySelector('input,select,textarea')?.focus?.();
  }
}

/* =========================================================
   Chips: render + global delegated click on the remove (×)
========================================================= */
function renderSelectedCertificates() {
  const box = $("selectedCertificatesList");
  if (!box) return;

  box.innerHTML = "";
  selectedCertificates.forEach(lower => {
    const label   = lower.replace(/\b\w/g, m => m.toUpperCase());
    const purpose = certificatePurposes[lower] || "";

    const li = document.createElement("li");
    li.className = "list-group-item d-inline-flex align-items-center gap-2 rounded-pill me-2 mb-2";
    li.dataset.cert = lower;
    li.innerHTML = `
      <i class="bi bi-award"></i>
      <strong>${label}</strong>${purpose ? ` — <span>${purpose}</span>` : ""}
      <button type="button"
              class="btn btn-sm p-0 ms-1"
              aria-label="Remove"
              title="Remove"
              data-action="remove-cert"
              data-cert="${lower}">&times;</button>
    `;
    box.appendChild(li);
  });
}

// Catch clicks anywhere in the document (works inside modals)
document.addEventListener("click", (e) => {
  const btn = e.target.closest?.('[data-action="remove-cert"]');
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();

  const lower = (btn.dataset.cert || "").toLowerCase();
  if (!lower) return;

  // Update data model
  selectedCertificates = selectedCertificates.filter(c => c !== lower);
  delete certificatePurposes[lower];

  // Cedula cleanup
  if (lower === "cedula") {
    const sel = $("cedulaOptionSelect");
    if (sel) sel.value = "";
    ["cedulaContainer","cedulaRequestFields","cedulaUploadFields"].forEach(id => {
      const el = $(id); if (el) el.style.display = "none";
    });
    $("cedulaOptionSelect")?.classList.remove("border","border-danger");
  }

  renderSelectedCertificates();
  updateCertificateUI();
});

/* ======================
   MAIN INITIALIZATION
====================== */
document.addEventListener("DOMContentLoaded", function () {
  // 🧼 Reset when the Purpose Modal opens — with NULL SAFEGUARDS
  $("purposeModal")?.addEventListener("show.bs.modal", () => {
    selectedCertificates = [];
    certificatePurposes = {};
    renderSelectedCertificates();

    const hideIds = [
      "cedulaContainer","cedulaRequestFields","cedulaUploadFields",
      "purposeContainer","customPurposeContainer",
      "additionalFieldsContainer","cedulaIncomeContainer"
    ];
    hideIds.forEach(id => { const el = $(id); if (el) el.style.display = "none"; });

    const clearSelects = ["purposeSelect"];
    clearSelects.forEach(id => { const el = $(id); if (el) el.innerHTML = ""; });

    const clearValues = ["purposeInput","cedulaOptionSelect"];
    clearValues.forEach(id => { const el = $(id); if (el) el.value = ""; });
  });

  // Initial modal
  const initialModal = new bootstrap.Modal($('appointmentTypeModal'));
  initialModal.show();
  setTimeout(() => { $('personalAppointmentBtn')?.focus?.(); }, 200);

  // Show/hide custom purpose
  $("purposeSelect")?.addEventListener("change", function () {
    const c = $("customPurposeContainer");
    if (!c) return;
    c.style.display = (this.value || "").toLowerCase() === "others" ? "block" : "none";
  });

  $('childAppointmentBtn')?.addEventListener('click', () => {
    bootstrap.Modal.getInstance($('appointmentTypeModal'))?.hide();
    fetch("ajax/fetch_children.php")
      .then(res => res.json())
      .then(children => {
        const tbody = document.querySelector('#childrenTable tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        children.forEach(child => {
          const row = document.createElement('tr');
          row.innerHTML = `
            <td>${child.full_name}</td>
            <td>${child.birth_date}</td>
            <td><button class="btn btn-outline-success btn-sm" onclick="selectChild(${child.res_id}, '${child.full_name}')">Select</button></td>
          `;
          tbody.appendChild(row);
        });
        new bootstrap.Modal($('childSelectionModal')).show();
      });
  });

  $("CertificateSelect")?.addEventListener("change", handleCertificateChange);
  document.querySelector("#CertificateSelect")?.addEventListener("change", function () {
    const cert = (this.value || "").toLowerCase();
    if (cert === "cedula") {
      $("cedulaOptionSelect")?.focus?.();
      $("cedulaOptionSelect")?.classList.add("border","border-warning");
    }
  });

  const cedulaDropdown = $("cedulaOptionSelect");
  const submitBtn = $("submitScheduleBtn");

  if (cedulaDropdown) {
    if (submitBtn) submitBtn.disabled = true;
    cedulaDropdown.addEventListener('change', () => {
      const mode = cedulaDropdown.value; // '', 'request', 'upload'
      applyCedulaMode(mode);
      if (submitBtn) submitBtn.disabled = !mode;
      if (mode) cedulaDropdown.classList.remove('border','border-danger');
    });
    if (cedulaDropdown.value) applyCedulaMode(cedulaDropdown.value);
  }

  ["setCedulaBtn","uploadCedulaBtn","newsetCedulaBtn","newuploadCedulaBtn"].forEach(id => {
    const btn = $(id);
    if (!btn) return;
    btn.addEventListener("click", function () {
      if (cedulaTargetResId) {
        const page = id.includes("upload") ? "upload_cedula_form" : "cedula";
        window.location.href = `index_admin.php?page=<?= urlencode(encrypt('${page}')) ?>&res_id=${cedulaTargetResId}`;
      }
    });
  });

  $("addCertificateBtn")?.addEventListener("click", function () {
    // 0) Certificate
    const select = $("CertificateSelect");
    const selectedValue = select?.value;
    if (!selectedValue)
      return Swal.fire({ icon:'warning', title:'Oops!', text:'Please select a certificate.', confirmButtonText:'OK' });

    const lower = selectedValue.toLowerCase();

    // 1) Purpose
    const purposeSelectVal = $("purposeSelect")?.value || "";
    const customPurpose    = $("purposeInput")?.value?.trim() || "";
    const purpose          = purposeSelectVal === "others" ? customPurpose : purposeSelectVal;

    if (lower !== "cedula" && !purpose) {
      Swal.fire({ icon:'warning', title:'Oops!', text:'Please choose a purpose for this certificate.', confirmButtonText:'OK' })
        .then(() => {
          if ($("CertificateSelect")) $("CertificateSelect").value = "";
          if ($("purposeSelect")) $("purposeSelect").innerHTML = "";
          if ($("purposeContainer")) $("purposeContainer").style.display = "none";
          if ($("customPurposeContainer")) $("customPurposeContainer").style.display = "none";
          if ($("purposeInput")) $("purposeInput").value = "";
          renderRequirementsFor("");
        });
      return;
    }

    // 2) Guards
    if (selectedCertificates.includes(lower))
      return Swal.fire({ icon:'warning', title:'Oops!', text:'This certificate is already added.', confirmButtonText:'OK' });

    if (lower === "beso application" && selectedCertificates.length > 0)
      return Swal.fire({ icon:'warning', title:'Oops!', text:'BESO Application must be scheduled alone.', confirmButtonText:'OK' });

    if (selectedCertificates.includes("beso application"))
      return Swal.fire({ icon:'warning', title:'Oops!', text:'You cannot add other certificates when BESO Application is selected.', confirmButtonText:'OK' });

    // 3) Save
    selectedCertificates.push(lower);
    certificatePurposes[lower] = purpose;

    // 4) Cedula reset
    const cedSel = $("cedulaOptionSelect");
    if (cedSel) cedSel.selectedIndex = 0;

    if (lower === "cedula") {
      clearPurposeUI();
      if ($("cedulaOptionSelect")) $("cedulaOptionSelect").value = "";
      ["cedulaContainer","cedulaRequestFields","cedulaUploadFields"].forEach(id => { const el = $(id); if (el) el.style.display = "none"; });
      $("cedulaOptionSelect")?.classList.remove("border","border-danger");
    }

    updateCertificateUI();

    // 5) Repaint chips (replaces the old manual <li> maker)
    renderSelectedCertificates();

    // 6) Reset pickers
    if (select) select.value = "";
    if ($("purposeSelect")) $("purposeSelect").innerHTML = "";
    if ($("purposeInput")) $("purposeInput").value = "";
    if ($("purposeContainer")) $("purposeContainer").style.display = "none";
    if ($("customPurposeContainer")) $("customPurposeContainer").style.display = "none";
    renderRequirementsFor("");
  });

  updateCertificateUI();
});

function clearPurposeUI() {
  const purposeSelectEl = $("purposeSelect");
  const purposeInputEl  = $("purposeInput");
  if (purposeSelectEl) { purposeSelectEl.innerHTML = ""; purposeSelectEl.value = ""; }
  if (purposeInputEl)  purposeInputEl.value = "";
  if ($("purposeContainer")) $("purposeContainer").style.display = "none";
  if ($("customPurposeContainer")) $("customPurposeContainer").style.display = "none";
}

function handleCertificateChange() {
  const selectedValue = (this.value || "").toLowerCase();

  const resetFields = () => {
    this.value = "";
    if ($("purposeSelect")) $("purposeSelect").innerHTML = "";
    if ($("purposeInput")) $("purposeInput").value = "";
    if ($("purposeContainer")) $("purposeContainer").style.display = "none";
    if ($("customPurposeContainer")) $("customPurposeContainer").style.display = "none";
    if ($("additionalFieldsContainer")) $("additionalFieldsContainer").style.display = "none";
    if ($("cedulaIncomeContainer")) $("cedulaIncomeContainer").style.display = "none";
    renderRequirementsFor("");
    clearPurposeUI();
  };

  renderRequirementsFor(selectedValue);

  // Reset Cedula bits
  if ($("cedulaContainer")) $("cedulaContainer").style.display = "none";
  if ($("additionalFieldsContainer")) $("additionalFieldsContainer").style.display = "none";
  if ($("cedulaRequestFields")) $("cedulaRequestFields").style.display = "none";
  if ($("cedulaUploadFields")) $("cedulaUploadFields").style.display = "none";

  const hasCedula = selectedCertificates.includes("cedula");

  if (!hasCedula && selectedValue !== "cedula") {
    fetch(`ajax/check_cedula_status.php?res_id=${currentResId}`)
      .then(res => res.json())
      .then(data => {
        const status = (data.status || "").toLowerCase();
        if (status === "valid" || status === "released") {
          continueCertificateCheck(selectedValue);
          enableSubmitButton();
        } else if (status === "pending") {
          Swal.fire({ icon:'info', title:'Cedula Pending', text:'Your Cedula is still pending. Please wait for approval.', confirmButtonText:'OK' })
            .then(() => resetFields()); return;
        } else if (status === "approved") {
          Swal.fire({ icon:'info', title:'Cedula Approved', text:'Your Cedula is approved. Please wait until it is released.', confirmButtonText:'OK' })
            .then(() => resetFields()); return;
        } else {
          Swal.fire({ icon:'warning', title:'Cedula Required', text:'You must add Cedula first before adding other certificates.', confirmButtonText:'OK' })
            .then(() => resetFields()); return;
        }
      })
      .catch(() => resetFields());
    return;
  }

  if (selectedValue === "cedula") {
    clearPurposeUI();
    fetch(`ajax/check_cedula_status.php?res_id=${currentResId}`)
      .then(res => res.json())
      .then(data => {
        const status = (data.status || "").toLowerCase();
        if (status === "pending") {
          Swal.fire({ icon:'info', title:'Cedula Pending', text:'Your Cedula is pending. Please wait for approval.', confirmButtonText:'OK' })
            .then(() => resetFields());
          renderRequirementsFor(""); clearPurposeUI(); return;
        }
        if (status === "approved") {
          Swal.fire({ icon:'info', title:'Cedula Approved', text:'Your Cedula is approved. Please wait until it is released.', confirmButtonText:'OK' })
            .then(() => resetFields());
          renderRequirementsFor(""); clearPurposeUI(); return;
        }
        if ($("cedulaContainer")) $("cedulaContainer").style.display = "block";
        if ($("cedulaFullName"))  $("cedulaFullName").value  = data.full_name;
        if ($("cedulaBirthDate")) $("cedulaBirthDate").value = data.birth_date;
        if ($("cedulaBirthPlace"))$("cedulaBirthPlace").value= data.birth_place;
        if ($("cedulaAddress"))   $("cedulaAddress").value   = data.full_address;
      });
    return;
  }

  const certRequiresCedula = ["barangay clearance","barangay indigency","beso application","barangay residency"];
  if (certRequiresCedula.includes(selectedValue)) {
    fetch(`ajax/check_cedula_status.php?res_id=${currentResId}`)
      .then(res => res.json())
      .then(data => {
        const status = (data.status || "").toLowerCase();
        if (status === "valid" || status === "released") {
          if (selectedValue === "barangay clearance") {
            checkOngoingCases();
          } else if (selectedValue === "beso application") {
            checkBarangayResidencyForBeso(selectedValue);
          } else {
            continueCertificateCheck(selectedValue);
          }
          enableSubmitButton();
        } else if (status === "pending") {
          Swal.fire({ icon:'info', title:'Cedula Pending', text:'Your Cedula is pending. Please wait for approval.', confirmButtonText:'OK' })
            .then(() => resetFields()); return;
        } else if (status === "approved") {
          Swal.fire({ icon:'info', title:'Cedula Approved', text:'Your Cedula is approved. Please wait until it is released.', confirmButtonText:'OK' })
            .then(() => resetFields()); return;
        }
        continueCertificateCheck(selectedValue);
        enableSubmitButton();
      });
    return;
  }

  if (selectedValue === "beso application") {
    if ($("additionalFieldsContainer")) $("additionalFieldsContainer").style.display = "block";
    if ($("education")) $("education").value = "";
    if ($("course")) $("course").value = "";
  } else {
    if ($("additionalFieldsContainer")) $("additionalFieldsContainer").style.display = "none";
  }

  continueCertificateCheck(selectedValue);
  enableSubmitButton();

  if (selectedValue === "cedula") {
    removeCertificate("cedula");
    removeDependentCertificates();
  }
}

function removeCertificate(cert) {
  selectedCertificates = selectedCertificates.filter(item => item !== cert);
  renderSelectedCertificates();
  updateCertificateUI();
  if ($("cedulaOptionSelect")) $("cedulaOptionSelect").value = "";
  if ($("cedulaContainer")) $("cedulaContainer").style.display = "none";
  if ($("cedulaRequestFields")) $("cedulaRequestFields").style.display = "none";
  if ($("cedulaUploadFields")) $("cedulaUploadFields").style.display = "none";
}

function removeDependentCertificates() {
  const certRequiresCedula = ["barangay clearance","barangay indigency","beso application","barangay residency"];
  selectedCertificates = selectedCertificates.filter(cert => !certRequiresCedula.includes(cert));
  renderSelectedCertificates();
  updateCertificateUI();
}

function updateCertificateUI() {
  const cedulaContainer = $("cedulaContainer");
  if (!cedulaContainer) return;
  cedulaContainer.style.display = selectedCertificates.includes("cedula") ? "block" : "none";
}

const requirementsByCert = {
  "barangay clearance": ["Valid ID"],
  "barangay indigency": ["Valid ID"],
  "barangay residency": ["Valid ID"],
  "beso application":   ["Valid ID"]
};

function renderRequirementsFor(certName) {
  const container = $("requirementsContainer");
  const list = $("requirementsList");
  if (!container || !list) return;
  const key = (certName || "").toLowerCase().trim();
  if (!key || !requirementsByCert[key] || requirementsByCert[key].length === 0) {
    container.style.display = "none"; list.innerHTML = ""; return;
  }
  list.innerHTML = requirementsByCert[key].map(item => `<li><i class="bi bi-check2-circle me-1"></i>${item}</li>`).join("");
  container.style.display = "block";
}

function checkBarangayResidencyForBeso(selectedValue) {
  if (selectedValue !== "beso application") return;
  fetch(`ajax/check_residency_used_for_beso.php?res_id=${currentResId}`)
    .then(res => res.json())
    .then(data => {
      if (data.used) { $("CertificateSelect").value = ""; renderRequirementsFor(""); return; }
      if (data.has_beso_record) {
        Swal.fire({ icon:'warning', title:'BESO Already Exists', text:'You already have an existing BESO record.', confirmButtonText:'OK' })
          .then(() => { $("CertificateSelect").value = ""; renderRequirementsFor(""); });
        return;
      }
      if (!data.has_residency_ftj) {
        Swal.fire({ icon:'info', title:'Barangay Residency Required', text:'To apply for BESO, you must first obtain a Barangay Residency with purpose “First Time Jobseeker.”', confirmButtonText:'OK' })
          .then(() => { $("CertificateSelect").value = ""; renderRequirementsFor(""); });
        return;
      }
      continueCertificateCheck(selectedValue);
      enableSubmitButton();
    })
    .catch(() => {
      Swal.fire({ icon:'error', title:'Validation Error', text:'Error validating Residency. Please try again.', confirmButtonText:'Close' });
    });
}

function checkOngoingCases() {
  fetch(`ajax/check_ongoing_cases.php?res_id=${currentResId}`)
    .then(res => res.json())
    .then(data => {
      if (data.ongoing_cases > 0) {
        Swal.fire({ icon:'error', title:'Ongoing Case Detected', text:'You cannot schedule Barangay Clearance while you have ongoing cases.', confirmButtonText:'OK' })
          .then(() => { $("CertificateSelect").value = "";renderRequirementsFor(""); });
        return;
      }
      continueCertificateCheck("barangay clearance"); //e add dri if gusto e apply sa uban cert
      enableSubmitButton();
    })
    .catch(() => {
      Swal.fire({ icon:'error', title:'Request Failed', text:'Error checking ongoing cases. Please try again.', confirmButtonText:'Close' });
    });
}

function enableSubmitButton() {
  const submitBtn = $("submitScheduleBtn");
  if (submitBtn) submitBtn.disabled = false;
}

function continueCertificateCheck(cert) {
  const purposeSelect = $("purposeSelect");
  if (purposeSelect) purposeSelect.innerHTML = '<option value="">-- Choose Purpose --</option>';
  if ($("purposeContainer")) $("purposeContainer").style.display = "none";
  if ($("customPurposeContainer")) $("customPurposeContainer").style.display = "none";
  if ($("additionalFieldsContainer")) $("additionalFieldsContainer").style.display = "none";

  fetch(`ajax/fetch_pending_certificates.php?res_id=${currentResId}`, { method:'GET', headers:{ 'X-Requested-With':'XMLHttpRequest' }})
    .then(res => res.json())
    .then(pendingCerts => {
      if (pendingCerts.map(c => c.toLowerCase()).includes(cert)) {
        Swal.fire({ icon:'warning', title:'Already Pending', text:`You already have a pending application for ${cert}.`, confirmButtonText:'OK' })
          .then(() => { $("CertificateSelect").value = ""; renderRequirementsFor(""); });
        return;
      }

      if (cert === "barangay clearance") {
        fetch(`ajax/get_resident_age.php?res_id=${currentResId}`, { method:'GET', headers:{ 'X-Requested-With':'XMLHttpRequest' }})
          .then(res => res.json())
          .then(data => {
            residentData = data;
            currentAge = parseInt(data.age);
            if (currentAge >= 18) {
              fetch(`ajax/check_ongoing_cases.php?res_id=${currentResId}`, { method:'GET', headers:{ 'X-Requested-With':'XMLHttpRequest' }})
                .then(res => res.json())
                .then(data => {
                  if (data.ongoing_cases > 0) {
                    Swal.fire({ icon:'error', title:'Ongoing Case Detected', text:'You cannot schedule Barangay Clearance while you have ongoing cases.', confirmButtonText:'OK' })
                      .then(() => { $("CertificateSelect").value = ""; renderRequirementsFor("");});
                    return;
                  } else {
                    loadPurposes(cert);
                  }
                })
                .catch(() => {
                  Swal.fire({ icon:'error', title:'Request Failed', text:'Error checking ongoing cases. Please try again.', confirmButtonText:'Close' })
                    .then(() => { $("CertificateSelect").value = ""; });
                });
            } else {
              Swal.fire({ icon:'warning', title:'Age Restriction', text:'You must be 18 or older to apply for Barangay Clearance.', confirmButtonText:'OK' })
                .then(() => { $("CertificateSelect").value = ""; renderRequirementsFor(""); });
            }
          })
          .catch(() => {
            Swal.fire({ icon:'error', title:'Error', text:'Error fetching resident age. Please try again.', confirmButtonText:'Close' })
              .then(() => { $("CertificateSelect").value = ""; });
          });
        return;
      }

      if (cert === "beso application") {
        fetch(`ajax/get_resident_age.php?res_id=${currentResId}`, { method:'GET', headers:{ 'X-Requested-With':'XMLHttpRequest' }})
          .then(res => res.json())
          .then(data => {
            residentData = data;
            currentAge = parseInt(data.age);
            if (currentAge >= 18) {
              fetch(`ajax/check_residency_used_for_beso.php?res_id=${currentResId}`, { method:'GET', headers:{ 'X-Requested-With':'XMLHttpRequest' }})
                .then(res => res.json())
                .then(data => {
                  if (data.used || data.has_beso_record) {
                    Swal.fire({ icon:'warning', title:'Already Used for BESO', text:'You already used your Barangay Residency for BESO or have an existing record.', confirmButtonText:'OK' })
                      .then(() => { $("CertificateSelect").value = ""; renderRequirementsFor(""); });
                    return;
                  }
                  if (!data.has_residency_ftj) {
                    Swal.fire({ icon:'info', title:'FTJ Residency Required', text:"To apply for BESO, you must first obtain a Barangay Residency with purpose 'First Time Jobseeker.'", confirmButtonText:'OK' })
                      .then(() => { $("CertificateSelect").value = ""; renderRequirementsFor(""); });
                    return;
                  }
                  if ($("additionalFieldsContainer")) $("additionalFieldsContainer").style.display = "block";
                  loadPurposes(cert);
                });
            } else {
              Swal.fire({ icon:'warning', title:'Age Restriction', text:'You must be 18 or older to apply for BESO.', confirmButtonText:'OK' })
                .then(() => { $("CertificateSelect").value = ""; renderRequirementsFor(""); });
            }
          })
          .catch(() => {
            Swal.fire({ icon:'error', title:'Error', text:'Error fetching resident age. Please try again.', confirmButtonText:'Close' });
          });
        return;
      }

      loadPurposes(cert);
    });
}

function loadPurposes(cert) {
  const purposeSelect = $("purposeSelect");
  fetch(`ajax/fetch_purposes_by_certificate.php?cert=${encodeURIComponent(cert)}`)
    .then(res => res.json())
    .then(data => {
      data.forEach(purpose => {
        const opt = document.createElement("option");
        opt.value = purpose.purpose_name;
        opt.textContent = purpose.purpose_name;
        purposeSelect?.appendChild(opt);
      });

      if ($("purposeContainer")) $("purposeContainer").style.display = "block";

      if (purposeSelect?.dataset.ftjListenerAttached) return;
      if (purposeSelect) purposeSelect.dataset.ftjListenerAttached = "true";

      purposeSelect?.addEventListener("change", function () {
        const selectedPurpose = this.value;
        const currentCert = ($("CertificateSelect")?.value || "").toLowerCase();

        if (selectedPurpose === "First Time Jobseeker" && currentCert === "barangay residency") {
          fetch(`ajax/check_residency_used_for_beso.php?res_id=${currentResId}`)
            .then(res => res.json())
            .then(({ has_residency_ftj }) => {
              if (has_residency_ftj) {
                Swal.fire({ icon:'warning', title:'Already Exists', text:'You already have a Barangay Residency for "First Time Jobseeker".', confirmButtonText:'OK' })
                  .then(() => { this.value = ""; });
              } else {
                checkFirstTimeJobseekerRecord(currentResId);
                renderRequirementsFor("");
              }
            })
            .catch(() => {
              Swal.fire({ icon:'error', title:'Verification Failed', text:'Could not verify BESO application status. Try again later.', confirmButtonText:'Close' })
                .then(() => { this.value = ""; });
            });
          return;
        }

        if (selectedPurpose === "First Time Jobseeker" && ["barangay clearance","barangay indigency"].includes(currentCert)) {
          fetch(`ajax/check_residency_used_for_beso.php?res_id=${currentResId}`)
            .then(res => res.json())
            .then(data => {
              if (!data.has_beso_record) {
                Swal.fire({ icon:'warning', title:'Missing BESO Application', text:`You cannot select "First Time Jobseeker" under ${currentCert} because you don't have an existing BESO Application.`, confirmButtonText:'OK' });
                this.value = "";
                if ($("CertificateSelect")) $("CertificateSelect").value = "";
                if ($("purposeContainer")) $("purposeContainer").style.display = "none";
                if ($("customPurposeContainer")) $("customPurposeContainer").style.display = "none";
                if (purposeSelect) purposeSelect.innerHTML = "";
                renderRequirementsFor("");
                return;
              }
              checkFirstTimeJobseekerUsage(currentCert);
            })
            .catch(() => {
              Swal.fire({ icon:'error', title:'Verification Failed', text:'Could not verify First Time Jobseeker usage. Try again later.', confirmButtonText:'Close' });
              this.value = "";
              if ($("CertificateSelect")) $("CertificateSelect").value = "";
              if ($("purposeContainer")) $("purposeContainer").style.display = "none";
              if ($("customPurposeContainer")) $("customPurposeContainer").style.display = "none";
              if (purposeSelect) purposeSelect.innerHTML = "";
            });
        } else if (selectedPurpose === "First Time Jobseeker") {
          checkFirstTimeJobseekerRecord(currentResId);
        }
      });
    });
}

// FTJ checks
function checkFirstTimeJobseekerRecord(resId) {
  fetch(`ajax/check_residency_used_for_beso.php?res_id=${resId}`)
    .then(res => res.json())
    .then(response => {
      if (response.has_residency) {
        Swal.fire({ icon:'warning', title:'Duplicate Record', text:"You already have a record for 'First Time Jobseeker'. You cannot select this purpose again.", confirmButtonText:'OK' })
          .then(() => { if ($("purposeSelect")) $("purposeSelect").value = ""; renderRequirementsFor(""); });
      }
    })
    .catch(() => {
      Swal.fire({ icon:'error', title:'Request Failed', text:'Error checking residency record. Please try again.', confirmButtonText:'Close' });
    });
}

function checkFirstTimeJobseekerUsage(cert) {
  fetch(`ajax/check_first_time_jobseeker_conflict.php?res_id=${currentResId}`)
    .then(res => res.json())
    .then(data => {
      if (data.is_pending == 1) {
        Swal.fire({
          icon: 'info',
          title: 'Pending Application',
          text: 'Your BESO Application is still pending.',
          confirmButtonText: 'OK'
        }).then(() => {
          if ($("purposeSelect")) $("purposeSelect").value = "";
          renderRequirementsFor("");
        });
        return; // stop here if pending
      }

      if (data.is_approved == 1) {
        Swal.fire({
          icon: 'success',
          title: 'Approved',
          text: 'Your BESO Application was approved, please wait for releasing.',
          confirmButtonText: 'OK'
        }).then(() => {
          if ($("purposeSelect")) $("purposeSelect").value = "";
          renderRequirementsFor("");
        });
        return;
      }

      if (data.is_approved_captain == 1) {
        Swal.fire({
          icon: 'success',
          title: 'Approved by Captain',
          text: 'Your BESO Application was approved by the Barangay Captain, please wait for releasing.',
          confirmButtonText: 'OK'
        }).then(() => {
          if ($("purposeSelect")) $("purposeSelect").value = "";
          renderRequirementsFor("");
        });
        return;
      }

      if (data.is_rejected == 1) {
        Swal.fire({
          icon: 'warning',
          title: 'Rejected',
          text: 'Your BESO Application was rejected.',
          confirmButtonText: 'OK'
        }).then(() => {
          if ($("purposeSelect")) $("purposeSelect").value = "";
          renderRequirementsFor("");
        });
        return;
      }

      const conflict =
        (cert === "barangay clearance" && data.used_for_clearance == 1) ||
        (cert === "barangay indigency" && data.used_for_indigency == 1);

      if (conflict) {
        Swal.fire({
          icon: 'warning',
          title: 'Already Used for FTJ',
          text: `You already used your ${cert} for First Time Jobseeker. You cannot use it again.`,
          confirmButtonText: 'OK'
        }).then(() => {
          if ($("purposeSelect")) $("purposeSelect").value = "";
          renderRequirementsFor("");
        });
      }
    })
    .catch(() => {
      Swal.fire({
        icon: 'error',
        title: 'Verification Failed',
        text: 'Could not verify First Time Jobseeker usage. Try again later.',
        confirmButtonText: 'Close'
      }).then(() => {
        if ($("purposeSelect")) $("purposeSelect").value = "";
        renderRequirementsFor("");
      });
    });
}


function selectChild(childId, childName) {
  bootstrap.Modal.getInstance($('childSelectionModal'))?.hide();
  currentResId = childId;
  const display = $('selectedForDisplay');
  const text = $('selectedForText');
  if (display) display.style.display = "block";
  if (text) text.textContent = childName;
  fetchResidentDetails(currentResId);
}

function fetchResidentDetails(resId) {
  fetch(`ajax/get_resident_age.php?res_id=${resId}`)
    .then(res => res.json())
    .then(data => {
      residentData = data;
      currentAge = parseInt(data.age);
      loadCertificatesWithAgeRestriction();
    });
}

function loadCertificatesWithAgeRestriction() {
  fetch("ajax/fetch_active_certificates.php")
    .then(response => response.json())
    .then(data => {
      const certSelect = $("CertificateSelect");
      if (!certSelect) return;
      certSelect.innerHTML = '<option value="">-- Choose Certificate --</option>';
      data.forEach(cert => {
        const lower = cert.Certificates_Name.toLowerCase();
        const option = document.createElement("option");
        option.value = cert.Certificates_Name;
        option.textContent = cert.Certificates_Name;
        if (currentAge < 18 && (lower === "beso application" || lower === "barangay clearance")) {
          option.disabled = true;
          option.textContent += " (18+ only)";
        }
        certSelect.appendChild(option);
      });
    });
}

// Updated submitSchedule function to handle the Cedula validation
function submitschedule() {
  console.log("▶️ Submitting schedule...");
  document.dispatchEvent(new Event('submitSchedule:start'));
  console.log("Selected Certificates:", selectedCertificates);

  const certRequiresCedula = ["barangay clearance","barangay indigency","beso application","barangay residency"];
  const isCedulaRequired = selectedCertificates.some(cert => certRequiresCedula.includes(cert));
  const hasCedula = selectedCertificates.includes("cedula");

  if (isCedulaRequired && !hasCedula) {
    fetch(`ajax/check_cedula_status.php?res_id=${currentResId}`)
      .then(res => res.json())
      .then(data => {
        const status = (data.status || "").toLowerCase();
        if (status === "valid" || status === "released") {
          submitForm();
        } else {
          Swal.fire({ icon:'warning', title:'Cedula Required', text:'You must add a valid Cedula before proceeding with this certificate.', confirmButtonText:'OK' });
        }
      })
      .catch(() => Swal.fire({ icon:'error', title:'Request Failed', text:'Error checking Cedula status. Please try again.', confirmButtonText:'Close' }));
    return;
  }

  if (hasCedula) {
    fetch(`ajax/check_cedula_status.php?res_id=${currentResId}`)
      .then(res => res.json())
      .then(() => submitForm())
      .catch(() => Swal.fire({ icon:'error', title:'Cedula Status Error', text:'Error checking Cedula status. Please try again.', confirmButtonText:'Close' }));
    return;
  }

  submitForm();
}

// Function to handle form submission
function submitForm() {
  console.log("▶️ Submitting the form...");

  const selectedPurpose = $("purposeSelect")?.value || "";
  const customPurpose = $("purposeInput")?.value?.trim() || "";
  const finalPurpose = selectedPurpose === "others" ? customPurpose : selectedPurpose;

  const education = $("education")?.value?.trim() || "";
  const course = $("course")?.value?.trim() || "";

  const selectedTime = ($("purposeModalLabel")?.textContent || "").replace('Enter Purpose for ', '').trim();
  const selectedDateStr = ($("selectedDateTitle")?.textContent || "").replace('Selected Date: ', '').trim();
  const selectedDate = new Date(selectedDateStr);
  const formattedDate = `${selectedDate.getFullYear()}-${String(selectedDate.getMonth() + 1).padStart(2, '0')}-${String(selectedDate.getDate()).padStart(2, '0')}`;

  if (selectedCertificates.length === 0) {
    Swal.fire({ icon:'warning', title:'No Certificate Selected', text:'Please select at least one certificate.', confirmButtonText:'OK' });
    return;
  }
  if (selectedCertificates.includes("beso application") && selectedCertificates.length > 1) {
    Swal.fire({ icon:'error', title:'Invalid Selection', text:'You can only schedule BESO Application alone.', confirmButtonText:'OK' });
    return;
  }

  const isBeso = selectedCertificates.includes("beso application");
  const isCedula = selectedCertificates.includes("cedula");

  if (isBeso && (!education || !course)) {
    Swal.fire({ icon:'warning', title:'Incomplete Information', text:'For BESO Application, please provide your Educational Attainment and Course.', confirmButtonText:'OK' });
    return;
  }

  const formData = {
    userId: currentResId,
    selectedDate: formattedDate,
    selectedTime: selectedTime,
    certificates: selectedCertificates.map(cert => ({ name: cert, purpose: certificatePurposes[cert] || "" })),
    purpose: finalPurpose
  };

  if (isBeso) { formData.education = education; formData.course = course; }

  // Cedula: Request or Upload Mode validation
  if (isCedula) {
    const cedulaDropdown = $("cedulaOptionSelect");
    if (!cedulaDropdown || !cedulaDropdown.options || cedulaDropdown.options.length === 0) {
      Swal.fire({ icon:'warning', title:'Cedula Dropdown Error', text:'Cedula dropdown not initialized properly.', confirmButtonText:'OK' });
      return;
    }
    const cedulaMode = (cedulaDropdown.options[cedulaDropdown.selectedIndex]?.value || "").trim();
    if (!cedulaMode) {
      Swal.fire({ icon:'warning', title:'Cedula Mode Required', text:'Please choose a Cedula mode before submitting.', confirmButtonText:'OK' })
        .then(() => {
          cedulaDropdown.classList.add("border","border-danger");
          cedulaDropdown.scrollIntoView({ behavior:"smooth", block:"center" });
        });
      return;
    }

    cedulaDropdown.classList.remove("border","border-danger");
    formData.cedulaMode = cedulaMode;

    if (cedulaMode === "request") {
      const incomeVal = $("cedulaIncome")?.value?.trim() || "";
      if (!incomeVal) {
        Swal.fire({ icon:'warning', title:'Income Required', text:'Please enter income for Cedula request.', confirmButtonText:'OK' });
        return;
      }

      function isExemptCert(c = {}) {
        const normalize = s => (s || '').toLowerCase().replace(/\s+/g, ' ').trim();
        const name = normalize(c.name);
        const purpose = normalize(c.purpose);
        if (name === 'beso application') return true;
        if (name === 'barangay indigency' && purpose === 'medical assistance') return true;
        const isFTJ = /first\s*-?\s*time\s*job\s*seeker(s)?/.test(purpose);
        if (isFTJ) return (name === 'barangay clearance' || name === 'barangay indigency');
        return false;
      }

const onlyCedula = formData.certificates.length === 1 && formData.certificates[0].name === 'cedula';
const anyExempt = formData.certificates.some(isExemptCert);
formData.certificate_payment = (!onlyCedula && !anyExempt) ? 50 : 0;

formData.income = incomeVal; // monthly income
const monthlyIncome = Number(String(formData.income).replace(/[^\d.]/g, '')) || 0;

let cedulaPayment = 0;
if (selectedCertificates.includes('cedula')) {
  // Step 1: gross = monthly × 12
  const gross = monthlyIncome * 12;

  // Step 2: payment = gross ÷ 1000
  const payment = Math.floor(gross / 1000);

  // Step 3: cedPayment = payment + 5
  const cedPayment = payment + 5;

  // Step 4: month-based interest
  const d = new Date((formData.selectedDate || '') + 'T00:00:00');
  const m = isNaN(d) ? null : d.getMonth() + 1; // Adjusted: Now, 1 = Jan, 2 = Feb, ..., 12 = Dec
  let rate = 0;
  if (m !== null && m >= 2) {
    rate = (0.04 + 0.02 * (m - 2)) / 100; // Mar=0.04%→0.0004, Apr=0.06%→0.0006, etc.
  }

  // Step 5: interestRate = gross × rate
  const interestRate = gross * rate;

  // Step 6: final = cedPayment + interestRate
  cedulaPayment = Math.round(cedPayment + interestRate);

  // Step 7: enforce default ₱50 if income is 0 or computed < 50
  if (cedulaPayment < 50) {
    cedulaPayment = 50;
  }
}

formData.cedula_payment = cedulaPayment.toFixed(2);


      window._pendingScheduleData = formData;
      bootstrap.Modal.getInstance($("purposeModal"))?.hide();
      generateAppointmentSummary(formData, residentData);
      new bootstrap.Modal($("confirmSubmitModal")).show();
      return;
    }

    if (cedulaMode === "upload") {
      const number = $("cedula_number")?.value?.trim();
      const issuedOn = $("date_issued")?.value?.trim();
      const issuedAt = $("issued_at")?.value?.trim();
      const income = $("cedula_upload_income")?.value?.trim();
      const uploadFile = $("upload_path")?.files?.[0];

      if (!number || !issuedOn || !issuedAt || !income || !uploadFile) {
        Swal.fire({ icon:'warning', title:'Incomplete Cedula Info', text:'Please complete all fields for Upload Cedula.', confirmButtonText:'OK' });
        return;
      }
      const reader = new FileReader();
      reader.onload = function (e) {
        const base64Image = e.target.result;
        Object.assign(formData, {
          cedula_number: number,
          issued_on: issuedOn,
          issued_at: issuedAt,
          income,
          cedula_image_base64: base64Image
        });

        window._pendingScheduleData = formData;
        bootstrap.Modal.getInstance($("purposeModal"))?.hide();
        generateAppointmentSummary(formData, residentData);
        new bootstrap.Modal($("confirmSubmitModal")).show();
      };
      reader.readAsDataURL(uploadFile);
      return;
    }
  }

  // Non-Cedula (or Cedula already validated)
  window._pendingScheduleData = formData;
  bootstrap.Modal.getInstance($("purposeModal"))?.hide();
  generateAppointmentSummary(formData, residentData);
  new bootstrap.Modal($("confirmSubmitModal")).show();
}

function generateAppointmentSummary(formData) {
  const summaryDiv = $("appointmentSummary");
  if (!summaryDiv) return;

  const certs = formData.certificates || [];
  const norm = s => (s || '').toLowerCase().trim().replace(/\s+/g, ' ');
  const isFTJ = p => /first\s*-?\s*time\s*jobseeker/i.test(p || '');
  const isMedicalAssist = p => /medical\s*assistance/i.test(p || '');

  const isChargeable = (c) => {
    const name = norm(c.name);
    const purpose = norm(c.purpose);
    if (name === 'cedula') return false;
    if (name === 'beso application') return false;
    if (name === 'barangay indigency' && isMedicalAssist(purpose)) return false;
    if ((name === 'barangay clearance' || name === 'barangay indigency') && isFTJ(purpose)) return false;
    return true;
  };

  const chargeable = certs.filter(isChargeable);
  const chargeableCount = chargeable.length;
  formData.certificate_payment = chargeableCount * 50;

  let html = `
    <p><strong>Scheduled Date:</strong> ${formData.selectedDate}</p>
    <p><strong>Time Slot:</strong> ${formData.selectedTime}</p>
    <p><strong>Certificates Requested:</strong></p>
    <ul>${certs.map(c => `<li><strong>${c.name}</strong> - ${c.purpose}</li>`).join('')}</ul>
  `;

  const exemptNotes = certs.filter(c => !isChargeable(c) && norm(c.name) !== 'cedula')
                           .map(c => `<li>${c.name} — ${c.purpose} (exempt)</li>`).join('');
  if (exemptNotes) html += `<p class="mt-2"><strong>Exempted:</strong></p><ul>${exemptNotes}</ul>`;

  if (formData.certificate_payment > 0) {
    html += `<p><strong>Estimated Certificate Payment:</strong> ₱${formData.certificate_payment.toFixed(2)} (${chargeableCount} × ₱50)</p>`;
  }
  if (formData.cedula_payment && Number(formData.cedula_payment) > 0) {
    html += `<p><strong>Estimated Cedula Payment:</strong> ₱${Number(formData.cedula_payment).toFixed(2)}</p>`;
  }

  const total = (Number(formData.certificate_payment||0) + Number(formData.cedula_payment||0)).toFixed(2);
  html += `<hr><p><strong>Estimated Total Payment:</strong> ₱${total}</p>`;
  summaryDiv.innerHTML = html;
}

document.addEventListener("DOMContentLoaded", () => {
  const confirmModalEl = $("confirmSubmitModal");
  if (!confirmModalEl) return;

  let allowClose = false;
  confirmModalEl.addEventListener("hide.bs.modal", (e) => {
    if (allowClose) { allowClose = false; return; }
    e.preventDefault();
    Swal.fire({
      title: 'Discard this appointment?',
      text: "Your selections won't be saved.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, discard',
      cancelButtonText: 'Keep editing',
      reverseButtons: true,
      focusCancel: true
    }).then((result) => {
      if (result.isConfirmed) {
        allowClose = true;
        const instance = bootstrap.Modal.getInstance(confirmModalEl) || new bootstrap.Modal(confirmModalEl);
        instance.hide();
      }
    });
  });
});
