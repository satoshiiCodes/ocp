<?php
/**
 * api/employee_registration-endpoint.php
 *
 * Every read for employee_registration.php lives in this one file: the signed-in
 * user's display name for the side menu, and the employee table with each one's
 * current deductions.
 *
 * The page pulls this in instead of querying the database itself, so all of the page's
 * fetching is in one place. It runs in the page's scope and returns an array of the
 * variables the markup needs; the page unpacks that array. Nothing is printed here, so
 * this file cannot disturb the page's output.
 *
 * Note that the handler's own values reach the template through the page's scope, not
 * through this array: this runs after the actions file and only fills what the markup
 * reads for display.
 *
 * Returns
 *   employees
 *   employee_error
 */

// employee_error is deliberately absent: the markup chooses between an error alert,
// an empty notice and the table with isset($employee_error), so the key must only be
// present when a read actually failed. Seeding it with '' would make the page show the
// error alert instead of the table on every load.
$ocp_endpoint = [
    'employees' => [],
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// Get user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Format the display name
$ocp_endpoint['display_name'] = $user['firstname'];

if (!empty($user['middlename'])) {
    $ocp_endpoint['display_name'] .= ' ' . substr($user['middlename'], 0, 1) . '.';
}

$ocp_endpoint['display_name'] .= ' ' . $user['lastname'];

if (!empty($user['suffix'])) {
    $ocp_endpoint['display_name'] .= ' ' . $user['suffix'];
}

// Fetch all employees for the table with their current deductions
try {
    $employeesStmt = $pdo->prepare("
        SELECT e.*, 
            (SELECT amount FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'cash_advance' LIMIT 1) as cash_advance_amount,
            (SELECT from_date FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'cash_advance' LIMIT 1) as cash_advance_from,
            (SELECT to_date FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'cash_advance' LIMIT 1) as cash_advance_to,
            (SELECT amount FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'sss' LIMIT 1) as sss_amount,
            (SELECT effectivity_date FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'sss' LIMIT 1) as sss_effectivity,
            (SELECT amount FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'pag_ibig' LIMIT 1) as pagibig_amount,
            (SELECT effectivity_date FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'pag_ibig' LIMIT 1) as pagibig_effectivity,
            (SELECT amount FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'philhealth' LIMIT 1) as philhealth_amount,
            (SELECT effectivity_date FROM employee_deductions WHERE employee_id = e.id AND deduction_type = 'philhealth' LIMIT 1) as philhealth_effectivity
        FROM employee e 
        ORDER BY e.created_at DESC
    ");
    $employeesStmt->execute();
    $ocp_endpoint['employees'] = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $ocp_endpoint['employees'] = [];
    // only now does the key appear, which is what the markup tests for
    $ocp_endpoint['employee_error'] = "Error fetching employees: " . $e->getMessage();
}

return $ocp_endpoint;
