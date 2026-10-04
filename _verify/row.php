<?php
// Prints one row as JSON, so _verify/row-snapshot.mjs can record it verbatim.
//
//   php _verify/row.php <table> <id>
require_once __DIR__ . '/../config/db_config.php';

$table = $argv[1] ?? '';
$id = $argv[2] ?? '';

if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $table) || !ctype_digit((string) $id)) {
    echo 'null';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = :id");
$stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo $row ? json_encode($row) : 'null';
