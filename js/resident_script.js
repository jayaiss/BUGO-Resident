// Function to print the resident details in the view modal
// Function to print the resident details in the view modal
function printResidentDetails() {
    var printContent = document.querySelector('#viewModal .modal-body').innerHTML; // Get modal content
    var originalContent = document.body.innerHTML; // Save original content

    // Create a new window for printing
    var printWindow = window.open('', '', 'height=600,width=800');
    
    // Add some styles for a more presentable print layout
    printWindow.document.write(`
        <html>
            <head>
                <title>Resident Details</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        margin: 20px;
                    }
                    h1 {
                        text-align: center;
                        margin-bottom: 30px;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin: 20px 0;
                    }
                    table th, table td {
                        border: 1px solid #ccc;
                        padding: 8px;
                        text-align: left;
                    }
                    table th {
                        background-color: #f2f2f2;
                    }
                    .container {
                        max-width: 800px;
                        margin: 0 auto;
                    }
                </style>
            </head>
            <body>
                <h1>Resident Details</h1>
                <div class="container">
                    <table>
                        <tr><th>Full Name</th><td>${document.getElementById('viewFullName').textContent}</td></tr>
                        <tr><th>Gender</th><td>${document.getElementById('viewGender').textContent}</td></tr>
                        <tr><th>Birth Date</th><td>${document.getElementById('viewBirthDate').textContent}</td></tr>
                        <tr><th>Age</th><td>${document.getElementById('viewAge').textContent}</td></tr>
                        <tr><th>Civil Status</th><td>${document.getElementById('viewCivilStatus').textContent}</td></tr>
                        <tr><th>Contact Number</th><td>${document.getElementById('viewContactNumber').textContent}</td></tr>
                        <tr><th>Email</th><td>${document.getElementById('viewEmail').textContent}</td></tr>
                        <tr><th>Purok</th><td>${document.getElementById('viewPurok').textContent}</td></tr>
                        <tr><th>Citizenship</th><td>${document.getElementById('viewCitizenship').textContent}</td></tr>
                        <tr><th>Religion</th><td>${document.getElementById('viewReligion').textContent}</td></tr>
                        <tr><th>Occupation</th><td>${document.getElementById('viewOccupation').textContent}</td></tr>
                    </table>
                </div>
            </body>
        </html>
    `);
    
    printWindow.document.close(); // Close the document to complete writing
    printWindow.print(); // Print the content
}


// When the View button is clicked, load the corresponding resident's details into the view modal
$('#viewModal').on('show.bs.modal', function (e) {
    var residentId = $(e.relatedTarget).data('id'); // Get the resident ID from the button's data-id attribute

    $.ajax({
        url: 'include/get_resident_details.php', // PHP file to fetch the data
        type: 'GET',
        data: { id: residentId },
        success: function(response) {
            var resident = JSON.parse(response);
            
            $('#viewFullName').text(resident.full_name);
            $('#viewGender').text(resident.gender);
            $('#viewBirthDate').text(resident.birth_date);
            $('#viewAge').text(resident.age);
            $('#viewCivilStatus').text(resident.civil_status);
            $('#viewContactNumber').text(resident.contact_number);
            $('#viewEmail').text(resident.email);
            $('#viewPurok').text(resident.purok);
            $('#viewCitizenship').text(resident.citizenship);
            $('#viewReligion').text(resident.religion);
            $('#viewOccupation').text(resident.occupation);
        }
    });
});

// When the Edit button is clicked, load the resident's details into the edit modal
$('#editModal').on('show.bs.modal', function (e) {
    var residentId = $(e.relatedTarget).data('id'); // Get the resident ID from the button's data-id attribute

    $.ajax({
        url: 'include/get_resident_details.php', // PHP file to fetch the data
        type: 'GET',
        data: { id: residentId },
        success: function(response) {
            var resident = JSON.parse(response);

            // Populate the form fields with individual parts of the name
            $('#editId').val(resident.id);
            $('#editFirstName').val(resident.full_name.split(' ')[0]); // First Name
            $('#editMiddleName').val(resident.full_name.split(' ')[1] || ''); // Middle Name (if exists)
            $('#editLastName').val(resident.full_name.split(' ')[2] || ''); // Last Name

            // Populate other fields
            $('#editGender').val(resident.gender);
            $('#editBirthDate').val(resident.birth_date);
            $('#editCivilStatus').val(resident.civil_status);
            $('#editContactNumber').val(resident.contact_number);
            $('#editEmail').val(resident.email);
            $('#editPurok').val(resident.purok);
            $('#editCitizenship').val(resident.citizenship);
            $('#editReligion').val(resident.religion);
            $('#editOccupation').val(resident.occupation);
        }
    });
});

// Submit the edit form to update the resident's details
$('#editForm').on('submit', function (e) {
    e.preventDefault(); // Prevent default form submission

    $.ajax({
        url: 'api/update_resident.php', // Ensure this path is correct
        type: 'POST',
        data: $('#editForm').serialize(), // Serialize the form data
        success: function(response) {
            alert(response); // Display the success message or error
            location.reload(); // Reload the page to update the table
        },
        error: function(xhr, status, error) {
            console.error("Error: " + error); // Handle any AJAX errors
            alert('Failed to update resident details. Please try again.');
        }
    });
});

function confirmDelete(residentId) {
    // Show confirmation prompt
    if (confirm("Are you sure you want to delete this resident? This action cannot be undone.")) {
        // Redirect to the delete action if confirmed
        window.location.href = 'api/delete_resident.php?id=' + residentId;
    }
}


