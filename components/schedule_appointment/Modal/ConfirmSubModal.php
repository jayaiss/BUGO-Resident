<!-- confirmSubmitModal (UPGRADED) -->
<div class="modal fade" id="confirmSubmitModal" tabindex="-1"
     aria-labelledby="confirmSubmitModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-primary modal-elevate">
      <div class="modal-header gradient-primary">
        <h5 class="modal-title d-flex align-items-center gap-2" id="confirmSubmitModalLabel">
          <i class="bi bi-check2-circle"></i> Confirm Appointment
        </h5>
      </div>

      <div class="modal-body">
        <div id="appointmentSummary"><!-- injected by JS --></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary"  id="cancelConfirmBtn" data-bs-dismiss="modal" >No, Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmSubmitBtn">Yes, Submit</button>
      </div>
    </div>
  </div>
</div>