<?php
/**
 * api/backup_sql-endpoint.php
 *
 * Every read for backup_sql.php lives in this one file: the signed-in user's
 * display name, and the stored backup schedule.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * Returns
 *   display_name      string the signed-in user's name, for the side menu
 *   current_schedule  array  the stored auto-backup schedule, or null
 */

$ocp_endpoint = [
    'current_schedule' => null,
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// --- the signed-in user, whose name the side menu prints ---------------------
$ocp_user_id = $_SESSION['user_id'] ?? null;
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
$stmt->bindParam(':id', $ocp_user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $display_name = $user['firstname'];
    if (!empty($user['middlename'])) {
        $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
    }
    $display_name .= ' ' . $user['lastname'];
    if (!empty($user['suffix'])) {
        $display_name .= ' ' . $user['suffix'];
    }
    $ocp_endpoint['display_name'] = $display_name;
}
unset($ocp_user_id, $user, $display_name);

// --- the stored auto-backup schedule -----------------------------------------
if (file_exists('backup_schedule.json')) {
    $ocp_endpoint['current_schedule'] = json_decode(file_get_contents('backup_schedule.json'), true);
}

return $ocp_endpoint;
