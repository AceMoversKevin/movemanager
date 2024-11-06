<?php
session_start();
require 'db.php'; // Include your database connection script

header('Content-Type: application/json');

// Check if the user is logged in and has the appropriate role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Replace with your actual SMTP2GO API key
$apiKey = 'YOUR_SMTP2GO_API_KEY';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientEmail = isset($_POST['clientEmail']) ? $_POST['clientEmail'] : '';
    $clientName = isset($_POST['clientName']) ? $_POST['clientName'] : '';
    $invoiceID = isset($_POST['invoiceID']) ? intval($_POST['invoiceID']) : 0;
    $pdfData = isset($_POST['pdfData']) ? $_POST['pdfData'] : '';

    if (empty($clientEmail) || empty($clientName) || $invoiceID <= 0 || empty($pdfData)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
        exit;
    }

    // Prepare the email data
    $senderEmail = 'your-email@example.com'; // Replace with your email
    $senderName = 'Your Company Name';       // Replace with your company name
    $subject = 'Your Invoice from ' . $senderName;
    $body = <<<EOT
<html>
<head>
    <title>Your Invoice</title>
</head>
<body>
    <p>Dear {$clientName},</p>
    <p>Please find attached your invoice.</p>
    <p>Best regards,<br/>{$senderName}</p>
</body>
</html>
EOT;

    // Prepare the attachment
    $attachments = [
        [
            'filename' => 'invoice.pdf',
            'content' => $pdfData,
            'encoding' => 'base64'
        ]
    ];

    // Prepare the data payload for SMTP2GO API
    $data = [
        'api_key' => $apiKey,
        'to' => [
            $clientEmail
        ],
        'sender' => "{$senderName} <{$senderEmail}>",
        'subject' => $subject,
        'html_body' => $body,
        'attachments' => $attachments
    ];

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.smtp2go.com/v3/email/send');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    // Execute cURL request
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo json_encode(['success' => false, 'message' => 'cURL error: ' . curl_error($ch)]);
        curl_close($ch);
        exit;
    }
    curl_close($ch);

    // Decode the response
    $responseData = json_decode($response, true);

    // Check if the email was sent successfully
    if (isset($responseData['data']['succeeded']) && $responseData['data']['succeeded'] > 0) {
        echo json_encode(['success' => true, 'message' => 'Email sent successfully.']);
    } else {
        $errorMessage = isset($responseData['data']['failures'][0]['error']) ? $responseData['data']['failures'][0]['error'] : 'Unknown error';
        echo json_encode(['success' => false, 'message' => 'Failed to send email: ' . $errorMessage]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
