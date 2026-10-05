<?php
header("Content-Type: application/json");
require "../includes/db.php";

function fail($httpCode, $message, $errorCode = null) {
    global $pdo;
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code($httpCode);
    echo json_encode(["success" => false, "error" => $message, "error_code" => $errorCode]);
    exit;
}

// Writes only -- a GET must never be able to close an item.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, "Method not allowed");
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    fail(400, "Invalid request body");
}

$itemId    = trim((string) ($input['item_id'] ?? ''));
$paddle    = trim((string) ($input['paddle_number'] ?? ''));
$bid       = trim((string) ($input['winning_bid'] ?? ''));
// true only when the clerk is correcting a winner this page just entered
// (or deliberately clicked "Change Winner"). Never assumed.
$overwrite = ($input['overwrite'] ?? false) === true;

// --- Validate ---
if ($itemId === '' || !ctype_digit($itemId) || (int) $itemId < 1) {
    fail(400, "Enter a section first");
}
if ($paddle === '' || !ctype_digit($paddle) || (int) $paddle < 1 || (int) $paddle > 999) {
    fail(400, "Paddle number must be a whole number from 1 to 999");
}
// Dollars and cents only, up to 8 digits before the decimal (matches DECIMAL(10,2)).
if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $bid) || (float) $bid <= 0) {
    fail(400, "Winning bid must be greater than 0, with at most 2 decimal places");
}
$itemId = (int) $itemId;
$paddle = (int) $paddle;

try {
    $pdo->beginTransaction();

    // Lock this item's row until we commit. If two clerks try to award the same
    // item at once, the second one waits here, then sees it is already closed.
    $stmt = $pdo->prepare("SELECT status, paid, section FROM items WHERE item_id = :id FOR UPDATE");
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        fail(404, "That item no longer exists", "ITEM_NOT_FOUND");
    }
    $label = ($item['section'] !== null) ? "Section " . $item['section'] : "This item";

    // The client sends a paddle number; we work out the bidder ourselves
    // rather than trusting a bidder_id from the browser.
    $stmt = $pdo->prepare("SELECT bidder_id, name FROM bidders WHERE paddle_number = :p");
    $stmt->execute([':p' => $paddle]);
    $bidder = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bidder) {
        fail(404, "No bidder has paddle " . $paddle, "BIDDER_NOT_FOUND");
    }

    if (!$overwrite && $item['status'] !== 'open') {
        fail(409, $label . " is already closed", "ALREADY_CLOSED");
    }
    if ((int) $item['paid'] === 1) {
        fail(409, $label . " has already been paid for, so its winner can't be changed", "ALREADY_PAID");
    }

    // Entering the winner is what closes the item.
    $stmt = $pdo->prepare(
        "UPDATE items
         SET winner_id = :bidder, winning_bid = :bid, status = 'closed', won_at = NOW(3)
         WHERE item_id = :id"
    );
    $stmt->execute([':bidder' => $bidder['bidder_id'], ':bid' => $bid, ':id' => $itemId]);

    // This bidder now owes money for an unpaid item, so their account is open
    // again even if they had already checked out once.
    $stmt = $pdo->prepare("UPDATE bidders SET paid = 0 WHERE bidder_id = :bidder");
    $stmt->execute([':bidder' => $bidder['bidder_id']]);

    $pdo->commit();

    echo json_encode([
        "success"       => true,
        "item_id"       => $itemId,
        "section"       => $item['section'],
        "paddle_number" => $paddle,
        "bidder_name"   => $bidder['name'],
        "winning_bid"   => number_format((float) $bid, 2, '.', '')
    ]);
} catch (PDOException $e) {
    fail(500, "Database error: " . $e->getMessage());
}
