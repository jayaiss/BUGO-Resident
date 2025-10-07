<!-- appointmentTypeModal (UPGRADED) -->
<div class="modal fade" id="appointmentTypeModal" tabindex="-1"
     aria-labelledby="appointmentTypeModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-primary modal-elevate">
      <div class="modal-header gradient-primary">
        <h5 class="modal-title d-flex align-items-center gap-2" id="appointmentTypeModalLabel">
          <i class="bi bi-calendar2-check"></i> Choose Appointment For
        </h5>
        <button type="button" class="btn-close" id="appointmentCloseBtn" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body text-center py-4">
        <div class="d-flex justify-content-center gap-3 flex-wrap">
          <button class="btn btn-primary px-4 py-2" id="personalAppointmentBtn">
            👤 Personal
          </button>
          <button class="btn btn-success px-4 py-2" id="childAppointmentBtn">
            🧒 For My Child
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
  document.getElementById('appointmentCloseBtn').addEventListener('click', function () {
    window.location.href = "<?= $redirects['homepage']; ?>";
  });
</script>