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
    if (preg_match('/(\d+(?:\.\d+)?)\s*(?:hour|hr|h|half)\s*(?:call\s*out|travel\s*charge|travel\s*charges)/i', $text, $matches)) {
        return $matches[1];
    }
    if (preg_match('/(\d+)\s*min(?:ute)?s?\s*(?:call\s*out|travel\s*charge|travel\s*charges)/i', $text, $matches)) {
        return $matches[1] / 60;
    }
    if (stripos($text, 'half an hour call out') !== false || stripos($text, 'half hour call out') !== false || stripos($text, '0.5 hour call out') !== false || stripos($text, 'half hr call out') !== false || stripos($text, 'half hour callout fee') !== false || stripos($text, 'half hour travel charges') !== false || stripos($text, 'half hour travel charge') !== false) {
        return 0.5;
    }
    return null;
}

function extractTruckSize($text)
{
    if (preg_match('/(\d+)\s*-?\s*ton\s*truck/i', $text, $matches)) {
        return $matches[1];
    }
    if (preg_match('/(\d+)\s*-?\s*ton/i', $text, $matches)) {
        return $matches[1];
    }
    return null;
}

// Fetch filter parameters from the query parameters
$sortColumn = isset($_GET['sort_column']) ? $_GET['sort_column'] : 'BookingID';
$sortOrder = isset($_GET['sort_order']) && $_GET['sort_order'] === 'asc' ? 'asc' : 'desc';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Construct the query with filters
$query = "SELECT BookingID, Name, Email, AdditionalDetails, Rate, CalloutFee, TruckSize FROM Bookings WHERE isActive = 1 AND (TruckSize IS NULL OR CalloutFee IS NULL OR Rate IS NULL)";

// Add search term filtering
if ($searchTerm) {
    $query .= " AND (Name LIKE '%$searchTerm%' OR Email LIKE '%$searchTerm%' OR AdditionalDetails LIKE '%$searchTerm%')";
}

// Add date filter
if ($dateFilter) {
    switch ($dateFilter) {
        case 'today':
            $query .= " AND DATE(MovingDate) = CURDATE()";
            break;
        case 'next_day':
            $query .= " AND DATE(MovingDate) = CURDATE() + INTERVAL 1 DAY";
            break;
        case 'next_2_days':
            $query .= " AND DATE(MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 2 DAY";
            break;
        case 'next_3_days':
            $query .= " AND DATE(MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY";
            break;
        case 'next_week':
            $query .= " AND DATE(MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 WEEK";
            break;
        case 'next_month':
            $query .= " AND DATE(MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 MONTH";
            break;
        case 'next_year':
            $query .= " AND DATE(MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 YEAR";
            break;
        case 'current_month':
            $query .= " AND MONTH(MovingDate) = MONTH(CURRENT_DATE()) AND YEAR(MovingDate) = YEAR(CURRENT_DATE())";
            break;
        case 'last_month':
            $query .= " AND MONTH(MovingDate) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(MovingDate) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)";
            break;
        case 'current_year':
            $query .= " AND YEAR(MovingDate) = YEAR(CURRENT_DATE())";
            break;
        case 'last_year':
            $query .= " AND YEAR(MovingDate) = YEAR(CURRENT_DATE() - INTERVAL 1 YEAR)";
            break;
        case 'date_range':
            if ($startDate && $endDate) {
                $query .= " AND MovingDate BETWEEN '$startDate' AND '$endDate'";
            }
            break;
        default:
            break;
    }
}

// Add sorting criteria to the query
$query .= " ORDER BY $sortColumn $sortOrder";

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
            'Name' => $row['Name'],
            'Email' => $row['Email'],
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
                        <th>Name</th>
                        <th>Email</th>
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
                            <td><?= htmlspecialchars($row['Name']) ?></td>
                            <td><?= htmlspecialchars($row['Email']) ?></td>
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
