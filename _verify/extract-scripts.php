<?php
ini_set('display_errors', '0');
error_reporting(0);
$page = $argv[1];
$sid  = $argv[2];
$wanted = array_slice($argv, 3);

session_id($sid);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME']    = '/' . $page;
$_SERVER['PHP_SELF']       = '/' . $page;
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../' . $page;
$_SERVER['HTTP_HOST']      = '127.0.0.1';
$_SERVER['REQUEST_URI']    = '/' . $page;
chdir(__DIR__ . '/..');

ob_start();
include __DIR__ . '/../' . $page;
ob_end_clean();

$out = [];
foreach ($wanted as $w) {
    $body = null;
    $path = __DIR__ . '/../assets/js/' . $w;
    if (is_file($path)) {
        // Same process, same scope the page had: this is the embedded rendering.
        ob_start();
        include $path;
        $body = ob_get_clean();
    }
    $out[$w] = $body;
}
echo json_encode($out);
