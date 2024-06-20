<?php
session_start();
// Include db.php for database connection
require 'db.php';
require_once './dompdf-3.0.0/dompdf/autoload.inc.php'; // Adjust the path if needed
require_once 'PHPMailer-master/src/PHPMailer.php'; // Adjust the path 
require_once 'PHPMailer-master/src/SMTP.php'; // Adjust the path 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use Dompdf\Options;
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
        $gstPercentage = ($row['GST'] == 1) ? '10%' : '0%';
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

// Create Invoice HTML Content (based on your provided structure)
$invoiceHtml = '
<div class="invoice-box">
    <table>
        <tr class="top">
            <td colspan="2">
                <table>
                    <tr>
                        <td class="title">
                            <img src="https://i.postimg.cc/sfp6rLGY/cropped-200x76-1-161x86.png" alt="House moving logo" />
                        </td>
                        <td class="invoice-details">
                            <b>INVOICE</b><br />
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
                            Client Name: ' . htmlspecialchars($jobDetails['BookingName']) . '<br />
                            Email: ' . htmlspecialchars($jobDetails['Email']) . '
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
            <td>' . htmlspecialchars($jobDetails['TotalLaborTime']) . ' hours</td>
        </tr>
        <tr class="item">
            <td>Callout Fee</td>
            <td>' . htmlspecialchars($jobDetails['CalloutFee']) . ' hour/s</td>
        </tr>
        <tr class="heading">
            <td colspan="2">Rate</td>
        </tr>
        <tr class="item">
            <td>Per Hour Rate</td>
            <td>$' . htmlspecialchars($jobDetails['Rate']) . '</td>
        </tr>
        <tr class="item">
            <td>SubTotal</td>
            <td>$' . htmlspecialchars($jobDetails['SubTotal']) . '</td>
        </tr>
        <tr class="item">
            <td>GST</td>
            <td>' . $gstPercentage . '</td>
        </tr>
        <tr class="item">
            <td>Surcharge</td>
            <td>$' . number_format($jobDetails['Surcharge'], 2) . '</td>
        </tr>';

if ($hasAdditionalCharges) {
    $invoiceHtml .= '
        <tr class="heading">
            <td colspan="2">Additional Charges</td>
        </tr>';

    if ($jobDetails['StairCharges'] != 0) {
        $invoiceHtml .= '
            <tr class="item">
                <td>Stair Charge</td>
                <td>$' . number_format($jobDetails['StairCharges']) . '</td>
            </tr>';
    }

    if ($jobDetails['PianoCharge'] != 0) {
        $invoiceHtml .= '
            <tr class="item">
                <td>Piano Charge</td>
                <td>' . number_format($jobDetails['PianoCharge']) . 'h</td>
            </tr>';
    }

    if ($jobDetails['PoolTableCharge'] != 0) {
        $invoiceHtml .= '
            <tr class="item">
                <td>Pool Table Charge</td>
                <td>' . number_format($jobDetails['PoolTableCharge']) . 'h</td>
            </tr>';
    }
}

$invoiceHtml .= '
        <tr class="heading">
            <td colspan="2">Deposit</td>
        </tr>
        <tr class="item last">
            <td>Initial Deposit Adjustment</td>
            <td>-$' . htmlspecialchars($jobDetails['Deposit']) . '</td>
        </tr>
        <tr class="total">
            <td></td>
            <td><b>Total: $' . htmlspecialchars($jobDetails['TotalCharge']) . '</b></td>
        </tr>
    </table>
    <p>For any queries please contact us at info@acemovers.com.au or call us at 1300 136 735</p>
</div>';

$invoiceHtml .= '
<style>
body {
    font-family: \'Helvetica Neue\', \'Helvetica\', Helvetica, Arial, sans-serif;
    text-align: center;
    color: #777;
}

body h1 {
    font-weight: 300;
    margin-bottom: 0px;
    padding-bottom: 0px;
    color: #000;
}

body h3 {
    font-weight: 300;
    margin-top: 10px;
    margin-bottom: 20px;
    font-style: italic;
    color: #555;
}

body a {
    color: #06f;
}

.invoice-box {
    max-width: 800px;
    margin: auto;
    padding: 30px;
    border: 1px solid #eee;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
    font-size: 16px;
    line-height: 24px;
    font-family: \'Helvetica Neue\', \'Helvetica\', Helvetica, Arial, sans-serif;
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
    font-size: 45px;
    line-height: 45px;
    color: #333;
}

.invoice-box table tr.information table td {
    padding-bottom: 40px;
}

.invoice-box table tr.heading td {
    background: #eee;
    border-bottom: 1px solid #ddd;
    font-weight: bold;
}

.invoice-box table tr.details td {
    padding-bottom: 20px;
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

@media only screen and (max-width: 600px) {
    .invoice-box table tr.top table td {
        width: 100%;
        display: block;
        text-align: center;
    }

    .invoice-box table tr.information table td {
        width: 100%;
        display: block;
        text-align: center;
    }
}
</style>';

// Function to delete all PDF files in the Invoices directory
function clearInvoicesFolder($directory)
{
    // Ensure the directory exists
    if (is_dir($directory)) {
        // Get all files in the directory
        $files = glob($directory . '/*.pdf');

        // Loop through the files and delete each one
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    } else {
        echo "Directory does not exist.";
    }
}

$invoicesDirectory = './Invoices';

// Create Dompdf Options
$options = new Options();
$options->set('isRemoteEnabled', true); // Enable remote image fetching
$options->set('defaultFont', 'Arial'); // Set your desired font

// Initialize Dompdf
$dompdf = new Dompdf($options);

// Load HTML content into Dompdf
$dompdf->loadHtml($invoiceHtml);

// (Optional) Set paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render the HTML as PDF
$dompdf->render();

// Output the generated PDF to a file
$pdfOutput = $dompdf->output();


// Generate the file name for the PDF
$bookingID = $jobDetails['BookingID'];
$bookingName = $jobDetails['BookingName'];
$email = $jobDetails['Email'];

// Replace spaces with underscores and sanitize the strings to avoid issues with file names
$bookingName = str_replace(' ', '_', $bookingName);
$email = str_replace(' ', '_', $email);
$email = preg_replace('/[^A-Za-z0-9\_\-\.]/', '', $email);

$pdfFilePath = "./Invoices/invoice_{$bookingID}_{$bookingName}_{$email}.pdf";

file_put_contents($pdfFilePath, $pdfOutput);

// Prepare PHPMailer
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.elasticemail.com'; // Set the SMTP server to send through
    $mail->SMTPAuth = true;
    $mail->Username = 'aaron@acemovers.com.au'; // SMTP username
    $mail->Password = '8F1E23DEE343B60A0336456A6944E7B4F7DA';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('aaron@acemovers.com.au', 'Ace Movers');
    $email = $jobDetails['Email'];
    $mail->addAddress($email); // Add a recipient

    // Attach the PDF file
    $mail->addAttachment($pdfFilePath);

    // Content
    $mail->isHTML(true); // Set email format to HTML
    $mail->Subject = 'Invoice ACE MOVERS';
    $mail->Body = 'Please find the attached invoice.'; // Adjust the email body as needed

    $mail->send();
    echo 'Message has been sent';
    clearInvoicesFolder($invoicesDirectory);

} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}

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
