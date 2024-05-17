<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit;
}

$query = "
SELECT 
    b.BookingID, 
    b.Name AS BookingName, 
    b.Email AS BookingEmail, 
    b.Phone AS BookingPhone, 
    b.Bedrooms, 
    b.MovingDate,
    b.PickupLocation,
    b.DropoffLocation,
    GROUP_CONCAT(e.Name ORDER BY e.Name SEPARATOR ', ') AS EmployeeNames,
    MAX(jt.StartTime) AS JobStartTime,
    MAX(jt.EndTime) AS JobEndTime,
    MAX(jt.TotalTime) AS JobTotalTime,
    MAX(jt.isComplete) AS JobIsComplete,
    MAX(jt.BreakTime) AS JobBreakTime,
    MAX(jt.isConfirmed) AS JobIsConfirmed,
    MAX(jc.TotalCharge) AS JobTotalCharge,
    MAX(jc.TotalLaborTime) AS JobTotalLaborTime,
    MAX(jc.TotalBillableTime) AS JobTotalBillableTime,
    MAX(jc.StairCharge) AS JobStairCharge,
    MAX(jc.PianoCharge) AS JobPianoCharge,
    MAX(jc.PoolTableCharge) AS JobPoolTableCharge,
    MAX(jc.Deposit) AS JobDeposit,
    MAX(jc.GST) AS JobGST
FROM 
    Bookings b
JOIN 
    BookingAssignments ba ON b.BookingID = ba.BookingID
JOIN 
    Employees e ON ba.EmployeePhoneNo = e.PhoneNo
LEFT JOIN 
    JobTimings jt ON b.BookingID = jt.BookingID
LEFT JOIN 
    JobCharges jc ON b.BookingID = jc.BookingID
WHERE 
    b.isActive = 1 AND
    b.BookingID NOT IN (SELECT BookingID FROM CompletedJobs)
GROUP BY 
    b.BookingID, 
    b.Name, 
    b.Email, 
    b.Phone, 
    b.Bedrooms, 
    b.MovingDate,
    b.PickupLocation,
    b.DropoffLocation;
";

$result = $conn->query($query);



function getStatusClass($status)
{
    switch ($status) {
        case 'not started':
            return 'status-not-started';
        case 'in progress':
            return 'status-in-progress';
        case 'completed':
            return 'status-completed';
        case 'audit':
            return 'status-audit';
        case 'payment':
            return 'status-payment';
        default:
            return '';
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $refreshTime = 100;
    echo '<meta http-equiv="refresh" content="' . $refreshTime . '">';
    ?>
    <title>Assigned Jobs</title>
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
        <style>
            .status-not-started {
                background-color: lightgray;
            }

            .status-in-progress {
                background-color: lightyellow;
            }

            .status-ended {
                background-color: lightgreen;
            }

            .status-audit {
                background-color: lightblue;
            }

            .status-payment {
                background-color: lightcoral;
            }

            .status-completed {
                background-color: lightgoldenrodyellow;
            }
        </style>

    </header>

    <div class="container-fluid">
        <div class="row">

            <?php include 'navbar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2" id="Main-Heading">Assigned Jobs</h1>
                </div>
                <!-- Dashboard content goes here -->
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Booking Name</th>
                            <th>Moving Date</th>
                            <th>Employees</th>
                            <th>Details</th>
                            <th>Action</th>
                            <th>Started</th>
                            <th>Ended</th>
                            <th>Audit</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row["BookingName"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["MovingDate"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["EmployeeNames"]) . "</td>";
                                echo "<td><a href='jobDetails.php?BookingID=" . htmlspecialchars($row["BookingID"]) . "'>View Details</a></td>";
                                echo "<td><button type='button' class='btn btn-outline-success completeJob' data-bookingid='" . htmlspecialchars($row["BookingID"]) . "'>Mark as Complete</button></td>";

                                // Determine the status for Started
                                $startedClass = 'status-not-started';
                                if ($row["JobStartTime"]) {
                                    $startedClass = 'status-in-progress';
                                }

                                // Determine the status for Ended
                                $endedClass = 'status-not-started';
                                if ($row["JobStartTime"]) {
                                    $endedClass = 'status-in-progress';
                                    if ($row["JobEndTime"]) {
                                        $startedClass = 'status-ended';
                                        $endedClass = 'status-ended';
                                    }
                                }

                                // Determine the status for Audit
                                $auditClass = 'status-not-started';
                                if ($row["JobIsComplete"]) {
                                    $auditClass = 'status-audit';
                                } else if ($row["JobStartTime"] && $row["JobEndTime"]) {
                                    $auditClass = 'status-ended';
                                }

                                // Determine the status for Payment
                                $paymentClass = 'status-not-started';
                                if ($row["JobIsConfirmed"]) {
                                    $auditClass = 'status-audit';
                                    // $paymentClass = 'status-completed';
                                } else if ($row["JobIsComplete"]) {
                                    $auditClass = 'status-ended';
                                    $paymentClass = 'status-payment';
                                }

                                echo "<td class='$startedClass'>" . ($row["JobStartTime"] ? htmlspecialchars($row["JobStartTime"]) : 'Not Started') . "</td>";
                                echo "<td class='$endedClass'>" . ($row["JobEndTime"] ? htmlspecialchars($row["JobEndTime"]) : 'Not Ended') . "</td>";
                                echo "<td class='$auditClass'>" . ($row["JobIsComplete"] ? 'Audit' : 'Audit') . "</td>";
                                echo "<td class='$paymentClass'>" . ($row["JobIsConfirmed"] ? 'Payment' : 'Payment') . "</td>";

                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='9'>No assigned jobs found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            $('.completeJob').click(function() {
                event.preventDefault(); // This prevents the default action of the button
                event.stopPropagation(); // This stops the click event from "bubbling" up to the parent elements
                var bookingId = $(this).data('bookingid'); // Get the booking ID
                $.ajax({
                    url: 'mark-completed.php', // The script to call to insert data into the CompletedJobs table
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