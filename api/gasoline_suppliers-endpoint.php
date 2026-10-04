<?php
/**
 * api/gasoline_suppliers-endpoint.php
 *
 * Every read for gasoline_suppliers.php lives in this one file: the supplier
 * listing and the signed-in user's display name.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * Returns
 *   suppliers     array  every gasoline supplier, newest first
 *   display_name  string the signed-in user's name, for the side menu
 */

$ocp_endpoint = [
    'suppliers' => [],
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// --- the supplier listing ----------------------------------------------------
try {
    $suppliersStmt = $pdo->prepare("SELECT * FROM gasoline_suppliers ORDER BY created_at DESC");
    $suppliersStmt->execute();
    $ocp_endpoint['suppliers'] = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (empty($swal_message)) {
        $_SESSION['swal_title'] = 'Database Error!';
        $_SESSION['swal_message'] = 'Error fetching gasoline suppliers: ' . $e->getMessage();
        $_SESSION['swal_type'] = 'error';
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
