<?php
session_start();
include 'db.php'; // Ensure this points to your database connection file

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$response = ['success' => false, 'message' => 'Something went wrong.'];

// if ($_SERVER['REQUEST_METHOD'] == 'POST') {
//     var_dump($_POST); // Debug to see received POST data
//     exit; // Stop execution to read the debug output
// }
// Check if the form data is present and properly formatted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bookingID'], $_POST['employeePhoneNo'], $_POST['startTime'])) {
    var_dump($_POST); // Debug to see received POST data
    $bookingID = $conn->real_escape_string($_POST['bookingID']);
    $startTime = $conn->real_escape_string($_POST['startTime']);
    $employees = $_POST['employeePhoneNo'];

    // Start a transaction
    $conn->begin_transaction();

    try {
        $allSuccess = true;

        // Insert each employee assignment into the database
        foreach ($employees as $employeePhoneNo) {
            if (!empty($employeePhoneNo)) { // Make sure the employeePhoneNo is not empty
                $employeePhoneNo = $conn->real_escape_string($employeePhoneNo);
                $sql = "INSERT INTO Bookings_Employees (BookingID, EmployeePhoneNo, TimeSlot) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $bookingID, $employeePhoneNo, $startTime);
                if (!$stmt->execute()) {
                    $allSuccess = false;
                    break; // Exit the loop if an error occurs
                }
            }
        }

        if ($allSuccess) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Booking updated successfully.']);
        } else {
            $conn->rollback();
            $response = ['success' => false, 'message' => 'Failed to assign employees.'];
        }
    } catch (Exception $e) {
        $conn->rollback();
        $response = ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
    }

    $conn->close();
} else {
    $response = ['success' => false, 'message' => 'Invalid request data.'];
}

echo json_encode($response);
