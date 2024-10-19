<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['Name'];
    $email = $_POST['Email'];
    $phone = $_POST['Phone'];
    $bedrooms = $_POST['Bedrooms'];
    $movingDate = $_POST['MovingDate'];
    $pickupLocation = $_POST['PickupLocation'];
    $dropoffLocation = $_POST['DropoffLocation'];
    $truckSize = $_POST['TruckSize'];
    $calloutFee = $_POST['CalloutFee'];
    $rate = $_POST['Rate'];
    $deposit = $_POST['Deposit'];
    $timeSlot = $_POST['TimeSlot'];
    $details = $_POST['AdditionalDetails'];
    $source = 'Manual';
    $isActive = 1;

    $stmt = $conn->prepare("INSERT INTO Bookings (Name, Email, Phone, Bedrooms, BookingDate, MovingDate, PickupLocation, DropoffLocation, TruckSize, CalloutFee, Rate, Deposit, TimeSlot, isActive, Source, AdditionalDetails) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssissssssssiss", $name, $email, $phone, $bedrooms, $movingDate, $pickupLocation, $dropoffLocation, $truckSize, $calloutFee, $rate, $deposit, $timeSlot, $isActive, $source, $details);

    if ($stmt->execute()) {
        echo "Booking created successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Manual Booking</title>
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
                    <h1 class="h2" id="Main-Heading">Manual Booking</h1>
                </div>
                <form method="post" action="createBooking.php">
                    <div class="form-group">
                        <label for="Name">Name</label>
                        <input type="text" class="form-control" id="Name" name="Name" required>
                    </div>
                    <div class="form-group">
                        <label for="Email">Email</label>
                        <input type="email" class="form-control" id="Email" name="Email" required>
                    </div>
                    <div class="form-group">
                        <label for="Phone">Phone</label>
                        <input type="text" class="form-control" id="Phone" name="Phone" required>
                    </div>
                    <div class="form-group">
                        <label for="Bedrooms">Bedrooms</label>
                        <input type="number" class="form-control" id="Bedrooms" name="Bedrooms" required>
                    </div>
                    <div class="form-group">
                        <label for="MovingDate">Moving Date</label>
                        <input type="date" class="form-control" id="MovingDate" name="MovingDate" required>
                    </div>
                    <div class="form-group">
                        <label for="PickupLocation">Pickup Location</label>
                        <input type="text" class="form-control" id="PickupLocation" name="PickupLocation" required>
                    </div>
                    <div class="form-group">
                        <label for="DropoffLocation">Dropoff Location</label>
                        <input type="text" class="form-control" id="DropoffLocation" name="DropoffLocation" required>
                    </div>
                    <div class="form-group">
                        <label for="TruckSize">Truck Size</label>
                        <input type="text" class="form-control" id="TruckSize" name="TruckSize" required>
                    </div>
                    <div class="form-group">
                        <label for="CalloutFee">Callout Fee</label>
                        <input type="number" step="0.01" class="form-control" id="CalloutFee" name="CalloutFee" required>
                    </div>
                    <div class="form-group">
                        <label for="Rate">Rate</label>
                        <input type="number" step="0.01" class="form-control" id="Rate" name="Rate" required>
                    </div>
                    <div class="form-group">
                        <label for="Deposit">Deposit</label>
                        <input type="number" step="0.01" class="form-control" id="Deposit" name="Deposit" required>
                    </div>
                    <div class="form-group">
                        <label for="TimeSlot">Time Slot</label>
                        <input type="time" class="form-control" id="TimeSlot" name="TimeSlot" required>
                    </div>
                    <div class="form-group">
                        <label for="AdditionalDetails">Additional Details</label>
                        <textarea class="form-control" id="AdditionalDetails" name="AdditionalDetails" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Booking</button>
                </form>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>

</html>