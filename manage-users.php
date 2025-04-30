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

// --- Pagination Logic ---
$limit = 100; // Number of records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total number of users
$sql_total = "SELECT COUNT(*) FROM users";
$stmt_total = mysqli_prepare($conn, $sql_total);
mysqli_stmt_execute($stmt_total);
$result_total = mysqli_stmt_get_result($stmt_total);
$total_records = mysqli_fetch_array($result_total)[0];
$total_pages = ceil($total_records / $limit);
mysqli_stmt_close($stmt_total);

// --- Fetch Users for the Current Page ---
$sql_users = "SELECT * FROM users INNER JOIN faceencodings ON users.user_id = faceencodings.user_id WHERE users.role != 'Admin' ORDER BY users.user_id LIMIT ? OFFSET ?";
$stmt_users = mysqli_prepare($conn, $sql_users);
mysqli_stmt_bind_param($stmt_users, "ii", $limit, $offset);
mysqli_stmt_execute($stmt_users);
$result_users = mysqli_stmt_get_result($stmt_users);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - User Management</title>
    <link rel="stylesheet" href="./styles/style.css">
    <link rel="stylesheet" href="./styles/manage-user.css">
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
                    <span class="dashboard-title">User Management</span>
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
                        <a href="./admin-dashboard.php" class="nav-link">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span>Main Dashboard</span>
                        </a>
                    </li>
                    <!-- <li>
                        <a href="./upload_image.php?user_id=<?= $user_id ?>" class="nav-link">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>Profile</span>
                        </a>
                    </li> -->
                    <li>
                        <a href="./attendance-dashboard.php" class="nav-link ">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg> <span>Attendance Logs</span>
                        </a>
                    </li>
                    <li>
                        <a href="./manage-users.php" class="nav-link active">
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
                        <h2>Welcome to User Management, <?= htmlspecialchars($first_name) ?>!</h2>
                        <p>Here you can manage user accounts.</p>
                        </div>

                    </div>

                <div id="error-display" class="error-display hidden"></div>

                <div class="table-container table-responsive">
                    <h2>User List</h2>

                    <div class="table-controls">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search users on this page...">
                        <button class="button primary" onclick="window.location.href='http://localhost/client/admin_register_user.php'">Add New User</button>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Profile</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody">
                            <?php
                            if (mysqli_num_rows($result_users) > 0) {
                                while ($row = mysqli_fetch_assoc($result_users)) {
                                    $user_id = $row['user_id'];
                                    $first_name = htmlspecialchars($row['first_name']);
                                    $last_name = htmlspecialchars($row['last_name']);
                                    $email = htmlspecialchars($row['email']);
                                    $role = htmlspecialchars($row['role']);
                                    $is_active = $row['is_active'];
                                    $pic = $row['face_image_path'];
                                    $created_at = date("M j, Y, g:i A", strtotime($row['created_at']));
                                    $status_class = $is_active ? 'status-active' : 'status-inactive';
                                    $status_text = $is_active ? 'Active' : 'Inactive';

                                    echo <<<HTML
                                        <tr>
                                            <td>{$user_id}</td>
                                            <td>{$first_name}</td>
                                            <td>{$last_name}</td>
                                            <td>{$email}</td>
                                            <td>{$role}</td>
                                            <td><span class="{$status_class}">{$status_text}</span></td>
                                            <td>{$created_at}</td>
                                            <td class='profile-picture'><img src="{$pic}" alt=""></td>
                                            <td class="action-links">
                                                <a href="admin-view-user.php?id={$user_id}" class="edit-link" title="Edit User">View</a>
                                                <a href="edit.php?id={$user_id}" class="edit-link" title="Edit User">Edit</a>
                                                <a href="delete_user.php?id={$user_id}" class="delete-link" title="Delete User" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                                            </td>
                                        </tr>
                                    HTML;
                                }
                            } else {
                                echo '<tr><td colspan="8" style="text-align: center; padding: 1rem;">No users found.</td></tr>';
                            }
                            mysqli_stmt_close($stmt_users); // Close the statement for users
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
            const tableBody = document.getElementById('userTableBody');
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
                        // Check if it's a data row
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

            // --- Initial Display Logic ---
            const loadingIndicator = document.getElementById('loading-indicator');
            const dashboardLayout = document.getElementById('dashboard-layout');
            if (loadingIndicator) loadingIndicator.classList.add('hidden'); // Hide loading
            if (dashboardLayout) dashboardLayout.classList.remove('hidden'); // Show dashboard

        });
    </script>
</body>
</html>