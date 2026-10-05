<?php
require "../includes/db.php";
require "../includes/backup.php";

// Read-only: the backups available to download or restore, newest first.
echo json_encode(["success" => true, "folder" => "backups", "backups" => listBackups()]);
