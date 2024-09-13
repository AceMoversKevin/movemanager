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
    GROUP_CONCAT(CONCAT(e.Name, '|', ba.isAccepted) ORDER BY e.Name SEPARATOR ',') AS EmployeeNamesStatus,
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

// Fetch data for Number of Bookings per Month
$bookingsPerMonthQuery = "
    SELECT DATE_FORMAT(BookingDate, '%Y-%m') AS Month, COUNT(*) AS BookingCount
    FROM Bookings
    GROUP BY Month
    ORDER BY Month;
";
$bookingsPerMonthResult = $conn->query($bookingsPerMonthQuery);
$bookingsPerMonthData = [];
while ($row = $bookingsPerMonthResult->fetch_assoc()) {
    $bookingsPerMonthData[] = $row;
}

// Fetch data for Bookings by Weekday
$bookingsByWeekdayQuery = "
    SELECT DAYNAME(BookingDate) AS Weekday, COUNT(*) AS BookingCount
    FROM Bookings
    GROUP BY Weekday
    ORDER BY FIELD(Weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
";
$bookingsByWeekdayResult = $conn->query($bookingsByWeekdayQuery);
$bookingsByWeekdayData = [];
while ($row = $bookingsByWeekdayResult->fetch_assoc()) {
    $bookingsByWeekdayData[] = $row;
}

// Fetch data for Bookings by Number of Bedrooms
$bookingsByBedroomsQuery = "
    SELECT Bedrooms, COUNT(*) AS BookingCount
    FROM Bookings
    GROUP BY Bedrooms;
";
$bookingsByBedroomsResult = $conn->query($bookingsByBedroomsQuery);
$bookingsByBedroomsData = [];
while ($row = $bookingsByBedroomsResult->fetch_assoc()) {
    $bookingsByBedroomsData[] = $row;
}

// Fetch data for Bookings by Truck Size
$bookingsByTruckSizeQuery = "
    SELECT TruckSize, COUNT(*) AS BookingCount
    FROM Bookings
    GROUP BY TruckSize;
";
$bookingsByTruckSizeResult = $conn->query($bookingsByTruckSizeQuery);
$bookingsByTruckSizeData = [];
while ($row = $bookingsByTruckSizeResult->fetch_assoc()) {
    $bookingsByTruckSizeData[] = $row;
}

// Fetch data for Bookings by Time Slot
$bookingsByTimeSlotQuery = "
    SELECT TimeSlot, COUNT(*) AS BookingCount
    FROM Bookings
    GROUP BY TimeSlot;
";
$bookingsByTimeSlotResult = $conn->query($bookingsByTimeSlotQuery);
$bookingsByTimeSlotData = [];
while ($row = $bookingsByTimeSlotResult->fetch_assoc()) {
    $bookingsByTimeSlotData[] = $row;
}

// Fetch data for Revenue from Bookings Over Time
$revenueOverTimeQuery = "
    SELECT DATE_FORMAT(BookingDate, '%Y-%m') AS Month, SUM(CalloutFee + Rate + StairCharges + PianoCharge + PoolTableCharge) AS TotalRevenue
    FROM Bookings
    GROUP BY Month
    ORDER BY Month;
";
$revenueOverTimeResult = $conn->query($revenueOverTimeQuery);
$revenueOverTimeData = [];
while ($row = $revenueOverTimeResult->fetch_assoc()) {
    $revenueOverTimeData[] = $row;
}

// Fetch data for Average Callout Fee and Rate per Booking
$averageFeeRateQuery = "
    SELECT AVG(CalloutFee) AS AvgCalloutFee, AVG(Rate) AS AvgRate
    FROM Bookings;
";
$averageFeeRateResult = $conn->query($averageFeeRateQuery);
$averageFeeRateData = $averageFeeRateResult->fetch_assoc();

// Fetch data for Instances of Additional Charges (Stair, Piano, Pool Table)
$additionalChargesQuery = "
    SELECT 
        COUNT(CASE WHEN StairCharges > 0 THEN 1 END) AS StairChargeInstances,
        COUNT(CASE WHEN PianoCharge > 0 THEN 1 END) AS PianoChargeInstances,
        COUNT(CASE WHEN PoolTableCharge > 0 THEN 1 END) AS PoolTableChargeInstances
    FROM Bookings;
";
$additionalChargesResult = $conn->query($additionalChargesQuery);
$additionalChargesData = $additionalChargesResult->fetch_assoc();

// Fetch data for Average Booking Duration
$averageBookingDurationQuery = "
    SELECT Bedrooms, AVG(DATEDIFF(MovingDate, BookingDate)) AS AvgDuration
    FROM Bookings
    GROUP BY Bedrooms;
";
$averageBookingDurationResult = $conn->query($averageBookingDurationQuery);
$averageBookingDurationData = [];
while ($row = $averageBookingDurationResult->fetch_assoc()) {
    $averageBookingDurationData[] = $row;
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
    <title>Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="keep-session-alive.js"></script>
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

    <style>
        .chart-container {
            width: 100%;
            max-width: 500px;
            height: 500px;
            margin: auto;
        }

        .toggle-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            cursor: pointer;
            z-index: 10;
            font-size: 20px;
        }
    </style>

    <style>
        .employee-container {
            display: flex;
            flex-wrap: wrap;
        }

        .employee-name-status {
            display: flex;
            align-items: center;
            margin-right: 10px;
            margin-bottom: 5px;
            /* Optional, for spacing in case of wrapping */
        }

        .status-circle {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-left: 5px;
        }

        .accepted {
            background-color: green;
        }

        .not-accepted {
            background-color: red;
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
                    <?php if ($result->num_rows > 0) : ?>
                        <?php while ($row = $result->fetch_assoc()) : ?>
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

                            $employeeNamesStatus = explode(',', $row['EmployeeNamesStatus']);
                            ?>
                            <div class="col-md-4">
                                <div class="card mb-4 shadow-sm" onclick="window.location.href='jobDetails.php?BookingID=<?= $row['BookingID'] ?>'">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($row["BookingName"]) ?> from <?= htmlspecialchars($row["PickupLocation"]) ?> to <?= htmlspecialchars($row["DropoffLocation"]) ?></h5>
                                        <div class="employee-list">
                                            <strong>Employees:</strong>
                                            <div class="employee-container">
                                                <?php foreach ($employeeNamesStatus as $employeeStatus) : ?>
                                                    <?php list($name, $isAccepted) = explode('|', $employeeStatus); ?>
                                                    <span class="employee-name-status">
                                                        <?= htmlspecialchars($name) ?>
                                                        <span class="status-circle <?= $isAccepted == 1 ? 'accepted' : 'not-accepted' ?>"></span>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="progress-circle <?= $progressClass ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="80" height="80">
                                                <defs>
                                                    <linearGradient id="GradientColor">
                                                        <stop offset="0%" stop-color="#21d4fd" />
                                                        <stop offset="100%" stop-color="#b721ff" />
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
                    <?php else : ?>
                        <p>No assigned jobs found.</p>
                    <?php endif; ?>
                </div>
-->
                <!-- Statistics Visualization Section -->
                <h2>Statistics</h2>

                <div class="row">
                    <!-- Number of Bookings per Month Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="toggleBookingsPerMonthChart" aria-hidden="true"></i>
                            <canvas id="bookingsPerMonthChart"></canvas>
                        </div>
                    </div>
                    <!-- Bookings by Weekday Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="toggleBookingsByWeekdayChart" aria-hidden="true"></i>
                            <canvas id="bookingsByWeekdayChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Bookings by Bedrooms Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="toggleBookingsByBedroomsChart" aria-hidden="true"></i>
                            <canvas id="bookingsByBedroomsChart"></canvas>
                        </div>
                    </div>
                    <!-- Bookings by Truck Size Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="toggleBookingsByTruckSizeChart" aria-hidden="true"></i>
                            <canvas id="bookingsByTruckSizeChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Bookings by Time Slot Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="toggleBookingsByTimeSlotChart" aria-hidden="true"></i>
                            <canvas id="bookingsByTimeSlotChart"></canvas>
                        </div>
                    </div>
                    <!-- Revenue from Bookings Over Time Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="toggleRevenueOverTimeChart" aria-hidden="true"></i>
                            <canvas id="revenueOverTimeChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Instances of Additional Charges Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="toggleAdditionalChargesChart" aria-hidden="true"></i>
                            <canvas id="additionalChargesChart"></canvas>
                        </div>
                    </div>
                    <!-- Average Booking Duration Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="toggleAverageBookingDurationChart" aria-hidden="true"></i>
                            <canvas id="averageBookingDurationChart"></canvas>
                        </div>
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
        // Prepare data for Number of Bookings per Month chart
        var bookingsPerMonthLabels = <?= json_encode(array_column($bookingsPerMonthData, 'Month')) ?>;
        var bookingsPerMonthData = <?= json_encode(array_column($bookingsPerMonthData, 'BookingCount')) ?>;

        // Prepare data for Bookings by Weekday chart
        var bookingsByWeekdayLabels = <?= json_encode(array_column($bookingsByWeekdayData, 'Weekday')) ?>;
        var bookingsByWeekdayData = <?= json_encode(array_column($bookingsByWeekdayData, 'BookingCount')) ?>;

        // Prepare data for Bookings by Bedrooms chart
        var bookingsByBedroomsLabels = <?= json_encode(array_column($bookingsByBedroomsData, 'Bedrooms')) ?>;
        var bookingsByBedroomsData = <?= json_encode(array_column($bookingsByBedroomsData, 'BookingCount')) ?>;

        // Prepare data for Bookings by Truck Size chart
        var bookingsByTruckSizeLabels = <?= json_encode(array_column($bookingsByTruckSizeData, 'TruckSize')) ?>;
        var bookingsByTruckSizeData = <?= json_encode(array_column($bookingsByTruckSizeData, 'BookingCount')) ?>;

        // Prepare data for Bookings by Time Slot chart
        var bookingsByTimeSlotLabels = <?= json_encode(array_column($bookingsByTimeSlotData, 'TimeSlot')) ?>;
        var bookingsByTimeSlotData = <?= json_encode(array_column($bookingsByTimeSlotData, 'BookingCount')) ?>;

        // Prepare data for Revenue from Bookings Over Time chart
        var revenueOverTimeLabels = <?= json_encode(array_column($revenueOverTimeData, 'Month')) ?>;
        var revenueOverTimeData = <?= json_encode(array_column($revenueOverTimeData, 'TotalRevenue')) ?>;

        // Prepare data for Instances of Additional Charges chart
        var additionalChargesLabels = ['Stair Charge Instances', 'Piano Charge Instances', 'Pool Table Charge Instances'];
        var additionalChargesData = [<?= $additionalChargesData['StairChargeInstances'] ?>, <?= $additionalChargesData['PianoChargeInstances'] ?>, <?= $additionalChargesData['PoolTableChargeInstances'] ?>];

        // Prepare data for Average Booking Duration chart
        var averageBookingDurationLabels = <?= json_encode(array_column($averageBookingDurationData, 'Bedrooms')) ?>;
        var averageBookingDurationData = <?= json_encode(array_column($averageBookingDurationData, 'AvgDuration')) ?>;

        // Function to create charts
        function createChart(ctx, type, labels, data, label) {
            return new Chart(ctx, {
                type: type,
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: data,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 206, 86, 0.2)',
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(153, 102, 255, 0.2)',
                            'rgba(255, 159, 64, 0.2)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: type === 'pie' || type === 'doughnut' || type === 'polarArea' ? {} : {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Initial chart type
        var chartType = 'bar';

        // Create charts
        var bookingsPerMonthChart = createChart(document.getElementById('bookingsPerMonthChart').getContext('2d'), chartType, bookingsPerMonthLabels, bookingsPerMonthData, 'Number of Bookings per Month');
        var bookingsByWeekdayChart = createChart(document.getElementById('bookingsByWeekdayChart').getContext('2d'), chartType, bookingsByWeekdayLabels, bookingsByWeekdayData, 'Bookings by Weekday');
        var bookingsByBedroomsChart = createChart(document.getElementById('bookingsByBedroomsChart').getContext('2d'), chartType, bookingsByBedroomsLabels, bookingsByBedroomsData, 'Bookings by Bedrooms');
        var bookingsByTruckSizeChart = createChart(document.getElementById('bookingsByTruckSizeChart').getContext('2d'), chartType, bookingsByTruckSizeLabels, bookingsByTruckSizeData, 'Bookings by Truck Size');
        var bookingsByTimeSlotChart = createChart(document.getElementById('bookingsByTimeSlotChart').getContext('2d'), chartType, bookingsByTimeSlotLabels, bookingsByTimeSlotData, 'Bookings by Time Slot');
        var revenueOverTimeChart = createChart(document.getElementById('revenueOverTimeChart').getContext('2d'), chartType, revenueOverTimeLabels, revenueOverTimeData, 'Revenue from Bookings Over Time');
        var additionalChargesChart = createChart(document.getElementById('additionalChargesChart').getContext('2d'), chartType, additionalChargesLabels, additionalChargesData, 'Instances of Additional Charges');
        var averageBookingDurationChart = createChart(document.getElementById('averageBookingDurationChart').getContext('2d'), chartType, averageBookingDurationLabels, averageBookingDurationData, 'Average Booking Duration');

        // Function to toggle chart type
        function toggleChart(chart, ctx, labels, data, label, newType) {
            chart.destroy();
            return createChart(ctx, newType, labels, data, label);
        }

        // Toggle chart icons
        document.getElementById('toggleBookingsPerMonthChart').addEventListener('click', function() {
            var newType = chartType === 'bar' ? 'pie' : chartType === 'pie' ? 'line' : chartType === 'line' ? 'bubble' : chartType === 'bubble' ? 'doughnut' : chartType === 'doughnut' ? 'radar' : chartType === 'radar' ? 'polarArea' : 'bar';
            bookingsPerMonthChart = toggleChart(bookingsPerMonthChart, document.getElementById('bookingsPerMonthChart').getContext('2d'), bookingsPerMonthLabels, bookingsPerMonthData, 'Number of Bookings per Month', newType);
            chartType = newType;
        });

        document.getElementById('toggleBookingsByWeekdayChart').addEventListener('click', function() {
            var newType = chartType === 'bar' ? 'pie' : chartType === 'pie' ? 'line' : chartType === 'line' ? 'bubble' : chartType === 'bubble' ? 'doughnut' : chartType === 'doughnut' ? 'radar' : chartType === 'radar' ? 'polarArea' : 'bar';
            bookingsByWeekdayChart = toggleChart(bookingsByWeekdayChart, document.getElementById('bookingsByWeekdayChart').getContext('2d'), bookingsByWeekdayLabels, bookingsByWeekdayData, 'Bookings by Weekday', newType);
            chartType = newType;
        });

        document.getElementById('toggleBookingsByBedroomsChart').addEventListener('click', function() {
            var newType = chartType === 'bar' ? 'pie' : chartType === 'pie' ? 'line' : chartType === 'line' ? 'bubble' : chartType === 'bubble' ? 'doughnut' : chartType === 'doughnut' ? 'radar' : chartType === 'radar' ? 'polarArea' : 'bar';
            bookingsByBedroomsChart = toggleChart(bookingsByBedroomsChart, document.getElementById('bookingsByBedroomsChart').getContext('2d'), bookingsByBedroomsLabels, bookingsByBedroomsData, 'Bookings by Bedrooms', newType);
            chartType = newType;
        });

        document.getElementById('toggleBookingsByTruckSizeChart').addEventListener('click', function() {
            var newType = chartType === 'bar' ? 'pie' : chartType === 'pie' ? 'line' : chartType === 'line' ? 'bubble' : chartType === 'bubble' ? 'doughnut' : chartType === 'doughnut' ? 'radar' : chartType === 'radar' ? 'polarArea' : 'bar';
            bookingsByTruckSizeChart = toggleChart(bookingsByTruckSizeChart, document.getElementById('bookingsByTruckSizeChart').getContext('2d'), bookingsByTruckSizeLabels, bookingsByTruckSizeData, 'Bookings by Truck Size', newType);
            chartType = newType;
        });

        document.getElementById('toggleBookingsByTimeSlotChart').addEventListener('click', function() {
            var newType = chartType === 'bar' ? 'pie' : chartType === 'pie' ? 'line' : chartType === 'line' ? 'bubble' : chartType === 'bubble' ? 'doughnut' : chartType === 'doughnut' ? 'radar' : chartType === 'radar' ? 'polarArea' : 'bar';
            bookingsByTimeSlotChart = toggleChart(bookingsByTimeSlotChart, document.getElementById('bookingsByTimeSlotChart').getContext('2d'), bookingsByTimeSlotLabels, bookingsByTimeSlotData, 'Bookings by Time Slot', newType);
            chartType = newType;
        });

        document.getElementById('toggleRevenueOverTimeChart').addEventListener('click', function() {
            var newType = chartType === 'bar' ? 'pie' : chartType === 'pie' ? 'line' : chartType === 'line' ? 'bubble' : chartType === 'bubble' ? 'doughnut' : chartType === 'doughnut' ? 'radar' : chartType === 'radar' ? 'polarArea' : 'bar';
            revenueOverTimeChart = toggleChart(revenueOverTimeChart, document.getElementById('revenueOverTimeChart').getContext('2d'), revenueOverTimeLabels, revenueOverTimeData, 'Revenue from Bookings Over Time', newType);
            chartType = newType;
        });

        document.getElementById('toggleAdditionalChargesChart').addEventListener('click', function() {
            var newType = chartType === 'bar' ? 'pie' : chartType === 'pie' ? 'line' : chartType === 'line' ? 'bubble' : chartType === 'bubble' ? 'doughnut' : chartType === 'doughnut' ? 'radar' : chartType === 'radar' ? 'polarArea' : 'bar';
            additionalChargesChart = toggleChart(additionalChargesChart, document.getElementById('additionalChargesChart').getContext('2d'), additionalChargesLabels, additionalChargesData, 'Instances of Additional Charges', newType);
            chartType = newType;
        });

        document.getElementById('toggleAverageBookingDurationChart').addEventListener('click', function() {
            var newType = chartType === 'bar' ? 'pie' : chartType === 'pie' ? 'line' : chartType === 'line' ? 'bubble' : chartType === 'bubble' ? 'doughnut' : chartType === 'doughnut' ? 'radar' : chartType === 'radar' ? 'polarArea' : 'bar';
            averageBookingDurationChart = toggleChart(averageBookingDurationChart, document.getElementById('averageBookingDurationChart').getContext('2d'), averageBookingDurationLabels, averageBookingDurationData, 'Average Booking Duration', newType);
            chartType = newType;
        });
    </script>

</html>