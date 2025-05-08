<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Logs Dashboard</title>
    <link rel="stylesheet" href="./styles/style.css">
    <link rel="stylesheet" href="./styles/admin-attendance.css">
</head>

<body>
    <div class="loading-indicator hidden" id="loading-indicator">
        <div>Loading...</div>
    </div>

    <div class="dashboard-layout" id="dashboard-layout" style="visibility: hidden;">
        <nav class="navbar">
            <div class="navbar-content">
                <div class="navbar-left">
                    <button class="hamburger-button" id="hamburger-button" aria-label="Toggle sidebar">
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <span class="dashboard-title"> Attendance Dashboard</span>
                </div>
                <div class="navbar-right">
                    <div class="user-info">
                        <span class="user-email" id="user-email">albolerasjoshualuis@gmail.com</span>
                        <a class="signout-button" id="signout-button" href="logout.php">Sign Out</a>
                    </div>
                </div>
            </div>
        </nav>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-content">
                <ul class="sidebar-nav">
                    <li>
                        <a href="./admin-dashboard.php" class="nav-link ">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span>Main Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="./admin-gatepass-logs.php" class="nav-link">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg> <span>Filtered Logs</span>
                        </a>
                    </li>
                    <li>
                        <a href="./admin-attendance-logs.php" class="nav-link active">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg> <span>Attendance Logs</span>
                        </a>
                    </li>
                    <li>
                        <a href="./manage-users.php" class="nav-link">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span>Manage Users</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>

        <main class="main-content" id="main-content">
            <div class="main-content-inner">

                <div class="content-grid">
                    <div class="card">
                        <h2>Welcome back, Joshua Luis!</h2>
                        <p>View attendance logs and manage users.</p>
                    </div>

                    <div class="card">
                        <h2>Quick Stats</h2>
                        <div class="stats-item">
                            <div class="stats-icon-wrapper">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                            </div>
                            <div class="stats-text">
                                <p>Your Card ID</p>
                                <p>f3c49fe4</p>
                            </div>
                        </div>
                        <div class="stats-item" style="margin-top: 1rem;">
                            <div class="stats-icon-wrapper" style="background-color: var(--bg-green-100);">
                                <svg class="w-6 h-6" fill="none" stroke="var(--text-green-500)" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="stats-text">
                                <p>Filtered Attendance Logs (Daily Summary)</p>
                                <p>1</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="error-display" class="error-display hidden"></div>

                <div class="table-container ">
                    <h2>Attendance History</h2>

                    <form method="GET" action="" class="filter-form">
                        <div>
                            <label for="start_date">From:</label>
                            <input type="date" id="start_date" name="start_date" value="2025-05-08">
                        </div>
                        <div>
                            <label for="end_date">To:</label>
                            <input type="date" id="end_date" name="end_date" value="2025-05-08">
                        </div>
                        <button type="submit">Filter</button>
                        <a href="?" class="clear-filter-link">Clear Filter</a>
                    </form>

                    <div class="table-controls" style=" margin-bottom: 20px;">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search logs on this page...">
                    </div>

                    <div class="attendance-tables-container">
                        <div class="attendance-table" id="attendance-table">
                            <div class="attendance-header">
                                <div class="user-info-column">Student Details</div>
                                <div class="log-column">IN Record</div>
                                <div class="log-column">OUT Record</div>
                            </div>

                            <div class="attendance-row">
                                <div class="user-info-column">
                                    <div class="user-name">Joshua Luis Alboleras</div>
                                    <div class="user-date">May 8, 2025</div>
                                    <div class="user-detail">albolerasjoshualuis@gmail.com</div>
                                    <div class="user-detail">Admin</div>
                                </div>
                                <div class="log-column in-log">
                                    <div class="log-details">
                                        <div class="log-detail"><span class="detail-name">Time</span><span class="detail-value">May 8, 2025, 12:12 AM</span></div>
                                        <div class="log-detail"><span class="detail-name">Device ID</span><span class="detail-value">device001</span></div>
                                        <div class="log-detail"><span class="detail-name">Location</span><span class="detail-value">Tomas Oppus, Eastern Visayas, Philippines</span></div>
                                        <div class="log-detail"><span class="detail-name">Method(s)</span><span class="detail-value">Face, RFID</span></div>
                                        <div class="log-detail"><span class="detail-name">Verification</span><span class="detail-value">RFID: 0</span></div>
                                        <div class="log-detail"><span class="detail-name">Status</span><span class="detail-value"><span class="status status-granted">Granted</span></span></div>
                                        <div class="log-detail action-links"><span class="detail-name">Action</span><span class="detail-value">
                                                <a href="view_log_details.php?id=15" title="View Details">View</a>
                                                <a href="delete_log.php?id=15" class="delete-link" title="Delete Log"
                                                    onclick="return confirm('Are you sure you want to delete this log entry?');">Delete</a>
                                            </span></div>
                                    </div>
                                </div>
                                <div class="log-column out-log">
                                    <div class="log-details">
                                        <div class="log-detail"><span class="detail-name">Time</span><span class="detail-value">May 8, 2025, 12:30 AM</span></div>
                                        <div class="log-detail"><span class="detail-name">Device ID</span><span class="detail-value">device001</span></div>
                                        <div class="log-detail"><span class="detail-name">Location</span><span class="detail-value">Tomas Oppus, Eastern Visayas, Philippines</span></div>
                                        <div class="log-detail"><span class="detail-name">Method(s)</span><span class="detail-value">Face, RFID</span></div>
                                        <div class="log-detail"><span class="detail-name">Verification</span><span class="detail-value">RFID: 0</span></div>
                                        <div class="log-detail"><span class="detail-name">Status</span><span class="detail-value"><span class="status status-granted">Granted</span></span></div>
                                        <div class="log-detail action-links"><span class="detail-name">Action</span><span class="detail-value">
                                                <a href="view_log_details.php?id=18" title="View Details">View</a>
                                                <a href="delete_log.php?id=18" class="delete-link" title="Delete Log"
                                                    onclick="return confirm('Are you sure you want to delete this log entry?');">Delete</a>
                                            </span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
            </div>
        </main>

    </div>
    <script>
        // JavaScript for sidebar and loading indicator remains the same.
        document.addEventListener('DOMContentLoaded', () => {
            const hamburgerButton = document.getElementById('hamburger-button');
            const sidebar = document.getElementById('sidebar');
            const errorDisplay = document.getElementById('error-display'); // Assuming this element exists for errors
            const searchInput = document.getElementById('searchInput');
            const attendanceTable = document.getElementById('attendance-table'); // Get the table container

            // --- Sidebar Toggle ---
            if (hamburgerButton && sidebar) {
                hamburgerButton.addEventListener('click', () => {
                    sidebar.classList.toggle('is-open');
                });
            }

            // --- Client-Side Search/Filter ---
            if (searchInput && attendanceTable) {
                searchInput.addEventListener('input', () => {
                    const searchTerm = searchInput.value.toLowerCase().trim();
                    // Target the attendance-row divs
                    const logEntries = attendanceTable.querySelectorAll('.attendance-row');

                    logEntries.forEach(row => {
                        // Search within the text content of the entire row
                        const rowText = row.textContent.toLowerCase();
                        if (rowText.includes(searchTerm)) {
                            row.style.display = ''; // Show the row
                        } else {
                            row.style.display = 'none'; // Hide the row
                        }
                    });
                });
            }


            // --- Helper Functions (assuming these exist elsewhere or are placeholders) ---
            function showError(message) {
                /* Implementation depends on your needs */
                if (errorDisplay) {
                    errorDisplay.classList.remove('hidden');
                    errorDisplay.textContent = message;
                }
            }

            function hideError() {
                /* Implementation depends on your needs */
                if (errorDisplay) {
                    errorDisplay.classList.add('hidden');
                    errorDisplay.textContent = '';
                }
            }

            // --- Close sidebar on outside click (small screens) ---
            // Assuming your existing implementation for this
            document.addEventListener('click', (event) => {
                // Add logic to close sidebar if click is outside sidebar and hamburger
                // e.g., if sidebar is open AND !sidebar.contains(event.target) AND !hamburgerButton.contains(event.target)
            });


            // --- Initial Display Logic ---
            const loadingIndicator = document.getElementById('loading-indicator');
            const dashboardLayout = document.getElementById('dashboard-layout');
            // Hide loading indicator and show the layout once DOM is loaded
            if (loadingIndicator) loadingIndicator.classList.add('hidden');
            if (dashboardLayout) dashboardLayout.style.visibility = 'visible'; // Make visible now

        });
    </script>

</body>

</html>