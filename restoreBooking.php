<?php
// cancelBooking.php

require_once 'db.php'; // Replace with path to your db connection file

// Check if a booking ID was sent
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bookingID'])) {
    $bookingID = $_POST['bookingID'];

    // Prepare the SQL statement to deactivate the booking
    $sql = "UPDATE Bookings SET isActive = 1 WHERE BookingID = ?";

    // Prepare statement
    if ($stmt = $conn->prepare($sql)) {
        // Bind parameters and execute
        $stmt->bind_param("i", $bookingID);
        if ($stmt->execute()) {
            echo "Booking restored successfully.";
        } else {
            echo "Error restoring booking: " . $stmt->error;
        }
        // Close statement
        $stmt->close();
    } else {
        echo "Error preparing statement: " . $conn->error;
    }

    // Close the connection
    header("cancelledBookings.php");
    $conn->close();
} else {
    echo "No booking ID provided";
}
?>
