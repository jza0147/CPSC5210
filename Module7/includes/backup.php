<?php
// Shared code for the Database Functions page: save, list, restore and clear.
//
// A backup is a single .json file in the "backups" folder: every row of every
// table, read in one consistent snapshot. Restoring it reads the same file back
// with prepared statements, so a backup file can never run arbitrary SQL.

header("Content-Type: application/json");

// First number each table hands out. Must match schema.sql.
const AUTO_INCREMENT_START = ['donors' => 5000, 'bidders' => 9000, 'items' => 1000, 'payments' => 1];

// Tables in the order rows can be INSERTED (parents first). Deleting uses the reverse.
// Each entry: table => [primary key, columns that are backed up].
const BACKUP_TABLES = [
    'donors'   => ['donor_id',   ['donor_id', 'name', 'address', 'phone']],
    'bidders'  => ['bidder_id',  ['bidder_id', 'paddle_number', 'name', 'address', 'phone', 'paid']],
    'items'    => ['item_id',    ['item_id', 'name', 'description', 'section', 'price', 'suggested_start_price',
                                  'donor_id', 'status', 'winner_id', 'winning_bid', 'won_at', 'paid']],
    'payments' => ['payment_id', ['payment_id', 'bidder_id', 'method', 'check_number', 'amount', 'card_fee', 'paid_at']],
];

const BACKUP_FORMAT = 'auction-backup';
const BACKUP_VERSION = 1;
const MAX_BACKUP_BYTES = 50 * 1024 * 1024;

function fail($httpCode, $message, $errorCode = null) {
    global $pdo;
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code($httpCode);
    header("Content-Type: application/json");
    echo json_encode(["success" => false, "error" => $message, "error_code" => $errorCode]);
    exit;
}

// Everything that changes data must be a POST with a JSON body.
function readPostBody() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail(405, "Method not allowed");
    }
    $input = json_decode(file_get_contents("php://input"), true);
    if (!is_array($input)) {
        fail(400, "Invalid request body");
    }
    return $input;
}

// ---- the backups folder ---------------------------------------------------------------

function backupDir() {
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775) && !is_dir($dir)) {
            fail(500, "Could not create the backups folder. Check that the project folder is writable.", "BACKUP_FAILED");
        }
    }
    // Backups hold names, addresses and phone numbers: keep Apache from serving them directly.
    // (They can still be downloaded through api/db_download.php.)
    $guard = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($guard)) {
        @file_put_contents($guard, "Require all denied\n");
    }
    return $dir;
}

// A backup is named by its "stem": letters, numbers, spaces, - and _ only. That keeps
// every name safe to use as a file name (no folders, no ".." tricks).
function cleanStem($raw) {
    $name = trim((string) $raw);
    if (preg_match('/\.json$/i', $name)) {
        $name = substr($name, 0, -5);
    }
    $name = trim($name);
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 _-]{0,59}$/', $name)
        || preg_match('/^(con|prn|aux|nul|com[1-9]|lpt[1-9])$/i', $name)) {
        return null;
    }
    return $name;
}

function backupPath($stem) {
    return backupDir() . DIRECTORY_SEPARATOR . $stem . '.json';
}

function listBackups() {
    $out = [];
    foreach (glob(backupDir() . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
        $stem = substr(basename($path), 0, -5);
        if (cleanStem($stem) !== $stem) {
            continue;   // not one of ours
        }
        $out[] = ["file" => $stem, "size" => filesize($path), "modified" => filemtime($path)];
    }
    usort($out, function ($a, $b) {
        return $b['modified'] <=> $a['modified'] ?: strcmp($a['file'], $b['file']);
    });
    return $out;
}

// ---- reading and writing the data ---------------------------------------------------

// All tables, read inside one transaction so they agree with each other even while
// other people keep working.
function snapshotAll(PDO $pdo) {
    $pdo->exec("START TRANSACTION WITH CONSISTENT SNAPSHOT");
    try {
        $tables = [];
        foreach (BACKUP_TABLES as $table => [$pk, $columns]) {
            $stmt = $pdo->query("SELECT " . implode(", ", $columns) . " FROM $table ORDER BY $pk");
            $tables[$table] = [];
            foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
                // Strings and nulls only, so numbers come back exactly as the database stored them.
                $tables[$table][] = array_map(function ($v) { return $v === null ? null : (string) $v; }, $row);
            }
        }
    } finally {
        $pdo->commit();
    }
    return $tables;
}

function countRows(array $tables) {
    $counts = [];
    foreach ($tables as $table => $rows) {
        $counts[$table] = count($rows);
    }
    return $counts;
}

// Writes a backup file and returns [stem, counts].
//   $stem given:  that exact name. With $overwrite false an existing file is never touched.
//   $stem null:   an automatic name, <prefix>_<date>_<time>, with -2, -3... added if several
//                 are saved in the same second.
// Files are created atomically ("x" mode), so two people saving at once can't clobber each other.
function writeBackup(PDO $pdo, $stem, $overwrite, $prefix = 'auction') {
    $tables = snapshotAll($pdo);
    $json = json_encode([
        "format"   => BACKUP_FORMAT,
        "version"  => BACKUP_VERSION,
        "saved_at" => date('Y-m-d H:i:s'),
        "counts"   => countRows($tables),
        "columns"  => array_map(function ($t) { return $t[1]; }, BACKUP_TABLES),
        "tables"   => $tables,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        fail(500, "Could not encode the data for saving");
    }

    if ($stem !== null && $overwrite) {
        $path = backupPath($stem);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            @unlink($tmp);
            fail(500, "Could not write the backup file", "BACKUP_FAILED");
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            fail(500, "Could not replace the existing backup file", "BACKUP_FAILED");
        }
        return [$stem, countRows($tables)];
    }

    $base = $prefix . '_' . date('Y-m-d_His');
    for ($n = 1; $n <= 200; $n++) {
        $candidate = ($stem !== null) ? $stem : ($n === 1 ? $base : $base . '-' . $n);
        $path = backupPath($candidate);
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            if ($stem !== null) {
                fail(409, "A backup named \"$stem\" already exists", "FILE_EXISTS");
            }
            continue;   // automatic name already taken: try the next suffix
        }
        $ok = (fwrite($handle, $json) === strlen($json));
        fclose($handle);
        if (!$ok) {
            @unlink($path);
            fail(500, "Could not write the backup file", "BACKUP_FAILED");
        }
        return [$candidate, countRows($tables)];
    }
    fail(500, "Could not save the backup file. Check that the backups folder is writable.", "BACKUP_FAILED");
}

// Reads and checks a backup file, returning its tables. Nothing is changed in the
// database here, so a bad file is rejected before anything is deleted.
function loadBackup($stem) {
    $path = backupPath($stem);
    if (!is_file($path)) {
        fail(404, "Backup not found", "BACKUP_NOT_FOUND");
    }
    if (filesize($path) > MAX_BACKUP_BYTES) {
        fail(400, "That backup file is too large");
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || ($data['format'] ?? null) !== BACKUP_FORMAT || ($data['version'] ?? null) !== BACKUP_VERSION
        || !is_array($data['tables'] ?? null)) {
        fail(400, "That file is not a valid auction backup", "BAD_BACKUP");
    }
    $tables = [];
    foreach (BACKUP_TABLES as $table => [$pk, $columns]) {
        $rows = $data['tables'][$table] ?? null;
        if (!is_array($rows)) {
            fail(400, "The backup is missing the $table table", "BAD_BACKUP");
        }
        // Each backup lists the columns it holds, so a file saved before a column was added
        // still loads (the missing column just takes its default).
        $fileColumns = $data['columns'][$table] ?? $columns;
        if (!is_array($fileColumns) || !in_array($pk, $fileColumns, true)
            || count($fileColumns) !== count(array_unique($fileColumns))
            || array_diff($fileColumns, $columns) !== []) {
            fail(400, "The backup has an unexpected column list for $table", "BAD_BACKUP");
        }
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) !== count($fileColumns)) {
                fail(400, "The backup has a damaged row in $table", "BAD_BACKUP");
            }
            foreach ($row as $value) {
                if ($value !== null && !is_string($value)) {
                    fail(400, "The backup has a damaged row in $table", "BAD_BACKUP");
                }
            }
        }
        // Older backups may hold a blank section as "": that now means "no section" (NULL), because
        // sections must be unique and blanks would count as duplicates of each other.
        if ($table === 'items' && ($i = array_search('section', $fileColumns, true)) !== false) {
            foreach ($rows as $n => $row) {
                if ($row[$i] !== null && trim($row[$i]) === '') {
                    $rows[$n][$i] = null;
                }
            }
        }
        $tables[$table] = ["columns" => array_values($fileColumns), "rows" => $rows];
    }
    return $tables;
}

// Makes the next generated id continue after the highest one in use (or restart at the
// table's starting number when it is empty). ALTER TABLE commits by itself, so this runs
// after the transaction.
function resetAutoIncrement(PDO $pdo) {
    foreach (BACKUP_TABLES as $table => [$pk, $columns]) {
        $max = (int) $pdo->query("SELECT COALESCE(MAX($pk), 0) FROM $table")->fetchColumn();
        $next = max(AUTO_INCREMENT_START[$table], $max + 1);
        $pdo->exec("ALTER TABLE $table AUTO_INCREMENT = " . (int) $next);
    }
}

// Replaces everything in the database with $tables as returned by loadBackup (all or
// nothing), or just empties it when $tables is empty.
//
// All four tables are locked for the duration, so anyone saving something at that moment
// simply waits a second instead of being handed an id that is about to be reused. If
// anything fails the changes are rolled back and the old data is still there.
function replaceAll(PDO $pdo, array $tables) {
    $pdo->exec("SET autocommit = 0");
    try {
        $pdo->exec("LOCK TABLES " . implode(" WRITE, ", array_keys(BACKUP_TABLES)) . " WRITE");
        try {
            foreach (array_reverse(array_keys(BACKUP_TABLES)) as $table) {   // children first
                $pdo->exec("DELETE FROM $table");
            }
            foreach (BACKUP_TABLES as $table => [$pk, $columns]) {
                if (empty($tables[$table]['rows'])) {
                    continue;
                }
                $use = $tables[$table]['columns'];
                $stmt = $pdo->prepare("INSERT INTO $table (" . implode(", ", $use) . ") VALUES ("
                                      . implode(", ", array_fill(0, count($use), "?")) . ")");
                foreach ($tables[$table]['rows'] as $row) {
                    $stmt->execute($row);
                }
            }
            $pdo->exec("COMMIT");
        } catch (PDOException $e) {
            $pdo->exec("ROLLBACK");
            throw $e;
        }
        resetAutoIncrement($pdo);   // still holding the locks, so nothing can sneak in between
    } finally {
        try {
            $pdo->exec("UNLOCK TABLES");
            $pdo->exec("SET autocommit = 1");
        } catch (PDOException $ignored) {
        }
    }
}
