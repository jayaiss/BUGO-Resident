<!-- Released Appointment Modal -->
<div class="modal fade app-modal app-modal--info" id="releasedNotifModal" tabindex="-1" aria-labelledby="releasedNotifModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-check2-circle"></i>
          Released Certificate Details
          <span class="status-pill success">Paid</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="kv"><strong>Tracking Number:</strong> <span id="releasedTrackingNumber">N/A</span></p>
        <p class="kv"><strong>Certificate:</strong> <span id="releasedCertificate">N/A</span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Approved Captain Modal -->
<div class="modal fade app-modal app-modal--primary" id="approvedCaptainModal" tabindex="-1" aria-labelledby="approvedCaptainModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-patch-check"></i>
          Approved by Barangay Captain
          <span class="status-pill primary">Approved</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="kv"><strong>Status:</strong> <span>Kindly wait for final processing or releasing.</span></p>
        <p class="kv"><strong>Claim Date:</strong> <span id="modalClaimDate">N/A</span></p>
        <p class="kv"><strong>Claim Time:</strong> <span id="modalClaimTime">N/A</span></p>
        <p class="kv"><strong>Certificate:</strong> <span id="modalCertificate">N/A</span></p>
        <p class="kv"><strong>Amount to Pay:</strong> <span>₱<span id="modalPaymentAmount">0.00</span></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Pending Modal -->
<div class="modal fade app-modal app-modal--warning" id="pendingNotifModal" tabindex="-1" aria-labelledby="pendingNotifModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-hourglass-split"></i>
          Pending Notification
          <span class="status-pill warning">Pending</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="kv"><strong>Status:</strong> <span>Your request is still pending. Please wait for further updates.</span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Rejection Reason Modal -->
<div class="modal fade app-modal app-modal--danger" id="rejectionReasonModal" tabindex="-1" aria-labelledby="rejectionReasonLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-x-octagon"></i>
          Reason for Rejection
          <span class="status-pill danger">Rejected</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="rejectionReasonText" style="word-break: break-word;">
        Loading...
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Approved Appointment Modal -->
<div class="modal fade app-modal app-modal--success" id="approvedNotifModal" tabindex="-1" aria-labelledby="approvedNotifModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-check-circle"></i>
          Approved Appointment Details
          <span class="status-pill success">Approved</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="kv"><strong>Tracking Number:</strong> <span id="modalAppTrackingNumber">N/A</span></p>
        <p class="kv"><strong>Claim Date:</strong> <span id="modalAppClaimDate">N/A</span></p>
        <p class="kv"><strong>Claim Time:</strong> <span id="modalAppClaimTime">N/A</span></p>
        <p class="kv"><strong>Certificate:</strong> <span id="modalAppCertificate">N/A</span></p>
        <p class="kv"><strong>Amount to Pay:</strong> <span>₱<span id="modalAppPaymentAmount">0.00</span></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<!-- Event Details Modal -->
<div class="modal fade app-modal app-modal--info" id="eventNotifModal" tabindex="-1" aria-labelledby="eventNotifModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-calendar-event"></i> Event Details
          <span class="status-pill primary">Upcoming</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="kv"><strong>Event:</strong> <span id="eventTitle">N/A</span></p>
        <p class="kv"><strong>Date:</strong> <span id="eventDate">N/A</span></p>
        <p class="kv"><strong>Time:</strong> <span id="eventTime">N/A</span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
