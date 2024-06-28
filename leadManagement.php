<?php
session_start();
require 'db.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}

// Fetch sorting criteria from the query parameters
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'lead_date';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc';

// Handle search term and date filter
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Handle column visibility
$allColumns = ['lead_id', 'lead_name', 'bedrooms', 'pickup', 'dropoff', 'lead_date', 'phone', 'email', 'details', 'booking_status', 'created_at', 'Source', 'AssignedTo'];
$visibleColumns = isset($_GET['visible_columns']) ? (is_array($_GET['visible_columns']) ? $_GET['visible_columns'] : explode(',', $_GET['visible_columns'])) : array_diff($allColumns, ['created_at']);

// Fetch leads from the database
$query = "SELECT * FROM leads WHERE 1=1";

// Add search term filtering
if ($searchTerm) {
    $query .= " AND (lead_id LIKE '%$searchTerm%' OR lead_name LIKE '%$searchTerm%' OR bedrooms LIKE '%$searchTerm%' OR pickup LIKE '%$searchTerm%' OR dropoff LIKE '%$searchTerm%' OR lead_date LIKE '%$searchTerm%' OR phone LIKE '%$searchTerm%' OR email LIKE '%$searchTerm%' OR details LIKE '%$searchTerm%' OR booking_status LIKE '%$searchTerm%' OR AssignedTo LIKE '%$searchTerm%' OR Source LIKE '%$searchTerm%')";
}

// Add date filter
if ($dateFilter) {
    switch ($dateFilter) {
        case 'today':
            $query .= " AND DATE(lead_date) = CURDATE()";
            break;
        case 'next_day':
            $query .= " AND DATE(lead_date) = CURDATE() + INTERVAL 1 DAY";
            break;
        case 'next_2_days':
            $query .= " AND DATE(lead_date) BETWEEN CURDATE() AND CURDATE() + INTERVAL 2 DAY";
            break;
        case 'next_3_days':
            $query .= " AND DATE(lead_date) BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY";
            break;
        case 'next_week':
            $query .= " AND DATE(lead_date) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 WEEK";
            break;
        case 'next_month':
            $query .= " AND DATE(lead_date) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 MONTH";
            break;
        case 'next_year':
            $query .= " AND DATE(lead_date) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 YEAR";
            break;
        case 'current_month':
            $query .= " AND MONTH(lead_date) = MONTH(CURRENT_DATE()) AND YEAR(lead_date) = YEAR(CURRENT_DATE())";
            break;
        case 'last_month':
            $query .= " AND MONTH(lead_date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(lead_date) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)";
            break;
        case 'current_year':
            $query .= " AND YEAR(lead_date) = YEAR(CURRENT_DATE())";
            break;
        case 'last_year':
            $query .= " AND YEAR(lead_date) = YEAR(CURRENT_DATE() - INTERVAL 1 YEAR)";
            break;
        case 'date_range':
            if ($startDate && $endDate) {
                $query .= " AND lead_date BETWEEN '$startDate' AND '$endDate'";
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
    <title>Leads Dashboard</title>
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

        .limited-text {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
        }

        .expanded-text {
            max-width: none;
            white-space: normal;
        }

        .show-more {
            cursor: pointer;
            color: blue;
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
                    <h1 class="h2" id="Main-Heading">Leads Dashboard</h1>
                </div>

                <form method="GET" action="leadManagement.php" class="mb-3">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search Leads" class="form-control" style="display:inline-block; width: auto;">
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
                        <option value="lead_date" <?= $sortColumn == 'lead_date' ? 'selected' : '' ?>>Sort by Lead Date</option>
                        <option value="created_at" <?= $sortColumn == 'created_at' ? 'selected' : '' ?>>Sort by Created At</option>
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
                    <button type="button" onclick="window.location.href='leadManagement.php'" class="btn btn-outline-secondary">Reset</button>
                    <!-- Button to parse raw leads -->
                    <a href="parseRawLeads.php" class="btn btn-secondary">View Raw Leads</a>

                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <?php if (in_array('lead_id', $visibleColumns)) : ?><th class="sortable" data-sort="lead_id">Lead ID</th><?php endif; ?>
                                <?php if (in_array('lead_name', $visibleColumns)) : ?><th class="sortable" data-sort="lead_name">Name</th><?php endif; ?>
                                <?php if (in_array('bedrooms', $visibleColumns)) : ?><th class="sortable" data-sort="bedrooms">Bedrooms</th><?php endif; ?>
                                <?php if (in_array('pickup', $visibleColumns)) : ?><th class="sortable" data-sort="pickup">Pickup</th><?php endif; ?>
                                <?php if (in_array('dropoff', $visibleColumns)) : ?><th class="sortable" data-sort="dropoff">Dropoff</th><?php endif; ?>
                                <?php if (in_array('lead_date', $visibleColumns)) : ?><th class="sortable" data-sort="lead_date">Date</th><?php endif; ?>
                                <?php if (in_array('phone', $visibleColumns)) : ?><th class="sortable" data-sort="phone">Phone</th><?php endif; ?>
                                <?php if (in_array('email', $visibleColumns)) : ?><th class="sortable" data-sort="email">Email</th><?php endif; ?>
                                <?php if (in_array('details', $visibleColumns)) : ?><th class="sortable" data-sort="details">Details</th><?php endif; ?>
                                <?php if (in_array('booking_status', $visibleColumns)) : ?><th class="sortable" data-sort="booking_status">Booking Status</th><?php endif; ?>
                                <?php if (in_array('created_at', $visibleColumns)) : ?><th class="sortable" data-sort="created_at" style="display: none;">Created At</th><?php endif; ?>
                                <?php if (in_array('Source', $visibleColumns)) : ?><th class="sortable" data-sort="Source">Source</th><?php endif; ?>
                                <?php if (in_array('AssignedTo', $visibleColumns)) : ?><th class="sortable" data-sort="AssignedTo">Assigned To</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()) : ?>
                                <tr>
                                    <?php if (in_array('lead_id', $visibleColumns)) : ?><td><?php echo $row['lead_id']; ?></td><?php endif; ?>
                                    <?php if (in_array('lead_name', $visibleColumns)) : ?><td class="editable" data-field="lead_name" data-id="<?= $row['lead_id'] ?>"><?= htmlspecialchars($row['lead_name']) ?></td><?php endif; ?>
                                    <?php if (in_array('bedrooms', $visibleColumns)) : ?><td class="editable" data-field="bedrooms" data-id="<?= $row['lead_id'] ?>"><?= htmlspecialchars($row['bedrooms']) ?></td><?php endif; ?>
                                    <?php if (in_array('pickup', $visibleColumns)) : ?><td class="editable" data-field="pickup" data-id="<?= $row['lead_id'] ?>"><?= htmlspecialchars($row['pickup']) ?></td><?php endif; ?>
                                    <?php if (in_array('dropoff', $visibleColumns)) : ?><td class="editable" data-field="dropoff" data-id="<?= $row['lead_id'] ?>"><?= htmlspecialchars($row['dropoff']) ?></td><?php endif; ?>
                                    <?php if (in_array('lead_date', $visibleColumns)) : ?>
                                        <td class="editable-date" data-field="lead_date" data-id="<?= $row['lead_id'] ?>">
                                            <input type="date" class="form-control" value="<?= htmlspecialchars($row['lead_date']) ?>" readonly>
                                        </td>
                                    <?php endif; ?>
                                    <?php if (in_array('phone', $visibleColumns)) : ?><td class="editable" data-field="phone" data-id="<?= $row['lead_id'] ?>"><?= htmlspecialchars($row['phone']) ?></td><?php endif; ?>
                                    <?php if (in_array('email', $visibleColumns)) : ?><td class="editable" data-field="email" data-id="<?= $row['lead_id'] ?>"><?= htmlspecialchars($row['email']) ?></td><?php endif; ?>
                                    <?php if (in_array('details', $visibleColumns)) : ?>
                                        <td class="editable" data-field="details" data-id="<?= $row['lead_id'] ?>">
                                            <div class="limited-text"><?= htmlspecialchars($row['details']) ?></div>
                                            <?php if (strlen($row['details']) > 100) : ?>
                                                <span class="show-more">Read More</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <?php if (in_array('booking_status', $visibleColumns)) : ?><td class="editable" data-field="booking_status" data-id="<?= $row['lead_id'] ?>"><?= htmlspecialchars($row['booking_status']) ?></td><?php endif; ?>
                                    <?php if (in_array('created_at', $visibleColumns)) : ?><td style="display: none;"><?php echo $row['created_at']; ?></td><?php endif; ?>
                                    <?php if (in_array('Source', $visibleColumns)) : ?><td class="editable" data-field="Source" data-id="<?= $row['lead_id'] ?>"><?= htmlspecialchars($row['Source']) ?></td><?php endif; ?>
                                    <?php if (in_array('AssignedTo', $visibleColumns)) : ?><td class="editable" data-field="AssignedTo" data-id="<?= $row['lead_id'] ?>"><?= htmlspecialchars($row['AssignedTo']) ?></td><?php endif; ?>
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
                var leadId = $td.data('id');

                var $input = $('<input>', {
                    type: 'text',
                    value: originalValue,
                    blur: function() {
                        var newValue = $input.val();
                        $td.text(newValue);

                        // Update the database with the new value
                        $.ajax({
                            url: 'update_lead.php',
                            method: 'POST',
                            data: {
                                lead_id: leadId,
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
                    var leadId = $td.data('id');

                    // Update the database with the new value
                    $.ajax({
                        url: 'update_lead.php',
                        method: 'POST',
                        data: {
                            lead_id: leadId,
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
                    '&sort_column=' + encodeURIComponent('<?= $sortColumn ?>');
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

            // Handle expanding and collapsing of long text
            $('.show-more').on('click', function() {
                var $details = $(this).closest('td').find('.limited-text');
                if ($details.hasClass('expanded-text')) {
                    $details.removeClass('expanded-text').addClass('limited-text');
                    $(this).text('Read More');
                } else {
                    $details.removeClass('limited-text').addClass('expanded-text');
                    $(this).text('Read Less');
                }
            });
        });
    </script>

</body>

</html>