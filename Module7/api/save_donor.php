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
$donorId = trim((string) ($input['donor_id'] ?? ''));
$name    = normalizeName($input['name'] ?? '');
// Only an explicit true means "yes, add it even though the name exists".
$allowDuplicate = (($input['allow_duplicate'] ?? false) === true);
$address = trim((string) ($input['address'] ?? ''));
$phone   = trim((string) ($input['phone'] ?? ''));

// --- Validate ---
if ($name === '') {
    fail(400, "Donor name is required");
}
if (mb_strlen($name) > 100)    { fail(400, "Name is too long (100 characters max)"); }
if (mb_strlen($address) > 200) { fail(400, "Address is too long (200 characters max)"); }
if (mb_strlen($phone) > 20)    { fail(400, "Phone number is too long (20 characters max)"); }

if ($donorId !== '' && !ctype_digit($donorId)) {
    fail(400, "Invalid donor id");
}

try {
    if ($donorId === '') {
        if (!$allowDuplicate) {
            $same = findSameName($pdo, 'donors', 'donor_id', $name);
            if ($same) { failDuplicateName("donor", $name, $same); }
        }
        // No id yet -- brand new donor. The database generates the unique
        // donor number (donors start at 5000, separate from items and bidders).
        $stmt = $pdo->prepare(
            "INSERT INTO donors (name, address, phone)
             VALUES (:name, :address, :phone)"
        );
        $stmt->execute([
            ':name'    => $name,
            ':address' => $address,
            ':phone'   => $phone
        ]);
        $donorId = (int) $pdo->lastInsertId();
    } else {
        // Make sure that donor really exists. (An UPDATE that changes nothing
        // also reports 0 rows, so rowCount() can't tell us this.)
        $check = $pdo->prepare("SELECT name FROM donors WHERE donor_id = :id");
        $check->execute([':id' => (int) $donorId]);
        $oldName = $check->fetchColumn();
        if ($oldName === false) {
            fail(404, "That donor no longer exists -- click Start New Donor");
        }
        // Only when the name itself was changed (address/phone saves must not re-ask).
        if (!$allowDuplicate && mb_strtolower(normalizeName($oldName)) !== mb_strtolower($name)) {
            $same = findSameName($pdo, 'donors', 'donor_id', $name, (int) $donorId);
            if ($same) { failDuplicateName("donor", $name, $same); }
        }

        $stmt = $pdo->prepare(
            "UPDATE donors
             SET name = :name, address = :address, phone = :phone
             WHERE donor_id = :id"
        );
        $stmt->execute([
            ':name'    => $name,
            ':address' => $address,
            ':phone'   => $phone,
            ':id'      => (int) $donorId
        ]);
        $donorId = (int) $donorId;
    }

    echo json_encode(["success" => true, "donor_id" => $donorId]);
} catch (PDOException $e) {
    // Fine for local testing; don't show raw database errors on a real site.
    fail(500, "Database error: " . $e->getMessage());
}
