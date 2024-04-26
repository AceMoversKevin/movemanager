<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit;
}

// Include any necessary PHP code for handling backend logic
// Query available employees
$employeeQuery = "SELECT PhoneNo, Name, EmployeeType FROM Employees WHERE isActive = 1 AND (EmployeeType = 'Helper' OR EmployeeType = 'Driver')";
$employeeResult = $conn->query($employeeQuery);
$employees = [];

while ($row = $employeeResult->fetch_assoc()) {
    $employees[] = $row;
}
// Fetch the booking ID from the URL
$bookingID = isset($_GET['BookingID']) ? intval($_GET['BookingID']) : 0;

// Initialize an empty array for job details
$jobDetails = [];

if ($bookingID > 0) {
    $query = "SELECT 
                  b.BookingID, 
                  b.Name AS BookingName, 
                  b.Email AS BookingEmail, 
                  b.Phone AS BookingPhone, 
                  b.Bedrooms, 
                  b.MovingDate,
                  b.PickupLocation,
                  b.DropoffLocation,
                  GROUP_CONCAT(e.Name ORDER BY e.Name SEPARATOR ', ') AS EmployeeNames
              FROM 
                  Bookings b
              JOIN 
                  BookingAssignments ba ON b.BookingID = ba.BookingID
              JOIN 
                  Employees e ON ba.EmployeePhoneNo = e.PhoneNo
              WHERE 
                  b.BookingID = ?
              GROUP BY 
                  b.BookingID";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $bookingID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $jobDetails = $result->fetch_assoc();
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        #employee-edit-form {
            margin-top: 20px;
        }

        #employee-edit-form select {
            margin-bottom: 10px;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        #employee-edit-form button {
            margin-right: 10px;
            padding: 5px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        #employee-edit-form button:hover {
            opacity: 0.8;
        }

        .add-button {
            background-color: #4CAF50;
            /* Green */
            color: white;
        }

        .save-button {
            background-color: #008CBA;
            /* Blue */
            color: white;
        }

        .cancel-button {
            background-color: #f44336;
            /* Red */
            color: white;
        }
    </style>
</head>

<body>

    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0">
        <a class="navbar-brand col-md-3 col-lg-2 mr-0 px-3" href="index.php">AceMovers</a>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
                <a class="nav-link" href="logout.php">Logout</a>
            </li>
        </ul>
    </header>

    <div class="container-fluid">
        <div class="row">

            <?php include 'navbar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2" id="Main-Heading">Details for the Job</h1>
                </div>
                <!-- Dashboard content goes here -->
                <?php if (!empty($jobDetails)) : ?>
                    <p><strong>Booking Name:</strong> <?php echo htmlspecialchars($jobDetails['BookingName']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($jobDetails['BookingEmail']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($jobDetails['BookingPhone']); ?></p>
                    <p><strong>Bedrooms:</strong> <?php echo htmlspecialchars($jobDetails['Bedrooms']); ?></p>
                    <p><strong>Moving Date:</strong> <?php echo htmlspecialchars($jobDetails['MovingDate']); ?></p>
                    <p><strong>Pickup Location:</strong> <?php echo htmlspecialchars($jobDetails['PickupLocation']); ?></p>
                    <p><strong>Dropoff Location:</strong> <?php echo htmlspecialchars($jobDetails['DropoffLocation']); ?></p>
                    <p><strong>Assigned Employees:</strong> <?php echo htmlspecialchars($jobDetails['EmployeeNames']); ?></p>
                    <button type="button" class="btn btn-outline-info" id="editEmployee">Edit Employees</button>
                    <button type="button" class="btn btn-outline-warning" id="notifyEmployee">Notify Employees</button>
                <?php else : ?>
                    <p>Job details not found.</p>
                <?php endif; ?>

                <div id="employee-edit-form"></div>


            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editButton = document.getElementById('editEmployee');
            const employeeEditForm = document.getElementById('employee-edit-form');
            const employees = <?php echo json_encode($employees); ?>;
            let assignedEmployees = <?php echo json_encode(explode(', ', $jobDetails['EmployeeNames'])); ?>;

            editButton.addEventListener('click', function() {
                createForm();
            });

            // Get BookingID from the URL
            const urlParams = new URLSearchParams(window.location.search);
            const bookingID = urlParams.get('BookingID');

            function createForm() {
                // Clear any existing content in the form.
                employeeEditForm.innerHTML = '';

                // Create the container for the select elements.
                const selectContainer = document.createElement('div');
                selectContainer.id = 'select-container';

                // Create employee select options from the PHP array.
                const selectHTML = employees.map(emp =>
                    `<option value="${emp.PhoneNo}">${emp.Name} (${emp.EmployeeType})</option>`
                ).join('');

                // Populate the select container with a dropdown for each assigned employee.
                assignedEmployees.forEach(employeeName => {
                    const selectWrapper = document.createElement('div');
                    selectWrapper.classList.add('select-wrapper');

                    const employeeSelect = document.createElement('select');
                    employeeSelect.innerHTML = selectHTML;
                    employeeSelect.value = employees.find(emp => emp.Name === employeeName)?.PhoneNo || '';

                    const removeButton = document.createElement('i');
                    removeButton.classList.add('fa', 'fa-ban');
                    removeButton.setAttribute('aria-hidden', 'true');
                    removeButton.onclick = function() {
                        // Logic to remove the employee goes here.
                        // For now, this will just remove the select element from the DOM.
                        selectWrapper.remove();
                    };

                    selectWrapper.appendChild(employeeSelect);
                    selectWrapper.appendChild(removeButton);
                    selectContainer.appendChild(selectWrapper);
                });

                // Append the select container to the form.
                employeeEditForm.appendChild(selectContainer);

                // Create the button container.
                const buttonContainer = document.createElement('div');
                buttonContainer.id = 'button-container';

                // Create the "Add Employee" button.
                const addButton = document.createElement('button');
                addButton.textContent = 'Add Employee';
                addButton.type = 'button';
                addButton.classList.add('add-button');
                addButton.onclick = function() {
                    const selectWrapper = document.createElement('div');
                    selectWrapper.classList.add('select-wrapper');

                    const newSelect = document.createElement('select');
                    newSelect.innerHTML = selectHTML;

                    selectWrapper.appendChild(newSelect);
                    selectContainer.appendChild(selectWrapper); // Append the new select to the select container.
                };

                // Create the "Save Changes" button.
                const saveButton = document.createElement('button');
                saveButton.textContent = 'Save Changes';
                saveButton.type = 'button';
                saveButton.classList.add('save-button');
                saveButton.onclick = function() {
                    // Logic to save changes goes here.
                    saveChanges();
                };

                // Create the "Cancel" button.
                const cancelButton = document.createElement('button');
                cancelButton.textContent = 'Cancel';
                cancelButton.type = 'button';
                cancelButton.classList.add('cancel-button');
                cancelButton.onclick = function() {
                    employeeEditForm.innerHTML = ''; // Clear the form to cancel.
                };

                // Append buttons to the button container.
                buttonContainer.appendChild(addButton);
                buttonContainer.appendChild(saveButton);
                buttonContainer.appendChild(cancelButton);

                // Append the button container to the form.
                employeeEditForm.appendChild(buttonContainer);
            }

            function saveChanges() {
                const allSelects = employeeEditForm.querySelectorAll('.select-wrapper > select');
                const updatedEmployees = Array.from(allSelects).map(select => select.value);

                // Prepare form data for XHR request
                const formData = new FormData();
                formData.append('bookingID', bookingID);
                updatedEmployees.forEach((phoneNo, index) => {
                    // Append each employee phone number with a key
                    formData.append('employees[]', phoneNo);
                });

                // Create an XHR request
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'update-booking-employees.php', true);

                // Set up a handler for when the task for the request is complete
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        // Handle success - the server responded with a success message
                        console.log('Response from server:', xhr.responseText);
                        location.reload();
                    } else {
                        // Handle error - the server responded with an error message
                        console.error('Error from server:', xhr.responseText);
                    }
                };

                // Handle network errors
                xhr.onerror = function() {
                    console.error('Network error.');
                };

                // Send the request with the form data
                xhr.send(formData);
            }

        });
    </script>




</body>

</html>