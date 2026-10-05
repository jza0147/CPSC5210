<?php
header("Content-Type: application/json");
require "../includes/db.php";
require "../includes/dupname.php";

function fail($code, $message) {
    http_response_code($code);
    echo json_encode(["success" => false, "error" => $message]);
    exit;
}

// Writes only -- a GET must never be able to save anything.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, "Method not allowed");
}

// The JS sends a JSON body, so read php://input instead of $_POST.
$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    fail(400, "Invalid request body");
}

// --- Pull out and clean the fields ---
$bidderId = trim((string) ($input['bidder_id'] ?? ''));
$paddle   = trim((string) ($input['paddle_number'] ?? ''));
$name     = normalizeName($input['name'] ?? '');
// Only an explicit true means "yes, add it even though the name exists".
$allowDuplicate = (($input['allow_duplicate'] ?? false) === true);
$address  = trim((string) ($input['address'] ?? ''));
$phone    = trim((string) ($input['phone'] ?? ''));

// --- Validate ---
if ($paddle === '' || !ctype_digit($paddle) || (int) $paddle < 1 || (int) $paddle > 999) {
    fail(400, "Paddle number must be a whole number from 1 to 999");
}
$paddle = (int) $paddle;

if ($name === '') {
    fail(400, "Bidder name is required");
}
if (mb_strlen($name) > 100)    { fail(400, "Name is too long (100 characters max)"); }
if (mb_strlen($address) > 200) { fail(400, "Address is too long (200 characters max)"); }
if (mb_strlen($phone) > 20)    { fail(400, "Phone number is too long (20 characters max)"); }

if ($bidderId !== '' && !ctype_digit($bidderId)) {
    fail(400, "Invalid bidder id");
}

try {
    if ($bidderId === '') {
        if (!$allowDuplicate) {
            $same = findSameName($pdo, 'bidders', 'bidder_id', $name);
            if ($same) { failDuplicateName("bidder", $name, $same); }
        }
        // No id yet -- brand new bidder.
        $stmt = $pdo->prepare(
            "INSERT INTO bidders (paddle_number, name, address, phone)
             VALUES (:paddle, :name, :address, :phone)"
        );
        $stmt->execute([
            ':paddle'  => $paddle,
            ':name'    => $name,
            ':address' => $address,
            ':phone'   => $phone
        ]);
        $bidderId = (int) $pdo->lastInsertId();
    } else {
        // Make sure that bidder really exists. (An UPDATE that changes nothing
        // also reports 0 rows, so rowCount() can't tell us this.)
        $check = $pdo->prepare("SELECT name FROM bidders WHERE bidder_id = :id");
        $check->execute([':id' => (int) $bidderId]);
        $oldName = $check->fetchColumn();
        if ($oldName === false) {
            fail(404, "That bidder no longer exists -- click Start New Bidder");
        }
        // Only when the name itself was changed (paddle/address/phone saves must not re-ask).
        if (!$allowDuplicate && mb_strtolower(normalizeName($oldName)) !== mb_strtolower($name)) {
            $same = findSameName($pdo, 'bidders', 'bidder_id', $name, (int) $bidderId);
            if ($same) { failDuplicateName("bidder", $name, $same); }
        }

        $stmt = $pdo->prepare(
            "UPDATE bidders
             SET paddle_number = :paddle,
                 name = :name,
                 address = :address,
                 phone = :phone
             WHERE bidder_id = :id"
        );
        $stmt->execute([
            ':paddle'  => $paddle,
            ':name'    => $name,
            ':address' => $address,
            ':phone'   => $phone,
            ':id'      => (int) $bidderId
        ]);
        $bidderId = (int) $bidderId;
    }

    echo json_encode([
        "success"       => true,
        "bidder_id"     => $bidderId,
        "paddle_number" => $paddle
    ]);
} catch (PDOException $e) {
    // 1062 = duplicate entry. The UNIQUE key on paddle_number is what
    // guarantees two bidders can never share a paddle, even if two clerks
    // register the same one at the same moment.
    if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062) {
        fail(409, "Paddle " . $paddle . " is already assigned to another bidder");
    }
    // Fine for local testing; don't show raw database errors on a real site.
    fail(500, "Database error: " . $e->getMessage());
}
