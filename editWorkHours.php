<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

$phoneNo = $_GET['PhoneNo'];
$weekStartDate = $_GET['WeekStartDate'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and retrieve values from POST data
    $hoursWorked = filter_input(INPUT_POST, 'hoursWorked', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $payRate = filter_input(INPUT_POST, 'payRate', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $abn = filter_input(INPUT_POST, 'abn', FILTER_SANITIZE_STRING);
    $gst = filter_input(INPUT_POST, 'gst', FILTER_SANITIZE_NUMBER_INT);

    // Prepare the SQL query to update work hours
    $stmt = $conn->prepare("UPDATE WorkHours SET HoursWorked=? WHERE EmployeePhoneNo=? AND WeekStartDate=?");
    $stmt->bind_param("dss", $hoursWorked, $phoneNo, $weekStartDate);

    // Execute the query and check if it was successful
    if ($stmt->execute()) {
        // Update the employee details in the Employees table
        $employeeStmt = $conn->prepare("UPDATE Employees SET PayRate=?, ABN=?, GST=? WHERE PhoneNo=?");
        $employeeStmt->bind_param("dsis", $payRate, $abn, $gst, $phoneNo);
        if ($employeeStmt->execute()) {
            echo "Work hours, pay rate, ABN, and GST have been successfully updated.";
            // Optionally, redirect to another page
            header("Location: payroll.php");
            exit();
        } else {
            echo "Error updating employee details: " . $employeeStmt->error;
        }
        $employeeStmt->close();
    } else {
        echo "Error updating work hours: " . $stmt->error;
    }

    $stmt->close();
} else {
    // Fetch current work hours and employee details
    $stmt = $conn->prepare("SELECT w.HoursWorked, e.PayRate, e.ABN, e.GST FROM WorkHours w JOIN Employees e ON w.EmployeePhoneNo = e.PhoneNo WHERE w.EmployeePhoneNo=? AND w.WeekStartDate=?");
    $stmt->bind_param("ss", $phoneNo, $weekStartDate);
    $stmt->execute();
    $stmt->bind_result($hoursWorked, $payRate, $abn, $gst);
    $stmt->fetch();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Work Hours, Pay Rate, ABN, and GST</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
</head>

<body>

    <div class="container">
        <h1 class="mt-5">Edit Work Hours, Pay Rate, ABN, and GST</h1>
        <form method="POST" action="">
            <div class="form-group">
                <label for="hoursWorked">Hours Worked</label>
                <input type="number" step="0.01" class="form-control" id="hoursWorked" name="hoursWorked" value="<?= htmlspecialchars($hoursWorked) ?>" required>
            </div>
            <div class="form-group">
                <label for="payRate">Pay Rate</label>
                <input type="number" step="0.01" class="form-control" id="payRate" name="payRate" value="<?= htmlspecialchars($payRate) ?>" required>
            </div>
            <div class="form-group">
                <label for="abn">ABN</label>
                <input type="text" class="form-control" id="abn" name="abn" value="<?= htmlspecialchars($abn) ?>" required>
            </div>
            <div class="form-group">
                <label for="gst">GST</label>
                <select class="form-control" id="gst" name="gst" required>
                    <option value="0" <?= $gst == 0 ? 'selected' : '' ?>>No</option>
                    <option value="1" <?= $gst == 1 ? 'selected' : '' ?>>Yes</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="payroll.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>

</html>