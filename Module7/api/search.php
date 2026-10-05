<?php
header("Content-Type: application/json");
require "../includes/db.php";
require "../includes/money.php";
require "../includes/sections.php";

// Read-only search for the Search page.
//   GET api/search.php?type=items|winners|bidders|donors&q=some+text
// An empty q lists everything (first 100). Each result row comes back already
// shaped for display, with the link to open that record for editing.

const SEARCH_LIMIT = 100;

function fail($httpCode, $message) {
    http_response_code($httpCode);
    echo json_encode(["success" => false, "error" => $message]);
    exit;
}

$type = (string) ($_GET['type'] ?? '');
$q    = trim((string) ($_GET['q'] ?? ''));

if (!in_array($type, ['items', 'winners', 'bidders', 'donors'], true)) {
    fail(400, "Unknown search type");
}
if (mb_strlen($q) > 100) {
    fail(400, "Search text is too long (100 characters max)");
}

// A typed % or _ should be searched for literally, not act as a wildcard.
$like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
// All digits? Then it can also be an exact item / paddle / id number.
$num  = ctype_digit($q) ? $q : null;

// Builds "(col LIKE ? OR col LIKE ? OR numcol = ?)". Column names come from the
// lists below (never from the user); the search text is only ever a bound value.
function matchClause(array $textCols, array $numCols, $like, $num, array &$params)
{
    $parts = [];
    foreach ($textCols as $i => $col) {
        $parts[] = "$col LIKE :t$i ESCAPE '!'";
        $params[":t$i"] = $like;
    }
    if ($num !== null) {
        foreach ($numCols as $i => $col) {
            $parts[] = "$col = :n$i";
            $params[":n$i"] = $num;
        }
    }
    return '(' . implode(' OR ', $parts) . ')';
}

function money($cents)
{
    return $cents === null ? '' : '$' . centsToString((int) $cents);
}

try {
    $params = [];
    $where  = [];

    switch ($type) {
        case 'items':
            if ($q !== '') {
                $where[] = matchClause(['i.section', 'i.name', 'i.description', 'd.name'], [], $like, $num, $params);
            }
            $sql = "SELECT i.item_id, i.name, i.section, i.status, i.paid,
                           d.name AS donor_name, b.paddle_number, b.name AS winner_name,
                           CAST(ROUND(i.winning_bid * 100) AS UNSIGNED) AS bid_cents
                    FROM items i
                    LEFT JOIN donors d  ON d.donor_id = i.donor_id
                    LEFT JOIN bidders b ON b.bidder_id = i.winner_id";
            $order = "ORDER BY " . sectionOrder('i');
            $columns = ["Section", "Name", "Donor", "Status", "Winner", "Winning Bid", "Paid"];
            break;

        case 'winners':
            $where[] = "i.status = 'closed'";
            if ($q !== '') {
                $where[] = matchClause(['i.section', 'i.name', 'b.name'], ['b.paddle_number'], $like, $num, $params);
            }
            $sql = "SELECT i.item_id, i.name, i.section, i.paid, b.paddle_number, b.name AS winner_name,
                           CAST(ROUND(i.winning_bid * 100) AS UNSIGNED) AS bid_cents
                    FROM items i
                    LEFT JOIN bidders b ON b.bidder_id = i.winner_id";
            $order = "ORDER BY " . sectionOrder('i');
            $columns = ["Section", "Item", "Winner", "Winning Bid", "Paid"];
            break;

        case 'bidders':
            if ($q !== '') {
                $where[] = matchClause(['b.name', 'b.phone', 'b.address'], ['b.paddle_number', 'b.bidder_id'], $like, $num, $params);
            }
            $sql = "SELECT b.bidder_id, b.paddle_number, b.name, b.phone, b.address,
                           CAST(ROUND(COALESCE((SELECT SUM(i.winning_bid) FROM items i
                                                WHERE i.winner_id = b.bidder_id AND i.status = 'closed' AND i.paid = 0), 0) * 100) AS UNSIGNED) AS owed_cents
                    FROM bidders b";
            $order = "ORDER BY b.paddle_number IS NULL, b.paddle_number, b.bidder_id";
            $columns = ["Paddle", "Name", "Phone", "Address", "Owes"];
            break;

        case 'donors':
            if ($q !== '') {
                $where[] = matchClause(['d.name', 'd.phone', 'd.address'], ['d.donor_id'], $like, $num, $params);
            }
            $sql = "SELECT d.donor_id, d.name, d.phone, d.address,
                           (SELECT COUNT(*) FROM items i WHERE i.donor_id = d.donor_id) AS item_count
                    FROM donors d";
            $order = "ORDER BY d.name, d.donor_id";
            $columns = ["Donor #", "Name", "Phone", "Address", "Items Donated"];
            break;
    }

    $sql .= ($where ? " WHERE " . implode(" AND ", $where) : "")
          . " $order LIMIT " . (SEARCH_LIMIT + 1);   // one extra row tells us there are more

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $found = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $truncated = count($found) > SEARCH_LIMIT;
    $found = array_slice($found, 0, SEARCH_LIMIT);

    $rows = [];
    foreach ($found as $r) {
        switch ($type) {
            case 'items':
                $winner = $r['paddle_number'] !== null ? $r['paddle_number'] . ' - ' . $r['winner_name'] : '';
                $rows[] = [
                    "cells" => [
                        sectionText($r['section']), $r['name'], (string) $r['donor_name'],
                        ucfirst($r['status']), $winner, money($r['bid_cents']),
                        $r['status'] === 'closed' ? ((int) $r['paid'] === 1 ? 'Yes' : 'No') : ''
                    ],
                    "link" => ["label" => "Edit", "href" => "items.php?item_id=" . (int) $r['item_id']]
                ];
                break;

            case 'winners':
                $rows[] = [
                    "cells" => [
                        sectionText($r['section']), $r['name'],
                        $r['paddle_number'] . ' - ' . $r['winner_name'],
                        money($r['bid_cents']), (int) $r['paid'] === 1 ? 'Yes' : 'No'
                    ],
                    "link" => ["label" => "Change winner", "href" => $r['section'] !== null
                                    ? "winners.php?section=" . rawurlencode($r['section'])
                                    : "winners.php?item_id=" . (int) $r['item_id']]
                ];
                break;

            case 'bidders':
                $rows[] = [
                    "cells" => [
                        $r['paddle_number'] !== null ? (string) $r['paddle_number'] : '(none)',
                        $r['name'], (string) $r['phone'], (string) $r['address'],
                        '$' . centsToString((int) $r['owed_cents'])
                    ],
                    "link" => ["label" => "Edit", "href" => "bidders.php?bidder_id=" . (int) $r['bidder_id']]
                ];
                break;

            case 'donors':
                $rows[] = [
                    "cells" => [
                        (string) $r['donor_id'], $r['name'], (string) $r['phone'], (string) $r['address'],
                        (string) $r['item_count']
                    ],
                    "link" => ["label" => "Edit", "href" => "donors.php?donor_id=" . (int) $r['donor_id']]
                ];
                break;
        }
    }

    echo json_encode([
        "success"   => true,
        "type"      => $type,
        "columns"   => $columns,
        "rows"      => $rows,
        "truncated" => $truncated
    ]);
} catch (PDOException $e) {
    fail(500, "Database error: " . $e->getMessage());
}
