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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['updateWorkHours'])) {
    // Sanitize and retrieve values from POST data
    $workHoursID = filter_input(INPUT_POST, 'workHoursID', FILTER_SANITIZE_NUMBER_INT);
    $hoursWorked = filter_input(INPUT_POST, 'hoursWorked', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $payRate = filter_input(INPUT_POST, 'payRate', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $abn = filter_input(INPUT_POST, 'abn', FILTER_SANITIZE_STRING);
    $gst = filter_input(INPUT_POST, 'gst', FILTER_SANITIZE_NUMBER_INT);

    // Prepare the SQL query to update work hours
    $stmt = $conn->prepare("UPDATE WorkHours SET HoursWorked=? WHERE WorkHoursID=?");
    $stmt->bind_param("di", $hoursWorked, $workHoursID);

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
    // Fetch work hours and employee details
    $stmt = $conn->prepare("SELECT w.WorkHoursID, w.WeekStartDate, w.HoursWorked, w.BookingID, e.PayRate, e.ABN, e.GST, b.Name AS BookingName, b.PickupLocation, b.DropoffLocation FROM WorkHours w JOIN Employees e ON w.EmployeePhoneNo = e.PhoneNo JOIN Bookings b ON w.BookingID = b.BookingID WHERE w.EmployeePhoneNo=?");
    $stmt->bind_param("s", $phoneNo);
    $stmt->execute();
    $result = $stmt->get_result();
    $workHours = [];
    while ($row = $result->fetch_assoc()) {
        $workHours[] = $row;
    }
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
        <?php if (count($workHours) > 0) : ?>
            <form method="POST" action="">
                <?php foreach ($workHours as $work) : ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            Job Details: <?= htmlspecialchars($work['BookingName']) ?> (<?= htmlspecialchars($work['PickupLocation']) ?> to <?= htmlspecialchars($work['DropoffLocation']) ?>)
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="hoursWorked<?= $work['WorkHoursID'] ?>">Hours Worked</label>
                                <input type="number" step="0.01" class="form-control" id="hoursWorked<?= $work['WorkHoursID'] ?>" name="hoursWorked" value="<?= htmlspecialchars($work['HoursWorked']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="payRate<?= $work['WorkHoursID'] ?>">Pay Rate</label>
                                <input type="number" step="0.01" class="form-control" id="payRate<?= $work['WorkHoursID'] ?>" name="payRate" value="<?= htmlspecialchars($work['PayRate']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="abn<?= $work['WorkHoursID'] ?>">ABN</label>
                                <input type="text" class="form-control" id="abn<?= $work['WorkHoursID'] ?>" name="abn" value="<?= htmlspecialchars($work['ABN']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="gst<?= $work['WorkHoursID'] ?>">GST</label>
                                <select class="form-control" id="gst<?= $work['WorkHoursID'] ?>" name="gst" required>
                                    <option value="0" <?= $work['GST'] == 0 ? 'selected' : '' ?>>No</option>
                                    <option value="1" <?= $work['GST'] == 1 ? 'selected' : '' ?>>Yes</option>
                                </select>
                            </div>
                            <input type="hidden" name="workHoursID" value="<?= $work['WorkHoursID'] ?>">
                            <button type="submit" name="updateWorkHours" class="btn btn-primary">Save Changes</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <a href="payroll.php" class="btn btn-secondary">Cancel</a>
            </form>
        <?php else : ?>
            <p>No work hours found for this employee.</p>
            <a href="payroll.php" class="btn btn-secondary">Back to Payroll</a>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>

</html>