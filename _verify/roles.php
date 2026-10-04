<?php
// Emits the department/position of each test session's user, so a checker can
// know which dashboard branch applies.
require_once __DIR__ . '/../config/db_config.php';
$rows = $pdo->query("SELECT id, department, position FROM users ORDER BY id LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
$out = [];
foreach ($rows as $i => $r) { $out['ocpverify' . str_pad((string) $i, 16, '0', STR_PAD_LEFT)] = $r['department'] . '/' . $r['position']; }
echo json_encode($out);
