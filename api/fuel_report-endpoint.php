<?php
/**
 * api/fuel_report-endpoint.php
 *
 * Every read for fuel_report.php lives in this one file: the gasoline types for
 * the filter dropdown, the filtered PO items, the summary totals, and the
 * signed-in user's display name.
 *
 * The page pulls this in instead of querying the database itself, so all of the
 * page's fetching is in one place. It runs in the page's scope and returns an
 * array of the variables the markup needs; the page unpacks that array. Nothing
 * is printed here, so this file cannot disturb the page's output.
 *
 * The date and type filters come from the query string, exactly as before, and the
 * summary totals are derived the same way the page derived them.
 *
 * Returns
 *   gasoline_types        array  the distinct types, for the filter dropdown
 *   po_items              array  the filtered PO items
 *   total_quantity        float  litres across every filtered item
 *   total_amount          float  cost across every filtered item
 *   gasoline_type_totals  array  quantity and amount per gasoline type
 *   error_message         string a fetch failure, so the page can show it
 *   display_name          string the signed-in user's name, for the side menu
 */

$ocp_endpoint = [
    'gasoline_types' => [],
    'po_items' => [],
    'total_quantity' => 0,
    'total_amount' => 0,
    'gasoline_type_totals' => [],
    'error_message' => '',
];

// Standalone guard: see the note above.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

// --- the filters, from the query string --------------------------------------
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$gasoline_type = $_GET['gasoline_type'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($start_date)) {
    $where_conditions[] = "pi.date_issued >= :start_date";
    $params[':start_date'] = $start_date;
}

if (!empty($end_date)) {
    $where_conditions[] = "pi.date_issued <= :end_date";
    $params[':end_date'] = $end_date;
}

if (!empty($gasoline_type)) {
    $where_conditions[] = "pi.gasoline_type = :gasoline_type";
    $params[':gasoline_type'] = $gasoline_type;
}

$where_clause = !empty($where_conditions) ? " WHERE " . implode(" AND ", $where_conditions) : "";

// --- the types the filter offers ---------------------------------------------
try {
    $typeStmt = $pdo->prepare("SELECT DISTINCT gasoline_type FROM gasoline_po_items ORDER BY gasoline_type");
    $typeStmt->execute();
    $ocp_endpoint['gasoline_types'] = $typeStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // The dropdown is simply empty; the original showed nothing either.
}

// --- the filtered items, and the totals the page derives from them -----------
try {
    $itemsQuery = "
        SELECT 
            pi.*,
            po.po_number,
            s.supplier_name,
            v.vehicle_name,
            v.plate_number,
            e.equipment_name,
            CONCAT(emp.firstname, ' ', emp.lastname) as driver_name
        FROM gasoline_po_items pi
        LEFT JOIN gasoline_purchase_orders po ON pi.po_id = po.id
        LEFT JOIN gasoline_suppliers s ON pi.supplier_id = s.id
        LEFT JOIN vehicles v ON pi.vehicle_id = v.id
        LEFT JOIN equipment e ON pi.equipment_id = e.id
        LEFT JOIN employee emp ON pi.driver_operator_id = emp.id
        $where_clause
        ORDER BY pi.date_issued DESC, pi.id DESC
    ";

    $itemsStmt = $pdo->prepare($itemsQuery);

    foreach ($params as $key => $value) {
        $itemsStmt->bindValue($key, $value);
    }

    $itemsStmt->execute();
    $ocp_endpoint['po_items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary statistics, calculated exactly as the page calculated them.
    $total_quantity = 0;
    $total_amount = 0;
    $gasoline_type_totals = [];

    foreach ($ocp_endpoint['po_items'] as $item) {
        $quantity = $item['quantity_liters'];
        $amount = $quantity * ($item['price_per_liter'] ?? 0);
        $total_quantity += $quantity;
        $total_amount += $amount;

        $type = $item['gasoline_type'];
        if (!isset($gasoline_type_totals[$type])) {
            $gasoline_type_totals[$type] = ['quantity' => 0, 'amount' => 0];
        }
        $gasoline_type_totals[$type]['quantity'] += $quantity;
        $gasoline_type_totals[$type]['amount'] += $amount;
    }

    $ocp_endpoint['total_quantity'] = $total_quantity;
    $ocp_endpoint['total_amount'] = $total_amount;
    $ocp_endpoint['gasoline_type_totals'] = $gasoline_type_totals;
    unset($total_quantity, $total_amount, $gasoline_type_totals, $quantity, $amount, $type, $item);
} catch (PDOException $e) {
    $ocp_endpoint['error_message'] = "Error fetching data: " . $e->getMessage();
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
