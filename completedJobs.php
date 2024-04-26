<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit;
}

// Fetch the bookings and the employees assigned to each booking.
$query = "SELECT 
b.BookingID, 
b.Name AS BookingName, 
b.Email AS BookingEmail, 
b.Phone AS BookingPhone, 
b.Bedrooms, 
b.MovingDate,
b.PickupLocation,
b.DropoffLocation
FROM 
Bookings b
JOIN 
BookingAssignments ba ON b.BookingID = ba.BookingID
JOIN 
Employees e ON ba.EmployeePhoneNo = e.PhoneNo
WHERE 
b.isActive = 1 AND
b.BookingID IN (SELECT BookingID FROM CompletedJobs)
GROUP BY 
b.BookingID;
";

$result = $conn->query($query);



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Jobs</title>
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
                    <h1 class="h2" id="Main-Heading">Completed Jobs</h1>
                </div>
                <!-- Dashboard content goes here -->
                <div class="row">
                    <?php
                    if ($result->num_rows > 0) {
                        // Output data of each row
                        while ($row = $result->fetch_assoc()) {
                            echo '<div class="col-md-4">
                                    <a href="jobDetails.php?BookingID=' . htmlspecialchars($row["BookingID"]) . '" class="card mb-4 shadow-sm text-decoration-none text-dark">
                                        <div class="card-header">
                                            <h4 class="my-0 font-weight-normal">' . htmlspecialchars($row["BookingName"]) . '</h4>
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title pricing-card-title">Moving Date: ' . htmlspecialchars($row["MovingDate"]) . '</h5>
                                            <ul class="list-unstyled mt-3 mb-4">
                                                <li>Email: ' . htmlspecialchars($row["BookingEmail"]) . '</li>
                                                <li>Phone: ' . htmlspecialchars($row["BookingPhone"]) . '</li>
                                                <li>Bedrooms: ' . htmlspecialchars($row["Bedrooms"]) . '</li>
                                                <li>Pickup Location: ' . htmlspecialchars($row["PickupLocation"]) . '</li>
                                                <li>Dropoff Location: ' . htmlspecialchars($row["DropoffLocation"]) . '</li>
                                            </ul>
                                            <button type="button" class="btn btn-outline-danger incompleteJob" data-bookingid="' . htmlspecialchars($row["BookingID"]) . '">Mark as Incomplete</button>
                                        </div>
                                    </a>
                                    
                                </div>';
                            // echo '';
                        }
                    } else {
                        echo "<p>No assigned jobs found.</p>";
                    }
                    ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            $('.incompleteJob').click(function() {
                event.preventDefault(); // This prevents the default action of the button
                event.stopPropagation(); // This stops the click event from "bubbling" up to the parent elements
                var bookingId = $(this).data('bookingid'); // Get the booking ID
                $.ajax({
                    url: 'mark-incompleted.php', // The script to call to delete data from the CompletedJobs table
                    type: 'POST',
                    data: {
                        'bookingID': bookingId
                    },
                    success: function(response) {
                        console.log(response);
                        location.reload(); // Reload the page to update the list
                    },
                    error: function(xhr, status, error) {
                        console.error("An error occurred: " + error);
                    }
                });
            });
        });
    </script>



</body>

</html>