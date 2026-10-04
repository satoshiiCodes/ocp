<?php
// Creates throw-away PHP session files so the runtime check can reach pages
// behind the login guard. Emits the session ids as JSON.
require_once __DIR__ . '/../config/db_config.php';
$savePath = ini_get('session.save_path');
$users = $pdo->query("SELECT id, firstname, lastname, accounttype, department, position FROM users ORDER BY id LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
$out = [];
foreach ($users as $i => $u) {
    $sid = 'ocpverify' . str_pad((string) $i, 16, '0', STR_PAD_LEFT);
    $name = $u['firstname'] . ' ' . $u['lastname'];
    $data = 'user_id|i:' . $u['id'] . ';'
        . 'fullname|s:' . strlen($name) . ':"' . $name . '";'
        . 'account_type|s:' . strlen($u['accounttype']) . ':"' . $u['accounttype'] . '";';
    file_put_contents($savePath . '/sess_' . $sid, $data);
    $out[] = ['sid' => $sid, 'id' => $u['id'], 'dept' => $u['department'], 'pos' => $u['position']];
}
echo json_encode($out);
