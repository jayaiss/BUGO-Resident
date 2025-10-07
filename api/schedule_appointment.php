<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    require_once __DIR__ . '/../security/403.html';
    exit;
}
require_once __DIR__ . '/../include/connection.php';
$mysqli = db_connection();
include 'class/session_timeout.php';

$loggedInResidentId = $_SESSION['id'] ?? null;
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Scheduler</title>
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="css/resident.css">
    <link rel="stylesheet" href="assets/css/Schedule_Appointment/SchedApp.css">
    <link rel="stylesheet" href="assets/css/Schedule_Appointment/Sched_Cal.css?v=4">
</head>
<body>
  <script>
  const userId = <?php echo json_encode($_SESSION['id']); ?>;
</script>


<div class="container mt-5 scheduler-wrap" >
<h2 class="text-center mb-4">
  <i class="bi bi-calendar-plus me-2 text-primary" aria-hidden="true"></i>
  Schedule an Appointment
</h2>

    <div id="selectedForDisplay" class="d-flex justify-content-center align-items-center gap-3 flex-wrap" style="display: none;">
      <div><strong>Appointment for: </strong><strong id="selectedForText"></strong></div>
    </div>


   <!-- Calendar Section -->
<div class="calendar-navigation d-flex flex-wrap justify-content-center align-items-center gap-2 mb-3">
  
  <button class="btn btn-outline-primary" onclick="changeMonth(-1)">
    <i class="bi bi-arrow-left"></i> Previous
  </button>

  <div class="d-flex align-items-center gap-2">
    <h4 id="calendarHeader" class="mb-0 fw-bold text-primary"></h4>
    <select id="yearSelector" class="form-select form-select-sm" style="width: auto;" onchange="changeYear(this.value)">
      <!-- Options will be added via JS -->
    </select>
  </div>

  <button class="btn btn-outline-primary mt-1 mt-sm-0" onclick="changeMonth(1)">
    Next <i class="bi bi-arrow-right"></i>
  </button>

</div>
<!-- <div class="calendar-legend">
  <span class="legend-pill"><span class="legend-dot dot-available"></span> Available</span>
  <span class="legend-pill"><span class="legend-dot dot-full"></span> Fully booked</span>
  <span class="legend-pill"><span class="legend-dot dot-disabled"></span> Unavailable</span>
  <span class="legend-pill"><span class="legend-dot dot-today"></span> Today</span>
</div> -->

<!-- Calendar Grid -->
<div id="calendar" class="calendar d-grid"style="display: grid;grid-template-columns: repeat(7, minmax(40px, 1fr));gap: 0.5rem;overflow-x: auto;-webkit-overflow-scrolling: touch;"></div>


  
  <!-- Reminder: -->
  <div id="selectDateReminder" class="alert alert-info mb-3" style="display: block;">
    Please select a date from the calendar to view available time slots.
  </div>

  <!-- Time Slots Section -->
  <div id="timeSlotsSection" class="mt-5" style="display: none;">
    <h5 id="selectedDateTitle" class="fw-semibold text-dark mb-3"></h5>
    <div id="timeSlotsContainer" class="rounded border p-3 bg-light"></div>
  </div>
</div>
<?php include 'components/schedule_appointment/Modal/SelectChildModal.php'; ?>
<?php include 'components/schedule_appointment/Modal/AppTypeModal.php'; ?>
<?php include 'components/schedule_appointment/Modal/ConfirmSubModal.php'; ?>
<?php include 'components/schedule_appointment/Modal/modal.php'; ?>

<!-- Bootstrap 5 JS and Popper -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
<script src="js/schedule.js?=v5"></script>
<script src="components/schedule_appointment/js/SchedProcess.js"></script>
<script src="components/schedule_appointment/js/SchedApp.js"></script>

<script>
  let currentResId = <?php echo json_encode($_SESSION['id']); ?>;
  document.getElementById('personalAppointmentBtn').addEventListener('click', () => {
    bootstrap.Modal.getInstance(document.getElementById('appointmentTypeModal')).hide();
    const display = document.getElementById('selectedForDisplay');
    const text = document.getElementById('selectedForText');
    display.style.display = "block";
    text.textContent = "Yourself (Personal)";
    currentResId = <?php echo json_encode($_SESSION['id']); ?>;
    fetchResidentDetails(currentResId);
  });
  
function sendCedulaData(formData) {
  fetch("save_schedule.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(formData)
  })
    .then(res => res.json())
    .then(response => {
      if (response.success) {
        Swal.fire({
          icon: 'success',
          title: 'Cedula Submitted!',
          text: `Tracking #: ${response.trackingNumber}`,
          confirmButtonText: 'OK'
        }).then(() => {
          window.location.href = "<?= $redirects['resident_appointments']; ?>";
        });
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Submission Error',
          text: response.message || 'An error occurred while submitting.',
          confirmButtonText: 'Close'
        });
      }
    })
    .catch(err => {
      console.error(err);
      Swal.fire({
        icon: 'error',
        title: 'Request Failed',
        text: 'Submission failed. See console for details.',
        confirmButtonText: 'Close'
      });
    });
}
document.getElementById("confirmSubmitBtn").addEventListener("click", function () {
  // 1. Hide the confirmation modal
  const modal = bootstrap.Modal.getInstance(document.getElementById('confirmSubmitModal'));
  if (modal) modal.hide();

  // 2. Show a loading spinner using SweetAlert2
  Swal.fire({
    title: 'Submitting...',
    text: 'Please wait while we save your appointment.',
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });

  // 3. Submit the data (existing code)
  const data = window._pendingScheduleData;

  if (data?.certificate?.toLowerCase() === "cedula") {
    sendCedulaData(data); // Cedula will handle its own SweetAlert
    return;
  }

  const xhr = new XMLHttpRequest();
  xhr.open("POST", "save_schedule.php", true);
  xhr.setRequestHeader("Content-Type", "application/json");

  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4) {
      document.dispatchEvent(new Event('submitSchedule:end')); 
      Swal.close(); // Close loading spinner first

      try {
        const response = JSON.parse(xhr.responseText);

        if (xhr.status === 200 && response.success) {
          Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: response.message || "Schedule saved successfully!",
            confirmButtonText: 'OK'
          }).then(() => {
            // Show spinner while redirecting
            Swal.fire({
              title: 'Redirecting...',
              text: 'Taking you to your appointments...',
              allowOutsideClick: false,
              allowEscapeKey: false,
              didOpen: () => {
                Swal.showLoading();
              }
            });
            window.location.href = "<?= $redirects['resident_appointments']; ?>";
          });
        }
        else {
          Swal.fire({
            icon: 'error',
            title: 'Submission Failed',
            text: response.message || "Something went wrong.",
            confirmButtonText: 'Close'
          });
        }
      } catch (err) {
        Swal.fire({
          icon: 'error',
          title: 'Invalid Response',
          html: `<pre>${xhr.responseText}</pre>`,
          confirmButtonText: 'Close',
          customClass: {
            popup: 'swal-wide'
          }
        });
      }
    }
  };

  xhr.send(JSON.stringify(data));
});
</script>
</body>
</html>
