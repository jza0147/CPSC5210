<?php
require "../includes/db.php";
require "../includes/backup.php";

// POST {file, confirm: "RESTORE"}  -- replaces everything with the contents of a backup.
// The file is checked first, then a safety copy of the current data is saved, then the
// swap happens in one transaction: it either all works or nothing changes.

$input = readPostBody();
if (($input['confirm'] ?? '') !== 'RESTORE') {
    fail(400, "Type RESTORE to confirm", "NOT_CONFIRMED");
}
$stem = cleanStem($input['file'] ?? '');
if ($stem === null) {
    fail(400, "Choose a backup", "BAD_NAME");
}

try {
    $tables = loadBackup($stem);
    [$safety] = writeBackup($pdo, null, false, 'before-restore');
    try {
        replaceAll($pdo, $tables);
    } catch (PDOException $e) {
        error_log("Restore of $stem failed: " . $e->getMessage());
        fail(400, "That backup's data doesn't fit the database (for example a duplicate paddle number, two items with the same section, or an item pointing at a donor that isn't in the file). Nothing was changed.", "RESTORE_FAILED");
    }
    echo json_encode(["success" => true, "file" => $stem, "safety_copy" => $safety, "restored" => array_map(function ($t) { return count($t["rows"]); }, $tables)]);
} catch (PDOException $e) {
    fail(500, "Database error: " . $e->getMessage());
}
