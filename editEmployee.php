<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

$phoneNo = $_GET['PhoneNo'];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and retrieve values from POST data
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $employeeType = filter_input(INPUT_POST, 'employeeType', FILTER_SANITIZE_STRING);
    $isActive = filter_input(INPUT_POST, 'isActive', FILTER_SANITIZE_NUMBER_INT);
    $payRate = filter_input(INPUT_POST, 'payRate', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    // Prepare the SQL query to update employee details
    $stmt = $conn->prepare("UPDATE Employees SET Name=?, Email=?, EmployeeType=?, isActive=?, PayRate=? WHERE PhoneNo=?");
    $stmt->bind_param("sssids", $name, $email, $employeeType, $isActive, $payRate, $phoneNo);

    // Execute the query and check if it was successful
    if ($stmt->execute()) {
        echo "Employee details have been successfully updated.";
        // Optionally, redirect to another page
        header("Location: employeeDetails.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
} else {
    // Fetch employee details
    $stmt = $conn->prepare("SELECT Name, Email, EmployeeType, isActive, PayRate FROM Employees WHERE PhoneNo=?");
    $stmt->bind_param("s", $phoneNo);
    $stmt->execute();
    $stmt->bind_result($name, $email, $employeeType, $isActive, $payRate);
    $stmt->fetch();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
</head>

<body>

    <div class="container">
        <h1 class="mt-5">Edit Employee</h1>
        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>
            <div class="form-group">
                <label for="employeeType">Employee Type</label>
                <select class="form-control" id="employeeType" name="employeeType">
                    <option value="Helper" <?= ($employeeType == 'Helper') ? 'selected' : '' ?>>Helper</option>
                    <option value="Driver" <?= ($employeeType == 'Driver') ? 'selected' : '' ?>>Driver</option>
                </select>
            </div>
            <div class="form-group">
                <label for="isActive">Active</label>
                <select class="form-control" id="isActive" name="isActive">
                    <option value="1" <?= ($isActive == 1) ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= ($isActive == 0) ? 'selected' : '' ?>>No</option>
                </select>
            </div>
            <div class="form-group">
                <label for="payRate">Pay Rate</label>
                <input type="number" step="0.01" class="form-control" id="payRate" name="payRate" value="<?= htmlspecialchars($payRate) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="employeeDetails.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>

</html>
