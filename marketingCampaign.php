<?php
session_start();

// Include database connection
require 'db.php';

// Include the Twilio PHP library
require_once 'twilio-php-main/src/Twilio/autoload.php';

use Twilio\Rest\Client;

// Function to log errors and messages to sms-error-log.log
function log_message($message)
{
    $logFile = __DIR__ . '/sms-error-log.log';
    $current_time = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$current_time] $message" . PHP_EOL, FILE_APPEND);
}


// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    log_message('Unauthorized access attempt.');
    header("Location: login.php");
    exit;
}

// Fetch available templates from the database
$templates = [];
$template_selected = null;

try {
    $result = $conn->query("SELECT id, template_name FROM sms_templates");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $templates[] = $row;
        }
        $result->free();
    } else {
        throw new Exception("Error fetching templates: " . $conn->error);
    }

    if (isset($_POST['template_id']) && !empty($_POST['template_id'])) {
        $template_id = $_POST['template_id'];
        $stmt = $conn->prepare("SELECT template_body FROM sms_templates WHERE id = ?");
        $stmt->bind_param("i", $template_id);
        $stmt->execute();
        $stmt->bind_result($template_body);
        if ($stmt->fetch()) {
            $template_selected = $template_body;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    log_message($e->getMessage());
    $error = "Error fetching templates.";
}

// Handle creating new template
if (isset($_POST['new_template_name']) && isset($_POST['new_template_body'])) {
    $new_template_name = $_POST['new_template_name'];
    $new_template_body = $_POST['new_template_body'];

    try {
        $stmt = $conn->prepare("INSERT INTO sms_templates (template_name, template_body) VALUES (?, ?)");
        $stmt->bind_param("ss", $new_template_name, $new_template_body);
        $stmt->execute();
        $stmt->close();
        log_message("New template '$new_template_name' created.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        log_message('Error creating template: ' . $e->getMessage());
        $error = "Error creating template.";
    }
}

// Handle deleting a template
if (isset($_POST['delete_template_id'])) {
    $delete_template_id = $_POST['delete_template_id'];

    try {
        $stmt = $conn->prepare("DELETE FROM sms_templates WHERE id = ?");
        $stmt->bind_param("i", $delete_template_id);
        $stmt->execute();
        $stmt->close();
        log_message("Template with ID $delete_template_id deleted.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        log_message('Error deleting template: ' . $e->getMessage());
        $error = "Error deleting template.";
    }
}

// Twilio credentials
$sid    = "SID";
$token  = "Token";
$twilio = new Client($sid, $token);

$uploadSuccess = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file']) && isset($_POST['send_sms'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, 'r');

    if ($handle !== false) {
        log_message('CSV file uploaded and opened successfully.');

        // Skip the header row
        fgetcsv($handle);

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $name = $data[0];
            $phone_number = $data[1];

            // Use the selected template or fallback to the default message
            $message_body = !empty($template_selected) ? str_replace("{name}", $name, $template_selected) : "Hello $name, this is a marketing message from AceMovers.";

            // Ensure the message body is not empty
            if (empty(trim($message_body))) {
                log_message("Empty message body for phone number $phone_number.");
                $error = "Cannot send SMS with an empty message body.";
                break;
            }

            // Send SMS using Twilio
            try {
                $message = $twilio->messages->create(
                    $phone_number, // to
                    array(
                        "messagingServiceSid" => "MSID", // Your messaging service SID
                        "body" => $message_body
                    )
                );
                log_message("SMS sent successfully to $phone_number.");
            } catch (Exception $e) {
                $error = "Error sending SMS to $phone_number: " . $e->getMessage();
                log_message($error);
                break;
            }
        }

        fclose($handle);
        if (!$error) {
            $uploadSuccess = true;
        }
    } else {
        $error = "Error reading the CSV file.";
        log_message($error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Marketing Campaign</title>
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
                    <h1 class="h2 text-primary font-weight-bold" id="Main-Heading">Marketing Campaign Dashboard</h1>
                </div>

                <!-- Display success or error messages -->
                <?php if ($uploadSuccess): ?>
                    <div class="alert alert-success shadow-sm rounded">Messages have been sent successfully!</div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger shadow-sm rounded"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Select Template -->
                <form method="post">
                    <div class="form-group">
                        <label for="template_id" class="font-weight-bold">Select Template</label>
                        <select class="form-control custom-select" id="template_id" name="template_id" onchange="this.form.submit()">
                            <option value="">Select Template</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo $template['id']; ?>" <?php if (isset($_POST['template_id']) && $_POST['template_id'] == $template['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($template['template_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <!-- Display selected template -->
                <?php if ($template_selected): ?>
                    <div class="alert alert-info shadow-sm rounded">
                        <strong>Selected Template:</strong> <?php echo htmlspecialchars($template_selected); ?>
                    </div>
                <?php endif; ?>

                <!-- Template Management -->
                <h2 class="h4 text-secondary mt-5">Manage Templates</h2>
                <table class="table table-striped table-hover mt-3">
                    <thead>
                        <tr>
                            <th>Template Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($template['template_name']); ?></td>
                                <td>
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="delete_template_id" value="<?php echo $template['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this template?');">
                                            <i class="fa fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Create New Template -->
                <h2 class="h4 text-secondary mt-5">Create New Template</h2>
                <form method="post">
                    <div class="form-group">
                        <label for="new_template_name" class="font-weight-bold">Template Name</label>
                        <input type="text" class="form-control" id="new_template_name" name="new_template_name" required>
                    </div>
                    <div class="form-group">
                        <label for="new_template_body" class="font-weight-bold">Template Body</label>
                        <textarea class="form-control" id="new_template_body" name="new_template_body" rows="4" required></textarea>
                        <small class="form-text text-muted">Use <code>{name}</code> to insert the customer's name.</small>
                    </div>
                    <button type="submit" class="btn btn-success btn-block shadow-sm">Create Template</button>
                </form>
            </main>

        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>

</html>