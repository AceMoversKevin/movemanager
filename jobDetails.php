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
        GROUP_CONCAT(DISTINCT e.Name ORDER BY e.Name SEPARATOR ', ') AS EmployeeNames,
        GROUP_CONCAT(DISTINCT e.Email ORDER BY e.Name SEPARATOR ', ') AS EmployeeEmails,
        MAX(jt.TimingID) AS TimingID,
        MAX(jt.StartTime) AS TimingStartTime,
        MAX(jt.EndTime) AS TimingEndTime,
        MAX(jt.TotalTime) AS TimingTotalTime,
        MAX(jt.isComplete) AS TimingIsComplete,
        MAX(jt.BreakTime) AS TimingBreakTime,
        MAX(jt.isConfirmed) AS TimingIsConfirmed,
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
        MAX(jc.PoolTableCharge) AS JobPoolTableCharge
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

        $hasAdditionalCharges = ($row['StairCharges'] != 0 || $row['PianoCharge'] != 0 || $row['BookingPoolTableCharge'] != 0);
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

    $apiKey = 'API KEY';
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Job Details</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
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

            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2" id="Main-Heading">Details for the Job</h1>
                </div>

                <?php if (!empty($jobDetails)) : ?>
                    <div class="row">
                        <div class="col-md-4">
                            <h3>Move Details</h3>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($jobDetails['MovingDate']); ?></p>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($jobDetails['BookingName']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($jobDetails['BookingEmail']); ?></p>
                            <p><strong>Rate:</strong> $<?php echo htmlspecialchars($jobDetails['Rate']); ?>/hr</p>
                            <p><strong>Deposit:</strong> $<?php echo htmlspecialchars($jobDetails['Deposit']); ?></p>
                            <p><strong>Start Time:</strong> <?php echo htmlspecialchars($jobDetails['TimingStartTime']); ?></p>
                        </div>

                        <div class="col-md-4">
                            <h3>Additional Charges</h3>
                            <ul>
                                <?php if ($jobDetails['JobStairCharge'] != 0) : ?>
                                    <li>Stair Charges: $<?php echo number_format($jobDetails['JobStairCharge']); ?></li>
                                <?php endif; ?>
                                <?php if ($jobDetails['JobPoolTableCharge'] != 0) : ?>
                                    <li>Pool Table Charges: $<?php echo number_format($jobDetails['JobPoolTableCharge']); ?></li>
                                <?php endif; ?>
                                <?php if ($jobDetails['JobPianoCharge'] != 0) : ?>
                                    <li>Piano Charges: $<?php echo number_format($jobDetails['JobPianoCharge']); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="col-md-4">
                            <h3>Live Invoice Preview</h3>
                            <p><strong>Total Labor Time:</strong> <?php echo htmlspecialchars($jobDetails['JobTotalLaborTime']); ?>h</p>
                            <p><strong>Total Billable Time:</strong> <?php echo htmlspecialchars($jobDetails['JobTotalBillableTime']); ?>h</p>
                            <p><strong>Subtotal:</strong> $<?php echo htmlspecialchars($jobDetails['SubTotal']); ?></p>
                            <p><strong>GST:</strong> $<?php echo $jobDetails['Surcharge']; ?></p>
                            <p><strong>Total:</strong> $<?php echo htmlspecialchars($jobDetails['JobTotalCharge']); ?></p>
                            <button class="btn btn-secondary" name="download-invoice-button">Download Invoice</button>
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

                <?php else : ?>
                    <p>Job details not found for BookingID: <?php echo htmlspecialchars($bookingID); ?></p>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editButton = document.getElementById('editEmployee');
            const employeeEditForm = document.getElementById('employee-edit-form');
            const employees = <?php echo json_encode($employees); ?>;
            let assignedEmployees = <?php echo json_encode(explode(', ', $jobDetails['EmployeeNames'])); ?>;

            editButton.addEventListener('click', function() {
                createForm();
            });

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
        });
    </script>
</body>

</html>