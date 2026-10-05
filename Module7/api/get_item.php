<?php
header("Content-Type: application/json");
require "../includes/db.php";

// Read-only lookup of one item, plus its winner if it has one.
//   GET api/get_item.php?section=12     (or ?item_id=1000)

function fail($httpCode, $message, $errorCode = null) {
    http_response_code($httpCode);
    echo json_encode(["success" => false, "error" => $message, "error_code" => $errorCode]);
    exit;
}

// Looked up by section (how people find items), or by the internal key when a link from
// the Search page carries it.
$section = trim((string) ($_GET['section'] ?? ''));
$itemId  = trim((string) ($_GET['item_id'] ?? ''));
if ($section === '' && ($itemId === '' || !ctype_digit($itemId) || (int) $itemId < 1)) {
    fail(400, "Enter a section");
}
if (mb_strlen($section) > 50) {
    fail(400, "Section is too long (50 characters max)");
}

try {
    $stmt = $pdo->prepare(
        "SELECT i.item_id, i.name, i.description, i.section, i.price, i.suggested_start_price,
                i.donor_id, i.status, i.winning_bid, i.paid,
                b.paddle_number, b.name AS winner_name
         FROM items i
         LEFT JOIN bidders b ON b.bidder_id = i.winner_id
         WHERE " . ($section !== '' ? "i.section = :key" : "i.item_id = :key")
    );
    $stmt->execute([':key' => ($section !== '' ? $section : (int) $itemId)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        fail(404, $section !== '' ? "No item has section " . $section : "Item not found", "ITEM_NOT_FOUND");
    }

    // Build the response explicitly so numbers/booleans have real types in JSON
    // (PDO hands back everything as strings, and the string "0" is truthy in JS).
    echo json_encode([
        "success" => true,
        "item" => [
            "item_id"       => (int) $row['item_id'],
            "name"          => $row['name'],
            "section"       => $row['section'],
            // Full record too, so Enter Items can load it for editing:
            "description"           => $row['description'],
            "price"                 => $row['price'],                  // decimal text like "12.50", or null
            "suggested_start_price" => $row['suggested_start_price'],
            "donor_id"              => $row['donor_id'] === null ? null : (int) $row['donor_id'],
            "status"        => $row['status'],
            "paid"          => ((int) $row['paid'] === 1),
            "winning_bid"   => $row['winning_bid'] === null ? null : number_format((float) $row['winning_bid'], 2, '.', ''),
            "paddle_number" => $row['paddle_number'] === null ? null : (int) $row['paddle_number'],
            "winner_name"   => $row['winner_name']
        ]
    ]);
} catch (PDOException $e) {
    fail(500, "Database error: " . $e->getMessage());
}
