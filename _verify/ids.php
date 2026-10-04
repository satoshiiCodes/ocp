<?php
// Real record ids for the report-generator checks.
require_once __DIR__ . '/../config/db_config.php';
echo json_encode([
    'pr' => $pdo->query("SELECT id FROM purchase_requests ORDER BY id DESC LIMIT 1")->fetchColumn(),
    'gas_po' => $pdo->query("SELECT id FROM gasoline_purchase_orders ORDER BY id DESC LIMIT 1")->fetchColumn(),
]);
