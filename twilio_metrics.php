<?php
session_start();

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'SuperAdmin')) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Twilio Metrics Dashboard</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Additional styles -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">

    <style>
        .loading-spinner {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100px;
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

            <!-- Main Content -->
            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2 text-primary font-weight-bold" id="Main-Heading">Twilio Metrics Dashboard</h1>
                </div>

                <!-- Twilio Account Information Panel -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="h5 text-primary">Twilio Account Information</h3>
                    </div>
                    <div class="card-body" id="account-info">
                        <div class="loading-spinner">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Twilio Usage Records Panel -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="h5 text-primary">Twilio Usage Records (Today)</h3>
                    </div>
                    <div class="card-body" id="usage-records">
                        <div class="loading-spinner">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                        <p>Total Usage Records: <span id="total-usage-records">0</span></p>
                    </div>
                </div>

                <!-- Queued Messages Panel -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="h5 text-primary">Queued Messages</h3>
                    </div>
                    <div class="card-body" id="queued-messages">
                        <div class="loading-spinner">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                    </div>
                    <div id="queued-pagination"></div>
                </div>

                <!-- Recent Messages Panel -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="h5 text-primary">Recent Messages</h3>
                    </div>
                    <div class="card-body" id="recent-messages">
                        <div class="loading-spinner">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                    </div>
                    <div id="recent-pagination"></div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        $(document).ready(function () {
            function loadTwilioData(page = 1, perPage = 10) {
                $.ajax({
                    url: 'fetch_twilio_data.php',
                    method: 'GET',
                    data: {
                        page: page,
                        perPage: perPage
                    },
                    dataType: 'json',
                    success: function (data) {
                        // Account Information
                        if (data.account) {
                            $('#account-info').html(
                                `<p><strong>Account SID:</strong> ${data.account.sid}</p>` +
                                `<p><strong>Friendly Name:</strong> ${data.account.friendlyName}</p>` +
                                `<p><strong>Status:</strong> ${data.account.status}</p>`
                            );
                        }

                        // Usage Records
                        if (data.usageRecords) {
                            let usageHtml = '<ul class="list-group">';
                            data.usageRecords.forEach(record => {
                                usageHtml += `<li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>${record.category}</span>
                                    <span class="badge badge-primary badge-pill">${record.usage} ${record.usageUnit}</span>
                                </li>`;
                            });
                            usageHtml += '</ul>';
                            $('#usage-records').html(usageHtml);
                            $('#total-usage-records').text(data.totalUsageRecords);
                        }

                        // Queued Messages
                        if (data.queuedMessages && data.queuedMessages.length > 0) {
                            let queuedHtml = `<p>Total Queued Messages: ${data.totalQueuedMessages}</p><ul class="list-group">`;
                            data.queuedMessages.forEach(message => {
                                queuedHtml += `<li class="list-group-item">
                                    <strong>From:</strong> ${message.from}<br>
                                    <strong>To:</strong> ${message.to}<br>
                                    <strong>Status:</strong> ${message.status}
                                </li>`;
                            });
                            queuedHtml += '</ul>';
                            $('#queued-messages').html(queuedHtml);
                            $('#queued-pagination').html(createPagination(data.queuedMessagesPage, data.totalQueuedMessages, data.queuedMessagesPerPage, 'queued'));
                        } else {
                            $('#queued-messages').html('<p>No queued messages found.</p>');
                        }

                        // Recent Messages
                        if (data.recentMessages && data.recentMessages.length > 0) {
                            let recentHtml = `<p>Total Recent Messages: ${data.totalRecentMessages}</p><ul class="list-group">`;
                            data.recentMessages.forEach(message => {
                                recentHtml += `<li class="list-group-item">
                                    <strong>From:</strong> ${message.from}<br>
                                    <strong>To:</strong> ${message.to}<br>
                                    <strong>Body:</strong> ${message.body}<br>
                                    <strong>Status:</strong> ${message.status}
                                </li>`;
                            });
                            recentHtml += '</ul>';
                            $('#recent-messages').html(recentHtml);
                            $('#recent-pagination').html(createPagination(data.recentMessagesPage, data.totalRecentMessages, data.recentMessagesPerPage, 'recent'));
                        } else {
                            $('#recent-messages').html('<p>No recent messages found.</p>');
                        }
                    },
                    error: function () {
                        $('#account-info').html('<p class="text-danger">Failed to load account information.</p>');
                        $('#usage-records').html('<p class="text-danger">Failed to load usage records.</p>');
                        $('#queued-messages').html('<p class="text-danger">Failed to load queued messages.</p>');
                        $('#recent-messages').html('<p class="text-danger">Failed to load recent messages.</p>');
                    }
                });
            }

            // Function to create pagination links
            function createPagination(currentPage, totalItems, itemsPerPage, type) {
                const totalPages = Math.ceil(totalItems / itemsPerPage);
                let paginationHtml = '<nav><ul class="pagination justify-content-center">';

                for (let i = 1; i <= totalPages; i++) {
                    paginationHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="javascript:void(0)" onclick="loadTwilioData(${i}, ${itemsPerPage}, '${type}')">${i}</a>
                    </li>`;
                }

                paginationHtml += '</ul></nav>';
                return paginationHtml;
            }

            // Load initial data
            loadTwilioData();

            // Attach event listeners to pagination links (if needed)
            window.loadTwilioData = loadTwilioData;
        });
    </script>
</body>

</html>
