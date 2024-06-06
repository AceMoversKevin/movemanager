<?php
session_start();
require 'db.php'; // Your database connection file

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin' || $_SESSION['role'] != 'SuperAdmin') {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bookingID = isset($_POST['bookingID']) ? intval($_POST['bookingID']) : 0;
    
    if ($bookingID > 0) {
        $stmt = $conn->prepare("INSERT INTO CompletedJobs (BookingID) VALUES (?)");
        $stmt->bind_param('i', $bookingID);
        
        if ($stmt->execute()) {
            echo "Job marked as completed.";
        } else {
            echo "Error marking job as completed.";
        }
        
        $stmt->close();
    }
} else {
    echo "Invalid request method.";
}

$conn->close();
?>
