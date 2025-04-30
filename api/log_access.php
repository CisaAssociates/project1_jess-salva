<?php
// api/log_access.php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_operations.php';

// Check HTTP Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => 'Method Not Allowed']);
    exit;
}

// --- Get JSON Input --- (Same block as register_card.php)
$jsonData = null;
if (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    if (!empty($rawBody)) {
        $jsonData = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            send_json_response(400, ['error'=>'Invalid JSON: ' . json_last_error_msg()]); exit;
        }
    }
}
if ($jsonData === null) { send_json_response(400, ["error" => "Request body must be JSON and not empty"]); exit; }
// --- End JSON Input ---

// Validate input
$user_name = $jsonData['user_name'] ?? null;
$face_recognized = $jsonData['face_recognized'] ?? null;
$rfid_card_id_used = $jsonData['rfid_card_id_used'] ?? null;
$access_granted = $jsonData['access_granted'] ?? null;
$confidence_score = $jsonData['confidence_score'] ?? null;

if ($user_name === null) { send_json_response(400, ["error" => "Missing 'user_name'"]); exit; }
if ($face_recognized === null || !is_bool($face_recognized)) { send_json_response(400, ["error" => "Missing/invalid 'face_recognized' (boolean)"]); exit; }
if ($access_granted === null || !is_bool($access_granted)) { send_json_response(400, ["error" => "Missing/invalid 'access_granted' (boolean)"]); exit; }
if ($confidence_score !== null && !is_numeric($confidence_score)) { send_json_response(400, ["error" => "Invalid 'confidence_score' (numeric or null)"]); exit; }

try {
    list($success, $message) = log_access_attempt(
        (string)$user_name,
        (bool)$face_recognized,
        $rfid_card_id_used === null ? null : (string)$rfid_card_id_used,
        (bool)$access_granted,
        $confidence_score === null ? null : (float)$confidence_score
    );

    if ($success) {
        send_json_response(201, ["success" => true, "message" => $message]); // 201 Created
    } else {
        send_json_response(500, ["success" => false, "error" => $message]); // Assume server error if logging fails after validation
    }
} catch (Throwable $e) {
    log_message('ERROR', "Error in log_access.php: " . $e->getMessage());
    send_json_response(500, ["success" => false, "error" => "Internal server error"]);
}
?>