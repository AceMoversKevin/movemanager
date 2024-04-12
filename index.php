<?php
session_start(); // Start or resume an existing session

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php"); // Redirect to login page
    exit; // Prevent further execution of the script
}

include 'db.php'; // Include database connection
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <header class="mb-3 py-3">
        <div class="container-fluid">
            <div class="row justify-content-between align-items-center">
                <div class="col-md-6 col-lg-4 user-info">
                    <img src="user.svg" alt="User icon">
                    <span><?= htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 text-md-right">
                <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
                <a href="unassignedBookings.php" class="btn btn-outline-primary">Unassigned Moves</a>
                <a href="logout.php" class="btn btn-outline-danger">Logout</a>
            </div>
        </div>
        </div>

    </header>

    <div class="container">
        <h1>Admin Dashboard</h1>
        <p>Welcome to the Admin Dashboard, <?= htmlspecialchars($_SESSION['username']); ?>!</p>
        <!-- You can add more statistics and information relevant to the admin here -->
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>