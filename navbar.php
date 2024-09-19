            <!-- Sidebar -->
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="sidebar-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#bookingsSubmenu" data-toggle="collapse" aria-expanded="false">
                                Bookings
                            </a>
                            <ul class="collapse list-unstyled sub-menu" id="bookingsSubmenu">
                                <li class="nav-item">
                                    <a class="nav-link" href="activeBookings.php">Active Bookings</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="cancelledBookings.php">Canceled Bookings</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="createBooking.php">Create Booking</a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#jobsSubmenu" data-toggle="collapse" aria-expanded="false">
                                Jobs
                            </a>
                            <ul class="collapse list-unstyled sub-menu" id="jobsSubmenu">
                                <li class="nav-item">
                                    <a class="nav-link" href="unassignedJobs.php">Jobs</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="assignedJobs.php">Assigned Jobs</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="completedJobs.php">Completed Jobs</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="customerSignatures.php">Customer Signatures</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="customerInvoices.php">Customer Invoices</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="createInvoice.php">Create Invoice</a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#employeesSubmenu" data-toggle="collapse" aria-expanded="false">
                                Employees
                            </a>
                            <ul class="collapse list-unstyled sub-menu" id="employeesSubmenu">
                                <li class="nav-item">
                                    <a class="nav-link" href="activeEmployees.php">Active Employees</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="inactiveEmployees.php">Employee Activation</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="registerEmployee.php">Register Employee</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="employeeDetails.php">Employee Details</a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#leadsSubMenu" data-toggle="collapse" aria-expanded="false">
                                Leads
                            </a>
                            <ul class="collapse list-unstyled sub-menu" id="leadsSubMenu">
                                <li class="nav-item">
                                    <a class="nav-link" href="leadManagement.php">Google Leads</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="leadManagement-ms.php">Moving Select Leads</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#">Prices Compare Leads</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#">FindAMover Leads</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#">HiPages Leads</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#">Facebook Leads</a>
                                </li>
                            </ul>
                        </li>

                        <?php if ($_SESSION['role'] == 'SuperAdmin') : ?>
                            <li class="nav-item">
                                <a class="nav-link" href="#superAdminSubmenu" data-toggle="collapse" aria-expanded="false">
                                    Super Admin
                                </a>
                                <ul class="collapse list-unstyled sub-menu" id="superAdminSubmenu">
                                    <li class="nav-item">
                                        <a class="nav-link" href="payroll.php">Pay roll</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="#">Admin Activation</a>
                                    </li>
                                </ul>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#marketingSubmenu" data-toggle="collapse" aria-expanded="false">
                                    SMS Marketing Campaign
                                </a>
                                <ul class="collapse list-unstyled sub-menu" id="marketingSubmenu">
                                    <li class="nav-item">
                                        <a class="nav-link" href="marketingCampaign.php">Marketing</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="twilio_metrics.php">Metrics</a>
                                    </li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <li class="nav-item">
                            <a class="nav-link" href="template.php">
                                Template/Testbed
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var navItems = document.querySelectorAll('.nav-link[data-toggle="collapse"]');
                    navItems.forEach(function(item) {
                        item.addEventListener('click', function(event) {
                            var currentSubmenu = document.querySelector(item.getAttribute('href'));
                            var isCurrentSubmenuOpen = currentSubmenu.classList.contains('show');

                            // Close all submenus first
                            var allSubmenus = document.querySelectorAll('.sub-menu');
                            allSubmenus.forEach(function(submenu) {
                                if (submenu !== currentSubmenu) {
                                    $(submenu).collapse('hide');
                                }
                            });

                            // If the clicked submenu was already open, close it, otherwise open it
                            if (isCurrentSubmenuOpen) {
                                $(currentSubmenu).collapse('hide');
                            } else {
                                $(currentSubmenu).collapse('show');
                            }

                            // Prevent default if it's a hash "#" link
                            if (item.getAttribute('href').startsWith('#')) {
                                event.preventDefault();
                            }
                        });
                    });
                });
            </script>

            <style>
                #sidebarMenu {
                    min-height: 100vh;
                    background-color: #fff;
                    /* White background for the sidebar */
                    box-shadow: 0px 2px 5px 0px rgba(0, 0, 0, 0.2);
                    /* Soft shadow to lift the sidebar off the page */
                    border-right: 1px solid #e7e7e7;
                    /* Right border for the sidebar */
                    position: fixed;
                }

                #sidebarMenu .nav-link {
                    color: #333;
                    /* Dark color for the text for better readability */
                    padding: 10px 15px;
                    /* Uniform padding for all links */
                    transition: background-color .3s;
                    /* Smooth transition for hover effects */
                    border-radius: 0;
                    /* Remove border-radius to align with sidebar edges */
                }

                #sidebarMenu .nav-item {
                    position: relative;
                    /* Positioning context for before/after pseudo-elements */
                }

                #sidebarMenu .nav-item:not(:last-child) {
                    border-bottom: 1px solid #e7e7e7;
                    /* Borders between items */
                }

                /* Style for main menu items */
                #sidebarMenu>.nav>.nav-item>.nav-link {
                    font-weight: 500;
                    /* Slightly bolder font for main menu items */
                    text-transform: uppercase;
                    /* Uppercase letters for main menu items */
                    letter-spacing: 0.05rem;
                    /* More spacing between letters */
                    font-size: 0.85rem;
                    /* Smaller font size for a refined look */
                }

                /* Indicator for collapsible items */
                #sidebarMenu .nav-item .nav-link[data-toggle="collapse"]::after {
                    content: '\f107';
                    /* FontAwesome chevron-down */
                    font-family: 'FontAwesome';
                    float: right;
                    /* Position the icon to the right */
                    transition: transform .3s ease;
                    /* Animation for rotating icon */
                }

                /* Rotate chevron icon when submenu is open */
                #sidebarMenu .nav-item .nav-link[data-toggle="collapse"].collapsed::after {
                    transform: rotate(-180deg);
                }

                .sub-menu {
                    padding-left: 30px;
                    /* Increased left-padding to visually separate submenus */
                    background: #f9f9f9;
                    /* Slightly different background for submenus */
                }

                .sub-menu .nav-item {
                    border-bottom: none;
                    /* No borders for sub-items */
                }

                .sub-menu .nav-link {
                    font-size: 0.8rem;
                    /* Smaller font size for submenu items */
                    color: #555;
                    /* Lighter color for submenu items */
                    padding: 8px 15px;
                    /* Slightly smaller padding for submenu items */
                    position: relative;
                    /* Added to position the pseudo-element */
                }

                .sub-menu .nav-link::before {
                    content: '';
                    /* Empty content for pseudo-element */
                    position: absolute;
                    /* Absolute positioning relative to the nav-link */
                    top: 0;
                    bottom: 0;
                    left: -30px;
                    /* Align with the start of the padding */
                    border-left: 2px solid #007bff;
                    /* Create the line */
                    width: 2px;
                    /* Line width */
                }

                /* Clear distinction of submenu items on hover */
                .sub-menu .nav-link:hover {
                    background-color: #e7e7e7;
                    color: #333;
                }

                /* Add a left border to active submenu item for clarity */
                .sub-menu .nav-link.active {
                    border-left: 2px solid #007bff;
                    padding-left: calc(30px - 2px);
                    /* Adjust padding to account for border width */
                    background-color: #e7e7e7;
                }

                /* Responsive behavior for sidebar */
                @media (max-width: 768px) {
                    #sidebarMenu {
                        min-height: auto;
                        max-height: 300px;
                        /* Adjust as per requirement */
                        overflow-y: auto;
                        /* Scroll for sidebar on smaller screens */
                    }
                }
            </style>