<?php
session_start();
// Include db.php for database connection
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Fetch the list of invoices
$invoices = [];
$invoiceDir = '/home/alphaard/movers.alphamovers.com.au/Invoices'; // Use the absolute path to the invoices directory

if (is_dir($invoiceDir)) {
    $files = scandir($invoiceDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'pdf') {
            $invoices[] = $file;
        }
    }
}

// Handle email sending (if form is submitted)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $invoiceFile = $_POST['invoice_file'];
    $recipientEmail = $_POST['recipient_email'];
    $recipientName = $_POST['recipient_name'];

    $subject = "Invoice from Ace Movers";
    $body = "Please find attached the invoice for your recent service.";

    sendEmail($invoiceFile, $recipientEmail, $recipientName, $subject, $body, $invoiceDir);

    // Redirect back to the page to avoid form re-submission
    header("Location: customerInvoices.php");
    exit;
}

// Function to send email using SMTP2GO API
function sendEmail($invoiceFile, $recipientEmail, $recipientName, $subject, $body, $invoiceDir)
{
    $apiKey = 'API KEY'; // Replace with your SMTP2GO API key
    $senderEmail = 'aaron@acemovers.com.au'; // Replace with your sender email
    $senderName = 'Aaron Miller'; // Replace with your sender name

    $attachment = base64_encode(file_get_contents($invoiceDir . '/' . $invoiceFile));

    $data = [
        'api_key' => $apiKey,
        'to' => [
            $recipientEmail
        ],
        'sender' => $senderEmail,
        'subject' => $subject,
        'text_body' => $body,
        'attachments' => [
            [
                'filename' => $invoiceFile,
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
        error_log('Message has been sent.');
    } else {
        error_log('Message was not sent. Error: ' . ($responseData['data']['errors'][0]['message'] ?? 'Unknown error'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>View Invoices</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="keep-session-alive.js"></script>
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
                    <h1 class="h2">View Invoices</h1>
                </div>

                <!-- Search Form -->
                <form class="form-inline mb-3" method="GET" action="view_invoices.php">
                    <input class="form-control mr-sm-2" type="search" placeholder="Search invoices" aria-label="Search" name="search">
                    <button class="btn btn-outline-success my-2 my-sm-0" type="submit">Search</button>
                </form>

                <div class="row">
                    <div class="col-md-6">
                        <!-- Invoices List -->
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Invoice</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $index => $invoice) : ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= $invoice ?></td>
                                            <td>
                                                <button class="btn btn-info btn-sm view-invoice" data-file="<?= $invoice ?>"><i class="fa fa-eye"></i> View</button>
                                                <button class="btn btn-secondary btn-sm" data-toggle="modal" data-target="#emailModal" data-file="<?= $invoice ?>"><i class="fa fa-envelope"></i> Email</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <!-- Invoice Preview -->
                        <iframe id="invoicePreview" style="width: 100%; height: 600px; border: 1px solid #ddd;"></iframe>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Email Modal -->
    <div class="modal fade" id="emailModal" tabindex="-1" role="dialog" aria-labelledby="emailModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="customerInvoices.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="emailModalLabel">Send Invoice via Email</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="invoice_file" id="invoice_file" value="">
                        <div class="form-group">
                            <label for="recipient_name">Recipient Name</label>
                            <input type="text" class="form-control" id="recipient_name" name="recipient_name" required>
                        </div>
                        <div class="form-group">
                            <label for="recipient_email">Recipient Email</label>
                            <input type="email" class="form-control" id="recipient_email" name="recipient_email" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="send_email" class="btn btn-primary">Send Email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.view-invoice').click(function() {
                var invoiceFile = $(this).data('file');
                $('#invoicePreview').attr('src', 'https://movers.alphamovers.com.au/Invoices/' + invoiceFile);
            });

            $('#emailModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget); // Button that triggered the modal
                var invoiceFile = button.data('file'); // Extract info from data-* attributes
                var modal = $(this);
                modal.find('#invoice_file').val(invoiceFile);
            });
        });
    </script>
</body>

</html>