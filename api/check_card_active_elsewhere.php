<?php
// api/check_card_active.php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_operations.php';

// Check HTTP Method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(405, ['error' => 'Method Not Allowed']);
    exit;
}

// Get input
$card_id = $_GET['card_id'] ?? null;
$current_user_id_str = $_GET['current_user_id'] ?? null;

if (empty($card_id) || empty($current_user_id_str)) {
    send_json_response(400, ["error" => "Missing 'card_id' or 'current_user_id' query parameter"]);
    exit;
}
if (!ctype_digit($current_user_id_str)) {
     send_json_response(400, ["error" => "'current_user_id' must be an integer"]);
     exit;
}
$current_user_id = (int)$current_user_id_str;

try {
    list($active_elsewhere, $owner_info) = check_card_is_active_elsewhere((string)$card_id, $current_user_id);
    $response_data = [
        "card_id" => $card_id,
        "checked_against_user_id" => $current_user_id,
        "is_active_elsewhere" => $active_elsewhere
    ];
    if ($owner_info) {
        $response_data["assigned_to"] = $owner_info;
    }
    send_json_response(200, $response_data);

} catch (Throwable $e) {
    log_message('ERROR', "Error in check_card_active.php: " . $e->getMessage());
    send_json_response(500, ["error" => "An internal server error occurred"]);
}
?>