<?php
session_start();
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Fetch the last changes made to the bookings
$query = "SELECT * FROM BookingChangesLog ORDER BY ChangeDate DESC LIMIT 1";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $log = $result->fetch_assoc();

    // Rollback the changes
    $stmt = $conn->prepare("UPDATE Bookings SET Rate = ?, CalloutFee = ?, TruckSize = ? WHERE BookingID = ?");
    $stmt->bind_param("sssi", $log['OriginalRate'], $log['OriginalCalloutFee'], $log['OriginalTruckSize'], $log['BookingID']);
    $stmt->execute();
    $stmt->close();

    // Delete the log entry
    $stmt = $conn->prepare("DELETE FROM BookingChangesLog WHERE LogID = ?");
    $stmt->bind_param("i", $log['LogID']);
    $stmt->execute();
    $stmt->close();

    echo "Changes rolled back successfully!";
} else {
    echo "No changes to roll back.";
}

$conn->close();
