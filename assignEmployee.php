<?php
session_start();
include 'db.php'; // Include your database connection file

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$response = ['success' => false, 'message' => 'Something went wrong.'];

// Check if the form data is present and properly formatted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bookingID'], $_POST['employeePhoneNo'], $_POST['startTime'], $_POST['truckSize'], $_POST['calloutFee'], $_POST['rate'], $_POST['deposit'])) {
    $bookingID = $conn->real_escape_string($_POST['bookingID']);
    $startTime = $conn->real_escape_string($_POST['startTime']);
    $employees = $_POST['employeePhoneNo'];
    $truckSize = $conn->real_escape_string($_POST['truckSize']);
    $calloutFee = intval($_POST['calloutFee']);
    $rate = intval($_POST['rate']);
    $deposit = $conn->real_escape_string($_POST['deposit']);

    // Start a transaction
    $conn->begin_transaction();

    try {
        $allSuccess = true;

        // Insert each employee assignment into the database
        foreach ($employees as $employeePhoneNo) {
            if (!empty($employeePhoneNo)) { // Make sure the employeePhoneNo is not empty
                $employeePhoneNo = $conn->real_escape_string($employeePhoneNo);
                $sql = "INSERT INTO Bookings_Employees (BookingID, EmployeePhoneNo, TimeSlot) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $bookingID, $employeePhoneNo, $startTime);
                if (!$stmt->execute()) {
                    $allSuccess = false;
                    break; // Exit the loop if an error occurs
                }
            }
        }

        // Insert into BookingPricing if employees were successfully assigned
        if ($allSuccess) {
            $pricingSql = "INSERT INTO BookingPricing (BookingID, TruckSize, CalloutFee, Rate, Deposit) VALUES (?, ?, ?, ?, ?)";
            $pricingStmt = $conn->prepare($pricingSql);
            $pricingStmt->bind_param("isiii", $bookingID, $truckSize, $calloutFee, $rate, $deposit);
            if (!$pricingStmt->execute()) {
                $allSuccess = false;
            }
        }

        if ($allSuccess) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Employees and pricing assigned successfully.']);
        } else {
            $conn->rollback();
            $response = ['success' => false, 'message' => 'Failed to assign employees and pricing.'];
        }
    } catch (Exception $e) {
        $conn->rollback();
        $response = ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
    }

    $conn->close();
} else {
    $response = ['success' => false, 'message' => 'Invalid request data.'];
}

echo json_encode($response);
?>
