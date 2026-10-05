<?php
require "../includes/db.php";
require "../includes/backup.php";

// POST {name?, overwrite?}
//   Save:    no name  -> auction_<date>_<time>.json
//   Save As: a name   -> <name>.json; if that name is taken the answer is 409 FILE_EXISTS
//            and the page asks before resending with overwrite = true.

$input = readPostBody();
$rawName = trim((string) ($input['name'] ?? ''));
$overwrite = ($input['overwrite'] ?? false) === true;

try {
    if ($rawName === '') {
        $stem = null;           // automatic name
        $overwrite = false;
    } else {
        $stem = cleanStem($rawName);
        if ($stem === null) {
            fail(400, "Use letters, numbers, spaces, - and _ only (up to 60 characters, starting with a letter or number)", "BAD_NAME");
        }
    }
    [$stem, $counts] = writeBackup($pdo, $stem, $overwrite);
    echo json_encode(["success" => true, "file" => $stem, "counts" => $counts]);
} catch (PDOException $e) {
    fail(500, "Database error: " . $e->getMessage());
}
