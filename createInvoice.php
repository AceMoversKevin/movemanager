<?php
session_start();
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Function to save invoice data if the request is POST and contains the necessary data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoiceID'], $_POST['invoiceName'])) {
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
            padding: 30px;
            background-color: #fff;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            color: #333;
        }

        .invoice-box table {
            width: 100%;
            line-height: 1.6;
            text-align: left;
            border-collapse: collapse;
        }

        .invoice-box table td {
            padding: 8px;
            vertical-align: top;
        }

        .invoice-box table tr td:nth-child(2) {
            text-align: right;
        }

        .invoice-box table tr.top table td.title {
            font-size: 36px;
            color: #333;
        }

        .invoice-box table tr.information table td {
            padding-bottom: 20px;
        }

        .invoice-box table tr.heading td {
            background: #f0f0f0;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            padding: 10px;
        }

        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
            padding: 10px;
        }

        .invoice-box table tr.item.last td {
            border-bottom: none;
        }

        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #f0f0f0;
            font-weight: bold;
            font-size: 18px;
        }

        .invoice-box p {
            font-size: 14px;
            color: #555;
            margin-top: 20px;
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
            font-size: 16px;
            padding: 2px;
            border: none;
            background: transparent;
            outline: none;
            appearance: none;
            text-align-last: right;
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
                    <h1 class="h2" id="Main-Heading">Create Invoice</h1>
                </div>
                <!-- Controls for toggling additional charges -->
                <div class="controls no-print">
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
                </div>
                <!-- Invoice Container -->
                <div id="invoice-preview" class="invoice-box">
                    <!-- Editable Invoice content will be populated here by JavaScript -->
                </div>
                <div class="text-center no-print">
                    <button type="button" class="btn btn-success mt-3" onclick="downloadInvoice()">Download Invoice</button>
                </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            generateInvoice(); // Populate with default values
            attachEventListeners(); // Attach event listeners to the checkboxes
        });

        function calculateSubTotal(totalLaborTime, pianoCharge, poolTableCharge, rate, calloutFee) {
            return (totalLaborTime + pianoCharge + poolTableCharge + calloutFee) * rate;
        }

        function isGSTIncluded(gstValue) {
            return gstValue.trim().toLowerCase() === 'yes';
        }

        function generateInvoice() {
            const defaultValues = {
                clientName: 'Client Name',
                clientEmail: 'Email',
                totalLaborTime: '0.00',
                calloutFee: '0.00',
                rate: '0.00',
                gstIncluded: 'Yes',
                stairCharges: '0.00',
                pianoCharge: '0.00',
                poolTableCharge: '0.00',
                deposit: '0.00'
            };

            const invoiceHTML = `
                <table>
                    <tr class="top">
                        <td colspan="2">
                            <table>
                                <tr>
                                    <td class="title">
                                        <img src="https://portal.alphamovers.com.au/logo.png" alt="Company logo" style="width:100%; max-width:150px;">
                                    </td>
                                    <td class="invoice-details">
                                        <b>INVOICE #${nextInvoiceID}</b><br />
                                        Moving Service
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
                    <tr class="heading">
                        <td colspan="2">Timing</td>
                    </tr>
                    <tr class="item">
                        <td>Total Work Hours</td>
                        <td><span id="totalLaborTime" contenteditable="true" data-type="number">${defaultValues.totalLaborTime}</span> hours</td>
                    </tr>
                    <tr class="item">
                        <td>Callout Fee</td>
                        <td><span id="calloutFee" contenteditable="true" data-type="number">${defaultValues.calloutFee}</span> hour(s)</td>
                    </tr>
                    <tr class="heading">
                        <td colspan="2">Rate</td>
                    </tr>
                    <tr class="item">
                        <td>Per Hour Rate</td>
                        <td>$<span id="rate" contenteditable="true" data-type="number">${defaultValues.rate}</span></td>
                    </tr>
                    <tr class="item">
                        <td>SubTotal</td>
                        <td>$<span id="subTotal">0.00</span></td>
                    </tr>
                    <tr class="item">
                        <td>GST Included</td>
                        <td>
                            <select id="gstIncluded" data-type="select">
                                <option value="Yes" ${defaultValues.gstIncluded === 'Yes' ? 'selected' : ''}>Yes</option>
                                <option value="No" ${defaultValues.gstIncluded === 'No' ? 'selected' : ''}>No</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="item">
                        <td>Surcharge</td>
                        <td>$<span id="surcharge">0.00</span></td>
                    </tr>
                    <tbody id="additionalChargesSection">
                        <tr class="heading">
                            <td colspan="2">Additional Charges</td>
                        </tr>
                        <tr class="item" id="stairChargeRow">
                            <td>Stair Charge</td>
                            <td>$<span id="stairCharges" contenteditable="true" data-type="number">${defaultValues.stairCharges}</span></td>
                        </tr>
                        <tr class="item" id="pianoChargeRow">
                            <td>Piano Charge</td>
                            <td><span id="pianoCharge" contenteditable="true" data-type="number">${defaultValues.pianoCharge}</span> hours</td>
                        </tr>
                        <tr class="item" id="poolTableChargeRow">
                            <td>Pool Table Charge</td>
                            <td><span id="poolTableCharge" contenteditable="true" data-type="number">${defaultValues.poolTableCharge}</span> hours</td>
                        </tr>
                    </tbody>
                    <tr class="heading">
                        <td colspan="2">Deposit</td>
                    </tr>
                    <tr class="item last">
                        <td>Initial Deposit Adjustment</td>
                        <td>-$<span id="deposit" contenteditable="true" data-type="number">${defaultValues.deposit}</span></td>
                    </tr>
                    <tr class="total">
                        <td></td>
                        <td>Total: $<span id="totalCharge">0.00</span></td>
                    </tr>
                </table>
                <p>For any queries please contact us at info@acemovers.com.au or call us at 1300 136 735</p>
            `;

            document.getElementById('invoice-preview').innerHTML = invoiceHTML;
            makeFieldsEditable();
            toggleChargeVisibility(); // Add this line to set initial visibility
            recalculateTotals();
        }

        function attachEventListeners() {
            // For contenteditable elements
            const editableFields = document.querySelectorAll('#invoice-preview [contenteditable="true"]');
            editableFields.forEach(field => {
                field.addEventListener('input', recalculateTotals);
                field.addEventListener('keydown', function(e) {
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

            // For checkboxes (now outside the invoice preview)
            document.getElementById('toggleStairCharge').addEventListener('change', function() {
                toggleChargeVisibility();
                recalculateTotals();
            });

            document.getElementById('togglePianoCharge').addEventListener('change', function() {
                toggleChargeVisibility();
                recalculateTotals();
            });

            document.getElementById('togglePoolTableCharge').addEventListener('change', function() {
                toggleChargeVisibility();
                recalculateTotals();
            });
        }

        function toggleChargeVisibility() {
            const stairChargeRow = document.getElementById('stairChargeRow');
            const pianoChargeRow = document.getElementById('pianoChargeRow');
            const poolTableChargeRow = document.getElementById('poolTableChargeRow');

            const toggleStairCharge = document.getElementById('toggleStairCharge').checked;
            const togglePianoCharge = document.getElementById('togglePianoCharge').checked;
            const togglePoolTableCharge = document.getElementById('togglePoolTableCharge').checked;

            stairChargeRow.style.display = toggleStairCharge ? '' : 'none';
            pianoChargeRow.style.display = togglePianoCharge ? '' : 'none';
            poolTableChargeRow.style.display = togglePoolTableCharge ? '' : 'none';

            // Control the display of the additional charges section header
            const additionalChargesSection = document.getElementById('additionalChargesSection');
            if (toggleStairCharge || togglePianoCharge || togglePoolTableCharge) {
                additionalChargesSection.style.display = '';
            } else {
                additionalChargesSection.style.display = 'none';
            }
        }

        function recalculateTotals() {
            // Retrieve values from the invoice
            const totalLaborTime = parseFloat(document.getElementById('totalLaborTime').innerText) || 0;
            const calloutFee = parseFloat(document.getElementById('calloutFee').innerText) || 0;
            const rate = parseFloat(document.getElementById('rate').innerText) || 0;
            const gstIncluded = isGSTIncluded(document.getElementById('gstIncluded').value);
            const deposit = parseFloat(document.getElementById('deposit').innerText) || 0;

            // Additional charges
            const toggleStairCharge = document.getElementById('toggleStairCharge').checked;
            const togglePianoCharge = document.getElementById('togglePianoCharge').checked;
            const togglePoolTableCharge = document.getElementById('togglePoolTableCharge').checked;

            const stairCharges = toggleStairCharge ? (parseFloat(document.getElementById('stairCharges').innerText) || 0) : 0;
            const pianoCharge = togglePianoCharge ? (parseFloat(document.getElementById('pianoCharge').innerText) || 0) : 0;
            const poolTableCharge = togglePoolTableCharge ? (parseFloat(document.getElementById('poolTableCharge').innerText) || 0) : 0;

            // Perform calculations
            const subTotal = calculateSubTotal(totalLaborTime, pianoCharge, poolTableCharge, rate, calloutFee);
            const surcharge = gstIncluded ? subTotal * 0.10 : 0;
            const totalCharge = subTotal + surcharge - deposit + stairCharges;

            // Update the invoice display
            document.getElementById('subTotal').innerText = subTotal.toFixed(2);
            document.getElementById('surcharge').innerText = surcharge.toFixed(2);
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

            // Remove any style attributes added for display purposes
            clonedElement.querySelectorAll('[style]').forEach(el => {
                el.removeAttribute('style');
            });

            // Set options for html2pdf
            var opt = {
                margin: [10, 10, 10, 10], // top, left, bottom, right
                filename: 'invoice.pdf',
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
        }
    </script>
</body>

</html>