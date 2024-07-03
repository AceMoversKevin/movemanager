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
                        <option value="today">Today</option>
                        <option value="next_day">Next Day</option>
                        <option value="next_2_days">Next 2 Days</option>
                        <option value="next_3_days">Next 3 Days</option>
                        <option value="next_week">Next Week</option>
                        <option value="next_month">Next Month</option>
                        <option value="next_year">Next Year</option>
                        <option value="current_month">Current Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="current_year">Current Year</option>
                        <option value="last_year">Last Year</option>
                        <option value="date_range">Date Range</option>
                    </select>
                    <select name="sort_column" id="sort_column" class="form-control" style="display:inline-block; width: auto;">
                        <option value="MovingDate" <?= $sortColumn == 'MovingDate' ? 'selected' : '' ?>>Sort by Moving Date</option>
                        <option value="BookingDate" <?= $sortColumn == 'BookingDate' ? 'selected' : '' ?>>Sort by Booking Date</option>
                        <option value="BookingID" <?= $sortColumn == 'BookingID' ? 'selected' : '' ?>>Sort by Booking ID</option>
                    </select>
                    <input type="date" name="start_date" id="start_date" class="form-control" style="display:inline-block; width: auto;">
                    <input type="date" name="end_date" id="end_date" class="form-control" style="display:inline-block; width: auto;">
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
                    <a href="extractBookingInfo.php" class="btn btn-secondary">Extract Booking Info</a>
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
                                <?php if (in_array('TruckSize', $visibleColumns)) : ?><th class="sortable" data-sort="TruckSize">Truck Size</th><?php endif; ?>
                                <?php if (in_array('CalloutFee', $visibleColumns)) : ?><th class="sortable" data-sort="CalloutFee">Callout Fee</th><?php endif; ?>
                                <?php if (in_array('Rate', $visibleColumns)) : ?><th class="sortable" data-sort="Rate">Rate</th><?php endif; ?>
                                <?php if (in_array('Deposit', $visibleColumns)) : ?><th class="sortable" data-sort="Deposit">Deposit</th><?php endif; ?>
                                <?php if (in_array('TimeSlot', $visibleColumns)) : ?><th class="sortable" data-sort="TimeSlot">Time Slot</th><?php endif; ?>
                                <?php if (in_array('AdditionalDetails', $visibleColumns)) : ?><th class="sortable" data-sort="AdditionalDetails">Additional Details</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()) : ?>
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
                                    <?php if (in_array('TruckSize', $visibleColumns)) : ?><td class="editable" data-field="TruckSize" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['TruckSize']) ?></td><?php endif; ?>
                                    <?php if (in_array('CalloutFee', $visibleColumns)) : ?><td class="editable" data-field="CalloutFee" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['CalloutFee']) ?></td><?php endif; ?>
                                    <?php if (in_array('Rate', $visibleColumns)) : ?><td class="editable" data-field="Rate" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Rate']) ?></td><?php endif; ?>
                                    <?php if (in_array('Deposit', $visibleColumns)) : ?><td class="editable" data-field="Deposit" data-id="<?= $row['BookingID'] ?>"><?= htmlspecialchars($row['Deposit']) ?></td><?php endif; ?>
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