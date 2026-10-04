<?php
ini_set('display_errors', '0');
error_reporting(0);
$page = $argv[1]; $sid = $argv[2]; $id = $argv[3];
$wanted = array_slice($argv, 4);
session_id($sid);
$_GET['id'] = $id;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/' . $page;
$_SERVER['PHP_SELF'] = '/' . $page;
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../' . $page;
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/' . $page . '?id=' . $id;
chdir(__DIR__ . '/..');
ob_start();
include __DIR__ . '/../' . $page;
$html = ob_get_clean();
$out = ['__bytes' => strlen($html)];
foreach ($wanted as $w) {
    $p = __DIR__ . '/../assets/js/' . $w;
    if (!is_file($p)) { $out[$w] = null; continue; }
    ob_start(); include $p; $out[$w] = ob_get_clean();
}
echo json_encode($out);
