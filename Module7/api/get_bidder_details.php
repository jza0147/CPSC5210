<?php
header("Content-Type: application/json");
require "../includes/db.php";

// Read-only: one bidder's full record by bidder_id, so Enter Bidders can load
// it for editing (bidders.php?bidder_id=9000).
// (get_bidder.php is different on purpose: it looks up by paddle number and
// returns only the name, for Enter Winners.)

function fail($httpCode, $message, $errorCode = null) {
    http_response_code($httpCode);
    echo json_encode(["success" => false, "error" => $message, "error_code" => $errorCode]);
    exit;
}

$bidderId = trim((string) ($_GET['bidder_id'] ?? ''));
if ($bidderId === '' || !ctype_digit($bidderId) || (int) $bidderId < 1) {
    fail(400, "Bidder id must be a whole number");
}

try {
    $stmt = $pdo->prepare(
        "SELECT bidder_id, paddle_number, name, address, phone FROM bidders WHERE bidder_id = :id"
    );
    $stmt->execute([':id' => (int) $bidderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        fail(404, "Bidder " . (int) $bidderId . " not found", "BIDDER_NOT_FOUND");
    }

    echo json_encode([
        "success" => true,
        "bidder" => [
            "bidder_id"     => (int) $row['bidder_id'],
            "paddle_number" => $row['paddle_number'] === null ? null : (int) $row['paddle_number'],
            "name"          => $row['name'],
            "address"       => $row['address'],
            "phone"         => $row['phone']
        ]
    ]);
} catch (PDOException $e) {
    fail(500, "Database error: " . $e->getMessage());
}
