<?php
require_once __DIR__ . '/../config/db_config.php';
foreach (['spare_parts_suppliers', 'spare_parts'] as $t) {
    echo "=== $t\n";
    foreach ($pdo->query("SHOW COLUMNS FROM $t")->fetchAll(PDO::FETCH_ASSOC) as $c) {
        printf("  %-22s %-28s null=%s def=%s\n", $c['Field'], $c['Type'], $c['Null'], var_export($c['Default'], true));
    }
}
