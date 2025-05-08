<?php
include("db_config.php");
session_start();


if (!isset($_SESSION['user_data'])) {
    header("Location: index.php");
    exit();
}

// Safer way to extract session variables, compatible with older PHP
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
$filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');


$filter_start_date = (preg_match("/^\d{4}-\d{2}-\d{2}$/", $filter_start_date)) ? $filter_start_date : '';
$filter_end_date = (preg_match("/^\d{4}-\d{2}-\d{2}$/", $filter_end_date)) ? $filter_end_date : '';

// --- Pagination Logic ---
$limit = 100;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Construct the base URL for pagination links while preserving filters
$base_pagination_url = '?';
if (!empty($filter_start_date)) {
    $base_pagination_url .= 'start_date=' . urlencode($filter_start_date) . '&';
}
if (!empty($filter_end_date)) {
    $base_pagination_url .= 'end_date=' . urlencode($filter_end_date) . '&';
}
// Remove trailing '&' if no filters were applied
$base_pagination_url = rtrim($base_pagination_url, '&');


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

// --- Calculate Total Records (for pagination, uses the new base and where) ---
$sql_total = "SELECT COUNT(DISTINCT l.user_id, DATE(l.timestamp)) " . $sql_base . $sql_where; // Count distinct user+date entries
$stmt_total = mysqli_prepare($conn, $sql_total);

if (!$stmt_total) {
    die("Error preparing total count query: " . mysqli_error($conn) . " SQL: " . $sql_total);
}

// Bind parameters if any exist for the WHERE clause using call_user_func_array
if (!empty($params)) {
    // Prepare parameters for call_user_func_array (pass by reference)
    $a_params_total = [];
    $a_params_total[] = &$param_types;
    for ($i = 0; $i < count($params); $i++) {
        $a_params_total[] = &$params[$i];
    }
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_total], $a_params_total));
}

mysqli_stmt_execute($stmt_total);
$result_total = mysqli_stmt_get_result($stmt_total);
// Fetch the count correctly - using mysqli_fetch_array with index 0
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
    ORDER BY u.last_name ASC, u.first_name ASC, l.timestamp ASC -- Order by user then date/time for processing
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


// Create associative arrays to organize logs by user and date
$user_logs = [];

if ($result_logs) {
    while ($row = mysqli_fetch_assoc($result_logs)) {
        // Create a date key for grouping logs by day - this helps match IN/OUT per day
        $log_date = date("Y-m-d", strtotime($row['timestamp']));

        // Create a unique key for each user per day
        $user_day_key = $row['user_id'] . '_' . $log_date;

        // Initialize the user's entry for this day if needed
        if (!isset($user_logs[$user_day_key])) {
            $user_logs[$user_day_key] = [
                'user_id' => $row['user_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'role' => $row['role'],
                'date' => $log_date,
                'in_log' => null,
                'out_log' => null
            ];
        }

        // Store the log in the appropriate slot (in or out)
        // If multiple logs of same type exist for same day, use the earliest IN and latest OUT
        if ($row['log_type'] === 'in') {
            if (
                is_null($user_logs[$user_day_key]['in_log']) ||
                strtotime($row['timestamp']) < strtotime($user_logs[$user_day_key]['in_log']['timestamp'])
            ) {
                $user_logs[$user_day_key]['in_log'] = $row;
            }
        } elseif ($row['log_type'] === 'out') {
            if (
                is_null($user_logs[$user_day_key]['out_log']) ||
                strtotime($row['timestamp']) > strtotime($user_logs[$user_day_key]['out_log']['timestamp'])
            ) {
                $user_logs[$user_day_key]['out_log'] = $row;
            }
        }
    }
}

// Sort logs by user name (ascending) and then by date (ascending)
usort($user_logs, function ($a, $b) {
    // First sort by name (ascending)
    $name_a = $a['last_name'] . ', ' . $a['first_name'];
    $name_b = $b['last_name'] . ', ' . $b['first_name'];
    $name_compare = strcmp($name_a, $name_b);
    if ($name_compare !== 0) {
        return $name_compare;
    }

    // If same name, sort by date (ascending)
    return strtotime($a['date']) - strtotime($b['date']);
});


// Function to format log display
function formatLogDetails($log)
{
    if (!$log) {
        return '<div class="no-log-details">No details available</div>';
    }

    $timestamp_formatted = date("M j, Y, g:i A", strtotime($log['timestamp']));
    $device_id = htmlspecialchars($log['device_id']);
    $location_context = isset($log['location_context']) ? htmlspecialchars($log['location_context']) : 'N/A';

    // Determine Method(s) Used
    $methods_used_list = [];
    // Check if face recognition data exists (not NULL for face_recognized or confidence_score)
    if (
        (isset($log['face_recognized']) && $log['face_recognized'] !== NULL) ||
        (isset($log['confidence_score']) && $log['confidence_score'] !== NULL)
    ) {
        $methods_used_list[] = 'Face';
    }
    if (isset($log['rfid_card_id_used']) && $log['rfid_card_id_used'] !== NULL) {
        $methods_used_list[] = 'RFID';
    }
    $methods_used_text = empty($methods_used_list) ? 'N/A' : implode(', ', $methods_used_list);

    // Determine Verification Details
    $verification_details_list = [];
    if (isset($log['face_recognized']) && $log['face_recognized'] === TRUE) {
        $verification_details_list[] = 'Face: Recognized';
        if (isset($log['confidence_score']) && $log['confidence_score'] !== NULL) {
            $verification_details_list[] = '(' . htmlspecialchars(number_format($log['confidence_score'], 2)) . '%)';
        }
    } elseif (isset($log['face_recognized']) && $log['face_recognized'] === FALSE) {
         if (isset($log['confidence_score']) && $log['confidence_score'] !== NULL) {
            $verification_details_list[] = 'Face: Not Recognized (' .
                htmlspecialchars(number_format($log['confidence_score'], 2)) . '%)';
        } else {
            $verification_details_list[] = 'Face: Attempted'; // Or just leave empty if no score for failed attempts
        }
    }

    if (isset($log['rfid_card_id_used']) && $log['rfid_card_id_used'] !== NULL) {
        $verification_details_list[] = 'RFID: ' . htmlspecialchars($log['rfid_card_id_used']);
    }
    $verification_details_text = empty($verification_details_list) ? 'N/A' : implode(' ', $verification_details_list);


    // Determine Access Granted Status Display
    $access_granted = isset($log['access_granted']) ? $log['access_granted'] : FALSE;
    $status_text = $access_granted ? 'Granted' : 'Denied';
    $status_class = $access_granted ? 'status-granted' : 'status-denied';

    // Get attendance log ID for action links
    $attendance_log_id = $log['attendance_log_id'];

    $output = <<<HTML
        <div class="log-details">
            <div class="log-detail"><span class="detail-name">Time</span><span class="detail-value">{$timestamp_formatted}</span></div>
            <div class="log-detail"><span class="detail-name">Device ID</span><span class="detail-value">{$device_id}</span></div>
            <div class="log-detail"><span class="detail-name">Location</span><span class="detail-value">{$location_context}</span></div>
            <div class="log-detail"><span class="detail-name">Method(s)</span><span class="detail-value">{$methods_used_text}</span></div>
            <div class="log-detail"><span class="detail-name">Verification</span><span class="detail-value">{$verification_details_text}</span></div>
            <div class="log-detail"><span class="detail-name">Status</span><span class="detail-value"><span class="status {$status_class}">{$status_text}</span></span></div>
            <div class="log-detail action-links"><span class="detail-name">Action</span><span class="detail-value">
                <a href="view_log_details.php?id={$attendance_log_id}" title="View Details">View</a>
                <a href="delete_log.php?id={$attendance_log_id}" class="delete-link" title="Delete Log"
                   onclick="return confirm('Are you sure you want to delete this log entry?');">Delete</a>
            </span></div>
        </div>
HTML;

    return $output;
}
?>


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
                                <p>Filtered Attendance Logs (Daily Summary)</p>
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

                    <div class="table-controls" style="margin-bottom: 20px;">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search logs on this page...">
                    </div>

                    <div class="attendance-tables-container">
                        <div class="attendance-table" id="attendance-table"> <div class="attendance-header">
                                <div class="user-info-column">Student Details</div>
                                <div class="log-column">IN Record</div>
                                <div class="log-column">OUT Record</div>
                            </div>

                            <?php if (empty($user_logs)): ?>
                                <div class="no-logs-message">No attendance logs found for the selected criteria.</div>
                            <?php else: ?>
                                <?php foreach ($user_logs as $user_log): ?>
                                    <?php
                                    $display_name = (isset($user_log['first_name']) && isset($user_log['last_name'])) ?
                                        htmlspecialchars($user_log['first_name'] . ' ' . $user_log['last_name']) : 'Unknown User';
                                    $display_date = date("M j, Y", strtotime($user_log['date']));
                                    $display_email = htmlspecialchars($user_log['email'] ?? 'No Email');
                                    $display_role = htmlspecialchars($user_log['role'] ?? 'No Role');
                                    ?>
                                    <div class="attendance-row">
                                        <div class="user-info-column">
                                            <div class="user-name"><?= $display_name ?></div>
                                            <div class="user-date"><?= $display_date ?></div>
                                            <div class="user-detail"><?= $display_email ?></div>
                                            <div class="user-detail"><?= $display_role ?></div>
                                        </div>
                                        <div class="log-column in-log">
                                            <?= formatLogDetails($user_log['in_log']) ?>
                                        </div>
                                        <div class="log-column out-log">
                                            <?= formatLogDetails($user_log['out_log']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>


                    <?php if ($total_pages > 1) : ?>
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
                                    // Avoid printing ellipsis if the gap is only one page wide
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