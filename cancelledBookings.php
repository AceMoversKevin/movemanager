<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Back end logic
// Fetch cancelled bookings from the database
$query = "SELECT * FROM Bookings WHERE isActive = 0";
$result = $conn->query($query);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Cancelled Bookings</title>
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
                    <h1 class="h2" id="Main-Heading">Cancelled Bookings</h1>
                </div>
                <!-- Dashboard content goes here -->
                <div class="container mt-4">
                    <div class="row">
                        <?php while ($row = $result->fetch_assoc()) : ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card cancelled-booking-card">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($row['Name']) ?: "Please Update" ?></h5>
                                        <p class="card-text"><strong>Email: </strong> <?= htmlspecialchars($row['Email']) ?></p>
                                        <p class="card-text"><strong>Phone: </strong> <?= htmlspecialchars($row['Phone']) ?></p>
                                        <p class="card-text"><strong>Bedrooms: </strong> <?= htmlspecialchars($row['Bedrooms']) ?></p>
                                        <p class="card-text"><strong>Booking Date: </strong> <?= htmlspecialchars($row['BookingDate']) ?></p>
                                        <p class="card-text"><strong>Moving Date: </strong> <?= htmlspecialchars($row['MovingDate']) ?></p>
                                        <p class="card-text"><strong>Pickup Location: </strong> <?= htmlspecialchars($row['PickupLocation']) ?></p>
                                        <p class="card-text"><strong>Dropoff Location: </strong> <?= htmlspecialchars($row['DropoffLocation']) ?></p>
                                        <!-- Restore Booking button -->
                                        <button class="btn btn btn-outline-success btn-sm restore-booking" data-bookingid="<?= $row['BookingID'] ?>">Restore</button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
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
        document.querySelectorAll('.restore-booking').forEach(function(button) {
            button.addEventListener('click', function() {
                if (confirm('Are you sure you want to restore this booking?')) {
                    const bookingID = button.getAttribute('data-bookingid');

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'restoreBooking.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        if (this.status === 200) {
                            // Handle success (maybe refresh the list or show a message)
                            alert(this.responseText);
                            // Optionally, remove the card from view or mark it as cancelled
                            button.closest('.card').style.display = 'none';
                        } else {
                            // Handle error
                            alert('An error occurred while restoring the booking.');
                        }
                    };
                    xhr.onerror = function() {
                        alert('An error occurred during the request.');
                    };
                    xhr.send('bookingID=' + bookingID);
                }
            });
        });
    </script>



</body>

</html>