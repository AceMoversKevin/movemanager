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
        b.StairCharges, b.PianoCharge, b.PoolTableCharge,  -- Include the new columns
        e.Name AS EmployeeName, e.PhoneNo AS EmployeePhone, e.Email AS EmployeeEmail, e.EmployeeType
    FROM Bookings b
    JOIN BookingAssignments ba ON b.BookingID = ba.BookingID
    JOIN Employees e ON ba.EmployeePhoneNo = e.PhoneNo
    WHERE b.BookingID = ?
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
            $employeeNames[] = $row['EmployeeName'];
        } while ($row = $result->fetch_assoc());
    }
    $stmt->close();
    $employeeList = implode(', ', $employeeNames);
} else {
    header("Location: index.php");
    exit;
}

function checkJobAuditReadiness($conn, $bookingID)
{
    // Prepare the query to check if the start and end times are set and isComplete is 0
    $stmt = $conn->prepare("SELECT StartTime, EndTime, isComplete FROM JobTimings WHERE BookingID = ? AND StartTime IS NOT NULL AND EndTime IS NOT NULL");
    $stmt->bind_param("i", $bookingID);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if any results match the criteria
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['isComplete'] == 1) {
            // If isComplete is 1, redirect to a different page
            header("Location: invoice-payment.php?bookingID=" . $bookingID);
            exit;
        } elseif ($row['isComplete'] == 0) {
            // If conditions are met, redirect to the audit page
            header("Location: complete-job-audit.php?bookingID=" . $bookingID);
            exit;
        }
    }

    $stmt->close();
}

// Call this function at the appropriate place in your script where the database connection is available and the bookingID is defined
checkJobAuditReadiness($conn, $bookingID);

// Function to convert UTC time to Melbourne time
function toMelbourneTime($time)
{
    $date = new DateTime($time, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Australia/Melbourne'));
    return $date;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle the start of the job
    if (isset($_POST['startJob'])) {
        // Check if the signature is provided
        if (!isset($_POST['signature']) || empty($_POST['signature'])) {
            echo "<script>alert('Signature is required to start the job.');</script>";
        } else {
            // Save the signature as an image file
            $signatureData = $_POST['signature'];
            $signatureData = str_replace('data:image/png;base64,', '', $signatureData);
            $signatureData = str_replace(' ', '+', $signatureData);
            $signature = base64_decode($signatureData);

            $customerName = preg_replace('/[^a-zA-Z0-9]/', '_', $jobDetails['BookingName']); // Sanitize customer name
            $fileName = "signatures/{$bookingID}_{$customerName}.png";
            file_put_contents($fileName, $signature);

            // Save the signature file path in the Bookings table
            $stmt = $conn->prepare("UPDATE Bookings SET signature = ? WHERE BookingID = ?");
            $stmt->bind_param("si", $fileName, $bookingID);
            $stmt->execute();
            $stmt->close();

            // Start the job and record the start time
            $startTime = new DateTime('now', new DateTimeZone('Australia/Melbourne'));
            $stmt = $conn->prepare("INSERT INTO JobTimings (BookingID, StartTime) VALUES (?, ?)");
            $stmt->bind_param("is", $bookingID, $startTime->format('Y-m-d H:i:s'));
            $stmt->execute();
            $stmt->close();
            // Redirect to refresh and show the job as started
            header("Location: jobDetails.php?bookingID=$bookingID");
            exit;
        }
    }

    // Handle the end of the job
    if (isset($_POST['endJob'])) {
        $endTime = new DateTime('now', new DateTimeZone('Australia/Melbourne'));
        $formattedEndTime = $endTime->format('Y-m-d H:i:s');

        // Update the JobTimings table to set the end time
        $stmt = $conn->prepare("UPDATE JobTimings SET EndTime = ? WHERE BookingID = ? AND EndTime IS NULL");
        if (!$stmt) {
            die('MySQL prepare error: ' . $conn->error);
        }

        $stmt->bind_param("si", $formattedEndTime, $bookingID);
        $executeResult = $stmt->execute();

        if (!$executeResult) {
            die('Execute error: ' . $stmt->error);
        }

        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        if ($affectedRows === 0) {
            die('No rows updated, it may be that there is no such active job or it has already ended.');
        }

        // Redirect to refresh and show the job as ended
        header("Location: complete-job-audit.php?bookingID=$bookingID");
        exit;
    }
}



// Retrieve existing timing data
$stmt = $conn->prepare("SELECT StartTime FROM JobTimings WHERE BookingID = ? AND EndTime IS NULL");
$stmt->bind_param("i", $bookingID);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $startTime = new DateTime($row['StartTime'], new DateTimeZone('Australia/Melbourne'));
    $jobStarted = true;
} else {
    $jobStarted = false;
}
$stmt->close();

// Retrieve Job Completion status
function checkJobCompletionStatus($conn, $bookingID)
{
    // Initialize the completion status as false
    $isComplete = false;

    // Prepare and execute the query to check if the job is complete
    $stmt = $conn->prepare("SELECT isComplete FROM JobTimings WHERE BookingID = ? AND isComplete = 1");
    $stmt->bind_param("i", $bookingID);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch the result and determine if the job is marked as complete
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ((int)$row['isComplete'] === 1) {
            $isComplete = true;
        }
    }

    $stmt->close();
    return $isComplete;
}

// Usage
$isComplete = checkJobCompletionStatus($conn, $bookingID);

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
    <!-- Signature Pad CSS -->
    <style>
        .signature-pad {
            border: 1px solid #000;
            width: 100%;
            height: 200px;
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
                    <h1 class="h2" id="Main-Heading">Remote View: Job Timer</h1>
                </div>
                <!-- Dashboard content goes here -->
                <div class="container mt-3" id="job-details-container">
                    <h2>Job Details for <?= htmlspecialchars($jobDetails['BookingName']) ?>'s Move</h2>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($jobDetails['Phone']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($jobDetails['Email']) ?></p>
                    <p><strong>Pickup Location:</strong>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($jobDetails['PickupLocation']) ?>" target="_blank">
                            <?= htmlspecialchars($jobDetails['PickupLocation']) ?>
                        </a>
                    </p>
                    <p><strong>Dropoff Location:</strong>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($jobDetails['DropoffLocation']) ?>" target="_blank">
                            <?= htmlspecialchars($jobDetails['DropoffLocation']) ?>
                        </a>
                    </p>
                    <p><strong>Time Slot:</strong> <?= date('H:i', strtotime($jobDetails['TimeSlot'])) ?></p>
                    <p><strong>Callout Fee:</strong> <?= number_format($jobDetails['CalloutFee'], 2) ?> h</p>
                    <p><strong>The team for this move:</strong> <?= $employeeList ?></p>

                    <?php if ($jobStarted) : ?>
                        <!-- Separate form for ending the job -->
                        <form method="post" id="endJobForm">
                            <button type="submit" id="endJobButton" name="endJob" class="btn btn-danger btn-block">End Job</button>
                            <p>Started at: <?= $startTime->format('g:ia') ?></p>
                            <p>Time elapsed: <span id="timeElapsed"></span></p>
                        </form>
                    <?php else : ?>
                        <!-- Signature and job start form -->
                        <form method="post" id="startJobForm">
                            <div class="form-group">
                                <label for="signature">Customer Signature:</label>
                                <canvas id="signaturePad" class="signature-pad"></canvas>
                                <input type="hidden" id="signature" name="signature">
                            </div>
                            <button type="submit" id="startJobButton" name="startJob" class="btn btn-warning btn-block">Start Job</button>
                        </form>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="signature_pad.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const timeElapsed = document.getElementById('timeElapsed');
            let interval; // To manage the setInterval reference
            const jobStarted = <?= json_encode($jobStarted) ?>;
            const takeBreakButton = document.getElementById('takeBreakButton');
            const endBreakButton = document.getElementById('endBreakButton');

            // Initialize startTime from the server's time, correctly handled for time zone
            const startTimeString = '<?= $startTime ? $startTime->format('Y-m-d H:i:s') : 'now' ?>';
            let startTime = new Date(startTimeString); // Assuming startTime is provided in local time

            function updateTimeElapsed() {
                if (!jobStarted) {
                    clearInterval(interval);
                    return;
                }

                const now = new Date();
                let elapsed = now - startTime; // Calculate elapsed time in milliseconds

                if (elapsed < 0) {
                    elapsed = 0; // Prevent future start times from creating negative intervals
                }

                const hours = Math.floor(elapsed / 3600000);
                const minutes = Math.floor((elapsed % 3600000) / 60000);
                const seconds = Math.floor((elapsed % 60000) / 1000);
                timeElapsed.textContent = `${hours}h ${minutes.toString().padStart(2, '0')}m ${seconds.toString().padStart(2, '0')}s`;
            }

            function startTimer() {
                if (!interval) {
                    updateTimeElapsed();
                    interval = setInterval(updateTimeElapsed, 1000);
                }
            }

            // Start timer based on job status
            if (jobStarted) {
                startTimer();
            }

            // Initialize Signature Pad
            const canvas = document.getElementById('signaturePad');
            const signaturePad = new SignaturePad(canvas);
            const startJobForm = document.getElementById('startJobForm');

            startJobForm.addEventListener('submit', function(event) {
                if (signaturePad.isEmpty()) {
                    alert('Please provide a signature first.');
                    event.preventDefault();
                } else {
                    const signatureData = signaturePad.toDataURL();
                    document.getElementById('signature').value = signatureData;

                    // Additional confirmation dialog
                    const confirmation = confirm("I hereby confirm that the Move is ready to be started.");
                    if (!confirmation) {
                        event.preventDefault();
                    }
                }
            });

        });

        document.addEventListener('DOMContentLoaded', function() {
            const endJobButton = document.getElementById('endJobButton');
            const form = document.getElementById('endJobForm'); // Ensure this ID matches your form's ID

            console.log('Form:', form); // Check if the form is detected
            console.log('Button:', endJobButton); // Check if the button is detected

            if (endJobButton && form) {
                endJobButton.addEventListener('click', function(event) {
                    console.log('Button clicked'); // Confirm button click is detected
                    if (confirm('Are you sure you want to end the job?')) {
                        console.log('First Dialog Option Triggered');
                        //form.submit()
                        const confirmationInput = prompt("Type 'end' to confirm END of job.");
                        if (confirmationInput === "end") {
                            console.log('Form submission initiated'); // Check if submission is initiated
                            form.submit(); // Attempt to submit the form
                        } else {
                            alert('Job ending canceled. You did not type "end".');
                            event.preventDefault();
                        }
                    } else {
                        console.log('Job ending not confirmed'); // Check if the confirmation fails
                        event.preventDefault();
                    }
                });
            } else {
                console.log('Form or button not found'); // Output if form or button is not detected
            }
        });
    </script>



</body>

</html>