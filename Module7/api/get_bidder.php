<?php
header("Content-Type: application/json");
require "../includes/db.php";

// Read-only lookup of a bidder by paddle number. Returns only what the
// clerk needs to confirm who it is (name), not address or phone.
// Enter Winners uses this now; Checkout can reuse it later.

function fail($httpCode, $message, $errorCode = null) {
    http_response_code($httpCode);
    echo json_encode(["success" => false, "error" => $message, "error_code" => $errorCode]);
    exit;
}

$paddle = trim((string) ($_GET['paddle_number'] ?? ''));
if ($paddle === '' || !ctype_digit($paddle) || (int) $paddle < 1 || (int) $paddle > 999) {
    fail(400, "Paddle number must be a whole number from 1 to 999");
}

try {
    $stmt = $pdo->prepare(
        "SELECT bidder_id, paddle_number, name FROM bidders WHERE paddle_number = :p"
    );
    $stmt->execute([':p' => (int) $paddle]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        fail(404, "No bidder has paddle " . (int) $paddle, "BIDDER_NOT_FOUND");
    }

    echo json_encode([
        "success" => true,
        "bidder" => [
            "bidder_id"     => (int) $row['bidder_id'],
            "paddle_number" => (int) $row['paddle_number'],
            "name"          => $row['name']
        ]
    ]);
} catch (PDOException $e) {
    fail(500, "Database error: " . $e->getMessage());
}
