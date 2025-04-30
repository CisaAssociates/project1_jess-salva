<?php
include("db_config.php"); // Include your database connection file

// Start session (if not already started)
if (session_status() == PHP_SESSION_NONE) {
    session_start();

    foreach($_SESSION as $key => $value){
        $$key = $value;
    }

    if($role != "Admin"){
        header("Location: index.php");
    }
}

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Initialize variables for form data and errors
$user_id = null;
$first_name = "";
$last_name = "";
$email = "";
$role = "";
$is_active = 1; // Default to active
$errors = [];
$success_message = "";

// Check if user ID is provided in the GET request for editing
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = $_GET['id'];

    // Fetch user data to pre-populate the form
    $conn = mysqli_connect($host, $user, $pass, $db);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }

    $sql = "SELECT user_id, first_name, last_name, email, role, is_active FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        $first_name = htmlspecialchars($row['first_name']);
        $last_name = htmlspecialchars($row['last_name']);
        $email = htmlspecialchars($row['email']);
        $role = htmlspecialchars($row['role']);
        $is_active = (int)$row['is_active'];
    } else {
        $errors[] = "User not found.";
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
} elseif (isset($_POST['user_id'])) {
    // Form submitted for editing

    // Retrieve and sanitize form data
    $user_id = sanitize_input($_POST['user_id']);
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $email = sanitize_input($_POST['email']);
    $role = sanitize_input($_POST['role']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validate form data
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($role)) {
        $errors[] = "Role is required.";
    }

    // If no errors, update the database
    if (empty($errors)) {
        $conn = mysqli_connect($host, $user, $pass, $db);
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }

        $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, is_active = ?, updated_at = NOW() WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssii", $first_name, $last_name, $email, $role, $is_active, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            $success_message = "User information updated successfully!";
        } else {
            $errors[] = "Error updating user: " . mysqli_error($conn);
        }

        mysqli_stmt_close($stmt);
        mysqli_close($conn);
    }
} else {
    // No user ID provided for editing
    $errors[] = "Invalid request. No user ID provided.";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($user_id) ? 'Edit User' : 'Error'; ?></title>
    <link rel="stylesheet" href="./styles/style.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="email"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 5px;
        }
        .error-message {
            color: red;
            margin-bottom: 10px;
        }
        .success-message {
            color: green;
            margin-bottom: 10px;
        }
        .button-group {
            margin-top: 20px;
        }
        .button {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        .primary {
            background-color: #007bff;
            color: white;
        }
        .secondary {
            background-color: #6c757d;
            color: white;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo ($user_id) ? 'Edit User' : 'Error'; ?></h1>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <p><?php echo $success_message; ?></p>
                <p><a href="manage_users.php">Back to User List</a></p>
            </div>
        <?php endif; ?>

        <?php if ($user_id): ?>
            <div class="form-container">
                <form method="post">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

                    <div class="form-group">
                        <label for="first_name">First Name:</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo $first_name; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name:</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo $last_name; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo $email; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="role">Role:</label>
                        <select id="role" name="role" required>
                            <option value="admin" <?php if ($role === 'admin') echo 'selected'; ?>>Admin</option>
                            <option value="editor" <?php if ($role === 'editor') echo 'selected'; ?>>Editor</option>
                            <option value="viewer" <?php if ($role === 'viewer') echo 'selected'; ?>>Viewer</option>
                            </select>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?php if ($is_active === 1) echo 'checked'; ?>>
                        <label for="is_active">Active</label>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="button primary">Save Changes</button>
                        <a href="manage_users.php" class="button secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php elseif (!empty($errors)): ?>
            <p><a href="manage_users.php">Back to User List</a></p>
        <?php endif; ?>
    </div>
</body>
</html>