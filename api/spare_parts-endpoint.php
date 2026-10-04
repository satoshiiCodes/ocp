<?php
/**
 * api/spare_parts-endpoint.php
 *
 * Every read for spare_parts.php lives in this one file: the parts listing with
 * their categories, the category list for the form's dropdown, and the signed-in
 * user's display name.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * Returns
 *   parts         array  every spare part with its category, newest first
 *   categories    array  every category, for the form's dropdown
 *   display_name  string the signed-in user's name, for the side menu
 *   error_message string a listing failure, so the page can show it
 */

$ocp_endpoint = [
    'parts' => [],
    'categories' => [],
    'error_message' => '',
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// --- the parts listing, with each part's category ----------------------------
try {
    $partsStmt = $pdo->prepare("
        SELECT sp.*, spc.category_name 
        FROM spare_parts sp 
        LEFT JOIN spare_parts_categories spc ON sp.category_id = spc.id 
        ORDER BY sp.created_at DESC
    ");
    $partsStmt->execute();
    $ocp_endpoint['parts'] = $partsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ocp_endpoint['error_message'] = 'Error fetching spare parts: ' . $e->getMessage();
}

// --- the categories the form offers ------------------------------------------
try {
    $categoriesStmt = $pdo->prepare("SELECT * FROM spare_parts_categories ORDER BY category_name");
    $categoriesStmt->execute();
    $ocp_endpoint['categories'] = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // The page never showed a message for this one; the dropdown is simply empty.
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
