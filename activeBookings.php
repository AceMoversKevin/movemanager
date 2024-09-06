<?php
session_start();
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Fetch sorting criteria from the query parameters
$sortColumn = isset($_GET['sort_column']) ? $_GET['sort_column'] : 'BookingID';
$sortOrder = isset($_GET['sort_order']) && $_GET['sort_order'] === 'asc' ? 'asc' : 'desc';

// Handle search term and date filter
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Handle column visibility
$allColumns = ['Name', 'Email', 'Phone', 'Bedrooms', 'BookingDate', 'MovingDate', 'PickupLocation', 'DropoffLocation', 'TruckSize', 'CalloutFee', 'Rate', 'Deposit', 'TimeSlot', 'AdditionalDetails'];
$visibleColumns = isset($_GET['visible_columns']) ? (is_array($_GET['visible_columns']) ? $_GET['visible_columns'] : explode(',', $_GET['visible_columns'])) : $allColumns;

// Handle pagination
$itemsPerPage = 30; // Adjust this value as needed
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $itemsPerPage;

// Fetch active bookings from the database
$query = "SELECT * FROM Bookings WHERE isActive = 1";

// Add search term filtering
if ($searchTerm) {
    $query .= " AND (Name LIKE '%$searchTerm%' OR Email LIKE '%$searchTerm%' OR Phone LIKE '%$searchTerm%' OR Bedrooms LIKE '%$searchTerm%' OR BookingDate LIKE '%$searchTerm%' OR MovingDate LIKE '%$searchTerm%' OR PickupLocation LIKE '%$searchTerm%' OR DropoffLocation LIKE '%$searchTerm%' OR TruckSize LIKE '%$searchTerm%' OR CalloutFee LIKE '%$searchTerm%' OR Rate LIKE '%$searchTerm%' OR Deposit LIKE '%$searchTerm%' OR TimeSlot LIKE '%$searchTerm%' OR AdditionalDetails LIKE '%$searchTerm%')";
}

// Add date filter
if ($dateFilter) {
    switch ($dateFilter) {
        case 'today':
            $query .= " AND DATE(MovingDate) = CURDATE()";
            break;
        case 'next_day':
            $query .= " AND DATE(MovingDate) = CURDATE() + INTERVAL 1 DAY";
            break;
        case 'next_2_days':
            $query .= " AND DATE(MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 2 DAY";
            break;
        case 'next_3_days':
            $query .= " AND DATE(MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY";
            break;
        case 'next_week':
            $query .= " AND DATE(MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 WEEK";
            break;
        case 'next_month':
            $query .= " AND DATE(MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 MONTH";
            break;
        case 'next_year':
            $query .= " AND DATE(MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 YEAR";
            break;
        case 'current_month':
            $query .= " AND MONTH(MovingDate) = MONTH(CURRENT_DATE()) AND YEAR(MovingDate) = YEAR(CURRENT_DATE())";
            break;
        case 'last_month':
            $query .= " AND MONTH(MovingDate) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(MovingDate) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)";
            break;
        case 'current_year':
            $query .= " AND YEAR(MovingDate) = YEAR(CURRENT_DATE())";
            break;
        case 'last_year':
            $query .= " AND YEAR(MovingDate) = YEAR(CURRENT_DATE() - INTERVAL 1 YEAR)";
            break;
        case 'date_range':
            if ($startDate && $endDate) {
                $query .= " AND MovingDate BETWEEN '$startDate' AND '$endDate'";
            }
            break;
        default:
            break;
    }
}

// Add sorting criteria to the query
$query .= " ORDER BY $sortColumn $sortOrder";

// Get the total number of records for pagination
$totalQuery = "SELECT COUNT(*) as total FROM (" . $query . ") as subquery";
$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetch_assoc();
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $itemsPerPage);

// Add pagination to the query
$query .= " LIMIT $itemsPerPage OFFSET $offset";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Active Bookings</title>
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

        .table-responsive {
            overflow-x: auto;
        }

        .table th,
        .table td {
            white-space: nowrap;
        }

        .details-short {
            display: inline;
        }

        .details-full {
            display: none;
        }

        .read-more,
        .read-less {
            cursor: pointer;
            color: blue;
        }
    </style>

    <style>
        .pagination-container {
            display: flex;
            justify-content: center;
            overflow-x: auto;
            /* Allow horizontal scrolling if the pagination is too wide */
            white-space: nowrap;
            /* Prevent the pagination from wrapping to the next line */
        }

        .pagination-container::-webkit-scrollbar {
            display: none;
            /* Optional: Hide the scrollbar for better UX */
        }

        .pagination {
            flex-wrap: nowrap;
            /* Ensure pagination stays in one line */
        }

        .pagination-item {
            min-width: 40px;
            /* Ensures each pagination link has a minimum width */
        }

        @media (max-width: 768px) {
            .pagination-item {
                min-width: 30px;
                /* Adjust for smaller screens */
            }
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
                    <h1 class="h2" id="Main-Heading">Active Bookings</h1>
                </div>

                <form method="GET" action="activeBookings.php" class="mb-3">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search Bookings" class="form-control" style="display:inline-block; width: auto;">
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
                        <option value="BookingID" <?= $sortColumn == 'BookingID' ? 'selected' : '' ?>>Sort by Booking ID</option>
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
                    <button type="button" onclick="window.location.href='activeBookings.php'" class="btn btn-outline-secondary">Reset</button>
                    <a href="extractBookingInfo.php?sort_column=<?= htmlspecialchars($sortColumn) ?>&sort_order=<?= htmlspecialchars($sortOrder) ?>&search=<?= htmlspecialchars($searchTerm) ?>&date_filter=<?= htmlspecialchars($dateFilter) ?>&start_date=<?= htmlspecialchars($startDate) ?>&end_date=<?= htmlspecialchars($endDate) ?>" class="btn btn-secondary">Extract Booking Info</a>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th></th> <!-- New column for the button -->
                                <?php if (in_array('Name', $visibleColumns)) : ?><th class="sortable" data-sort="Name">Name</th><?php endif; ?>
                                <?php if (in_array('Bedrooms', $visibleColumns)) : ?><th class="sortable" data-sort="Bedrooms">Bedrooms</th><?php endif; ?>
                                <?php if (in_array('PickupLocation', $visibleColumns)) : ?><th class="sortable" data-sort="PickupLocation">Pickup Location</th><?php endif; ?>
                                <?php if (in_array('DropoffLocation', $visibleColumns)) : ?><th class="sortable" data-sort="DropoffLocation">Dropoff Location</th><?php endif; ?>
                                <?php if (in_array('TruckSize', $visibleColumns)) : ?><th class="sortable" data-sort="TruckSize">Truck Size</th><?php endif; ?>
                                <?php if (in_array('CalloutFee', $visibleColumns)) : ?><th class="sortable" data-sort="CalloutFee">Callout Fee</th><?php endif; ?>
                                <?php if (in_array('Rate', $visibleColumns)) : ?><th class="sortable" data-sort="Rate">Rate</th><?php endif; ?>
                                <?php if (in_array('Deposit', $visibleColumns)) : ?><th class="sortable" data-sort="Deposit">Deposit</th><?php endif; ?>
                                <?php if (in_array('Email', $visibleColumns)) : ?><th class="sortable" data-sort="Email">Email</th><?php endif; ?>
                                <?php if (in_array('Phone', $visibleColumns)) : ?><th class="sortable" data-sort="Phone">Phone</th><?php endif; ?>
                                <!-- Remaining columns -->
                                <?php if (in_array('BookingDate', $visibleColumns)) : ?><th class="sortable" data-sort="BookingDate">Booking Date</th><?php endif; ?>
                                <?php if (in_array('MovingDate', $visibleColumns)) : ?><th class="sortable" data-sort="MovingDate">Moving Date</th><?php endif; ?>
                                <?php if (in_array('TimeSlot', $visibleColumns)) : ?>
                                    <td class="editable-time" data-field="TimeSlot" data-id="<?= $row['BookingID'] ?>">
                                        <input type="time" class="form-control" value="<?= htmlspecialchars($row['TimeSlot']) ?>" readonly>
                                    </td>
                                <?php endif; ?>
                                <?php if (in_array('AdditionalDetails', $visibleColumns)) : ?><th class="sortable" data-sort="AdditionalDetails">Additional Details</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()) : ?>
                                <tr>
                                    <td>
                                        <a href="bookingsConnections.php?booking_id=<?= $row['BookingID'] ?>" class="btn btn-primary btn-sm">Check Connection</a>
                                    </td>
                                    <!-- Adjust the order of corresponding data cells (td) -->
                                    <?php if (in_array('Name', $visibleColumns)) : ?><td class="editable" data-field="Name" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Name']) ?></td><?php endif; ?>
                                    <?php if (in_array('Bedrooms', $visibleColumns)) : ?><td class="editable" data-field="Bedrooms" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Bedrooms']) ?></td><?php endif; ?>
                                    <?php if (in_array('PickupLocation', $visibleColumns)) : ?><td class="editable" data-field="PickupLocation" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['PickupLocation']) ?></td><?php endif; ?>
                                    <?php if (in_array('DropoffLocation', $visibleColumns)) : ?><td class="editable" data-field="DropoffLocation" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['DropoffLocation']) ?></td><?php endif; ?>
                                    <?php if (in_array('TruckSize', $visibleColumns)) : ?><td class="editable" data-field="TruckSize" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['TruckSize']) ?></td><?php endif; ?>
                                    <?php if (in_array('CalloutFee', $visibleColumns)) : ?><td class="editable" data-field="CalloutFee" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['CalloutFee']) ?></td><?php endif; ?>
                                    <?php if (in_array('Rate', $visibleColumns)) : ?><td class="editable" data-field="Rate" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Rate']) ?></td><?php endif; ?>
                                    <?php if (in_array('Deposit', $visibleColumns)) : ?><td class="editable" data-field="Deposit" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Deposit']) ?></td><?php endif; ?>
                                    <?php if (in_array('Email', $visibleColumns)) : ?><td class="editable" data-field="Email" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Email']) ?></td><?php endif; ?>
                                    <?php if (in_array('Phone', $visibleColumns)) : ?><td class="editable" data-field="Phone" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Phone']) ?></td><?php endif; ?>
                                    <!-- Remaining columns -->
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
                                    <?php if (in_array('TimeSlot', $visibleColumns)) : ?>
                                        <td class="editable-time" data-field="TimeSlot" data-id="<?= $row['BookingID'] ?>">
                                            <input type="time" class="form-control" value="<?= htmlspecialchars($row['TimeSlot']) ?>" readonly>
                                        </td>
                                    <?php endif; ?>
                                    <?php if (in_array('AdditionalDetails', $visibleColumns)) : ?>
                                        <td class="editable" data-field="AdditionalDetails" data-id="<?= $row['BookingID'] ?>">
                                            <span class="details-short"><?= htmlspecialchars(substr($row['AdditionalDetails'], 0, 100)) ?></span>
                                            <span class="details-full"><?= htmlspecialchars($row['AdditionalDetails']) ?></span>
                                            <span class="read-more">Read More</span>
                                            <span class="read-less">Read Less</span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-container">
                    <nav aria-label="Page navigation example">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1) : ?>
                                <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>&sort_column=<?= htmlspecialchars($sortColumn) ?>&sort_order=<?= htmlspecialchars($sortOrder) ?>&search=<?= htmlspecialchars($searchTerm) ?>&date_filter=<?= htmlspecialchars($dateFilter) ?>&start_date=<?= htmlspecialchars($startDate) ?>&end_date=<?= htmlspecialchars($endDate) ?>&visible_columns=<?= implode(',', $visibleColumns) ?>">Previous</a></li>
                            <?php endif; ?>

                            <!-- Always show the first page -->
                            <li class="page-item <?= $page == 1 ? 'active' : '' ?>"><a class="page-link" href="?page=1">1</a></li>

                            <!-- Show ellipsis if current page is beyond page 3 -->
                            <?php if ($page > 3) : ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>

                            <!-- Show pages around the current page -->
                            <?php for ($i = max(2, $page - 2); $i <= min($page + 2, $totalPages - 1); $i++) : ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
                            <?php endfor; ?>

                            <!-- Show ellipsis if current page is far from the last page -->
                            <?php if ($page < $totalPages - 2) : ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>

                            <!-- Always show the last page -->
                            <?php if ($totalPages > 1) : ?>
                                <li class="page-item <?= $page == $totalPages ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $totalPages ?>"><?= $totalPages ?></a></li>
                            <?php endif; ?>

                            <?php if ($page < $totalPages) : ?>
                                <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>&sort_column=<?= htmlspecialchars($sortColumn) ?>&sort_order=<?= htmlspecialchars($sortOrder) ?>&search=<?= htmlspecialchars($searchTerm) ?>&date_filter=<?= htmlspecialchars($dateFilter) ?>&start_date=<?= htmlspecialchars($startDate) ?>&end_date=<?= htmlspecialchars($endDate) ?>&visible_columns=<?= implode(',', $visibleColumns) ?>">Next</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>

            </main>
        </div>
    </div>

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

            // Make time fields editable
            $('.editable-time').on('dblclick', function() {
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
                var order = '<?= $sortOrder ?>' === 'asc' ? 'desc' : 'asc';
                var currentUrl = window.location.href.split('?')[0];
                var newUrl = currentUrl + '?sort_column=' + column + '&sort_order=' + order +
                    '&search=' + encodeURIComponent('<?= $searchTerm ?>') +
                    '&date_filter=' + encodeURIComponent('<?= $dateFilter ?>') +
                    '&start_date=' + encodeURIComponent('<?= $startDate ?>') +
                    '&end_date=' + encodeURIComponent('<?= $endDate ?>') +
                    '&visible_columns=' + encodeURIComponent('<?= implode(',', $visibleColumns) ?>');
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

            // Handle read more/read less functionality
            $('.read-more').on('click', function() {
                $(this).siblings('.details-full').show();
                $(this).siblings('.details-short').hide();
                $(this).hide();
                $(this).siblings('.read-less').show();
            });

            $('.read-less').on('click', function() {
                $(this).siblings('.details-full').hide();
                $(this).siblings('.details-short').show();
                $(this).hide();
                $(this).siblings('.read-more').show();
            });

            // Initialize the read more/read less functionality
            $('td[data-field="AdditionalDetails"]').each(function() {
                if ($(this).find('.details-full').text().length <= 100) {
                    $(this).find('.read-more, .read-less').hide();
                }
            });
        });
    </script>
</body>

</html>