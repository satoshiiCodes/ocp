<?php
/**
 * actions/item_names-actions.php
 *
 * Every action for item_names.php lives in this one file.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. The
 * AJAX actions post straight to this file, so the bootstrap below opens the
 * session and the database handle when they are not already open.
 *
 * Actions handled
 *   add     (POST item_form_action=add)   form post, redirects back
 *   view    (POST view_item_id)           the id is left in $_POST, the page
 *                                         renders the modal from it
 *   update  (POST action=update)          AJAX, answers JSON
 *   delete  (POST action=delete)          AJAX, answers JSON
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_ITEM_NAMES_ACTIONS_RAN')) {
    return;
}
define('OCP_ITEM_NAMES_ACTIONS_RAN', true);

// Works both ways: pulled in by the page, or posted to directly by the page's JS.
$ocp_dir = __DIR__;
for ($ocp_i = 0; $ocp_i < 4 && !is_file($ocp_dir . '/config/db_config.php'); $ocp_i++) {
    $ocp_parent = dirname($ocp_dir);
    if ($ocp_parent === $ocp_dir) {
        break;
    }
    $ocp_dir = $ocp_parent;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($pdo)) {
    require_once $ocp_dir . '/config/db_config.php';
}
unset($ocp_dir, $ocp_i, $ocp_parent);

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Which AJAX action this is. The forms send it as ocp_action: a control named "action"
// would shadow the form's own action property, which is what the page's script reads to
// find this file. The plain name is still accepted for a caller that posts it directly.
$ocp_action = $_POST['ocp_action'] ?? $_POST['action'] ?? '';

/** Answers one AJAX action and stops. */
$ocp_json = static function (array $payload): void {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
};

// ---------------------------------------------------------------- update (AJAX)
if ($ocp_action === 'update') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $item_code = trim($_POST['item_code'] ?? '');
    $item_name = trim($_POST['item_name'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
    $unit_of_measure = trim($_POST['unit_of_measure'] ?? '');
    $min_stock_level = isset($_POST['min_stock_level']) ? (int) $_POST['min_stock_level'] : 0;

    if ($id <= 0 || $item_code === '' || $item_name === '' || empty($category_id) || $unit_of_measure === '') {
        $ocp_json(['success' => false, 'message' => 'All fields are required.']);
    }
    if ($min_stock_level < 0) {
        $ocp_json(['success' => false, 'message' => 'Minimum stock level cannot be negative.']);
    }

    try {
        // The item code must stay unique across the other records
        $checkStmt = $pdo->prepare("SELECT id FROM item_names WHERE item_code = :item_code AND id != :id");
        $checkStmt->bindParam(':item_code', $item_code);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            $ocp_json(['success' => false, 'message' => 'Item code already exists. Please use a different code.']);
        }

        $updateStmt = $pdo->prepare("UPDATE item_names SET item_code = :item_code, item_name = :item_name, category_id = :category_id, unit_of_measure = :unit_of_measure, min_stock_level = :min_stock_level WHERE id = :id");
        $updateStmt->bindParam(':item_code', $item_code);
        $updateStmt->bindParam(':item_name', $item_name);
        $updateStmt->bindParam(':category_id', $category_id);
        $updateStmt->bindParam(':unit_of_measure', $unit_of_measure);
        $updateStmt->bindParam(':min_stock_level', $min_stock_level);
        $updateStmt->bindParam(':id', $id);

        $ocp_json($updateStmt->execute()
            ? ['success' => true, 'message' => 'Item updated successfully!']
            : ['success' => false, 'message' => 'Error updating item. Please try again.']);
    } catch (PDOException $e) {
        $ocp_json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ---------------------------------------------------------------- delete (AJAX)
if ($ocp_action === 'delete') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($id <= 0) {
        $ocp_json(['success' => false, 'message' => 'Invalid ID']);
    }

    try {
        $deleteStmt = $pdo->prepare("DELETE FROM item_names WHERE id = :id");
        $deleteStmt->bindParam(':id', $id);

        $ocp_json($deleteStmt->execute()
            ? ['success' => true, 'message' => 'Item deleted successfully!']
            : ['success' => false, 'message' => 'Error deleting item. Please try again.']);
    } catch (PDOException $e) {
        $ocp_json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// --- view: hand the id back to the page, which renders the modal -------------
if (isset($_POST['view_item_id'])) {
    return;
}

// --------------------------------------------------------------------- add
if (($_POST['item_form_action'] ?? '') === 'add') {
    $item_code = trim($_POST['item_code'] ?? '');
    $item_name = trim($_POST['item_name'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
    $unit_of_measure = trim($_POST['unit_of_measure'] ?? '');
    $min_stock_level = isset($_POST['min_stock_level']) ? (int) $_POST['min_stock_level'] : 0;

    if ($item_code === '' || $item_name === '' || empty($category_id) || $unit_of_measure === '') {
        $_SESSION['swal_message'] = 'All fields are required.';
        $_SESSION['swal_message_type'] = 'error';
        return;
    }
    if ($min_stock_level < 0) {
        $_SESSION['swal_message'] = 'Minimum stock level cannot be negative.';
        $_SESSION['swal_message_type'] = 'error';
        return;
    }

    try {
        // The item code must not already be taken
        $checkStmt = $pdo->prepare("SELECT id FROM item_names WHERE item_code = :item_code");
        $checkStmt->bindParam(':item_code', $item_code);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            $_SESSION['swal_message'] = 'Item code already exists. Please use a different code.';
            $_SESSION['swal_message_type'] = 'error';
            return;
        }

        $insertStmt = $pdo->prepare("INSERT INTO item_names (item_code, item_name, category_id, unit_of_measure, min_stock_level) VALUES (:item_code, :item_name, :category_id, :unit_of_measure, :min_stock_level)");
        $insertStmt->bindParam(':item_code', $item_code);
        $insertStmt->bindParam(':item_name', $item_name);
        $insertStmt->bindParam(':category_id', $category_id);
        $insertStmt->bindParam(':unit_of_measure', $unit_of_measure);
        $insertStmt->bindParam(':min_stock_level', $min_stock_level);

        if ($insertStmt->execute()) {
            $_SESSION['swal_message'] = 'Item added successfully!';
            $_SESSION['swal_message_type'] = 'success';
            $_POST = array();

            // Refresh so the new item is listed
            header('Location: item_names.php');
            exit();
        }

        $_SESSION['swal_message'] = 'Error adding item. Please try again.';
        $_SESSION['swal_message_type'] = 'error';
    } catch (PDOException $e) {
        $_SESSION['swal_message'] = 'Database error: ' . $e->getMessage();
        $_SESSION['swal_message_type'] = 'error';
    }
    return;
}
