<?php
/**
 * api/gasoline_tank-endpoint.php
 *
 * Every read for gasoline_tank.php lives in this one file: the tank listing and
 * the signed-in user's display name.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * Returns
 *   tanks         array  every gasoline tank, newest first
 *   display_name  string the signed-in user's name, for the side menu
 */

$ocp_endpoint = [
    'tanks' => [],
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// --- the tank listing --------------------------------------------------------
try {
    $tanksStmt = $pdo->prepare("SELECT * FROM gasoline_tanks ORDER BY created_at DESC");
    $tanksStmt->execute();
    $ocp_endpoint['tanks'] = $tanksStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (empty($swal_data)) {
        $_SESSION['swal_data'] = [
            'icon' => 'error',
            'title' => 'Database Error!',
            'text' => 'Error fetching gasoline tanks: ' . $e->getMessage(),
        ];
    }
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

return $ocp_endpoint;
