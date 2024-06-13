<?php
session_start();
require_once 'db.php'; // Ensure this is your database connection file

// Define a function to filter and validate assigned employees data
function validateAssignedEmployees($employees)
{
    $validatedEmployees = [];
    foreach ($employees as $employee) {
        // Assuming the 'EmployeePhoneNo' is always a string
        if (isset($employee['EmployeePhoneNo']) && preg_match('/^[0-9]{10}$/', $employee['EmployeePhoneNo'])) {
            $validatedEmployees[] = $employee['EmployeePhoneNo'];
        }
    }
    return $validatedEmployees;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id']) && $_SESSION['role'] === 'Admin' || $_SESSION['role'] === 'SuperAdmin') {
    $bookingID = $_POST['bookingID'];
    $assignedEmployees = validateAssignedEmployees($_POST['assignedEmployees'] ?? []);

    if (empty($assignedEmployees)) {
        echo json_encode(['success' => false, 'message' => "No valid employees provided"]);
        exit;
    }

    // Begin transaction
    $conn->begin_transaction();
    $stmt = $conn->prepare("INSERT INTO BookingAssignments (BookingID, EmployeePhoneNo) VALUES (?, ?)");
    $error = false;

    foreach ($assignedEmployees as $employeePhoneNo) {
        if (!$stmt->bind_param("is", $bookingID, $employeePhoneNo) || !$stmt->execute()) {
            $error = true;
            break;
        }
    }

    if ($error) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => "Database error: " . $stmt->error]);
    } else {
        if ($conn->commit()) {
            echo json_encode(['success' => true, 'message' => "Employees assigned successfully."]);
        } else {
            echo json_encode(['success' => false, 'message' => "Failed to commit transaction"]);
        }
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request or not authorized.']);
}
