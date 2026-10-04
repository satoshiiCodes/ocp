<?php
/**
 * actions/expenses_type-actions.php
 *
 * Every action for expenses_type.php lives in this one file.
 *
 * The page pulls this file in at the top, so it runs in the page's own scope:
 * the database handle, the session flash messages and $_POST all behave exactly
 * as they did when this code sat inline in the page.
 *
 * Actions handled
 *   add     (POST expense_form_action=add)      form post, redirects back
 *   view    (POST view_expense_id)              the id is left in $_POST and the
 *                                               page renders the modal from it
 *   update  (POST action=update)                AJAX, answers JSON
 *   delete  (POST action=delete)                AJAX, answers JSON
 *
 * The two AJAX actions keep answering JSON because the page's JavaScript reads
 * the response; the form actions redirect, exactly as they did before.
 */

// Only run while the page is handling a POST. The constant stops a re-render from
// running the handler twice: the page includes this file once, and the flash
// message it sets is then read by the page below.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_EXPENSES_TYPE_ACTIONS_RAN')) {
    return;
}
define('OCP_EXPENSES_TYPE_ACTIONS_RAN', true);

// The AJAX actions post straight to this file, where the page's session and
// database handle are not open yet. Pulling them in here keeps this file working
// both ways: called directly (AJAX) and pulled in by the page (form posts).
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
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
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
    $expense_name = trim($_POST['expense_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    // The form's hidden input carries this, so an untouched dialog posts the record's current
    // value rather than dropping the column to its default.
    $approval_required = isset($_POST['approval_required']) && (int) $_POST['approval_required'] === 1 ? 1 : 0;

    if ($id <= 0 || $expense_name === '') {
        $ocp_json(['success' => false, 'message' => 'Invalid input']);
    }

    try {
        // The name must stay unique across the other records
        $checkStmt = $pdo->prepare("SELECT id FROM expenses_type WHERE expense_name = :expense_name AND id != :id");
        $checkStmt->bindParam(':expense_name', $expense_name);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            $ocp_json(['success' => false, 'message' => 'Expense name already exists. Please use a different name.']);
        }

        $updateStmt = $pdo->prepare("UPDATE expenses_type SET expense_name = :expense_name, description = :description, approval_required = :approval_required, updated_at = NOW() WHERE id = :id");
        $updateStmt->bindParam(':expense_name', $expense_name);
        $updateStmt->bindParam(':description', $description);
        $updateStmt->bindParam(':approval_required', $approval_required, PDO::PARAM_INT);
        $updateStmt->bindParam(':id', $id);

        $ocp_json($updateStmt->execute()
            ? ['success' => true, 'message' => 'Expense type updated successfully!']
            : ['success' => false, 'message' => 'Error updating expense type.']);
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
        $deleteStmt = $pdo->prepare("DELETE FROM expenses_type WHERE id = :id");
        $deleteStmt->bindParam(':id', $id);

        $ocp_json($deleteStmt->execute()
            ? ['success' => true, 'message' => 'Expense type deleted successfully!']
            : ['success' => false, 'message' => 'Error deleting expense type.']);
    } catch (PDOException $e) {
        $ocp_json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// --- view: hand the id back to the page, which renders the modal -------------
if (isset($_POST['view_expense_id'])) {
    return;
}

// --------------------------------------------------------------------- add
if (($_POST['expense_form_action'] ?? '') === 'add') {
    $expense_name = trim($_POST['expense_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $approval_required = isset($_POST['approval_required']) && (int) $_POST['approval_required'] === 1 ? 1 : 0;

    if ($expense_name === '') {
        $_SESSION['swal_message'] = 'Expense name is required.';
        $_SESSION['swal_message_type'] = 'error';
        return;
    }

    try {
        // The name must not already be taken
        $checkStmt = $pdo->prepare("SELECT id FROM expenses_type WHERE expense_name = :expense_name");
        $checkStmt->bindParam(':expense_name', $expense_name);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            $_SESSION['swal_message'] = 'Expense name already exists. Please use a different name.';
            $_SESSION['swal_message_type'] = 'error';
            return;
        }

        $insertStmt = $pdo->prepare("INSERT INTO expenses_type (expense_name, description, approval_required) VALUES (:expense_name, :description, :approval_required)");
        $insertStmt->bindParam(':expense_name', $expense_name);
        $insertStmt->bindParam(':description', $description);
        $insertStmt->bindParam(':approval_required', $approval_required, PDO::PARAM_INT);

        if ($insertStmt->execute()) {
            $_SESSION['swal_message'] = 'Expense type added successfully!';
            $_SESSION['swal_message_type'] = 'success';
            $_POST = array();

            // Refresh so the new expense type is listed
            header('Location: expenses_type.php');
            exit();
        }

        $_SESSION['swal_message'] = 'Error adding expense type. Please try again.';
        $_SESSION['swal_message_type'] = 'error';
    } catch (PDOException $e) {
        $_SESSION['swal_message'] = 'Database error: ' . $e->getMessage();
        $_SESSION['swal_message_type'] = 'error';
    }
    return;
}
