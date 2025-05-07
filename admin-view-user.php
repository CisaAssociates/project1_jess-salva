<?php
include("db_config.php");
session_start();

// Redirect if user data is not in session
if (!isset($_SESSION['user_data'])) {
    header("Location: index.php");
    exit();
}

// Extract session user data safely
$user_id_session = $_SESSION['user_data']['user_id'] ?? null;
$role = $_SESSION['user_data']['role'] ?? null;
$email = $_SESSION['user_data']['email'] ?? null;

// Check if Admin
if ($role !== "Admin") {
    echo "Access denied: Admins only.";
    exit();
}

// Check if user id is provided and is numeric
$user_id = $_GET['id'] ?? null;
if (!$user_id || !is_numeric($user_id)) {
    header("Location: admin-dashboard.php"); // Redirect to admin dashboard if no valid ID
    exit();
}
$user_id = (int) $user_id;

// Database Connection
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Fetch User Information
$stmt_user = mysqli_prepare($conn, "SELECT first_name, last_name FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$userinfo = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

if (!$userinfo) {
    echo "User not found.";
    exit();
}

// Fetch User's RFID Card
$card_id = null;
$stmt_card = mysqli_prepare($conn, "SELECT card_id FROM rfidcards WHERE user_id = ?");
mysqli_stmt_bind_param($stmt_card, "i", $user_id);
mysqli_stmt_execute($stmt_card);
$result_card = mysqli_stmt_get_result($stmt_card);
if ($row_card = mysqli_fetch_assoc($result_card)) {
    $card_id = $row_card['card_id'];
}
mysqli_stmt_close($stmt_card);

// --- Filtering and Pagination ---
$limit = 10;
$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Initialize filter variables
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';

// Construct the WHERE clause for filtering
$where_clause = "WHERE l.user_id = ? AND l.access_granted = 1";
$filter_params = [$user_id];
$filter_types = "i";

if (!empty($filter_start_date)) {
    $where_clause .= " AND l.timestamp >= ?";
    $filter_params[] = $filter_start_date . " 00:00:00";
    $filter_types .= "s";
}

if (!empty($filter_end_date)) {
    $where_clause .= " AND l.timestamp <= ?";
    $filter_params[] = $filter_end_date . " 23:59:59";
    $filter_types .= "s";
}

// Count total filtered access logs for this user
$stmt_total = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM accesslogs l " . $where_clause);
mysqli_stmt_bind_param($stmt_total, $filter_types, ...$filter_params);
mysqli_stmt_execute($stmt_total);
$result_total = mysqli_stmt_get_result($stmt_total);
$total_records = mysqli_fetch_assoc($result_total)['total'] ?? 0;
$total_pages = ceil($total_records / $limit);
mysqli_stmt_close($stmt_total);

// Fetch filtered access logs
$stmt_logs = mysqli_prepare($conn, "
    SELECT l.*, u.first_name, u.last_name
    FROM accesslogs l
    LEFT JOIN users u ON l.user_id = u.user_id
    " . $where_clause . "
    ORDER BY l.timestamp DESC
    LIMIT ? OFFSET ?
");

// Add limit and offset to the parameters
$filter_params[] = $limit;
$filter_params[] = $offset;
$filter_types .= "ii";

mysqli_stmt_bind_param($stmt_logs, $filter_types, ...$filter_params);
mysqli_stmt_execute($stmt_logs);
$result_logs = mysqli_stmt_get_result($stmt_logs);

mysqli_close($conn); // Close the database connection here after all data fetching
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Access Logs</title>
    <link rel="stylesheet" href="./styles/style.css">
    <link rel="stylesheet" href="./styles/user-dashboard.css">
    <style>
        /* Add styles for pagination if they don't exist in admin-dashboard.css */
        .pagination {
            margin-top: 1.5rem;
            text-align: center;
        }

        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 0.5rem 0.8rem;
            margin: 0 0.2rem;
            border: 1px solid #ddd;
            color: #337ab7;
            text-decoration: none;
            border-radius: 4px;
            background-color: #fff;
        }

        .pagination a:hover {
            background-color: #eee;
        }

        .pagination .current-page {
            font-weight: bold;
            color: #fff;
            background-color: #337ab7;
            border-color: #337ab7;
            cursor: default;
        }

        .pagination .disabled {
            color: #777;
            cursor: default;
            background-color: #f9f9f9;
            border-color: #ddd;
        }

        /* Style for status spans */
        .status {
            padding: 0.2em 0.6em;
            border-radius: 0.25em;
            font-size: 0.85em;
            font-weight: bold;
            color: #fff;
            white-space: nowrap;
        }

        .status-granted {
            background-color: #5cb85c;
            /* Green */
        }

        .status-denied {
            background-color: #d9534f;
            /* Red */
        }

        .status-unknown {
            background-color: #777;
            /* Gray */
        }

        .action-links a {
            margin-right: 0.5rem;
            color: #337ab7;
            text-decoration: none;
        }

        .action-links a:hover {
            text-decoration: underline;
        }

        .action-links .delete-link {
            color: #d9534f;
            /* Red */
        }

        .action-links .delete-link:hover {
            color: #c9302c;
        }

        /* Styles for Filter Form */
        .filter-form {
            margin-bottom: 1rem;
            padding: 1rem;
            background-color: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            /* Allow wrapping on smaller screens */
        }

        .filter-form label {
            font-weight: bold;
            margin-right: 0.5rem;
        }

        .filter-form input[type="date"] {
            padding: 0.4rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .filter-form button {
            padding: 0.5rem 1rem;
            background-color: #337ab7;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .filter-form button:hover {
            background-color: #286090;
        }

        .filter-form .clear-filter-link {
            padding: 0.5rem 1rem;
            background-color: #f0ad4e;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
            display: inline-block;
            text-align: center;
            line-height: normal;
        }

        .filter-form .clear-filter-link:hover {
            background-color: #ec971f;
        }
    </style>
</head>

<body>
    <div class="loading-indicator hidden" id="loading-indicator">
        <div>Loading...</div>
    </div>

    <div class="dashboard-layout" id="dashboard-layout">
        <nav class="navbar">
            <div class="navbar-content">
                <div class="navbar-left">
                    <button class="hamburger-button" id="hamburger-button" aria-label="Toggle sidebar">
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <span class="dashboard-title">User Gatepass Logs</span>
                </div>
                <div class="navbar-right">
                    <div class="user-info">
                        <span class="user-email" id="user-email"><?= htmlspecialchars($email) ?></span>
                        <a class="signout-button" id="signout-button" href="logout.php">Sign Out</a>
                    </div>
                </div>
            </div>
        </nav>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-content">
                <ul class="sidebar-nav">
                    <li>
                        <a href="./admin-dashboard.php" class="nav-link active">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span>Main Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="./admin-gatepass-logs.php" class="nav-link ">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg> <span>Filtered Logs</span>
                        </a>
                    </li>
                    <li>
                        <a href="./admin-gatepass-logs.php" class="nav-link ">
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
                        <h2>Welcome back, <?= htmlspecialchars($userinfo['first_name'] . ' ' . $userinfo['last_name']) ?>!</h2>
                        <p>Manage your account, view access logs, and update your profile.</p>
                    </div>

                    <div class="card">
                        <h2>Card ID</h2>
                        <div class="stats-item">
                            <div class="stats-icon-wrapper">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                            </div>
                            <div class="stats-text">
                                <p>Registered Card</p>
                                <p><?= $card_id ? htmlspecialchars($card_id) : 'Not Registered' ?></p>
                            </div>
                        </div>
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
                                <p>Registered Card</p>
                                <p><?= $card_id ? htmlspecialchars($card_id) : 'Not Registered' ?></p>
                            </div>
                        </div>
                        <div class="stats-item" style="margin-top: 1rem;">
                            <div class="stats-icon-wrapper" style="background-color: var(--bg-green-100);">
                                <svg class="w-6 h-6" fill="none" stroke="var(--text-green-500)" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="stats-text">
                                <p>Total Access Logs</p>
                                <p><?= $total_records ?></p>
                            </div>
                        </div>
                    </div>

                </div>

                <div id="error-display" class="error-display hidden"></div>

                <div class="table-container table-responsive">
                    <h2>Access Log History</h2>

                    <form method="GET" action="admin-view-user.php" class="filter-form">
                        <input type="hidden" name="id" value="<?= $user_id ?>">
                        <div>
                            <label for="start_date">From:</label>
                            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($filter_start_date) ?>">
                        </div>
                        <div>
                            <label for="end_date">To:</label>
                            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($filter_end_date) ?>">
                        </div>
                        <button type="submit">Filter</button>
                        <?php if (!empty($filter_start_date) || !empty($filter_end_date)): ?>
                            <a href="admin-view-user.php?id=<?= $user_id ?>" class="clear-filter-link">Clear Filter</a>
                        <?php endif; ?>
                    </form>

                    <div class="table-controls">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search logs on this page...">
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Date Time</th>
                                <th>Card Scanned</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="logTableBody">
                            <?php
                            if (mysqli_num_rows($result_logs) > 0) {
                                while ($row = mysqli_fetch_assoc($result_logs)) {
                                    $log_id = $row['log_id']; // Assuming you have a log_id primary key
                                    $display_name = (isset($row['first_name']) && isset($row['last_name'])) ? htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) : 'Unknown User';
                                    $timestamp_raw = $row['timestamp'];
                                    $rfid_scanned = $row['rfid_scanned'] == "" ? "No Card Scanned" : $row['rfid_scanned'];
                                    $access_granted = $row['access_granted']; // 1 for granted, 0 for denied

                                    // Format the timestamp to 12-hour format with AM/PM
                                    $timestamp = date("M j, Y, g:i A", strtotime($timestamp_raw));

                                    // Determine status text and class
                                    $status_text = '';
                                    $status_class = '';
                                    if ($access_granted == 1) {
                                        $status_text = 'Granted';
                                        $status_class = 'status-granted';
                                    } elseif ($access_granted == 0) {
                                        $status_text = 'Denied';
                                        $status_class = 'status-denied';
                                    } else {
                                        $status_text = 'Unknown'; // Handle potential NULL or other values
                                        $status_class = 'status-unknown';
                                    }

                                    echo <<<HTML
                                        <tr>
                                            <td>{$display_name}</td>
                                            <td>{$timestamp}</td>
                                            <td>{$rfid_scanned}</td>
                                            <td><span class="status {$status_class}">{$status_text}</span></td>
                                        </tr>
                                        HTML;
                                }
                            } else {
                                echo '<tr><td colspan="5" style="text-align: center; padding: 1rem;">No access logs found with the current filters.</td></tr>';
                            }
                            ?>

                        </tbody>
                    </table>

                    <div class="pagination">
                        <?php if ($page > 1) : ?>
                            <a href="?id=<?= $user_id ?>&page=<?= $page - 1 ?><?php if (!empty($filter_start_date)) echo '&start_date=' . urlencode($filter_start_date); ?><?php if (!empty($filter_end_date)) echo '&end_date=' . urlencode($filter_end_date); ?>">&laquo; Previous</a>
                        <?php else: ?>
                            <span class="disabled">&laquo; Previous</span>
                        <?php endif; ?>

                        <?php
                        // Display page numbers (optional: limit the number shown for many pages)
                        $range = 2; // Number of links to show around the current page
                        for ($i = 1; $i <= $total_pages; $i++) {
                            // Only display first, last, and nearby pages
                            if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)) {
                                if ($i == $page) {
                                    echo '<span class="current-page">' . $i . '</span>';
                                } else {
                                    echo '<a href="?id=' . $user_id . '&page=' . $i . '">';
                                    if (!empty($filter_start_date)) echo '&start_date=' . urlencode($filter_start_date);
                                    if (!empty($filter_end_date)) echo '&end_date=' . urlencode($filter_end_date);
                                    echo '">' . $i . '</a>';
                                }
                            } elseif (($i == $page - $range - 1) || ($i == $page + $range + 1)) {
                                // Add ellipsis (...) if needed, but only once
                                $dots_shown = false;
                                if ($i == $page - $range - 1 && $page - $range > 2 && !$dots_shown) {
                                    echo '<span>...</span>';
                                    $dots_shown = true;
                                }
                                if ($i == $page + $range + 1 && $page + $range < $total_pages - 1 && !$dots_shown) {
                                    echo '<span>...</span>';
                                    $dots_shown = true;
                                }
                            }
                        }
                        ?>

                        <?php if ($page < $total_pages) : ?>
                            <a href="?id=<?= $user_id ?>&page=<?= $page + 1 ?><?php if (!empty($filter_start_date)) echo '&start_date=' . urlencode($filter_start_date); ?><?php if (!empty($filter_end_date)) echo '&end_date=' . urlencode($filter_end_date); ?>">Next &raquo;</a>
                        <?php else: ?>
                            <span class="disabled">Next &raquo;</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const hamburgerButton = document.getElementById('hamburger-button');
            const sidebar = document.getElementById('sidebar');
            const errorDisplay = document.getElementById('error-display');
            const searchInput = document.getElementById('searchInput');
            const tableBody = document.getElementById('logTableBody');
            const tableRows = tableBody.getElementsByTagName('tr');

            // --- Sidebar Toggle ---
            if (hamburgerButton && sidebar) {
                hamburgerButton.addEventListener('click', () => {
                    sidebar.classList.toggle('is-open');
                });
            }

            // --- Client-Side Search/Filter ---
            if (searchInput && tableBody) {
                searchInput.addEventListener('input', () => {
                    const searchTerm = searchInput.value.toLowerCase().trim();

                    for (let i = 0; i < tableRows.length; i++) {
                        const row = tableRows[i];
                        // Check if it's a data row (ignore potential header/footer rows in tbody)
                        if (row.getElementsByTagName('td').length > 0) {
                            const rowText = row.textContent.toLowerCase();
                            if (rowText.includes(searchTerm)) {
                                row.style.display = ''; // Show row
                            } else {
                                row.style.display = 'none'; // Hide row
                            }
                        }
                    }
                });
            }

            // --- Helper Functions ---
            function showError(message) {
                if (errorDisplay) {
                    errorDisplay.textContent = message;
                    errorDisplay.classList.remove('hidden');
                }
            }

            function hideError() {
                if (errorDisplay) {
                    errorDisplay.textContent = '';
                    errorDisplay.classList.add('hidden');
                }
            }

            // --- Close sidebar on outside click (small screens) ---
            document.addEventListener('click', (event) => {
                const isSmallScreen = window.innerWidth < 640;
                if (isSmallScreen && sidebar.classList.contains('is-open')) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnHamburger = hamburgerButton.contains(event.target);
                    if (!isClickInsideSidebar && !isClickOnHamburger) {
                        sidebar.classList.remove('is-open');
                    }
                }
            });

            // --- Initial Display Logic (Example) ---
            // You might have loading indicators etc.
            const loadingIndicator = document.getElementById('loading-indicator');
            const dashboardLayout = document.getElementById('dashboard-layout');
            if (loadingIndicator) loadingIndicator.classList.add('hidden'); // Hide loading
            if (dashboardLayout) dashboardLayout.classList.remove('hidden'); // Show dashboard


        });
    </script>

</body>

</html>