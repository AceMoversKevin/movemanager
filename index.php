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

// Fetch data for bookings by bedrooms
$bedroomsQuery = "SELECT Bedrooms, COUNT(*) AS BookingCount FROM Bookings GROUP BY Bedrooms";
$bedroomsResult = $conn->query($bedroomsQuery);
$bedroomsData = [];
while ($row = $bedroomsResult->fetch_assoc()) {
    $bedroomsData[] = $row;
}

// Fetch data for bookings by truck size
$truckSizeQuery = "SELECT TruckSize, COUNT(*) AS BookingCount FROM Bookings GROUP BY TruckSize";
$truckSizeResult = $conn->query($truckSizeQuery);
$truckSizeData = [];
while ($row = $truckSizeResult->fetch_assoc()) {
    $truckSizeData[] = $row;
}

// Fetch data for bedrooms by truck size
$bedroomsTruckSizeQuery = "SELECT TruckSize, Bedrooms, COUNT(*) AS BookingCount FROM Bookings GROUP BY TruckSize, Bedrooms";
$bedroomsTruckSizeResult = $conn->query($bedroomsTruckSizeQuery);
$bedroomsTruckSizeData = [];
while ($row = $bedroomsTruckSizeResult->fetch_assoc()) {
    $bedroomsTruckSizeData[] = $row;
}

// Fetch data for bookings by callout fee
$calloutFeeQuery = "SELECT CalloutFee, COUNT(*) AS BookingCount FROM Bookings GROUP BY CalloutFee";
$calloutFeeResult = $conn->query($calloutFeeQuery);
$calloutFeeData = [];
while ($row = $calloutFeeResult->fetch_assoc()) {
    $calloutFeeData[] = $row;
}

// Fetch data for bookings by stair charges
$stairChargesQuery = "SELECT StairCharges, COUNT(*) AS BookingCount FROM Bookings WHERE StairCharges IS NOT NULL GROUP BY StairCharges";
$stairChargesResult = $conn->query($stairChargesQuery);
$stairChargesData = [];
while ($row = $stairChargesResult->fetch_assoc()) {
    $stairChargesData[] = $row;
}

// Fetch data for bookings by piano charges
$pianoChargesQuery = "SELECT PianoCharge, COUNT(*) AS BookingCount FROM Bookings WHERE PianoCharge IS NOT NULL GROUP BY PianoCharge";
$pianoChargesResult = $conn->query($pianoChargesQuery);
$pianoChargesData = [];
while ($row = $pianoChargesResult->fetch_assoc()) {
    $pianoChargesData[] = $row;
}

// Fetch data for bookings by pool table charges
$poolTableChargesQuery = "SELECT PoolTableCharge, COUNT(*) AS BookingCount FROM Bookings WHERE PoolTableCharge IS NOT NULL GROUP BY PoolTableCharge";
$poolTableChargesResult = $conn->query($poolTableChargesQuery);
$poolTableChargesData = [];
while ($row = $poolTableChargesResult->fetch_assoc()) {
    $poolTableChargesData[] = $row;
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
    <title>Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

                            // Split employee names into an array
                            $employeeNames = explode(', ', $row['EmployeeNames']);
                            ?>
                            <div class="col-md-4">
                                <div class="card mb-4 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($row["BookingName"]) ?> from <?= htmlspecialchars($row["PickupLocation"]) ?> to <?= htmlspecialchars($row["DropoffLocation"]) ?></h5>
                                        <div class="employee-list">
                                            <strong>Employees:</strong>
                                            <?php foreach ($employeeNames as $name) : ?>
                                                <span><?= htmlspecialchars($name) ?></span>
                                            <?php endforeach; ?>
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

                <div class="calendar">
                    <iframe src="https://calendar.google.com/calendar/embed?height=800&wkst=1&ctz=Australia%2FSydney&bgcolor=%23ffffff&src=a2V2aW5AYWNlbW92ZXJzLmNvbS5hdQ&src=cTloam5oNmlxMzZlcmQxMmw0NG5lMG1lN2NAZ3JvdXAuY2FsZW5kYXIuZ29vZ2xlLmNvbQ&src=Y19iMjhkYWY5ZDU5MDM4NWNhZDUxYmZhMGRiOWQ0YWY1YmFkNjBmNDM2MzcxZmU5MTc3ZDgwM2ViYjQ5YmRhZjBkQGdyb3VwLmNhbGVuZGFyLmdvb2dsZS5jb20&src=dGQ5dnZmZWNwM29uOTRxOTE1bGx2bjFrdGNAZ3JvdXAuY2FsZW5kYXIuZ29vZ2xlLmNvbQ&src=bmlja0BhY2Vtb3ZlcnMuY29tLmF1&color=%23039BE5&color=%238E24AA&color=%23B39DDB&color=%23E67C73&color=%234285F4" style="border-width:0" width="1400" height="700" frameborder="0" scrolling="no"></iframe>
                </div>

                <!-- Statistics Visualization Section -->
                <h2>Statistics</h2>

                <div class="row">
                    <!-- Bookings by Bedrooms Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="toggleBedroomsChart" aria-hidden="true"></i>
                            <canvas id="bookingsByBedroomsChart"></canvas>
                        </div>
                    </div>
                    <!-- Bookings by Truck Size Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="toggleTruckSizeChart" aria-hidden="true"></i>
                            <canvas id="bookingsByTruckSizeChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Bedrooms by Truck Size Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="toggleBedroomsTruckSizeChart" aria-hidden="true"></i>
                            <canvas id="bedroomsByTruckSizeChart"></canvas>
                        </div>
                    </div>
                    <!-- Bookings by Callout Fee Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="toggleCalloutFeeChart" aria-hidden="true"></i>
                            <canvas id="bookingsByCalloutFeeChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Bookings by Stair Charges Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="toggleStairChargesChart" aria-hidden="true"></i>
                            <canvas id="bookingsByStairChargesChart"></canvas>
                        </div>
                    </div>
                    <!-- Bookings by Piano Charges Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="togglePianoChargesChart" aria-hidden="true"></i>
                            <canvas id="bookingsByPianoChargesChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Bookings by Pool Table Charges Chart -->
                    <div class="col-md-6">
                        <div class="chart-container">
                            <i class="fa fa-adjust toggle-icon" id="togglePoolTableChargesChart" aria-hidden="true"></i>
                            <canvas id="bookingsByPoolTableChargesChart"></canvas>
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
        // Prepare data for Bookings by Bedrooms chart
        var bedroomsLabels = <?= json_encode(array_column($bedroomsData, 'Bedrooms')) ?>;
        var bedroomsData = <?= json_encode(array_column($bedroomsData, 'BookingCount')) ?>;

        // Prepare data for Bookings by Truck Size chart
        var truckSizeLabels = <?= json_encode(array_column($truckSizeData, 'TruckSize')) ?>;
        var truckSizeData = <?= json_encode(array_column($truckSizeData, 'BookingCount')) ?>;

        // Prepare data for Bedrooms by Truck Size chart
        var bedroomsTruckSizeLabels = <?= json_encode(array_column($bedroomsTruckSizeData, 'TruckSize')) ?>;
        var bedroomsTruckSizeData = <?= json_encode(array_column($bedroomsTruckSizeData, 'BookingCount')) ?>;

        // Prepare data for Bookings by Callout Fee chart
        var calloutFeeLabels = <?= json_encode(array_column($calloutFeeData, 'CalloutFee')) ?>;
        var calloutFeeData = <?= json_encode(array_column($calloutFeeData, 'BookingCount')) ?>;

        // Prepare data for Bookings by Stair Charges chart
        var stairChargesLabels = <?= json_encode(array_column($stairChargesData, 'StairCharges')) ?>;
        var stairChargesData = <?= json_encode(array_column($stairChargesData, 'BookingCount')) ?>;

        // Prepare data for Bookings by Piano Charges chart
        var pianoChargesLabels = <?= json_encode(array_column($pianoChargesData, 'PianoCharge')) ?>;
        var pianoChargesData = <?= json_encode(array_column($pianoChargesData, 'BookingCount')) ?>;

        // Prepare data for Bookings by Pool Table Charges chart
        var poolTableChargesLabels = <?= json_encode(array_column($poolTableChargesData, 'PoolTableCharge')) ?>;
        var poolTableChargesData = <?= json_encode(array_column($poolTableChargesData, 'BookingCount')) ?>;

        // Function to create charts
        function createChart(ctx, type, labels, data, label) {
            return new Chart(ctx, {
                type: type,
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: data,
                        backgroundColor: type === 'pie' ? [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 206, 86, 0.2)',
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(153, 102, 255, 0.2)',
                            'rgba(255, 159, 64, 0.2)'
                        ] : 'rgba(75, 192, 192, 0.2)',
                        borderColor: type === 'pie' ? [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64, 1)'
                        ] : 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: type === 'pie' ? {} : {
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
        var bedroomsChart = createChart(document.getElementById('bookingsByBedroomsChart').getContext('2d'), chartType, bedroomsLabels, bedroomsData, 'Bookings by Bedrooms');
        var truckSizeChart = createChart(document.getElementById('bookingsByTruckSizeChart').getContext('2d'), chartType, truckSizeLabels, truckSizeData, 'Bookings by Truck Size');
        var bedroomsTruckSizeChart = createChart(document.getElementById('bedroomsByTruckSizeChart').getContext('2d'), chartType, bedroomsTruckSizeLabels, bedroomsTruckSizeData, 'Bedrooms by Truck Size');
        var calloutFeeChart = createChart(document.getElementById('bookingsByCalloutFeeChart').getContext('2d'), chartType, calloutFeeLabels, calloutFeeData, 'Bookings by Callout Fee');
        var stairChargesChart = createChart(document.getElementById('bookingsByStairChargesChart').getContext('2d'), chartType, stairChargesLabels, stairChargesData, 'Bookings by Stair Charges');
        var pianoChargesChart = createChart(document.getElementById('bookingsByPianoChargesChart').getContext('2d'), chartType, pianoChargesLabels, pianoChargesData, 'Bookings by Piano Charges');
        var poolTableChargesChart = createChart(document.getElementById('bookingsByPoolTableChargesChart').getContext('2d'), chartType, poolTableChargesLabels, poolTableChargesData, 'Bookings by Pool Table Charges');

        // Function to toggle chart type
        function toggleChart(chart, ctx, labels, data, label) {
            var newType = chart.config.type === 'bar' ? 'pie' : 'bar';
            chart.destroy();
            return createChart(ctx, newType, labels, data, label);
        }

        // Toggle chart buttons
        document.getElementById('toggleBedroomsChart').addEventListener('click', function() {
            bedroomsChart = toggleChart(bedroomsChart, document.getElementById('bookingsByBedroomsChart').getContext('2d'), bedroomsLabels, bedroomsData, 'Bookings by Bedrooms');
        });

        document.getElementById('toggleTruckSizeChart').addEventListener('click', function() {
            truckSizeChart = toggleChart(truckSizeChart, document.getElementById('bookingsByTruckSizeChart').getContext('2d'), truckSizeLabels, truckSizeData, 'Bookings by Truck Size');
        });

        document.getElementById('toggleBedroomsTruckSizeChart').addEventListener('click', function() {
            bedroomsTruckSizeChart = toggleChart(bedroomsTruckSizeChart, document.getElementById('bedroomsByTruckSizeChart').getContext('2d'), bedroomsTruckSizeLabels, bedroomsTruckSizeData, 'Bedrooms by Truck Size');
        });

        document.getElementById('toggleCalloutFeeChart').addEventListener('click', function() {
            calloutFeeChart = toggleChart(calloutFeeChart, document.getElementById('bookingsByCalloutFeeChart').getContext('2d'), calloutFeeLabels, calloutFeeData, 'Bookings by Callout Fee');
        });

        document.getElementById('toggleStairChargesChart').addEventListener('click', function() {
            stairChargesChart = toggleChart(stairChargesChart, document.getElementById('bookingsByStairChargesChart').getContext('2d'), stairChargesLabels, stairChargesData, 'Bookings by Stair Charges');
        });

        document.getElementById('togglePianoChargesChart').addEventListener('click', function() {
            pianoChargesChart = toggleChart(pianoChargesChart, document.getElementById('bookingsByPianoChargesChart').getContext('2d'), pianoChargesLabels, pianoChargesData, 'Bookings by Piano Charges');
        });

        document.getElementById('togglePoolTableChargesChart').addEventListener('click', function() {
            poolTableChargesChart = toggleChart(poolTableChargesChart, document.getElementById('bookingsByPoolTableChargesChart').getContext('2d'), poolTableChargesLabels, poolTableChargesData, 'Bookings by Pool Table Charges');
        });
    </script>

</body>

</html>