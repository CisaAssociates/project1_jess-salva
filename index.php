<?php 
include("db_config.php");

if($_SERVER['REQUEST_METHOD'] == "POST"){

    $email = $_POST["email"];
    $password = $_POST["password"];

    $conn = mysqli_connect($host, $user, $pass, $db);
    $sql = "SELECT * FROM users WHERE email = '$email' AND password='$password'";

    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);

    if(mysqli_num_rows($result)){
        session_start();
        $_SESSION['user_data'] = $data;   
        if($data['role'] == 'Admin'){
            header("Location: admin-dashboard.php");
            exit();
        }else{
            header("Location: user-dashboard.php");
            exit();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Welcome Back</title>
    <style>
        /* Basic Reset & Body Styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            line-height: 1.5;
            color: #374151; /* gray-700 */
        }

        /* Full Screen Container */
        .login-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            align-items: center;
            justify-content: center;
            /* Gradient similar to from-blue-50 to-indigo-50 */
            background: linear-gradient(to bottom right, #EFF6FF, #E0E7FF);
            padding: 1rem; /* p-4 */
        }

        /* Login Form Card */
        .login-form {
            width: 100%;
            max-width: 28rem; /* max-w-md */
            background-color: #ffffff;
            padding: 2rem; /* p-8 */
            border-radius: 1rem; /* rounded-2xl */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); /* shadow-xl approximate */
        }

        /* Heading */
        .login-form h1 {
            margin-bottom: 2rem; /* mb-8 */
            text-align: center;
            font-size: 1.875rem; /* text-3xl */
            font-weight: 700; /* font-bold */
            color: #1F2937; /* gray-800 */
        }

        /* Container for Fields (handles spacing) */
        .form-fields-container > * + * {
            margin-top: 1.5rem; /* space-y-6 */
        }

        /* Input Group (Label + Input Wrapper) */
        .input-group > * + * {
            margin-top: 0.5rem; /* space-y-2 */
        }

        .input-label {
            display: block;
            font-size: 0.875rem; /* text-sm */
            font-weight: 500; /* font-medium */
            color: #4B5563; /* gray-700 */
        }

        /* Input Wrapper (styles the box around the input) */
        .input-wrapper {
            overflow: hidden;
            border-radius: 0.5rem; /* rounded-lg */
            border: 1px solid #E5E7EB; /* border-gray-200 */
            background-color: #ffffff;
            padding: 0.75rem 1rem; /* px-4 py-3 */
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out; /* For focus transition */
        }

        /* Input Wrapper Focus State */
        .input-wrapper:focus-within {
            border-color: #357AFF;
            /* ring-1 ring-[#357AFF] */
            box-shadow: 0 0 0 1px #357AFF;
        }

        /* Input Field */
        .input-field {
            width: 100%;
            background-color: transparent;
            font-size: 1.125rem; /* text-lg */
            border: none;
            outline: none;
            color: inherit; /* Inherit color from body or parent */
        }

        .input-field::placeholder {
            color: #9CA3AF; /* gray-400 approx */
        }

        /* Error Message */
        .error-message {
            background-color: #FEF2F2; /* red-50 */
            color: #EF4444; /* red-500 */
            padding: 0.75rem; /* p-3 */
            border-radius: 0.5rem; /* rounded-lg */
            font-size: 0.875rem; /* text-sm */
        }

        /* Utility to hide elements */
        .hidden {
            display: none;
        }

        /* Submit Button */
        .submit-button {
            width: 100%;
            border-radius: 0.5rem; /* rounded-lg */
            background-color: #357AFF;
            padding: 0.75rem 1rem; /* px-4 py-3 */
            font-size: 1rem; /* text-base */
            font-weight: 500; /* font-medium */
            color: #ffffff;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out; /* transition-colors */
            outline: none; /* Remove default outline */
        }

        .submit-button:hover {
            background-color: #2E69DE; /* hover:bg-[#2E69DE] */
        }

        .submit-button:focus {
            /* focus:ring-2 focus:ring-[#357AFF] focus:ring-offset-2 */
            box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px #357AFF;
        }

        .submit-button:disabled {
            opacity: 0.5; /* disabled:opacity-50 */
            cursor: not-allowed;
        }

        /* Loading state (optional static display) */
        /* .submit-button.loading .button-text { display: none; } */
        /* .submit-button.loading .loading-text { display: inline; } */

        /* Sign Up Link */
        .signup-link-container {
            text-align: center;
            font-size: 0.875rem; /* text-sm */
            color: #4B5563; /* gray-600 */
        }

        .signup-link {
            color: #357AFF;
            text-decoration: none;
            transition: color 0.2s ease-in-out;
        }

        .signup-link:hover {
            color: #2E69DE;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <form action="" method="POST" novalidate class="login-form">
            <h1>Welcome Back</h1>
            <h2>CI/CD Testing</h2>
            <div class="form-fields-container">
                <div class="input-group">
                    <label for="email" class="input-label">Email</label>
                    <div class="input-wrapper">
                        <input
                            required
                            id="email"
                            name="email"
                            type="email"
                            placeholder="Enter your email"
                            class="input-field"
                        />
                    </div>
                </div>

                <div class="input-group">
                    <label for="password" class="input-label">Password</label>
                    <div class="input-wrapper">
                        <input
                            required
                            id="password"
                            name="password"
                            type="password"
                            placeholder="Enter your password"
                            class="input-field"
                        />
                    </div>
                </div>

                <div id="error-message" class="error-message hidden">
                    </div>

                <button type="submit" class="submit-button">
                    Sign In
                </button>
            </div>
        </form>
    </div>
    </body>
</html>
