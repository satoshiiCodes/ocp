<?php
// Creates a logged-in session for a user, so a page can be exercised as that user.
//
//   php _verify/make-session.php <user_id>      -> prints the session id
//
// It writes a session file in the same shape the app's own login writes, using the
// session save path PHP is actually configured with.
$userId = (int) ($argv[1] ?? 0);
if ($userId <= 0) {
    fwrite(STDERR, "usage: make-session.php <user_id>\n");
    exit(2);
}

require_once __DIR__ . '/../config/db_config.php';

$stmt = $pdo->prepare("SELECT id, firstname, middlename, lastname, suffix, accounttype FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    fwrite(STDERR, "no such user\n");
    exit(1);
}

$name = trim($user['firstname'] . ' ' . $user['lastname']);

$sid = bin2hex(random_bytes(13));
$data = 'user_id|i:' . (int) $user['id'] . ';'
    . 'fullname|s:' . strlen($name) . ':"' . $name . '";'
    . 'account_type|s:' . strlen($user['accounttype']) . ':"' . $user['accounttype'] . '";';

$savePath = session_save_path();
if ($savePath === '' || strpos($savePath, ';') !== false) {
    $savePath = sys_get_temp_dir();
}
$file = rtrim($savePath, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sid;
if (file_put_contents($file, $data) === false) {
    fwrite(STDERR, "could not write $file\n");
    exit(1);
}

echo $sid;
