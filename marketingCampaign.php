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

// Initialize variables
$templates = [];
$template_selected = null;
$uploadSuccess = false;
$error = '';

// Handle deleting a template
if (isset($_GET['delete_template_id'])) {
    $delete_template_id = intval($_GET['delete_template_id']);
    try {
        $stmt = $conn->prepare("DELETE FROM sms_templates WHERE id = ?");
        $stmt->bind_param("i", $delete_template_id);
        $stmt->execute();
        $stmt->close();
        log_message("Template with ID '$delete_template_id' deleted.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        log_message('Error deleting template: ' . $e->getMessage());
        $error = "Error deleting template.";
    }
}

// Handle updating a template
if (isset($_POST['edit_template_id']) && isset($_POST['edit_template_name']) && isset($_POST['edit_template_body'])) {
    $edit_template_id = intval($_POST['edit_template_id']);
    $edit_template_name = $_POST['edit_template_name'];
    $edit_template_body = $_POST['edit_template_body'];

    try {
        $stmt = $conn->prepare("UPDATE sms_templates SET template_name = ?, template_body = ? WHERE id = ?");
        $stmt->bind_param("ssi", $edit_template_name, $edit_template_body, $edit_template_id);
        $stmt->execute();
        $stmt->close();
        log_message("Template with ID '$edit_template_id' updated.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        log_message('Error updating template: ' . $e->getMessage());
        $error = "Error updating template.";
    }
}

// Fetch available templates from the database
try {
    $result = $conn->query("SELECT id, template_name, template_body FROM sms_templates");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $templates[] = $row;
        }
        $result->free();
    } else {
        throw new Exception("Error fetching templates: " . $conn->error);
    }

    if (isset($_POST['template_id']) && !empty($_POST['template_id'])) {
        $template_id = intval($_POST['template_id']);
        foreach ($templates as $template) {
            if ($template['id'] == $template_id) {
                $template_selected = $template['template_body'];
                break;
            }
        }
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

// Twilio credentials
$sid    = "THE_ACCOUNT_SID";
$token  = "THE_ACCOUNT_AUTH_TOKEN";
$twilio = new Client($sid, $token);

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

            // Strip HTML tags from message body
            $plain_message_body = strip_tags($message_body);

            // Ensure the message body is not empty
            if (empty(trim($plain_message_body))) {
                log_message("Empty message body for phone number $phone_number.");
                $error = "Cannot send SMS with an empty message body.";
                break;
            }

            // Send SMS using Twilio
            try {
                $message = $twilio->messages->create(
                    $phone_number, // to
                    array(
                        "messagingServiceSid" => "THE_MESSAGING_SERVICE_SID", // The messaging service SID
                        "body" => $plain_message_body
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
    <!-- Meta tags and other head content -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing Campaign</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="styles.css">
    <!-- CKEditor 5 -->
    <script src="https://cdn.ckeditor.com/ckeditor5/38.1.0/classic/ckeditor.js"></script>
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
                    <div class="card mb-3 shadow-sm">
                        <div class="card-header">
                            <strong>Selected Template:</strong>
                        </div>
                        <div class="card-body">
                            <?php echo $template_selected; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Upload CSV Form -->
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="template_id" value="<?php echo isset($_POST['template_id']) ? htmlspecialchars($_POST['template_id']) : ''; ?>">
                    <div class="form-group">
                        <label for="csv_file" class="font-weight-bold">Upload CSV File</label>
                        <input type="file" class="form-control-file border rounded p-2" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block shadow-sm" name="send_sms">Send SMS</button>
                </form>

                <!-- Template Management -->
                <h2 class="h4 text-secondary mt-5">Template Management</h2>
                <table class="table table-bordered table-hover shadow-sm">
                    <thead class="thead-light">
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
                                    <!-- Edit Button -->
                                    <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editTemplateModal<?php echo $template['id']; ?>">Edit</button>
                                    <!-- Delete Button -->
                                    <a href="?delete_template_id=<?php echo $template['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this template?');">Delete</a>
                                </td>
                            </tr>

                            <!-- Edit Template Modal -->
                            <div class="modal fade" id="editTemplateModal<?php echo $template['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editTemplateModalLabel<?php echo $template['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <form method="post">
                                        <input type="hidden" name="edit_template_id" value="<?php echo $template['id']; ?>">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editTemplateModalLabel<?php echo $template['id']; ?>">Edit Template</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="form-group">
                                                    <label for="edit_template_name_<?php echo $template['id']; ?>" class="font-weight-bold">Template Name</label>
                                                    <input type="text" class="form-control" id="edit_template_name_<?php echo $template['id']; ?>" name="edit_template_name" value="<?php echo htmlspecialchars($template['template_name']); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="edit_template_body_<?php echo $template['id']; ?>" class="font-weight-bold">Template Body</label>
                                                    <textarea class="form-control" id="edit_template_body_<?php echo $template['id']; ?>" name="edit_template_body" rows="4" required><?php echo htmlspecialchars($template['template_body']); ?></textarea>
                                                    <small class="form-text text-muted">Use <code>{name}</code> to insert the customer's name.</small>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn-success">Save Changes</button>
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

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

    <!-- Include Scripts at the end of body -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- CKEditor 5 -->
    <script src="https://cdn.ckeditor.com/ckeditor5/38.1.0/classic/ckeditor.js"></script>

    <!-- Initialize CKEditor for 'Create New Template' -->
    <script>
        ClassicEditor.create(document.querySelector('#new_template_body'))
            .catch(error => {
                console.error(error);
            });
    </script>

    <!-- Initialize CKEditor for Edit Modals -->
    <?php foreach ($templates as $template): ?>
        <script>
            let editor<?php echo $template['id']; ?>;
            $('#editTemplateModal<?php echo $template['id']; ?>').on('shown.bs.modal', function() {
                if (editor<?php echo $template['id']; ?>) {
                    editor<?php echo $template['id']; ?>.destroy()
                        .then(() => {
                            initEditor<?php echo $template['id']; ?>();
                        });
                } else {
                    initEditor<?php echo $template['id']; ?>();
                }
            });

            $('#editTemplateModal<?php echo $template['id']; ?>').on('hidden.bs.modal', function() {
                if (editor<?php echo $template['id']; ?>) {
                    editor<?php echo $template['id']; ?>.destroy()
                        .then(() => {
                            editor<?php echo $template['id']; ?> = null;
                        });
                }
            });

            function initEditor<?php echo $template['id']; ?>() {
                ClassicEditor.create(document.querySelector('#edit_template_body_<?php echo $template['id']; ?>'))
                    .then(editor => {
                        editor<?php echo $template['id']; ?> = editor;
                    })
                    .catch(error => {
                        console.error(error);
                    });
            }
        </script>
    <?php endforeach; ?>

</body>

</html>