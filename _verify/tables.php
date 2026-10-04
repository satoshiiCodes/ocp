<?php
require_once __DIR__ . '/../config/db_config.php';
echo json_encode($pdo->query("SHOW TABLES LIKE '%purchase%'")->fetchAll(PDO::FETCH_COLUMN));
