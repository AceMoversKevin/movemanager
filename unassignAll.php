<?php
include 'db.php';
session_start();

// Check for admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Check for the correct POST variable
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bookingID'])) {
    $bookingID = $conn->real_escape_string($_POST['bookingID']);

    // Begin a transaction
    $conn->begin_transaction();

    try {
        // Delete the assignments for this booking
        $deleteQuery = "DELETE FROM Bookings_Employees WHERE BookingID = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("i", $bookingID);
        $deleteStmt->execute();

        $deletePricingQuery = "DELETE FROM BookingPricing WHERE BookingID = ?";
        $deletePricingStmt = $conn->prepare($deletePricingQuery);
        $deletePricingStmt->bind_param("i", $bookingID);
        $deletePricingStmt->execute();

        // Commit the transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'All employees have been unassigned from this booking.']);
    } catch (Exception $e) {
        // An error occurred, rollback the transaction
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error unassigning employees: ' . $e->getMessage()]);
    }

    // Close statement and connection
    $deleteStmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}
