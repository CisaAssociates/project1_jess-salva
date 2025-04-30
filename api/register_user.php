<?php
// Include database configuration
include("../db_config.php");

// Set response headers
header('Content-Type: application/json');

// Define response function to ensure consistent output
function sendResponse($success, $message = '', $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'user_id' => $data['user_id'] ?? null,
        'image_path' => $data['image_path'] ?? null,
        // Add any other data fields we want to return
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST requests are allowed');
}

// --- 1. Gather and validate input ---
$firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS);
$lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS);
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_SPECIAL_CHARS);
$password = $_POST['password'] ?? '';
$profileImage = $_POST['profile_image'] ?? null; // Get profile image data

// Validate inputs
$errors = [];
if (empty($firstName)) $errors[] = "First name is required.";
if (empty($lastName)) $errors[] = "Last name is required.";
if ($email === false) $errors[] = "Invalid email format.";
elseif (empty($email)) $errors[] = "Email is required.";
if (empty($role)) $errors[] = "Role is required.";
if (empty($password)) $errors[] = "Password is required.";
elseif (strlen($password) < 8) $errors[] = "Password must be at least 8 characters long.";

// Check image data if provided
$imagePath = null;
if (!empty($profileImage)) {
    // Validate image data format
    if (strpos($profileImage, 'data:image/') !== 0) {
        $errors[] = "Invalid image format.";
    }
}

// If validation fails, return error response
if (!empty($errors)) {
    sendResponse(false, implode(', ', $errors));
}

// --- 2. Connect to database ---
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    sendResponse(false, "Database connection failed. Please try again later.");
}

// --- 3. Check for email uniqueness ---
try {
    $stmtCheck = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmtCheck->execute([$email]);
    if ($stmtCheck->rowCount() > 0) {
        sendResponse(false, "The email address is already registered.");
    }
} catch (PDOException $e) {
    error_log("Email check failed: " . $e->getMessage());
    sendResponse(false, "Could not verify email uniqueness. Please try again.");
}

// --- 4. Insert user into database ---
try {
    // Start transaction to ensure both user and image record are saved together
    $pdo->beginTransaction();

    // Consider hashing the password in a production environment
    // $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    // For now, keeping it consistent with the original code

    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, role, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$firstName, $lastName, $email, $role, $password]);

    // Get the new user ID
    $userId = $pdo->lastInsertId();

    if (!$userId) {
        throw new PDOException("Failed to retrieve new user ID after insertion.");
    }

    // --- 5. Process and save image if provided ---
    if (!empty($profileImage)) {
        // Set up image directory
        $uploadDir = "../uploads/";

        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception("Failed to create upload directory.");
            }
        }

        // Extract image data
        list($type, $data) = explode(';', $profileImage);
        list(, $data) = explode(',', $data);
        $imageData = base64_decode($data);

        if ($imageData === false) {
            throw new Exception("Image data decoding failed.");
        }

        // Extract image extension
        list(, $extension) = explode('/', $type);
        $extension = strtolower(explode('+', $extension)[0]); // Get base extension (e.g., 'jpeg' from 'image/jpeg')

        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            throw new Exception("Invalid image type ($extension).");
        }

        // Generate unique filename
        $imageName = 'user_' . $userId . '_' . uniqid() . '.' . $extension;
        $targetFile = $uploadDir . $imageName;

        // Save image to server
        if (file_put_contents($targetFile, $imageData) === false) {
            throw new Exception("Failed to save image to server.");
        }

        // Store relative image path
        $imagePath = 'uploads/' . $imageName;

        // *** MODIFIED PART: Insert new record into faceencodings ***
        $stmtImage = $pdo->prepare("INSERT INTO faceencodings (user_id, face_image_path,created_at) VALUES (?, ?,NOW())");
        $stmtImage->execute([$userId, $imagePath]);
        // *** END OF MODIFIED PART ***

    }

    // Commit transaction
    $pdo->commit();

    // Log successful registration
    error_log("User registered successfully: ID $userId, Email: $email, Role: $role" .
              ($imagePath ? ", Image Path: $imagePath" : ""));

    // Return success response with user ID and image path
    sendResponse(true, "User registered successfully", [
        'user_id' => $userId,
        'image_path' => $imagePath // Will be null if no image was provided
    ]);

} catch (PDOException $e) {
    // Roll back transaction on database error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("User registration or face encoding insertion failed: " . $e->getMessage());

    // For debugging purposes, return the actual error message
    sendResponse(false, "Database error during registration: " . $e->getMessage());

} catch (Exception $e) {
    // Roll back transaction on general error (like file saving)
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Image processing or saving failed: " . $e->getMessage());
    sendResponse(false, "Failed to process profile image: " . $e->getMessage());
}
?>