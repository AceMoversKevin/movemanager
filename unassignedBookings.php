<?php
session_start();
include 'db.php'; // Ensure this points to your database connection file

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

// Fetch unassigned bookings
$bookingsQuery = "SELECT * FROM Bookings WHERE BookingID NOT IN (SELECT BookingID FROM Bookings_Employees)";
$bookings = $conn->query($bookingsQuery);

// Fetch available employees (Drivers and Helpers)
$employeesQuery = "SELECT PhoneNo, Name, EmployeeType FROM Employees WHERE EmployeeType IN ('Driver', 'Helper')";
$employeesResult = $conn->query($employeesQuery);
$employees = [];
while ($emp = $employeesResult->fetch_assoc()) {
    $employees[] = $emp;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unassigned Bookings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .card {
            margin-bottom: 20px;
        }

        .employee-row {
            margin-bottom: 10px;
            position: relative;
        }

        .remove-employee {
            position: absolute;
            right: 0;
            top: 0;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <header class="mb-3 py-3">
        <div class="container-fluid">
            <div class="row justify-content-between align-items-center">
                <div class="col-md-6 col-lg-4 user-info">
                    <img src="user.svg" alt="User icon">
                    <span><?= htmlspecialchars($_SESSION['username']); ?></span>
                </div>
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
        <h1>Unassigned Bookings</h1>
        <div class="row">
            <?php while ($row = $bookings->fetch_assoc()) : ?>
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Booking ID: <?= htmlspecialchars($row['BookingID']) ?></h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">Name: <?= htmlspecialchars($row['Name']) ?></li>
                                <li class="list-group-item">Email: <?= htmlspecialchars($row['Email']) ?></li>
                                <li class="list-group-item">Phone: <?= htmlspecialchars($row['Phone']) ?></li>
                                <li class="list-group-item">Bedrooms: <?= htmlspecialchars($row['Bedrooms']) ?></li>
                                <li class="list-group-item">Booking Date: <?= htmlspecialchars($row['BookingDate']) ?></li>
                                <li class="list-group-item">Moving Date: <?= htmlspecialchars($row['MovingDate']) ?></li>
                                <li class="list-group-item">Pickup Location: <?= htmlspecialchars($row['PickupLocation']) ?></li>
                                <li class="list-group-item">Dropoff Location: <?= htmlspecialchars($row['DropoffLocation']) ?></li>
                            </ul>
                            <button class="btn btn-primary my-2 assign-btn" data-id="<?= $row['BookingID'] ?>">Assign Employees</button>
                            <form id="assignForm<?= $row['BookingID'] ?>" class="assign-form" style="display:none;" data-id="<?= $row['BookingID'] ?>">
                                <input type="hidden" name="bookingID" value="<?= $row['BookingID'] ?>">
                                <div class="employee-container">
                                    <!-- Dynamic employee assignment rows will be inserted here -->
                                </div>
                                <button type="button" class="btn btn-secondary add-employee">Add Employee</button>
                                <input type="time" name="startTime" required>
                                <button type="submit" class="btn btn-success">Assign</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            const employees = <?= json_encode($employees); ?>;

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

            // Initialize two default employee fields for each form
            $('.assign-form').each(function() {
                const container = $(this).find('.employee-container');
                addEmployeeDropdown(container);
                addEmployeeDropdown(container);
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

            // Toggle form visibility and button
            $('.assign-btn').click(function() {
                const formId = '#assignForm' + $(this).data('id');
                $(formId).toggle();
                $(this).toggle(); // Hide the assign button
            });

            // Handle form submission
            $('form').submit(function(event) {
                event.preventDefault();
                const form = $(this);
                $.post('assignEmployee.php', form.serialize(), function(data) {
                    if (data.success) {
                        alert(data.message); // Show success message
                        location.reload(); // Reload the page to reflect the updated assignments
                    } else {
                        alert(data.message); // Show error message
                    }
                }, 'json');
                form.hide();
                $('.assign-btn[data-id="' + form.data('id') + '"]').show(); // Optionally show the assign button again if needed
            });
        });
    </script>
</body>

</html>