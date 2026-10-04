<?php
require_once __DIR__ . '/../config/db_config.php';
echo json_encode($pdo->query("SHOW TABLES LIKE '%spare%'")->fetchAll(PDO::FETCH_COLUMN));
