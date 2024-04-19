<?php
// updateBooking.php

require_once 'db.php';

// Function to check if the provided field name is valid
function isValidColumnName($field, $validFields) {
    return in_array($field, $validFields, true);
}

// Check if the request is AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    if (isset($_POST['bookingID'], $_POST['field'], $_POST['newValue'])) {
        $bookingID = $_POST['bookingID'];
        $field = $_POST['field'];
        $newValue = $_POST['newValue'];

        // Whitelist of valid field names to prevent SQL injection
        $validFields = ['Name', 'Email', 'Phone', 'Bedrooms', 'BookingDate', 'MovingDate', 'PickupLocation', 'DropoffLocation', 'TruckSize', 'CalloutFee', 'Rate', 'Deposit', 'TimeSlot', 'isActive'];

        // Check if the field name is valid
        if (!isValidColumnName($field, $validFields)) {
            echo json_encode(['success' => false, 'message' => 'Invalid field name.']);
            exit;
        }

        // Prepare the SQL statement - WARNING: This part of the code still needs careful review for security
        $sql = "UPDATE Bookings SET $field = ? WHERE BookingID = ?";

        if ($stmt = $conn->prepare($sql)) {
            // Bind parameters and execute
            $stmt->bind_param("si", $newValue, $bookingID);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Booking updated successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating booking: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error preparing statement: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Incomplete request.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}

$conn->close();
?>