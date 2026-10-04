<?php
/**
 * api/item_names-endpoint.php
 *
 * Every read for item_names.php lives in this one file: the item listing with
 * its category, the category list for the dropdown, and the single-record lookup
 * used by the view modal.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * Requested on its own, with no page behind it, there is no connection and no
 * session: the guard below then returns the empty result instead of erroring.
 *
 * Returns
 *   items        array  every item with its category name, newest first
 *   categories   array  every category, for the add/edit dropdowns
 *   view_item    array  one item when the view action asked for it
 */

$ocp_endpoint = [
    'items' => [],
    'categories' => [],
    'view_item' => null,
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// --- every item, with its category -------------------------------------------
try {
    $itemsStmt = $pdo->prepare("
        SELECT i.*, c.category_name, c.id as category_id 
        FROM item_names i 
        LEFT JOIN items_categories c ON i.category_id = c.id 
        ORDER BY i.created_at DESC
    ");
    $itemsStmt->execute();
    $ocp_endpoint['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['swal_message'] = 'Error fetching items: ' . $e->getMessage();
    $_SESSION['swal_message_type'] = 'error';
}

// --- every category, for the dropdowns ---------------------------------------
try {
    $categoriesStmt = $pdo->prepare("SELECT * FROM items_categories ORDER BY category_name ASC");
    $categoriesStmt->execute();
    $ocp_endpoint['categories'] = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['swal_message'] = 'Error fetching categories: ' . $e->getMessage();
    $_SESSION['swal_message_type'] = 'error';
}

// --- one item, for the view modal --------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['view_item_id'])) {
    try {
        $viewStmt = $pdo->prepare("
            SELECT i.*, c.category_name 
            FROM item_names i 
            LEFT JOIN items_categories c ON i.category_id = c.id 
            WHERE i.id = :id
        ");
        $viewStmt->bindParam(':id', $_POST['view_item_id']);
        $viewStmt->execute();
        $ocp_endpoint['view_item'] = $viewStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['swal_message'] = 'Error fetching item details: ' . $e->getMessage();
        $_SESSION['swal_message_type'] = 'error';
    }
}

return $ocp_endpoint;
