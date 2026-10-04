<?php
// Maps a username to "department/position", so a checker can tell which
// dashboard branch applies to the account it logged in with.
require_once __DIR__ . '/../config/db_config.php';
$name = $argv[1] ?? '';
$row = $pdo->prepare("SELECT department, position FROM users WHERE username = :u LIMIT 1");
$row->execute([':u' => $name]);
$r = $row->fetch(PDO::FETCH_ASSOC);
echo $r ? $r['department'] . '/' . $r['position'] : '';
