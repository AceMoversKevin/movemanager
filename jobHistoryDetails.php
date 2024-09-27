<?php
session_start();
require 'db.php';

// Check if the user is logged in and has the appropriate role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Get the BookingID from the URL
$bookingID = isset($_GET['bookingID']) ? intval($_GET['bookingID']) : 0;
if ($bookingID <= 0) {
    die("Invalid Booking ID.");
}

// Fetch booking details
$stmt = $conn->prepare("SELECT * FROM Bookings WHERE BookingID = ?");
$stmt->bind_param("i", $bookingID);
$stmt->execute();
$result = $stmt->get_result();
$bookingDetails = $result->fetch_assoc();
$stmt->close();
if (!$bookingDetails) {
    die("No details found for this booking.");
}

// Fetch assigned employees
$stmt = $conn->prepare("
    SELECT e.*
    FROM BookingAssignments ba
    JOIN Employees e ON ba.EmployeePhoneNo = e.PhoneNo
    WHERE ba.BookingID = ? AND (ba.isAccepted IS NULL OR ba.isAccepted = 1)
");
$stmt->bind_param("i", $bookingID);
$stmt->execute();
$assignedEmployeesResult = $stmt->get_result();
$assignedEmployees = [];
while ($row = $assignedEmployeesResult->fetch_assoc()) {
    $assignedEmployees[$row['PhoneNo']] = $row;
}
$stmt->close();

// Fetch job charges
$stmt = $conn->prepare("SELECT * FROM JobCharges WHERE BookingID = ?");
$stmt->bind_param("i", $bookingID);
$stmt->execute();
$result = $stmt->get_result();
$jobCharges = $result->fetch_assoc();
$stmt->close();

// Fetch job timings
$stmt = $conn->prepare("SELECT * FROM JobTimings WHERE BookingID = ?");
$stmt->bind_param("i", $bookingID);
$stmt->execute();
$result = $stmt->get_result();
$jobTimings = $result->fetch_assoc();
$stmt->close();

// Fetch trip details
$stmt = $conn->prepare("SELECT * FROM TripDetails WHERE BookingID = ? ORDER BY TripNumber ASC");
$stmt->bind_param("i", $bookingID);
$stmt->execute();
$tripDetailsResult = $stmt->get_result();
$tripDetails = [];
while ($row = $tripDetailsResult->fetch_assoc()) {
    $tripDetails[] = $row;
}
$stmt->close();

// Fetch partial hours
$stmt = $conn->prepare("SELECT ph.*, e.Name FROM PartialHours ph JOIN Employees e ON ph.PhoneNo = e.PhoneNo WHERE ph.BookingID = ?");
$stmt->bind_param("i", $bookingID);
$stmt->execute();
$partialHoursResult = $stmt->get_result();
$partialHours = [];
while ($row = $partialHoursResult->fetch_assoc()) {
    $partialHours[] = $row;
}
$stmt->close();

// Process partial hours to group by employee
$partialEmployees = [];
foreach ($partialHours as $ph) {
    $phoneNo = $ph['PhoneNo'];
    if (!isset($partialEmployees[$phoneNo])) {
        $partialEmployees[$phoneNo] = [];
    }
    $partialEmployees[$phoneNo][] = $ph;
}

// Determine job start and end times
$jobStartTime = $jobTimings['StartTime'];
$jobEndTime = $jobTimings['EndTime'];
if (!empty($tripDetails)) {
    $lastTrip = end($tripDetails);
    if ($lastTrip['EndTime'] > $jobEndTime) {
        $jobEndTime = $lastTrip['EndTime'];
    }
}

// Calculate total job duration in seconds
$jobDurationSeconds = strtotime($jobEndTime) - strtotime($jobStartTime);

// Prepare employees working times
$employeesWorkingTimes = [];
foreach ($assignedEmployees as $employee) {
    $phoneNo = $employee['PhoneNo'];
    $name = $employee['Name'];
    if (isset($partialEmployees[$phoneNo])) {
        // Employee has partial hours
        $totalSeconds = 0;
        $employeePartialHours = [];
        foreach ($partialEmployees[$phoneNo] as $ph) {
            $startTime = strtotime($ph['StartTime']);
            $endTime = strtotime($ph['EndTime']);
            $duration = $endTime - $startTime;
            $totalSeconds += $duration;
            $ph['Duration'] = $duration;
            $employeePartialHours[] = $ph;
        }
        $employeesWorkingTimes[$phoneNo] = [
            'PhoneNo' => $phoneNo,
            'Name' => $name,
            'Partial' => true,
            'TotalWorkingTime' => $totalSeconds,
            'PartialHours' => $employeePartialHours,
            'EmployeeType' => $employee['EmployeeType']
        ];
    } else {
        // Employee worked full job duration
        $startTime = strtotime($jobStartTime);
        $endTime = strtotime($jobEndTime);
        $totalSeconds = $endTime - $startTime;
        $employeesWorkingTimes[$phoneNo] = [
            'PhoneNo' => $phoneNo,
            'Name' => $name,
            'Partial' => false,
            'StartTime' => $jobStartTime,
            'EndTime' => $jobEndTime,
            'TotalWorkingTime' => $totalSeconds,
            'EmployeeType' => $employee['EmployeeType']
        ];
    }
}

// Format duration function
function format_duration($seconds)
{
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    return sprintf("%d hours %d minutes", $hours, $minutes);
}

// Find signature image
//$signatureDir = 'https://movers.alphamovers.com.au/signatures/'; (globe() function does not read URLs, path should be as follows
$signatureDir = '/home/alphaard/movers.alphamovers.com.au/signatures/';
$signatureFilePattern = $signatureDir . $bookingID . '_' . '*' . '.*';
$signatureFiles = glob($signatureFilePattern);
$signatureFile = !empty($signatureFiles) ? $signatureFiles[0] : null;

// Find transfer images
$transferDir = 'https://movers.alphamovers.com.au/TransferImages/';
$transferFilePattern = $transferDir . $bookingID . '_' . '*' . '.*';
$transferFiles = glob($transferFilePattern);
$transferImages = [];
foreach ($transferFiles as $file) {
    $filename = basename($file);
    // Parse filename to get amount and timestamp
    // Format: BookingID_Name_Amount_Timestamp.ext
    $parts = explode('_', $filename);
    if (count($parts) >= 4) {
        $amount = $parts[2];
        $timestampWithExt = $parts[3];
        // Remove extension from timestamp
        $timestamp = preg_replace('/\.[^.]+$/', '', $timestampWithExt);
        $transferImages[] = [
            'file' => $file,
            'amount' => $amount,
            'timestamp' => $timestamp
        ];
    }
}

// Fetch payment method from CompletedJobs
$stmt = $conn->prepare("SELECT PaymentMethod FROM CompletedJobs WHERE BookingID = ?");
$stmt->bind_param("i", $bookingID);
$stmt->execute();
$result = $stmt->get_result();
$completedJob = $result->fetch_assoc();
$stmt->close();

// Check if payment method exists
$paymentMethod = $completedJob ? $completedJob['PaymentMethod'] : 'Not Specified';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Full Details</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <script src="keep-session-alive.js"></script>
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
                    <h1 class="h2">Full Details for <?php echo htmlspecialchars($bookingDetails['Name']); ?>'s Move</h1>
                </div>
                <div class="row">
                    <!-- Booking Details -->
                    <div class="col-md-6">
                        <h3>Booking Details</h3>
                        <table class="table table-bordered">
                            <tr>
                                <th>Name</th>
                                <td><?php echo htmlspecialchars($bookingDetails['Name']); ?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?php echo htmlspecialchars($bookingDetails['Email']); ?></td>
                            </tr>
                            <tr>
                                <th>Phone</th>
                                <td><?php echo htmlspecialchars($bookingDetails['Phone']); ?></td>
                            </tr>
                            <tr>
                                <th>Bedrooms</th>
                                <td><?php echo htmlspecialchars($bookingDetails['Bedrooms']); ?></td>
                            </tr>
                            <tr>
                                <th>Booking Date</th>
                                <td><?php echo htmlspecialchars($bookingDetails['BookingDate']); ?></td>
                            </tr>
                            <tr>
                                <th>Moving Date</th>
                                <td><?php echo htmlspecialchars($bookingDetails['MovingDate']); ?></td>
                            </tr>
                            <tr>
                                <th>Pickup Location</th>
                                <td><?php echo htmlspecialchars($bookingDetails['PickupLocation']); ?></td>
                            </tr>
                            <tr>
                                <th>Dropoff Location</th>
                                <td><?php echo htmlspecialchars($bookingDetails['DropoffLocation']); ?></td>
                            </tr>
                            <tr>
                                <th>Truck Size</th>
                                <td><?php echo htmlspecialchars($bookingDetails['TruckSize']); ?></td>
                            </tr>
                            <tr>
                                <th>Callout Fee</th>
                                <td>$<?php echo number_format($bookingDetails['CalloutFee'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Rate</th>
                                <td>$<?php echo number_format($bookingDetails['Rate'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Deposit</th>
                                <td>$<?php echo number_format($bookingDetails['Deposit'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Time Slot</th>
                                <td><?php echo htmlspecialchars($bookingDetails['TimeSlot']); ?></td>
                            </tr>
                            <tr>
                                <th>Stair Charges</th>
                                <td>$<?php echo number_format($bookingDetails['StairCharges'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Piano Charge</th>
                                <td>$<?php echo number_format($bookingDetails['PianoCharge'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Pool Table Charge</th>
                                <td>$<?php echo number_format($bookingDetails['PoolTableCharge'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Source</th>
                                <td><?php echo htmlspecialchars($bookingDetails['Source']); ?></td>
                            </tr>
                            <tr>
                                <th>Additional Details</th>
                                <td><?php echo nl2br(htmlspecialchars($bookingDetails['AdditionalDetails'])); ?></td>
                            </tr>
                            <tr>
                                <th>Signature</th>
                                <td>
                                    <!--
                                    <?php if ($signatureFile): ?>
                                        <img src="<?php echo htmlspecialchars($signatureFile); ?>" alt="Signature" style="max-width: 100%;">
                                    <?php else: ?>
                                        No signature available.
                                    <?php endif; ?>
                                    <?php if ($signatureFile): ?>
                              -->
                                    <?php
                                        // globe() returns file paths, not URLs, se we have to reconstruct URL as follows                                
                                        // Define the base URL to your signatures directory
                                        $baseURL = 'https://movers.alphamovers.com.au/signatures/';
                                        // Get the filename from the filesystem path
                                        $filename = basename($signatureFile);
                                        // Construct the full URL
                                        $signatureURL = $baseURL . $filename;
                                    ?>
                                    <img src="<?php echo htmlspecialchars($signatureURL); ?>" alt="Signature" style="max-width: 100%;">
                                <?php else: ?>
                                    No signature available.
                                <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <!-- Job Details -->
                    <div class="col-md-6">
                        <h3>Job Details</h3>
                        <table class="table table-bordered">
                            <?php if ($jobCharges): ?>
                                <tr>
                                    <th>Total Charge</th>
                                    <td>$<?php echo number_format($jobCharges['TotalCharge'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Total Labor Time</th>
                                    <td><?php echo htmlspecialchars($jobCharges['TotalLaborTime']); ?> hours</td>
                                </tr>
                                <tr>
                                    <th>Total Billable Time</th>
                                    <td><?php echo htmlspecialchars($jobCharges['TotalBillableTime']); ?> hours</td>
                                </tr>
                                <tr>
                                    <th>Deposit</th>
                                    <td>$<?php echo number_format($jobCharges['Deposit'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Piano Charge</th>
                                    <td>$<?php echo number_format($jobCharges['PianoCharge'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Pool Table Charge</th>
                                    <td>$<?php echo number_format($jobCharges['PoolTableCharge'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Stair Charge</th>
                                    <td>$<?php echo number_format($jobCharges['StairCharge'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Paid Amount Card</th>
                                    <td>$<?php echo number_format($jobCharges['PaidAmountCard'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Paid Amount Transfer</th>
                                    <td>$<?php echo number_format($jobCharges['PaidAmountTransfer'], 2); ?></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2">No job charges available.</td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($jobTimings): ?>
                                <tr>
                                    <th>Job Confirmed</th>
                                    <td><?php echo $jobTimings['isConfirmed'] ? 'Yes' : 'No'; ?></td>
                                </tr>
                                <tr>
                                    <th>Job Completed</th>
                                    <td><?php echo $jobTimings['isComplete'] ? 'Yes' : 'No'; ?></td>
                                </tr>
                                <tr>
                                    <th>Start Time</th>
                                    <td><?php echo htmlspecialchars($jobStartTime); ?></td>
                                </tr>
                                <tr>
                                    <th>End Time</th>
                                    <td><?php echo htmlspecialchars($jobEndTime); ?></td>
                                </tr>
                                <tr>
                                    <th>Total Job Duration</th>
                                    <td><?php echo format_duration($jobDurationSeconds); ?></td>
                                </tr>
                                <tr>
                                    <th>Break Time</th>
                                    <td><?php echo htmlspecialchars($jobTimings['BreakTime']); ?> minutes</td>
                                </tr>
                                <tr>
                                    <th>Final Payment method</th>
                                    <td><?php echo htmlspecialchars($completedJob['PaymentMethod']); ?></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2">No job timings available.</td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                <!-- Assigned Employees -->
                <div class="row">
                    <div class="col-md-12">
                        <h3>Assigned Employees</h3>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Employee Name</th>
                                    <th>Employee Type</th>
                                    <th>Working Times</th>
                                    <th>Total Working Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employeesWorkingTimes as $employee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($employee['Name']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['EmployeeType']); ?></td>
                                        <td>
                                            <?php if ($employee['Partial']): ?>
                                                <?php foreach ($employee['PartialHours'] as $ph): ?>
                                                    Start: <?php echo htmlspecialchars($ph['StartTime']); ?><br>
                                                    End: <?php echo htmlspecialchars($ph['EndTime']); ?><br>
                                                    Duration: <?php echo format_duration($ph['Duration']); ?><br>
                                                    Reason: <?php echo nl2br(htmlspecialchars($ph['Reason'])); ?><br><br>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                Start: <?php echo htmlspecialchars($employee['StartTime']); ?><br>
                                                End: <?php echo htmlspecialchars($employee['EndTime']); ?><br>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo format_duration($employee['TotalWorkingTime']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Trip Details -->
                <?php if (!empty($tripDetails)): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <h3>Trip Details</h3>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Trip Number</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tripDetails as $trip): ?>
                                        <?php
                                        $tripStartTime = strtotime($trip['StartTime']);
                                        $tripEndTime = strtotime($trip['EndTime']);
                                        $tripDuration = $tripEndTime - $tripStartTime;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($trip['TripNumber']); ?></td>
                                            <td><?php echo htmlspecialchars($trip['StartTime']); ?></td>
                                            <td><?php echo htmlspecialchars($trip['EndTime']); ?></td>
                                            <td><?php echo format_duration($tripDuration); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Partial Hours -->
                <?php if (!empty($partialHours)): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <h3>Partial Hours Details</h3>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Employee Name</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Duration</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($partialHours as $ph): ?>
                                        <?php
                                        $startTime = strtotime($ph['StartTime']);
                                        $endTime = strtotime($ph['EndTime']);
                                        $duration = $endTime - $startTime;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($ph['Name']); ?></td>
                                            <td><?php echo htmlspecialchars($ph['StartTime']); ?></td>
                                            <td><?php echo htmlspecialchars($ph['EndTime']); ?></td>
                                            <td><?php echo format_duration($duration); ?></td>
                                            <td><?php echo nl2br(htmlspecialchars($ph['Reason'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Transfer Images -->
                <?php if (!empty($transferImages)): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <h3>Transfer Images</h3>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Amount</th>
                                        <th>Timestamp</th>
                                        <th>Image</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transferImages as $img): ?>
                                        <tr>
                                            <td>$<?php echo htmlspecialchars($img['amount']); ?></td>
                                            <td><?php echo htmlspecialchars($img['timestamp']); ?></td>
                                            <td><img src="<?php echo htmlspecialchars($img['file']); ?>" alt="Transfer Image" style="max-width: 100%;"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>