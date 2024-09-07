<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Fetch employees except Admins and SuperAdmins
$query = "SELECT PhoneNo, Name, Email, EmployeeType, isActive, PayRate, ABN, GST FROM Employees WHERE EmployeeType != 'Admin' AND EmployeeType != 'SuperAdmin'";
$result = $conn->query($query);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Employee Details</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="keep-session-alive.js"></script>
</head>

<body>

    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0">
        <a class="navbar-brand col-md-3 col-lg-2 mr-0 px-3" href="index.php">AceMovers</a>
        <ul class="navbar-nav px-3">
            <li class="nav-item text-nowrap">
                <a class="nav-link" href="logout.php">Logout</a>
            </li>
        </ul>
    </header>

    <div class="container-fluid">
        <div class="row">
            <?php include 'navbar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2" id="Main-Heading">Employee Details</h1>
                </div>
                <!-- Dashboard content goes here -->
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>PhoneNo</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Employee Type</th>
                            <th>Active</th>
                            <th>Pay Rate</th>
                            <th>ABN</th>
                            <th>GST</th>
                            <th>Edit</th>
                            <th>Job History</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row["PhoneNo"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["Name"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["Email"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["EmployeeType"]) . "</td>";
                                echo "<td>" . ($row["isActive"] ? 'Yes' : 'No') . "</td>";
                                echo "<td>" . htmlspecialchars($row["PayRate"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["ABN"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["GST"]) . "</td>";
                                echo "<td><a href='editEmployee.php?PhoneNo=" . htmlspecialchars($row["PhoneNo"]) . "' class='btn btn-warning btn-sm'>Edit</a></td>";
                                echo "<td><a href='shiftHistory.php?PhoneNo=" . htmlspecialchars($row["PhoneNo"]) . "' class='btn btn-info btn-sm'>History</a></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7'>No employees found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>

</html>
