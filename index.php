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
    <title>Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .progress-circle {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 10px;
        }
        .progress-circle svg {
            position: absolute;
            top: 0;
            left: 0;
            transform: rotate(-90deg);
        }
        .progress-circle circle {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
        }
        .progress-circle .bg {
            stroke: #f0f0f0;
        }
        .progress-circle .progress {
            stroke: url(#GradientColor);
            stroke-dasharray: 188.4;
            stroke-dashoffset: 188.4;
            transition: stroke-dashoffset 0.5s linear;
        }
        .quarter .progress {
            stroke-dashoffset: 141.3;
        }
        .half .progress {
            stroke-dashoffset: 94.2;
        }
        .three-quarters .progress {
            stroke-dashoffset: 47.1;
        }
        .full .progress {
            stroke-dashoffset: 0;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .card-body {
            padding: 20px;
        }
        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .progress-circle {
            margin-top: 20px;
        }
        .progress-label {
            font-size: 14px;
            font-weight: 600;
            margin-top: 10px;
        }
        .employee-list {
            margin-top: 10px;
            font-size: 14px;
        }
        .employee-list span {
            display: block;
        }
    </style>
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
                    <h1 class="h2" id="Main-Heading">Welcome, <?= $_SESSION['username'] ?>!</h1>
                </div>
                <!-- Dashboard content goes here -->
                <div class="row">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php 
                            $steps = [
                                'Started' => !is_null($row["JobStartTime"]),
                                'Ended' => !is_null($row["JobEndTime"]),
                                'Audit' => !is_null($row["JobIsComplete"]) && $row["JobIsComplete"] == 1,
                                'Payment' => !is_null($row["JobIsConfirmed"]) && $row["JobIsConfirmed"] == 1
                            ];
                            
                            $progressClass = '';
                            if ($steps['Payment']) {
                                $progressClass = 'full';
                            } elseif ($steps['Audit']) {
                                $progressClass = 'three-quarters';
                            } elseif ($steps['Ended']) {
                                $progressClass = 'half';
                            } elseif ($steps['Started']) {
                                $progressClass = 'quarter';
                            }
                            
                            // Split employee names into an array
                            $employeeNames = explode(', ', $row['EmployeeNames']);
                            ?>
                            <div class="col-md-4">
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($row["BookingName"]) ?> from <?= htmlspecialchars($row["PickupLocation"]) ?> to <?= htmlspecialchars($row["DropoffLocation"]) ?></h5>
                                        <div class="employee-list">
                                            <strong>Employees:</strong>
                                            <?php foreach ($employeeNames as $name): ?>
                                                <span><?= htmlspecialchars($name) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="progress-circle <?= $progressClass ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="80" height="80">
                                                <defs>
                                                    <linearGradient id="GradientColor">
                                                        <stop offset="0%" stop-color="#21d4fd"/>
                                                        <stop offset="100%" stop-color="#b721ff"/>
                                                    </linearGradient>
                                                </defs>
                                                <circle class="bg" cx="40" cy="40" r="30" />
                                                <circle class="progress" cx="40" cy="40" r="30" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No assigned jobs found.</p>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>

</html>