<?php
// api/get_user_id.php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_operations.php';

// Check HTTP Method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(405, ['error' => 'Method Not Allowed']);
    exit;
}

// Get input
$user_name = $_GET['name'] ?? null;
if (empty($user_name)) {
    send_json_response(400, ["error" => "Missing 'name' query parameter"]);
    exit;
}

try {
    $user_id = get_user_id((string)$user_name);
    if ($user_id !== null) {
        send_json_response(200, ["user_id" => $user_id]);
    } else {
        // Use htmlspecialchars to prevent XSS if echoing user input in error message
        send_json_response(404, ["error" => "Active user '" . htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') . "' not found"]);
    }
} catch (Throwable $e) {
    log_message('ERROR', "Error in get_user_id.php: " . $e->getMessage());
    send_json_response(500, ["error" => "An internal server error occurred"]);
}
?>