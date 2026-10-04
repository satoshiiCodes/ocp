<?php
/**
 * api/payroll-endpoint.php
 *
 * Every read for payroll.php lives in this one file: the attendance records under
 * the chosen filters, the employees for the dropdown, the departments the
 * attendance table already uses, and the signed-in user's display name.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * The filters come from whatever the actions file decided, so they are read from
 * the variables already in scope.
 *
 * Returns
 *   attendance_records  array  the filtered attendance rows, newest first
 *   attendance_error    string a listing failure, so the page can show it
 *   employees           array  every employee, for the dropdown
 *   departments         array  the departments present in the attendance table
 *   display_name        string the signed-in user's name, for the side menu
 */

$ocp_endpoint = [
    'attendance_records' => [],
    'attendance_error' => null,
    'employees' => [],
    'departments' => [],
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// Set by the actions file when a filter was applied; empty otherwise.
$ocp_date_from = $selected_date_from ?? '';
$ocp_date_to = $selected_date_to ?? '';
$ocp_department = $selected_department ?? '';

// --- the attendance records, under the chosen filters ------------------------
try {
    $sql = "
        SELECT a.*, 
               e.employee_id as emp_code
        FROM attendance a
        JOIN employee e ON a.employee_id = e.id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($ocp_date_from) && !empty($ocp_date_to)) {
        $sql .= " AND a.attendance_date BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $ocp_date_from;
        $params[':date_to'] = $ocp_date_to;
    }

    if (!empty($ocp_department)) {
        $sql .= " AND a.department = :department";
        $params[':department'] = $ocp_department;
    }

    $sql .= " ORDER BY a.attendance_date DESC, a.employee_name ASC";

    $attendanceStmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $attendanceStmt->bindValue($key, $value);
    }

    $attendanceStmt->execute();
    $ocp_endpoint['attendance_records'] = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ocp_endpoint['attendance_error'] = "Error fetching attendance records: " . $e->getMessage();
}

// --- the employees the form offers -------------------------------------------
try {
    $employeesStmt = $pdo->prepare("SELECT id, employee_id, firstname, middlename, lastname, suffix FROM employee WHERE status = 'active' ORDER BY firstname ASC");
    $employeesStmt->execute();
    $ocp_endpoint['employees'] = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ocp_endpoint['employees'] = [];
}

// --- the departments already present in the attendance table -----------------
try {
    $deptStmt = $pdo->prepare("SELECT DISTINCT department FROM attendance WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
    $deptStmt->execute();
    $ocp_endpoint['departments'] = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ocp_endpoint['departments'] = [];
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
unset($ocp_user_id, $user, $display_name, $ocp_date_from, $ocp_date_to, $ocp_department, $params, $sql);

return $ocp_endpoint;
