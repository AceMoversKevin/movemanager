<?php
session_start();
require_once 'db.php'; // This should be your database connection file

// Define a function to filter and validate assigned employees data
function validateAssignedEmployees($employees) {
    $validatedEmployees = [];
    foreach ($employees as $employee) {
        // Assuming the 'EmployeePhoneNo' is always a string
        if (isset($employee['EmployeePhoneNo'])) {
            $validatedEmployees[] = [
                'EmployeePhoneNo' => $employee['EmployeePhoneNo']
            ];
        }
    }
    return $validatedEmployees;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id']) && $_SESSION['role'] === 'Admin') {
    $bookingID = $_POST['bookingID'];
    $assignedEmployees = validateAssignedEmployees($_POST['assignedEmployees'] ?? []);

    // Prepare the SQL to insert new assignments
    $stmt = $conn->prepare("INSERT INTO BookingAssignments (BookingID, EmployeePhoneNo) VALUES (?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => "Error preparing statement: " . $conn->error]);
        exit;
    }

    // Disable autocommit for transaction
    $conn->autocommit(FALSE);
    $error = false;

    foreach ($assignedEmployees as $assignment) {
        if (!$stmt->bind_param("is", $bookingID, $assignment['EmployeePhoneNo'])) {
            $error = true;
            break;
        }
        if (!$stmt->execute()) {
            $error = true;
            break;
        }
    }

    if ($error) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => "Error: " . $stmt->error]);
    } else {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Employees assigned successfully."]);
    }

    $stmt->close();
    $conn->autocommit(TRUE); // Enable autocommit
    $conn->close();
    header("unassignedJobs.php");
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request or not authorized.']);
}
?>
