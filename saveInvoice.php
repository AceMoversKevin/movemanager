<?php
session_start();
require 'db.php'; // Include your database connection script

header('Content-Type: application/json');

// Check if the user is logged in and has the appropriate role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the invoice ID and name from the POST data
    $invoiceID = isset($_POST['invoiceID']) ? intval($_POST['invoiceID']) : 0;
    $invoiceName = isset($_POST['invoiceName']) ? $_POST['invoiceName'] : '';

    if ($invoiceID > 0 && !empty($invoiceName)) {
        // Save the invoice record to the database
        $stmt = $conn->prepare("INSERT INTO InvoiceCount (InvoiceID, InvoiceName) VALUES (?, ?)");
        $stmt->bind_param("is", $invoiceID, $invoiceName);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Invoice saved successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save the invoice.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid invoice data.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
