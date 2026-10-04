<?php
require_once __DIR__ . '/../config/db_config.php';
foreach (['project_workers','project_rentals'] as $t) {
    echo "--- $t\n";
    foreach ($pdo->query("SHOW COLUMNS FROM $t")->fetchAll(PDO::FETCH_ASSOC) as $c) {
        printf("  %-18s %s\n", $c['Field'], $c['Type']);
    }
}
