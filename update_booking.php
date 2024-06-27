<?php
session_start();
require 'db.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    echo 'Unauthorized';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $booking_id = $_POST['booking_id'];
    $field = $_POST['field'];
    $value = $_POST['value'];

    // Validate and sanitize input
    $booking_id = intval($booking_id);
    $field = mysqli_real_escape_string($conn, $field);
    $value = mysqli_real_escape_string($conn, $value);

    // Update the booking in the database
    $sql = "UPDATE Bookings SET $field = '$value' WHERE BookingID = $booking_id";
    if ($conn->query($sql) === TRUE) {
        echo 'Record updated successfully';
    } else {
        echo 'Error updating record: ' . $conn->error;
    }
}
?>
