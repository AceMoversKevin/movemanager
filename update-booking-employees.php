<?php
require_once 'db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bookingID = isset($_POST['bookingID']) ? intval($_POST['bookingID']) : 0;
    $employeePhones = isset($_POST['employees']) ? $_POST['employees'] : [];

    if ($bookingID > 0) {
        // Start transaction
        $conn->autocommit(FALSE);

        try {
            // First, remove all current employees assigned to this booking
            $stmt = $conn->prepare("DELETE FROM BookingAssignments WHERE BookingID = ?");
            if (!$stmt) {
                throw new Exception($conn->error);
            }
            $stmt->bind_param('i', $bookingID);
            $stmt->execute();
            $stmt->close();

            // Check if there are new employees to assign
            if (!empty($employeePhones)) {
                $stmt = $conn->prepare("INSERT INTO BookingAssignments (BookingID, EmployeePhoneNo) VALUES (?, ?)");
                if (!$stmt) {
                    throw new Exception($conn->error);
                }
                foreach ($employeePhones as $phoneNo) {
                    $stmt->bind_param('is', $bookingID, $phoneNo);
                    $stmt->execute();
                }
                $stmt->close();
            }

            // Commit the transaction
            $conn->commit();
            echo "Booking updated successfully";
        } catch (Exception $e) {
            // An error occurred, rollback the transaction
            $conn->rollback();
            http_response_code(500); // Internal Server Error
            echo "An error occurred while updating the booking: " . $e->getMessage();
        }

        // Turn autocommit back on
        $conn->autocommit(TRUE);
    } else {
        http_response_code(400); // Bad Request
        echo "Invalid booking ID.";
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo "Invalid request method.";
}

// Close the database connection
$conn->close();
?>
