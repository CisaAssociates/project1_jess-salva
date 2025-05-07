
<?php

include("db_config.php");

// Connect to the database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Could not connect to the database. Please try again later.");
}

$errorMessage = '';
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$userData = null;

// Fetch existing user data
if ($userId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_SPECIAL_CHARS);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS);

    $errors = [];

    if (empty($firstName)) $errors[] = "First name is required.";
    if (empty($lastName)) $errors[] = "Last name is required.";
    if ($email === false && !empty($_POST['email'])) $errors[] = "Invalid email format.";
    elseif (empty($email) && !isset($_POST['email'])) $email = null;
    if (empty($role)) $errors[] = "Role is required.";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, password = ? WHERE user_id = ?");
            $stmt->execute([$firstName, $lastName, $email, $role, $password, $userId]);

            header("Location: manage-users.php");
            exit;
        } catch (PDOException $e) {
            error_log("Database update failed: " . $e->getMessage());
            if ($e->getCode() == '23000') {
                $errorMessage = "The email address is already in use.";
            } else {
                $errorMessage = "An error occurred. Please try again.";
            }
        }
    } else {
        $errorMessage = implode("<br>", array_map('htmlspecialchars', $errors));
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Your CSS/Styles remain unchanged -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create Account</title>
    <link rel="stylesheet" href="style.css" />
    <style>
        /* Keep your existing CSS here — omitted for brevity */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            display: flex;
            min-height: 100vh;
            /* min-h-screen */
            width: 100%;
            /* w-full */
            align-items: center;
            /* items-center */
            justify-content: center;
            /* justify-center */
            background-color: #eff6ff;
            /* Fallback */
            /* bg-gradient-to-br from-blue-50 to-indigo-50 */
            background-image: linear-gradient(to bottom right, #eff6ff, #e0e7ff);
            padding: 1rem;
            /* p-4 */
        }

        /* Form Container */
        .container {
            width: 100%;
        }

        /* Form Styling */
        .signup-form {
            width: 100%;
            /* w-full */
            max-width: 28rem;
            /* max-w-md */
            margin: 0 auto;
            /* Center the form */
            background-color: #ffffff;
            /* bg-white */
            padding: 2rem;
            /* p-8 */
            border-radius: 1rem;
            /* rounded-2xl */
            /* shadow-xl */
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .form-title {
            margin-bottom: 2rem;
            /* mb-8 */
            text-align: center;
            /* text-center */
            font-size: 1.875rem;
            /* text-3xl */
            line-height: 2.25rem;
            font-weight: 700;
            /* font-bold */
            color: #1f2937;
            /* text-gray-800 */
        }

        /* Form Fields Container (mimicking space-y-6) */
        .form-fields>*+* {
            margin-top: 1.5rem;
            /* space-y-6 */
        }

        /* Input Group (mimicking space-y-2) */
        .input-group>*+* {
            margin-top: 0.5rem;
            /* space-y-2 */
        }

        .input-label {
            display: block;
            /* block */
            font-size: 0.875rem;
            /* text-sm */
            line-height: 1.25rem;
            font-weight: 500;
            /* font-medium */
            color: #374151;
            /* text-gray-700 */
        }

        .input-wrapper {
            overflow: hidden;
            /* overflow-hidden */
            border-radius: 0.5rem;
            /* rounded-lg */
            border: 1px solid #e5e7eb;
            /* border border-gray-200 */
            background-color: #ffffff;
            /* bg-white */
            padding: 0.75rem 1rem;
            /* px-4 py-3 */
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        /* Focus Within Styling */
        .input-wrapper:focus-within {
            border-color: #357AFF;
            /* focus-within:border-[#357AFF] */
            /* focus-within:ring-1 focus-within:ring-[#357AFF] */
            box-shadow: 0 0 0 1px #357AFF;
        }

        .input-field {
            width: 100%;
            /* w-full */
            background-color: transparent;
            /* bg-transparent */
            font-size: 1.125rem;
            /* text-lg */
            line-height: 1.75rem;
            outline: none;
            /* outline-none */
            border: none;
            /* Remove default input border */
        }

        .input-field::placeholder {
            color: #9ca3af;
            /* text-gray-400 or similar */
        }

        /* Error Message Styling */
        .error-message {
            border-radius: 0.5rem;
            /* rounded-lg */
            background-color: #fee2e2;
            /* bg-red-50 */
            padding: 0.75rem;
            /* p-3 */
            font-size: 0.875rem;
            /* text-sm */
            line-height: 1.25rem;
            color: #ef4444;
            /* text-red-500 */
            border: 1px solid #fecaca;
            /* Optional: add a subtle border */
        }

        /* Hidden Utility Class */
        .hidden {
            display: none;
        }

        /* Submit Button Styling */
        .submit-button {
            width: 100%;
            /* w-full */
            border-radius: 0.5rem;
            /* rounded-lg */
            background-color: #357AFF;
            /* bg-[#357AFF] */
            padding: 0.75rem 1rem;
            /* px-4 py-3 */
            font-size: 1rem;
            /* text-base */
            line-height: 1.5rem;
            font-weight: 500;
            /* font-medium */
            color: #ffffff;
            /* text-white */
            border: none;
            cursor: pointer;
            /* transition-colors */
            transition: background-color 0.2s ease-in-out, opacity 0.2s ease-in-out;
        }

        .submit-button:hover {
            background-color: #2E69DE;
            /* hover:bg-[#2E69DE] */
        }

        .submit-button:focus {
            outline: 2px solid transparent;
            /* focus:outline-none */
            outline-offset: 2px;
            /* focus:ring-2 focus:ring-[#357AFF] focus:ring-offset-2 */
            box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px #357AFF;
        }

        .submit-button:disabled {
            opacity: 0.5;
            /* disabled:opacity-50 */
            cursor: not-allowed;
        }

        /* Login Link Styling */
        .login-link {
            text-align: center;
            /* text-center */
            font-size: 0.875rem;
            /* text-sm */
            line-height: 1.25rem;
            color: #4b5563;
            /* text-gray-600 */
        }

        .login-link a {
            color: #357AFF;
            /* text-[#357AFF] */
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            color: #2E69DE;
            /* hover:text-[#2E69DE] */
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <form method="post" action="" id="signup-form" novalidate class="signup-form">
            <h1 class="form-title">Update Account</h1>

            <div class="form-fields">
                <div class="input-group">
                    <label for="first_name" class="input-label">First Name</label>
                    <div class="input-wrapper">
                        <input required id="first_name" name="first_name" type="text" placeholder="Enter your first name" class="input-field" value="<?= htmlspecialchars($userData['first_name'] ?? '') ?>" />
                    </div>
                </div>

                <div class="input-group">
                    <label for="last_name" class="input-label">Last Name</label>
                    <div class="input-wrapper">
                        <input required id="last_name" name="last_name" type="text" placeholder="Enter your last name" class="input-field" value="<?= htmlspecialchars($userData['last_name'] ?? '') ?>" />
                    </div>
                </div>

                <div class="input-group">
                    <label for="role" class="input-label">Role</label>
                    <div class="input-wrapper">
                        <input required id="role" name="role" type="text" placeholder="Enter your role" class="input-field" value="<?= htmlspecialchars($userData['role'] ?? '') ?>" />
                    </div>
                </div>

                <div class="input-group">
                    <label for="email" class="input-label">Email</label>
                    <div class="input-wrapper">
                        <input required id="email" name="email" type="email" placeholder="Enter your email" class="input-field" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" />
                    </div>
                </div>

                <div class="input-group">
                    <label for="password" class="input-label">Password</label>
                    <div class="input-wrapper">
                        <input required id="password" name="password" type="password" placeholder="Enter new password" class="input-field" />
                    </div>
                </div>

                <div class="input-group">
                    <label for="confirmPassword" class="input-label">Confirm Password</label>
                    <div class="input-wrapper">
                        <input required id="confirmPassword" name="confirmPassword" type="password" placeholder="Confirm your password" class="input-field" />
                    </div>
                </div>

                <?php if (!empty($errorMessage)): ?>
                    <div class="error-message">
                        <?= $errorMessage ?>
                    </div>
                <?php endif; ?>

                <button type="submit" class="submit-button">Update</button>

                <p class="login-link">
                    Return to <a href="./admin-dashboard.php">dashboard</a>
                </p>
            </div>
        </form>
    </div>
</body>
</html>

