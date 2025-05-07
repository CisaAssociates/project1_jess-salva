<?php
include("db_config.php");
session_start();

// Redirect if user data is not in session
if (!isset($_SESSION['user_data'])) {
    header("Location: index.php"); // Or your login page
    exit();
}

foreach ($_SESSION['user_data'] as $key => $value) {
    $$key = $value; // Extracts user_id, email, first_name, last_name, etc.
}

if($role !== "Admin"){
    header("Location: index.php"); // Or your login page
    exit();
}

$conn = mysqli_connect($host, $user, $pass, $db);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// --- Get User's RFID Card (remains the same) ---
$card_id = null;
$sql_card = "SELECT card_id FROM rfidcards WHERE user_id = ?";
$stmt_card = mysqli_prepare($conn, $sql_card);
mysqli_stmt_bind_param($stmt_card, "i", $user_id);
mysqli_stmt_execute($stmt_card);
$result_card = mysqli_stmt_get_result($stmt_card);
if ($data_card = mysqli_fetch_assoc($result_card)) {
    $card_id = $data_card['card_id'];
}
mysqli_stmt_close($stmt_card);


// --- Get Filter Dates from GET Request ---
$filter_start_date = $_GET['start_date'] ?? ''; // Default to empty string if not set
$filter_end_date = $_GET['end_date'] ?? '';   // Default to empty string if not set

// Basic validation (ensure they look like dates if provided)
$filter_start_date = (preg_match("/^\d{4}-\d{2}-\d{2}$/", $filter_start_date)) ? $filter_start_date : '';
$filter_end_date = (preg_match("/^\d{4}-\d{2}-\d{2}$/", $filter_end_date)) ? $filter_end_date : '';


// --- Pagination Logic ---
$limit = 100; // Number of records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- Build Base SQL parts ---
$sql_select_count = "SELECT COUNT(*) ";
$sql_select_data = "SELECT l.*, u.first_name, u.last_name ";

// Simpler join compared to the previous script (no 'latest per day' logic)
$sql_from_joins = "
    FROM accesslogs l
    LEFT JOIN users u ON l.user_id = u.user_id
";

// --- Build Dynamic WHERE Clause & Parameters ---
// Base condition for this script: Show logs only for known users
$where_clauses = ["u.user_id IS NOT NULL"];
$params = [];
$param_types = "";

// Add date filters if provided
if (!empty($filter_start_date)) {
    $where_clauses[] = "DATE(l.timestamp) >= ?";
    $params[] = $filter_start_date;
    $param_types .= "s"; // 's' for string date
}

if (!empty($filter_end_date)) {
    $where_clauses[] = "DATE(l.timestamp) <= ?";
    $params[] = $filter_end_date;
    $param_types .= "s"; // 's' for string date
}

$sql_where = "";
if (!empty($where_clauses)) {
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// --- Calculate Total Records WITH Filters ---
// The COUNT query now includes the same joins and WHERE clause as the data query
$sql_total = $sql_select_count . $sql_from_joins . $sql_where;
$stmt_total = mysqli_prepare($conn, $sql_total);

if (!$stmt_total) {
    die("Error preparing total count query: " . mysqli_error($conn) . " SQL: " . $sql_total);
}

// Bind parameters (dates) if any exist for the WHERE clause
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_total, $param_types, ...$params);
}

mysqli_stmt_execute($stmt_total);
$result_total = mysqli_stmt_get_result($stmt_total);
$total_records = mysqli_fetch_array($result_total)[0];
$total_pages = ceil($total_records / $limit);
mysqli_stmt_close($stmt_total);


// --- Fetch Data for the Current Page WITH Filters ---
$sql_logs = $sql_select_data . $sql_from_joins . $sql_where . " ORDER BY l.timestamp DESC LIMIT ? OFFSET ?";
$stmt_logs = mysqli_prepare($conn, $sql_logs);

if (!$stmt_logs) {
     die("Error preparing log data query: " . mysqli_error($conn) . " SQL: " . $sql_logs);
}

// Combine date params with pagination params
$log_params = $params; // Start with date params
$log_param_types = $param_types; // Start with date param types

$log_params[] = $limit;      // Add limit
$log_param_types .= "i";     // Add type for limit

$log_params[] = $offset;     // Add offset
$log_param_types .= "i";     // Add type for offset

// Bind parameters if any exist (dates + limit + offset)
if (!empty($log_param_types)) {
    mysqli_stmt_bind_param($stmt_logs, $log_param_types, ...$log_params);
}

mysqli_stmt_execute($stmt_logs);
$result_logs = mysqli_stmt_get_result($stmt_logs);

// --- Build Base URL for Pagination ---
$query_params = [];
if (!empty($filter_start_date)) $query_params['start_date'] = $filter_start_date;
if (!empty($filter_end_date)) $query_params['end_date'] = $filter_end_date;
$base_pagination_url = '?' . http_build_query($query_params);


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard - All Logs</title>
    <link rel="stylesheet" href="./styles/style.css">
    <link rel="stylesheet" href="./styles/admin-dashboard.css">
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
            background-color: #5cb85c; /* Green */
        }
        .status-denied {
            background-color: #d9534f; /* Red */
        }
        .status-unknown {
            background-color: #777; /* Gray */
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
            color: #d9534f; /* Red */
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
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
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

    <div class="dashboard-layout" id="dashboard-layout" style="visibility: hidden;">
        <nav class="navbar">
           <div class="navbar-content">
                <div class="navbar-left">
                    <button class="hamburger-button" id="hamburger-button" aria-label="Toggle sidebar">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
                    </button>
                    <span class="dashboard-title">Logs Dashboard</span>
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
                           <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                            <span>Main Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="./admin-gatepass-logs.php" class="nav-link ">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg> <span>Filtered Logs</span>
                        </a>
                    </li>
                    <li>
                        <a href="./admin-attendance-logs.php" class="nav-link ">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg> <span>Attendance Logs</span>
                        </a>
                    </li>
                    <li>
                        <a href="./manage-users.php" class="nav-link">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
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
                        <h2>Attendance Overview</h2> <p>View all recorded filtered logs.</p>
                    </div>
                     <div class="card">
                        <h2>Quick Stats</h2>
                        <div class="stats-item">
                            <div class="stats-icon-wrapper">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                            </div>
                            <div class="stats-text">
                                <p>Your Card ID</p>
                                <p><?= $card_id ? htmlspecialchars($card_id) : 'Not Registered' ?></p>
                            </div>
                        </div>
                        <div class="stats-item" style="margin-top: 1rem;">
                            <div class="stats-icon-wrapper" style="background-color: var(--bg-green-100);">
                                <svg class="w-6 h-6" fill="none" stroke="var(--text-green-500)" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div class="stats-text">
                                <p>Filtered Logs (Total)</p>
                                <p><?= $total_records ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="error-display" class="error-display hidden"></div>

                <div class="table-container ">
                    <h2>Log History (All Records)</h2>

                    <form method="GET" action="" class="filter-form">
                        <div>
                            <label for="start_date">From:</label>
                            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($filter_start_date) ?>">
                        </div>
                        <div>
                            <label for="end_date">To:</label>
                            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($filter_end_date) ?>">
                        </div>
                        <button type="submit">Filter</button>
                        <a href="?" class="clear-filter-link">Clear Filter</a>
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
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="logTableBody">
                            <?php
                            if ($result_logs && mysqli_num_rows($result_logs) > 0) { // Check $result_logs
                                while ($row = mysqli_fetch_assoc($result_logs)) {
                                    $log_id = $row['log_id'];
                                    $display_name = (isset($row['first_name']) && isset($row['last_name'])) ? htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) : 'Unknown User';
                                    $timestamp_raw = $row['timestamp'];
                                    $rfid_scanned = $row['rfid_scanned'] == "" ? "No Scanned Card":$row['rfid_scanned']; // Sanitize
                                    $access_granted = $row['access_granted'];

                                    $timestamp = date("M j, Y, g:i A", strtotime($timestamp_raw));

                                    // Status logic remains relevant here as we show all logs
                                    $status_text = '';
                                    $status_class = '';
                                    if ($access_granted == 1) {
                                        $status_text = 'Granted'; // Or 'Present', 'Checked In' etc.
                                        $status_class = 'status-granted';
                                    } elseif ($access_granted == 0) {
                                        $status_text = 'Denied'; // Or 'Invalid Scan' etc.
                                        $status_class = 'status-denied';
                                    } else {
                                        $status_text = 'Unknown';
                                        $status_class = 'status-unknown';
                                    }

                                    echo <<<HTML
                                            <tr>
                                                <td>{$display_name}</td>
                                                <td>{$timestamp}</td>
                                                <td>{$rfid_scanned}</td>
                                                <td><span class="status {$status_class}">{$status_text}</span></td>
                                                <td class="action-links">
                                                    <a href="view_log_details.php?id={$log_id}" title="View Details">View</a>
                                                    <a href="delete_log.php?id={$log_id}" class="delete-link" title="Delete Log" onclick="return confirm('Are you sure you want to delete this log entry?');">Delete</a>
                                                </td>
                                            </tr>
                                        HTML;
                                }
                            } else {
                                // Message reflects potential filtering
                                echo '<tr><td colspan="5" style="text-align: center; padding: 1rem;">No filtered logs found for the selected criteria.</td></tr>';
                            }
                            // Close statement and connection
                            if (isset($stmt_logs)) mysqli_stmt_close($stmt_logs);
                            mysqli_close($conn);
                            ?>
                        </tbody>
                    </table>

                    <?php if ($total_pages > 1) : ?>
                        <div class="pagination">
                            <?php if ($page > 1) : ?>
                                <a href="<?= $base_pagination_url . (empty($query_params) ? '' : '&') . 'page=' . ($page - 1) ?>">&laquo; Previous</a>
                            <?php else : ?>
                                <span class="disabled">&laquo; Previous</span>
                            <?php endif; ?>

                            <?php
                            $range = 2;
                            for ($i = 1; $i <= $total_pages; $i++) {
                                if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)) {
                                    if ($i == $page) {
                                        echo '<span class="current-page">' . $i . '</span>';
                                    } else {
                                        // Append base URL with page param
                                        echo '<a href="' . $base_pagination_url . (empty($query_params) ? '' : '&') . 'page=' . $i . '">' . $i . '</a>';
                                    }
                                } elseif (($i == $page - $range - 1) || ($i == $page + $range + 1)) {
                                    echo '<span>...</span>';
                                }
                            }
                            ?>

                            <?php if ($page < $total_pages) : ?>
                                <a href="<?= $base_pagination_url . (empty($query_params) ? '' : '&') . 'page=' . ($page + 1) ?>">Next &raquo;</a>
                            <?php else : ?>
                                <span class="disabled">Next &raquo;</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

    </div>
    <script>
        // JavaScript should be fine as is
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

            // --- Client-Side Search/Filter (searches only current page) ---
            if (searchInput && tableBody) {
                 searchInput.addEventListener('input', () => { /* ... same as before ... */ });
            }

            // --- Helper Functions ---
            function showError(message) { /* ... */ }
            function hideError() { /* ... */ }

            // --- Close sidebar on outside click (small screens) ---
             document.addEventListener('click', (event) => { /* ... same as before ... */ });


            // --- Initial Display Logic ---
            const loadingIndicator = document.getElementById('loading-indicator');
            const dashboardLayout = document.getElementById('dashboard-layout');
            if (loadingIndicator) loadingIndicator.classList.add('hidden');
            if (dashboardLayout) dashboardLayout.style.visibility = 'visible'; // Make visible now

        });
    </script>

</body>

</html>