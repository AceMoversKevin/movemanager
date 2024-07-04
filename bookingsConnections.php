<?php
session_start();
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

$bookingId = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if ($bookingId === 0) {
    echo "Invalid booking ID.";
    exit;
}

// Fetch the booking details
$bookingQuery = "SELECT * FROM Bookings WHERE BookingID = $bookingId";
$bookingResult = $conn->query($bookingQuery);
$booking = $bookingResult->fetch_assoc();

if (!$booking) {
    echo "Booking not found.";
    exit;
}

// Normalize phone number function
function normalizePhoneNumber($phone) {
    return preg_replace('/\D/', '', $phone);
}

// Normalize addresses for comparison
function normalizeAddress($address) {
    $normalized = strtolower(trim($address));
    $normalized = preg_replace('/\b(st|street|rd|road|ave|avenue|blvd|boulevard|ln|lane|dr|drive)\b/', '', $normalized); // Remove common address suffixes
    $normalized = preg_replace('/\W+/', ' ', $normalized); // Remove non-alphanumeric characters
    return $normalized;
}

// Check if the booking details or additional details contain the address
function checkAddressMatch($bookingDetail, $leadAddress) {
    $normalizedBookingDetail = normalizeAddress($bookingDetail);
    $normalizedLeadAddress = normalizeAddress($leadAddress);
    return (stripos($normalizedLeadAddress, $normalizedBookingDetail) !== false) || (stripos($normalizedBookingDetail, $normalizedLeadAddress) !== false);
}

// Check name match with partial and case-insensitive comparison
function checkNameMatch($bookingName, $leadName) {
    return stripos($bookingName, $leadName) !== false || stripos($leadName, $bookingName) !== false;
}

// Fetch leads that match potential connections
$leadQuery = "
    SELECT * FROM leads 
    WHERE 
        (email = '{$booking['Email']}') OR
        (REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+61', '0') = REPLACE(REPLACE(REPLACE('{$booking['Phone']}', ' ', ''), '-', ''), '+61', '0'))
";

$leadResult = $conn->query($leadQuery);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Booking Connections</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .highlight {
            background-color: #d4edda;
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

            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Potential Lead Connections for Booking ID: <?= $bookingId ?></h1>
                </div>

                <h3>Booking Details</h3>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Booking</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Booking ID</td>
                            <td><?= htmlspecialchars($booking['BookingID']) ?></td>
                        </tr>
                        <tr>
                            <td>Name</td>
                            <td><?= htmlspecialchars($booking['Name']) ?></td>
                        </tr>
                        <tr>
                            <td>Email</td>
                            <td><?= htmlspecialchars($booking['Email']) ?></td>
                        </tr>
                        <tr>
                            <td>Phone</td>
                            <td><?= htmlspecialchars($booking['Phone']) ?></td>
                        </tr>
                        <tr>
                            <td>Bedrooms</td>
                            <td><?= htmlspecialchars($booking['Bedrooms']) ?></td>
                        </tr>
                        <tr>
                            <td>Pickup Location</td>
                            <td><?= htmlspecialchars($booking['PickupLocation']) ?></td>
                        </tr>
                        <tr>
                            <td>Dropoff Location</td>
                            <td><?= htmlspecialchars($booking['DropoffLocation']) ?></td>
                        </tr>
                        <tr>
                            <td>Booking Date</td>
                            <td><?= htmlspecialchars($booking['BookingDate']) ?></td>
                        </tr>
                        <tr>
                            <td>Moving Date</td>
                            <td><?= htmlspecialchars($booking['MovingDate']) ?></td>
                        </tr>
                        <tr>
                            <td>Additional Details</td>
                            <td><?= htmlspecialchars($booking['AdditionalDetails']) ?></td>
                        </tr>
                    </tbody>
                </table>

                <h3>Potential Lead Connections</h3>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Lead</th>
                                <th>Matches Booking</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($lead = $leadResult->fetch_assoc()) : ?>
                                <?php
                                    // Normalize and compare phone numbers
                                    $normalizedLeadPhone = normalizePhoneNumber($lead['phone']);
                                    $normalizedBookingPhone = normalizePhoneNumber($booking['Phone']);
                                    $phoneMatch = ($normalizedLeadPhone === $normalizedBookingPhone);

                                    // Check name match
                                    $nameMatch = checkNameMatch($booking['Name'], $lead['lead_name']);

                                    // Check address matches
                                    $pickupMatch = checkAddressMatch($booking['PickupLocation'], $lead['pickup']) ||
                                                   checkAddressMatch($booking['AdditionalDetails'], $lead['pickup']);
                                    $dropoffMatch = checkAddressMatch($booking['DropoffLocation'], $lead['dropoff']) ||
                                                    checkAddressMatch($booking['AdditionalDetails'], $lead['dropoff']);
                                ?>
                                <tr>
                                    <td>Lead ID</td>
                                    <td><?= htmlspecialchars($lead['lead_id']) ?></td>
                                    <td class="<?= ($booking['BookingID'] == $lead['lead_id']) ? 'highlight' : '' ?>"><?= ($booking['BookingID'] == $lead['lead_id']) ? 'Yes' : 'No' ?></td>
                                </tr>
                                <tr>
                                    <td>Name</td>
                                    <td><?= htmlspecialchars($lead['lead_name']) ?></td>
                                    <td class="<?= $nameMatch ? 'highlight' : '' ?>"><?= $nameMatch ? 'Yes' : 'No' ?></td>
                                </tr>
                                <tr>
                                    <td>Email</td>
                                    <td><?= htmlspecialchars($lead['email']) ?></td>
                                    <td class="<?= ($booking['Email'] == $lead['email']) ? 'highlight' : '' ?>"><?= ($booking['Email'] == $lead['email']) ? 'Yes' : 'No' ?></td>
                                </tr>
                                <tr>
                                    <td>Phone</td>
                                    <td><?= htmlspecialchars($lead['phone']) ?></td>
                                    <td class="<?= $phoneMatch ? 'highlight' : '' ?>"><?= $phoneMatch ? 'Yes' : 'No' ?></td>
                                </tr>
                                <tr>
                                    <td>Bedrooms</td>
                                    <td><?= htmlspecialchars($lead['bedrooms']) ?></td>
                                    <td class="<?= ($booking['Bedrooms'] == $lead['bedrooms']) ? 'highlight' : '' ?>"><?= ($booking['Bedrooms'] == $lead['bedrooms']) ? 'Yes' : 'No' ?></td>
                                </tr>
                                <tr>
                                    <td>Pickup Location</td>
                                    <td><?= htmlspecialchars($lead['pickup']) ?></td>
                                    <td class="<?= $pickupMatch ? 'highlight' : '' ?>"><?= $pickupMatch ? 'Yes' : 'No' ?></td>
                                </tr>
                                <tr>
                                    <td>Dropoff Location</td>
                                    <td><?= htmlspecialchars($lead['dropoff']) ?></td>
                                    <td class="<?= $dropoffMatch ? 'highlight' : '' ?>"><?= $dropoffMatch ? 'Yes' : 'No' ?></td>
                                </tr>
                                <tr>
                                    <td>Lead Date</td>
                                    <td><?= htmlspecialchars($lead['lead_date']) ?></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Details</td>
                                    <td><?= htmlspecialchars($lead['details']) ?></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Acceptance Limit</td>
                                    <td><?= htmlspecialchars($lead['acceptanceLimit']) ?></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Booking Status</td>
                                    <td><?= htmlspecialchars($lead['booking_status']) ?></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Created At</td>
                                    <td><?= htmlspecialchars($lead['created_at']) ?></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Is Released</td>
                                    <td><?= htmlspecialchars($lead['isReleased']) ?></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Source</td>
                                    <td><?= htmlspecialchars($lead['Source']) ?></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Assigned To</td>
                                    <td><?= htmlspecialchars($lead['AssignedTo']) ?></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Raw Lead ID</td>
                                    <td><?= htmlspecialchars($lead['rawLeadID']) ?></td>
                                    <td></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
</body>

</html>
