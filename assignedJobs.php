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

// Build the query with search and date filters
$query = "
SELECT 
    b.BookingID, 
    b.Name AS BookingName, 
    b.Email AS BookingEmail, 
    b.Phone AS BookingPhone, 
    b.Bedrooms, 
    b.MovingDate,
    b.PickupLocation,
    b.DropoffLocation,
    GROUP_CONCAT(e.Name ORDER BY e.Name SEPARATOR ', ') AS EmployeeNames,
    MAX(jt.StartTime) AS JobStartTime,
    MAX(jt.EndTime) AS JobEndTime,
    MAX(jt.TotalTime) AS JobTotalTime,
    MAX(jt.isComplete) AS JobIsComplete,
    MAX(jt.BreakTime) AS JobBreakTime,
    MAX(jt.isConfirmed) AS JobIsConfirmed,
    MAX(jc.TotalCharge) AS JobTotalCharge,
    MAX(jc.TotalLaborTime) AS JobTotalLaborTime,
    MAX(jc.TotalBillableTime) AS JobTotalBillableTime,
    MAX(jc.StairCharge) AS JobStairCharge,
    MAX(jc.PianoCharge) AS JobPianoCharge,
    MAX(jc.PoolTableCharge) AS JobPoolTableCharge,
    MAX(jc.Deposit) AS JobDeposit,
    MAX(jc.GST) AS JobGST
FROM 
    Bookings b
JOIN 
    BookingAssignments ba ON b.BookingID = ba.BookingID
JOIN 
    Employees e ON ba.EmployeePhoneNo = e.PhoneNo
LEFT JOIN 
    JobTimings jt ON b.BookingID = jt.BookingID
LEFT JOIN 
    JobCharges jc ON b.BookingID = jc.BookingID
WHERE 
    b.isActive = 1 AND
    b.BookingID NOT IN (SELECT BookingID FROM CompletedJobs)
";

// Add search term filtering
if ($searchTerm) {
    $query .= " AND (b.Name LIKE '%$searchTerm%' OR b.Email LIKE '%$searchTerm%' OR b.Phone LIKE '%$searchTerm%' OR b.PickupLocation LIKE '%$searchTerm%' OR b.DropoffLocation LIKE '%$searchTerm%' OR e.Name LIKE '%$searchTerm%')";
}

// Add date filter
if ($dateFilter) {
    switch ($dateFilter) {
        case 'today':
            $query .= " AND DATE(b.MovingDate) = CURDATE()";
            break;
        case 'next_day':
            $query .= " AND DATE(b.MovingDate) = CURDATE() + INTERVAL 1 DAY";
            break;
        case 'next_2_days':
            $query .= " AND DATE(b.MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 2 DAY";
            break;
        case 'next_3_days':
            $query .= " AND DATE(b.MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY";
            break;
        case 'next_week':
            $query .= " AND DATE(b.MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 WEEK";
            break;
        case 'next_month':
            $query .= " AND DATE(b.MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 MONTH";
            break;
        case 'next_year':
            $query .= " AND DATE(b.MovingDate) BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 YEAR";
            break;
        case 'current_month':
            $query .= " AND MONTH(b.MovingDate) = MONTH(CURRENT_DATE()) AND YEAR(b.MovingDate) = YEAR(CURRENT_DATE())";
            break;
        case 'last_month':
            $query .= " AND MONTH(b.MovingDate) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(b.MovingDate) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)";
            break;
        case 'current_year':
            $query .= " AND YEAR(b.MovingDate) = YEAR(CURRENT_DATE())";
            break;
        case 'last_year':
            $query .= " AND YEAR(b.MovingDate) = YEAR(CURRENT_DATE() - INTERVAL 1 YEAR)";
            break;
        case 'date_range':
            if ($startDate && $endDate) {
                $query .= " AND b.MovingDate BETWEEN '$startDate' AND '$endDate'";
            }
            break;
        default:
            break;
    }
}

$query .= " GROUP BY 
    b.BookingID, 
    b.Name, 
    b.Email, 
    b.Phone, 
    b.Bedrooms, 
    b.MovingDate,
    b.PickupLocation,
    b.DropoffLocation
ORDER BY $sortColumn $sortOrder
";

$result = $conn->query($query);

function getStatusClass($status)
{
    switch ($status) {
        case 'not started':
            return 'status-not-started';
        case 'in progress':
            return 'status-in-progress';
        case 'completed':
            return 'status-completed';
        case 'audit':
            return 'status-audit';
        case 'payment':
            return 'status-payment';
        default:
            return '';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Assigned Jobs</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
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
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: "Poppins", sans-serif;
            }

            .step-wizard {
                width: 100%;
                display: flex;
                justify-content: center;
                align-items: center;
                margin-top: 20px;
            }

            .step-wizard-list {
                background: #fff;
                box-shadow: 0 15px 25px rgba(0, 0, 0, 0.1);
                color: #333;
                list-style-type: none;
                border-radius: 10px;
                display: flex;
                padding: 20px 10px;
                position: relative;
                z-index: 10;
                width: 100%;
                max-width: 1000px;
            }

            .step-wizard-item {
                padding: 0 20px;
                flex-basis: 0;
                flex-grow: 1;
                max-width: 100%;
                display: flex;
                flex-direction: column;
                text-align: center;
                min-width: 170px;
                position: relative;
            }

            .step-wizard-item+.step-wizard-item:after {
                content: "";
                position: absolute;
                left: 0;
                top: 19px;
                background: #21d4fd;
                width: 100%;
                height: 2px;
                transform: translateX(-50%);
                z-index: -10;
            }

            .progress-count {
                height: 40px;
                width: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                font-weight: 600;
                margin: 0 auto;
                position: relative;
                z-index: 10;
                color: transparent;
            }

            .progress-count:after {
                content: "";
                height: 40px;
                width: 40px;
                background: #21d4fd;
                position: absolute;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%);
                border-radius: 50%;
                z-index: -10;
            }

            .progress-label {
                font-size: 14px;
                font-weight: 600;
                margin-top: 10px;
            }

            .current-item .progress-count:before,
            .current-item~.step-wizard-item .progress-count:before {
                display: none;
            }

            .current-item~.step-wizard-item .progress-count:after {
                height: 10px;
                width: 10px;
            }

            .current-item~.step-wizard-item .progress-label {
                opacity: 0.5;
            }

            .current-item .progress-count:after {
                background: #fff;
                border: 2px solid #21d4fd;
            }

            .current-item .progress-count {
                color: #21d4fd;
            }

            .completed-item .progress-count:after,
            .completed-item .progress-count {
                background: #21d4fd;
                color: #fff;
            }
        </style>
    </header>

    <div class="container-fluid">
        <div class="row">
            <?php include 'navbar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2" id="Main-Heading">Assigned Jobs</h1>
                </div>

                <form method="GET" action="assignedJobs.php" class="mb-3">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search Jobs" class="form-control" style="display:inline-block; width: auto;">
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
                    <input type="date" name="start_date" id="start_date" class="form-control" style="display:inline-block; width: auto;">
                    <input type="date" name="end_date" id="end_date" class="form-control" style="display:inline-block; width: auto;">
                    <div class="mb-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <button type="button" onclick="window.location.href='assignedJobs.php'" class="btn btn-outline-secondary">Reset</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="BookingName">Booking Name</th>
                                <th class="sortable" data-sort="MovingDate">Moving Date</th>
                                <th class="sortable" data-sort="EmployeeNames">Employees</th>
                                <th>Details</th>
                                <th>Action</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row["BookingName"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["MovingDate"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["EmployeeNames"]) . "</td>";
                                    echo "<td><a href='jobDetails.php?BookingID=" . htmlspecialchars($row["BookingID"]) . "'>View Details</a></td>";
                                    echo "<td><button type='button' class='btn btn-outline-success completeJob' data-bookingid='" . htmlspecialchars($row["BookingID"]) . "'>Mark as Complete</button></td>";
                                    echo "<td>";
                                    echo "<section class='step-wizard'>";
                                    echo "<ul class='step-wizard-list'>";

                                    // Determine the status for each step
                                    $steps = [
                                        'Started' => !is_null($row["JobStartTime"]),
                                        'Ended' => !is_null($row["JobEndTime"]),
                                        'Audit' => !is_null($row["JobIsComplete"]) && $row["JobIsComplete"] == 1,
                                        'Payment' => !is_null($row["JobIsConfirmed"]) && $row["JobIsConfirmed"] == 1
                                    ];

                                    $stepIndex = 1;
                                    $currentClass = 'current-item';
                                    $completedClass = 'completed-item';
                                    foreach ($steps as $step => $status) {
                                        $class = '';
                                        if ($status) {
                                            $class = $completedClass;
                                        } else {
                                            $class = $currentClass;
                                            $currentClass = '';
                                        }
                                        echo "<li class='step-wizard-item " . $class . "'>";
                                        echo "<span class='progress-count'>" . $stepIndex . "</span>";
                                        echo "<span class='progress-label'>" . $step . "</span>";
                                        echo "</li>";
                                        $stepIndex++;
                                    }

                                    echo "</ul>";
                                    echo "</section>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>No assigned jobs found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            $('.completeJob').click(function() {
                event.preventDefault(); // This prevents the default action of the button
                event.stopPropagation(); // This stops the click event from "bubbling" up to the parent elements
                var bookingId = $(this).data('bookingid'); // Get the booking ID
                $.ajax({
                    url: 'mark-completed.php', // The script to call to insert data into the CompletedJobs table
                    type: 'POST',
                    data: {
                        'bookingID': bookingId
                    },
                    success: function(response) {
                        console.log(response);
                        location.reload(); // Reload the page to update the list
                    },
                    error: function(xhr, status, error) {
                        console.error("An error occurred: " + error);
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
                    '&end_date=' + encodeURIComponent('<?= $endDate ?>');
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
        });
    </script>
</body>

</html>