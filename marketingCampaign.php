<?php
session_start();

// Include database connection
require 'db.php';

// Include the Twilio PHP library
require_once 'twilio-php-main/src/Twilio/autoload.php';
use Twilio\Rest\Client;

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Twilio credentials
$sid    = "[SID]";
$token  = "[AuthToken]";
$twilio = new Client($sid, $token);

$uploadSuccess = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, 'r');

    if ($handle !== false) {
        // Skip the header row
        fgetcsv($handle);

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $name = $data[0];
            $phone_number = $data[1];
            $message_body = "Hello $name, this is a marketing message from AceMovers.";

            // Send SMS using Twilio
            try {
                $message = $twilio->messages->create(
                    $phone_number, // to
                    array(
                        "messagingServiceSid" => "[MSID]", // Your messaging service SID
                        "body" => $message_body
                    )
                );
            } catch (Exception $e) {
                $error = "Error sending SMS to $phone_number: " . $e->getMessage();
                break;
            }
        }

        fclose($handle);
        if (!$error) {
            $uploadSuccess = true;
        }
    } else {
        $error = "Error reading the CSV file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Marketing Campaign</title>
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
                    <h1 class="h2" id="Main-Heading">Marketing Campaign Dashboard</h1>
                </div>

                <!-- Display success or error messages -->
                <?php if ($uploadSuccess): ?>
                    <div class="alert alert-success">Messages have been sent successfully!</div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Upload CSV Form -->
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_file">Upload CSV File</label>
                        <input type="file" class="form-control-file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Send SMS</button>
                </form>

            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>

</html>
