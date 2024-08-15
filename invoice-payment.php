<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

$IsCardPayment = isset($_SESSION['IsCardPayment']) ? $_SESSION['IsCardPayment'] : 1;

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

        // Calculate the GST surcharge
        $surcharge = ($row['GST'] == 1) ? $subTotal * 0.10 : 0;
        $jobDetails['Surcharge'] = $surcharge;

        // Calculate card surcharge (2.2% of subtotal)
        $cardSurcharge = $subTotal * 0.022;
        $jobDetails['CardSurcharge'] = $cardSurcharge;

        // Calculate total with card surcharge
        $totalCharge = $jobDetails['TotalCharge'];
        $totalWithCardSurcharge = ceil($totalCharge + $jobDetails['CardSurcharge']);

        $jobDetails['TotalWithCardSurcharge'] = $totalWithCardSurcharge;

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

// Check if the Alt complete button has been pressed
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
            display: inline-block;
            vertical-align: middle;
        }

        .top .invoice-details {
            width: 80%;
            display: inline-block;
            vertical-align: middle;
        }

        .invoice-box table tr.top table td.title img {
            width: 100px;
            max-width: 100%;
        }

        .invoice-box table tr.information table td {
            padding-bottom: 20px;
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
            font-size: 12px;
            margin-top: 10px;
        }

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
        }

        .payment-options-container {
            margin-top: 20px;
        }

        .payment-option {
            margin-bottom: 15px;
        }

        .payment-details {
            display: none;
            margin-top: 10px;
        }

        .payment-option input[type="radio"]:checked+label+.payment-details {
            display: block;
        }

        .continue-to-stripe {
            background-color: purple;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            text-align: center;
            display: block;
            margin-top: 20px;
        }

        .continue-to-stripe:hover {
            background-color: darkviolet;
        }

        .generate-pdf-button {
            margin-top: 20px;
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
                                                ABN:34 640 368 930
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
                                <td>GST Surcharge</td>
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
                                <td>
                                    <b>Total:
                                        <?php if ($IsCardPayment == 1) : ?>
                                            $<?= htmlspecialchars($jobDetails['TotalWithCardSurcharge']) ?>
                                            <span>(includes 2.2% surcharge of $<?= htmlspecialchars(number_format($jobDetails['CardSurcharge'], 2)) ?>)</span>
                                        <?php else : ?>
                                            $<?= htmlspecialchars($jobDetails['TotalCharge']) ?>
                                        <?php endif; ?>
                                    </b>
                                </td>
                            </tr>
                        </table>
                        <p>For any queries please contact us at info@acemovers.com.au or call us at 1300 136 735</p>
                    </div>
                </div>


                <div class="options-container">

                    <h3>Payment Options</h3>
                    <form method="post" id="paymentForm">
                        <input type="hidden" id="isCardPayment" name="isCardPayment" value="1">
                        <?php if ($gstIncluded) : ?>
                            <div class="payment-option">
                                <input type="radio" id="card" name="paymentMethod" value="card" <?= isset($_SESSION['IsCardPayment']) && $_SESSION['IsCardPayment'] == 1 ? 'checked' : '' ?>>
                                <label for="card">Card</label>
                                <div class="payment-details">
                                    <button class="continue-to-stripe" onclick="event.preventDefault(); redirectToStripe('<?= $jobDetails['BookingID'] ?>', '<?= $jobDetails['BookingName'] ?>', '<?= $jobDetails['Email'] ?>', '<?= urlencode($jobDetails['TotalWithCardSurcharge']) ?>')">Continue to Stripe for Payment</button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- <div class="payment-option">
                    <input type="radio" id="cash" name="paymentMethod" value="cash" <?= isset($_SESSION['IsCardPayment']) && $_SESSION['IsCardPayment'] == 0 ? 'checked' : '' ?>>
                    <label for="cash">Cash</label>
                    <div class="payment-details">
                        <p>Please provide the amount of $<?= htmlspecialchars($jobDetails['TotalCharge']) ?></p>
                    </div>
                </div> -->

                        <?php if ($gstIncluded) : ?>
                            <div class="payment-option">
                                <input type="radio" id="bank-transfer" name="paymentMethod" value="bank-transfer" <?= isset($_SESSION['IsCardPayment']) && $_SESSION['IsCardPayment'] == 0 ? 'checked' : '' ?>>
                                <label for="bank-transfer">Bank Transfer</label>
                                <div class="payment-details">
                                    <p>Please transfer over $<?= htmlspecialchars($jobDetails['TotalCharge']) ?> to the following bank account:</p>
                                    <ul>
                                        <li>BSB: 033-686</li>
                                        <li>Account Number: 673226</li>
                                        <li>Account Name: ACE MOVERS</li>
                                    </ul>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="payment-option">
                                <input type="radio" id="bank-transfer" name="paymentMethod" value="bank-transfer" <?= isset($_SESSION['IsCardPayment']) && $_SESSION['IsCardPayment'] == 0 ? 'checked' : '' ?>>
                                <label for="bank-transfer">PayID</label>
                                <div class="payment-details">
                                    <p>Please transfer over $<?= htmlspecialchars($jobDetails['TotalCharge']) ?> to the following bank account:</p>
                                    <ul>
                                        <li>BSB: 033-686</li>
                                        <li>Account Number: 673226</li>
                                        <li>Account Name: ACE MOVERS</li>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!$gstIncluded) : ?>
                            <p>This customer will not receive an invoice</p>
                            <button type="button" id="completeAltJobButton" class="btn btn-warning btn-block">Complete</button>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if ($gstIncluded) : ?>
                    <div class="generate-pdf-button">
                        <button type="button" id="completeCardPayment" class="btn btn-success btn-block" onclick="redirectToInvoiceCard(<?= $jobDetails['BookingID'] ?>)" style="display: none;">Complete Card Payment</button>
                        <button type="button" id="completePayment" class="btn btn-info btn-block" onclick="redirectToInvoice(<?= $jobDetails['BookingID'] ?>)" style="display: none;">Complete Payment</button>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentOptions = document.querySelectorAll('input[name="paymentMethod"]');
            const isCardPaymentInput = document.getElementById('isCardPayment');
            const completeCardPaymentButton = document.getElementById('completeCardPayment');
            const completePaymentButton = document.getElementById('completePayment');
            const completeAltJobButton = document.getElementById('completeAltJobButton');

            function togglePaymentButtons() {
                const selectedPaymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
                if (selectedPaymentMethod === 'card') {
                    completeCardPaymentButton.style.display = 'block';
                    completePaymentButton.style.display = 'none';
                } else {
                    completeCardPaymentButton.style.display = 'none';
                    completePaymentButton.style.display = 'block';
                }
            }

            // Add the event listener for the Complete button
            if (completeAltJobButton) {
                completeAltJobButton.addEventListener('click', function() {
                    // Make AJAX request to trigger the completeAltJob event
                    const xhr = new XMLHttpRequest();
                    xhr.open("POST", window.location.href, true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            console.log('ALT job completed');
                            window.location.href = 'index.php'; // Redirect after completion
                        }
                    };
                    xhr.send("completeAltJob=1"); // Send the completeAltJob request
                });
            }

            paymentOptions.forEach(option => {
                option.addEventListener('change', function() {
                    if (this.id === 'card') {
                        isCardPaymentInput.value = '1';
                    } else {
                        isCardPaymentInput.value = '0';
                    }

                    // Update button visibility immediately
                    togglePaymentButtons();

                    // Make AJAX request to update PHP variable
                    const xhr = new XMLHttpRequest();
                    xhr.open("POST", window.location.href, true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            console.log('PHP variable updated');
                            location.reload(); // Reload the page after the variable is updated
                        }
                    };
                    xhr.send("isCardPayment=" + isCardPaymentInput.value);
                });
            });

            // Initial toggle based on the current value
            togglePaymentButtons();
        });

        function redirectToInvoice(bookingID) {
            window.location.href = 'generate_invoice.php?bookingID=' + bookingID;
        }

        function redirectToInvoiceCard(bookingID) {
            window.location.href = 'generate_invoice_card.php?bookingID=' + bookingID;
        }

        function redirectToStripe(bookingID, bookingName, bookingEmail, totalCharge) {
            const stripeUrl = `https://jn8op1ai150.typeform.com/to/WR3oN0Ej#booking_id=${encodeURIComponent(bookingID)}&email=${encodeURIComponent(bookingEmail)}&name=${encodeURIComponent(bookingName)}&amount=${encodeURIComponent(totalCharge)}`;
            window.open(stripeUrl, '_blank');
        }
    </script>


</body>

</html>