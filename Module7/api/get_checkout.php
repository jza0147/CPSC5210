<?php
header("Content-Type: application/json");
require "../includes/db.php";
require "../includes/money.php";
require "../includes/sections.php";

// Read-only: what does this bidder (by paddle number) owe right now?
// Returns every item they won that has not been paid for yet, the subtotal,
// and what the credit card fee would be. Nothing is changed here.

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
    $stmt = $pdo->prepare("SELECT bidder_id, paddle_number, name FROM bidders WHERE paddle_number = :p");
    $stmt->execute([':p' => (int) $paddle]);
    $bidder = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bidder) {
        fail(404, "No bidder has paddle " . (int) $paddle, "BIDDER_NOT_FOUND");
    }

    // Unpaid items this bidder won. Cents are computed in SQL from the exact
    // DECIMAL column, so there is no float math anywhere.
    $stmt = $pdo->prepare(
        "SELECT i.item_id, i.name, i.section, CAST(ROUND(i.winning_bid * 100) AS UNSIGNED) AS cents
         FROM items i
         WHERE i.winner_id = :b AND i.status = 'closed' AND i.paid = 0
         ORDER BY " . sectionOrder('i')
    );
    $stmt->execute([':b' => $bidder['bidder_id']]);

    $items = [];
    $subtotal = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cents = (int) $row['cents'];
        $subtotal += $cents;
        $items[] = [
            "item_id"     => (int) $row['item_id'],
            "section"     => sectionText($row['section']),
            "name"        => $row['name'],
            "winning_bid" => centsToString($cents)
        ];
    }

    // How many items this bidder has already paid for (so the page can say
    // "all paid" instead of "never won anything").
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE winner_id = :b AND paid = 1");
    $stmt->execute([':b' => $bidder['bidder_id']]);
    $paidCount = (int) $stmt->fetchColumn();

    echo json_encode([
        "success"         => true,
        "bidder"          => [
            "paddle_number" => (int) $bidder['paddle_number'],
            "name"          => $bidder['name']
        ],
        "items"           => $items,
        "subtotal_cents"  => $subtotal,
        "card_fee_cents"  => cardFeeCents($subtotal),
        "paid_item_count" => $paidCount
    ]);
} catch (PDOException $e) {
    fail(500, "Database error: " . $e->getMessage());
}
