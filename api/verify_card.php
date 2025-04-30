<?php
// api/verify_card.php
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
$rfid_card_id = $jsonData['rfid_card_id'] ?? null;
$user_name = $jsonData['user_name'] ?? null;
if (empty($rfid_card_id) || empty($user_name)) {
    send_json_response(400, ["error" => "Missing 'rfid_card_id' or 'user_name'"]);
    exit;
}

try {
    $match = verify_rfid_card((string)$rfid_card_id, (string)$user_name);
    send_json_response(200, ["rfid_card_id" => $rfid_card_id, "user_name" => $user_name, "match" => $match]);
} catch (Throwable $e) {
    log_message('ERROR', "Error in verify_card.php: " . $e->getMessage());
    send_json_response(500, ["error" => "Internal server error"]);
}
?>