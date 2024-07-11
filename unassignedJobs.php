<?php
session_start();
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Fetch sorting criteria from the query parameters
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'MovingDate';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc';

// Handle search term and date filter
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Handle column visibility
$allColumns = ['Name', 'Email', 'Phone', 'Bedrooms', 'BookingDate', 'MovingDate', 'PickupLocation', 'DropoffLocation'];
$visibleColumns = isset($_GET['visible_columns']) ? (is_array($_GET['visible_columns']) ? $_GET['visible_columns'] : explode(',', $_GET['visible_columns'])) : $allColumns;

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Base query to select bookings with less than 2 employees assigned
$baseQuery = "
    SELECT b.*, COUNT(ba.BookingID) as AssignedCount
    FROM Bookings b
    LEFT JOIN BookingAssignments ba ON b.BookingID = ba.BookingID
    WHERE b.isActive = 1
";

// Add search term filtering
if ($searchTerm) {
    $baseQuery .= " AND (b.Name LIKE '%$searchTerm%' OR b.Email LIKE '%$searchTerm%' OR b.Phone LIKE '%$searchTerm%' OR b.Bedrooms LIKE '%$searchTerm%' OR b.BookingDate LIKE '%$searchTerm%' OR b.MovingDate LIKE '%$searchTerm%' OR b.PickupLocation LIKE '%$searchTerm%' OR b.DropoffLocation LIKE '%$searchTerm%')";
}

// Add date filter
if ($dateFilter) {
    switch ($dateFilter) {
        case 'today':
            $baseQuery .= " AND DATE(b.MovingDate) = CURDATE()";
            break;
        case 'next_day':
            $baseQuery .= " AND DATE(b.MovingDate) = CURDATE() + INTERVAL 1 DAY";
            break;
        case 'next_2_days':
            $baseQuery .= " AND DATE(b.MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 2 DAY";
            break;
        case 'next_3_days':
            $baseQuery .= " AND DATE(b.MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY";
            break;
        case 'next_week':
            $baseQuery .= " AND DATE(b.MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 WEEK";
            break;
        case 'next_month':
            $baseQuery .= " AND DATE(b.MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 MONTH";
            break;
        case 'next_year':
            $baseQuery .= " AND DATE(b.MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 YEAR";
            break;
        case 'current_month':
            $baseQuery .= " AND MONTH(b.MovingDate) = MONTH(CURRENT_DATE()) AND YEAR(b.MovingDate) = YEAR(CURRENT_DATE())";
            break;
        case 'last_month':
            $baseQuery .= " AND MONTH(b.MovingDate) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(b.MovingDate) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)";
            break;
        case 'current_year':
            $baseQuery .= " AND YEAR(b.MovingDate) = YEAR(CURRENT_DATE())";
            break;
        case 'last_year':
            $baseQuery .= " AND YEAR(b.MovingDate) = YEAR(CURRENT_DATE() - INTERVAL 1 YEAR)";
            break;
        case 'date_range':
            if ($startDate && $endDate) {
                $baseQuery .= " AND b.MovingDate BETWEEN '$startDate' AND '$endDate'";
            }
            break;
        default:
            break;
    }
}

// Query to get the total number of records
$totalQuery = "
    SELECT COUNT(*) as total 
    FROM ($baseQuery GROUP BY b.BookingID HAVING COUNT(ba.BookingID) < 2) as subquery
";
$totalResult = $conn->query($totalQuery);
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $recordsPerPage);

// Add grouping, having, sorting, and pagination to the base query
$query = "$baseQuery GROUP BY b.BookingID HAVING COUNT(ba.BookingID) < 2 ORDER BY $sortColumn $sortOrder LIMIT $offset, $recordsPerPage";
$result = $conn->query($query);

// Fetch available active Driver and Helper employees
$availableEmployeesQuery = "SELECT PhoneNo, Name, EmployeeType FROM Employees WHERE EmployeeType IN ('Driver', 'Helper') AND isActive = 1";
$availableEmployeesResult = $conn->query($availableEmployeesQuery);
$availableEmployees = $availableEmployeesResult->fetch_all(MYSQLI_ASSOC);
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Unassigned Jobs</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        .editable {
            cursor: pointer;
        }

        .editable:hover {
            background-color: #f0f0f0;
        }

        .sortable:hover {
            cursor: pointer;
            text-decoration: underline;
        }
    </style>
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
                    <h1 class="h2" id="Main-Heading">Unassigned Jobs</h1>
                </div>

                <form method="GET" action="unassignedJobs.php" class="mb-3">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search Jobs" class="form-control" style="display:inline-block; width: auto;">
                    <select name="date_filter" id="date_filter" class="form-control" style="display:inline-block; width: auto;">
                        <option value="">Select Date Filter</option>
                        <option value="today" <?= $dateFilter == 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="next_day" <?= $dateFilter == 'next_day' ? 'selected' : '' ?>>Next Day</option>
                        <option value="next_2_days" <?= $dateFilter == 'next_2_days' ? 'selected' : '' ?>>Next 2 Days</option>
                        <option value="next_3_days" <?= $dateFilter == 'next_3_days' ? 'selected' : '' ?>>Next 3 Days</option>
                        <option value="next_week" <?= $dateFilter == 'next_week' ? 'selected' : '' ?>>Next Week</option>
                        <option value="next_month" <?= $dateFilter == 'next_month' ? 'selected' : '' ?>>Next Month</option>
                        <option value="next_year" <?= $dateFilter == 'next_year' ? 'selected' : '' ?>>Next Year</option>
                        <option value="current_month" <?= $dateFilter == 'current_month' ? 'selected' : '' ?>>Current Month</option>
                        <option value="last_month" <?= $dateFilter == 'last_month' ? 'selected' : '' ?>>Last Month</option>
                        <option value="current_year" <?= $dateFilter == 'current_year' ? 'selected' : '' ?>>Current Year</option>
                        <option value="last_year" <?= $dateFilter == 'last_year' ? 'selected' : '' ?>>Last Year</option>
                        <option value="date_range" <?= $dateFilter == 'date_range' ? 'selected' : '' ?>>Date Range</option>
                    </select>
                    <select name="sort_column" id="sort_column" class="form-control" style="display:inline-block; width: auto;">
                        <option value="MovingDate" <?= $sortColumn == 'MovingDate' ? 'selected' : '' ?>>Sort by Moving Date</option>
                        <option value="BookingDate" <?= $sortColumn == 'BookingDate' ? 'selected' : '' ?>>Sort by Booking Date</option>
                    </select>
                    <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control" style="display:inline-block; width: auto;">
                    <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control" style="display:inline-block; width: auto;">
                    <div class="mb-2">
                        <button type="button" id="select_all" class="btn btn-outline-secondary btn-sm">Select All</button>
                        <button type="button" id="deselect_all" class="btn btn-outline-secondary btn-sm">Deselect All</button>
                    </div>
                    <p>
                        <a class="btn btn-secondary" data-toggle="collapse" href="#collapseColumnSelector" role="button" aria-expanded="false" aria-controls="collapseColumnSelector">
                            Show/Hide Columns
                        </a>
                    </p>
                    <div class="collapse" id="collapseColumnSelector">
                        <div class="form-check">
                            <?php
                            foreach ($allColumns as $column) {
                                $checked = in_array($column, $visibleColumns) ? 'checked' : '';
                                echo "<div class='form-check'>
                                        <input class='form-check-input' type='checkbox' name='visible_columns[]' value='$column' id='$column' $checked>
                                        <label class='form-check-label' for='$column'>$column</label>
                                      </div>";
                            }
                            ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <button type="button" onclick="window.location.href='unassignedJobs.php'" class="btn btn-outline-secondary">Reset</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <?php if (in_array('Name', $visibleColumns)) : ?><th class="sortable" data-sort="Name">Name</th><?php endif; ?>
                                <?php if (in_array('Email', $visibleColumns)) : ?><th class="sortable" data-sort="Email">Email</th><?php endif; ?>
                                <?php if (in_array('Phone', $visibleColumns)) : ?><th class="sortable" data-sort="Phone">Phone</th><?php endif; ?>
                                <?php if (in_array('Bedrooms', $visibleColumns)) : ?><th class="sortable" data-sort="Bedrooms">Bedrooms</th><?php endif; ?>
                                <?php if (in_array('BookingDate', $visibleColumns)) : ?><th class="sortable" data-sort="BookingDate">Booking Date</th><?php endif; ?>
                                <?php if (in_array('MovingDate', $visibleColumns)) : ?><th class="sortable" data-sort="MovingDate">Moving Date</th><?php endif; ?>
                                <?php if (in_array('PickupLocation', $visibleColumns)) : ?><th class="sortable" data-sort="PickupLocation">Pickup Location</th><?php endif; ?>
                                <?php if (in_array('DropoffLocation', $visibleColumns)) : ?><th class="sortable" data-sort="DropoffLocation">Dropoff Location</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()) :
                                // Fetch assigned employees for this booking
                                $assignedEmployeesQuery = "
                                    SELECT e.Name 
                                    FROM Employees e
                                    INNER JOIN BookingAssignments ba ON e.PhoneNo = ba.EmployeePhoneNo
                                    WHERE ba.BookingID = ?
                                ";
                                $assignedStmt = $conn->prepare($assignedEmployeesQuery);
                                $assignedStmt->bind_param("i", $row['BookingID']);
                                $assignedStmt->execute();
                                $assignedResult = $assignedStmt->get_result();
                                $assignedEmployees = $assignedResult->fetch_all(MYSQLI_ASSOC); ?>
                                <tr>
                                    <?php if (in_array('Name', $visibleColumns)) : ?><td class="editable" data-field="Name" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Name']) ?></td><?php endif; ?>
                                    <?php if (in_array('Email', $visibleColumns)) : ?><td class="editable" data-field="Email" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Email']) ?></td><?php endif; ?>
                                    <?php if (in_array('Phone', $visibleColumns)) : ?><td class="editable" data-field="Phone" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Phone']) ?></td><?php endif; ?>
                                    <?php if (in_array('Bedrooms', $visibleColumns)) : ?><td class="editable" data-field="Bedrooms" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Bedrooms']) ?></td><?php endif; ?>
                                    <?php if (in_array('BookingDate', $visibleColumns)) : ?>
                                        <td class="editable-date" data-field="BookingDate" data-id="<?= $row['BookingID'] ?>">
                                            <input type="date" class="form-control" value="<?= htmlspecialchars($row['BookingDate']) ?>" readonly>
                                        </td>
                                    <?php endif; ?>
                                    <?php if (in_array('MovingDate', $visibleColumns)) : ?>
                                        <td class="editable-date" data-field="MovingDate" data-id="<?= $row['BookingID'] ?>">
                                            <input type="date" class="form-control" value="<?= htmlspecialchars($row['MovingDate']) ?>" readonly>
                                        </td>
                                    <?php endif; ?>
                                    <?php if (in_array('PickupLocation', $visibleColumns)) : ?><td class="editable" data-field="PickupLocation" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['PickupLocation']) ?></td><?php endif; ?>
                                    <?php if (in_array('DropoffLocation', $visibleColumns)) : ?><td class="editable" data-field="DropoffLocation" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['DropoffLocation']) ?></td><?php endif; ?>
                                    <!-- Display assigned employees or a message if there are none -->
                                    <?php if (count($assignedEmployees) > 0) : ?>
                                        <td>
                                            <strong>Assigned Employees:</strong>
                                            <ul>
                                                <?php foreach ($assignedEmployees as $employee) : ?>
                                                    <li><?= htmlspecialchars($employee['Name']) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </td>
                                    <?php else : ?>
                                        <td>No assigned employees</td>
                                    <?php endif; ?>
                                    <!-- Assign job button -->
                                    <td>
                                        <button class="btn btn-outline-success btn-sm assign-job" data-toggle="modal" data-target="#assignJobsModal<?= $row['BookingID'] ?>">
                                            Assign Job
                                        </button>
                                    </td>
                                </tr>

                                <!-- Modal for assigning Employees -->
                                <div class="modal fade" id="assignJobsModal<?= $row['BookingID'] ?>" tabindex="-1" role="dialog" aria-labelledby="modalLabel<?= $row['BookingID'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="modalLabel<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Name']) ?>'s Job</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <!-- Here, you would show the assigned employees or a message indicating that there are none -->
                                                <?php if (count($assignedEmployees) > 0) : ?>
                                                    <p><strong>Assigned Employees:</strong></p>
                                                    <ul>
                                                        <?php foreach ($assignedEmployees as $employee) : ?>
                                                            <li><?= htmlspecialchars($employee['Name']) ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else : ?>
                                                    <p>No assigned employees</p>
                                                <?php endif; ?>

                                                <div id="employeeFieldContainer<?= $row['BookingID'] ?>">

                                                </div>

                                            </div>
                                            <div class="modal-footer">
                                                <!-- Button to add a new employee assignment field -->
                                                <button type="button" class="btn btn-primary add-employee-btn" data-bookingid="<?= $row['BookingID'] ?>">Add Employee</button>
                                                <!-- Button to confirm assignments -->
                                                <button type="button" class="btn btn-success confirm-assignment-btn" data-bookingid="<?= $row['BookingID'] ?>">Confirm Assignment</button>
                                                <!-- The footer can contain buttons like 'Save Changes' or 'Close' -->
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>

                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Links -->
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1) : ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&sort=<?= $sortColumn ?>&order=<?= $sortOrder ?>&search=<?= $searchTerm ?>&date_filter=<?= $dateFilter ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&visible_columns=<?= implode(',', $visibleColumns) ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++) : ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&sort=<?= $sortColumn ?>&order=<?= $sortOrder ?>&search=<?= $searchTerm ?>&date_filter=<?= $dateFilter ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&visible_columns=<?= implode(',', $visibleColumns) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages) : ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&sort=<?= $sortColumn ?>&order=<?= $sortOrder ?>&search=<?= $searchTerm ?>&date_filter=<?= $dateFilter ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&visible_columns=<?= implode(',', $visibleColumns) ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </main>
        </div>
    </div>

    <script>
        var availableEmployees = <?php echo json_encode($availableEmployees); ?>;
    </script>

    <script src="assignEmployeeModal.js"></script>
    <script>
        $(document).ready(function() {
            // Make table cells editable
            $('.editable').on('dblclick', function() {
                var $td = $(this);
                var originalValue = $td.text();
                var field = $td.data('field');
                var bookingId = $td.data('id');

                var $input = $('<input>', {
                    type: 'text',
                    value: originalValue,
                    blur: function() {
                        var newValue = $input.val();
                        $td.text(newValue);

                        // Update the database with the new value
                        $.ajax({
                            url: 'update_booking.php',
                            method: 'POST',
                            data: {
                                booking_id: bookingId,
                                field: field,
                                value: newValue
                            },
                            success: function(response) {
                                console.log(response);
                            },
                            error: function(xhr, status, error) {
                                console.error(xhr.responseText);
                            }
                        });
                    },
                    keyup: function(e) {
                        if (e.which === 13) {
                            $input.blur();
                        }
                    }
                }).appendTo($td.empty()).focus();
            });

            // Make date fields editable
            $('.editable-date').on('dblclick', function() {
                var $td = $(this);
                var $input = $td.find('input');
                $input.prop('readonly', false).focus();

                $input.on('blur', function() {
                    var newValue = $input.val();
                    $input.prop('readonly', true);

                    var field = $td.data('field');
                    var bookingId = $td.data('id');

                    // Update the database with the new value
                    $.ajax({
                        url: 'update_booking.php',
                        method: 'POST',
                        data: {
                            booking_id: bookingId,
                            field: field,
                            value: newValue
                        },
                        success: function(response) {
                            console.log(response);
                        },
                        error: function(xhr, status, error) {
                            console.error(xhr.responseText);
                        }
                    });
                });

                $input.on('keyup', function(e) {
                    if (e.which === 13) { // Enter key
                        $input.blur();
                    }
                });
            });

            // Handle sorting
            $('.sortable').on('click', function() {
                var column = $(this).data('sort');
                var currentUrl = window.location.href.split('?')[0];
                var newUrl = currentUrl + '?sort=' + column + '&order=' + ('<?= $sortOrder ?>' === 'asc' ? 'desc' : 'asc') +
                    '&search=' + encodeURIComponent('<?= $searchTerm ?>') +
                    '&date_filter=' + encodeURIComponent('<?= $dateFilter ?>') +
                    '&start_date=' + encodeURIComponent('<?= $startDate ?>') +
                    '&end_date=' + encodeURIComponent('<?= $endDate ?>') +
                    '&visible_columns=' + encodeURIComponent('<?= implode(',', $visibleColumns) ?>') +
                    '&page=<?= $page ?>';
                window.location.href = newUrl;
            });

            // Date filter logic
            $('#date_filter').on('change', function() {
                var filter = $(this).val();
                if (filter === 'date_range') {
                    $('#start_date, #end_date').show();
                } else {
                    $('#start_date, #end_date').hide();
                }
            }).trigger('change');

            // Select All and Deselect All functionality
            $('#select_all').on('click', function() {
                $('.form-check-input').prop('checked', true);
            });

            $('#deselect_all').on('click', function() {
                $('.form-check-input').prop('checked', false);
            });

            // Close the modal when clicking outside of it
            $(document).on('click', '[data-dismiss="modal"]', function(e) {
                if ($(e.target).hasClass('modal')) {
                    $(e.target).modal('hide');
                }
            });
        });
    </script>
</body>

</html>