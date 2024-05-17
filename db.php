<?php
// db.php

// New database connection details
$host = 'mysql-30f3d557-acemovers-dd24.b.aivencloud.com';
$port = 26656; // Your Aiven MySQL port
$dbUser = 'avnadmin';
$dbPass = 'AVNS_NU9ZIgbnh6Rrvc7ThrU'; // Change to your actual database password
$dbName = 'defaultdb';

// Path to your ca.pem file
$ssl_ca = './ca.pem';

// Create a new mysqli instance and enable SSL
$conn = mysqli_init();

if (!$conn) {
    die("mysqli_init failed");
}

mysqli_ssl_set($conn, NULL, NULL, $ssl_ca, NULL, NULL); // Set SSL options

if (!mysqli_real_connect($conn, $host, $dbUser, $dbPass, $dbName, $port, NULL, MYSQLI_CLIENT_SSL)) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check if the connection is established
if ($conn->connect_errno) {
    die("Connection failed: " . $conn->connect_error);
}

// Success message (for testing purposes; remove in production)
echo "Connected successfully to the external database";
?>
