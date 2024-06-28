<?php
session_start();
require 'db.php';

function parseEmailBody($emailBody)
{
    $parsedData = [];

    // Parse the email body using regular expressions
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

    return $parsedData;
}

$query = "SELECT * FROM rawLeads";
$result = $conn->query($query);
$rows = [];

while ($row = $result->fetch_assoc()) {
    $parsedData = parseEmailBody($row['EmailBody']);

    if ($parsedData) {
        $parsedData['Source'] = 'Google';
        $parsedData['lead_date'] = date('Y-m-d');
        $parsedData['isReleased'] = 0;
        $parsedData['AssignedTo'] = $row['teamMember'];
        $parsedData['rawLeadID'] = $row['rawLeadID'];

        $rows[] = [
            'rawLeadID' => $row['rawLeadID'],
            'EmailBody' => $row['EmailBody'],
            'parsedData' => $parsedData
        ];
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