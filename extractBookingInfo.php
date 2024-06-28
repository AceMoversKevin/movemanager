<?php
require 'db.php';

function extractRate($text)
{
    if (preg_match('/\$(\d{2,3}(?:\.\d{1,2})?)/', $text, $matches)) {
        return $matches[1];
    }
    return null;
}

function extractCalloutFee($text)
{
    if (preg_match('/(\d+)\s*(?:hour|hr|h|half)\s*call\s*out/i', $text, $matches)) {
        return $matches[1];
    }
    if (preg_match('/(\d+)\s*min(?:ute)?s?\s*call\s*out/i', $text, $matches)) {
        return $matches[1] / 60;
    }
    if (stripos($text, 'half an hour call out') !== false || stripos($text, 'half hour call out') !== false || stripos($text, '0.5 hour call out') !== false) {
        return 0.5;
    }
    return null;
}

function extractTruckSize($text)
{
    if (preg_match('/(\d+)\s*ton\s*truck/i', $text, $matches)) {
        return $matches[1];
    }
    return null;
}

$query = "SELECT BookingID, AdditionalDetails, Rate, CalloutFee, TruckSize FROM Bookings WHERE isActive = 1";
$result = $conn->query($query);
$rows = [];

while ($row = $result->fetch_assoc()) {
    $rate = extractRate($row['AdditionalDetails']);
    $calloutFee = extractCalloutFee($row['AdditionalDetails']);
    $truckSize = extractTruckSize($row['AdditionalDetails']);

    if ($rate || $calloutFee || $truckSize) {
        if (!$truckSize && $rate && $rate < 150) {
            $truckSize = 5;
        }
        $rows[] = [
            'BookingID' => $row['BookingID'],
            'AdditionalDetails' => $row['AdditionalDetails'],
            'Rate' => $rate,
            'CalloutFee' => $calloutFee,
            'TruckSize' => $truckSize
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['bookings'] as $bookingID => $data) {
        $rate = $data['Rate'];
        $calloutFee = $data['CalloutFee'];
        $truckSize = $data['TruckSize'];

        $stmt = $conn->prepare("UPDATE Bookings SET Rate = ?, CalloutFee = ?, TruckSize = ? WHERE BookingID = ?");
        $stmt->bind_param("dddi", $rate, $calloutFee, $truckSize, $bookingID);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: activeBookings.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Extract Booking Info</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>

<body>
    <div class="container mt-5">
        <h2>Extracted Booking Information</h2>
        <form method="post">
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
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td><?= htmlspecialchars($row['BookingID']) ?></td>
                            <td><?= htmlspecialchars($row['AdditionalDetails']) ?></td>
                            <td><input type="text" name="bookings[<?= $row['BookingID'] ?>][Rate]" value="<?= htmlspecialchars($row['Rate']) ?>" class="form-control"></td>
                            <td><input type="text" name="bookings[<?= $row['BookingID'] ?>][CalloutFee]" value="<?= htmlspecialchars($row['CalloutFee']) ?>" class="form-control"></td>
                            <td><input type="text" name="bookings[<?= $row['BookingID'] ?>][TruckSize]" value="<?= htmlspecialchars($row['TruckSize']) ?>" class="form-control"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">Approve and Update</button>
        </form>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>