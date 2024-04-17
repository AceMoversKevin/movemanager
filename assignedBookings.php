<?php
include 'db.php'; // Include your database connection file
session_start(); // Check for admin role

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

// Fetch available employees (only Drivers and Helpers)
$employeesQuery = "SELECT PhoneNo, Name, EmployeeType FROM Employees WHERE EmployeeType <> 'Admin'";
$allEmployeesResult = $conn->query($employeesQuery);
$allEmployees = $allEmployeesResult->fetch_all(MYSQLI_ASSOC);

$query = "SELECT b.BookingID, b.Name, b.Email, b.Phone, b.Bedrooms, b.BookingDate, b.MovingDate, b.PickupLocation, b.DropoffLocation,
          GROUP_CONCAT(CONCAT(e.Name, ' (', e.EmployeeType, ')') SEPARATOR ', ') AS AssignedEmployees,
          be.TimeSlot, COALESCE(bp.TruckSize, '5') AS TruckSize, COALESCE(bp.CalloutFee, '65') AS CalloutFee,
          COALESCE(bp.Rate, '130') AS Rate, COALESCE(bp.Deposit, '50.00') AS Deposit
          FROM Bookings b
          INNER JOIN Bookings_Employees be ON b.BookingID = be.BookingID
          INNER JOIN Employees e ON be.EmployeePhoneNo = e.PhoneNo
          LEFT JOIN BookingPricing bp ON b.BookingID = bp.BookingID
          GROUP BY b.BookingID
          ORDER BY b.BookingID;";
$result = $conn->query($query);

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookingID = $row['BookingID'];
    $bookings[$bookingID] = [
        'Details' => [
            'Name' => $row['Name'],
            'Email' => $row['Email'],
            'Phone' => $row['Phone'],
            'Bedrooms' => $row['Bedrooms'],
            'BookingDate' => $row['BookingDate'],
            'MovingDate' => $row['MovingDate'],
            'PickupLocation' => $row['PickupLocation'],
            'DropoffLocation' => $row['DropoffLocation']
        ],
        'AssignedEmployees' => $row['AssignedEmployees'],
        'TimeSlot' => $row['TimeSlot'],
        'TruckSize' => $row['TruckSize'],
        'CalloutFee' => $row['CalloutFee'],
        'Rate' => $row['Rate'],
        'Deposit' => $row['Deposit']
    ];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Bookings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <header class="mb-3 py-3">
        <div class="container-fluid">
            <div class="row justify-content-between align-items-center">
                <div class="col-md-6 col-lg-4 user-info">
                    <img src="user.svg" alt="User icon">
                    <span><?= htmlspecialchars($_SESSION['username']); ?></span>
                </div>
                <div class="col-md-6 col-lg-4 text-md-right">
                    <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
                    <a href="unassignedBookings.php" class="btn btn-outline-primary">Unassigned Moves</a>
                    <a href="assignedBookings.php" class="btn btn-outline-primary">Assigned Moves</a>
                    <a href="logout.php" class="btn btn-outline-danger">Logout</a>
                </div>
            </div>
        </div>
    </header>
    <div class="container">
        <h1>Assigned Bookings</h1>
        <div class="row">
            <?php foreach ($bookings as $bookingID => $booking) : ?>
                <div class="col-lg-6 col-md-12 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Booking ID: <?= htmlspecialchars($bookingID) ?></h5>
                            <p class="card-text">Customer Name: <?= htmlspecialchars($booking['Details']['Name']) ?></p>
                            <p class="card-text">Pickup: <?= htmlspecialchars($booking['Details']['PickupLocation']) ?></p>
                            <p class="card-text">Dropoff: <?= htmlspecialchars($booking['Details']['DropoffLocation']) ?></p>
                            <p class="card-text">Email: <?= htmlspecialchars($booking['Details']['Email']) ?></p>
                            <p class="card-text">Phone: <?= htmlspecialchars($booking['Details']['Phone']) ?></p>
                            <p class="card-text">Assigned To: <?= htmlspecialchars($booking['AssignedEmployees']) ?></p>
                            <p class="card-text">Time Slot: <?= htmlspecialchars($booking['TimeSlot']) ?></p>
                            <p class="card-text">Truck Size: <?= htmlspecialchars($booking['TruckSize']) ?></p>
                            <p class="card-text">Callout Fee: <?= htmlspecialchars($booking['CalloutFee']) ?></p>
                            <p class="card-text">Rate: <?= htmlspecialchars($booking['Rate']) ?></p>
                            <p class="card-text">Deposit: <?= htmlspecialchars($booking['Deposit']) ?></p>
                            <button class="btn btn-primary my-2 update-assigned-btn" data-booking-id="<?= $bookingID ?>">Update Assigned</button>
                            <!-- Update form for each booking -->
                            <form class="update-assign-form" style="display:none;" data-booking-id="<?= $bookingID ?>">
                                <div class="employee-container">
                                    <?php foreach ($booking['Employees'] as $employeePhoneNo) : ?>
                                        <div class="employee-row">
                                            <select class="form-control" name="employeePhoneNo[]">
                                                <option value="">Select Employee</option>
                                                <?php foreach ($allEmployees as $emp) : ?>
                                                    <option value="<?= $emp['PhoneNo'] ?>" <?= $employeePhoneNo === $emp['PhoneNo'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($emp['Name']) . ' (' . $emp['EmployeeType'] . ')' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="remove-employee btn btn-danger btn-sm">Remove</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="pricing-container">
                                    <div class="mb-3">
                                        <label for="truckSize" class="form-label">Truck Size:</label>
                                        <input type="text" class="form-control" name="truckSize" value="<?= htmlspecialchars($booking['TruckSize']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="calloutFee" the form-label">Callout Fee:</label>
                                        <input type="number" class="form-control" name="calloutFee" value="<?= htmlspecialchars($booking['CalloutFee']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="rate" class="form-label">Rate:</label>
                                        <input type="number" class="form-control" name="rate" value="<?= htmlspecialchars($booking['Rate']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="deposit" class="form-label">Deposit:</label>
                                        <input type="text" class="form-control" name="deposit" value="<?= htmlspecialchars($booking['Deposit']) ?>" required>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary add-employee">Add Employee</button>
                                <input type="time" name="startTime" value="<?= htmlspecialchars($booking['TimeSlot']) ?>" required>
                                <button type="submit" class="btn btn-success">Update</button>
                            </form>
                            <!-- Within the loop of each booking -->
                            <button class="btn btn-warning unassign-all-btn" data-booking-id="<?= $bookingID ?>">Unassign All</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).on('click', '.unassign-all-btn', function() {
            if (confirm('Are you sure you want to unassign all employees from this booking?')) {
                const bookingId = $(this).data('booking-id');
                $.post('unassignAll.php', {
                    bookingID: bookingId
                }, function(data) {
                    if (data.success) {
                        alert(data.message);
                        location.reload(); // Reload the page to reflect the changes
                    } else {
                        alert(data.message);
                    }
                }, 'json');
            }
        });

        $(document).ready(function() {
            const employees = <?= json_encode($allEmployees); ?>;

            // Function to add employee dropdown
            function addEmployeeDropdown(container) {
                const newElem = $('<div class="employee-row">').append(
                    $('<select class="form-control" name="employeePhoneNo[]">').append(
                        $('<option>').text('Select Employee').val(''),
                        employees.map(emp => $('<option>').val(emp.PhoneNo).text(emp.Name + ' (' + emp.EmployeeType + ')'))
                    ),
                    $('<span class="remove-employee btn btn-danger btn-sm">Remove</span>')
                );
                container.append(newElem);
            }

            // Initialize two default employee fields for each form, if there are none
            $('.update-assign-form').each(function() {
                const container = $(this).find('.employee-container');
                if (container.find('.employee-row').length === 0) { // Add two default dropdowns only if there are none
                    addEmployeeDropdown(container);
                    addEmployeeDropdown(container);
                }
            });

            // Handle click to add more employee fields
            $('.add-employee').click(function() {
                const container = $(this).siblings('.employee-container');
                addEmployeeDropdown(container);
            });

            // Handle removal of employee fields
            $(document).on('click', '.remove-employee', function() {
                $(this).parent().remove();
            });

            // Toggle form visibility and button for update
            $('.update-assigned-btn').click(function() {
                const bookingId = $(this).data('booking-id');
                $('.update-assign-form[data-booking-id="' + bookingId + '"]').toggle();
                $(this).toggle(); // Hide the update button
            });

            // Handle form submission for update
            $('.update-assign-form').submit(function(event) {
                event.preventDefault();
                const form = $(this);
                const bookingId = form.data('booking-id');
                $.post('updateBooking.php', form.serialize() + '&bookingID=' + bookingId, function(data) {
                    if (data.success) {
                        alert(data.message); // Show success message
                        location.reload(); // Reload the page to reflect the updated assignments
                    } else {
                        alert(data.message); // Show error message
                    }
                }, 'json');
                // No need to hide the form or toggle buttons since we are reloading the page
            });

        });
    </script>

</body>

</html>