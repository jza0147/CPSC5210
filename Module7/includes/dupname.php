<?php
// Duplicate-name warning for donors and bidders. Only the NAME is compared (upper/lower case
// and extra spaces are ignored). Two different people can share a name, so this is a warning
// the clerk can override, not a hard block.

// Trim and squeeze runs of spaces down to one, so "John   Smith " and "john smith" match.
function normalizeName($name) {
    $clean = preg_replace('/\s+/u', ' ', (string) $name);
    return trim($clean === null ? (string) $name : $clean);
}

// Other rows with the same name (at most 3, oldest first). $table / $idColumn come from our own
// code, never from the request.
function findSameName(PDO $pdo, $table, $idColumn, $name, $excludeId = null) {
    $allowed = ['donors' => 'donor_id', 'bidders' => 'bidder_id'];
    if (!isset($allowed[$table]) || $allowed[$table] !== $idColumn) {
        throw new InvalidArgumentException("Bad table");
    }
    $sql = "SELECT $idColumn AS id, name, address, phone FROM $table WHERE TRIM(name) = :name";
    $params = [':name' => $name];
    if ($excludeId !== null) {
        $sql .= " AND $idColumn <> :id";
        $params[':id'] = (int) $excludeId;
    }
    $stmt = $pdo->prepare($sql . " ORDER BY $idColumn LIMIT 3");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Sends the answer the pages look for (error_code DUPLICATE_NAME) and stops. This is a question
// for the clerk, not a server error, so it goes out as a normal 200: some web hosts replace the
// body of 4xx/5xx replies with their own error page, and the page would never see this message.
function failDuplicateName($label, $name, array $matches) {
    http_response_code(200);
    echo json_encode([
        "success"    => false,
        "error"      => "A $label named \"$name\" already exists.",
        "error_code" => "DUPLICATE_NAME",
        "matches"    => $matches
    ]);
    exit;
}
