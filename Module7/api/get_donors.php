<?php
header("Content-Type: application/json");
require "../includes/db.php";

// Read-only lookup -- GET is fine here, nothing is being changed.
try {
    $stmt = $pdo->query("SELECT donor_id, name FROM donors ORDER BY name");
    $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "donors" => $donors]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
}
