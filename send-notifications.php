<?php
require_once 'db.php'; // Include your database connection

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bookingID = $_POST['bookingID'] ?? 0;

    // Fetch job details and employees
    $query = "SELECT 
    b.BookingID, 
    b.Name AS BookingName, 
    b.Email AS BookingEmail, 
    b.Phone AS BookingPhone, 
    b.Bedrooms, 
    b.MovingDate,
    b.PickupLocation,
    b.DropoffLocation,
    b.TruckSize,
    b.CalloutFee,
    b.Rate,
    b.Deposit,
    b.TimeSlot,  -- Include the TimeSlot in the SELECT
    GROUP_CONCAT(e.Name ORDER BY e.Name SEPARATOR ', ') AS EmployeeNames,
    GROUP_CONCAT(e.Email ORDER BY e.Name SEPARATOR ', ') AS EmployeeEmails -- To get the emails for notification
FROM 
    Bookings b
JOIN 
    BookingAssignments ba ON b.BookingID = ba.BookingID
JOIN 
    Employees e ON ba.EmployeePhoneNo = e.PhoneNo
WHERE 
    b.BookingID = ?
GROUP BY 
    b.BookingID";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $bookingID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $jobDetails = $result->fetch_assoc();
    }
    $stmt->close();

    // SMTP2GO Email API details
    $apiKey = '<Add key in prod>'; // Replace with your SMTP2GO API key
    $recipientEmails = explode(', ', $jobDetails['EmployeeEmails']);
    $senderEmail = 'aaron@acemovers.com.au'; // Replace with your sender email
    $senderName = 'Aaron Miller'; // Replace with your sender name
    $subject = 'Job Assignment Notification';

    // Construct email body
    $body = <<<EOT
<html>
<head>
<title>Job Assignment Notification</title>
</head>
<body>
<h1>Job Assignment Details</h1>
<p><strong>Booking Name:</strong> {$jobDetails['BookingName']}</p>
<p><strong>Email:</strong> {$jobDetails['BookingEmail']}</p>
<p><strong>Phone:</strong> {$jobDetails['BookingPhone']}</p>
<p><strong>Bedrooms:</strong> {$jobDetails['Bedrooms']}</p>
<p><strong>Moving Date:</strong> {$jobDetails['MovingDate']}</p>
<p><strong>Time Slot:</strong> {$jobDetails['TimeSlot']}</p>
<p><strong>Pickup Location:</strong> {$jobDetails['PickupLocation']}</p>
<p><strong>Dropoff Location:</strong> {$jobDetails['DropoffLocation']}</p>
<p><strong>Truck Size:</strong> {$jobDetails['TruckSize']}</p>
<p><strong>Callout Fee:</strong> \${$jobDetails['CalloutFee']}</p>
<p><strong>Rate:</strong> \${$jobDetails['Rate']}</p>
<p><strong>Deposit:</strong> \${$jobDetails['Deposit']}</p>
<p><strong>Assigned Employees:</strong> {$jobDetails['EmployeeNames']}</p>
</body>
</html>
EOT;

    // Send the email using SMTP2GO Email API
    foreach ($recipientEmails as $recipientEmail) {
        $data = [
            'api_key' => $apiKey,
            'to' => [
                $recipientEmail
            ],
            'sender' => $senderEmail,
            'subject' => $subject,
            'html_body' => $body
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.smtp2go.com/v3/email/send');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Smtp2go-Api-Key: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);

        // Log the JSON response to the error log
        error_log('SMTP2GO API Response: ' . json_encode($responseData));

        if (isset($responseData['data']['succeeded']) && $responseData['data']['succeeded'] > 0) {
            error_log('Message has been sent to ' . $recipientEmail);
        } else {
            error_log('Message was not sent to ' . $recipientEmail . '. Error: ' . ($responseData['data']['errors'][0]['message'] ?? 'Unknown error'));
        }
    }
}
