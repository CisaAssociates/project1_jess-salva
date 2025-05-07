<?php
header('Content-Type: application/json'); // Indicate that the response is JSON

include '../db_config.php';

// Get the raw POST data from the request body
$json_data = file_get_contents('php://input');
// Decode the JSON data into a PHP associative array
$data = json_decode($json_data, true);

// Check if JSON decoding was successful
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(["error" => "Invalid JSON received.", "json_error" => json_last_error_msg()]);
    exit();
}

// --- Input Validation ---
// Define the fields that are REQUIRED in the incoming JSON payload
$required_fields = ['user_id', 'log_type', 'device_id', 'face_recognized', 'access_granted'];
foreach ($required_fields as $field) {
    // Check if the required field is NOT set or is null
    if (!isset($data[$field]) || $data[$field] === null) {
        http_response_code(400); // Bad Request
        echo json_encode(["error" => "Missing or null required field: " . $field]);
        exit();
    }
}

// Sanitize and cast input data to appropriate types
// Use htmlspecialchars to prevent XSS attacks if this data were ever displayed on a webpage
$user_id = (int)$data['user_id']; // Ensure user_id is an integer
$log_type = $data['log_type']; // Will validate against ENUM values next
$device_id = htmlspecialchars($data['device_id']); // Sanitize device ID
$face_recognized = (bool)$data['face_recognized']; // Ensure boolean (will be 0 or 1 in MySQL)
$access_granted = (bool)$data['access_granted']; // Ensure boolean (will be 0 or 1 in MySQL)

// Optional fields - check existence and sanitize/cast, default to null if not provided
$rfid_card_id_used = isset($data['rfid_card_id_used']) ? htmlspecialchars($data['rfid_card_id_used']) : null;
$confidence_score = isset($data['confidence_score']) ? (float)$data['confidence_score'] : null; // Ensure float
$location_context = isset($data['location_context']) ? htmlspecialchars($data['location_context']) : null; // Sanitize location context


// Validate log_type against the ENUM values ('in', 'out') defined in the database schema
if (!in_array($log_type, ['in', 'out'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["error" => "Invalid log_type. Must be 'in' or 'out'."]);
    exit();
}

// This API endpoint is specifically for logging *successful* attendance events.
// The Python client should only call this if access was granted.
// We add this check here as a server-side safety measure.
if (!$access_granted) {
    http_response_code(400); // Bad Request
    echo json_encode(["error" => "Attendance log requires access_granted to be true."]);
    exit();
}


// --- Database Connection ---
$conn = new mysqli($host, $user, $pass, $db);

// Check the database connection
if ($conn->connect_error) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["error" => "Database Connection failed: " . $conn->connect_error]);
    exit();
}

// --- Prepare and Execute SQL Statement ---
// Use prepared statements to prevent SQL injection vulnerabilities.
// The table name is `attendance_logs`.
$sql = "INSERT INTO attendance_logs (user_id, log_type, device_id, location_context, face_recognized, rfid_card_id_used, access_granted, confidence_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

// Prepare the SQL statement
$stmt = $conn->prepare($sql);

// Check if the prepare statement was successful
if ($stmt === false) {
     http_response_code(500); // Internal Server Error
     echo json_encode(["error" => "Database Prepare failed: (" . $conn->errno . ") " . $conn->error]);
     $conn->close(); // Close the database connection
     exit();
}

// Bind parameters to the prepared statement
// The types correspond to the placeholders (?): i=integer, s=string, d=double (float)
// The order must match the columns in the INSERT statement.
$stmt->bind_param("isssidsd",
    $user_id,
    $log_type,
    $device_id,
    $location_context,
    $face_recognized, // PHP boolean is converted to 0 or 1 for MySQL TINYINT/BOOLEAN
    $rfid_card_id_used,
    $access_granted,  // PHP boolean is converted to 0 or 1
    $confidence_score
);

// Execute the prepared statement
if ($stmt->execute()) {
    // Check if any rows were affected (a successful insert should affect 1 row)
    if ($stmt->affected_rows > 0) {
        http_response_code(201); // 201 Created is the standard response for a successful POST that creates a resource
        // Return a success message and the ID of the newly inserted row
        echo json_encode(["success" => true, "message" => "Attendance log recorded successfully.", "log_id" => $conn->insert_id]);
    } else {
        // This case is unlikely if execute() returns true, but indicates a problem
        http_response_code(500); // Internal Server Error
        echo json_encode(["success" => false, "error" => "Attendance log insertion failed (no rows affected)."]);
    }
} else {
    // If execute fails, return the database error
    http_response_code(500); // Internal Server Error
    echo json_encode(["success" => false, "error" => "Database Execute failed: (" . $stmt->errno . ") " . $stmt->error]);
}

// Close the prepared statement and the database connection
$stmt->close();
$conn->close();

?>
