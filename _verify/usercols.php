<?php
require_once __DIR__ . '/../config/db_config.php';
foreach (['accounttype','status','department','position'] as $c) {
    $s = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME=?");
    $s->execute([$c]);
    printf("  %-12s %s\n", $c, $s->fetchColumn());
}
