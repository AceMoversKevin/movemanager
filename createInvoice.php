<?php
session_start();
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Function to save invoice data if the request is POST and contains the necessary data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle AJAX request to send email
    if (isset($_POST['action']) && $_POST['action'] === 'sendEmail') {
        // Get the posted data
        $pdfData = $_POST['pdfData'] ?? null;
        $clientEmail = $_POST['clientEmail'] ?? null;
        $clientName = $_POST['clientName'] ?? null;
        $invoiceID = $_POST['invoiceID'] ?? null;

        if (!$pdfData || !$clientEmail || !$clientName || !$invoiceID) {
            echo json_encode(['success' => false, 'error' => 'Missing data']);
            exit;
        }

        // Now, set up the SMTP2GO API call

        $apiKey = 'SMTP API KEY'; // Replace with your SMTP2GO API key
        $recipientEmail = $clientEmail;
        $recipientName = $clientName;
        $senderEmail = 'aaron@acemovers.com.au'; // Replace with your sender email
        $senderName = 'Aaron Miller'; // Replace with your sender name
        $subject = "Invoice for Booking #$invoiceID";
        $body = "Dear $clientName,\n\nPlease find attached the invoice for your recent booking.\n\nBest regards,\nAce Movers";

        $attachment = $pdfData; // base64 encoded PDF data

        $data = [
            'api_key' => $apiKey,
            'to' => [
                $recipientEmail
            ],
            'cc' => [
                'info@acemovers.com.au' // Add the CC email address here
            ],
            'sender' => "$senderName <$senderEmail>",
            'subject' => $subject,
            'text_body' => $body,
            'attachments' => [
                [
                    'filename' => 'invoice.pdf',
                    'fileblob' => $attachment,
                    'mimetype' => 'application/pdf'
                ]
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.smtp2go.com/v3/email/send');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);

        // Log the JSON response to the error log
        error_log('SMTP2GO API Response: ' . json_encode($responseData));

        if (isset($responseData['data']['succeeded']) && $responseData['data']['succeeded'] > 0) {
            // Email sent successfully
            echo json_encode(['success' => true]);
        } else {
            // Failed to send email
            $errorMessage = $responseData['data']['errors'][0]['message'] ?? 'Unknown error';
            echo json_encode(['success' => false, 'error' => $errorMessage]);
        }
        exit;
    }

    // Handle saving invoice data
    if (isset($_POST['invoiceID'], $_POST['invoiceName'])) {
        $invoiceID = $_POST['invoiceID'];
        $invoiceName = $_POST['invoiceName'];

        $sql = "INSERT INTO InvoiceCount (InvoiceID, InvoiceName) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $invoiceID, $invoiceName);

        $response = ['success' => false];
        if ($stmt->execute()) {
            $response['success'] = true;
        }
        $stmt->close();
        $conn->close();

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Retrieve the latest InvoiceID
$sql = "SELECT MAX(InvoiceID) AS latestInvoiceID FROM InvoiceCount";
$result = $conn->query($sql);

$latestInvoiceID = 10979170; // Default value if no records exist
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if ($row['latestInvoiceID'] != null) {
        $latestInvoiceID = $row['latestInvoiceID'] + 1;
    }
}

echo "<script>const nextInvoiceID = $latestInvoiceID;</script>";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Head content -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice</title>
    <!-- Include Bootstrap CSS and other styles -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Include custom fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <!-- Keep Session Alive -->
    <script src="keep-session-alive.js"></script>
    <style>
        /* General styles */
        body,
        html {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background-color: #f5f5f5;
        }

        /* Invoice styles */
        .invoice-box {
            width: 100%;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            /* Reduced padding */
            padding: 20px;
            /* Reduced padding */
            background-color: #fff;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            color: #333;
        }

        .invoice-box table {
            width: 100%;
            line-height: 1.4;
            /* Adjusted line height */
            text-align: left;
            border-collapse: collapse;
        }

        .invoice-box table td {
            padding: 5px;
            /* Reduced padding */
            vertical-align: top;
        }

        .invoice-box table tr td:nth-child(2) {
            text-align: right;
        }

        .invoice-box table tr.top table td.title {
            font-size: 28px;
            /* Reduced font size */
            color: #333;
        }

        .invoice-box table tr.information table td {
            padding-bottom: 10px;
            /* Reduced padding */
        }

        .invoice-box table tr.heading td {
            background: #f0f0f0;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            padding: 8px;
            /* Reduced padding */
        }

        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
            padding: 8px;
            /* Reduced padding */
        }

        .invoice-box table tr.item.last td {
            border-bottom: none;
        }

        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #f0f0f0;
            font-weight: bold;
            font-size: 16px;
            /* Reduced font size */
        }

        .invoice-box p {
            font-size: 12px;
            /* Reduced font size */
            color: #555;
            margin-top: 10px;
            /* Reduced margin */
        }

        /* Editable Fields Styles */
        .invoice-box [contenteditable="true"] {
            background-color: #e8f0fe;
            /* Light blue */
            border-bottom: 1px dashed #ccc;
            padding: 2px;
        }

        .invoice-box [contenteditable="true"]:focus {
            outline: none;
            background-color: #d2e3fc;
            /* Slightly darker blue */
        }

        /* Style for select elements */
        .invoice-box select {
            font-size: 14px;
            /* Reduced font size */
            padding: 2px;
            border: none;
            background: transparent;
            outline: none;
            appearance: none;
            text-align-last: right;
        }

        /* Stamp styles */
        .stamp {
            display: inline-block;
            padding: 8px 16px;
            /* Reduced padding */
            color: #fff;
            border-radius: 5px;
            user-select: none;
            cursor: pointer;
            font-size: 14px;
            /* Reduced font size */
        }

        .stamp.PAID {
            background-color: green;
        }

        .stamp.UNPAID {
            background-color: red;
        }

        /* Hidden class to hide elements */
        .hidden {
            display: none;
        }

        /* Responsive styles */
        @media only screen and (max-width: 600px) {
            .invoice-box table tr.top table td {
                display: block;
                text-align: center;
            }

            .invoice-box table tr.information table td {
                display: block;
                text-align: center;
            }
        }

        /* Controls styling */
        .controls {
            margin: 20px auto;
            max-width: 800px;
        }

        .controls h4 {
            margin-bottom: 15px;
        }

        .controls .form-check {
            margin-bottom: 10px;
        }

        /* PDF adjustments */
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
    <!-- Include html2pdf.js library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
</head>

<body>
    <!-- Include your header and navigation -->
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 no-print">
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
                <div
                    class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
                    <h1 class="h2" id="Main-Heading">Create Invoice</h1>
                </div>
                <!-- Controls for selecting invoice type and toggling additional charges -->
                <div class="controls no-print">
                    <h4>Invoice Type</h4>
                    <div class="form-group">
                        <label for="invoiceTypeSelect">Select Invoice Type:</label>
                        <select id="invoiceTypeSelect" class="form-control" onchange="generateInvoice()">
                            <option value="variable">Variable Price Invoice</option>
                            <option value="fixed">Fixed Price Invoice</option>
                        </select>
                    </div>
                    <h4>Additional Charges</h4>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="toggleStairCharge">
                        <label class="form-check-label" for="toggleStairCharge">
                            Include Stair Charge
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="togglePianoCharge">
                        <label class="form-check-label" for="togglePianoCharge">
                            Include Piano Charge
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="togglePoolTableCharge">
                        <label class="form-check-label" for="togglePoolTableCharge">
                            Include Pool Table Charge
                        </label>
                    </div>
                    <!-- New Card Surcharge Control -->
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="toggleCardSurcharge">
                        <label class="form-check-label" for="toggleCardSurcharge">
                            Include Card Surcharge
                        </label>
                    </div>
                    <!-- Miscellaneous Charge Control -->
                    <div class="form-check hidden" id="toggleMiscChargeContainer">
                        <input class="form-check-input" type="checkbox" id="toggleMiscCharge">
                        <label class="form-check-label" for="toggleMiscCharge">
                            Include Miscellaneous Charge
                        </label>
                    </div>
                </div>
                <!-- Invoice Container -->
                <div id="invoice-preview" class="invoice-box">
                    <!-- Editable Invoice content will be populated here by JavaScript -->
                </div>
                <!-- Buttons -->
                <div class="text-center no-print">
                    <button type="button" class="btn btn-success mt-3" onclick="downloadInvoice()">Download
                        Invoice</button>
                    <button type="button" class="btn btn-primary mt-3 ml-2" onclick="sendInvoiceEmail()">Send
                        Email</button>
                </div>
                <br>
                <br>
                <br>
            </main>
        </div>
    </div>
    <!-- Include Bootstrap JS and other scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Include Popper and Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- JavaScript code -->
    <script>
        // Initialize the invoice when the page loads
        document.addEventListener('DOMContentLoaded', function () {
            generateInvoice(); // Populate with default values
            attachEventListeners(); // Attach event listeners to the checkboxes
        });

        function isGSTIncluded(gstValue) {
            return gstValue.trim().toLowerCase() === 'yes';
        }

        function generateInvoice() {
            const invoiceType = document.getElementById('invoiceTypeSelect').value;
            const today = new Date();
            const formattedDate = today.toLocaleDateString('en-AU'); // Australian date format

            const defaultValues = {
                clientName: 'Client Name',
                clientEmail: 'Email',
                date: formattedDate
            };

            // Common invoice HTML parts
            const commonInvoiceHTML = `
                <table>
                    <tr class="top">
                        <td colspan="2">
                            <table>
                                <tr>
                                    <td class="title">
                                        <img src="https://portal.alphamovers.com.au/logo.png" alt="Company logo" style="width:100%; max-width:120px;">
                                    </td>
                                    <td class="invoice-details">
                                        <b>INVOICE #${nextInvoiceID}</b><br />
                                        Moving Service<br />
                                        <span id="invoiceDate" contenteditable="true" data-type="text">${defaultValues.date}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr class="information">
                        <td colspan="2">
                            <table>
                                <tr>
                                    <td>
                                        ACE MOVERS PTY LTD.<br />
                                        ACN:640 368 930
                                    </td>
                                    <td>
                                        Client Name: <span id="clientName" contenteditable="true" data-type="text">${defaultValues.clientName}</span><br />
                                        Email: <span id="clientEmail" contenteditable="true" data-type="text">${defaultValues.clientEmail}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
            `;

            // Additional charges section, common to both invoice types
            const additionalChargesHTML = `
                <tbody id="additionalChargesSection">
                    <tr class="heading">
                        <td colspan="2">Additional Charges</td>
                    </tr>
                    <tr class="item" id="stairChargeRow">
                        <td>Stair Charge</td>
                        <td>$<span id="stairCharges" contenteditable="true" data-type="number">0.00</span></td>
                    </tr>
                    <tr class="item" id="pianoChargeRow">
                        <td>Piano Charge</td>
                        <td>$<span id="pianoCharge" contenteditable="true" data-type="number">0.00</span></td>
                    </tr>
                    <tr class="item" id="poolTableChargeRow">
                        <td>Pool Table Charge</td>
                        <td>$<span id="poolTableCharge" contenteditable="true" data-type="number">0.00</span></td>
                    </tr>
                    <!-- For fixed price invoice, include custom miscellaneous charge -->
                    ${invoiceType === 'fixed' ? `
                    <tr class="item" id="miscChargeRow">
                        <td><span id="miscChargeName" contenteditable="true" data-type="text">Miscellaneous Charge</span></td>
                        <td>$<span id="miscChargeAmount" contenteditable="true" data-type="number">0.00</span></td>
                    </tr>
                    ` : ''}
                </tbody>
            `;

            // Common footer and total
            const commonFooterHTML = `
                <tr class="heading">
                    <td colspan="2">Deposit</td>
                </tr>
                <tr class="item last">
                    <td>Initial Deposit Adjustment</td>
                    <td>-$<span id="deposit" contenteditable="true" data-type="number">0.00</span></td>
                </tr>
                <tr class="total">
                    <td></td>
                    <td>Total: $<span id="totalCharge">0.00</span></td>
                </tr>
                <!-- New Card Surcharge Row -->
                <tr id="cardSurchargeRow" class="hidden">
                    <td colspan="2" style="text-align: right;">
                    (includes 2% surcharge of $<span id="cardSurchargeAmount">0.00</span>)
                    </td>
                </tr>
                <tr class="status">
                    <td colspan="2" style="text-align: center;">
                        <span id="paymentStatus" class="stamp PAID">PAID</span>
                    </td>
                </tr>
                </table>
                <p>For any queries please contact us at info@acemovers.com.au or call us at 1300 136 735</p>
            `;

            let invoiceHTML = '';

            if (invoiceType === 'variable') {
                // Generate variable price invoice
                invoiceHTML = `
                    ${commonInvoiceHTML}
                    <!-- Variable Invoice Specific Rows -->
                    <tr class="heading">
                        <td colspan="2">Timing</td>
                    </tr>
                    <tr class="item">
                        <td>Total Work Hours</td>
                        <td><span id="totalLaborTime" contenteditable="true" data-type="number">0.00</span> hours</td>
                    </tr>
                    <tr class="item">
                        <td>Callout Fee</td>
                        <td><span id="calloutFee" contenteditable="true" data-type="number">0.00</span> hour(s)</td>
                    </tr>
                    <tr class="heading">
                        <td colspan="2">Rate</td>
                    </tr>
                    <tr class="item">
                        <td>Per Hour Rate</td>
                        <td>$<span id="rate" contenteditable="true" data-type="number">0.00</span></td>
                    </tr>
                    <tr class="item">
                        <td>SubTotal</td>
                        <td>$<span id="subTotal">0.00</span></td>
                    </tr>
                    <tr class="item">
                        <td>GST Included</td>
                        <td>
                            <select id="gstIncluded" data-type="select">
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="item">
                        <td>GST Amount</td>
                        <td>$<span id="gstAmount">0.00</span></td>
                    </tr>
                    ${additionalChargesHTML}
                    ${commonFooterHTML}
                `;
            } else if (invoiceType === 'fixed') {
                // Generate fixed price invoice
                invoiceHTML = `
                    ${commonInvoiceHTML}
                    <!-- Fixed Invoice Specific Rows -->
                    <tr class="heading">
                        <td colspan="2">Charges</td>
                    </tr>
                    <tr class="item">
                        <td>Total Initial Charge</td>
                        <td>$<span id="totalInitialCharge" contenteditable="true" data-type="number">0.00</span></td>
                    </tr>
                    <tr class="item">
                        <td>SubTotal</td>
                        <td>$<span id="subTotal">0.00</span></td>
                    </tr>
                    <tr class="item">
                        <td>GST Included</td>
                        <td>
                            <select id="gstIncluded" data-type="select">
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="item">
                        <td>GST Amount</td>
                        <td>$<span id="gstAmount">0.00</span></td>
                    </tr>
                    ${additionalChargesHTML}
                    ${commonFooterHTML}
                `;
            }

            document.getElementById('invoice-preview').innerHTML = invoiceHTML;
            makeFieldsEditable();
            toggleChargeVisibility();
            recalculateTotals();

            // Show or hide the 'Include Miscellaneous Charge' checkbox based on invoice type
            const toggleMiscChargeContainer = document.getElementById('toggleMiscChargeContainer');
            const toggleMiscCharge = document.getElementById('toggleMiscCharge');
            if (invoiceType === 'fixed') {
                toggleMiscChargeContainer.classList.remove('hidden');
            } else {
                toggleMiscChargeContainer.classList.add('hidden');
                toggleMiscCharge.checked = false;
            }

            // Attach event listener for payment status stamp
            const paymentStatus = document.getElementById('paymentStatus');
            paymentStatus.addEventListener('click', function () {
                if (this.innerText === 'PAID') {
                    this.innerText = 'UNPAID';
                    this.classList.remove('PAID');
                    this.classList.add('UNPAID');
                } else {
                    this.innerText = 'PAID';
                    this.classList.remove('UNPAID');
                    this.classList.add('PAID');
                }
            });
        }

        function attachEventListeners() {
            // For contenteditable elements
            const editableFields = document.querySelectorAll('#invoice-preview [contenteditable="true"]');
            editableFields.forEach(field => {
                field.addEventListener('input', recalculateTotals);
                field.addEventListener('keydown', function (e) {
                    // Prevent entering invalid characters in number fields
                    const dataType = this.getAttribute('data-type');
                    if (dataType === 'number') {
                        if (!isAllowedNumberKey(e)) {
                            e.preventDefault();
                        }
                    }
                });
            });

            // For select elements
            const gstIncludedSelect = document.getElementById('gstIncluded');
            gstIncludedSelect.addEventListener('change', recalculateTotals);

            // For checkboxes
            document.getElementById('toggleStairCharge').addEventListener('change', function () {
                toggleChargeVisibility();
                recalculateTotals();
            });

            document.getElementById('togglePianoCharge').addEventListener('change', function () {
                toggleChargeVisibility();
                recalculateTotals();
            });

            document.getElementById('togglePoolTableCharge').addEventListener('change', function () {
                toggleChargeVisibility();
                recalculateTotals();
            });

            // Add this event listener for the card surcharge checkbox
            document.getElementById('toggleCardSurcharge').addEventListener('change', recalculateTotals);

            // Event listener for the miscellaneous charge checkbox
            document.getElementById('toggleMiscCharge').addEventListener('change', function () {
                toggleChargeVisibility();
                recalculateTotals();
            });
        }

        function toggleChargeVisibility() {
            const stairChargeRow = document.getElementById('stairChargeRow');
            const pianoChargeRow = document.getElementById('pianoChargeRow');
            const poolTableChargeRow = document.getElementById('poolTableChargeRow');
            const miscChargeRow = document.getElementById('miscChargeRow');

            const toggleStairCharge = document.getElementById('toggleStairCharge').checked;
            const togglePianoCharge = document.getElementById('togglePianoCharge').checked;
            const togglePoolTableCharge = document.getElementById('togglePoolTableCharge').checked;
            const toggleMiscCharge = document.getElementById('toggleMiscCharge').checked;

            stairChargeRow.classList.toggle('hidden', !toggleStairCharge);
            pianoChargeRow.classList.toggle('hidden', !togglePianoCharge);
            poolTableChargeRow.classList.toggle('hidden', !togglePoolTableCharge);

            if (miscChargeRow) {
                miscChargeRow.classList.toggle('hidden', !toggleMiscCharge);
            }

            // Control the display of the additional charges section header
            const additionalChargesSection = document.getElementById('additionalChargesSection');
            if (toggleStairCharge || togglePianoCharge || togglePoolTableCharge || toggleMiscCharge) {
                additionalChargesSection.classList.remove('hidden');
            } else {
                additionalChargesSection.classList.add('hidden');
            }

            // Adjust invoice layout to fit one page if no additional charges
            adjustInvoiceLayout();
        }

        function adjustInvoiceLayout() {
            const additionalChargesSection = document.getElementById('additionalChargesSection');
            const invoiceBox = document.getElementById('invoice-preview');

            if (additionalChargesSection.classList.contains('hidden')) {
                // Adjust styles to fit one page
                invoiceBox.style.padding = '20px';
                invoiceBox.style.maxHeight = 'none';
            } else {
                // Reset styles if additional charges are included
                invoiceBox.style.padding = '20px';
                invoiceBox.style.maxHeight = 'none';
            }
        }

        function recalculateTotals() {
            const invoiceType = document.getElementById('invoiceTypeSelect').value;

            if (invoiceType === 'variable') {
                // Variable price invoice calculations
                // Retrieve values from the invoice
                const totalLaborTime = parseFloat(document.getElementById('totalLaborTime').innerText) || 0;
                const calloutFee = parseFloat(document.getElementById('calloutFee').innerText) || 0;
                const rate = parseFloat(document.getElementById('rate').innerText) || 0;
                const gstIncluded = isGSTIncluded(document.getElementById('gstIncluded').value);
                const deposit = parseFloat(document.getElementById('deposit').innerText) || 0;

                // Additional charges
                const stairCharges = getAdditionalCharge('stairCharges', 'toggleStairCharge');
                const pianoCharge = getAdditionalCharge('pianoCharge', 'togglePianoCharge');
                const poolTableCharge = getAdditionalCharge('poolTableCharge', 'togglePoolTableCharge');

                // Perform calculations
                const subTotal = (totalLaborTime + calloutFee + pianoCharge + poolTableCharge) * rate;
                const gstAmount = gstIncluded ? subTotal * 0.10 : 0;
                const stairChargesGST = gstIncluded ? stairCharges * 1.10 : stairCharges;
                const totalChargeBeforeCardSurcharge = subTotal + gstAmount + stairChargesGST - deposit;

                // Calculate card surcharge if applicable
                const totalCharge = calculateTotalWithCardSurcharge(totalChargeBeforeCardSurcharge, deposit);

                // Update the invoice display
                updateInvoiceDisplay(subTotal, gstAmount, totalCharge);

            } else if (invoiceType === 'fixed') {
                // Fixed price invoice calculations
                // Retrieve values from the invoice
                const totalInitialCharge = parseFloat(document.getElementById('totalInitialCharge').innerText) || 0;
                const gstIncluded = isGSTIncluded(document.getElementById('gstIncluded').value);
                const deposit = parseFloat(document.getElementById('deposit').innerText) || 0;

                // Additional charges
                const stairCharges = getAdditionalCharge('stairCharges', 'toggleStairCharge');
                const pianoChargeAmount = getAdditionalCharge('pianoCharge', 'togglePianoCharge');
                const poolTableChargeAmount = getAdditionalCharge('poolTableCharge', 'togglePoolTableCharge');

                // Miscellaneous charge
                const toggleMiscCharge = document.getElementById('toggleMiscCharge').checked;
                const miscChargeAmount = toggleMiscCharge ? (parseFloat(document.getElementById('miscChargeAmount').innerText) || 0) : 0;

                // Perform calculations
                const subTotal = totalInitialCharge + pianoChargeAmount + poolTableChargeAmount + miscChargeAmount;
                const gstAmount = gstIncluded ? subTotal * 0.10 : 0;
                const stairChargesGST = gstIncluded ? stairCharges * 1.10 : stairCharges;
                const totalChargeBeforeCardSurcharge = subTotal + gstAmount - deposit + stairChargesGST;

                // Calculate card surcharge if applicable
                const totalCharge = calculateTotalWithCardSurcharge(totalChargeBeforeCardSurcharge, deposit);

                // Update the invoice display
                updateInvoiceDisplay(subTotal, gstAmount, totalCharge);
            }
        }

        function getAdditionalCharge(fieldId, toggleId) {
            const isToggled = document.getElementById(toggleId).checked;
            return isToggled ? (parseFloat(document.getElementById(fieldId).innerText) || 0) : 0;
        }

        function calculateTotalWithCardSurcharge(totalChargeBeforeCardSurcharge, deposit) {
            const toggleCardSurcharge = document.getElementById('toggleCardSurcharge').checked;
            let cardSurchargeAmount = 0;
            let totalCharge = totalChargeBeforeCardSurcharge;
            if (toggleCardSurcharge) {
                cardSurchargeAmount = ((totalChargeBeforeCardSurcharge + deposit) * 0.02);
                totalCharge += cardSurchargeAmount;
            }

            // Show or hide the card surcharge row using the 'hidden' class
            const cardSurchargeRow = document.getElementById('cardSurchargeRow');
            if (toggleCardSurcharge) {
                document.getElementById('cardSurchargeAmount').innerText = cardSurchargeAmount.toFixed(2);
                cardSurchargeRow.classList.remove('hidden'); // Remove 'hidden' to show the row
            } else {
                cardSurchargeRow.classList.add('hidden'); // Add 'hidden' to hide the row
            }
            return totalCharge;
        }

        function updateInvoiceDisplay(subTotal, gstAmount, totalCharge) {
            document.getElementById('subTotal').innerText = subTotal.toFixed(2);
            document.getElementById('gstAmount').innerText = gstAmount.toFixed(2);
            document.getElementById('totalCharge').innerText = totalCharge.toFixed(2);
        }

        function downloadInvoice() {
            // Before generating the PDF, ensure the invoice reflects the current state
            recalculateTotals();

            const element = document.getElementById('invoice-preview');
            // Clone the element to avoid changes during PDF generation
            const clonedElement = element.cloneNode(true);

            // Remove contenteditable attributes
            clonedElement.querySelectorAll('[contenteditable="true"]').forEach(el => {
                el.removeAttribute('contenteditable');
            });

            // Replace select element with text
            const gstIncludedSelect = clonedElement.querySelector('#gstIncluded');
            const gstIncludedText = document.createElement('span');
            gstIncludedText.innerText = gstIncludedSelect.value;
            gstIncludedSelect.parentNode.replaceChild(gstIncludedText, gstIncludedSelect);

            // Preserve display: none styles
            clonedElement.querySelectorAll('[style]').forEach(el => {
                const style = el.getAttribute('style');
                if (style) {
                    const styleRules = style.split(';').map(rule => rule.trim()).filter(rule => rule);
                    const filteredRules = styleRules.filter(rule => rule.startsWith('display: none'));
                    if (filteredRules.length > 0) {
                        el.setAttribute('style', filteredRules.join(';') + ';');
                    } else {
                        el.removeAttribute('style');
                    }
                }
            });

            // Get client name for the filename
            let clientName = document.getElementById('clientName').innerText.trim();
            if (!clientName) {
                clientName = 'invoice';
            }
            // Sanitize client name to remove invalid filename characters
            clientName = clientName.replace(/[<>:"\/\\|?*]+/g, '').replace(/\s+/g, '_');

            // Set options for html2pdf
            var opt = {
                margin: [10, 10, 10, 10], // top, left, bottom, right
                filename: clientName + '.pdf',
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                }
            };

            // Generate PDF from the cloned element
            html2pdf().set(opt).from(clonedElement).save().then(() => {
                const invoiceName = "Invoice #" + nextInvoiceID;

                // Send data to the server to save the invoice record
                fetch('createInvoice.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `invoiceID=${nextInvoiceID}&invoiceName=${encodeURIComponent(invoiceName)}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Invoice saved successfully!');
                        } else {
                            alert('Failed to save the invoice.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            });
        }

        function sendInvoiceEmail() {
            // Before generating the PDF, ensure the invoice reflects the current state
            recalculateTotals();

            const element = document.getElementById('invoice-preview');
            // Clone the element to avoid changes during PDF generation
            const clonedElement = element.cloneNode(true);

            // Remove contenteditable attributes
            clonedElement.querySelectorAll('[contenteditable="true"]').forEach(el => {
                el.removeAttribute('contenteditable');
            });

            // Replace select element with text
            const gstIncludedSelect = clonedElement.querySelector('#gstIncluded');
            const gstIncludedText = document.createElement('span');
            gstIncludedText.innerText = gstIncludedSelect.value;
            gstIncludedSelect.parentNode.replaceChild(gstIncludedText, gstIncludedSelect);

            // Preserve display: none styles
            clonedElement.querySelectorAll('[style]').forEach(el => {
                const style = el.getAttribute('style');
                if (style) {
                    const styleRules = style.split(';').map(rule => rule.trim()).filter(rule => rule);
                    const filteredRules = styleRules.filter(rule => rule.startsWith('display: none'));
                    if (filteredRules.length > 0) {
                        el.setAttribute('style', filteredRules.join(';') + ';');
                    } else {
                        el.removeAttribute('style');
                    }
                }
            });

            // Get client email and name
            let clientEmail = document.getElementById('clientEmail').innerText.trim();
            let clientName = document.getElementById('clientName').innerText.trim();
            let invoiceID = nextInvoiceID;

            if (!clientEmail) {
                alert('Please enter the client email.');
                return;
            }

            // Set options for html2pdf
            var opt = {
                margin: [10, 10, 10, 10], // top, left, bottom, right
                filename: 'invoice.pdf', // filename is not important here
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                }
            };

            // Disable the button to prevent multiple clicks
            const sendEmailButton = document.querySelector('button[onclick="sendInvoiceEmail()"]');
            sendEmailButton.disabled = true;
            sendEmailButton.innerText = 'Sending...';

            // Generate PDF from the cloned element and get the Blob
            html2pdf().set(opt).from(clonedElement).outputPdf('blob').then(function (pdfBlob) {
                // Read the Blob as base64
                var reader = new FileReader();
                reader.readAsDataURL(pdfBlob);
                reader.onloadend = function () {
                    var base64data = reader.result;
                    // Remove the data URL prefix to get just the base64 string
                    base64data = base64data.split(',')[1];

                    // Prepare data to send
                    var formData = new FormData();
                    formData.append('action', 'sendEmail');
                    formData.append('pdfData', base64data);
                    formData.append('clientEmail', clientEmail);
                    formData.append('clientName', clientName);
                    formData.append('invoiceID', invoiceID);

                    // Send the data via AJAX POST to the server
                    fetch('createInvoice.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(result => {
                            sendEmailButton.disabled = false;
                            sendEmailButton.innerText = 'Send Email';

                            if (result.success) {
                                alert('Email sent successfully!');
                            } else {
                                alert('Failed to send email: ' + result.error);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            sendEmailButton.disabled = false;
                            sendEmailButton.innerText = 'Send Email';
                            alert('An error occurred while sending email.');
                        });
                };
            });
        }

        function isAllowedNumberKey(e) {
            // Allow: backspace, delete, tab, escape, enter, and .
            if ([46, 8, 9, 27, 13, 110, 190].includes(e.keyCode) ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.ctrlKey === true && [65, 67, 86, 88].includes(e.keyCode)) ||
                // Allow: home, end, left, right, down, up
                (e.keyCode >= 35 && e.keyCode <= 40)) {
                return true;
            }
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) &&
                (e.keyCode < 96 || e.keyCode > 105)) {
                return false;
            }
            return true;
        }

        function makeFieldsEditable() {
            const fields = document.querySelectorAll('#invoice-preview [contenteditable="true"]');
            fields.forEach(field => {
                field.setAttribute('contenteditable', 'true');
                field.addEventListener('input', recalculateTotals);
            });

            // Attach change event for GST select
            const gstIncludedSelect = document.getElementById('gstIncluded');
            if (gstIncludedSelect) {
                gstIncludedSelect.addEventListener('change', recalculateTotals);
            }
        }
    </script>
</body>

</html>