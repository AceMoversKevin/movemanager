<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Fetch job details from the database
if (isset($_GET['bookingID'])) {
    $bookingID = intval($_GET['bookingID']);

    // Prepare the correct SQL query
    $stmt = $conn->prepare("
    SELECT 
        b.BookingID, b.Name AS BookingName, b.Email, b.Phone, b.Bedrooms, b.BookingDate, 
        b.MovingDate, b.PickupLocation, b.DropoffLocation, b.TruckSize, b.CalloutFee, 
        b.Rate, b.Deposit, b.TimeSlot, b.isActive AS BookingActive,
        b.StairCharges, b.PianoCharge, b.PoolTableCharge,  -- Charges from the Bookings table
        jt.StartTime, jt.EndTime, jt.BreakTime, jt.isComplete, jt.isConfirmed,  -- Include isConfirmed
        e.Name AS EmployeeName, e.PhoneNo AS EmployeePhone, e.Email AS EmployeeEmail, e.EmployeeType
    FROM Bookings b
    JOIN BookingAssignments ba ON b.BookingID = ba.BookingID
    JOIN Employees e ON ba.EmployeePhoneNo = e.PhoneNo
    JOIN JobTimings jt ON jt.BookingID = b.BookingID
    WHERE b.BookingID = ? AND jt.isComplete = 0 AND jt.isConfirmed = 0 AND jt.StartTime IS NOT NULL AND jt.EndTime IS NOT NULL
");


    $stmt->bind_param("i", $bookingID);
    $stmt->execute();
    $result = $stmt->get_result();

    $jobDetails = [];
    $employeeNames = [];
    if ($row = $result->fetch_assoc()) {
        $jobDetails = $row;
        // Assuming there can be multiple employees per booking
        do {
            if (!in_array($row['EmployeeName'], $employeeNames)) { // Prevent duplicates
                $employeeNames[] = $row['EmployeeName'];
            }
            $currentStairCharge = $row['StairCharges'];
            $currentPianoCharge = $row['PianoCharge'];
            $currentPoolTableCharge = $row['PoolTableCharge'];
            $currentStartDateTime = $row['StartTime'];
            $currentEndDateTime = $row['EndTime'];
        } while ($row = $result->fetch_assoc());
    }
    $stmt->close();
    $employeeList = implode(', ', $employeeNames);
} else {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['saveStartTime'])) {
        $newStartTimeInput = $_POST['startTime']; // Get the time input from the form

        // Fetch the existing date part from StartTime
        $stmt = $conn->prepare("SELECT DATE(StartTime) as StartDate FROM JobTimings WHERE BookingID = ?");
        $stmt->bind_param("i", $bookingID);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $startDate = $row['StartDate']; // Existing date part

        if ($startDate) {
            // Combine existing date with new time input
            $newStartTime = $startDate . ' ' . $newStartTimeInput;
        } else {
            // Fallback in case no existing date is found; use today's date
            $newStartTime = date('Y-m-d') . ' ' . $newStartTimeInput;
        }

        $stmt = $conn->prepare("UPDATE JobTimings SET StartTime = ? WHERE BookingID = ?");
        $stmt->bind_param("si", $newStartTime, $bookingID);
        executeStatement($stmt);
        $result->close(); // Close the previous result set before the next prepare()

    } elseif (isset($_POST['saveEndTime'])) {
        // Similarly, handle end time ensuring proper date-time combination
        $newEndTimeInput = $_POST['endTime'];

        // Validate and format the end time input
        $endTime = DateTime::createFromFormat('H:i', $newEndTimeInput);
        if ($endTime !== false) {
            // Fetch the existing date part from EndTime
            $stmt = $conn->prepare("SELECT DATE(EndTime) as EndDate FROM JobTimings WHERE BookingID = ?");
            $stmt->bind_param("i", $bookingID);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $endDate = $row['EndDate']; // Existing date part

            if ($endDate) {
                // Combine existing date with new time input
                $newEndTime = $endDate . ' ' . $endTime->format('H:i:s');
            } else {
                // Fallback in case no existing date is found; use today's date
                $newEndTime = date('Y-m-d') . ' ' . $endTime->format('H:i:s');
            }

            $stmt = $conn->prepare("UPDATE JobTimings SET EndTime = ? WHERE BookingID = ?");
            $stmt->bind_param("si", $newEndTime, $bookingID);
            executeStatement($stmt);
        } else {
            // Handle invalid time input, e.g., display an error message
            echo "Invalid end time format. Please enter a valid time (HH:MM).";
        }
    } elseif (isset($_POST['saveBreakTime'])) {
        // Break time handling
        $newBreakTime = $_POST['breakTime'];
        $stmt = $conn->prepare("UPDATE JobTimings SET BreakTime = ? WHERE BookingID = ?");
        $stmt->bind_param("ii", $newBreakTime, $bookingID);
        executeStatement($stmt);
    } elseif (isset($_POST['saveStairCharges'])) {
        // Handle Stair Charges
        $newStairCharges = $_POST['stairChargeAmount'];
        $stmt = $conn->prepare("UPDATE Bookings SET StairCharges = ? WHERE BookingID = ?");
        $stmt->bind_param("di", $newStairCharges, $bookingID);
        executeStatement($stmt);
    } elseif (isset($_POST['savePianoCharges'])) {
        // Handle Piano Charge
        $newPianoCharge = $_POST['pianoChargeAmount'];
        $stmt = $conn->prepare("UPDATE Bookings SET PianoCharge = ? WHERE BookingID = ?");
        $stmt->bind_param("di", $newPianoCharge, $bookingID);
        executeStatement($stmt);
    } elseif (isset($_POST['savePoolTableCharges'])) {
        // Handle Pool Table Charge
        $newPoolTableCharge = $_POST['poolTableChargeAmount'];
        $stmt = $conn->prepare("UPDATE Bookings SET PoolTableCharge = ? WHERE BookingID = ?");
        $stmt->bind_param("di", $newPoolTableCharge, $bookingID);
        executeStatement($stmt);
    }
}

function executeStatement($stmt)
{
    if (!$stmt->execute()) {
        error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    } else {
        // Optionally add a redirect or refresh logic here
        header("Refresh:0");
        exit;
    }
    $stmt->close();
}

// Function to calculate the Wednesday of the current week
function getWeekStartDate($date)
{
    $currentDayOfWeek = date('w', strtotime($date)); // 0 (for Sunday) through 6 (for Saturday)
    $daysUntilWednesday = 3 - $currentDayOfWeek; // Wednesday is 3
    if ($daysUntilWednesday < 0) {
        $daysUntilWednesday += 7; // Ensure we move to the next Wednesday
    }
    return date('Y-m-d', strtotime("$date + $daysUntilWednesday days"));
}

// Check if the complete button has been pressed
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['completeJobButton'])) {
    $bookingID = intval($_GET['bookingID']); // Retrieve the BookingID from URL parameter

    // Sanitize and retrieve values from POST data
    $totalCharge = filter_input(INPUT_POST, 'totalCharge', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $totalLaborTime = filter_input(INPUT_POST, 'totalLaborTime', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $totalBillableTime = filter_input(INPUT_POST, 'totalBillableTime', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $gst = filter_input(INPUT_POST, 'gst', FILTER_SANITIZE_NUMBER_INT);
    $stairCharge = filter_input(INPUT_POST, 'stairCharge', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $pianoCharge = filter_input(INPUT_POST, 'pianoCharge', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $poolTableCharge = filter_input(INPUT_POST, 'poolCharge', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $startTimeInput = filter_input(INPUT_POST, 'startTime', FILTER_SANITIZE_STRING);
    $endTimeInput = filter_input(INPUT_POST, 'endTime', FILTER_SANITIZE_STRING);
    $deposit = filter_input(INPUT_POST, 'deposit', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    // Validate and format the start time and end time inputs
    $startTime = DateTime::createFromFormat('Y-m-d H:i:s', $startTimeInput);
    $endTime = DateTime::createFromFormat('Y-m-d H:i:s', $endTimeInput);

    if ($startTime !== false && $endTime !== false) {
        $formattedStartTime = $startTime->format('Y-m-d H:i:s');
        $formattedEndTime = $endTime->format('Y-m-d H:i:s');

        // Prepare the SQL query to insert job charges
        $stmt = $conn->prepare("INSERT INTO JobCharges (BookingID, TotalCharge, TotalLaborTime, TotalBillableTime, StairCharge, PianoCharge, PoolTableCharge, StartTime, EndTime, Deposit, GST) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Bind the parameters to the query
        $stmt->bind_param("iddddddssdi", $bookingID, $totalCharge, $totalLaborTime, $totalBillableTime, $stairCharge, $pianoCharge, $poolTableCharge, $formattedStartTime, $formattedEndTime, $deposit, $gst);

        // Execute the query and check if it was successful
        if ($stmt->execute()) {
            // Update the isComplete column in the JobTimings table
            $updateStmt = $conn->prepare("UPDATE JobTimings SET isComplete = 1 WHERE BookingID = ?");
            $updateStmt->bind_param("i", $bookingID);

            if ($updateStmt->execute()) {
                // Fetch the employees who worked on this job
                $employeeStmt = $conn->prepare("SELECT EmployeePhoneNo FROM BookingAssignments WHERE BookingID = ?");
                $employeeStmt->bind_param("i", $bookingID);
                $employeeStmt->execute();
                $employeeResult = $employeeStmt->get_result();

                // Calculate the WeekStartDate for the current week (Wednesday)
                $currentDate = date('Y-m-d');
                $weekStartDate = getWeekStartDate($currentDate);

                // Insert or update the total labor time for each employee
                while ($employeeRow = $employeeResult->fetch_assoc()) {
                    $employeePhoneNo = $employeeRow['EmployeePhoneNo'];

                    // Insert or update the work hours for the employee
                    $workHoursStmt = $conn->prepare("
                        INSERT INTO WorkHours (EmployeePhoneNo, WeekStartDate, HoursWorked, BookingID)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE HoursWorked = HoursWorked + VALUES(HoursWorked)
                    ");
                    $workHoursStmt->bind_param("ssdi", $employeePhoneNo, $weekStartDate, $totalBillableTime, $bookingID);
                    if (!$workHoursStmt->execute()) {
                        echo "Error: " . $workHoursStmt->error . "<br>";
                    }
                    $workHoursStmt->close();
                }

                $employeeStmt->close();

                echo "Job charges have been successfully recorded, job marked as complete, and work hours updated.";
                // Takes you to the invoice generation and payment finalization
                header("Location: invoice-payment.php?bookingID=" . $bookingID);
                exit();
            } else {
                echo "Error updating job status: " . $updateStmt->error;
            }

            $updateStmt->close();
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        // Handle invalid time inputs, e.g., display an error message
        echo "Invalid start time or end time format. Please enter valid datetime values.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Remote View</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="keep-session-alive.js"></script>
    <style>
        /* Initially hide all save buttons in input group append sections */
        button[name="saveStartTime"],
        button[name="saveEndTime"],
        button[name="saveBreakTime"] {
            display: none;
        }

        /* Styles for form elements */
        .form-group label {
            color: #495057;
            /* Dark gray for text for better readability */
            font-weight: bold;
            margin-bottom: 8px;
        }

        .input-group {
            margin-top: 10px;
        }

        .form-control {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            height: auto;
            /* Adjust height */
        }

        /* Button styles */
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            padding: 10px 24px;
            /* Larger padding for better touch target */
            font-size: 16px;
            /* Larger font size */
            border-radius: 0.25rem;
            box-shadow: 0 2px 2px rgba(0, 0, 0, 0.2);
            /* Subtle shadow for depth */
        }

        .btn-success:hover,
        .btn-success:focus {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .btn-default {
            background-color: #6c757d;
            /* Muted button for less important actions */
            color: #fff;
            border-color: #6c757d;
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        /* Additional visual tweaks */
        .input-group-append .btn,
        .input-group-prepend .btn {
            margin-left: 4px;
            /* spacing between button and input */
        }

        #additionalChargesContainer {
            margin-top: 15px;
        }

        /* Disabling visual styles for disabled inputs */
        input[disabled] {
            background-color: #e9ecef;
            color: #495057;
            cursor: not-allowed;
        }

        /* Styling for totals and fees */
        #totalTime,
        #billableTime,
        #billableAmount {
            font-weight: bold;
            color: #007bff;
        }

        /* Checkbox customization */
        #includeGST {
            margin-top: 5px;
            /* Align with label */
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
                    <h1 class="h2" id="Main-Heading">Remote View: Job Details Audit</h1>
                </div>
                <div class="container-fluid scrollable-container" name="Confirm-job-container">
                    <h2>Complete Details for <?= htmlspecialchars($jobDetails['BookingName']) ?>'s Move</h2>

                    <!-- Start Time Update Form -->
                    <form method="post">
                        <div class="form-group">
                            <label for="startTime">Start Time (Edit if Needed)</label>
                            <div class="input-group">
                                <input type="time" name="startTime" class="form-control" value="<?= date('H:i', strtotime($jobDetails['StartTime'])) ?>" placeholder="Start Time">
                                <div class="input-group-append">
                                    <button type="submit" name="saveStartTime" class="btn btn-success">Save</button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- End Time Update Form -->
                    <form method="post">
                        <div class="form-group">
                            <label for="endTime">End Time (Edit if Needed)</label>
                            <div class="input-group">
                                <input type="time" name="endTime" class="form-control" value="<?= date('H:i', strtotime($jobDetails['EndTime'])) ?>" placeholder="End Time">
                                <div class="input-group-append">
                                    <button type="submit" name="saveEndTime" class="btn btn-success">Save</button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Break Time Update Form -->
                    <form method="post">
                        <div class="form-group">
                            <label for="breakTime">Break Time (in minutes, Leave blank if none)</label>
                            <div class="input-group">
                                <input type="number" name="breakTime" class="form-control" value="<?= htmlspecialchars($jobDetails['BreakTime']) ?>" placeholder="Break Time">
                                <div class="input-group-append">
                                    <button type="submit" name="saveBreakTime" class="btn btn-success">Save</button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="form-group">
                        <label>Total Labor Time:</label>
                        <span id="totalTime"></span>
                    </div>

                    <div class="form-group">
                        <label for="hourlyRate">Hourly Rate</label>
                        <input type="text" id="hourlyRate" class="form-control" value="$<?= htmlspecialchars($jobDetails['Rate']) ?>" placeholder="Hourly Rate" disabled>
                    </div>
                    <div class="form-group">
                        <label for="calloutFee">Callout Fee</label>
                        <input type="text" id="calloutFee" class="form-control" value="<?= htmlspecialchars($jobDetails['CalloutFee']) ?> h" placeholder="Callout Fee" disabled>
                    </div>

                    <!-- Additional Charges Section -->
                    <div class="form-group">
                        <label for="additionalCharges">Additional Charges</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <button type="button" name="addChargeButton" class="btn btn-default">Add</button>
                            </div>
                        </div>
                        <!-- Container for additional charge input fields -->
                        <div id="additionalChargesContainer"></div>
                    </div>

                    <div class="form-group" name="BillableTime">
                        <label>Total Billable Time:</label>
                        <span id="billableTime"></span>
                    </div>

                    <div class="form-group" name="DepositAmount">
                        <label>Deposit:</label>
                        <input type="text" id="depositAmount" class="form-control" value="$<?= htmlspecialchars($jobDetails['Deposit']) ?>" placeholder="depositAmount" disabled>
                    </div>

                    <div class="form-group">
                        <label for="includeGST">Include GST</label>
                        <input type="checkbox" id="includeGST" checked>
                    </div>

                    <div class="form-group">
                        <label>Total Charge for Work Hours: $</label>
                        <span id="billableAmount"></span>
                    </div>

                    <form method="post" name="completeJobForm">
                        <!-- -----------------Handled By JS Function---------------------------------- -->
                        <input type="hidden" name="totalCharge" id="totalCharge">
                        <input type="hidden" name="totalLaborTime" id="totalLaborTime">
                        <input type="hidden" name="totalBillableTime" id="totalBillableTime">
                        <input type="hidden" name="gst" id="gst">
                        <!-- -------------------Set By PHP Variables-------------------------------------- -->
                        <input type="hidden" name="stairCharge" value="<?= $currentStairCharge ?>">
                        <input type="hidden" name="pianoCharge" value="<?= $currentPianoCharge ?>">
                        <input type="hidden" name="poolCharge" value="<?= $currentPoolTableCharge ?>">
                        <!-- ----------------Set through session variables------------------------------ -->
                        <input type="hidden" name="startTime" value="<?= $currentStartDateTime ?>">
                        <input type="hidden" name="endTime" value="<?= $currentEndDateTime ?>">
                        <input type="hidden" name="deposit" value="<?= $jobDetails['Deposit']; ?>">

                        <button type="submit" name="completeJobButton" class="btn btn-success btn-block" onclick="return showConfirmation()">Complete</button>
                    </form>

                </div>

                <div class="modal fade" id="additionalChargeModal" tabindex="-1" role="dialog" aria-labelledby="additionalChargeModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="additionalChargeModalLabel">Additional Charges</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <form id="additionalChargesForm">
                                    <div class="form-check">
                                        <?php if ($currentStairCharge == 0) : ?>
                                            <input class="form-check-input" type="checkbox" value="StairCharge" id="stairCharge">
                                        <?php else : ?>
                                            <input class="form-check-input" type="checkbox" value="StairCharge" id="stairCharge" checked>
                                        <?php endif; ?>
                                        <label class="form-check-label" for="stairCharge">
                                            Stair Charge
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <?php if ($currentPianoCharge == 0) : ?>
                                            <input class="form-check-input" type="checkbox" value="PianoCharge" id="pianoCharge">
                                        <?php else : ?>
                                            <input class="form-check-input" type="checkbox" value="PianoCharge" id="pianoCharge" checked>
                                        <?php endif; ?>
                                        <label class="form-check-label" for="pianoCharge">
                                            Piano Charge
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <?php if ($currentPoolTableCharge == 0) : ?>
                                            <input class="form-check-input" type="checkbox" value="PoolTableCharge" id="poolTableCharge">
                                        <?php else : ?>
                                            <input class="form-check-input" type="checkbox" value="PoolTableCharge" id="poolTableCharge" checked>
                                        <?php endif; ?>
                                        <label class="form-check-label" for="poolTableCharge">
                                            Pool Table Charge
                                        </label>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <!-- <button type="button" class="btn btn-primary" onclick="setupFormFields()">Add Charges</button> -->
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function showConfirmation() {
            return confirm("Are you sure you want to proceed to payment?");
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Function to set up form fields based on checkbox states
            function setupFormFields() {
                const chargesContainer = document.getElementById('additionalChargesContainer');
                chargesContainer.innerHTML = ''; // Clear previous inputs

                const form = document.createElement('form');
                form.method = 'post';
                chargesContainer.appendChild(form);

                const charges = {
                    stairCharge: currentStairCharge,
                    pianoCharge: currentPianoCharge,
                    poolTableCharge: currentPoolTableCharge
                };

                const chargeUnits = {
                    stairCharge: " (in $)",
                    pianoCharge: " (in hours)",
                    poolTableCharge: " (in hours)"
                };

                Object.keys(charges).forEach(id => {
                    const checkBox = document.getElementById(id);
                    if (checkBox.checked) {
                        const div = document.createElement('div');
                        div.className = 'form-group';
                        div.style.display = 'block'; // Ensure it's displayed if checkbox is checked

                        const label = document.createElement('label');
                        label.textContent = checkBox.labels[0].innerText + ' Amount' + chargeUnits[id];
                        label.htmlFor = id + 'Amount';

                        const inputGroup = document.createElement('div');
                        inputGroup.className = 'input-group';

                        const input = document.createElement('input');
                        input.type = 'number';
                        input.id = id + 'Amount';
                        input.name = id + 'Amount';
                        input.className = 'form-control';
                        input.placeholder = 'Enter ' + checkBox.labels[0].innerText + ' Amount' + chargeUnits[id];
                        input.value = charges[id]; // Pre-fill the input with the existing charge value

                        const inputGroupAppend = document.createElement('div');
                        inputGroupAppend.className = 'input-group-append';

                        const saveButton = document.createElement('button');
                        saveButton.type = 'submit';
                        saveButton.className = 'btn btn-success';
                        saveButton.textContent = 'Save';
                        saveButton.name = getButtonName(id); // Dynamically set the button name based on charge type

                        inputGroupAppend.appendChild(saveButton);
                        inputGroup.appendChild(input);
                        inputGroup.appendChild(inputGroupAppend);

                        div.appendChild(label);
                        div.appendChild(inputGroup);
                        form.appendChild(div);

                        $(div).slideDown(); // Use jQuery's slideDown to show the input nicely
                    }
                });
            }

            // Set up form fields when the page loads
            setupFormFields();

            document.querySelector('[name="addChargeButton"]').addEventListener('click', function() {
                $('#additionalChargeModal').modal('show');
            });

            // Helper function to determine the button name based on the charge type
            function getButtonName(chargeId) {
                switch (chargeId) {
                    case 'stairCharge':
                        return 'saveStairCharges';
                    case 'pianoCharge':
                        return 'savePianoCharges';
                    case 'poolTableCharge':
                        return 'savePoolTableCharges';
                    default:
                        return 'saveCharge'; // Fallback button name if needed
                }
            }

            document.querySelectorAll('.modal-body .form-check-input').forEach(input => {
                input.addEventListener('change', function() {
                    const targetID = this.id + 'Amount';
                    const existingInput = document.getElementById(targetID);
                    if (this.checked) {
                        if (!existingInput) {
                            setupFormFields(); // Call to update inputs immediately if needed
                        }
                    } else {
                        if (existingInput) {
                            $(existingInput).parent().slideUp(function() {
                                this.remove();
                            }); // Remove input if unchecked
                        }
                    }
                });
            });
        });


        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                let initialValue = input.value; // Store the initial value of the input
                const saveButton = input.closest('.input-group').querySelector('.btn-success');

                input.addEventListener('input', () => {
                    // When the input value changes, update the display of the save button
                    if (input.value !== initialValue) {
                        saveButton.style.display = 'block'; // Show save button if value changes
                    } else {
                        saveButton.style.display = 'none'; // Hide save button if value returns to initial
                    }
                });

                input.addEventListener('blur', () => {
                    // On blur, hide the save button only if the value hasn't changed
                    if (input.value === initialValue) {
                        saveButton.style.display = 'none';
                    }
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const chargesContainer = document.getElementById('additionalChargesContainer');

            // Event delegation for input events on dynamically created input fields
            chargesContainer.addEventListener('input', function(event) {
                if (event.target.classList.contains('form-control')) {
                    const input = event.target;
                    let initialValue = input.defaultValue; // Store the initial value of the input
                    const saveButton = input.closest('.input-group').querySelector('.btn-success');

                    // When the input value changes, update the display of the save button
                    if (input.value !== initialValue) {
                        saveButton.style.display = 'block'; // Show save button if value changes
                    } else {
                        saveButton.style.display = 'none'; // Hide save button if value returns to initial
                    }
                }
            });

            // Event delegation for blur events on dynamically created input fields
            chargesContainer.addEventListener('blur', function(event) {
                if (event.target.classList.contains('form-control')) {
                    const input = event.target;
                    let initialValue = input.defaultValue; // Initial value stored for comparison
                    const saveButton = input.closest('.input-group').querySelector('.btn-success');

                    // On blur, hide the save button only if the value hasn't changed
                    if (input.value === initialValue) {
                        saveButton.style.display = 'none';
                    }
                }
            }, true); // Using true for capture phase to ensure blur event is captured
        });


        document.addEventListener('DOMContentLoaded', function() {
            const totalTimeSpan = document.getElementById('totalTime');
            const billableTimeSpan = document.getElementById('billableTime');
            const billableAmountSpan = document.getElementById('billableAmount');
            const includeGSTCheckbox = document.getElementById('includeGST');

            // Hidden input fields
            const totalChargeInput = document.getElementById('totalCharge');
            const totalLaborTimeInput = document.getElementById('totalLaborTime');
            const totalBillableTimeInput = document.getElementById('totalBillableTime');
            const gstInput = document.getElementById('gst');

            // Convert SQL date-time strings to JavaScript Date objects
            const startTime = new Date('<?= $jobDetails['StartTime'] ?>');
            const endTime = new Date('<?= $jobDetails['EndTime'] ?>');
            const breakTime = parseInt('<?= $jobDetails['BreakTime'] ?>', 10); // Break time in minutes
            const calloutFee = parseFloat('<?= $jobDetails['CalloutFee'] ?>'); // Callout fee in hours
            const pianoCharge = parseFloat('<?= $jobDetails['PianoCharge'] ?>'); // Piano charge in hours
            const poolTableCharge = parseFloat('<?= $jobDetails['PoolTableCharge'] ?>'); // Pool table charge in hours
            const rate = parseFloat('<?= $jobDetails['Rate'] ?>'); // Rate per hour
            const stairCharges = parseFloat('<?= $jobDetails['StairCharges'] ?>'); // Additional stair charges
            const deposit = parseFloat('<?= $jobDetails['Deposit'] ?>'); // Deposit amount

            function calculateTotalTime() {
                if (startTime && endTime) {
                    let diff = endTime - startTime; // difference in milliseconds
                    let minutes = Math.floor(diff / 60000) - breakTime; // convert to minutes and subtract break time

                    if (minutes < 0) {
                        minutes = 0; // if break time is greater than work duration, set to 0
                    }

                    // Convert minutes to hours and round to nearest 0.5
                    let hours = Math.ceil((minutes / 60) * 2) / 2;

                    // Ensure minimum time of 2 hours
                    if (hours < 2) {
                        hours = 2;
                    }

                    // Display total hours
                    totalTimeSpan.textContent = `${hours.toFixed(1)} hours (excluding callout fee)`;

                    // Calculate total billable time
                    let totalBillableTime = hours + calloutFee + pianoCharge + poolTableCharge;

                    // Display total billable time
                    billableTimeSpan.textContent = `${totalBillableTime.toFixed(1)} hours`;

                    // Calculate the total charge excluding deposit
                    let totalChargeBeforeDeposit = (totalBillableTime * rate);

                    // Check if GST should be included
                    if (includeGSTCheckbox.checked) {
                        totalChargeBeforeDeposit *= 1.10; // Add 10% GST
                    }

                    // Subtract deposit and add stair charge to total charge after GST is added
                    let totalCharge = totalChargeBeforeDeposit + stairCharges - deposit;

                    // Display total charge depending on GST
                    // Check if GST should be included
                    if (includeGSTCheckbox.checked) {
                        billableAmountSpan.textContent = `${totalCharge.toFixed(2)} including GST`;
                    } else {
                        billableAmountSpan.textContent = `${totalCharge.toFixed(2)} excluding GST`;
                    }

                    // Update hidden inputs
                    totalChargeInput.value = totalCharge.toFixed(2);
                    totalLaborTimeInput.value = hours.toFixed(1);
                    totalBillableTimeInput.value = totalBillableTime.toFixed(1);
                    gstInput.value = includeGSTCheckbox.checked ? 1 : 0;

                }
            }

            // Call the function to calculate and display the total time and billable time
            calculateTotalTime();

            // Add an event listener to recalculate charges if GST checkbox changes
            includeGSTCheckbox.addEventListener('change', calculateTotalTime);
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Log the values of hidden fields by their IDs
            console.log('Total Charge:', document.getElementById('totalCharge').value);
            console.log('Total Labor Time:', document.getElementById('totalLaborTime').value);
            console.log('Total Billable Time:', document.getElementById('totalBillableTime').value);
            console.log('GST:', document.getElementById('gst').value);

            // Log the values of hidden fields with PHP values
            console.log('Stair Charge:', document.getElementsByName('stairCharge')[0].value);
            console.log('Piano Charge:', document.getElementsByName('pianoCharge')[0].value);
            console.log('Start Time:', document.getElementsByName('startTime')[0].value);
            console.log('End Time:', document.getElementsByName('endTime')[0].value);
            console.log('Deposit:', document.getElementsByName('deposit')[0].value);
        });
    </script>


</body>

</html>