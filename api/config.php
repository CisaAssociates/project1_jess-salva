<?php
// config.php (or combined with helpers)

// --- Error Reporting (Development vs Production) ---
// For development:
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// For production, turn display_errors Off and log errors instead.

// 
// $host = 'sql211.thsite.top';
// $db = 'thsi_38752733_facial_recognition_system';
// $user = 'thsi_38752733';
// $pass = '!5Bf?U1!';
// 

// --- Database Configuration ---
// Use getenv for consistency or define directly. Best practice is to keep credentials outside code.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'facial_recognition_system');
define('DB_PORT', getenv('DB_PORT') ?: 3306);
define('DB_CHARSET', 'utf8mb4');

// --- Application Settings ---
define('DEVICE_ID', getenv('DEVICE_ID') ?: 'device001'); // Device ID for logging

// --- Basic Logging Function ---
function send_json_response(int $status_code, array $data): void {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
}

function log_message(string $level, string $message): void {
    $logFile = './application.log'; // Adjust path as needed
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$level}: {$message}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// --- Database Connection Function ---
function create_db_connection(): ?PDO {
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays by default
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        // log_message('DEBUG', 'DB Connection Successful'); // Optional
        return $pdo;
    } catch (PDOException $e) {
        log_message('ERROR', "Error connecting to MySQL database: " . $e->getMessage());
        return null;
    }
}