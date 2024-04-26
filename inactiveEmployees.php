<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit;
}

// Include any necessary PHP code for handling backend logic
// Query active employees from the database
$query = "SELECT PhoneNo, Name, Email, EmployeeType FROM Employees WHERE isActive = 0 AND (EmployeeType = 'Helper' OR EmployeeType = 'Driver')";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inactive Employees</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">

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
                    <h1 class="h2" id="Main-Heading">Inactive Employees</h1>
                </div>
                <!-- Dashboard content goes here -->
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th scope="col">Phone Number</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Employee Type</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0) : ?>
                            <?php while ($row = $result->fetch_assoc()) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["PhoneNo"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Name"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["Email"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["EmployeeType"]); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-success deactivateBtn" data-phoneno="<?php echo htmlspecialchars($row["PhoneNo"]); ?>">Activate</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="5">No active employees found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.deactivateBtn').click(function() {
                var phoneNo = $(this).data('phoneno'); // Get the employee phone number
                $.ajax({
                    url: 'activate_employee.php',
                    type: 'POST',
                    data: {
                        'phoneNo': phoneNo
                    },
                    success: function(response) {
                        alert(response); // Show a message with the response
                        location.reload(); // Reload the page to reflect the changes
                    },
                    error: function(xhr, status, error) {
                        console.error("An error occurred: " + error);
                    }
                });
            });
        });
    </script>




</body>

</html>