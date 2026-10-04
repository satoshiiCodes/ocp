<?php
// Lists the active Engineering users, for the tests that fill an engineer picker.
require_once __DIR__ . '/../config/db_config.php';
$rows = $pdo->query("SELECT id FROM users WHERE department = 'Engineering' AND status = 'active' ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
echo json_encode(array_map('intval', $rows));
