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
        b.BookingDate,
        b.MovingDate,
        b.PickupLocation,
        b.DropoffLocation,
        b.TruckSize,
        b.CalloutFee,
        b.Rate,
        b.Deposit,
        b.TimeSlot,
        b.isActive,
        b.StairCharges,
        b.PianoCharge,
        b.PoolTableCharge AS BookingPoolTableCharge,
        GROUP_CONCAT(DISTINCT e.Name ORDER BY e.Name SEPARATOR ', ') AS EmployeeNames,
        GROUP_CONCAT(DISTINCT e.Email ORDER BY e.Name SEPARATOR ', ') AS EmployeeEmails,
        MAX(jt.TimingID) AS TimingID,  -- Use aggregate functions for JobTimings columns
        MAX(jt.StartTime) AS TimingStartTime,
        MAX(jt.EndTime) AS TimingEndTime,
        MAX(jt.TotalTime) AS TimingTotalTime,
        MAX(jt.isComplete) AS TimingIsComplete,
        MAX(jt.BreakTime) AS TimingBreakTime,
        MAX(jt.isConfirmed) AS TimingIsConfirmed,
        MAX(jc.jobID) AS jobID,  -- Use aggregate functions for JobCharges columns
        MAX(jc.TotalCharge) AS JobTotalCharge,
        MAX(jc.TotalLaborTime) AS JobTotalLaborTime,
        MAX(jc.TotalBillableTime) AS JobTotalBillableTime,
        MAX(jc.StairCharge) AS JobStairCharge,
        MAX(jc.PianoCharge) AS JobPianoCharge,
        MAX(jc.StartTime) AS JobStartTime,
        MAX(jc.EndTime) AS JobEndTime,
        MAX(jc.Deposit) AS JobDeposit,
        MAX(jc.GST) AS JobGST,
        MAX(jc.PoolTableCharge) AS JobPoolTableCharge
    FROM 
        Bookings b
    JOIN 
        BookingAssignments ba ON b.BookingID = ba.BookingID
    JOIN 
        Employees e ON ba.EmployeePhoneNo = e.PhoneNo
    LEFT JOIN 
        JobTimings jt ON b.BookingID = jt.BookingID
    LEFT JOIN 
        JobCharges jc ON b.BookingID = jc.BookingID
    WHERE 
        b.BookingID = ? AND
        b.BookingID NOT IN (SELECT BookingID FROM CompletedJobs)
    GROUP BY 
        b.BookingID, 
        b.Name, 
        b.Email, 
        b.Phone, 
        b.Bedrooms, 
        b.BookingDate,
        b.MovingDate,
        b.PickupLocation,
        b.DropoffLocation,
        b.TruckSize,
        b.CalloutFee,
        b.Rate,
        b.Deposit,
        b.TimeSlot,
        b.isActive,
        b.StairCharges,
        b.PianoCharge,
        b.PoolTableCharge;
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $bookingID);
    $stmt->execute();
    $result = $stmt->get_result();

    $jobDetails = [];
    if ($row = $result->fetch_assoc()) {
        $jobDetails = $row;

        $subTotal = calculateSubTotal($row['JobTotalLaborTime'], $row['Rate'], $row['CalloutFee']);
        $jobDetails['SubTotal'] = $subTotal;

        // Determine GST percentage
        $gstIncluded = isGSTIncluded($row['JobGST']);
        $gstPercentage = $gstIncluded ? '10%' : '0%';

        // Calculate the surcharge
        $surcharge = ($row['JobGST'] == 1) ? $subTotal * 0.10 : 0;
        $jobDetails['Surcharge'] = $surcharge;

        // Check if there are any additional charges
        $hasAdditionalCharges = ($row['StairCharges'] != 0 || $row['PianoCharge'] != 0 || $row['BookingPoolTableCharge'] != 0);
    }
    $stmt->close();
} else {
    header("Location: index.php");
    exit;
}
function calculateSubTotal($totalLaborTime, $rate, $calloutFee)
{
    return ($totalLaborTime + $calloutFee) * $rate;
}

function isGSTIncluded($gstValue)
{
    return $gstValue == 1;
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

        .invoice-container {
            overflow-y: auto;
            /* Enable scrolling for content overflow */
        }

        .invoice-box {
            max-width: 100%;
            margin: 15px;
            padding: 10px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            font-size: 14px;
            line-height: 1.6;
            font-family: sans-serif;
            color: #555;
        }

        .invoice-box table {
            width: 100%;
            line-height: inherit;
            text-align: left;
            border-collapse: collapse;
            /* Remove default table spacing */
        }

        .invoice-box table td {
            padding: 5px;
            vertical-align: top;
        }

        .invoice-box table tr td:nth-child(2) {
            text-align: right;
        }

        .invoice-box table tr.top table td {
            padding-bottom: 20px;
        }

        .invoice-box table tr.top table td.title {
            font-size: 20px;
            line-height: 1.2;
        }

        .top table {
            width: 100%;
        }

        .top td {
            vertical-align: middle;
            text-align: left;
        }

        .top .title img {
            max-width: 100%;
            height: auto;
        }

        .top .title {
            width: 20%;
            /* Adjust as needed */
            display: inline-block;
            vertical-align: middle;
        }

        .top .invoice-details {
            width: 80%;
            /* Adjust as needed */
            display: inline-block;
            vertical-align: middle;
        }

        .invoice-box table tr.top table td.title img {
            width: 100px;
            /* Adjust logo size as needed */
            max-width: 100%;
            /* Ensure the logo scales down on small screens */
        }

        .invoice-box table tr.information table td {
            padding-bottom: 20px;
            /* Reduced for better fit */
        }

        .invoice-box table tr.heading td {
            background: #eee;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
        }

        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
        }

        .invoice-box table tr.item.last td {
            border-bottom: none;
        }

        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
        }

        .invoice-box p {
            /* Style for the "For any queries..." paragraph */
            font-size: 12px;
            margin-top: 10px;
        }

        /* Responsive Styles */
        @media only screen and (max-width: 600px) {
            .invoice-box {
                font-size: 12px;
            }

            .invoice-box table tr.top table td,
            .invoice-box table tr.information table td {
                width: 100%;
                display: block;
                text-align: center;
            }

            /* Additional adjustments for smaller screens */
        }

        .payment-options-container {
            margin-top: 20px;
            /* Space the payment container from the invoice */
        }

        .payment-option {
            margin-bottom: 15px;
        }

        .payment-details {
            display: none;
            /* Hide by default */
            margin-top: 10px;
        }

        .payment-option input[type="radio"]:checked+label+.payment-details {
            display: block;
            /* Show when option is selected */
        }

        /* Mock Stripe element (basic styling) */
        #mock-stripe-element {
            border: 1px solid #ccc;
            padding: 10px;
        }

        #mock-stripe-element input[type="text"] {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ccc;
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

            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2" id="Main-Heading">Details for the Job</h1>
                </div>

                <?php if (!empty($jobDetails)) : ?>
                    <div class="row">
                        <div class="col-md-4">
                            <h3>Move Details</h3>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($jobDetails['MovingDate']); ?></p>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($jobDetails['BookingName']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($jobDetails['BookingEmail']); ?></p>
                            <p><strong>Rate:</strong> $<?php echo htmlspecialchars($jobDetails['Rate']); ?>/hr</p>
                            <p><strong>Deposit:</strong> $<?php echo htmlspecialchars($jobDetails['Deposit']); ?></p>
                            <p><strong>Start Time:</strong> <?php echo htmlspecialchars($jobDetails['TimingStartTime']); ?></p>
                        </div>

                        <div class="col-md-4">
                            <h3>Additional Charges</h3>
                            <ul>
                                <?php if ($jobDetails['JobStairCharge'] != 0) : ?>
                                    <li>Stair Charges: $<?php echo number_format($jobDetails['JobStairCharge']); ?></li>
                                <?php endif; ?>
                                <?php if ($jobDetails['JobPoolTableCharge'] != 0) : ?>
                                    <li>Pool Table Charges: $<?php echo number_format($jobDetails['JobPoolTableCharge']); ?></li>
                                <?php endif; ?>
                                <?php if ($jobDetails['JobPianoCharge'] != 0) : ?>
                                    <li>Piano Charges: $<?php echo number_format($jobDetails['JobPianoCharge']); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="col-md-4">
                            <h3>Live Invoice Preview</h3>
                            <p><strong>Total Labor Time:</strong> <?php echo htmlspecialchars($jobDetails['JobTotalLaborTime']); ?>h</p>
                            <p><strong>Total Billable Time:</strong> <?php echo htmlspecialchars($jobDetails['JobTotalBillableTime']); ?>h</p>
                            <p><strong>Subtotal:</strong> $<?php echo htmlspecialchars($jobDetails['SubTotal']); ?></p>
                            <p><strong>GST:</strong> $<?php echo $surcharge; ?></p>
                            <p><strong>Total:</strong> $<?php echo htmlspecialchars($jobDetails['JobTotalCharge']); ?></p>
                            <button class="btn btn-secondary" name="download-invoice-button">Download Invoice</button>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    Job Timings
                                </div>
                                <div class="card-body">
                                    <p><strong>Timing ID:</strong> <?php echo htmlspecialchars($jobDetails['TimingID']); ?></p>
                                    <p><strong>Start Time:</strong> <?php echo htmlspecialchars($jobDetails['TimingStartTime']); ?></p>
                                    <p><strong>End Time:</strong> <?php echo htmlspecialchars($jobDetails['TimingEndTime']); ?></p>
                                    <p><strong>Is Complete:</strong> <?php echo htmlspecialchars($jobDetails['TimingIsComplete'] ? 'Yes' : 'No'); ?></p>
                                    <p><strong>Break Time:</strong> <?php echo htmlspecialchars($jobDetails['TimingBreakTime']); ?></p>
                                    <p><strong>Is Confirmed:</strong> <?php echo htmlspecialchars($jobDetails['TimingIsConfirmed'] ? 'Yes' : 'No'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    Assigned Employees
                                </div>
                                <div class="card-body">
                                    <p><strong>Names:</strong> <?php echo htmlspecialchars($jobDetails['EmployeeNames']); ?></p>
                                    <p><strong>Emails:</strong> <?php echo htmlspecialchars($jobDetails['EmployeeEmails']); ?></p>
                                    <button type="button" class="btn btn-outline-info" id="editEmployee">Edit Employees</button>
                                    <button type="button" class="btn btn-outline-warning" id="notifyEmployee">Notify Employees</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="employee-edit-form"></div>

                <?php else : ?>
                    <p>Job details not found for BookingID: <?php echo htmlspecialchars($bookingID); ?></p>
                <?php endif; ?>
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

                // Notify Employees button listener
                const notifyButton = document.getElementById('notifyEmployee');
                notifyButton.addEventListener('click', function() {
                    notifyEmployees();
                });
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

            function notifyEmployees() {
                // Use the bookingID and jobDetails for the notification
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'send-notifications.php', true);
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        console.log('Notification sent:', xhr.responseText);
                    } else {
                        console.error('Error sending notification:', xhr.responseText);
                    }
                };
                xhr.onerror = function() {
                    console.error('Network error.');
                };
                xhr.send(`bookingID=${bookingID}`);
            }

        });
    </script>




</body>

</html>