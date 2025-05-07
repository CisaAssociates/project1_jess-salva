<?php
include("db_config.php");
session_start();

if (!isset($_SESSION['user_data'])) {
    header("Location: index.php"); 
    exit();
}

$user_id = isset($_SESSION['user_data']['user_id']) ? $_SESSION['user_data']['user_id'] : null;
$email = isset($_SESSION['user_data']['email']) ? $_SESSION['user_data']['email'] : null;
$first_name = isset($_SESSION['user_data']['first_name']) ? $_SESSION['user_data']['first_name'] : null;
$last_name = isset($_SESSION['user_data']['last_name']) ? $_SESSION['user_data']['last_name'] : null;
$role = isset($_SESSION['user_data']['role']) ? $_SESSION['user_data']['role'] : null;

if ($role !== "Admin") {
    header("Location: index.php");
    exit();
}

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$card_id = null;
$sql_card = "SELECT card_id FROM rfidcards WHERE user_id = ?";
$stmt_card = mysqli_prepare($conn, $sql_card);
if ($stmt_card) {
    mysqli_stmt_bind_param($stmt_card, "i", $user_id);
    mysqli_stmt_execute($stmt_card);
    $result_card = mysqli_stmt_get_result($stmt_card);
    if ($data_card = mysqli_fetch_assoc($result_card)) {
        $card_id = $data_card['card_id'];
    }
    mysqli_stmt_close($stmt_card);
}

$filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : ''; 
$filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';     

$filter_start_date = (preg_match("/^\d{4}-\d{2}-\d{2}$/", $filter_start_date)) ? $filter_start_date : '';
$filter_end_date = (preg_match("/^\d{4}-\d{2}-\d{2}$/", $filter_end_date)) ? $filter_end_date : '';     

$limit = 100;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$sql_base = "
    FROM attendance_logs l
    LEFT JOIN users u ON l.user_id = u.user_id
";

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

$sql_total = "SELECT COUNT(*) " . $sql_base . $sql_where;
$stmt_total = mysqli_prepare($conn, $sql_total);

if (!$stmt_total) {
    die("Error preparing total count query: " . mysqli_error($conn) . " SQL: " . $sql_total);
}

if (!empty($params)) {
    $a_params_total = [];
    $a_params_total[] = &$param_types;
    for ($i = 0; $i < count($params); $i++) {
        $a_params_total[] = &$params[$i];
    }
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_total], $a_params_total));
}

mysqli_stmt_execute($stmt_total);
$result_total = mysqli_stmt_get_result($stmt_total);
$total_records = mysqli_fetch_array($result_total)[0];
$total_pages = ceil($total_records / $limit);
mysqli_stmt_close($stmt_total);

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
    LIMIT ? OFFSET ?";

$stmt_logs = mysqli_prepare($conn, $sql_logs);

if (!$stmt_logs) {
    die("Error preparing log data query: " . mysqli_error($conn) . " SQL: " . $sql_logs);
}

$log_params = $params; 
$log_param_types = $param_types; 

$log_params[] = $limit;
$log_param_types .= "i";

$log_params[] = $offset; 
$log_param_types .= "i"; 

if (!empty($log_param_types)) {
    $a_params_logs = [];
    $a_params_logs[] = &$log_param_types;
    for ($i = 0; $i < count($log_params); $i++) {
        $a_params_logs[] = &$log_params[$i];
    }
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_logs], $a_params_logs));
}

mysqli_stmt_execute($stmt_logs);
$result_logs = mysqli_stmt_get_result($stmt_logs);

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

$query_params_for_pagination = [];
if (!empty($filter_start_date)) $query_params_for_pagination['start_date'] = $filter_start_date;
if (!empty($filter_end_date)) $query_params_for_pagination['end_date'] = $filter_end_date;
// $base_pagination_url = '?' . http_build_query($query_params_for_pagination) . '&'; // Old approach
// $base_pagination_url_no_page = 'admin-attendance-logs.php?' . http_build_query($query_params_for_pagination);


mysqli_stmt_close($stmt_logs);
// mysqli_close($conn); // Close connection later, after pagination link generation if it needs $conn

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Logs Dashboard</title>
    <link rel="stylesheet" href="./styles/style.css"> <?php // General styles ?>
    
    <style>
        /* NEW CSS FOR TABLE AND RELATED ELEMENTS */
        .table-container {
            overflow-x: auto; /* Allows horizontal scrolling for the table if needed */
        }

        .table-container h2 { /* Styling for "Attendance History" */
            font-size: 1.5rem;
            font-weight: normal; /* Changed from original dashboard h2 */
            color: #333;
            margin-bottom: 1rem;
        }
        
        .table-container h3 { /* Styling for "IN Logs" and "OUT Logs" */
            margin-top: 0;
            border-bottom: 2px solid #eee;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            font-size: 1.3rem;
            color: #444;
        }

        /* Styles for Filter Form */
        .filter-form {
            margin-bottom: 1.5rem; /* Increased from 1rem */
            padding: 1rem;
            background-color: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
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
            background-color: #007bff; /* Updated color */
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .filter-form button:hover {
            background-color: #0056b3; /* Darker shade for hover */
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

        /* Table Controls (Search) */
        .table-controls {
            margin-bottom: 1.5rem;
        }

        .search-input {
            padding: 0.6rem 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.9rem;
            width: 100%;
            max-width: 300px; /* Limit width */
        }

        .search-input:focus {
            outline: none;
            border-color: #999; /* Or #007bff for consistency */
            box-shadow: 0 0 3px rgba(0, 0, 0, 0.1); /* Or use var(--focus-ring-color) if defined */
        }

        /* Table Styling */
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem; /* Space before pagination */
        }

        .table-container th,
        .table-container td {
            padding: 0.8rem;
            border-bottom: 1px solid #eee; /* Minimalist border */
            text-align: left;
            white-space: nowrap; /* Prevents text wrapping, good for table data */
        }

        .table-container th {
            background-color: #f9f9f9; /* Very light background for headers */
            font-weight: normal; /* CSS specified normal, was bold in typical tables */
            color: #666; /* Dark gray for header text */
            font-size: 0.9rem; /* Slightly smaller header font */
        }

        .table-container tbody tr:hover {
            background-color: #f5f5f5; /* Subtle hover effect for rows */
        }

        /* Status Indicators */
        .table-container .status { /* General status span */
            display: inline-block; /* Or inline-flex for centering content */
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold; /* Make status text stand out */
            line-height: 1; /* Adjust if padding causes height issues */
            text-align: center;
            min-width: 60px; /* Ensure a minimum width */
        }

        .table-container .status-granted {
            background-color: #e6ffe6; /* Light green background */
            color: #2e8b57; /* Dark green text (SeaGreen) */
        }

        .table-container .status-denied {
            background-color: #ffe6e6; /* Light red background */
            color: #b22222; /* Dark red text (FireBrick) */
        }

        .table-container .status-unknown {
            background-color: #f0f0f0; /* Light gray background */
            color: #777; /* Medium gray text */
        }

        /* Action Links */
        .table-container .action-links a {
            margin-right: 0.5rem;
            text-decoration: none;
            color: #007bff; /* Primary link color */
            font-size: 0.9rem;
        }
        .table-container .action-links a:last-child {
            margin-right: 0;
        }

        .table-container .action-links a:hover {
            text-decoration: underline;
        }

        .table-container .delete-link { /* Specific styling for delete links */
            color: #dc3545; /* Danger color (Bootstrap's danger red) */
        }
        .table-container .delete-link:hover {
            color: #c82333; /* Darker danger color on hover */
        }

        /* Pagination Styling */
        .pagination { /* General pagination container */
            margin-top: 1.5rem; /* Ensure space above pagination */
            text-align: center; /* Center pagination block */
        }
        .table-container .pagination { /* More specific if pagination is inside table-container */
            margin-top: 1rem; /* From new CSS */
            display: flex; /* Use flex for alignment */
            justify-content: center; /* Center flex items */
            gap: 0.5rem; /* Space between pagination items */
            align-items: center;
            font-size: 0.9rem;
            color: #777; /* Default text color for pagination info */
        }

        .table-container .pagination a,
        .table-container .pagination span {
            padding: 0.4rem 0.7rem;
            border-radius: 4px;
            text-decoration: none;
            color: #555; /* Link color */
            border: 1px solid #ddd; /* Border for links/spans */
            background-color: #fff; /* Background for links */
        }
        .table-container .pagination span { /* For non-link items like current page or ellipsis */
             background-color: #f9f9f9; /* Slightly different bg for spans if needed */
        }

        .table-container .pagination a:hover {
            background-color: #eee; /* Hover background for links */
            border-color: #ccc;
        }

        .table-container .pagination .current-page {
            font-weight: bold; /* Make current page stand out */
            color: white;
            background-color: #007bff; /* Primary color for current page */
            border-color: #007bff;
            cursor: default;
        }

        .table-container .pagination .disabled {
            color: #ccc; /* Disabled link text color */
            cursor: default;
            background-color: #f9f9f9; /* Lighter background for disabled */
            border-color: #eee;
        }
        .table-container .pagination .disabled:hover {
            background-color: #f9f9f9; /* No change on hover for disabled */
        }


        /* --- Styles for Side-by-Side Sections (IN/OUT Logs) --- */
        .attendance-tables-container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .attendance-table-wrapper {
            flex: 1;
            min-width: 400px; /* Min width before wrapping */
            background-color: #fff;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .no-logs-message {
            text-align: center;
            padding: 1rem;
            font-style: italic;
            color: #777;
        }

        /* Responsive adjustments for table */
        @media (max-width: 800px) {
            .table-container table thead {
                display: none; /* Hide table headers on small screens */
            }

            .table-container table tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #ddd; /* Border around each "card" */
                border-radius: 4px; /* Optional: rounded corners for cards */
            }

            .table-container table td {
                display: block; /* Stack cells vertically */
                text-align: right; /* Align cell content to the right */
                padding-left: 50%; /* Make space for the label */
                position: relative; /* For label positioning */
                border-bottom: 1px solid #eee; /* Separator for cells */
                white-space: normal; /* Allow text to wrap in responsive view */
            }
            .table-container table td:last-child {
                border-bottom: none; /* No border for the last cell in a card */
            }

            .table-container table td::before {
                content: attr(data-label); /* Use data-label for the pseudo-element content */
                position: absolute;
                left: 0;
                width: 45%; /* Width of the label */
                padding-left: 0.8rem; /* Padding for the label */
                padding-right: 0.5rem; /* Space between label and value */
                font-weight: bold;
                text-align: left;
                color: #333;
                white-space: nowrap; /* Prevent label from wrapping */
            }

            /* Specific labels from user's CSS - these will override data-label for first 5 columns */
            .table-container table td:nth-child(1)::before { content: "Name"; }
            .table-container table td:nth-child(2)::before { content: "Date Time"; }
            .table-container table td:nth-child(3)::before { content: "Card Scanned"; }
            .table-container table td:nth-child(4)::before { content: "Status"; }
            .table-container table td:nth-child(5)::before { content: "Action"; }

            .table-container .action-links { /* Ensure action links align well in responsive */
                text-align: right; /* Keep content (links) to the right */
            }

            .table-container .action-links a {
                display: inline-block; /* Or block for full width */
                margin-bottom: 0.3rem;
            }
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
                        <a href="./admin-gatepass-logs.php" class="nav-link"> <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg> <span>Filtered Logs</span> </a>
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

                <div class="table-container">
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

                        <div class="attendance-table-wrapper in-section">
                            <h3>IN Logs</h3>
                             <?php if (!empty($in_logs)) : ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Timestamp</th>
                                            <th>Type</th>
                                            <th>Device ID</th>
                                            <th>Location</th>
                                            <th>Method(s) Used</th>
                                            <th>Verification Details</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($in_logs as $row) : ?>
                                            <?php
                                            $attendance_log_id = $row['attendance_log_id'];
                                            $display_name = (isset($row['first_name']) && isset($row['last_name'])) ? htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) : 'Unknown User';
                                            $timestamp_formatted = date("M j, Y, g:i A", strtotime($row['timestamp']));
                                            $log_type_display = htmlspecialchars($row['log_type']);
                                            $device_id_display = htmlspecialchars($row['device_id']);
                                            $location_context_display = isset($row['location_context']) ? htmlspecialchars($row['location_context']) : 'N/A';

                                            $methods_used_list = [];
                                            if ((isset($row['face_recognized']) && $row['face_recognized'] !== NULL) || (isset($row['confidence_score']) && $row['confidence_score'] !== NULL)) {
                                                $methods_used_list[] = 'Face';
                                            }
                                            if (isset($row['rfid_card_id_used']) && $row['rfid_card_id_used'] !== NULL) {
                                                $methods_used_list[] = 'RFID';
                                            }
                                            $methods_used_text = empty($methods_used_list) ? 'N/A' : implode(', ', $methods_used_list);

                                            $verification_details_list = [];
                                            if (isset($row['face_recognized']) && $row['face_recognized'] === TRUE) { // Assuming TRUE is 1 or '1'
                                                $verification_details_list[] = 'Face: Recognized';
                                                if (isset($row['confidence_score']) && $row['confidence_score'] !== NULL) {
                                                    $verification_details_list[] = '(' . htmlspecialchars(number_format($row['confidence_score'], 2)) . '%)';
                                                }
                                            } elseif (isset($row['face_recognized']) && $row['face_recognized'] === FALSE) { // Assuming FALSE is 0 or '0'
                                                if (isset($row['confidence_score']) && $row['confidence_score'] !== NULL) {
                                                    $verification_details_list[] = 'Face: Not Recognized (' . htmlspecialchars(number_format($row['confidence_score'], 2)) . '%)';
                                                } else {
                                                    $verification_details_list[] = 'Face: Attempted';
                                                }
                                            } elseif (isset($row['confidence_score']) && $row['confidence_score'] !== NULL && !isset($row['face_recognized'])) { 
                                                // Case where face_recognized might be NULL but confidence exists (e.g. face attempted but no clear recognition status)
                                                $verification_details_list[] = 'Face: Score ' . htmlspecialchars(number_format($row['confidence_score'], 2)) . '%';
                                            }


                                            if (isset($row['rfid_card_id_used']) && $row['rfid_card_id_used'] !== NULL) {
                                                $verification_details_list[] = 'RFID: ' . htmlspecialchars($row['rfid_card_id_used']);
                                            }
                                            $verification_details_text = empty($verification_details_list) ? 'N/A' : implode('; ', $verification_details_list);
                                            
                                            $status_text = 'Unknown';
                                            $status_class_css = 'status-unknown';
                                            if (is_null($row['access_granted'])) {
                                                $status_text = 'Unknown';
                                                $status_class_css = 'status-unknown';
                                            } elseif ($row['access_granted'] == TRUE) { // Check for boolean TRUE, or 1
                                                $status_text = 'Granted';
                                                $status_class_css = 'status-granted';
                                            } else { // Covers FALSE, 0 or any other non-true, non-null value
                                                $status_text = 'Denied';
                                                $status_class_css = 'status-denied';
                                            }
                                            ?>
                                            <tr>
                                                <td data-label="Name"><?= $display_name ?></td>
                                                <td data-label="Timestamp"><?= $timestamp_formatted ?></td>
                                                <td data-label="Type"><?= $log_type_display ?></td>
                                                <td data-label="Device ID"><?= $device_id_display ?></td>
                                                <td data-label="Location"><?= $location_context_display ?></td>
                                                <td data-label="Method(s) Used"><?= $methods_used_text ?></td>
                                                <td data-label="Verification Details"><?= $verification_details_text ?></td>
                                                <td data-label="Status"><span class="status <?= $status_class_css ?>"><?= $status_text ?></span></td>
                                                <td data-label="Action" class="action-links">
                                                    <a href="view_log_details.php?id=<?= $attendance_log_id ?>" title="View Details">View</a>
                                                    <a href="delete_log.php?id=<?= $attendance_log_id ?>" class="delete-link" title="Delete Log" onclick="return confirm('Are you sure you want to delete this log entry?');">Delete</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <div class="no-logs-message">No IN logs found for the selected criteria.</div>
                            <?php endif; ?>
                        </div>

                        <div class="attendance-table-wrapper out-section">
                            <h3>OUT Logs</h3>
                            <?php if (!empty($out_logs)) : ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Timestamp</th>
                                            <th>Type</th>
                                            <th>Device ID</th>
                                            <th>Location</th>
                                            <th>Method(s) Used</th>
                                            <th>Verification Details</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($out_logs as $row) : ?>
                                            <?php
                                            $attendance_log_id = $row['attendance_log_id'];
                                            $display_name = (isset($row['first_name']) && isset($row['last_name'])) ? htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) : 'Unknown User';
                                            $timestamp_formatted = date("M j, Y, g:i A", strtotime($row['timestamp']));
                                            $log_type_display = htmlspecialchars($row['log_type']);
                                            $device_id_display = htmlspecialchars($row['device_id']);
                                            $location_context_display = isset($row['location_context']) ? htmlspecialchars($row['location_context']) : 'N/A';

                                            $methods_used_list = [];
                                            if ((isset($row['face_recognized']) && $row['face_recognized'] !== NULL) || (isset($row['confidence_score']) && $row['confidence_score'] !== NULL)) {
                                                $methods_used_list[] = 'Face';
                                            }
                                            if (isset($row['rfid_card_id_used']) && $row['rfid_card_id_used'] !== NULL) {
                                                $methods_used_list[] = 'RFID';
                                            }
                                            $methods_used_text = empty($methods_used_list) ? 'N/A' : implode(', ', $methods_used_list);

                                            $verification_details_list = [];
                                            if (isset($row['face_recognized']) && $row['face_recognized'] == TRUE) {
                                                $verification_details_list[] = 'Face: Recognized';
                                                if (isset($row['confidence_score']) && $row['confidence_score'] !== NULL) {
                                                    $verification_details_list[] = '(' . htmlspecialchars(number_format($row['confidence_score'], 2)) . '%)';
                                                }
                                            } elseif (isset($row['face_recognized']) && $row['face_recognized'] == FALSE) {
                                                if (isset($row['confidence_score']) && $row['confidence_score'] !== NULL) {
                                                    $verification_details_list[] = 'Face: Not Recognized (' . htmlspecialchars(number_format($row['confidence_score'], 2)) . '%)';
                                                } else {
                                                    $verification_details_list[] = 'Face: Attempted';
                                                }
                                             } elseif (isset($row['confidence_score']) && $row['confidence_score'] !== NULL && !isset($row['face_recognized'])) { 
                                                $verification_details_list[] = 'Face: Score ' . htmlspecialchars(number_format($row['confidence_score'], 2)) . '%';
                                            }

                                            if (isset($row['rfid_card_id_used']) && $row['rfid_card_id_used'] !== NULL) {
                                                $verification_details_list[] = 'RFID: ' . htmlspecialchars($row['rfid_card_id_used']);
                                            }
                                            $verification_details_text = empty($verification_details_list) ? 'N/A' : implode('; ', $verification_details_list);

                                            $status_text = 'Unknown';
                                            $status_class_css = 'status-unknown';
                                            if (is_null($row['access_granted'])) {
                                                $status_text = 'Unknown';
                                                $status_class_css = 'status-unknown';
                                            } elseif ($row['access_granted'] == TRUE) {
                                                $status_text = 'Granted';
                                                $status_class_css = 'status-granted';
                                            } else {
                                                $status_text = 'Denied';
                                                $status_class_css = 'status-denied';
                                            }
                                            ?>
                                            <tr>
                                                <td data-label="Name"><?= $display_name ?></td>
                                                <td data-label="Timestamp"><?= $timestamp_formatted ?></td>
                                                <td data-label="Type"><?= $log_type_display ?></td>
                                                <td data-label="Device ID"><?= $device_id_display ?></td>
                                                <td data-label="Location"><?= $location_context_display ?></td>
                                                <td data-label="Method(s) Used"><?= $methods_used_text ?></td>
                                                <td data-label="Verification Details"><?= $verification_details_text ?></td>
                                                <td data-label="Status"><span class="status <?= $status_class_css ?>"><?= $status_text ?></span></td>
                                                <td data-label="Action" class="action-links">
                                                    <a href="view_log_details.php?id=<?= $attendance_log_id ?>" title="View Details">View</a>
                                                    <a href="delete_log.php?id=<?= $attendance_log_id ?>" class="delete-link" title="Delete Log" onclick="return confirm('Are you sure you want to delete this log entry?');">Delete</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <div class="no-logs-message">No OUT logs found for the selected criteria.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($total_pages > 1) : ?>
                    <div class="pagination">
                        <?php
                        // Build base URL for pagination links
                        $page_query_params = $query_params_for_pagination; // Use the array built earlier for filters

                        // Previous page link
                        if ($page > 1) {
                            $page_query_params['page'] = $page - 1;
                            echo '<a href="?' . http_build_query($page_query_params) . '">&laquo; Prev</a>';
                        } else {
                            echo '<span class="disabled">&laquo; Prev</span>';
                        }

                        // Page number links (simplified example)
                        // You might want a more complex logic for many pages (e.g., 1 ... 4 5 6 ... 10)
                        $num_links_to_show = 5;
                        $start_page = max(1, $page - floor($num_links_to_show / 2));
                        $end_page = min($total_pages, $start_page + $num_links_to_show - 1);
                        
                        if ($start_page > 1) {
                             $page_query_params['page'] = 1;
                             echo '<a href="?' . http_build_query($page_query_params) . '">1</a>';
                             if ($start_page > 2) {
                                 echo '<span class="disabled">...</span>';
                             }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $page) {
                                echo '<span class="current-page">' . $i . '</span>';
                            } else {
                                $page_query_params['page'] = $i;
                                echo '<a href="?' . http_build_query($page_query_params) . '">' . $i . '</a>';
                            }
                        }
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages -1) {
                                 echo '<span class="disabled">...</span>';
                            }
                            $page_query_params['page'] = $total_pages;
                            echo '<a href="?' . http_build_query($page_query_params) . '">' . $total_pages . '</a>';
                        }


                        // Next page link
                        if ($page < $total_pages) {
                            $page_query_params['page'] = $page + 1;
                            echo '<a href="?' . http_build_query($page_query_params) . '">Next &raquo;</a>';
                        } else {
                            echo '<span class="disabled">Next &raquo;</span>';
                        }
                        ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </main>
    </div>

    <script>
        // Basic script for sidebar toggle, loading indicator, and search (conceptual)
        document.addEventListener('DOMContentLoaded', function () {
            // Hide loader and show content
            const loadingIndicator = document.getElementById('loading-indicator');
            const dashboardLayout = document.getElementById('dashboard-layout');
            if (loadingIndicator && dashboardLayout) {
                loadingIndicator.classList.add('hidden');
                dashboardLayout.style.visibility = 'visible';
            }

            // Sidebar toggle
            const hamburgerButton = document.getElementById('hamburger-button');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');

            if (hamburgerButton && sidebar && mainContent) {
                hamburgerButton.addEventListener('click', function () {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                });
            }

            // Simple on-page search (searches text content within log rows)
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function () {
                    const filter = searchInput.value.toLowerCase();
                    const tables = document.querySelectorAll('.table-container table tbody');
                    tables.forEach(tbody => {
                        const rows = tbody.getElementsByTagName('tr');
                        for (let i = 0; i < rows.length; i++) {
                            let textContent = rows[i].textContent || rows[i].innerText;
                            if (textContent.toLowerCase().indexOf(filter) > -1) {
                                rows[i].style.display = '';
                            } else {
                                rows[i].style.display = 'none';
                            }
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
<?php
mysqli_close($conn); // Close DB connection at the very end
?>