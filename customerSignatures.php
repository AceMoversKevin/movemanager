<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Include any necessary PHP code for handling backend logic
$search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';

$stmt = $conn->prepare("SELECT BookingID, Name, Email, Phone, PickupLocation, DropoffLocation, MovingDate, signature FROM Bookings WHERE signature IS NOT NULL AND (Name LIKE ? OR BookingID LIKE ?)");
$stmt->bind_param("ss", $search, $search);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Signatures</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- Additional styling for futuristic look -->
    <style>
        .card {
            border: none;
            border-radius: 10px;
        }

        .card-header {
            border-bottom: 1px solid #4b4e62;
            border-radius: 10px 10px 0 0;
            color: #0000;
            text-decoration-color: #3c3f50;
        }

        .card-title {
            margin-bottom: 0;
        }

        .card-body p {
            margin-bottom: 0.5rem;
        }

        .img-fluid {
            border: 1px solid #4b4e62;
            border-radius: 5px;
            max-height: 200px;
        }

        .border-bottom {
            border-bottom: 1px solid #3c3f50 !important;
        }
    </style>

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
                    <h1 class="h2" id="Main-Heading">View Customer Signatures</h1>
                </div>
                <!-- Search Form -->
                <form class="form-inline mb-3" method="GET" action="">
                    <input class="form-control mr-sm-2" type="search" placeholder="Search by Customer Name or Booking ID" aria-label="Search" name="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                    <button class="btn btn-outline-success my-2 my-sm-0" type="submit">Search</button>
                </form>
                <!-- Dashboard content -->
                <div class="container-fluid">
                    <div class="row">
                        <?php



                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $signatureUrl = 'https://movers.alphamovers.com.au/' . htmlspecialchars($row['signature']);
                                echo '
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5 class="card-title">Booking ID: ' . htmlspecialchars($row['BookingID']) . '</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Customer Name:</strong> ' . htmlspecialchars($row['Name']) . '</p>
                                <p><strong>Email:</strong> ' . htmlspecialchars($row['Email']) . '</p>
                                <p><strong>Phone:</strong> ' . htmlspecialchars($row['Phone']) . '</p>
                                <p><strong>Pickup Location:</strong> ' . htmlspecialchars($row['PickupLocation']) . '</p>
                                <p><strong>Dropoff Location:</strong> ' . htmlspecialchars($row['DropoffLocation']) . '</p>
                                <p><strong>Moving Date:</strong> ' . htmlspecialchars($row['MovingDate']) . '</p>
                                <div class="text-center">
                                    <img src="' . $signatureUrl . '" class="img-fluid" alt="Customer Signature">
                                </div>
                            </div>
                        </div>
                    </div>';
                            }
                        } else {
                            echo '<div class="col-12"><p class="text-center">No signatures found.</p></div>';
                        }
                        $stmt->close();
                        ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>



</body>

</html>