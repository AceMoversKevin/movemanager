<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
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
$query = "
    SELECT 
        b.*, 
        jc.*, 
        jt.*, 
        GROUP_CONCAT(DISTINCT CONCAT(e.Name, ' (', e.EmployeeType, ')') SEPARATOR ', ') AS AssignedEmployees,
        GROUP_CONCAT(DISTINCT td.StartTime, ' - ', td.EndTime SEPARATOR ', ') AS TripDetails,
        GROUP_CONCAT(DISTINCT wh.EmployeePhoneNo, ': ', wh.HoursWorked, ' hours on ', wh.WeekStartDate SEPARATOR '; ') AS WorkHoursDetails
    FROM Bookings b
    LEFT JOIN JobCharges jc ON b.BookingID = jc.BookingID
    LEFT JOIN JobTimings jt ON b.BookingID = jt.BookingID
    LEFT JOIN BookingAssignments ba ON b.BookingID = ba.BookingID
    LEFT JOIN Employees e ON ba.EmployeePhoneNo = e.PhoneNo
    LEFT JOIN TripDetails td ON b.BookingID = td.BookingID
    LEFT JOIN WorkHours wh ON b.BookingID = wh.BookingID
    WHERE b.BookingID = ?
    GROUP BY b.BookingID
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $bookingID);
$stmt->execute();
$result = $stmt->get_result();
$bookingDetails = $result->fetch_assoc();
$stmt->close();

if (!$bookingDetails) {
    die("No details found for this booking.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Full details</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
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

            <!-- Main Content -->
            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2" id="Main-Heading">Full details for <?php echo htmlspecialchars($bookingDetails['Name']); ?>'s move</h1>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h3>Booking Details</h3>
                        <ul class="list-group">
                            <li class="list-group-item"><strong>Name:</strong> <?php echo htmlspecialchars($bookingDetails['Name']); ?></li>
                            <li class="list-group-item"><strong>Email:</strong> <?php echo htmlspecialchars($bookingDetails['Email']); ?></li>
                            <li class="list-group-item"><strong>Phone:</strong> <?php echo htmlspecialchars($bookingDetails['Phone']); ?></li>
                            <li class="list-group-item"><strong>Bedrooms:</strong> <?php echo htmlspecialchars($bookingDetails['Bedrooms']); ?></li>
                            <li class="list-group-item"><strong>Booking Date:</strong> <?php echo htmlspecialchars($bookingDetails['BookingDate']); ?></li>
                            <li class="list-group-item"><strong>Moving Date:</strong> <?php echo htmlspecialchars($bookingDetails['MovingDate']); ?></li>
                            <li class="list-group-item"><strong>Pickup Location:</strong> <?php echo htmlspecialchars($bookingDetails['PickupLocation']); ?></li>
                            <li class="list-group-item"><strong>Dropoff Location:</strong> <?php echo htmlspecialchars($bookingDetails['DropoffLocation']); ?></li>
                            <li class="list-group-item"><strong>Truck Size:</strong> <?php echo htmlspecialchars($bookingDetails['TruckSize']); ?></li>
                            <li class="list-group-item"><strong>Callout Fee:</strong> $<?php echo htmlspecialchars(number_format($bookingDetails['CalloutFee'], 2)); ?></li>
                            <li class="list-group-item"><strong>Rate:</strong> $<?php echo htmlspecialchars(number_format($bookingDetails['Rate'], 2)); ?></li>
                            <li class="list-group-item"><strong>Deposit:</strong> $<?php echo htmlspecialchars(number_format($bookingDetails['Deposit'], 2)); ?></li>
                            <li class="list-group-item"><strong>Time Slot:</strong> <?php echo htmlspecialchars($bookingDetails['TimeSlot']); ?></li>
                            <li class="list-group-item"><strong>Stair Charges:</strong> $<?php echo htmlspecialchars(number_format($bookingDetails['StairCharges'], 2)); ?></li>
                            <li class="list-group-item"><strong>Piano Charge:</strong> $<?php echo htmlspecialchars(number_format($bookingDetails['PianoCharge'], 2)); ?></li>
                            <li class="list-group-item"><strong>Pool Table Charge:</strong> $<?php echo htmlspecialchars(number_format($bookingDetails['PoolTableCharge'], 2)); ?></li>
                            <li class="list-group-item"><strong>Source:</strong> <?php echo htmlspecialchars($bookingDetails['Source']); ?></li>
                            <li class="list-group-item"><strong>Additional Details:</strong> <?php echo nl2br(htmlspecialchars($bookingDetails['AdditionalDetails'])); ?></li>
                            <li class="list-group-item"><strong>Signature:</strong> <?php echo htmlspecialchars($bookingDetails['signature']); ?></li>
                            <li class="list-group-item"><strong>Assigned Employees:</strong> <?php echo htmlspecialchars($bookingDetails['AssignedEmployees']); ?></li>
                        </ul>
                    </div>

                    <div class="col-md-6">
                        <h3>Job Details</h3>
                        <ul class="list-group">
                            <li class="list-group-item"><strong>Total Charge:</strong> $<?php echo htmlspecialchars(number_format($bookingDetails['TotalCharge'], 2)); ?></li>
                            <li class="list-group-item"><strong>Total Labor Time:</strong> <?php echo htmlspecialchars($bookingDetails['TotalLaborTime']); ?> hours</li>
                            <li class="list-group-item"><strong>Total Billable Time:</strong> <?php echo htmlspecialchars($bookingDetails['TotalBillableTime']); ?> hours</li>
                            <li class="list-group-item"><strong>Start Time:</strong> <?php echo htmlspecialchars($bookingDetails['StartTime']); ?></li>
                            <li class="list-group-item"><strong>End Time:</strong> <?php echo htmlspecialchars($bookingDetails['EndTime']); ?></li>
                            <li class="list-group-item"><strong>Break Time:</strong> <?php echo htmlspecialchars($bookingDetails['BreakTime']); ?> minutes</li>
                            <li class="list-group-item"><strong>Job Confirmed:</strong> <?php echo $bookingDetails['isConfirmed'] ? 'Yes' : 'No'; ?></li>
                            <li class="list-group-item"><strong>Job Completed:</strong> <?php echo $bookingDetails['isComplete'] ? 'Yes' : 'No'; ?></li>
                            <li class="list-group-item"><strong>Trip Details:</strong> <?php echo htmlspecialchars($bookingDetails['TripDetails']); ?></li>
                            <li class="list-group-item"><strong>Work Hours:</strong> <?php echo htmlspecialchars($bookingDetails['WorkHoursDetails']); ?></li>
                        </ul>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>

</html>