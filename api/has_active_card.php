<?php
// api/has_active_card.php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_operations.php';

// Check HTTP Method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(405, ['error' => 'Method Not Allowed']);
    exit;
}

// Get input
$user_id_str = $_GET['user_id'] ?? null;
if (empty($user_id_str) || !ctype_digit($user_id_str)) {
    send_json_response(400, ["error" => "Missing or invalid 'user_id' query parameter (must be integer)"]);
    exit;
}
$user_id = (int)$user_id_str;

try {
    $has_card = check_user_has_active_card($user_id);
    send_json_response(200, ["user_id" => $user_id, "has_active_card" => $has_card]);
} catch (Throwable $e) {
    log_message('ERROR', "Error in has_active_card.php: " . $e->getMessage());
    send_json_response(500, ["error" => "An internal server error occurred"]);
}
?>