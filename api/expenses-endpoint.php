<?php
/**
 * api/expenses-endpoint.php
 *
 * Every read for expenses.php lives in this one file: the expense types, the filtered
 * expense listing and its total, the running cash balance, the employees for the
 * form's dropdown, the Admin/CEO users for its approval dropdown, and the signed-in
 * user's display name.
 *
 * The page pulls this in instead of querying the database itself, so all of the page's
 * fetching is in one place. It runs in the page's scope and returns an array of the
 * variables the markup needs; the page unpacks that array. Nothing is printed here, so
 * this file cannot disturb the page's output.
 *
 * The filters come from the query string, exactly as before. It also requires the
 * shared helper include, because getCurrentCashBalance() is one of the reads.
 *
 * Returns
 *   expense_types_from_db
 *   expenses
 *   total_amount          excluding anything awaiting approval
 *   pending_amount        the part left out of total_amount
 *   current_cash_balance
 *   employees
 *   ceo_users
 *   my_sign_step          which approval step the signed-in user may sign, or null
 */

$ocp_endpoint = [
    'expense_types_from_db' => [],
    'expenses' => [],
    'total_amount' => 0,
    'pending_amount' => 0,
    'current_cash_balance' => 0,
    'employees' => [],
    'ceo_users' => [],
    'my_sign_step' => null,
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// The helper the page and the actions file both call.
require_once __DIR__ . '/../includes/expenses-functions.php';

// Get all expense types from database
try {
    $expenseTypesStmt = $pdo->prepare("SELECT id, expense_name, description FROM expenses_type ORDER BY expense_name");
    $expenseTypesStmt->execute();
    $ocp_endpoint['expense_types_from_db'] = $expenseTypesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $ocp_endpoint['expense_types_from_db'] = [];
}

// Get all expenses from database
try {
    // Get filter parameters
    $filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
    $filter_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $filter_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    
    $query = "
        SELECT e.*, 
               u.firstname as user_firstname, u.lastname as user_lastname,
               emp.firstname as emp_firstname, emp.lastname as emp_lastname,
               ceo.firstname as ceo_firstname, ceo.middlename as ceo_middlename, ceo.lastname as ceo_lastname, ceo.suffix as ceo_suffix,
               et.expense_name as expense_type_name, et.description as expense_type_description
        FROM expenses e 
        LEFT JOIN users u ON e.created_by = u.id 
        LEFT JOIN employee emp ON e.employee_id = emp.id
        LEFT JOIN users ceo ON e.ceo_id = ceo.id
        LEFT JOIN expenses_type et ON e.expense_type_id = et.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($filter_type)) {
        $query .= " AND e.expense_type_id = :expense_type_id";
        $params[':expense_type_id'] = $filter_type;
    }
    
    if (!empty($filter_start_date)) {
        $query .= " AND DATE(e.expense_date) >= :start_date";
        $params[':start_date'] = $filter_start_date;
    }
    
    if (!empty($filter_end_date)) {
        $query .= " AND DATE(e.expense_date) <= :end_date";
        $params[':end_date'] = $filter_end_date;
    }
    
    $query .= " ORDER BY e.expense_date DESC, e.created_at DESC";
    
    $expensesStmt = $pdo->prepare($query);
    $expensesStmt->execute($params);
    $ocp_endpoint['expenses'] = $expensesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total of the expenses shown.
    //
    // Anything still awaiting approval is left out: an approval-required expense is not settled
    // until it has been signed, so counting it would overstate the period. It still appears in the
    // table, marked, so nothing is hidden.
    $ocp_endpoint['total_amount'] = 0;
    $ocp_endpoint['pending_amount'] = 0;
    foreach ($ocp_endpoint['expenses'] as $expense) {
        if (($expense['approval_status'] ?? 'not_required') === 'pending'
            || ($expense['approval_status'] ?? '') === 'partially_signed') {
            $ocp_endpoint['pending_amount'] += $expense['amount'];
            continue;
        }
        $ocp_endpoint['total_amount'] += $expense['amount'];
    }
    
} catch(PDOException $e) {
    $ocp_endpoint['expenses'] = [];
    $ocp_endpoint['total_amount'] = 0;
    $ocp_endpoint['pending_amount'] = 0;
    $_SESSION['swal_message'] = 'Error fetching expenses: ' . $e->getMessage();
    $_SESSION['swal_message_type'] = 'error';
}

// Get current cash on hand balance
$ocp_endpoint['current_cash_balance'] = getCurrentCashBalance($pdo);

// Get all employees for dropdown
try {
    $employeesStmt = $pdo->prepare("
        SELECT id, firstname, middlename, lastname, suffix 
        FROM employee 
        WHERE status = 'active' 
        ORDER BY lastname, firstname
    ");
    $employeesStmt->execute();
    $ocp_endpoint['employees'] = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $ocp_endpoint['employees'] = [];
}

// Get all users with account type 'Admin' and position 'CEO' for CEO dropdown
try {
    $ceoUsersStmt = $pdo->prepare("
        SELECT id, firstname, middlename, lastname, suffix 
        FROM users 
        WHERE accounttype = 'Admin' AND position = 'CEO'
        ORDER BY lastname, firstname
    ");
    $ceoUsersStmt->execute();
    $ocp_endpoint['ceo_users'] = $ceoUsersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $ocp_endpoint['ceo_users'] = [];
}

// --- the signed-in user, whose name the side menu prints ---------------------
$ocp_user_id = $_SESSION['user_id'] ?? null;
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix, accounttype, department, position FROM users WHERE id = :id");
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

// Which approval step, if any, the signed-in user is entitled to sign.
//
// The same rules the approve handler enforces, evaluated here so the page can decide whether to
// offer the button at all. The handler re-checks them; this only drives the interface, so a
// crafted request still cannot sign a step the rules do not allow.
$ocp_endpoint['my_sign_step'] = null;
if ($user && $user['accounttype'] === 'Admin' && $user['department'] === 'Admin') {
    if ($user['position'] === 'Accounting') {
        $ocp_endpoint['my_sign_step'] = 'reviewed';
    } elseif ($user['position'] === 'CEO') {
        // A CEO signs whichever of the two CEO steps is next: Prepared first, then Acknowledged.
        $ocp_endpoint['my_sign_step'] = 'prepared';
        $ocp_endpoint['my_sign_step_secondary'] = 'acknowledged';
    }
}

unset($ocp_user_id, $user, $display_name);

return $ocp_endpoint;
