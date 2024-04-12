<?php
// db.php
$host = 'sql3.freesqldatabase.com';
$dbUser = 'sql3696865';
$dbPass = 'jUr7HcxIvr'; // Change to your actual database password
$dbName = 'sql3696865';

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
