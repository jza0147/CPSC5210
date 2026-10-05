<?php
// One shared PDO connection, included by every page and every api/ endpoint.

$host = '127.0.0.1'
$db   = 'auction'
$port = '3306'; 
$user = 'root' 
$pass = '' 
try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // In a real deployment you would NOT echo the raw error to the browser.
    // For local testing this is fine and helps you debug connection issues.
    $message = "Database connection failed: " . $e->getMessage();

    // The pages in api/ are called by JavaScript, which expects JSON, so answer in JSON there.
    // That way the page can show the real reason instead of a vague "check your connection".
    if (strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/') !== false) {
        http_response_code(500);
        header("Content-Type: application/json");
        die(json_encode(["success" => false, "error" => $message, "error_code" => "DB_CONNECT"]));
    }
    die($message);
}
