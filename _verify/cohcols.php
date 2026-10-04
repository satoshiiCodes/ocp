<?php
require_once __DIR__ . '/../config/db_config.php';
foreach ($pdo->query("SHOW COLUMNS FROM cash_on_hand")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    printf("  %-20s %-24s null=%s def=%s\n", $c['Field'], $c['Type'], $c['Null'], var_export($c['Default'], true));
}
