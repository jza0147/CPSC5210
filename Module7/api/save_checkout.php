<?php
header("Content-Type: application/json");
require "../includes/db.php";
require "../includes/money.php";

function fail($httpCode, $message, $errorCode = null) {
    global $pdo;
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code($httpCode);
    echo json_encode(["success" => false, "error" => $message, "error_code" => $errorCode]);
    exit;
}

// Writes only -- a GET must never be able to record a payment.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, "Method not allowed");
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    fail(400, "Invalid request body");
}

$paddle      = trim((string) ($input['paddle_number'] ?? ''));
$method      = trim((string) ($input['method'] ?? ''));
$checkNumber = trim((string) ($input['check_number'] ?? ''));
$expected    = trim((string) ($input['expected_amount'] ?? ''));

// --- Validate ---
if ($paddle === '' || !ctype_digit($paddle) || (int) $paddle < 1 || (int) $paddle > 999) {
    fail(400, "Paddle number must be a whole number from 1 to 999");
}
if (!in_array($method, ['cash', 'check', 'card'], true)) {
    fail(400, "Choose a payment method");
}
if ($method === 'check') {
    if ($checkNumber === '') {
        fail(400, "Enter the check number");
    }
    if (mb_strlen($checkNumber) > 30) {
        fail(400, "Check number is too long (30 characters max)");
    }
} else {
    $checkNumber = null;   // only checks have a check number
}
$expectedCents = stringToCents($expected);
if ($expectedCents === null) {
    fail(400, "Invalid amount");
}
$paddle = (int) $paddle;

try {
    $pdo->beginTransaction();

    // Lock the bidder first. If two cashiers check out the same bidder at once,
    // the second waits here, then finds nothing left to pay.
    $stmt = $pdo->prepare("SELECT bidder_id, name FROM bidders WHERE paddle_number = :p FOR UPDATE");
    $stmt->execute([':p' => $paddle]);
    $bidder = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bidder) {
        fail(404, "No bidder has paddle " . $paddle, "BIDDER_NOT_FOUND");
    }

    // Work out what is owed from the database, right now. We never trust an
    // amount sent from the browser -- it is only used below to detect that the
    // total changed after the cashier looked at it.
    $stmt = $pdo->prepare(
        "SELECT item_id, CAST(ROUND(winning_bid * 100) AS UNSIGNED) AS cents
         FROM items
         WHERE winner_id = :b AND status = 'closed' AND paid = 0
         FOR UPDATE"
    );
    $stmt->execute([':b' => $bidder['bidder_id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) === 0) {
        fail(409, "Nothing is owed by paddle " . $paddle . " (already paid, or no items won)", "NOTHING_OWED");
    }

    $itemIds  = [];
    $subtotal = 0;
    foreach ($rows as $row) {
        $itemIds[] = (int) $row['item_id'];
        $subtotal += (int) $row['cents'];
    }
    $fee   = ($method === 'card') ? cardFeeCents($subtotal) : 0;
    $total = $subtotal + $fee;

    if ($total !== $expectedCents) {
        fail(
            409,
            "The amount owed has changed (now $" . centsToString($total) . "). Please review and try again.",
            "AMOUNT_CHANGED"
        );
    }

    // 1. Record the payment.
    $stmt = $pdo->prepare(
        "INSERT INTO payments (bidder_id, method, check_number, amount, card_fee)
         VALUES (:bidder, :method, :check, :amount, :fee)"
    );
    $stmt->execute([
        ':bidder' => $bidder['bidder_id'],
        ':method' => $method,
        ':check'  => $checkNumber,
        ':amount' => centsToString($total),
        ':fee'    => centsToString($fee)
    ]);
    $paymentId = (int) $pdo->lastInsertId();

    // 2. Mark exactly the items we just totaled as paid (by id, not by "all of
    //    this bidder's unpaid items", so an item awarded a moment ago that we
    //    did not charge for can never be marked paid by mistake).
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $stmt = $pdo->prepare("UPDATE items SET paid = 1 WHERE item_id IN ($placeholders)");
    $stmt->execute($itemIds);

    // 3. Close the bidder's account.
    $stmt = $pdo->prepare("UPDATE bidders SET paid = 1 WHERE bidder_id = :b");
    $stmt->execute([':b' => $bidder['bidder_id']]);

    $pdo->commit();

    echo json_encode([
        "success"       => true,
        "payment_id"    => $paymentId,
        "paddle_number" => $paddle,
        "bidder_name"   => $bidder['name'],
        "method"        => $method,
        "item_count"    => count($itemIds),
        "subtotal"      => centsToString($subtotal),
        "card_fee"      => centsToString($fee),
        "total"         => centsToString($total)
    ]);
} catch (PDOException $e) {
    fail(500, "Database error: " . $e->getMessage());
}
