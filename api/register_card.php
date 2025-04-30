<?php
// api/register_card.php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_operations.php';

// Check HTTP Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(405, ['error' => 'Method Not Allowed']);
    exit;
}

// --- Get JSON Input ---
$jsonData = null;
if (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    if (!empty($rawBody)) {
        $jsonData = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            send_json_response(400, ['error' => 'Invalid JSON: ' . json_last_error_msg()]); exit;
        }
    }
}
if ($jsonData === null) {
    send_json_response(400, ["error" => "Request body must be JSON and not empty"]); exit;
}
// --- End JSON Input ---

// Validate input
$card_id = $jsonData['card_id'] ?? null;
$user_id = $jsonData['user_id'] ?? null;
if (empty($card_id) || $user_id === null) {
    send_json_response(400, ["error" => "Missing 'card_id' or 'user_id'"]); exit;
}
if (!is_int($user_id) && !(is_string($user_id) && ctype_digit($user_id))) {
     send_json_response(400, ["error" => "'user_id' must be an integer"]); exit;
}
$user_id = (int)$user_id;
if ($user_id <= 0) { send_json_response(400, ["error"=>"'user_id' must be positive"]); exit; }


try {
    list($success, $message) = call_register_rfid_procedure((string)$card_id, $user_id);
    if ($success) {
        send_json_response(200, ["success" => true, "message" => $message]);
    } else {
        $statusCode = (strpos($message, 'already active') !== false || strpos($message, 'different user') !== false) ? 409 : (strpos($message, 'Missing') !== false ? 400 : 500);
        send_json_response($statusCode, ["success" => false, "error" => $message]);
    }
} catch (Throwable $e) {
    log_message('ERROR', "Error in register_card.php: " . $e->getMessage());
    send_json_response(500, ["success" => false, "error" => "Internal server error"]);
}
?>