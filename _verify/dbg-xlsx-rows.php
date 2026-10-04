<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$path = $argv[1];
$ss = IOFactory::load($path);
$ws = $ss->getActiveSheet();
$rows = $ws->toArray();
echo "  rows: ", count($rows), PHP_EOL;
foreach ($rows as $i => $r) {
    echo "   [$i] (", count($r), ") ", json_encode(array_slice($r, 0, 6)), PHP_EOL;
}
