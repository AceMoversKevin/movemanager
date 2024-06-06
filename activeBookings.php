<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin' || $_SESSION['role'] != 'SuperAdmin') {
    header("Location: login.php");
    exit;
}

// Back end logic
// Fetch active bookings from the database
$query = "SELECT * FROM Bookings WHERE isActive = 1";

// Check if sorting criteria is provided
if (isset($_GET['movingDate']) && !empty($_GET['movingDate'])) {
    $movingDate = $_GET['movingDate'];
    $query .= " AND MovingDate = '$movingDate'";
}

if (isset($_GET['bookingDate']) && !empty($_GET['bookingDate'])) {
    $bookingDate = $_GET['bookingDate'];
    $query .= " AND BookingDate = '$bookingDate'";
}

if (isset($_GET['movingDateStart']) && isset($_GET['movingDateEnd']) && !empty($_GET['movingDateStart']) && !empty($_GET['movingDateEnd'])) {
    $movingDateStart = $_GET['movingDateStart'];
    $movingDateEnd = $_GET['movingDateEnd'];
    $query .= " AND MovingDate BETWEEN '$movingDateStart' AND '$movingDateEnd'";
}

if (isset($_GET['bookingDateStart']) && isset($_GET['bookingDateEnd']) && !empty($_GET['bookingDateStart']) && !empty($_GET['bookingDateEnd'])) {
    $bookingDateStart = $_GET['bookingDateStart'];
    $bookingDateEnd = $_GET['bookingDateEnd'];
    $query .= " AND BookingDate BETWEEN '$bookingDateStart' AND '$bookingDateEnd'";
}

$result = $conn->query($query);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Bookings</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="activeBookings.css">
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
                    <h1 class="h2" id="Main-Heading">Active Bookings</h1>
                </div>

                <form method="GET" action="">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="movingDate">Moving Date</label>
                            <input type="date" class="form-control" id="movingDate" name="movingDate">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="bookingDate">Booking Date</label>
                            <input type="date" class="form-control" id="bookingDate" name="bookingDate">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="movingDateStart">Moving Date Range Start</label>
                            <input type="date" class="form-control" id="movingDateStart" name="movingDateStart">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="movingDateEnd">Moving Date Range End</label>
                            <input type="date" class="form-control" id="movingDateEnd" name="movingDateEnd">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="bookingDateStart">Booking Date Range Start</label>
                            <input type="date" class="form-control" id="bookingDateStart" name="bookingDateStart">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="bookingDateEnd">Booking Date Range End</label>
                            <input type="date" class="form-control" id="bookingDateEnd" name="bookingDateEnd">
                        </div>
                        <div class="form-group col-md-12">
                            <button type="submit" class="btn btn-primary">Sort</button>
                        </div>
                    </div>
                </form>
                <!-- Dashboard content goes here -->
                <div class="container mt-4">
                    <div class="row">
                        <?php while ($row = $result->fetch_assoc()) : ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card active-booking-card">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($row['Name']) ?: "Please Update" ?></h5>
                                        <p class="card-text"><strong>Email: </strong> <?= htmlspecialchars($row['Email']) ?: "Please Update" ?></p>
                                        <p class="card-text"><strong>Phone: </strong> <?= htmlspecialchars($row['Phone']) ?: "Please Update" ?></p>
                                        <p class="card-text"><strong>Bedrooms: </strong> <?= htmlspecialchars($row['Bedrooms']) ?: "Please Update" ?></p>
                                        <p class="card-text"><strong>Booking Date: </strong> <?= htmlspecialchars($row['BookingDate']) ?: "Please Update" ?></p>
                                        <p class="card-text"><strong>Moving Date: </strong> <?= htmlspecialchars($row['MovingDate']) ?: "Please Update" ?></p>
                                        <p class="card-text"><strong>Pickup Location: </strong> <?= htmlspecialchars($row['PickupLocation']) ?: "Please Update" ?></p>
                                        <p class="card-text"><strong>Dropoff Location: </strong> <?= htmlspecialchars($row['DropoffLocation']) ?: "Please Update" ?></p>
                                        <!-- Cancel booking button -->
                                        <button class="btn btn btn-outline-danger btn-sm cancel-booking" data-bookingid="<?= $row['BookingID'] ?>">Cancel</button>
                                        <!-- Modal trigger button -->
                                        <i class="fa fa-arrows-alt" aria-hidden="true" data-toggle="modal" data-target="#modal<?= $row['BookingID'] ?>" style="cursor:pointer; float:right;"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal -->
                            <div class="modal fade" id="modal<?= $row['BookingID'] ?>" data-bookingid="<?= $row['BookingID'] ?>" tabindex="-1" aria-labelledby="modalLabel<?= $row['BookingID'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="modalLabel<?= $row['BookingID'] ?>">Booking Details for <strong><?= htmlspecialchars($row['Name']) ?></strong></h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <!-- All details here -->
                                            <p><strong>Name: </strong> <?= htmlspecialchars($row['Name']) ?></p>
                                            <div class="editableField">
                                                <strong>Email: </strong>
                                                <span><?= htmlspecialchars($row['Email']) ?></span>
                                                <input type="text" class="editInput" name="Email" value="<?= htmlspecialchars($row['Email']) ?>" style="display:none;">
                                                <i class="fa fa-pencil-square-o edit" aria-hidden="true"></i>
                                                <!-- Save and Cancel buttons -->
                                                <button type="button" class="saveEdit btn btn-success btn-sm" style="display:none;">Save</button>
                                                <button type="button" class="cancelEdit btn btn-danger btn-sm" style="display:none;">Cancel</button>
                                            </div>
                                            <div class="editableField">
                                                <strong>Phone: </strong>
                                                <span><?= htmlspecialchars($row['Phone']) ?></span>
                                                <input type="text" class="editInput" name="Phone" value="<?= htmlspecialchars($row['Phone']) ?>" style="display:none;">
                                                <i class="fa fa-pencil-square-o edit" aria-hidden="true"></i>
                                                <!-- Save and Cancel buttons -->
                                                <button type="button" class="saveEdit btn btn-success btn-sm" style="display:none;">Save</button>
                                                <button type="button" class="cancelEdit btn btn-danger btn-sm" style="display:none;">Cancel</button>
                                            </div>
                                            <div class="editableField">
                                                <strong>Bedrooms: </strong>
                                                <span><?= htmlspecialchars($row['Bedrooms']) ?></span>
                                                <input type="text" class="editInput" name="Bedrooms" value="<?= htmlspecialchars($row['Bedrooms']) ?>" style="display:none;">
                                                <i class="fa fa-pencil-square-o edit" aria-hidden="true"></i>
                                                <!-- Save and Cancel buttons -->
                                                <button type="button" class="saveEdit btn btn-success btn-sm" style="display:none;">Save</button>
                                                <button type="button" class="cancelEdit btn btn-danger btn-sm" style="display:none;">Cancel</button>
                                            </div>
                                            <div class="editableField">
                                                <strong>Booking Date: </strong>
                                                <span><?= htmlspecialchars($row['BookingDate']) ?></span>
                                                <input type="text" class="editInput" name="BookingDate" value="<?= htmlspecialchars($row['BookingDate']) ?>" style="display:none;">
                                                <i class="fa fa-pencil-square-o edit" aria-hidden="true"></i>
                                                <!-- Save and Cancel buttons -->
                                                <button type="button" class="saveEdit btn btn-success btn-sm" style="display:none;">Save</button>
                                                <button type="button" class="cancelEdit btn btn-danger btn-sm" style="display:none;">Cancel</button>
                                            </div>
                                            <div class="editableField">
                                                <strong>Moving Date: </strong>
                                                <span><?= htmlspecialchars($row['MovingDate']) ?></span>
                                                <input type="date" class="editInput" name="MovingDate" value="<?= htmlspecialchars($row['MovingDate']) ?>" style="display:none;">
                                                <i class="fa fa-pencil-square-o edit" aria-hidden="true"></i>
                                                <!-- Save and Cancel buttons -->
                                                <button type="button" class="saveEdit btn btn-success btn-sm" style="display:none;">Save</button>
                                                <button type="button" class="cancelEdit btn btn-danger btn-sm" style="display:none;">Cancel</button>
                                            </div>
                                            <div class="editableField">
                                                <strong>Pickup Location: </strong>
                                                <span><?= htmlspecialchars($row['PickupLocation']) ?></span>
                                                <input type="text" class="editInput" name="PickupLocation" value="<?= htmlspecialchars($row['PickupLocation']) ?>" style="display:none;">
                                                <i class="fa fa-pencil-square-o edit" aria-hidden="true"></i>
                                                <!-- Save and Cancel buttons -->
                                                <button type="button" class="saveEdit btn btn-success btn-sm" style="display:none;">Save</button>
                                                <button type="button" class="cancelEdit btn btn-danger btn-sm" style="display:none;">Cancel</button>
                                            </div>
                                            <div class="editableField">
                                                <strong>Dropoff Location: </strong>
                                                <span><?= htmlspecialchars($row['DropoffLocation']) ?></span>
                                                <input type="text" class="editInput" name="DropoffLocation" value="<?= htmlspecialchars($row['DropoffLocation']) ?>" style="display:none;">
                                                <i class="fa fa-pencil-square-o edit" aria-hidden="true"></i>
                                                <!-- Save and Cancel buttons -->
                                                <button type="button" class="saveEdit btn btn-success btn-sm" style="display:none;">Save</button>
                                                <button type="button" class="cancelEdit btn btn-danger btn-sm" style="display:none;">Cancel</button>
                                            </div>
                                            <div class="editableField">
                                                <strong>Truck Size: </strong>
                                                <span><?= htmlspecialchars($row['TruckSize']) ?: "Please Update" ?></span>
                                                <input type="text" class="editInput" name="TruckSize" value="<?= htmlspecialchars($row['TruckSize']) ?>" style="display:none;">
                                                <i class="fa fa-pencil-square-o edit" aria-hidden="true"></i>
                                                <!-- Save and Cancel buttons -->
                                                <button type="button" class="saveEdit btn btn-success btn-sm" style="display:none;">Save</button>
                                                <button type="button" class="cancelEdit btn btn-danger btn-sm" style="display:none;">Cancel</button>
                                            </div>
                                            <div class="editableField">
                                                <strong>Callout Fee: </strong>
                                                <span><?= htmlspecialchars($row['CalloutFee']) ?: "Please Update" ?></span>
                                                <input type="text" class="editInput" name="CalloutFee" value="<?= htmlspecialchars($row['CalloutFee']) ?>" style="display:none;">
                                                <i class="fa fa-pencil-square-o edit" aria-hidden="true"></i>
                                                <!-- Save and Cancel buttons -->
                                                <button type="button" class="saveEdit btn btn-success btn-sm" style="display:none;">Save</button>
                                                <button type="button" class="cancelEdit btn btn-danger btn-sm" style="display:none;">Cancel</button>
                                            </div>
                                            <div class="editableField">
                                                <strong>Rate: </strong>
                                                <span><?= htmlspecialchars($row['Rate']) ?: "Please Update" ?></span>
                                                <input type="text" class="editInput" name="Rate" value="<?= htmlspecialchars($row['Rate']) ?>" style="display:none;">
                                                <i class="fa fa-pencil-square-o edit" aria-hidden="true"></i>
                                                <!-- Save and Cancel buttons -->
                                                <button type="button" class="saveEdit btn btn-success btn-sm" style="display:none;">Save</button>
                                                <button type="button" class="cancelEdit btn btn-danger btn-sm" style="display:none;">Cancel</button>
                                            </div>
                                            <div class="editableField">
                                                <strong>Deposit: </strong>
                                                <span><?= htmlspecialchars($row['Deposit']) ?: "Please Update" ?></span>
                                                <input type="text" class="editInput" name="Deposit" value="<?= htmlspecialchars($row['Deposit']) ?>" style="display:none;">
                                                <i class="fa fa-pencil-square-o edit" aria-hidden="true"></i>
                                                <!-- Save and Cancel buttons -->
                                                <button type="button" class="saveEdit btn btn-success btn-sm" style="display:none;">Save</button>
                                                <button type="button" class="cancelEdit btn btn-danger btn-sm" style="display:none;">Cancel</button>
                                            </div>
                                            <div class="editableField">
                                                <strong>Time Slot: </strong>
                                                <span><?= htmlspecialchars($row['TimeSlot']) ?: "Please Update" ?></span>
                                                <input type="time" class="editInput" name="TimeSlot" value="<?= htmlspecialchars($row['TimeSlot']) ?>" style="display:none;">
                                                <i class="fa fa-pencil-square-o edit" aria-hidden="true"></i>
                                                <!-- Save and Cancel buttons -->
                                                <button type="button" class="saveEdit btn btn-success btn-sm" style="display:none;">Save</button>
                                                <button type="button" class="cancelEdit btn btn-danger btn-sm" style="display:none;">Cancel</button>
                                            </div>
                                        </div>
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
        // Close the modal when clicking outside of it
        $(document).on('click', '[data-dismiss="modal"]', function(e) {
            if ($(e.target).hasClass('modal')) {
                $(e.target).modal('hide');
            }
        });
    </script>

    <script src="updateBookingModal.js"></script>


</body>

</html>