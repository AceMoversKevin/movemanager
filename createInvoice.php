<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin' || $_SESSION['role'] != 'SuperAdmin') {
    header("Location: login.php");
    exit;
}

// Include any necessary PHP code for handling backend logic
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .invoice-container {
            overflow-y: auto;
            /* Enable scrolling for content overflow */
        }

        .invoice-box {
            max-width: 100%;
            margin: 15px;
            padding: 10px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            font-size: 14px;
            line-height: 1.6;
            font-family: sans-serif;
            color: #555;
        }

        .invoice-box table {
            width: 100%;
            line-height: inherit;
            text-align: left;
            border-collapse: collapse;
            /* Remove default table spacing */
        }

        .invoice-box table td {
            padding: 5px;
            vertical-align: top;
        }

        .invoice-box table tr td:nth-child(2) {
            text-align: right;
        }

        .invoice-box table tr.top table td {
            padding-bottom: 20px;
        }

        .invoice-box table tr.top table td.title {
            font-size: 20px;
            line-height: 1.2;
        }

        .top table {
            width: 100%;
        }

        .top td {
            vertical-align: middle;
            text-align: left;
        }

        .top .title img {
            max-width: 100%;
            height: auto;
        }

        .top .title {
            width: 20%;
            /* Adjust as needed */
            display: inline-block;
            vertical-align: middle;
        }

        .top .invoice-details {
            width: 80%;
            /* Adjust as needed */
            display: inline-block;
            vertical-align: middle;
        }

        .invoice-box table tr.top table td.title img {
            width: 100px;
            /* Adjust logo size as needed */
            max-width: 100%;
            /* Ensure the logo scales down on small screens */
        }

        .invoice-box table tr.information table td {
            padding-bottom: 20px;
            /* Reduced for better fit */
        }

        .invoice-box table tr.heading td {
            background: #eee;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
        }

        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
        }

        .invoice-box table tr.item.last td {
            border-bottom: none;
        }

        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
        }

        .invoice-box p {
            /* Style for the "For any queries..." paragraph */
            font-size: 12px;
            margin-top: 10px;
        }
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
                                        <option value="0">No</option>
                                        <option value="1">Yes</option>
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
                                        <img src="https://i.postimg.cc/sfp6rLGY/cropped-200x76-1-161x86.png" alt="House moving logo" />
                                    </td>
                                    ${gstIncluded ? 
                                        `<td class="invoice-details">
                                            <b>INVOICE</b><br />
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

        function downloadInvoice() {
            const element = document.getElementById('invoice-preview');
            html2pdf().from(element).save('invoice.pdf');
        }
    </script>




</body>

</html>