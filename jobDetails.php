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
// Fetch the booking ID from the URL
$bookingID = isset($_GET['BookingID']) ? intval($_GET['BookingID']) : 0;

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
                  GROUP_CONCAT(e.Name ORDER BY e.Name SEPARATOR ', ') AS EmployeeNames,
                  GROUP_CONCAT(ba.Role ORDER BY e.Name SEPARATOR ', ') AS EmployeeRoles
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
                    <h1 class="h2" id="Main-Heading">Details for <?php echo htmlspecialchars($jobDetails['BookingName']); ?>'s Job</h1>
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
                <?php else : ?>
                    <p>Job details not found.</p>
                    <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>



</body>

</html>