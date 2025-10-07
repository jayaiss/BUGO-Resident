<div class="modal fade" id="cropperModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-crop me-2"></i>Adjust your profile photo</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12 col-lg-8">
            <div class="border rounded-3 p-2">
              <img id="cropperImage" alt="Crop source" style="max-width:100%; display:block;">
            </div>
          </div>
          <div class="col-12 col-lg-4">
            <div class="small text-muted mb-2">Preview</div>
            <div class="avatar-preview rounded-circle border mb-3" style="width:160px;height:160px;overflow:hidden;"></div>
            <div class="avatar-preview rounded-circle border mb-3" style="width:96px;height:96px;overflow:hidden;"></div>
            <div class="avatar-preview rounded-circle border" style="width:48px;height:48px;overflow:hidden;"></div>
            <p class="small text-muted mt-3 mb-0">Drag to center. Pinch/scroll to zoom.</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button id="cropperSaveBtn" type="button" class="btn btn-primary btn-sm">
          <i class="bi bi-check2 me-1"></i>Crop & Save
        </button>
      </div>
    </div>
  </div>
</div>