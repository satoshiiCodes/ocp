<?php
/**
 * api/expenses_type-endpoint.php
 *
 * Every read for expenses_type.php lives in this one file: the table listing and
 * the single-record lookup used by the view modal.
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
 *   expenses       array  every expense type, newest first
 *   view_expense   array  one expense type when the view action asked for it
 */

$ocp_endpoint = [
    'expenses' => [],
    'view_expense' => null,
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// --- the table listing -------------------------------------------------------
try {
    $expensesStmt = $pdo->prepare("SELECT * FROM expenses_type ORDER BY created_at DESC");
    $expensesStmt->execute();
    $ocp_endpoint['expenses'] = $expensesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['swal_message'] = 'Error fetching expense types: ' . $e->getMessage();
    $_SESSION['swal_message_type'] = 'error';
}

// --- one record, for the view modal -----------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['view_expense_id'])) {
    try {
        $viewStmt = $pdo->prepare("SELECT * FROM expenses_type WHERE id = :id");
        $viewStmt->bindParam(':id', $_POST['view_expense_id']);
        $viewStmt->execute();
        $ocp_endpoint['view_expense'] = $viewStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['swal_message'] = 'Error fetching expense details: ' . $e->getMessage();
        $_SESSION['swal_message_type'] = 'error';
    }
}

return $ocp_endpoint;
