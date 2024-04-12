<?php
include 'db.php'; // Include your database connection file
session_start();

// Check for admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Check for the correct POST variables
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bookingID'], $_POST['employeePhoneNo'], $_POST['startTime'])) {
    $bookingID = $conn->real_escape_string($_POST['bookingID']);
    $startTime = $conn->real_escape_string($_POST['startTime']);
    $employeePhoneNos = $_POST['employeePhoneNo'];

    // Begin a transaction to ensure atomicity
    $conn->begin_transaction();

    try {
        // First, remove existing assignments for this booking
        $deleteQuery = "DELETE FROM Bookings_Employees WHERE BookingID = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("i", $bookingID);
        $deleteStmt->execute();

        // Insert new assignments
        $insertQuery = "INSERT INTO Bookings_Employees (BookingID, EmployeePhoneNo, TimeSlot) VALUES (?, ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        foreach ($employeePhoneNos as $employeePhoneNo) {
            if (!empty($employeePhoneNo)) { // Skip any empty selections
                $insertStmt->bind_param("iss", $bookingID, $employeePhoneNo, $startTime);
                $insertStmt->execute();
            }
        }

        // If all goes well, commit the transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Booking updated successfully.']);
    } catch (Exception $e) {
        // An error occurred, rollback the transaction
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error updating booking: ' . $e->getMessage()]);
    }

    // Close statement and connection
    $deleteStmt->close();
    $insertStmt->close();
    $conn->close();

} else {
    // If required POST variables are not set
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}
?>
