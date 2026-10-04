<?php
/**
 * api/warehouses-endpoint.php
 *
 * Every read for warehouses.php lives in this one file: the warehouse listing and
 * the signed-in user's display name.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * Returns
 *   warehouses    array  every warehouse, newest first
 *   display_name  string the signed-in user's name, for the side menu
 */

$ocp_endpoint = [
    'warehouses' => [],
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// --- the warehouse listing ---------------------------------------------------
try {
    $warehousesStmt = $pdo->prepare("SELECT * FROM warehouses ORDER BY created_at DESC");
    $warehousesStmt->execute();
    $ocp_endpoint['warehouses'] = $warehousesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['swal_data'] = [
        'title' => 'Database Error!',
        'text' => 'Error fetching warehouses: ' . $e->getMessage(),
        'icon' => 'error',
    ];
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
