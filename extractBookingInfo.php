<?php
require 'db.php';

// Function to extract rate, callout fee, and truck size from the AdditionalDetails
function extractBookingDetails($details)
{
    $rate = null;
    $calloutFee = null;
    $truckSize = null;

    // Extract rate
    if (preg_match('/\$(\d+)/', $details, $matches)) {
        $rate = $matches[1];
    }

    // Extract callout fee
    if (preg_match('/(\d+)\s*min(?:ute)?(?:s)?\s*call\s*out/', $details, $matches)) {
        $calloutFee = $matches[1] / 60; // Convert minutes to decimal hours
    } elseif (preg_match('/(\d+)\s*hour(?:s)?\s*call\s*out/', $details, $matches)) {
        $calloutFee = $matches[1];
    } elseif (preg_match('/half(?:\s*an)?\s*hour\s*call\s*out/', $details)) {
        $calloutFee = 0.5; // Half an hour as 0.5 hours
    } elseif (preg_match('/(\d+)\s*hr(?:s)?\s*call\s*out/', $details, $matches)) {
        $calloutFee = $matches[1];
    } elseif (preg_match('/(\d+)\s*h(?:rs?)?\s*call\s*out/', $details, $matches)) {
        $calloutFee = $matches[1];
    }

    // Extract truck size
    if (preg_match('/(\d+)\s*ton(?:ne)?\s*truck/', $details, $matches)) {
        $truckSize = $matches[1];
    } elseif (preg_match('/(\d+)\s*T\s*truck/', $details, $matches)) {
        $truckSize = $matches[1];
    } elseif (preg_match('/(\d+)\s*t(?:onne)?\s*truck/', $details, $matches)) {
        $truckSize = $matches[1];
    }

    // Assume truck size of 5 if rate is less than 150 and truck size is not present
    if ($rate !== null && $rate < 150 && $truckSize === null) {
        $truckSize = 5;
    }

    return [$rate, $calloutFee, $truckSize];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($_POST['rate'] as $bookingID => $rate) {
        $calloutFee = $_POST['callout_fee'][$bookingID];
        $truckSize = $_POST['truck_size'][$bookingID];

        $stmt = $conn->prepare("UPDATE Bookings SET Rate = ?, CalloutFee = ?, TruckSize = ? WHERE BookingID = ?");
        $stmt->bind_param("ddii", $rate, $calloutFee, $truckSize, $bookingID);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: activeBookings.php");
    exit;
}

// Fetch all bookings with missing rate, callout fee, or truck size
$query = "SELECT BookingID, AdditionalDetails FROM Bookings WHERE Rate IS NULL OR CalloutFee IS NULL OR TruckSize IS NULL";
$result = $conn->query($query);

$bookings = [];
while ($row = $result->fetch_assoc()) {
    list($rate, $calloutFee, $truckSize) = extractBookingDetails($row['AdditionalDetails']);
    $bookings[] = [
        'BookingID' => $row['BookingID'],
        'AdditionalDetails' => $row['AdditionalDetails'],
        'Rate' => $rate,
        'CalloutFee' => $calloutFee,
        'TruckSize' => $truckSize
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extracted Booking Information</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>

<body>
    <div class="container mt-5">
        <h2>Extracted Booking Information</h2>
        <form method="post" action="extractBookingInfo.php">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Additional Details</th>
                        <th>Extracted Rate</th>
                        <th>Extracted Callout Fee</th>
                        <th>Extracted Truck Size</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking) : ?>
                        <tr>
                            <td><?= htmlspecialchars($booking['BookingID']) ?></td>
                            <td><?= htmlspecialchars($booking['AdditionalDetails']) ?></td>
                            <td><input type="text" name="rate[<?= $booking['BookingID'] ?>]" value="<?= htmlspecialchars($booking['Rate']) ?>" class="form-control"></td>
                            <td><input type="text" name="callout_fee[<?= $booking['BookingID'] ?>]" value="<?= htmlspecialchars($booking['CalloutFee']) ?>" class="form-control"></td>
                            <td><input type="text" name="truck_size[<?= $booking['BookingID'] ?>]" value="<?= htmlspecialchars($booking['TruckSize']) ?>" class="form-control"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">Approve and Update</button>
        </form>
    </div>
</body>

</html>