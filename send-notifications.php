<?php
require_once 'db.php'; // Include your database connection
require_once 'PHPMailer-master/src/PHPMailer.php'; // Adjust the path 
require_once 'PHPMailer-master/src/SMTP.php'; // Adjust the path 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    // Prepare and execute statement...
    // Assume $jobDetails contains all the fetched data

    // Prepare PHPMailer
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.elasticemail.com'; // Set the SMTP server to send through
        $mail->SMTPAuth   = true;
        $mail->Username   = 'aaron@acemovers.com.au'; // SMTP username
        $mail->Password = '8F1E23DEE343B60A0336456A6944E7B4F7DA';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        //Recipients
        $mail->setFrom('aaron@acemovers.com.au', 'Ace Movers');
        $employeeEmails = explode(', ', $jobDetails['EmployeeEmails']);
        foreach ($employeeEmails as $email) {
            $mail->addAddress($email); // Add a recipient
        }

        // Content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = 'Job Assignment Notification';
        // Construct email body
        $mail->Body    = <<<EOT
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

        $mail->send();
        echo 'Message has been sent';
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}
