<?php
require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/upload_attendance-functions.php';
$path = $argv[1];
try {
    $r = parseExcelFile($path);
    echo "  parsed ok:", PHP_EOL;
    echo "    success: ", var_export($r['success'], true), PHP_EOL;
    echo "    message: ", $r['message'] ?? '', PHP_EOL;
    echo "    rows: ", count($r['data'] ?? []), PHP_EOL;
    if (!empty($r['data'])) { echo "    first: ", json_encode($r['data'][0]), PHP_EOL; }
} catch (Throwable $e) {
    echo "  THREW: ", get_class($e), ': ', $e->getMessage(), PHP_EOL;
}
