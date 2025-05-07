<?php
// Ensure error reporting is helpful during debugging
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

include("db_config.php");
session_start();

// Redirect if user data is not in session
if (!isset($_SESSION['user_data'])) {
    header("Location: index.php"); // Or your login page
    exit();
}

// Safer way to extract session variables, compatible with older PHP
$user_id = isset($_SESSION['user_data']['user_id']) ? $_SESSION['user_data']['user_id'] : null;
$email = isset($_SESSION['user_data']['email']) ? $_SESSION['user_data']['email'] : null;
$first_name = isset($_SESSION['user_data']['first_name']) ? $_SESSION['user_data']['first_name'] : null;
$last_name = isset($_SESSION['user_data']['last_name']) ? $_SESSION['user_data']['last_name'] : null;
$role = isset($_SESSION['user_data']['role']) ? $_SESSION['user_data']['role'] : null;


// Check if user has admin role
if($role !== "Admin"){
    header("Location: index.php"); // Redirect non-admin users
    exit();
}

$conn = mysqli_connect($host, $user, $pass, $db);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// --- Get User's RFID Card (remains the same, uses compatible syntax) ---
$card_id = null;
$sql_card = "SELECT card_id FROM rfidcards WHERE user_id = ?";
$stmt_card = mysqli_prepare($conn, $sql_card);
if ($stmt_card) { // Check if statement prepared successfully
    mysqli_stmt_bind_param($stmt_card, "i", $user_id);
    mysqli_stmt_execute($stmt_card);
    $result_card = mysqli_stmt_get_result($stmt_card);
    if ($data_card = mysqli_fetch_assoc($result_card)) {
        $card_id = $data_card['card_id'];
    }
    mysqli_stmt_close($stmt_card);
} else {
     // Handle error if prepare failed, though it might be non-critical for the page if card_id display is optional
     // echo "Error preparing card query: " . mysqli_error($conn);
}

// ...
$filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : ''; // around line 49
$filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';     // around line 50

// Basic validation (ensure they look like dates if provided)
$filter_start_date = (preg_match("/^\d{4}-\d{2}-\d{2}$/", $filter_start_date)) ? $filter_start_date : ''; // around line 52
$filter_end_date = (preg_match("/^\d{4}-\d{2}-\d{2}$/", $filter_end_date)) ? $filter_end_date : '';     // around line 53 -- Check this line in YOUR file!

// --- Pagination Logic ---                                                                               // around line 54
$limit = 100;                                                                                           // around line 55 -- This is line 55 in MY code, maybe it's different in yours
// ...

// --- Pagination Logic ---
// Note: Pagination here applies to the total number of logs before splitting into IN/OUT
$limit = 100;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- Base SQL Query Structure (Repurposed for attendance_logs) ---
// This now queries the attendance_logs table and joins with users
$sql_base = "
    FROM attendance_logs l
    LEFT JOIN users u ON l.user_id = u.user_id
";

// --- Build Dynamic WHERE Clause & Parameters (Date Filtering) ---
$where_clauses = [];
$params = [];
$param_types = "";

if (!empty($filter_start_date)) {
    $where_clauses[] = "DATE(l.timestamp) >= ?";
    $params[] = $filter_start_date;
    $param_types .= "s";
}

if (!empty($filter_end_date)) {
    $where_clauses[] = "DATE(l.timestamp) <= ?";
    $params[] = $filter_end_date;
    $param_types .= "s";
}

$sql_where = "";
if (!empty($where_clauses)) {
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// --- Calculate Total Records (for pagination, uses the new base and where) ---
$sql_total = "SELECT COUNT(*) " . $sql_base . $sql_where;
$stmt_total = mysqli_prepare($conn, $sql_total);

if (!$stmt_total) {
    die("Error preparing total count query: " . mysqli_error($conn) . " SQL: " . $sql_total);
}

// Bind parameters if any exist for the WHERE clause using call_user_func_array
if (!empty($params)) {
    // Prepare parameters for call_user_func_array (pass by reference)
    $a_params_total = [];
    $a_params_total[] = &$param_types;
    for($i = 0; $i < count($params); $i++) {
        $a_params_total[] = &$params[$i];
    }
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_total], $a_params_total));
}

mysqli_stmt_execute($stmt_total);
$result_total = mysqli_stmt_get_result($stmt_total);
$total_records = mysqli_fetch_array($result_total)[0];
$total_pages = ceil($total_records / $limit);
mysqli_stmt_close($stmt_total);

// --- Fetch Attendance Log Data (Repurposed query) ---
// Selects all necessary columns from attendance_logs and joined users
$sql_logs = "
    SELECT
        l.attendance_log_id,
        l.user_id,
        l.log_type,
        l.timestamp,
        l.device_id,
        l.location_context,
        l.face_recognized,
        l.rfid_card_id_used,
        l.access_granted,
        l.confidence_score,
        u.first_name,
        u.last_name,
        u.email,
        u.role
    " . $sql_base . $sql_where . "
    ORDER BY l.timestamp DESC
    LIMIT ? OFFSET ?"; // Order by timestamp for chronological log display

$stmt_logs = mysqli_prepare($conn, $sql_logs);

if (!$stmt_logs) {
     die("Error preparing log data query: " . mysqli_error($conn) . " SQL: " . $sql_logs);
}

// Combine date params with pagination params
$log_params = $params; // Start with date params
$log_param_types = $param_types; // Start with date param types

// Add pagination params
$log_params[] = $limit; // Limit value
$log_param_types .= "i"; // 'i' for integer

$log_params[] = $offset; // Offset value
$log_param_types .= "i"; // 'i' for integer


// Bind parameters using call_user_func_array (pass by reference)
if (!empty($log_param_types)) {
    $a_params_logs = [];
    $a_params_logs[] = &$log_param_types;
    for($i = 0; $i < count($log_params); $i++) {
        $a_params_logs[] = &$log_params[$i];
    }
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_logs], $a_params_logs));
}


mysqli_stmt_execute($stmt_logs);
$result_logs = mysqli_stmt_get_result($stmt_logs);

// --- Separate Logs into IN and OUT arrays ---
$in_logs = [];
$out_logs = [];
if ($result_logs) {
    while ($row = mysqli_fetch_assoc($result_logs)) {
        if ($row['log_type'] === 'in') {
            $in_logs[] = $row;
        } elseif ($row['log_type'] === 'out') {
            $out_logs[] = $row;
        }
    }
}


// --- Build Base URL for Pagination ---
$query_params = [];
if (!empty($filter_start_date)) $query_params['start_date'] = $filter_start_date;
if (!empty($filter_end_date)) $query_params['end_date'] = $filter_end_date;
// Keep existing GET params when building pagination links
$base_pagination_url = '?' . http_build_query($query_params);

// Close statement and connection only after they are no longer needed
if (isset($stmt_logs)) mysqli_stmt_close($stmt_logs);
mysqli_close($conn);

// The rest of your page content and HTML rendering would go here
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Logs Dashboard</title> <link rel="stylesheet" href="./styles/style.css">
    <link rel="stylesheet" href="./styles/admin-dashboard.css">
    <style>
        /* Existing styles for pagination, status, filter form, etc. */
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
             font-size: 0.9em; /* Match button size roughly */
             display: inline-block; /* Make it behave like a button */
             text-align: center;
             line-height: normal; /* Adjust line height if needed */
        }
        .filter-form .clear-filter-link:hover {
             background-color: #ec971f;
        }

         /* --- New Styles for Side-by-Side Tables --- */
        .attendance-tables-container {
            display: flex; /* Use Flexbox to arrange tables side-by-side */
            gap: 20px; /* Space between the tables */
            flex-wrap: wrap; /* Allow tables to wrap on smaller screens */
        }

        .attendance-table-wrapper {
            flex: 1; /* Allow tables to grow and shrink */
            min-width: 300px; /* Minimum width before wrapping */
            /* Add padding/margin if needed */
        }

        .attendance-tables-container table {
            width: 100%; /* Make tables fill their container */
            border-collapse: collapse; /* Collapse borders */
            margin-bottom: 1rem; /* Space below tables */
        }

        .attendance-tables-container table th,
        .attendance-tables-container table td {
            padding: 0.75rem; /* Standard padding */
            vertical-align: top; /* Align content to top */
            border-bottom: 1px solid #ddd; /* Add subtle row separators */
            text-align: left; /* Align text to the left */
        }

        .attendance-tables-container table th {
            background-color: #f2f2f2; /* Light grey background for headers */
            font-weight: bold;
        }

        /* Optional: Add specific styles for IN/OUT table headers */
        .attendance-tables-container .in-table th {
             background-color: #dff0d8; /* Light green for IN */
        }

         .attendance-tables-container .out-table th {
             background-color: #fcf8e3; /* Light yellow for OUT */
        }

        /* Style for the 'No logs found' message in each table */
        .attendance-tables-container table tbody tr td[colspan] {
             text-align: center;
             padding: 1rem;
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
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <span class="dashboard-title"> Attendance Dashboard</span>
                </div>
                <div class="navbar-right">
                    <div class="user-info">
                        <span class="user-email" id="user-email"><?= htmlspecialchars($email ?? '') ?></span>
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
                        <a href="./attendance-dashboard.php" class="nav-link active">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg> <span>Attendance Logs</span>
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
                        <h2>Welcome back, <?= htmlspecialchars($first_name ?? '') ?>!</h2>
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
                                <p>Filtered Attendance Logs (Total)</p>
                                <p><?= $total_records ?></p>
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
                            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($filter_start_date) ?>">
                        </div>
                        <div>
                            <label for="end_date">To:</label>
                            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($filter_end_date) ?>">
                        </div>
                        <button type="submit">Filter</button>
                         <?php if (!empty($filter_start_date) || !empty($filter_end_date)) : ?>
                            <a href="?" class="clear-filter-link">Clear Filter</a>
                        <?php endif; ?>
                    </form>

                    <div class="table-controls">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search logs on this page...">
                    </div>

                    <div class="attendance-tables-container">

                        <div class="attendance-table-wrapper">
                            <h3>IN Logs</h3>
                            <table class="in-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Timestamp</th>
                                        <th>Device ID</th>
                                        <th>Location</th>
                                        <th>Method(s)</th>
                                        <th>Verification</th>
                                        <th>Access Granted</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody class="logTableBody" id="inLogTableBody">
                                    <?php
                                    if (!empty($in_logs)) {
                                        foreach ($in_logs as $row) {
                                            $attendance_log_id = $row['attendance_log_id'];
                                            $display_name = (isset($row['first_name']) && isset($row['last_name'])) ? htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) : 'Unknown User';
                                            $timestamp_raw = $row['timestamp'];
                                            $timestamp_formatted = date("M j, Y, g:i A", strtotime($timestamp_raw));
                                            $device_id = htmlspecialchars($row['device_id']);
                                            $location_context = isset($row['location_context']) ? htmlspecialchars($row['location_context']) : 'N/A';

                                            // Determine Method(s) Used
                                            $methods_used_list = [];
                                            if (isset($row['face_recognized']) && $row['face_recognized'] !== NULL || isset($row['confidence_score']) && $row['confidence_score'] !== NULL) {
                                                $methods_used_list[] = 'Face';
                                            }
                                            if (isset($row['rfid_card_id_used']) && $row['rfid_card_id_used'] !== NULL) {
                                                $methods_used_list[] = 'RFID';
                                            }
                                            $methods_used_text = empty($methods_used_list) ? 'N/A' : implode(', ', $methods_used_list);

                                            // Determine Verification Details
                                            $verification_details_list = [];
                                            if (isset($row['face_recognized']) && $row['face_recognized'] === TRUE) {
                                                $verification_details_list[] = 'Face: Recognized';
                                                 if (isset($row['confidence_score']) && $row['confidence_score'] !== NULL) {
                                                      $verification_details_list[] = '(' . htmlspecialchars(number_format($row['confidence_score'], 2)) . '%)';
                                                 }
                                            } elseif (isset($row['face_recognized']) && $row['face_recognized'] === FALSE) {
                                                 if (isset($row['confidence_score']) && $row['confidence_score'] !== NULL) {
                                                      $verification_details_list[] = 'Face: Not Recognized (' . htmlspecialchars(number_format($row['confidence_score'], 2)) . '%)';
                                                 } else {
                                                      $verification_details_list[] = 'Face: Attempted';
                                                 }
                                            }

                                            if (isset($row['rfid_card_id_used']) && $row['rfid_card_id_used'] !== NULL) {
                                                $verification_details_list[] = 'RFID: ' . htmlspecialchars($row['rfid_card_id_used']);
                                            }
                                             $verification_details_text = empty($verification_details_list) ? 'N/A' : implode(' ', $verification_details_list);


                                            // Determine Access Granted Status Display
                                            $access_granted = isset($row['access_granted']) ? $row['access_granted'] : FALSE;
                                            $status_text = $access_granted ? 'Granted' : 'Denied';
                                            $status_class = $access_granted ? 'status-granted' : 'status-denied';

                                            echo <<<HTML
                                                <tr>
                                                    <td>{$display_name}</td>
                                                    <td>{$timestamp_formatted}</td>
                                                    <td>{$device_id}</td>
                                                    <td>{$location_context}</td>
                                                    <td>{$methods_used_text}</td>
                                                    <td>{$verification_details_text}</td>
                                                    <td><span class="status {$status_class}">{$status_text}</span></td>
                                                    <td class="action-links">
                                                        <a href="view_log_details.php?id={$attendance_log_id}" title="View Details">View</a>
                                                        <a href="delete_log.php?id={$attendance_log_id}" class="delete-link" title="Delete Log" onclick="return confirm('Are you sure you want to delete this log entry?');">Delete</a>
                                                    </td>
                                                </tr>
HTML;
                                        }
                                    } else {
                                        // Colspan updated to match the number of columns in this table (8)
                                        echo '<tr><td colspan="8" style="text-align: center; padding: 1rem;">No IN logs found for the selected criteria.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="attendance-table-wrapper">
                             <h3>OUT Logs</h3>
                            <table class="out-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Timestamp</th>
                                        <th>Device ID</th>
                                        <th>Location</th>
                                        <th>Method(s)</th>
                                        <th>Verification</th>
                                        <th>Access Granted</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody class="logTableBody" id="outLogTableBody">
                                    <?php
                                    if (!empty($out_logs)) {
                                        foreach ($out_logs as $row) {
                                            $attendance_log_id = $row['attendance_log_id'];
                                            $display_name = (isset($row['first_name']) && isset($row['last_name'])) ? htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) : 'Unknown User';
                                            $timestamp_raw = $row['timestamp'];
                                            $timestamp_formatted = date("M j, Y, g:i A", strtotime($timestamp_raw));
                                            $device_id = htmlspecialchars($row['device_id']);
                                            $location_context = isset($row['location_context']) ? htmlspecialchars($row['location_context']) : 'N/A';

                                            // Determine Method(s) Used
                                            $methods_used_list = [];
                                            if (isset($row['face_recognized']) && $row['face_recognized'] !== NULL || isset($row['confidence_score']) && $row['confidence_score'] !== NULL) {
                                                $methods_used_list[] = 'Face';
                                            }
                                            if (isset($row['rfid_card_id_used']) && $row['rfid_card_id_used'] !== NULL) {
                                                $methods_used_list[] = 'RFID';
                                            }
                                            $methods_used_text = empty($methods_used_list) ? 'N/A' : implode(', ', $methods_used_list);

                                            // Determine Verification Details
                                            $verification_details_list = [];
                                            if (isset($row['face_recognized']) && $row['face_recognized'] === TRUE) {
                                                $verification_details_list[] = 'Face: Recognized';
                                                 if (isset($row['confidence_score']) && $row['confidence_score'] !== NULL) {
                                                      $verification_details_list[] = '(' . htmlspecialchars(number_format($row['confidence_score'], 2)) . '%)';
                                                 }
                                            } elseif (isset($row['face_recognized']) && $row['face_recognized'] === FALSE) {
                                                 if (isset($row['confidence_score']) && $row['confidence_score'] !== NULL) {
                                                      $verification_details_list[] = 'Face: Not Recognized (' . htmlspecialchars(number_format($row['confidence_score'], 2)) . '%)';
                                                 } else {
                                                      $verification_details_list[] = 'Face: Attempted';
                                                 }
                                            }

                                            if (isset($row['rfid_card_id_used']) && $row['rfid_card_id_used'] !== NULL) {
                                                $verification_details_list[] = 'RFID: ' . htmlspecialchars($row['rfid_card_id_used']);
                                            }
                                             $verification_details_text = empty($verification_details_list) ? 'N/A' : implode(' ', $verification_details_list);


                                            // Determine Access Granted Status Display
                                            $access_granted = isset($row['access_granted']) ? $row['access_granted'] : FALSE;
                                            $status_text = $access_granted ? 'Granted' : 'Denied';
                                            $status_class = $access_granted ? 'status-granted' : 'status-denied';

                                            echo <<<HTML
                                                <tr>
                                                    <td>{$display_name}</td>
                                                    <td>{$timestamp_formatted}</td>
                                                    <td>{$device_id}</td>
                                                    <td>{$location_context}</td>
                                                    <td>{$methods_used_text}</td>
                                                    <td>{$verification_details_text}</td>
                                                    <td><span class="status {$status_class}">{$status_text}</span></td>
                                                    <td class="action-links">
                                                        <a href="view_log_details.php?id={$attendance_log_id}" title="View Details">View</a>
                                                        <a href="delete_log.php?id={$attendance_log_id}" class="delete-link" title="Delete Log" onclick="return confirm('Are you sure you want to delete this log entry?');">Delete</a>
                                                    </td>
                                                </tr>
HTML;
                                        }
                                    } else {
                                         // Colspan updated to match the number of columns in this table (8)
                                        echo '<tr><td colspan="8" style="text-align: center; padding: 1rem;">No OUT logs found for the selected criteria.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>

                    </div> <?php if ($total_pages > 1) : ?>
                        <div class="pagination">
                            <?php if ($page > 1) : ?>
                                <a href="<?= htmlspecialchars($base_pagination_url . '&page=' . ($page - 1)) ?>">&laquo; Previous</a>
                            <?php else : ?>
                                <span class="disabled">&laquo; Previous</span>
                            <?php endif; ?>

                            <?php
                            $range = 2; // Number of pages to show around the current page
                            $start_range = max(1, $page - $range);
                            $end_range = min($total_pages, $page + $range);

                            for ($i = 1; $i <= $total_pages; $i++) {
                                // Show first page, last page, or pages within the range of the current page
                                if ($i == 1 || $i == $total_pages || ($i >= $start_range && $i <= $end_range)) {
                                    if ($i == $page) {
                                        echo '<span class="current-page">' . $i . '</span>';
                                    } else {
                                        echo '<a href="' . htmlspecialchars($base_pagination_url . '&page=' . $i) . '">' . $i . '</a>';
                                    }
                                } elseif (($i == $start_range - 1) || ($i == $end_range + 1)) {
                                    // Print ellipsis if there's a gap (only once between segments)
                                     if (($i == $start_range - 1 && $start_range > 2) || ($i == $end_range + 1 && $end_range < $total_pages - 1)) {
                                        echo '<span>...</span>';
                                     }
                                }
                            }
                            ?>

                            <?php if ($page < $total_pages) : ?>
                                <a href="<?= htmlspecialchars($base_pagination_url . '&page=' . ($page + 1)) ?>">Next &raquo;</a>
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
        // JavaScript remains largely the same, adjusted for the new table structure if needed
        document.addEventListener('DOMContentLoaded', () => {
            const hamburgerButton = document.getElementById('hamburger-button');
            const sidebar = document.getElementById('sidebar');
            const errorDisplay = document.getElementById('error-display'); // Assuming this element exists for errors
            const searchInput = document.getElementById('searchInput');
            const inTableBody = document.getElementById('inLogTableBody'); // Get IN table body
            const outTableBody = document.getElementById('outLogTableBody'); // Get OUT table body

            // Get rows from both tables
            const inTableRows = inTableBody ? inTableBody.getElementsByTagName('tr') : [];
            const outTableRows = outTableBody ? outTableBody.getElementsByTagName('tr') : [];
            const allTableRows = [...inTableRows, ...outTableRows]; // Combine rows for searching


            // --- Sidebar Toggle ---
            if (hamburgerButton && sidebar) {
                hamburgerButton.addEventListener('click', () => {
                    sidebar.classList.toggle('is-open');
                });
            }

             // --- Client-Side Search/Filter (searches both tables on current page) ---
            if (searchInput && (inTableBody || outTableBody)) { // Check if at least one table body exists
                searchInput.addEventListener('input', () => {
                    const searchTerm = searchInput.value.toLowerCase().trim();

                    // Iterate through all rows (from both tables)
                    allTableRows.forEach(row => {
                        // Check if it's a data row (not the 'No logs found' row)
                         if (row.getElementsByTagName('td').length > 1) {
                            const rowText = row.textContent.toLowerCase();
                            if (rowText.includes(searchTerm)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        } else {
                             // This is likely a 'No logs found' row. Hide it when searching.
                             row.style.display = 'none';
                         }
                    });
                });
            }


            // --- Helper Functions (assuming these exist elsewhere or are placeholders) ---
            function showError(message) { /* Implementation depends on your needs */
                 if (errorDisplay) {
                     errorDisplay.classList.remove('hidden');
                     errorDisplay.textContent = message;
                 }
             }
            function hideError() { /* Implementation depends on your needs */
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