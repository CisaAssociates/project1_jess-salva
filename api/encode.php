<?php
// Include database configuration variables ($host, $db, $user, $pass)
include "../db_config.php";
/**
 * API Endpoint to receive face encoding data and update ONLY the encoding
 * in the database for a given user_id.
 *
 * Accepts POST requests with a JSON body containing:
 * - user_id (int): The ID of the user whose record should be updated.
 * - face_encoding_base64 (string): Base64 encoded face encoding data.
 * - image_path (string, optional): Path to the original image file (received but NOT used for update).
 */

// --- Database Configuration ---
// Using variables from db_config.php included above
define('DB_HOST', $host);
define('DB_NAME', $db);
define('DB_USER', $user);
define('DB_PASS', $pass); // Assumes $pass is defined in db_config.php
define('DB_CHARSET', 'utf8mb4');

// --- Response Helper Function ---
/**
 * Sends a JSON response and terminates the script.
 *
 * @param int $statusCode HTTP status code.
 * @param array $data Data to be encoded as JSON.
 */
function send_json_response(int $statusCode, array $data): void {
    header_remove(); // Remove previous headers
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit(); // Terminate script execution
}

// --- Main Logic ---

// 1. Check Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => 'Method Not Allowed', 'message' => 'Only POST requests are accepted.']);
}

// 2. Read and Decode JSON Input
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true); // Decode as associative array

// Check if JSON decoding was successful and data is an array
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    send_json_response(400, ['error' => 'Bad Request', 'message' => 'Invalid JSON payload received.', 'json_error' => json_last_error_msg()]);
}

// 3. Validate Input Data
$user_id = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT);
$face_encoding_base64 = $data['face_encoding_base64'] ?? null;
// We still read image_path if provided, but it's not required or used for the update
$image_path = $data['image_path'] ?? null;

if ($user_id === false || $user_id <= 0) {
    send_json_response(400, ['error' => 'Bad Request', 'message' => 'Missing or invalid user_id (must be a positive integer).']);
}
if (empty($face_encoding_base64) || !is_string($face_encoding_base64)) {
    send_json_response(400, ['error' => 'Bad Request', 'message' => 'Missing or invalid face_encoding_base64 (must be a non-empty string).']);
}
// ** REMOVED validation requirement for image_path **
// if (empty($image_path) || !is_string($image_path)) {
//      send_json_response(400, ['error' => 'Bad Request', 'message' => 'Missing or invalid image_path (must be a non-empty string).']);
// }

// 4. Decode Base64 Encoding
$face_encoding_binary = base64_decode($face_encoding_base64, true); // Use strict mode

if ($face_encoding_binary === false) {
    send_json_response(400, ['error' => 'Bad Request', 'message' => 'Invalid base64 encoding for face_encoding_base64.']);
}

// 5. Database Connection (PDO)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Log the detailed error internally if possible, but don't expose details to the client
    error_log("Database Connection Error: " . $e->getMessage()); // Log error
    send_json_response(500, ['error' => 'Internal Server Error', 'message' => 'Database connection failed.']);
}

// 6. Prepare and Execute SQL Update
// Ensure your table name and column names match exactly.
// `face_encoding` column should be of type BLOB or LONGBLOB.
// ** MODIFIED: Only update face_encoding, not face_image_path **
$sql = "UPDATE faceencodings
        SET face_encoding = :face_encoding
        WHERE user_id = :user_id";

try {
    $stmt = $pdo->prepare($sql);

    // Bind parameters
    // ** MODIFIED: Removed binding for image_path **
    $stmt->bindParam(':face_encoding', $face_encoding_binary, PDO::PARAM_LOB); // Bind as Large Object (BLOB)
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

    // Execute the statement
    $success = $stmt->execute();

    if ($success) {
        $affectedRows = $stmt->rowCount(); // Check how many rows were updated

        if ($affectedRows > 0) {
            // Successfully updated one or more rows (should typically be 1 if user_id is unique constraint)
            send_json_response(200, [ // 200 OK status code for successful update
                'status' => 'success',
                'message' => 'Face encoding updated successfully.',
                'user_id' => $user_id,
                'affected_rows' => $affectedRows
            ]);
        } else {
            // The statement executed successfully, but no rows were affected.
            // This usually means the user_id was not found in the table.
            send_json_response(404, [ // 404 Not Found
                'error' => 'Not Found',
                'message' => 'No record found for the provided user_id to update.',
                'user_id' => $user_id
            ]);
        }
    } else {
        // This part might not be reached if PDO throws an exception on failure,
        // but included for completeness.
        send_json_response(500, ['error' => 'Internal Server Error', 'message' => 'Failed to execute database update statement.']);
    }

} catch (\PDOException $e) {
    // Log the detailed error internally
    error_log("Database Update Error: " . $e->getMessage() . " | SQL: " . $sql); // Log error

    send_json_response(500, ['error' => 'Internal Server Error', 'message' => 'Failed to update face encoding in the database.', 'details' => $e->getMessage()]); // Avoid exposing too much detail in production
}
?>