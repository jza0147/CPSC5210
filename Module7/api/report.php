<?php
header("Content-Type: application/json");
require "../includes/db.php";
require "../includes/money.php";
require "../includes/sections.php";

// Read-only. One endpoint for every report on the Reports page:
//   GET api/report.php?report=<name>[&paddle_number=..][&donor_id=..]
//
// Every report comes back shaped for display:
//   title, columns, numeric (column numbers to right-align), rows (cells),
//   summary (label/value pairs under the table), refresh (poll again?),
//   or layout = "sheets" for the printable bid sheets.
// Money is added up in whole cents (see includes/money.php), never as floats.
// Items are identified and sorted by section (see includes/sections.php); the item number
// is only the database's own key and is never shown.

function fail($httpCode, $message, $errorCode = null) {
    http_response_code($httpCode);
    echo json_encode(["success" => false, "error" => $message, "error_code" => $errorCode]);
    exit;
}

function dollars($cents) {
    return $cents === null ? '' : '$' . centsToString((int) $cents);
}

function winnerText($paddle, $name) {
    return $paddle === null ? '' : $paddle . ' - ' . $name;
}

function yesNo($flag) {
    return ((int) $flag === 1) ? 'Yes' : 'No';
}

$report = (string) ($_GET['report'] ?? '');
$known  = ['items', 'bid-sheets', 'not-bid-on', 'winners-running', 'unpaid',
           'won-by-bidder', 'by-donor', 'donors', 'bidders'];
if (!in_array($report, $known, true)) {
    fail(400, "Unknown report");
}

try {
    $out = ["layout" => "table", "refresh" => false, "numeric" => [], "summary" => []];

    switch ($report) {

        // ---------------------------------------------------------- Item list
        case 'items':
            $stmt = $pdo->query(
                "SELECT i.item_id, i.name, i.description, i.section, i.status, i.paid,
                        d.name AS donor_name, b.paddle_number, b.name AS winner_name,
                        CAST(ROUND(i.suggested_start_price * 100) AS UNSIGNED) AS start_cents,
                        CAST(ROUND(i.price * 100) AS UNSIGNED) AS price_cents,
                        CAST(ROUND(i.winning_bid * 100) AS UNSIGNED) AS bid_cents
                 FROM items i
                 LEFT JOIN donors d  ON d.donor_id  = i.donor_id
                 LEFT JOIN bidders b ON b.bidder_id = i.winner_id
                 ORDER BY " . sectionOrder('i')
            );
            $rows = []; $total = 0; $sold = 0; $count = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $count++;
                if ($r['status'] === 'closed') { $sold++; $total += (int) $r['bid_cents']; }
                $rows[] = [
                    sectionText($r['section']), $r['name'], (string) $r['description'],
                    (string) $r['donor_name'], dollars($r['start_cents']), dollars($r['price_cents']),
                    ucfirst($r['status']), winnerText($r['paddle_number'], $r['winner_name']),
                    dollars($r['bid_cents']), $r['status'] === 'closed' ? yesNo($r['paid']) : ''
                ];
            }
            $out += [
                "title"   => "Item List",
                "columns" => ["Section", "Name", "Description", "Donor", "Suggested Start", "Price",
                              "Status", "Winner", "Winning Bid", "Paid"],
                "rows"    => $rows
            ];
            $out["numeric"]   = [4, 5, 8];
            $out["summary"]   = [["Items", (string) $count], ["Sold", (string) $sold],
                                 ["Not sold yet", (string) ($count - $sold)], ["Total raised", dollars($total)]];
            break;

        // ------------------------------------------------- Printable bid sheets
        case 'bid-sheets':
            $stmt = $pdo->query(
                "SELECT i.section, i.name, i.description, d.name AS donor_name,
                        CAST(ROUND(i.suggested_start_price * 100) AS UNSIGNED) AS start_cents
                 FROM items i LEFT JOIN donors d ON d.donor_id = i.donor_id
                 ORDER BY " . sectionOrder('i')
            );
            $sheets = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $sheets[] = [
                    "section"     => sectionText($r['section']),
                    "name"        => $r['name'],
                    "description" => (string) $r['description'],
                    "donor"       => (string) $r['donor_name'],
                    "start"       => dollars($r['start_cents'])
                ];
            }
            $out["layout"] = "sheets";
            $out["title"]  = "Item Bid Sheets";
            $out["sheets"] = $sheets;
            break;

        // ------------------------------------------------------ Not bid on
        case 'not-bid-on':
            $stmt = $pdo->query(
                "SELECT i.name, i.section, d.name AS donor_name,
                        CAST(ROUND(i.suggested_start_price * 100) AS UNSIGNED) AS start_cents
                 FROM items i LEFT JOIN donors d ON d.donor_id = i.donor_id
                 WHERE i.status = 'open'
                 ORDER BY " . sectionOrder('i')
            );
            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rows[] = [sectionText($r['section']), $r['name'],
                           (string) $r['donor_name'], dollars($r['start_cents'])];
            }
            $out += [
                "title"   => "Items Not Bid On",
                "columns" => ["Section", "Name", "Donor", "Suggested Start"],
                "rows"    => $rows
            ];
            $out["numeric"] = [3];
            $out["summary"] = [["Items without a winner", (string) count($rows)]];
            break;

        // ----------------------------------------- Running list of winners
        case 'winners-running':
            $stmt = $pdo->query(
                "SELECT i.name, i.section, b.paddle_number, b.name AS winner_name,
                        CAST(ROUND(i.winning_bid * 100) AS UNSIGNED) AS bid_cents
                 FROM items i JOIN bidders b ON b.bidder_id = i.winner_id
                 WHERE i.status = 'closed'
                 ORDER BY " . sectionOrder('i')
            );
            $rows = []; $total = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $total += (int) $r['bid_cents'];
                $rows[] = [sectionText($r['section']), $r['name'], (string) $r['paddle_number'],
                           $r['winner_name'], dollars($r['bid_cents'])];
            }
            $out += [
                "title"   => "Winners by Item",
                "columns" => ["Section", "Item", "Paddle", "Winner", "Winning Bid"],
                "rows"    => $rows
            ];
            $out["numeric"] = [4];
            $out["refresh"] = true;   // the page re-checks this one every few seconds
            $out["summary"] = [["Items sold", (string) count($rows)], ["Total raised so far", dollars($total)]];
            break;

        // -------------------------------------------------- Items not paid for
        case 'unpaid':
            $stmt = $pdo->query(
                "SELECT i.name, i.section, b.bidder_id, b.paddle_number, b.name AS winner_name,
                        CAST(ROUND(i.winning_bid * 100) AS UNSIGNED) AS bid_cents
                 FROM items i JOIN bidders b ON b.bidder_id = i.winner_id
                 WHERE i.status = 'closed' AND i.paid = 0
                 ORDER BY b.paddle_number, " . sectionOrder('i')
            );
            $rows = []; $total = 0; $bidders = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $total += (int) $r['bid_cents'];
                $bidders[$r['bidder_id']] = true;
                $rows[] = [(string) $r['paddle_number'], $r['winner_name'], sectionText($r['section']),
                           $r['name'], dollars($r['bid_cents'])];
            }
            $out += [
                "title"   => "Items Not Paid For",
                "columns" => ["Paddle", "Winner", "Section", "Item", "Winning Bid"],
                "rows"    => $rows
            ];
            $out["numeric"] = [4];
            $out["summary"] = [["Unpaid items", (string) count($rows)],
                               ["Bidders who owe", (string) count($bidders)],
                               ["Total owed", dollars($total)]];
            break;

        // ------------------------------------------------ Items won by a bidder
        case 'won-by-bidder':
            $paddle = trim((string) ($_GET['paddle_number'] ?? ''));
            if ($paddle === '' || !ctype_digit($paddle) || (int) $paddle < 1 || (int) $paddle > 999) {
                fail(400, "Paddle number must be a whole number from 1 to 999");
            }
            $stmt = $pdo->prepare("SELECT bidder_id, name FROM bidders WHERE paddle_number = :p");
            $stmt->execute([':p' => (int) $paddle]);
            $bidder = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$bidder) {
                fail(404, "No bidder has paddle " . (int) $paddle, "BIDDER_NOT_FOUND");
            }
            $stmt = $pdo->prepare(
                "SELECT i.name, i.section, i.paid,
                        CAST(ROUND(i.winning_bid * 100) AS UNSIGNED) AS bid_cents
                 FROM items i WHERE i.winner_id = :b AND i.status = 'closed' ORDER BY " . sectionOrder('i')
            );
            $stmt->execute([':b' => $bidder['bidder_id']]);
            $rows = []; $total = 0; $paid = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $total += (int) $r['bid_cents'];
                if ((int) $r['paid'] === 1) { $paid += (int) $r['bid_cents']; }
                $rows[] = [sectionText($r['section']), $r['name'],
                           dollars($r['bid_cents']), yesNo($r['paid'])];
            }
            $out += [
                "title"   => "Items Won by " . $bidder['name'] . " (Paddle " . (int) $paddle . ")",
                "columns" => ["Section", "Item", "Winning Bid", "Paid"],
                "rows"    => $rows
            ];
            $out["numeric"] = [2];
            $out["summary"] = [["Items won", (string) count($rows)], ["Total won", dollars($total)],
                               ["Already paid", dollars($paid)], ["Still owed", dollars($total - $paid)]];
            break;

        // ----------------------------------------------- Items donated by a donor
        case 'by-donor':
            $donorId = trim((string) ($_GET['donor_id'] ?? ''));
            if ($donorId === '' || !ctype_digit($donorId) || (int) $donorId < 1) {
                fail(400, "Choose a donor");
            }
            $stmt = $pdo->prepare("SELECT donor_id, name FROM donors WHERE donor_id = :d");
            $stmt->execute([':d' => (int) $donorId]);
            $donor = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$donor) {
                fail(404, "Donor " . (int) $donorId . " not found", "DONOR_NOT_FOUND");
            }
            $stmt = $pdo->prepare(
                "SELECT i.name, i.section, i.status, b.paddle_number, b.name AS winner_name,
                        CAST(ROUND(i.suggested_start_price * 100) AS UNSIGNED) AS start_cents,
                        CAST(ROUND(i.winning_bid * 100) AS UNSIGNED) AS bid_cents
                 FROM items i LEFT JOIN bidders b ON b.bidder_id = i.winner_id
                 WHERE i.donor_id = :d ORDER BY " . sectionOrder('i')
            );
            $stmt->execute([':d' => $donor['donor_id']]);
            $rows = []; $raised = 0; $sold = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if ($r['status'] === 'closed') { $sold++; $raised += (int) $r['bid_cents']; }
                $rows[] = [sectionText($r['section']), $r['name'], dollars($r['start_cents']),
                           ucfirst($r['status']), winnerText($r['paddle_number'], $r['winner_name']),
                           dollars($r['bid_cents'])];
            }
            $out += [
                "title"   => "Items Donated by " . $donor['name'],
                "columns" => ["Section", "Item", "Suggested Start", "Status", "Winner", "Winning Bid"],
                "rows"    => $rows
            ];
            $out["numeric"] = [2, 5];
            $out["summary"] = [["Items donated", (string) count($rows)], ["Items sold", (string) $sold],
                               ["Total raised", dollars($raised)]];
            break;

        // ------------------------------------------------------------ Donor list
        case 'donors':
            $stmt = $pdo->query(
                "SELECT d.donor_id, d.name, d.address, d.phone,
                        COUNT(i.item_id) AS item_count, COUNT(i.winner_id) AS sold_count,
                        CAST(ROUND(COALESCE(SUM(i.winning_bid), 0) * 100) AS UNSIGNED) AS raised_cents
                 FROM donors d LEFT JOIN items i ON i.donor_id = d.donor_id
                 GROUP BY d.donor_id, d.name, d.address, d.phone
                 ORDER BY d.name, d.donor_id"
            );
            $rows = []; $raised = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $raised += (int) $r['raised_cents'];
                $rows[] = [(string) $r['donor_id'], $r['name'], (string) $r['address'], (string) $r['phone'],
                           (string) $r['item_count'], (string) $r['sold_count'], dollars($r['raised_cents'])];
            }
            $out += [
                "title"   => "Donor List",
                "columns" => ["Donor #", "Name", "Address", "Phone", "Items Donated", "Items Sold", "Total Raised"],
                "rows"    => $rows
            ];
            $out["numeric"] = [4, 5, 6];
            $out["summary"] = [["Donors", (string) count($rows)], ["Total raised from donated items", dollars($raised)]];
            break;

        // ----------------------------------------------------------- Bidder list
        case 'bidders':
            $stmt = $pdo->query(
                "SELECT b.paddle_number, b.name, b.address, b.phone, COUNT(w.item_id) AS won_count,
                        CAST(ROUND(COALESCE(SUM(w.winning_bid), 0) * 100) AS UNSIGNED) AS won_cents,
                        CAST(ROUND(COALESCE(SUM(CASE WHEN w.paid = 0 THEN w.winning_bid ELSE 0 END), 0) * 100) AS UNSIGNED) AS owed_cents
                 FROM bidders b LEFT JOIN items w ON w.winner_id = b.bidder_id AND w.status = 'closed'
                 GROUP BY b.bidder_id, b.paddle_number, b.name, b.address, b.phone
                 ORDER BY b.paddle_number IS NULL, b.paddle_number, b.bidder_id"
            );
            $rows = []; $won = 0; $owed = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $won  += (int) $r['won_cents'];
                $owed += (int) $r['owed_cents'];
                $rows[] = [$r['paddle_number'] !== null ? (string) $r['paddle_number'] : '(none)', $r['name'],
                           (string) $r['address'], (string) $r['phone'], (string) $r['won_count'],
                           dollars($r['won_cents']), dollars($r['owed_cents'])];
            }
            $out += [
                "title"   => "Bidder List",
                "columns" => ["Paddle", "Name", "Address", "Phone", "Items Won", "Total Won", "Owes"],
                "rows"    => $rows
            ];
            $out["numeric"] = [4, 5, 6];
            $out["summary"] = [["Bidders", (string) count($rows)], ["Total won", dollars($won)],
                               ["Total still owed", dollars($owed)]];
            break;
    }

    echo json_encode(["success" => true, "report" => $report] + $out);
} catch (PDOException $e) {
    fail(500, "Database error: " . $e->getMessage());
}
