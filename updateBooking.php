<?php
include 'db.php'; // Include your database connection file
session_start();

// Check for admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Check for the correct POST variables
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bookingID'], $_POST['employeePhoneNo'], $_POST['startTime'], $_POST['truckSize'], $_POST['calloutFee'], $_POST['rate'], $_POST['deposit'])) {
    $bookingID = $conn->real_escape_string($_POST['bookingID']);
    $startTime = $conn->real_escape_string($_POST['startTime']);
    $employeePhoneNos = $_POST['employeePhoneNo'];
    $truckSize = $conn->real_escape_string($_POST['truckSize']);
    $calloutFee = $conn->real_escape_string($_POST['calloutFee']);
    $rate = $conn->real_escape_string($_POST['rate']);
    $deposit = $conn->real_escape_string($_POST['deposit']);

    // Begin a transaction to ensure atomicity
    $conn->begin_transaction();

    try {
        // First, remove existing assignments for this booking
        $deleteEmployeeQuery = "DELETE FROM Bookings_Employees WHERE BookingID = ?";
        $deleteEmployeeStmt = $conn->prepare($deleteEmployeeQuery);
        $deleteEmployeeStmt->bind_param("i", $bookingID);
        $deleteEmployeeStmt->execute();

        // Insert new assignments
        $insertEmployeeQuery = "INSERT INTO Bookings_Employees (BookingID, EmployeePhoneNo, TimeSlot) VALUES (?, ?, ?)";
        $insertEmployeeStmt = $conn->prepare($insertEmployeeQuery);
        foreach ($employeePhoneNos as $employeePhoneNo) {
            if (!empty($employeePhoneNo)) { // Skip any empty selections
                $insertEmployeeStmt->bind_param("iss", $bookingID, $employeePhoneNo, $startTime);
                $insertEmployeeStmt->execute();
            }
        }

        // Update or insert pricing data
        $updatePricingQuery = "INSERT INTO BookingPricing (BookingID, TruckSize, CalloutFee, Rate, Deposit) VALUES (?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE TruckSize = ?, CalloutFee = ?, Rate = ?, Deposit = ?";
        $updatePricingStmt = $conn->prepare($updatePricingQuery);
        $updatePricingStmt->bind_param("isiiisii", $bookingID, $truckSize, $calloutFee, $rate, $deposit, $truckSize, $calloutFee, $rate, $deposit);
        $updatePricingStmt->execute();

        // If all goes well, commit the transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Booking and pricing updated successfully.']);
    } catch (Exception $e) {
        // An error occurred, rollback the transaction
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error updating booking: ' . $e->getMessage()]);
    }

    // Close statements and connection
    $deleteEmployeeStmt->close();
    $insertEmployeeStmt->close();
    $updatePricingStmt->close();
    $conn->close();
} else {
    // If required POST variables are not set
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}
?>
