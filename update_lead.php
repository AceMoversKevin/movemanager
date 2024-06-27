<?php
session_start();
include 'db.php'; // Adjust the path to your database connection file as necessary

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    echo 'Unauthorized';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lead_id = $_POST['lead_id'];
    $field = $_POST['field'];
    $value = $_POST['value'];

    // Validate and sanitize input
    $lead_id = intval($lead_id);
    $field = mysqli_real_escape_string($conn, $field);
    $value = mysqli_real_escape_string($conn, $value);

    // Update the lead in the database
    $sql = "UPDATE leads SET $field = '$value' WHERE lead_id = $lead_id";
    if ($conn->query($sql) === TRUE) {
        echo 'Record updated successfully';
    } else {
        echo 'Error updating record: ' . $conn->error;
    }
}
