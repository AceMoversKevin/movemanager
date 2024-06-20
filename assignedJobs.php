<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
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
    <meta name="robots" content="noindex, nofollow">
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
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: "Poppins", sans-serif;
            }

            .step-wizard {
                width: 100%;
                display: flex;
                justify-content: center;
                align-items: center;
                margin-top: 20px;
            }

            .step-wizard-list {
                background: #fff;
                box-shadow: 0 15px 25px rgba(0, 0, 0, 0.1);
                color: #333;
                list-style-type: none;
                border-radius: 10px;
                display: flex;
                padding: 20px 10px;
                position: relative;
                z-index: 10;
                width: 100%;
                max-width: 1000px;
            }

            .step-wizard-item {
                padding: 0 20px;
                flex-basis: 0;
                flex-grow: 1;
                max-width: 100%;
                display: flex;
                flex-direction: column;
                text-align: center;
                min-width: 170px;
                position: relative;
            }

            .step-wizard-item+.step-wizard-item:after {
                content: "";
                position: absolute;
                left: 0;
                top: 19px;
                background: #21d4fd;
                width: 100%;
                height: 2px;
                transform: translateX(-50%);
                z-index: -10;
            }

            .progress-count {
                height: 40px;
                width: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                font-weight: 600;
                margin: 0 auto;
                position: relative;
                z-index: 10;
                color: transparent;
            }

            .progress-count:after {
                content: "";
                height: 40px;
                width: 40px;
                background: #21d4fd;
                position: absolute;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%);
                border-radius: 50%;
                z-index: -10;
            }

            /* .progress-count:before {
                content: "";
                height: 10px;
                width: 20px;
                border-left: 3px solid #fff;
                border-bottom: 3px solid #fff;
                position: absolute;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -60%) rotate(-45deg);
                transform-origin: center center;
            } */

            .progress-label {
                font-size: 14px;
                font-weight: 600;
                margin-top: 10px;
            }

            .current-item .progress-count:before,
            .current-item~.step-wizard-item .progress-count:before {
                display: none;
            }

            .current-item~.step-wizard-item .progress-count:after {
                height: 10px;
                width: 10px;
            }

            .current-item~.step-wizard-item .progress-label {
                opacity: 0.5;
            }

            .current-item .progress-count:after {
                background: #fff;
                border: 2px solid #21d4fd;
            }

            .current-item .progress-count {
                color: #21d4fd;
            }

            .completed-item .progress-count:after,
            .completed-item .progress-count {
                background: #21d4fd;
                color: #fff;
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
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Booking Name</th>
                            <th>Moving Date</th>
                            <th>Employees</th>
                            <th>Details</th>
                            <th>Action</th>
                            <th>Progress</th>
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
                                echo "<td>";
                                echo "<section class='step-wizard'>";
                                echo "<ul class='step-wizard-list'>";

                                // Determine the status for each step
                                $steps = [
                                    'Started' => !is_null($row["JobStartTime"]),
                                    'Ended' => !is_null($row["JobEndTime"]),
                                    'Audit' => !is_null($row["JobIsComplete"]) && $row["JobIsComplete"] == 1,
                                    'Payment' => !is_null($row["JobIsConfirmed"]) && $row["JobIsConfirmed"] == 1
                                ];

                                $stepIndex = 1;
                                $currentClass = 'current-item';
                                $completedClass = 'completed-item';
                                foreach ($steps as $step => $status) {
                                    $class = '';
                                    if ($status) {
                                        $class = $completedClass;
                                    } else {
                                        $class = $currentClass;
                                        $currentClass = '';
                                    }
                                    echo "<li class='step-wizard-item " . $class . "'>";
                                    echo "<span class='progress-count'>" . $stepIndex . "</span>";
                                    echo "<span class='progress-label'>" . $step . "</span>";
                                    echo "</li>";
                                    $stepIndex++;
                                }

                                echo "</ul>";
                                echo "</section>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6'>No assigned jobs found.</td></tr>";
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