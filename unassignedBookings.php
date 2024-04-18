<?php
session_start();
include 'db.php'; // Ensure this points to your database connection file

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

// Fetch unassigned bookings
$bookingsQuery = "SELECT * FROM Bookings WHERE BookingID NOT IN (SELECT BookingID FROM Bookings_Employees)";
$bookings = $conn->query($bookingsQuery);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bookings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .card {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <header class="mb-3 py-3">
        <div class="container-fluid">
            <div class="row justify-content-between align-items-center">
                <div class="col-md-6 col-lg-4 user-info">
                    <img src="user.svg" alt="User icon">
                    <span><?= htmlspecialchars($_SESSION['username']); ?></span>
                </div>
                <div class="col-md-6 col-lg-4 text-md-right">
                    <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
                    <a href="unassignedBookings.php" class="btn btn-outline-primary">Unassigned Moves</a>
                    <a href="assignedBookings.php" class="btn btn-outline-primary">Assigned Moves</a>
                    <a href="logout.php" class="btn btn-outline-danger">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <h1>All Bookings</h1>
        <div class="row">
            <?php while ($row = $bookings->fetch_assoc()) : ?>
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Booking ID: <?= htmlspecialchars($row['BookingID']) ?></h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">Name: <?= htmlspecialchars($row['Name']) ?></li>
                                <li class="list-group-item">Email: <?= htmlspecialchars($row['Email']) ?></li>
                                <li class="list-group-item">Phone: <?= htmlspecialchars($row['Phone']) ?></li>
                                <li class="list-group-item">Bedrooms: <?= htmlspecialchars($row['Bedrooms']) ?></li>
                                <li class="list-group-item">Booking Date: <?= htmlspecialchars($row['BookingDate']) ?></li>
                                <li class="list-group-item">Moving Date: <?= htmlspecialchars($row['MovingDate']) ?></li>
                                <li class="list-group-item">Pickup Location: <?= htmlspecialchars($row['PickupLocation']) ?></li>
                                <li class="list-group-item">Dropoff Location: <?= htmlspecialchars($row['DropoffLocation']) ?></li>
                                <li class="list-group-item">Truck Size: <?= htmlspecialchars($row['TruckSize']) ?></li>
                                <li class="list-group-item">Callout Fee: <?= htmlspecialchars(number_format($row['CalloutFee'], 2)) ?></li>
                                <li class="list-group-item">Rate: <?= htmlspecialchars(number_format($row['Rate'], 2)) ?></li>
                                <li class="list-group-item">Deposit: <?= htmlspecialchars(number_format($row['Deposit'], 2)) ?></li>
                                <li class="list-group-item">Time Slot: <?= htmlspecialchars($row['TimeSlot']) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>

</html>