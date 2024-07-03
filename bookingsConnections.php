<?php
session_start();
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

$bookingId = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if ($bookingId === 0) {
    echo "Invalid booking ID.";
    exit;
}

// Fetch the booking details
$bookingQuery = "SELECT * FROM Bookings WHERE BookingID = $bookingId";
$bookingResult = $conn->query($bookingQuery);
$booking = $bookingResult->fetch_assoc();

if (!$booking) {
    echo "Booking not found.";
    exit;
}

// Fetch leads that match potential connections
$leadQuery = "
    SELECT * FROM leads 
    WHERE 
        (lead_name = '{$booking['Name']}' AND email = '{$booking['Email']}') OR
        (lead_name = '{$booking['Name']}' AND (pickup = '{$booking['PickupLocation']}' OR dropoff = '{$booking['DropoffLocation']}')) OR
        (email = '{$booking['Email']}') OR
        (phone = '{$booking['Phone']}')
";

$leadResult = $conn->query($leadQuery);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Booking Connections</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
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

            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Potential Lead Connections for Booking ID: <?= $bookingId ?></h1>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Lead ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Bedrooms</th>
                                <th>Pickup</th>
                                <th>Dropoff</th>
                                <th>Lead Date</th>
                                <th>Details</th>
                                <th>Acceptance Limit</th>
                                <th>Booking Status</th>
                                <th>Created At</th>
                                <th>Is Released</th>
                                <th>Source</th>
                                <th>Assigned To</th>
                                <th>Raw Lead ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($lead = $leadResult->fetch_assoc()) : ?>
                                <tr>
                                    <td><?= $lead['lead_id'] ?></td>
                                    <td><?= htmlspecialchars($lead['lead_name']) ?></td>
                                    <td><?= htmlspecialchars($lead['email']) ?></td>
                                    <td><?= htmlspecialchars($lead['phone']) ?></td>
                                    <td><?= htmlspecialchars($lead['bedrooms']) ?></td>
                                    <td><?= htmlspecialchars($lead['pickup']) ?></td>
                                    <td><?= htmlspecialchars($lead['dropoff']) ?></td>
                                    <td><?= htmlspecialchars($lead['lead_date']) ?></td>
                                    <td><?= htmlspecialchars($lead['details']) ?></td>
                                    <td><?= htmlspecialchars($lead['acceptanceLimit']) ?></td>
                                    <td><?= htmlspecialchars($lead['booking_status']) ?></td>
                                    <td><?= htmlspecialchars($lead['created_at']) ?></td>
                                    <td><?= htmlspecialchars($lead['isReleased']) ?></td>
                                    <td><?= htmlspecialchars($lead['Source']) ?></td>
                                    <td><?= htmlspecialchars($lead['AssignedTo']) ?></td>
                                    <td><?= htmlspecialchars($lead['rawLeadID']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
</body>

</html>
