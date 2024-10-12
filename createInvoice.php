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
    $latestInvoiceID = $row['latestInvoiceID'] + 1;
}

echo "<script>const nextInvoiceID = $latestInvoiceID;</script>";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Create Invoice</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="keep-session-alive.js"></script>
    <style>
        .invoice-container {
            overflow-y: auto;
            background-color: #f5f5f5;
            /* Slightly greyer background */
            padding: 20px;
            border-radius: 5px;
        }

        .invoice-box {
            max-width: 100%;
            margin: 15px auto;
            padding: 20px;
            background-color: #ffffff;
            border: 1px solid #ddd;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            font-size: 15px;
            line-height: 1.6;
            font-family: Arial, sans-serif;
            color: #333;
            /* Darker text */
            border-radius: 5px;
        }

        .invoice-box table {
            width: 100%;
            border-collapse: collapse;
            line-height: inherit;
            text-align: left;
        }

        .invoice-box table td {
            padding: 8px;
            vertical-align: top;
        }

        .invoice-box table tr td:nth-child(2) {
            text-align: right;
        }

        .invoice-box table tr.top table td {
            padding-bottom: 25px;
        }

        .invoice-box table tr.top table td.title {
            font-size: 22px;
            font-weight: bold;
            color: #333;
        }

        .top table {
            width: 100%;
        }

        .top td {
            vertical-align: middle;
            text-align: left;
        }

        .top .title img {
            max-width: 80px;
            /* Adjusted for a more proportional look */
            height: auto;
        }

        .top .invoice-details {
            font-size: 16px;
            text-align: right;
            color: #555;
        }

        .invoice-box table tr.heading td {
            background: #f0f0f0;
            /* Softer gray */
            border-bottom: 1px solid #ccc;
            font-weight: bold;
            color: #333;
            padding: 10px;
        }

        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
            color: #555;
        }

        .invoice-box table tr.item.last td {
            border-bottom: none;
        }

        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #ddd;
            font-weight: bold;
            color: #333;
            font-size: 16px;
        }

        .invoice-box p {
            font-size: 13px;
            color: #666;
            margin-top: 10px;
            line-height: 1.5;
        }

        .invoice-box table tr.information table td {
            padding-bottom: 10px;
        }

        .invoice-box table tr.information td {
            color: #333;
        }
    </style>
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
</head>

<body>

    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0">
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2" id="Main-Heading">Create Invoice</h1>
                </div>
                <!-- Dashboard content goes here -->
                <div class="container">
                    <div class="row">
                        <!-- Input Fields Container -->
                        <div class="col-md-6">
                            <form id="invoice-form">
                                <div class="form-group">
                                    <label for="clientName">Client Name:</label>
                                    <input type="text" class="form-control" id="clientName" name="clientName">
                                </div>
                                <div class="form-group">
                                    <label for="clientEmail">Email:</label>
                                    <input type="email" class="form-control" id="clientEmail" name="clientEmail">
                                </div>
                                <div class="form-group">
                                    <label for="totalLaborTime">Total Work Hours:</label>
                                    <input type="number" step="0.01" class="form-control" id="totalLaborTime" name="totalLaborTime">
                                </div>
                                <div class="form-group">
                                    <label for="calloutFee">Callout Fee (hours):</label>
                                    <input type="number" step="0.01" class="form-control" id="calloutFee" name="calloutFee">
                                </div>
                                <div class="form-group">
                                    <label for="rate">Per Hour Rate:</label>
                                    <input type="number" step="0.01" class="form-control" id="rate" name="rate">
                                </div>
                                <div class="form-group">
                                    <label for="gst">GST Included:</label>
                                    <select class="form-control" id="gst" name="gst">
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="stairCharges">Stair Charge:</label>
                                    <input type="number" step="0.01" class="form-control" id="stairCharges" name="stairCharges">
                                </div>
                                <div class="form-group">
                                    <label for="pianoCharge">Piano Charge:</label>
                                    <input type="number" step="0.01" class="form-control" id="pianoCharge" name="pianoCharge">
                                </div>
                                <div class="form-group">
                                    <label for="poolTableCharge">Pool Table Charge:</label>
                                    <input type="number" step="0.01" class="form-control" id="poolTableCharge" name="poolTableCharge">
                                </div>
                                <div class="form-group">
                                    <label for="deposit">Initial Deposit:</label>
                                    <input type="number" step="0.01" class="form-control" id="deposit" name="deposit">
                                </div>
                                <button type="button" class="btn btn-primary" onclick="generateInvoice()">Generate Invoice</button>
                            </form>
                        </div>

                        <!-- Invoice Container -->
                        <div class="col-md-6">
                            <div id="invoice-preview" class="invoice-box">
                                <!-- Invoice content will be populated here by JavaScript -->
                            </div>
                            <button type="button" class="btn btn-success mt-3" onclick="downloadInvoice()">Download Invoice</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function calculateSubTotal(totalLaborTime, pianoCharge, poolTableCharge, rate, calloutFee) {
            return (totalLaborTime + pianoCharge + poolTableCharge + calloutFee) * rate;
        }

        function isGSTIncluded(gstValue) {
            return gstValue == 1;
        }

        function generateInvoice() {
            const form = document.getElementById('invoice-form');
            const formData = new FormData(form);

            const clientName = formData.get('clientName');
            const clientEmail = formData.get('clientEmail');
            const totalLaborTime = parseFloat(formData.get('totalLaborTime'));
            const calloutFee = parseFloat(formData.get('calloutFee'));
            const rate = parseFloat(formData.get('rate'));
            const gstIncluded = isGSTIncluded(formData.get('gst'));
            const stairCharges = parseFloat(formData.get('stairCharges')) || 0;
            const pianoCharge = parseFloat(formData.get('pianoCharge')) || 0;
            const poolTableCharge = parseFloat(formData.get('poolTableCharge')) || 0;
            const deposit = parseFloat(formData.get('deposit')) || 0;

            const subTotal = calculateSubTotal(totalLaborTime, pianoCharge, poolTableCharge, rate, calloutFee);
            const gstPercentage = gstIncluded ? '10%' : '0%';
            const surcharge = gstIncluded ? subTotal * 0.10 : 0;
            const hasAdditionalCharges = stairCharges !== 0 || pianoCharge !== 0 || poolTableCharge !== 0;
            const totalCharge = subTotal + surcharge - deposit + stairCharges;

            const invoiceHTML = `
                <table>
                    <tr class="top">
                        <td colspan="2">
                            <table>
                                <tr>
                                    <td class="title">
                                        <img src="https://portal.alphamovers.com.au/logo.png" alt="House moving logo" />
                                    </td>
                                    ${gstIncluded ? 
                                        `<td class="invoice-details">
                                            <b>INVOICE #${nextInvoiceID}</b><br />
                                            Moving Service
                                        </td>` : 
                                        `<td class="invoice-details">
                                            <b>Payment Overview</b><br />
                                            Moving Service
                                        </td>`
                                    }
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
                                        Client Name: ${clientName}<br />
                                        Email: ${clientEmail}
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
                        <td>${totalLaborTime} hours</td>
                    </tr>
                    <tr class="item">
                        <td>Callout Fee</td>
                        <td>${calloutFee} hour/s</td>
                    </tr>
                    <tr class="heading">
                        <td colspan="2">Rate</td>
                    </tr>
                    <tr class="item">
                        <td>Per Hour Rate</td>
                        <td>$${rate.toFixed(2)}</td>
                    </tr>
                    <tr class="item">
                        <td>SubTotal</td>
                        <td>$${subTotal.toFixed(2)}</td>
                    </tr>
                    <tr class="item">
                        <td>GST</td>
                        <td>${gstPercentage}</td>
                    </tr>
                    <tr class="item">
                        <td>Surcharge</td>
                        <td>$${surcharge.toFixed(2)}</td>
                    </tr>
                    ${hasAdditionalCharges ? `
                        <tr class="heading">
                            <td colspan="2">Additional Charges</td>
                        </tr>
                        ${stairCharges !== 0 ? `
                            <tr class="item">
                                <td>Stair Charge</td>
                                <td>$${stairCharges.toFixed(2)}</td>
                            </tr>` : ''}
                        ${pianoCharge !== 0 ? `
                            <tr class="item">
                                <td>Piano Charge</td>
                                <td>${pianoCharge.toFixed(2)} hours</td>
                            </tr>` : ''}
                        ${poolTableCharge !== 0 ? `
                            <tr class="item">
                                <td>Pool Table Charge</td>
                                <td>${poolTableCharge.toFixed(2)} hours</td>
                            </tr>` : ''}
                    ` : ''}
                    <tr class="heading">
                        <td colspan="2">Deposit</td>
                    </tr>
                    <tr class="item last">
                        <td>Initial Deposit Adjustment</td>
                        <td>-$${deposit.toFixed(2)}</td>
                    </tr>
                    <tr class="total">
                        <td></td>
                        <td><b>Total: $${totalCharge.toFixed(2)}</b></td>
                    </tr>
                </table>
                <p>For any queries please contact us at info@acemovers.com.au or call us at 1300 136 735</p>
            `;

            document.getElementById('invoice-preview').innerHTML = invoiceHTML;
        }

        // Save the invoice name upon download
        function downloadInvoice() {
            const element = document.getElementById('invoice-preview');
            html2pdf().from(element).save('invoice.pdf').then(() => {
                const invoiceName = "Invoice #" + nextInvoiceID;

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
    </script>

</body>

</html>