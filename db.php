<?php
// db.php

// New database connection details
$host = 'localhost'; // Since the database is on the same server
$port = 3306; // Default MySQL port
$dbUser = 'alphaard_aaron'; // Your database user
$dbPass = 'gaOctkSGLJ24'; // Change to your actual database password
$dbName = 'alphaard_testdatabase'; // Change to your actual database name

// Create a new mysqli instance
$conn = new mysqli($host, $dbUser, $dbPass, $dbName, $port);

// Check if the connection is established
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Success message (for testing purposes; remove in production)
// echo "Connected successfully to the local database";
?>
