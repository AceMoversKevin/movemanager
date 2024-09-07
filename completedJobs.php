<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Initialize variables for filters
$searchName = '';
$searchEmail = '';
$searchDate = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!empty($_GET['searchName'])) {
        $searchName = $_GET['searchName'];
    }
    if (!empty($_GET['searchEmail'])) {
        $searchEmail = $_GET['searchEmail'];
    }
    if (!empty($_GET['searchDate'])) {
        $searchDate = $_GET['searchDate'];
    }
}

// Fetch all completed jobs with their associated booking details, applying filters if set
$query = "
    SELECT 
        b.BookingID,
        b.Name AS CustomerName,
        b.Email AS CustomerEmail,
        b.MovingDate,
        b.PickupLocation,
        b.DropoffLocation,
        b.TimeSlot,
        b.TruckSize,
        b.Rate
    FROM CompletedJobs cj
    JOIN Bookings b ON cj.BookingID = b.BookingID
    WHERE 1 = 1
";

if ($searchName) {
    $query .= " AND b.Name LIKE ?";
    $searchName = '%' . $searchName . '%';
}
if ($searchEmail) {
    $query .= " AND b.Email LIKE ?";
    $searchEmail = '%' . $searchEmail . '%';
}
if ($searchDate) {
    $query .= " AND b.MovingDate = ?";
}

$stmt = $conn->prepare($query);

if ($searchName && $searchEmail && $searchDate) {
    $stmt->bind_param("sss", $searchName, $searchEmail, $searchDate);
} elseif ($searchName && $searchEmail) {
    $stmt->bind_param("ss", $searchName, $searchEmail);
} elseif ($searchName && $searchDate) {
    $stmt->bind_param("ss", $searchName, $searchDate);
} elseif ($searchEmail && $searchDate) {
    $stmt->bind_param("ss", $searchEmail, $searchDate);
} elseif ($searchName) {
    $stmt->bind_param("s", $searchName);
} elseif ($searchEmail) {
    $stmt->bind_param("s", $searchEmail);
} elseif ($searchDate) {
    $stmt->bind_param("s", $searchDate);
}

$stmt->execute();
$result = $stmt->get_result();
$completedJobs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Job History</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="keep-session-alive.js"></script>
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
                    <h1 class="h2" id="Main-Heading">Job History</h1>
                </div>

                <!-- Search and Filter Form -->
                <form class="form-inline mb-3" method="GET" action="">
                    <div class="form-group mr-2">
                        <input type="text" class="form-control" name="searchName" placeholder="Customer Name" value="<?php echo htmlspecialchars($searchName); ?>">
                    </div>
                    <div class="form-group mr-2">
                        <input type="email" class="form-control" name="searchEmail" placeholder="Email" value="<?php echo htmlspecialchars($searchEmail); ?>">
                    </div>
                    <div class="form-group mr-2">
                        <input type="date" class="form-control" name="searchDate" placeholder="Moving Date" value="<?php echo htmlspecialchars($searchDate); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>

                <!-- List of Completed Jobs -->
                <div class="list-group">
                    <?php if (count($completedJobs) > 0): ?>
                        <?php foreach ($completedJobs as $job): ?>
                            <a href="jobHistoryDetails.php?bookingID=<?php echo urlencode($job['BookingID']); ?>" class="list-group-item list-group-item-action flex-column align-items-start">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($job['CustomerName']); ?></h5>
                                    <small><?php echo htmlspecialchars($job['TimeSlot']); ?></small>
                                </div>
                                <p class="mb-1">
                                    <strong>Email:</strong> <?php echo htmlspecialchars($job['CustomerEmail']); ?><br>
                                    <strong>Moving Date:</strong> <?php echo htmlspecialchars($job['MovingDate']); ?><br>
                                    <strong>Pickup Location:</strong> <?php echo htmlspecialchars($job['PickupLocation']); ?><br>
                                    <strong>Dropoff Location:</strong> <?php echo htmlspecialchars($job['DropoffLocation']); ?>
                                </p>
                                <small>
                                    <strong>Truck Size:</strong> <?php echo htmlspecialchars($job['TruckSize']); ?> <br>
                                    <strong>Hourly Rate:</strong> $<?php echo htmlspecialchars(number_format($job['Rate'], 2)); ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="list-group-item">
                            <p class="mb-1">No completed jobs found.</p>
                        </div>
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
