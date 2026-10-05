<?php
header("Content-Type: application/json");
require "../includes/db.php";
require "../includes/money.php";

// Read-only. Feeds the projector display (winners-feed.html):
//   GET api/get_feed.php[?names=1]
// Returns the most recent winner, the winners before it (newest first), and the totals.
// Each winner is shown by section, paddle number and winning amount (no item name or description).
// Bidder names are left out unless names=1 is asked for, so they can't end up on a
// projector by accident.

const RECENT_LIMIT = 60;

try {
    $withNames = (($_GET['names'] ?? '') === '1');

    $totals = $pdo->query(
        "SELECT COUNT(*) AS sold, COALESCE(SUM(CAST(ROUND(winning_bid * 100) AS UNSIGNED)), 0) AS cents
         FROM items WHERE status = 'closed' AND winner_id IS NOT NULL"
    )->fetch(PDO::FETCH_ASSOC);

    // Newest first. Items closed before won_at existed (NULL) sort last, highest item number first.
    $stmt = $pdo->query(
        "SELECT i.item_id, i.section, i.won_at, b.paddle_number, b.name AS winner_name,
                CAST(ROUND(i.winning_bid * 100) AS UNSIGNED) AS bid_cents
         FROM items i JOIN bidders b ON b.bidder_id = i.winner_id
         WHERE i.status = 'closed'
         ORDER BY i.won_at IS NULL, i.won_at DESC, i.item_id DESC
         LIMIT " . (RECENT_LIMIT + 1)
    );

    $winners = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $w = [
            // Changes whenever this winner is entered or corrected, so the display knows to flash.
            "key"    => $r['item_id'] . '|' . $r['won_at'] . '|' . $r['bid_cents'] . '|' . $r['paddle_number'],
            "item_id" => (int) $r['item_id'],
            "section" => trim((string) $r['section']),
            "paddle" => $r['paddle_number'] === null ? '' : (string) $r['paddle_number'],
            "amount" => '$' . centsToString((int) $r['bid_cents']),
        ];
        if ($withNames) {
            $w["winner"] = $r['winner_name'];
        }
        $winners[] = $w;
    }

    echo json_encode([
        "success" => true,
        "sold"    => (int) $totals['sold'],
        "total"   => '$' . centsToString((int) $totals['cents']),
        "latest"  => $winners[0] ?? null,
        "recent"  => array_slice($winners, 1),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
}
