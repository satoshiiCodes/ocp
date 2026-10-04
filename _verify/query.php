<?php
// Runs one read-only query for the verification scripts and prints the result.
// Used to confirm that a page's action really changed the database.
require_once __DIR__ . '/../config/db_config.php';
$sql = $argv[1] ?? '';
if (!preg_match('/^\s*(SELECT|DELETE|UPDATE|INSERT)\b/i', $sql)) { fwrite(STDERR, "only SELECT/DELETE allowed\n"); exit(2); }
if (preg_match('/DELETE|UPDATE/i', $sql) && !preg_match('/WHERE/i', $sql)) { fwrite(STDERR, "needs a WHERE\n"); exit(2); }
$stmt = $pdo->query($sql);
if (preg_match('/^\s*SELECT/i', $sql)) {
    $v = $stmt->fetchColumn();
    echo $v === false || $v === null ? '' : $v;
} else {
    echo $stmt->rowCount();
}
