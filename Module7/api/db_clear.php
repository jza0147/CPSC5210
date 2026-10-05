<?php
require "../includes/db.php";
require "../includes/backup.php";

// POST {confirm: "CLEAR", skip_backup?: true}
// Empties every table and restarts the id numbers.
// Normally a safety copy is saved first, and if that fails nothing is deleted
// (error_code BACKUP_FAILED). Only an explicit boolean true in skip_backup clears without one.

$input = readPostBody();
if (($input['confirm'] ?? '') !== 'CLEAR') {
    fail(400, "Type CLEAR to confirm", "NOT_CONFIRMED");
}
$skipBackup = (($input['skip_backup'] ?? false) === true);

try {
    if ($skipBackup) {
        $counts = countRows(snapshotAll($pdo));
        replaceAll($pdo, []);
        echo json_encode(["success" => true, "safety_copy" => null, "deleted" => $counts]);
    } else {
        [$safety, $counts] = writeBackup($pdo, null, false, 'before-clear');
        replaceAll($pdo, []);
        echo json_encode(["success" => true, "safety_copy" => $safety, "deleted" => $counts]);
    }
} catch (PDOException $e) {
    fail(500, "Database error: " . $e->getMessage());
}
