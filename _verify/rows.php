<?php
// Prints a whole result set as JSON lines, one row per line.
//
//   php _verify/rows.php "SELECT id, stage FROM pr_routing LIMIT 5"
require_once __DIR__ . '/../config/db_config.php';

$sql = $argv[1] ?? '';
if (!preg_match('/^\s*SELECT\b/i', $sql)) {
    fwrite(STDERR, "SELECT only\n");
    exit(2);
}

foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo json_encode($row), PHP_EOL;
}
