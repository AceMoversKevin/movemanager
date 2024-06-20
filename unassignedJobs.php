<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// backend logic

// Query to select bookings with less than 2 employees assigned
$query = "
    SELECT b.*, COUNT(ba.BookingID) as AssignedCount
    FROM Bookings b
    LEFT JOIN BookingAssignments ba ON b.BookingID = ba.BookingID
    GROUP BY b.BookingID
    HAVING AssignedCount < 2 AND b.isActive = 1
";

$result = $conn->query($query);

// Fetch available active Driver and Helper employees
$availableEmployeesQuery = "SELECT PhoneNo, Name, EmployeeType FROM Employees WHERE EmployeeType IN ('Driver', 'Helper') AND isActive = 1";
$availableEmployeesResult = $conn->query($availableEmployeesQuery);
$availableEmployees = $availableEmployeesResult->fetch_all(MYSQLI_ASSOC);


// Check if the query was successful
if (!$result) {
    echo "Error: " . $conn->error;
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Unassigned Jobs</title>
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
                    <h1 class="h2" id="Main-Heading">Unassigned Jobs</h1>
                </div>
                <!-- Dashboard content goes here -->
                <div class="container mt-4">
                    <div class="row">
                        <?php while ($row = $result->fetch_assoc()) :
                            // Fetch assigned employees for this booking
                            $assignedEmployeesQuery = "
    SELECT e.Name 
    FROM Employees e
    INNER JOIN BookingAssignments ba ON e.PhoneNo = ba.EmployeePhoneNo
    WHERE ba.BookingID = ?
";
                            $assignedStmt = $conn->prepare($assignedEmployeesQuery);
                            $assignedStmt->bind_param("i", $row['BookingID']);
                            $assignedStmt->execute();
                            $assignedResult = $assignedStmt->get_result();
                            $assignedEmployees = $assignedResult->fetch_all(MYSQLI_ASSOC); ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card active-booking-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($row['Name']) ?></h5>
                                        <p class="card-text"><strong>Email: </strong> <?= htmlspecialchars($row['Email']) ?></p>
                                        <p class="card-text"><strong>Phone: </strong> <?= htmlspecialchars($row['Phone']) ?></p>
                                        <p class="card-text"><strong>Bedrooms: </strong> <?= htmlspecialchars($row['Bedrooms']) ?></p>
                                        <p class="card-text"><strong>Booking Date: </strong> <?= htmlspecialchars($row['BookingDate']) ?></p>
                                        <p class="card-text"><strong>Moving Date: </strong> <?= htmlspecialchars($row['MovingDate']) ?></p>
                                        <p class="card-text"><strong>Pickup Location: </strong> <?= htmlspecialchars($row['PickupLocation']) ?></p>
                                        <p class="card-text"><strong>Dropoff Location: </strong> <?= htmlspecialchars($row['DropoffLocation']) ?></p>
                                        <!-- Display assigned employees or a message if there are none -->
                                        <?php if (count($assignedEmployees) > 0) : ?>
                                            <p class="card-text"><strong>Assigned Employees:</strong>
                                            <ul>
                                                <?php foreach ($assignedEmployees as $employee) : ?>
                                                    <li><?= htmlspecialchars($employee['Name']) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                            </p>
                                        <?php else : ?>
                                            <p class="card-text">No assigned employees</p>
                                        <?php endif; ?>
                                        <!-- Assign job button -->
                                        <button class="btn btn-outline-success btn-sm assign-job" data-toggle="modal" data-target="#assignJobsModal<?= $row['BookingID'] ?>">
                                            Assign Job
                                        </button>

                                    </div>
                                </div>
                            </div>

                            <!-- Modal for assigning Employees -->
                            <div class="modal fade" id="assignJobsModal<?= $row['BookingID'] ?>" tabindex="-1" role="dialog" aria-labelledby="modalLabel<?= $row['BookingID'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="modalLabel<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Name']) ?>'s Job</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <!-- Here, you would show the assigned employees or a message indicating that there are none -->
                                            <?php if (count($assignedEmployees) > 0) : ?>
                                                <p><strong>Assigned Employees:</strong></p>
                                                <ul>
                                                    <?php foreach ($assignedEmployees as $employee) : ?>
                                                        <li><?= htmlspecialchars($employee['Name']) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else : ?>
                                                <p>No assigned employees</p>
                                            <?php endif; ?>

                                            <div id="employeeFieldContainer<?= $row['BookingID'] ?>">

                                            </div>

                                        </div>
                                        <div class="modal-footer">
                                            <!-- Button to add a new employee assignment field -->
                                            <button type="button" class="btn btn-primary add-employee-btn" data-bookingid="<?= $row['BookingID'] ?>">Add Employee</button>
                                            <!-- Button to confirm assignments -->
                                            <button type="button" class="btn btn-success confirm-assignment-btn" data-bookingid="<?= $row['BookingID'] ?>">Confirm Assignment</button>
                                            <!-- The footer can contain buttons like 'Save Changes' or 'Close' -->
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>

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
        var availableEmployees = <?php echo json_encode($availableEmployees); ?>;
    </script>

    <script src="assignEmployeeModal.js"></script>
    <script>
        // Close the modal when clicking outside of it
        $(document).on('click', '[data-dismiss="modal"]', function(e) {
            if ($(e.target).hasClass('modal')) {
                $(e.target).modal('hide');
            }
        });
    </script>




</body>

</html>