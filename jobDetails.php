<?php
session_start();
require 'db.php';

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/notification_log');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

$bookingID = isset($_GET['BookingID']) ? intval($_GET['BookingID']) : 0;
$jobDetails = [];

if ($bookingID > 0) {
    $query = "SELECT 
        b.BookingID, 
        b.Name AS BookingName, 
        b.Email AS BookingEmail, 
        b.Phone AS BookingPhone, 
        b.Bedrooms, 
        b.BookingDate,
        b.MovingDate,
        b.PickupLocation,
        b.DropoffLocation,
        b.TruckSize,
        b.CalloutFee,
        b.Rate,
        b.Deposit,
        b.TimeSlot,
        b.isActive,
        b.StairCharges,
        b.PianoCharge,
        b.PoolTableCharge AS BookingPoolTableCharge,
        
        -- Employee information
        GROUP_CONCAT(DISTINCT e.Name ORDER BY e.Name SEPARATOR ', ') AS EmployeeNames,
        GROUP_CONCAT(DISTINCT e.Email ORDER BY e.Name SEPARATOR ', ') AS EmployeeEmails,
        
        -- JobTimings information
        MAX(jt.TimingID) AS TimingID,
        MAX(jt.StartTime) AS TimingStartTime,
        MAX(jt.EndTime) AS TimingEndTime,
        MAX(jt.TotalTime) AS TimingTotalTime,
        MAX(jt.isComplete) AS TimingIsComplete,
        MAX(jt.BreakTime) AS TimingBreakTime,
        MAX(jt.isConfirmed) AS TimingIsConfirmed,
        
        -- JobCharges information
        MAX(jc.jobID) AS jobID,
        MAX(jc.TotalCharge) AS JobTotalCharge,
        MAX(jc.TotalLaborTime) AS JobTotalLaborTime,
        MAX(jc.TotalBillableTime) AS JobTotalBillableTime,
        MAX(jc.StairCharge) AS JobStairCharge,
        MAX(jc.PianoCharge) AS JobPianoCharge,
        MAX(jc.StartTime) AS JobStartTime,
        MAX(jc.EndTime) AS JobEndTime,
        MAX(jc.Deposit) AS JobDeposit,
        MAX(jc.GST) AS JobGST,
        MAX(jc.PoolTableCharge) AS JobPoolTableCharge,
        
        -- TripDetails information
        GROUP_CONCAT(DISTINCT CONCAT(td.TripNumber, ': ', td.StartTime, ' - ', td.EndTime) ORDER BY td.TripNumber SEPARATOR ', ') AS TripDetails,

        -- PartialHours information
        GROUP_CONCAT(DISTINCT CONCAT(e.Name, ': ', ph.StartTime, ' - ', ph.EndTime, ' (', ph.PartialHours, ' hrs)') ORDER BY e.Name SEPARATOR ', ') AS PartialHoursDetails
    
    FROM 
        Bookings b
    JOIN 
        BookingAssignments ba ON b.BookingID = ba.BookingID
    JOIN 
        Employees e ON ba.EmployeePhoneNo = e.PhoneNo
    LEFT JOIN 
        JobTimings jt ON b.BookingID = jt.BookingID
    LEFT JOIN 
        JobCharges jc ON b.BookingID = jc.BookingID
    LEFT JOIN 
        TripDetails td ON b.BookingID = td.BookingID
    LEFT JOIN 
        PartialHours ph ON b.BookingID = ph.BookingID AND ph.PhoneNo = e.PhoneNo -- To link employee's partial hours to the booking

    WHERE 
        b.BookingID = ? AND
        b.BookingID NOT IN (SELECT BookingID FROM CompletedJobs)
    
    GROUP BY 
        b.BookingID, 
        b.Name, 
        b.Email, 
        b.Phone, 
        b.Bedrooms, 
        b.BookingDate,
        b.MovingDate,
        b.PickupLocation,
        b.DropoffLocation,
        b.TruckSize,
        b.CalloutFee,
        b.Rate,
        b.Deposit,
        b.TimeSlot,
        b.isActive,
        b.StairCharges,
        b.PianoCharge,
        b.PoolTableCharge;
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $bookingID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $jobDetails = $row;
        $subTotal = calculateSubTotal($row['JobTotalLaborTime'], $row['Rate'], $row['CalloutFee']);
        $jobDetails['SubTotal'] = $subTotal;

        $gstIncluded = isGSTIncluded($row['JobGST']);
        $gstPercentage = $gstIncluded ? '10%' : '0%';

        $surcharge = ($row['JobGST'] == 1) ? $subTotal * 0.10 : 0;
        $jobDetails['Surcharge'] = $surcharge;

        $hasAdditionalCharges = ($row['JobStairCharge'] != 0 || $row['JobPianoCharge'] != 0 || $row['JobPoolTableCharge'] != 0);
    }
    $stmt->close();
} else {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['notifyEmployees'])) {
    $bookingID = $_POST['bookingID'];

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
        b.TimeSlot,  
        GROUP_CONCAT(e.Name ORDER BY e.Name SEPARATOR ', ') AS EmployeeNames,
        GROUP_CONCAT(e.Email ORDER BY e.Name SEPARATOR ', ') AS EmployeeEmails 
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

    $apiKey = 'YOUR_SMTP2GO_API_KEY'; // Replace with your SMTP2GO API key
    $recipientEmails = explode(', ', $jobDetails['EmployeeEmails']);
    $senderEmail = 'aaron@acemovers.com.au';
    $senderName = 'Aaron Miller';
    $subject = 'Job Assignment Notification';

    $body = <<<EOT
<html>
<head>
<title>Job Assignment Notification</title>
</head>
<body>
<h1>Job Assignment</h1>
<p>A job has been assigned to you, please login to check details.  movers.alphamovers.com.au</p>
</body>
</html>
EOT;


    foreach ($recipientEmails as $recipientEmail) {
        $data = [
            'api_key' => $apiKey,
            'to' => [
                $recipientEmail
            ],
            'sender' => $senderEmail,
            'subject' => $subject,
            'html_body' => $body
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.smtp2go.com/v3/email/send');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Smtp2go-Api-Key: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('cURL error: ' . curl_error($ch));
        }
        curl_close($ch);

        $responseData = json_decode($response, true);

        error_log('SMTP2GO API Response: ' . json_encode($responseData));

        if (isset($responseData['data']['succeeded']) && $responseData['data']['succeeded'] > 0) {
            error_log('Message has been sent to ' . $recipientEmail);
        } else {
            error_log('Message was not sent to ' . $recipientEmail . '. Error: ' . ($responseData['data']['errors'][0]['message'] ?? 'Unknown error'));
        }
    }
}

// Handle the send email action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'sendEmail') {
    $pdfData = $_POST['pdfData']; // Base64 encoded PDF
    $clientEmail = $_POST['clientEmail'];
    $clientName = $_POST['clientName'];
    $invoiceID = $_POST['invoiceID'];

    // Decode the PDF data
    $pdfContent = base64_decode($pdfData);

    // Save the PDF to a temporary file
    $pdfFilePath = sys_get_temp_dir() . '/invoice_' . $invoiceID . '.pdf';
    file_put_contents($pdfFilePath, $pdfContent);

    // Send the email using SMTP2GO Email API
    $apiKey = 'YOUR_SMTP2GO_API_KEY'; // Replace with your SMTP2GO API key
    $recipientEmail = $clientEmail;
    $recipientName = $clientName;
    $senderEmail = 'aaron@acemovers.com.au'; // Replace with your sender email
    $senderName = 'Aaron Miller'; // Replace with your sender name
    $subject = "Invoice #$invoiceID";
    $body = "Please find attached the invoice for the payment for your recent booking.";

    $attachment = base64_encode(file_get_contents($pdfFilePath));

    $data = [
        'api_key' => $apiKey,
        'to' => [
            $recipientEmail
        ],
        'cc' => [
            'info@acemovers.com.au' // Add the CC email address here if needed
        ],
        'sender' => $senderEmail,
        'subject' => $subject,
        'text_body' => $body,
        'attachments' => [
            [
                'filename' => 'invoice_' . $invoiceID . '.pdf',
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
        'Content-Type: application/json',
        'X-Smtp2go-Api-Key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);

    // Log the JSON response to the error log
    error_log('SMTP2GO API Response: ' . json_encode($responseData));

    if (isset($responseData['data']['succeeded']) && $responseData['data']['succeeded'] > 0) {
        error_log('Invoice email has been sent.');
        echo json_encode(['success' => true]);
    } else {
        $errorMessage = $responseData['data']['errors'][0]['message'] ?? 'Unknown error';
        error_log('Invoice email was not sent. Error: ' . $errorMessage);
        echo json_encode(['success' => false, 'error' => $errorMessage]);
    }

    // Delete the temporary PDF file
    unlink($pdfFilePath);

    exit; // Terminate the script to prevent further output
}

function calculateSubTotal($totalLaborTime, $rate, $calloutFee)
{
    return ($totalLaborTime + $calloutFee) * $rate;
}

function isGSTIncluded($gstValue)
{
    return $gstValue == 1;
}

$employees = [];
$employeesQuery = "SELECT PhoneNo, Name, EmployeeType FROM Employees WHERE isActive = 1";
$employeesResult = $conn->query($employeesQuery);
if ($employeesResult->num_rows > 0) {
    while ($employee = $employeesResult->fetch_assoc()) {
        $employees[] = $employee;
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
    <title>Job Details</title>
    <!-- Include Bootstrap CSS and other styles -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
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

        /* Additional styles for the page */
        .select-wrapper {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .select-wrapper select {
            flex-grow: 1;
            margin-right: 10px;
        }

        .select-wrapper i {
            cursor: pointer;
            color: red;
        }

        #button-container {
            margin-top: 10px;
        }

        #button-container button {
            margin-right: 10px;
        }

        .add-button {
            background-color: #28a745;
            color: #fff;
        }

        .save-button {
            background-color: #007bff;
            color: #fff;
        }

        .cancel-button {
            background-color: #dc3545;
            color: #fff;
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
                    <h1 class="h2" id="Main-Heading">Details for the Job</h1>
                </div>

                <?php if (!empty($jobDetails)) : ?>
                    <div class="row">
                        <div class="col-md-6">
                            <h3>Move Details</h3>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($jobDetails['MovingDate']); ?></p>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($jobDetails['BookingName']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($jobDetails['BookingEmail']); ?></p>
                            <p><strong>Rate:</strong> $<?php echo htmlspecialchars($jobDetails['Rate']); ?>/hr</p>
                            <p><strong>Deposit:</strong> $<?php echo htmlspecialchars($jobDetails['Deposit']); ?></p>
                            <p><strong>Start Time:</strong> <?php echo htmlspecialchars($jobDetails['TimingStartTime']); ?></p>
                        </div>

                        <div class="col-md-6">
                            <h3>Additional Charges</h3>
                            <ul>
                                <?php if ($jobDetails['JobStairCharge'] != 0) : ?>
                                    <li>Stair Charges: $<?php echo number_format($jobDetails['JobStairCharge'], 2); ?></li>
                                <?php endif; ?>
                                <?php if ($jobDetails['JobPoolTableCharge'] != 0) : ?>
                                    <li>Pool Table Charges: $<?php echo number_format($jobDetails['JobPoolTableCharge'], 2); ?></li>
                                <?php endif; ?>
                                <?php if ($jobDetails['JobPianoCharge'] != 0) : ?>
                                    <li>Piano Charges: $<?php echo number_format($jobDetails['JobPianoCharge'], 2); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    Job Timings
                                </div>
                                <div class="card-body">
                                    <p><strong>Timing ID:</strong> <?php echo htmlspecialchars($jobDetails['TimingID']); ?></p>
                                    <p><strong>Start Time:</strong> <?php echo htmlspecialchars($jobDetails['TimingStartTime']); ?></p>
                                    <p><strong>End Time:</strong> <?php echo htmlspecialchars($jobDetails['TimingEndTime']); ?></p>
                                    <p><strong>Is Complete:</strong> <?php echo htmlspecialchars($jobDetails['TimingIsComplete'] ? 'Yes' : 'No'); ?></p>
                                    <p><strong>Break Time:</strong> <?php echo htmlspecialchars($jobDetails['TimingBreakTime']); ?></p>
                                    <p><strong>Is Confirmed:</strong> <?php echo htmlspecialchars($jobDetails['TimingIsConfirmed'] ? 'Yes' : 'No'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    Assigned Employees
                                </div>
                                <div class="card-body">
                                    <p><strong>Names:</strong> <?php echo htmlspecialchars($jobDetails['EmployeeNames']); ?></p>
                                    <p><strong>Emails:</strong> <?php echo htmlspecialchars($jobDetails['EmployeeEmails']); ?></p>
                                    <button type="button" class="btn btn-outline-info" id="editEmployee">Edit Employees</button>
                                    <form method="POST" action="jobDetails.php?BookingID=<?php echo $bookingID; ?>">
                                        <input type="hidden" name="bookingID" value="<?php echo $bookingID; ?>">
                                        <button type="submit" class="btn btn-outline-warning" name="notifyEmployees">Notify Employees</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="employee-edit-form"></div>

                    <!-- Live Invoice Preview Section -->
                    <div class="mt-5">
                        <h3>Live Invoice Preview</h3>
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
                            <div class="form-check">
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
                            <button type="button" id="downloadInvoiceButton" class="btn btn-success mt-3">Download Invoice</button>
                            <button type="button" id="sendInvoiceEmailButton" class="btn btn-primary mt-3 ml-2">Send Email</button>
                        </div>
                    </div>

                <?php else : ?>
                    <p>Job details not found for BookingID: <?php echo htmlspecialchars($bookingID); ?></p>
                <?php endif; ?>
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
        document.addEventListener('DOMContentLoaded', function() {
            const editButton = document.getElementById('editEmployee');
            const employeeEditForm = document.getElementById('employee-edit-form');
            const employees = <?php echo json_encode($employees); ?>;
            let assignedEmployees = <?php echo json_encode(explode(', ', $jobDetails['EmployeeNames'])); ?>;

            editButton.addEventListener('click', function() {
                createForm();
            });

            // Attach event listeners to the buttons
            document.getElementById('downloadInvoiceButton').addEventListener('click', downloadInvoice);
            document.getElementById('sendInvoiceEmailButton').addEventListener('click', sendInvoiceEmail);

            function createForm() {
                employeeEditForm.innerHTML = '';

                const selectContainer = document.createElement('div');
                selectContainer.id = 'select-container';

                const selectHTML = employees.map(emp =>
                    `<option value="${emp.PhoneNo}">${emp.Name} (${emp.EmployeeType})</option>`
                ).join('');

                assignedEmployees.forEach(employeeName => {
                    const selectWrapper = document.createElement('div');
                    selectWrapper.classList.add('select-wrapper');

                    const employeeSelect = document.createElement('select');
                    employeeSelect.innerHTML = selectHTML;
                    employeeSelect.value = employees.find(emp => emp.Name === employeeName)?.PhoneNo || '';

                    const removeButton = document.createElement('i');
                    removeButton.classList.add('fa', 'fa-ban');
                    removeButton.setAttribute('aria-hidden', 'true');
                    removeButton.onclick = function() {
                        selectWrapper.remove();
                    };

                    selectWrapper.appendChild(employeeSelect);
                    selectWrapper.appendChild(removeButton);
                    selectContainer.appendChild(selectWrapper);
                });

                employeeEditForm.appendChild(selectContainer);

                const buttonContainer = document.createElement('div');
                buttonContainer.id = 'button-container';

                const addButton = document.createElement('button');
                addButton.textContent = 'Add Employee';
                addButton.type = 'button';
                addButton.classList.add('add-button');
                addButton.onclick = function() {
                    const selectWrapper = document.createElement('div');
                    selectWrapper.classList.add('select-wrapper');

                    const newSelect = document.createElement('select');
                    newSelect.innerHTML = selectHTML;

                    selectWrapper.appendChild(newSelect);
                    selectContainer.appendChild(selectWrapper);
                };

                const saveButton = document.createElement('button');
                saveButton.textContent = 'Save Changes';
                saveButton.type = 'button';
                saveButton.classList.add('save-button');
                saveButton.onclick = function() {
                    saveChanges();
                };

                const cancelButton = document.createElement('button');
                cancelButton.textContent = 'Cancel';
                cancelButton.type = 'button';
                cancelButton.classList.add('cancel-button');
                cancelButton.onclick = function() {
                    employeeEditForm.innerHTML = '';
                };

                buttonContainer.appendChild(addButton);
                buttonContainer.appendChild(saveButton);
                buttonContainer.appendChild(cancelButton);

                employeeEditForm.appendChild(buttonContainer);
            }

            function saveChanges() {
                const allSelects = employeeEditForm.querySelectorAll('.select-wrapper > select');
                const updatedEmployees = Array.from(allSelects).map(select => select.value);

                const formData = new FormData();
                formData.append('bookingID', <?php echo $bookingID; ?>);
                updatedEmployees.forEach((phoneNo, index) => {
                    formData.append('employees[]', phoneNo);
                });

                fetch('update-booking-employees.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        console.log('Response from server:', data);
                        if (data.includes('successfully')) {
                            location.reload();
                        } else {
                            console.error('Error from server:', data);
                        }
                    })
                    .catch(error => {
                        console.error('Network error:', error);
                    });
            }

            // Initialize the invoice when the page loads
            generateInvoice(); // Populate with default values
            attachEventListeners(); // Attach event listeners to the checkboxes

            function isGSTIncluded(gstValue) {
                return gstValue.trim().toLowerCase() === 'yes';
            }

            function generateInvoice() {
                const invoiceType = document.getElementById('invoiceTypeSelect').value;
                const today = new Date();
                const formattedDate = today.toLocaleDateString('en-AU'); // Australian date format

                const defaultValues = {
                    clientName: '<?php echo htmlspecialchars($jobDetails['BookingName']); ?>',
                    clientEmail: '<?php echo htmlspecialchars($jobDetails['BookingEmail']); ?>',
                    date: formattedDate,
                    totalLaborTime: '<?php echo htmlspecialchars($jobDetails['JobTotalLaborTime']); ?>',
                    calloutFee: '<?php echo htmlspecialchars($jobDetails['CalloutFee']); ?>',
                    rate: '<?php echo htmlspecialchars($jobDetails['Rate']); ?>',
                    deposit: '<?php echo htmlspecialchars($jobDetails['Deposit']); ?>',
                    gstIncluded: '<?php echo $jobDetails['JobGST'] == 1 ? 'Yes' : 'No'; ?>',
                    stairCharges: '<?php echo number_format($jobDetails['JobStairCharge'], 2); ?>',
                    pianoCharge: '<?php echo number_format($jobDetails['JobPianoCharge'], 2); ?>',
                    poolTableCharge: '<?php echo number_format($jobDetails['JobPoolTableCharge'], 2); ?>'
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
                            <td>$<span id="stairCharges" contenteditable="true" data-type="number">${defaultValues.stairCharges}</span></td>
                        </tr>
                        <tr class="item" id="pianoChargeRow">
                            <td>Piano Charge</td>
                            <td>$<span id="pianoCharge" contenteditable="true" data-type="number">${defaultValues.pianoCharge}</span></td>
                        </tr>
                        <tr class="item" id="poolTableChargeRow">
                            <td>Pool Table Charge</td>
                            <td>$<span id="poolTableCharge" contenteditable="true" data-type="number">${defaultValues.poolTableCharge}</span></td>
                        </tr>
                        <!-- For fixed price invoice, include misc charge if toggled -->
                        <tr class="item hidden" id="miscChargeRow">
                            <td><span id="miscChargeName" contenteditable="true" data-type="text">Miscellaneous Charge</span></td>
                            <td>$<span id="miscChargeAmount" contenteditable="true" data-type="number">0.00</span></td>
                        </tr>
                    </tbody>
                `;

                // Common footer and total
                const commonFooterHTML = `
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
                                    <option value="Yes" ${defaultValues.gstIncluded === 'Yes' ? 'selected' : ''}>Yes</option>
                                    <option value="No" ${defaultValues.gstIncluded === 'No' ? 'selected' : ''}>No</option>
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
                setInitialToggleStates(defaultValues); // Set initial states of toggles
                toggleChargeVisibility();
                recalculateTotals();

                // Attach event listener for payment status stamp
                const paymentStatus = document.getElementById('paymentStatus');
                paymentStatus.addEventListener('click', function() {
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

            function setInitialToggleStates(defaultValues) {
                // Set the toggle checkboxes based on whether the charges are non-zero
                document.getElementById('toggleStairCharge').checked = parseFloat(defaultValues.stairCharges) !== 0;
                document.getElementById('togglePianoCharge').checked = parseFloat(defaultValues.pianoCharge) !== 0;
                document.getElementById('togglePoolTableCharge').checked = parseFloat(defaultValues.poolTableCharge) !== 0;
                document.getElementById('toggleMiscCharge').checked = false; // Misc Charge default to unchecked
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

                // For checkboxes
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

                // Add this event listener for the card surcharge checkbox
                document.getElementById('toggleCardSurcharge').addEventListener('change', recalculateTotals);

                // For miscellaneous charge
                document.getElementById('toggleMiscCharge').addEventListener('change', function() {
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
                miscChargeRow.classList.toggle('hidden', !toggleMiscCharge);

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
                    const subTotal = (totalLaborTime + calloutFee) * rate + pianoCharge + poolTableCharge;
                    const gstAmount = gstIncluded ? subTotal * 0.10 : 0;
                    const totalChargeBeforeCardSurcharge = subTotal + gstAmount - deposit + stairCharges;

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
                    const miscChargeAmount = document.getElementById('miscChargeRow').classList.contains('hidden') ? 0 : parseFloat(document.getElementById('miscChargeAmount').innerText) || 0;

                    // Perform calculations
                    const subTotal = totalInitialCharge + pianoChargeAmount + poolTableChargeAmount + miscChargeAmount;
                    const gstAmount = gstIncluded ? subTotal * 0.10 : 0;
                    const totalChargeBeforeCardSurcharge = subTotal + gstAmount - deposit + stairCharges;

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
                    fetch('saveInvoice.php', {
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

            // Modify the sendInvoiceEmail function to point to jobDetails.php
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
                const sendEmailButton = document.getElementById('sendInvoiceEmailButton');
                sendEmailButton.disabled = true;
                sendEmailButton.innerText = 'Sending...';

                // Generate PDF from the cloned element and get the Blob
                html2pdf().set(opt).from(clonedElement).outputPdf('blob').then(function(pdfBlob) {
                    // Read the Blob as base64
                    var reader = new FileReader();
                    reader.readAsDataURL(pdfBlob);
                    reader.onloadend = function() {
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
                        fetch('jobDetails.php?BookingID=<?php echo $bookingID; ?>', {
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

            // Attach the modified sendInvoiceEmail function to the button
            document.getElementById('sendInvoiceEmailButton').addEventListener('click', sendInvoiceEmail);

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
        });
    </script>
</body>

</html>