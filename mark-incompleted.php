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
        // Delete the record from the CompletedJobs table
        $stmt = $conn->prepare("DELETE FROM CompletedJobs WHERE BookingID = ?");
        $stmt->bind_param('i', $bookingID);
        
        if ($stmt->execute()) {
            echo "Record deleted from CompletedJobs successfully.";
        } else {
            echo "Error deleting record from CompletedJobs.";
        }
        
        $stmt->close();
    }
} else {
    echo "Invalid request method.";
}

$conn->close();
?>
