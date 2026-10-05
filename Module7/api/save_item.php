<?php
header("Content-Type: application/json");
require "../includes/db.php";

function fail($code, $message, $errorCode = null) {
    http_response_code($code);
    echo json_encode(["success" => false, "error" => $message, "error_code" => $errorCode]);
    exit;
}

// Only allow POST -- a GET request should never be able to trigger a save.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

// The JS sent JSON in the request body, not a normal form post,
// so we read it from php://input instead of $_POST.
$input = json_decode(file_get_contents("php://input"), true);

if ($input === null) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid request body"]);
    exit;
}

// --- Validate / clean up input ---
$itemId               = isset($input['item_id']) ? trim($input['item_id']) : '';
$name                 = trim($input['name'] ?? '');
$description          = trim($input['description'] ?? '');
$section              = trim($input['section'] ?? '');
$price                = $input['price'] ?? null;
$suggestedStartPrice  = $input['suggested_start_price'] ?? null;
$donorId              = $input['donor_id'] ?? '';

if ($name === '') {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Item name is required"]);
    exit;
}

// A section is how this item is labeled and found, so no two items may share one.
// "No section yet" is stored as NULL (blank text would count as a duplicate of other blanks).
if (mb_strlen($section) > 50) {
    fail(400, "Section is too long (50 characters max)");
}
$section = ($section === '') ? null : $section;

// Empty numeric fields should be stored as NULL, not an empty string.
$price               = ($price === '' || $price === null) ? null : (float) $price;
$suggestedStartPrice = ($suggestedStartPrice === '' || $suggestedStartPrice === null) ? null : (float) $suggestedStartPrice;
$donorId             = ($donorId === '' ) ? null : (int) $donorId;

try {
    if ($itemId === '') {
        // No id yet -- this is a brand new item. Insert it.
        $stmt = $pdo->prepare(
            "INSERT INTO items (name, description, section, price, suggested_start_price, donor_id)
             VALUES (:name, :description, :section, :price, :suggested_start_price, :donor_id)"
        );
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':section' => $section,
            ':price' => $price,
            ':suggested_start_price' => $suggestedStartPrice,
            ':donor_id' => $donorId
        ]);

        $newId = $pdo->lastInsertId();

        echo json_encode(["success" => true, "item_id" => $newId]);
    } else {
        // We already have an id from a previous save -- update that row.
        $stmt = $pdo->prepare(
            "UPDATE items
             SET name = :name,
                 description = :description,
                 section = :section,
                 price = :price,
                 suggested_start_price = :suggested_start_price,
                 donor_id = :donor_id
             WHERE item_id = :item_id"
        );
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':section' => $section,
            ':price' => $price,
            ':suggested_start_price' => $suggestedStartPrice,
            ':donor_id' => $donorId,
            ':item_id' => (int) $itemId
        ]);

        echo json_encode(["success" => true, "item_id" => (int) $itemId]);
    }
} catch (PDOException $e) {
    // 1062 = duplicate value in a UNIQUE column. The only one here is the section.
    if (($e->errorInfo[1] ?? 0) === 1062 && $section !== null) {
        $stmt = $pdo->prepare("SELECT name FROM items WHERE section = :s");
        $stmt->execute([':s' => $section]);
        $other = $stmt->fetchColumn();
        fail(409, "Section " . $section . " is already used by " . ($other !== false ? "\"" . $other . "\"" : "another item")
                  . ". Nothing was saved -- choose a different section.", "SECTION_TAKEN");
    }
    fail(500, "Database error: " . $e->getMessage());
}
