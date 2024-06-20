<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Fetch job details from the database
if (isset($_GET['bookingID'])) {
    $bookingID = intval($_GET['bookingID']);

    // Prepare the correct SQL query
    $stmt = $conn->prepare("
        SELECT 
            b.BookingID, b.Name AS BookingName, b.Email, b.Phone, b.Bedrooms, b.BookingDate, 
            b.MovingDate, b.PickupLocation, b.DropoffLocation, b.TruckSize, b.CalloutFee, 
            b.Rate, b.Deposit, b.TimeSlot, b.isActive AS BookingActive,
            b.StairCharges, b.PianoCharge, b.PoolTableCharge,  -- Charges from the Bookings table
            jc.TotalCharge, jc.TotalLaborTime, jc.TotalBillableTime, jc.StairCharge AS JobStairCharge,
            jc.PianoCharge AS JobPianoCharge, jc.StartTime, jc.EndTime, jc.Deposit AS JobDeposit, 
            jc.GST, jc.PoolTableCharge AS JobPoolTableCharge
        FROM Bookings b
        JOIN JobCharges jc ON b.BookingID = jc.BookingID
        WHERE b.BookingID = ?
    ");

    $stmt->bind_param("i", $bookingID);
    $stmt->execute();
    $result = $stmt->get_result();

    $jobDetails = [];
    if ($row = $result->fetch_assoc()) {
        $jobDetails = $row;

        $subTotal = calculateSubTotal($row['TotalLaborTime'], $row['Rate'], $row['CalloutFee']);
        $jobDetails['SubTotal'] = $subTotal;
        // Determine GST percentage
        $gstIncluded = isGSTIncluded($row['GST']);
        $gstPercentage = $gstIncluded ? '10%' : '0%';
        // Calculate the surcharge
        $surcharge = ($row['GST'] == 1) ? $subTotal * 0.10 : 0;
        $jobDetails['Surcharge'] = $surcharge;
        // Check if there are any additional charges
        $hasAdditionalCharges = ($row['StairCharges'] != 0 || $row['PianoCharge'] != 0 || $row['PoolTableCharge'] != 0);
    }
    $stmt->close();
} else {
    header("Location: index.php");
    exit;
}

function calculateSubTotal($totalLaborTime, $rate, $calloutfee)
{
    return (($totalLaborTime + $calloutfee) * $rate);
}

function isGSTIncluded($gstValue)
{
    return $gstValue == 1;
}

// Check if the complete button has been pressed
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['completeAltJob'])) {
    // Update the isConfirmed and isActive columns
    $updateStmt = $conn->prepare("
        UPDATE JobTimings jt
        JOIN Bookings b ON jt.BookingID = b.BookingID
        SET jt.isConfirmed = 1, b.isActive = 0
        WHERE b.BookingID = ?
    ");
    $updateStmt->bind_param("i", $bookingID);
    $updateStmt->execute();
    $updateStmt->close();
    header("Location: index.php");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Remote View</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style scoped>
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

        /* Responsive Styles */
        @media only screen and (max-width: 600px) {
            .invoice-box {
                font-size: 12px;
            }

            .invoice-box table tr.top table td,
            .invoice-box table tr.information table td {
                width: 100%;
                display: block;
                text-align: center;
            }

            /* Additional adjustments for smaller screens */
        }

        .payment-options-container {
            margin-top: 20px;
            /* Space the payment container from the invoice */
        }

        .payment-option {
            margin-bottom: 15px;
        }

        .payment-details {
            display: none;
            /* Hide by default */
            margin-top: 10px;
        }

        .payment-option input[type="radio"]:checked+label+.payment-details {
            display: block;
            /* Show when option is selected */
        }

        /* Mock Stripe element (basic styling) */
        #mock-stripe-element {
            border: 1px solid #ccc;
            padding: 10px;
        }

        #mock-stripe-element input[type="text"] {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ccc;
        }
    </style>
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
                    <h1 class="h2" id="Main-Heading">Remote View: Invoice</h1>
                </div>
                <!-- Dashboard content goes here -->
                <div class="invoice-container">
                    <div class="invoice-box">
                        <table>
                            <tr class="top">
                                <td colspan="2">
                                    <table>
                                        <tr>
                                            <td class="title">
                                                <img src="https://i.postimg.cc/sfp6rLGY/cropped-200x76-1-161x86.png" alt="House moving logo" />
                                            </td>
                                            <?php if ($gstIncluded) : ?>
                                                <td class="invoice-details">
                                                    <b>INVOICE</b><br />
                                                    Moving Service
                                                </td>
                                            <?php else : ?>
                                                <td class="invoice-details">
                                                    <b>Payment Overview</b><br />
                                                    Moving Service
                                                </td>
                                            <?php endif; ?>
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
                                                Client Name: <?= htmlspecialchars($jobDetails['BookingName']) ?><br />
                                                Email: <?= htmlspecialchars($jobDetails['Email']) ?>
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
                                <td><?= htmlspecialchars($jobDetails['TotalLaborTime']) ?> hours</td>
                            </tr>
                            <tr class="item">
                                <td>Callout Fee</td>
                                <td><?= htmlspecialchars($jobDetails['CalloutFee']) ?> hour/s</td>
                            </tr>
                            <tr class="heading">
                                <td colspan="2">Rate</td>
                            </tr>
                            <tr class="item">
                                <td>Per Hour Rate</td>
                                <td>$<?= htmlspecialchars($jobDetails['Rate']) ?></td>
                            </tr>
                            <tr class="item">
                                <td>SubTotal</td>
                                <td>$<?= htmlspecialchars($jobDetails['SubTotal']) ?></td>
                            </tr>
                            <tr class="item">
                                <td>GST</td>
                                <td><?php echo $gstPercentage; ?></td>
                            </tr>
                            <tr class="item">
                                <td>Surcharge</td>
                                <td>$<?php echo number_format($jobDetails['Surcharge'], 2); ?></td>
                            </tr>
                            <?php if ($hasAdditionalCharges) : ?>
                                <tr class="heading">
                                    <td colspan="2">Additional Charges</td>
                                </tr>
                                <?php if ($jobDetails['StairCharges'] != 0) : ?>
                                    <tr class="item">
                                        <td>Stair Charge</td>
                                        <td>$<?php echo number_format($jobDetails['StairCharges']); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($jobDetails['PianoCharge'] != 0) : ?>
                                    <tr class="item">
                                        <td>Piano Charge</td>
                                        <td><?php echo number_format($jobDetails['PianoCharge']); ?>h</td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($jobDetails['PoolTableCharge'] != 0) : ?>
                                    <tr class="item">
                                        <td>Pool Table Charge</td>
                                        <td><?php echo number_format($jobDetails['PoolTableCharge']); ?>h</td>
                                    </tr>
                                <?php endif; ?>
                            <?php endif; ?>
                            <tr class="heading">
                                <td colspan="2">Deposit</td>
                            </tr>
                            <tr class="item last">
                                <td>Initial Deposit Adjustment</td>
                                <td>-$<?= htmlspecialchars($jobDetails['Deposit']) ?></td>
                            </tr>
                            <tr class="total">
                                <td></td>
                                <td><b>Total: $<?= htmlspecialchars($jobDetails['TotalCharge']) ?></b></td>
                            </tr>
                        </table>
                        <p>For any queries please contact us at info@acemovers.com.au or call us at 1300 136 735</p>
                    </div>
                </div>

                <div class="options-container">

                    <h3>Payment Options</h3>
                    <?php if ($gstIncluded) : ?>
                        <div class="payment-option">
                            <input type="radio" id="card" name="payment" checked>
                            <label for="card">Card</label>
                            <div class="payment-details">
                                <div id="mock-stripe-element">
                                    Amount
                                    <input type="text" placeholder="$">
                                    Card Number
                                    <input type="text" placeholder="**** **** **** ****"><br>
                                    Expiration
                                    <input type="text" placeholder="MM / YY">
                                    <input type="text" placeholder="CVC">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="payment-option">
                        <input type="radio" id="cash" name="payment">
                        <label for="cash">Cash</label>
                        <div class="payment-details">
                            <p>Instructions for cash payment (expandable)</p>
                        </div>
                    </div>

                    <?php if ($gstIncluded) : ?>
                        <div class="payment-option">
                            <input type="radio" id="bank-transfer" name="payment">
                            <label for="bank-transfer">Bank Transfer</label>
                            <div class="payment-details">
                                <p>Bank transfer details (expandable)</p>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="payment-option">
                            <input type="radio" id="bank-transfer" name="payment">
                            <label for="bank-transfer">PayID</label>
                            <div class="payment-details">
                                <p>Bank transfer details (expandable)</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($gstIncluded) : ?>
                    <div class="generate-pdf-button">
                        <button type="button" name="generatePdfButton" onclick="redirectToInvoice(<?= $jobDetails['BookingID'] ?>)" class="btn btn-success btn-block">Complete</button>
                    </div>
                <?php else : ?>
                    <form method="post">
                        <p>This customer will not recieve an invoice</p>
                        <button type="submit" name="completeAltJob" class="btn btn-warning btn-block">Complete</button>
                    </form>
                <?php endif; ?>
        </div>
        </main>
    </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function redirectToInvoice(bookingID) {
            window.location.href = 'generate_invoice.php?bookingID=' + bookingID;
        }
    </script>


</body>

</html>