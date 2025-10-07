<!-- childSelectionModal (UPGRADED) -->
<div class="modal fade" id="childSelectionModal" tabindex="-1"
     aria-labelledby="childSelectionModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content border-success modal-elevate">
      <div class="modal-header gradient-primary">
        <h5 class="modal-title d-flex align-items-center gap-2" id="childSelectionModalLabel">
          <i class="bi bi-people"></i> Select a Child
        </h5>
        <button type="button" class="btn-close" id="childSelectionCloseBtn" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <table class="table table-hover mb-0" id="childrenTable">
          <thead>
            <tr>
              <th>Full Name</th>
              <th>Birthdate</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <!-- Filled dynamically -->
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  document.getElementById('childSelectionCloseBtn').addEventListener('click', function () {
    window.location.href = "<?= $redirects['schedule_appointment']; ?>";
  });
</script>