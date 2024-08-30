<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Get the PhoneNo from the URL
$phoneNo = isset($_GET['PhoneNo']) ? htmlspecialchars($_GET['PhoneNo']) : '';

if (empty($phoneNo)) {
    die("Invalid Employee Phone Number.");
}

// Fetch the employee's name and assignment history from the database
$query = "
    SELECT 
        ba.AssignmentID,
        ba.BookingID,
        ba.isAccepted,
        b.Name AS CustomerName,
        b.PickupLocation,
        b.DropoffLocation,
        b.MovingDate,
        b.TimeSlot,
        b.TruckSize,
        jc.TotalCharge,
        jc.StairCharge,
        jc.PianoCharge,
        jc.PoolTableCharge,
        jc.TotalLaborTime,
        wh.HoursWorked,
        wh.WeekStartDate,
        wh.TripNumber
    FROM BookingAssignments ba
    JOIN Bookings b ON ba.BookingID = b.BookingID
    LEFT JOIN JobCharges jc ON ba.BookingID = jc.BookingID
    LEFT JOIN WorkHours wh ON ba.BookingID = wh.BookingID AND wh.EmployeePhoneNo = ba.EmployeePhoneNo
    WHERE ba.EmployeePhoneNo = ?
    ORDER BY b.MovingDate DESC, b.TimeSlot DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $phoneNo);
$stmt->execute();
$result = $stmt->get_result();
$assignments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Shift History</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
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
                    <h1 class="h2">Shift History for <?php echo htmlspecialchars($phoneNo); ?></h1>
                </div>

                <!-- Shift History Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Assignment ID</th>
                                <th>Booking ID</th>
                                <th>Customer Name</th>
                                <th>Pickup Location</th>
                                <th>Dropoff Location</th>
                                <th>Moving Date</th>
                                <th>Time Slot</th>
                                <th>Truck Size</th>
                                <th>Total Charge</th>
                                <th>Stair Charge</th>
                                <th>Piano Charge</th>
                                <th>Pool Table Charge</th>
                                <th>Total Labor Time</th>
                                <th>Work Hours</th>
                                <th>Week Start Date</th>
                                <th>Trip Number</th>
                                <th>Accepted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($assignments) > 0): ?>
                                <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['AssignmentID']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['BookingID']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['CustomerName']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['PickupLocation']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['DropoffLocation']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['MovingDate']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['TimeSlot']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['TruckSize']); ?></td>
                                        <td>$<?php echo htmlspecialchars(number_format($assignment['TotalCharge'], 2)); ?></td>
                                        <td>$<?php echo htmlspecialchars(number_format($assignment['StairCharge'], 2)); ?></td>
                                        <td>$<?php echo htmlspecialchars(number_format($assignment['PianoCharge'], 2)); ?></td>
                                        <td>$<?php echo htmlspecialchars(number_format($assignment['PoolTableCharge'], 2)); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['TotalLaborTime']); ?> hours</td>
                                        <td><?php echo htmlspecialchars($assignment['HoursWorked']); ?> hours</td>
                                        <td><?php echo htmlspecialchars($assignment['WeekStartDate']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['TripNumber']); ?></td>
                                        <td><?php echo $assignment['isAccepted'] ? 'Yes' : 'No'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="17">No shift history found for this employee.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
