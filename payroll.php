<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Fetch all unique pay periods
$payPeriodQuery = "SELECT DISTINCT WeekStartDate FROM WorkHours ORDER BY WeekStartDate DESC";
$payPeriodResult = $conn->query($payPeriodQuery);
$payPeriodsList = [];
if ($payPeriodResult->num_rows > 0) {
    while ($row = $payPeriodResult->fetch_assoc()) {
        $payPeriodsList[] = $row['WeekStartDate'];
    }
}

// Get the selected pay period from the request or default to the latest
$selectedPayPeriod = isset($_GET['payPeriod']) ? $_GET['payPeriod'] : $payPeriodsList[0];

// Fetch work hours data for the selected pay period
$query = "
SELECT 
    e.Name AS EmployeeName,
    e.PhoneNo,
    e.PayRate,
    e.ABN,
    e.GST,
    w.WeekStartDate,
    SUM(w.HoursWorked) AS TotalHoursWorked,
    SUM(w.HoursWorked) * e.PayRate AS TotalPay
FROM WorkHours w
JOIN Employees e ON w.EmployeePhoneNo = e.PhoneNo
WHERE e.EmployeeType != 'Admin' AND e.EmployeeType != 'SuperAdmin' AND w.WeekStartDate = ?
GROUP BY e.PhoneNo, w.WeekStartDate
ORDER BY e.Name;
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $selectedPayPeriod);
$stmt->execute();
$result = $stmt->get_result();

// Prepare data for displaying in the table
$employees = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}
$stmt->close();

// Function to format date
function formatDate($date)
{
    return date("d M Y", strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Pay Roll</title>
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
                    <h1 class="h2" id="Main-Heading">Pay Roll</h1>
                </div>
                <!-- Filter by Pay Period -->
                <form method="GET" action="" class="mb-3">
                    <div class="form-group">
                        <label for="payPeriod">Select Pay Period:</label>
                        <select name="payPeriod" id="payPeriod" class="form-control" onchange="this.form.submit()">
                            <?php foreach ($payPeriodsList as $payPeriod) : ?>
                                <option value="<?= htmlspecialchars($payPeriod) ?>" <?= $selectedPayPeriod == $payPeriod ? 'selected' : '' ?>>
                                    <?= formatDate($payPeriod) ?> - <?= formatDate(date('Y-m-d', strtotime($payPeriod . ' +6 days'))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <!-- Display Payroll Data -->
                <?php if (!empty($employees)) : ?>
                    <h3>Pay Period: <?= formatDate($selectedPayPeriod) ?> - <?= formatDate(date('Y-m-d', strtotime($selectedPayPeriod . ' +6 days'))) ?></h3>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Phone No</th>
                                <th>Pay Rate</th>
                                <th>ABN</th>
                                <th>GST</th>
                                <th>Total Hours Worked</th>
                                <th>Total Pay</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee) : ?>
                                <tr>
                                    <td><?= htmlspecialchars($employee['EmployeeName']) ?></td>
                                    <td><?= htmlspecialchars($employee['PhoneNo']) ?></td>
                                    <td>$<?= htmlspecialchars($employee['PayRate']) ?>/h</td>
                                    <td><?= htmlspecialchars($employee['ABN']) ?></td>
                                    <td><?= htmlspecialchars($employee['GST'] == 1 ? 'Yes' : 'No') ?></td>
                                    <td><?= htmlspecialchars($employee['TotalHoursWorked']) ?> hours</td>
                                    <td>$<?= htmlspecialchars($employee['TotalPay']) ?></td>
                                    <td><a href="editWorkHours.php?PhoneNo=<?= htmlspecialchars($employee['PhoneNo']) ?>&WeekStartDate=<?= htmlspecialchars($selectedPayPeriod) ?>" class="btn btn-warning btn-sm">Edit</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>No records found for the selected pay period.</p>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>

</html>