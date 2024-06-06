<?php
session_start();
require 'db.php'; // Ensure your db connection is correctly set up

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    echo "You do not have permission to perform this action.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['phoneNo'])) {
    $phoneNo = $conn->real_escape_string($_POST['phoneNo']);
    
    $sql = "UPDATE Employees SET isActive = 1 WHERE PhoneNo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $phoneNo);
    if ($stmt->execute()) {
        echo "Employee activated successfully.";
    } else {
        echo "Error activating employee.";
    }
    $stmt->close();
} else {
    echo "Invalid request.";
}

$conn->close();
?>
