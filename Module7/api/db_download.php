<?php
require "../includes/db.php";
require "../includes/backup.php";

// GET ?file=<name>  -- sends one backup to the browser as a download.
$stem = cleanStem($_GET['file'] ?? '');
if ($stem === null || !is_file(backupPath($stem))) {
    fail(404, "Backup not found", "BACKUP_NOT_FOUND");
}
$path = backupPath($stem);
header("Content-Type: application/json");
header("Content-Disposition: attachment; filename=\"" . $stem . ".json\"");
header("Content-Length: " . filesize($path));
header("X-Content-Type-Options: nosniff");
readfile($path);
