<?php
/**
 * api/registration-endpoint.php
 *
 * Every read for registration.php lives in this one file.
 *
 * The page is a form: the user list, and the single user its edit modal shows, are both
 * fetched by the page's own script, which posts to actions/registration-actions.php. So
 * the one read the page itself performs is the signed-in user's name, which the side
 * menu prints below. It is here rather than inline in the page so that registration.php
 * follows the same layout as every other page - one actions file, one endpoint file -
 * and so a read added later has an obvious home.
 *
 * Returns
 *   display_name  string  the signed-in user's name, for the side menu
 */

$ocp_endpoint = [
];

// Standalone guard: the read runs in the page's scope, where the connection is already
// open. Requested on its own there is none, so it answers with the empty result.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

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
