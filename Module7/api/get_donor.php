<?php
header("Content-Type: application/json");
require "../includes/db.php";

// Read-only: one donor's full record, so Enter Donors can load it for editing
// (donors.php?donor_id=5000).

function fail($httpCode, $message, $errorCode = null) {
    http_response_code($httpCode);
    echo json_encode(["success" => false, "error" => $message, "error_code" => $errorCode]);
    exit;
}

$donorId = trim((string) ($_GET['donor_id'] ?? ''));
if ($donorId === '' || !ctype_digit($donorId) || (int) $donorId < 1) {
    fail(400, "Donor number must be a whole number");
}

try {
    $stmt = $pdo->prepare("SELECT donor_id, name, address, phone FROM donors WHERE donor_id = :id");
    $stmt->execute([':id' => (int) $donorId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        fail(404, "Donor " . (int) $donorId . " not found", "DONOR_NOT_FOUND");
    }

    echo json_encode([
        "success" => true,
        "donor" => [
            "donor_id" => (int) $row['donor_id'],
            "name"     => $row['name'],
            "address"  => $row['address'],
            "phone"    => $row['phone']
        ]
    ]);
} catch (PDOException $e) {
    fail(500, "Database error: " . $e->getMessage());
}
