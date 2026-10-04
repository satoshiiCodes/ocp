<?php
require_once __DIR__ . '/../config/db_config.php';
$s = $pdo->query("SHOW COLUMNS FROM gasoline_suppliers");
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $c) { printf("  %-20s %s\n", $c['Field'], $c['Type']); }
