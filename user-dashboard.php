<?php
include("db_config.php");
session_start();

// Redirect if user data is not in session
if (!isset($_SESSION['user_data'])) {
    header("Location: login.php"); // Or your login page
    exit();
}

foreach ($_SESSION['user_data'] as $key => $value) {
    $$key = $value; // Extracts user_id, email, first_name, last_name, etc.
}

$conn = mysqli_connect($host, $user, $pass, $db);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// --- Get User's RFID Card ---
$card_id = null; // Initialize card_id
$sql_card = "SELECT card_id FROM rfidcards WHERE user_id = ?";
$stmt_card = mysqli_prepare($conn, $sql_card);
mysqli_stmt_bind_param($stmt_card, "i", $user_id);
mysqli_stmt_execute($stmt_card);
$result_card = mysqli_stmt_get_result($stmt_card);
if ($data_card = mysqli_fetch_assoc($result_card)) {
    $card_id = $data_card['card_id'];
}
mysqli_stmt_close($stmt_card);


// --- Pagination Logic ---
$limit = 10; // Number of records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total number of records for this user (or all if admin)
// Assuming accesslogs links to users somehow, e.g., via rfid_scanned matching rfidcards.card_id
// For simplicity here, we fetch ALL logs. Adjust the query if logs should be user-specific.
// If logs ARE user-specific and linked via card_id:
// $sql_total = "SELECT COUNT(*) FROM accesslogs WHERE rfid_scanned = (SELECT card_id FROM rfidcards WHERE user_id = ? LIMIT 1)";
// $stmt_total = mysqli_prepare($conn, $sql_total);
// mysqli_stmt_bind_param($stmt_total, "i", $user_id);

// If showing ALL logs (as per original code):
$sql_total = "SELECT COUNT(*) FROM accesslogs WHERE user_id = $user_id";
$stmt_total = mysqli_prepare($conn, $sql_total);



mysqli_stmt_execute($stmt_total);
$result_total = mysqli_stmt_get_result($stmt_total);
$total_records = mysqli_fetch_array($result_total)[0];
$total_pages = ceil($total_records / $limit);
mysqli_stmt_close($stmt_total);

// --- Fetch Data for the Current Page ---
// Add ORDER BY for consistent pagination
$sql_logs = "SELECT l.*, u.first_name, u.last_name
             FROM accesslogs l
             LEFT JOIN users u ON l.user_id = u.user_id WHERE u.user_id = $user_id
             ORDER BY l.timestamp DESC
             LIMIT ? OFFSET ?";
$stmt_logs = mysqli_prepare($conn, $sql_logs);
mysqli_stmt_bind_param($stmt_logs, "ii", $limit, $offset);
mysqli_stmt_execute($stmt_logs);
$result_logs = mysqli_stmt_get_result($stmt_logs);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Access Logs</title>
    <link rel="stylesheet" href="./styles/style.css">
    <link rel="stylesheet" href="./styles/user-dashboard.css">
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
                    <span class="dashboard-title">Dashboard</span>
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
                        <a href="./user-dashboard.php" class="nav-link active"> <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="./user-attendance.php" class="nav-link "> <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span>Attendance</span>
                        </a>
                    </li>
                    <!-- <li>
                        <a href="./upload_image.php?user_id=<?= $user_id ?>" class="nav-link"> <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>Profile</span>
                        </a>
                    </li> -->
                </ul>
            </div>
        </aside>

        <main class="main-content" id="main-content">
            <div class="main-content-inner">

                <div class="content-grid">
                    <div class="card">
                        <h2>Welcome back, <?= htmlspecialchars($first_name) ?>!</h2>
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
                                <!-- <th>Action</th> -->
                            </tr>
                        </thead>
                        <tbody id="logTableBody">
                            <?php
                            if (mysqli_num_rows($result_logs) > 0) {
                                while ($row = mysqli_fetch_assoc($result_logs)) {
                                    $log_id = $row['log_id']; // Assuming you have a log_id primary key
                                    $display_name = (isset($row['first_name']) && isset($row['last_name'])) ? htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) : 'Unknown User';
                                    $timestamp_raw = $row['timestamp'];
                                    $rfid_scanned = $row['rfid_scanned'] == "" ? "No Card Scanned":$row['rfid_scanned'];
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
                                echo '<tr><td colspan="5" style="text-align: center; padding: 1rem;">No access logs found.</td></tr>';
                            }
                            mysqli_stmt_close($stmt_logs); // Close the statement for logs
                            mysqli_close($conn); // Close DB connection after fetching all needed data
                            ?>

                        </tbody>
                    </table>

                    <div class="pagination">
                        <?php if ($page > 1) : ?>
                            <a href="?page=<?= $page - 1 ?>">&laquo; Previous</a>
                        <?php else: ?>
                            <span class="disabled">&laquo; Previous</span>
                        <?php endif; ?>

                        <?php
                        // Display page numbers (optional: limit the number shown for many pages)
                        $range = 2; // Number of links to show around the current page
                        for ($i = 1; $i <= $total_pages; $i++) {
                            if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)) {
                                if ($i == $page) {
                                    echo '<span class="current-page">' . $i . '</span>';
                                } else {
                                    echo '<a href="?page=' . $i . '">' . $i . '</a>';
                                }
                            } elseif (($i == $page - $range - 1) || ($i == $page + $range + 1)) {
                                // Add ellipsis (...) if needed
                                echo '<span>...</span>';
                            }
                        }
                        ?>

                        <?php if ($page < $total_pages) : ?>
                            <a href="?page=<?= $page + 1 ?>">Next &raquo;</a>
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