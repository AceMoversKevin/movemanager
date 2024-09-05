<?php
require_once 'db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bookingID = isset($_POST['bookingID']) ? intval($_POST['bookingID']) : 0;
    $employeePhones = isset($_POST['employees']) ? $_POST['employees'] : [];

    if ($bookingID > 0) {
        // Get the current time
        $currentDateTime = new DateTime();

        // Retrieve job timing information from the JobTimings table
        $sqlJobTimings = "SELECT * FROM JobTimings WHERE BookingID = ?";
        $stmtJobTimings = $conn->prepare($sqlJobTimings);
        $stmtJobTimings->bind_param("i", $bookingID);
        $stmtJobTimings->execute();
        $resultJobTimings = $stmtJobTimings->get_result();
        $jobTimingsInfo = $resultJobTimings->fetch_assoc();

        // Retrieve trip details from the TripDetails table
        $sqlTripDetails = "SELECT * FROM TripDetails WHERE BookingID = ? ORDER BY TripNumber DESC";
        $stmtTripDetails = $conn->prepare($sqlTripDetails);
        $stmtTripDetails->bind_param("i", $bookingID);
        $stmtTripDetails->execute();
        $resultTripDetails = $stmtTripDetails->get_result();
        $tripDetails = [];
        while ($row = $resultTripDetails->fetch_assoc()) {
            $tripDetails[] = $row;
        }

        // Initialize job status flags
        $jobStarted = !empty($jobTimingsInfo);
        $workedTime = 0;

        if ($jobStarted) {
            $mainTripStartTime = new DateTime($jobTimingsInfo['StartTime']);
            $workedTime = $currentDateTime->getTimestamp() - $mainTripStartTime->getTimestamp();
        }

        // Start transaction
        $conn->autocommit(FALSE);

        try {
            // Get the currently assigned employees
            $stmt = $conn->prepare("SELECT EmployeePhoneNo FROM BookingAssignments WHERE BookingID = ?");
            $stmt->bind_param('i', $bookingID);
            $stmt->execute();
            $result = $stmt->get_result();
            $currentEmployees = [];
            while ($row = $result->fetch_assoc()) {
                $currentEmployees[] = $row['EmployeePhoneNo'];
            }
            $stmt->close();

            // Determine which employees to add and which to remove
            $employeesToAdd = array_diff($employeePhones, $currentEmployees); // Employees that are new
            $employeesToRemove = array_diff($currentEmployees, $employeePhones); // Employees that are no longer assigned

            // Handle removed employees
            if (!empty($employeesToRemove)) {
                foreach ($employeesToRemove as $phoneNo) {
                    // Log partial hours if job is started
                    if ($jobStarted) {
                        $partialHours = $workedTime / 3600; // Convert seconds to hours
                        $reason = "Employee removed during active job.";
                        $endTime = $currentDateTime->format('Y-m-d H:i:s');

                        $stmt = $conn->prepare("INSERT INTO PartialHours (BookingID, PhoneNo, PartialHours, Reason, StartTime, EndTime) VALUES (?, ?, ?, ?, ?, ?)");
                        if (!$stmt) {
                            throw new Exception($conn->error);
                        }
                        $stmt->bind_param('isdsss', $bookingID, $phoneNo, $partialHours, $reason, $jobTimingsInfo['StartTime'], $endTime);
                        $stmt->execute();
                        $stmt->close();
                    }

                    // Remove employee from assignment
                    $stmt = $conn->prepare("DELETE FROM BookingAssignments WHERE BookingID = ? AND EmployeePhoneNo = ?");
                    if (!$stmt) {
                        throw new Exception($conn->error);
                    }
                    $stmt->bind_param('is', $bookingID, $phoneNo);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Handle added employees
            if (!empty($employeesToAdd)) {
                foreach ($employeesToAdd as $phoneNo) {
                    // Log start time for added employee if job is started
                    if ($jobStarted) {
                        $startTime = $currentDateTime->format('Y-m-d H:i:s');
                        $reason = "Employee added during active job.";

                        $stmt = $conn->prepare("INSERT INTO PartialHours (BookingID, PhoneNo, Reason, StartTime) VALUES (?, ?, ?, ?)");
                        if (!$stmt) {
                            throw new Exception($conn->error);
                        }
                        $stmt->bind_param('isss', $bookingID, $phoneNo, $reason, $startTime);
                        $stmt->execute();
                        $stmt->close();
                    }

                    // Add new employee to assignment
                    $stmt = $conn->prepare("INSERT INTO BookingAssignments (BookingID, EmployeePhoneNo) VALUES (?, ?)");
                    if (!$stmt) {
                        throw new Exception($conn->error);
                    }
                    $stmt->bind_param('is', $bookingID, $phoneNo);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Commit the transaction
            $conn->commit();
            echo "Booking updated successfully";
        } catch (Exception $e) {
            // An error occurred, rollback the transaction
            $conn->rollback();
            http_response_code(500); // Internal Server Error
            echo "An error occurred while updating the booking: " . $e->getMessage();
        }

        // Turn autocommit back on
        $conn->autocommit(TRUE);
    } else {
        http_response_code(400); // Bad Request
        echo "Invalid booking ID.";
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo "Invalid request method.";
}

// Close the database connection
$conn->close();
