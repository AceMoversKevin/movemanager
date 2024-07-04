<?php
session_start();
require 'db.php';

function parseEmailBody($emailBody, $teamMember)
{
    $parsedData = [];

    if ($teamMember === 'MovingSelect') {
        // Parse MovingSelect email body
        if (preg_match('/Name<\/td>.*?<td.*?><span.*?>(.*?)<\/span>/', $emailBody, $matches)) {
            $parsedData['lead_name'] = trim($matches[1]);
        }

        if (preg_match('/Phone<\/td>.*?<td.*?><a.*?>(.*?)<\/a>/', $emailBody, $matches)) {
            $parsedData['phone'] = trim($matches[1]);
        }

        if (preg_match('/Email<\/td>.*?<td.*?><a.*?>(.*?)<\/a>/', $emailBody, $matches)) {
            $parsedData['email'] = trim($matches[1]);
        }

        if (preg_match('/Moving From<\/td>.*?<td.*?>(.*?)<\/td>/', $emailBody, $matches)) {
            $parsedData['pickup'] = trim($matches[1]);
        }

        if (preg_match('/Moving To<\/td>.*?<td.*?>(.*?)<\/td>/', $emailBody, $matches)) {
            $parsedData['dropoff'] = trim($matches[1]);
        }

        if (preg_match('/Moving Size<\/td>.*?<td.*?>(.*?)<\/td>/', $emailBody, $matches)) {
            $parsedData['bedrooms'] = trim($matches[1]);
        }

        if (preg_match('/Moving Date<\/td>.*?<td.*?><span.*?>(.*?)<\/span>/', $emailBody, $matches)) {
            $parsedData['lead_date'] = date('Y-m-d', strtotime(trim($matches[1])));
        }

        $parsedData['Source'] = 'MovingSelect';
        $parsedData['AssignedTo'] = 'Admin';
    } elseif ($teamMember === 'PricesCompare') {
        // Parse PricesCompare email body
        if (preg_match('/Contact Name:<\/td>\s*<td[^>]*><\/td>\s*<td>(.*?)<\/td>/', $emailBody, $matches)) {
            $parsedData['lead_name'] = trim($matches[1]);
        }

        if (preg_match('/Phone:<\/td>\s*<td[^>]*><\/td>\s*<td>(.*?)<\/td>/', $emailBody, $matches)) {
            $parsedData['phone'] = trim($matches[1]);
        }

        if (preg_match('/Email:<\/td>\s*<td[^>]*><\/td>\s*<td><a[^>]*>(.*?)<\/a>/', $emailBody, $matches)) {
            $parsedData['email'] = trim($matches[1]);
        }

        if (preg_match('/Pick Up:<\/td>\s*<td[^>]*><\/td>\s*<td>\s*(.*?)\s*<\/td>/', $emailBody, $matches)) {
            $parsedData['pickup'] = trim(strip_tags($matches[1]));
        }

        if (preg_match('/Drop Off:<\/td>\s*<td[^>]*><\/td>\s*<td>\s*(.*?)\s*<\/td>/', $emailBody, $matches)) {
            $parsedData['dropoff'] = trim(strip_tags($matches[1]));
        }

        if (preg_match('/Date of Job:<\/td>\s*<td[^>]*><\/td>\s*<td>(.*?)<\/td>/', $emailBody, $matches)) {
            $parsedData['lead_date'] = date('Y-m-d', strtotime(trim($matches[1])));
        }

        if (preg_match('/Rooms:<\/td>\s*<td[^>]*><\/td>\s*<td>(.*?)<\/td>/', $emailBody, $matches)) {
            $parsedData['bedrooms'] = trim($matches[1]);
        }

        if (preg_match('/<b>Notes:<\/b><br>(.*?)<br>/', $emailBody, $matches)) {
            $parsedData['details'] = trim($matches[1]);
        } else {
            $parsedData['details'] = '';
        }

        $parsedData['Source'] = 'PricesCompare';
        $parsedData['AssignedTo'] = 'Admin';
    } else {
        // Parse Google lead email body
        if (preg_match('/So, What\'s your name\s*(.*?)\n/', $emailBody, $matches)) {
            $parsedData['lead_name'] = trim($matches[1]);
        }

        if (preg_match('/How many bedrooms are we looking at\?\s*(.*?)\n/', $emailBody, $matches)) {
            $parsedData['bedrooms'] = trim($matches[1]);
        }

        if (preg_match('/Now please tell me the pick up location\s*(.*?)\n/', $emailBody, $matches)) {
            $parsedData['pickup'] = trim($matches[1]);
        }

        if (preg_match('/And where are we moving to\?\s*(.*?)\n/', $emailBody, $matches)) {
            $parsedData['dropoff'] = trim($matches[1]);
        }

        if (preg_match('/Now please enter your phone number\s*(.*?)\n/', $emailBody, $matches)) {
            $parsedData['phone'] = trim($matches[1]);
        }

        if (preg_match('/Do you want to add any additional details\? \(optional\)\s*(.*?)\n/', $emailBody, $matches)) {
            $parsedData['details'] = trim($matches[1]);
        }

        if (preg_match('/Now Please enter your email address\s*(.*?)\n/', $emailBody, $matches)) {
            $parsedData['email'] = trim($matches[1]);
        }

        $parsedData['Source'] = 'Google';
        $parsedData['AssignedTo'] = $teamMember;
    }

    $parsedData['isReleased'] = 0;
    return $parsedData;
}

// Fetch raw leads and order them by rawLeadID in descending order
$query = "SELECT * FROM rawLeads ORDER BY rawLeadID DESC";
$result = $conn->query($query);
$rows = [];

while ($row = $result->fetch_assoc()) {
    $parsedData = parseEmailBody($row['EmailBody'], $row['teamMember']);

    if ($parsedData) {
        $parsedData['lead_date'] = $parsedData['lead_date'] ?? date('Y-m-d');
        $parsedData['rawLeadID'] = $row['rawLeadID'];

        $rows[] = [
            'rawLeadID' => $row['rawLeadID'],
            'EmailBody' => $row['EmailBody'],
            'parsedData' => $parsedData
        ];
    } else {
        // For MovingSelect and PricesCompare leads, if parsing fails, still show the email body.
        if ($row['teamMember'] === 'MovingSelect' || $row['teamMember'] === 'PricesCompare') {
            $rows[] = [
                'rawLeadID' => $row['rawLeadID'],
                'EmailBody' => $row['EmailBody'],
                'parsedData' => [
                    'Source' => $row['teamMember'],
                    'AssignedTo' => 'Admin'
                ]
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Parsed Raw Leads</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>

<body>
    <div class="container mt-5">
        <h2>Parsed Lead Information</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Raw Lead ID</th>
                    <th>Email Body</th>
                    <th>Parsed Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?= htmlspecialchars($row['rawLeadID']) ?></td>
                        <td>
                            <pre><?= htmlspecialchars($row['EmailBody']) ?></pre>
                        </td>
                        <td>
                            <ul>
                                <?php foreach ($row['parsedData'] as $key => $value) : ?>
                                    <li><strong><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars($value) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>