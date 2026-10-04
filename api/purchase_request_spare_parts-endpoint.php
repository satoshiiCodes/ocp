<?php
/**
 * api/purchase_request_spare_parts-endpoint.php
 *
 * Every read for purchase_request_spare_parts.php lives in this one file: the purchase
 * request listing with its related records, the part lists for each request type, the
 * suppliers, vehicles, equipment and employee dropdowns, the status counts and the
 * document total.
 *
 * The page pulls this in for its listing: it runs in the page's scope and returns an
 * array of the variables the markup needs, which the page unpacks. The single-record
 * lookup is a separate HTTP request from the page's own script, so it is detected here
 * by its "?id=" parameter and answers JSON instead - it lived in
 * api/get_spare_parts_pr_details.php, which this file replaces.
 *
 * Requires the shared helper include, because formatEmployeeName() builds the
 * dropdowns' display names.
 *
 * Returns
 *   purchase_requests
 *   all_parts
 *   issue_parts
 *   issue_materials
 *   suppliers
 *   vehicles
 *   equipment
 *   employees
 *   formatted_employees
 *   mechanics
 *   formatted_mechanics
 *   drivers
 *   formatted_drivers
 *   status_counts
 *   totalCount
 *   total_requests
 *   swal_data
 */

// ---------------------------------------------------------------------------
// The single-record lookup the page's JavaScript performs: ?id=<pr id>.
// Lifted from api/get_spare_parts_pr_details.php, which this file replaces.
// ---------------------------------------------------------------------------
if (isset($_GET['id'])) {
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

    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}


$pr_id = $_GET['id'] ?? 0;

try {
    // Get PR details
    $prStmt = $pdo->prepare("
        SELECT pr.*, 
               u.firstname, u.middlename, u.lastname, u.suffix,
               s.supplier_name,
               v.vehicle_name, v.plate_number,
               e.equipment_name,
               emp.firstname as emp_firstname, emp.middlename as emp_middlename, 
               emp.lastname as emp_lastname, emp.suffix as emp_suffix,
               emp.position as emp_position,
               d.firstname as driver_firstname, d.middlename as driver_middlename, 
               d.lastname as driver_lastname, d.suffix as driver_suffix,
               d.position as driver_position,
               tech.firstname as tech_firstname, tech.middlename as tech_middlename,
               tech.lastname as tech_lastname, tech.suffix as tech_suffix
        FROM spare_parts_pr pr
        LEFT JOIN users u ON pr.requested_by = u.id
        LEFT JOIN spare_parts_suppliers s ON pr.supplier_id = s.id
        LEFT JOIN vehicles v ON pr.vehicle_id = v.id
        LEFT JOIN equipment e ON pr.equipment_id = e.id
        LEFT JOIN employee emp ON pr.employee_id = emp.id
        LEFT JOIN employee d ON pr.driver_id = d.id
        LEFT JOIN employee tech ON pr.technician = tech.id
        WHERE pr.id = ?
    ");
    $prStmt->execute([$pr_id]);
    $pr = $prStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pr) {
        echo json_encode(['success' => false, 'message' => 'Purchase request not found']);
        exit();
    }
    
    // Get PR items
    $itemsStmt = $pdo->prepare("
        SELECT pri.*, 
               sp.part_number, sp.part_name, spc.category_name
        FROM spare_parts_pr_items pri
        JOIN spare_parts sp ON pri.part_id = sp.id
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        WHERE pri.pr_id = ?
        ORDER BY pri.id
    ");
    $itemsStmt->execute([$pr_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get status history
    $historyStmt = $pdo->prepare("
        SELECT h.*, 
               u.firstname, u.middlename, u.lastname, u.suffix,
               CONCAT(u.firstname, ' ', 
                      IF(u.middlename IS NOT NULL, CONCAT(LEFT(u.middlename, 1), '. '), ''), 
                      u.lastname, 
                      IF(u.suffix IS NOT NULL, CONCAT(' ', u.suffix), '')
               ) as changer_name
        FROM spare_parts_pr_status_history h
        LEFT JOIN users u ON h.changed_by = u.id
        WHERE h.pr_id = ?
        ORDER BY h.created_at DESC
    ");
    $historyStmt->execute([$pr_id]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get PO number if this is a Stock Purchase Request
    $po_number = null;
    if ($pr['request_type'] === 'stock') {
        $poStmt = $pdo->prepare("SELECT po_number FROM spare_part_po WHERE pr_id = ? LIMIT 1");
        $poStmt->execute([$pr_id]);
        $po = $poStmt->fetch(PDO::FETCH_ASSOC);
        if ($po) {
            $po_number = $po['po_number'];
        }
    }
    
    // Get Job Order number if this is an Issue Parts Purchase Request
    $job_order_number = null;
    if ($pr['request_type'] === 'issue') {
        $joStmt = $pdo->prepare("SELECT job_order_number FROM spare_parts_job_orders WHERE pr_id = ? LIMIT 1");
        $joStmt->execute([$pr_id]);
        $jo = $joStmt->fetch(PDO::FETCH_ASSOC);
        if ($jo) {
            $job_order_number = $jo['job_order_number'];
        }
    }
    
    // Get Withdrawal Slip number if this is an Issue Materials Withdrawal Slip
    $withdrawal_slip_number = null;
    if ($pr['request_type'] === 'issue_materials') {
        $wsStmt = $pdo->prepare("SELECT withdrawal_slip_number FROM spare_parts_withdrawal_slips WHERE pr_id = ? LIMIT 1");
        $wsStmt->execute([$pr_id]);
        $ws = $wsStmt->fetch(PDO::FETCH_ASSOC);
        if ($ws) {
            $withdrawal_slip_number = $ws['withdrawal_slip_number'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'pr' => $pr,
        'items' => $items,
        'history' => $history,
        'po_number' => $po_number,
        'job_order_number' => $job_order_number,
        'withdrawal_slip_number' => $withdrawal_slip_number
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

    exit();
}

$ocp_endpoint = [
    'purchase_requests' => [],
    'all_parts' => [],
    'issue_parts' => [],
    'issue_materials' => [],
    'suppliers' => [],
    'vehicles' => [],
    'equipment' => [],
    'employees' => [],
    'formatted_employees' => [],
    'mechanics' => [],
    'formatted_mechanics' => [],
    'drivers' => [],
    'formatted_drivers' => [],
    'status_counts' => [],
    'totalCount' => [],
    'total_requests' => [],
    // Seeded from the session read the page performs, so a message it carries into
    // this load is not wiped out by the endpoint running after it.
    'swal_data' => $swal_data ?? [],
];

// Standalone guard: the read block runs in the page's scope, where the connection is
// already open. Requested on its own there is none, so it answers with the empty result.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

require_once __DIR__ . '/../includes/purchase_request_spare_parts-functions.php';

// Fetch data
try {
    // Get purchase requests with supplier information
    $prStmt = $pdo->prepare("
        SELECT pr.*, 
               u.firstname, u.middlename, u.lastname, u.suffix,
               s.supplier_name,
               v.vehicle_name, v.plate_number,
               e.equipment_name,
               emp.firstname as emp_firstname, emp.middlename as emp_middlename, 
               emp.lastname as emp_lastname, emp.suffix as emp_suffix,
               emp.position as emp_position,
               d.firstname as driver_firstname, d.middlename as driver_middlename, 
               d.lastname as driver_lastname, d.suffix as driver_suffix,
               d.position as driver_position
        FROM spare_parts_pr pr
        LEFT JOIN users u ON pr.requested_by = u.id
        LEFT JOIN spare_parts_suppliers s ON pr.supplier_id = s.id
        LEFT JOIN vehicles v ON pr.vehicle_id = v.id
        LEFT JOIN equipment e ON pr.equipment_id = e.id
        LEFT JOIN employee emp ON pr.employee_id = emp.id
        LEFT JOIN employee d ON pr.driver_id = d.id
        ORDER BY pr.created_at DESC
    ");
    $prStmt->execute();
    $ocp_endpoint['purchase_requests'] = $prStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get ALL spare parts for dropdown (with current stock) - for Stock Purchase Request
    $allPartsStmt = $pdo->prepare("
        SELECT sp.id, sp.part_number, sp.part_name, spc.category_name,
               COALESCE(spi.quantity, 0) as current_stock,
               COALESCE(spi.price_per_unit, 0) as current_price,
               COALESCE(spml.min_stock, 0) as min_stock
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_inventory spi ON sp.id = spi.part_id
        LEFT JOIN spare_parts_min_levels spml ON sp.id = spml.part_id
        ORDER BY sp.part_name
    ");
    $allPartsStmt->execute();
    $ocp_endpoint['all_parts'] = $allPartsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get spare parts EXCLUDING 'Materials' category - for Issue Parts Purchase Request
    $issuePartsStmt = $pdo->prepare("
        SELECT sp.id, sp.part_number, sp.part_name, spc.category_name,
               COALESCE(spi.quantity, 0) as current_stock,
               COALESCE(spi.price_per_unit, 0) as current_price,
               COALESCE(spml.min_stock, 0) as min_stock
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_inventory spi ON sp.id = spi.part_id
        LEFT JOIN spare_parts_min_levels spml ON sp.id = spml.part_id
        WHERE spc.category_name != 'Materials'
        ORDER BY sp.part_name
    ");
    $issuePartsStmt->execute();
    $ocp_endpoint['issue_parts'] = $issuePartsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get spare parts ONLY 'Materials' category - for Issue Materials Purchase Request
    $issueMaterialsStmt = $pdo->prepare("
        SELECT sp.id, sp.part_number, sp.part_name, spc.category_name,
               COALESCE(spi.quantity, 0) as current_stock,
               COALESCE(spi.price_per_unit, 0) as current_price,
               COALESCE(spml.min_stock, 0) as min_stock
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_inventory spi ON sp.id = spi.part_id
        LEFT JOIN spare_parts_min_levels spml ON sp.id = spml.part_id
        WHERE spc.category_name = 'Materials'
        ORDER BY sp.part_name
    ");
    $issueMaterialsStmt->execute();
    $ocp_endpoint['issue_materials'] = $issueMaterialsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get suppliers for dropdown
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM spare_parts_suppliers ORDER BY supplier_name");
    $suppliersStmt->execute();
    $ocp_endpoint['suppliers'] = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get vehicles for dropdown
    $vehiclesStmt = $pdo->prepare("SELECT id, vehicle_name, plate_number FROM vehicles ORDER BY vehicle_name");
    $vehiclesStmt->execute();
    $ocp_endpoint['vehicles'] = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get equipment for dropdown
    $equipmentStmt = $pdo->prepare("SELECT id, equipment_name FROM equipment ORDER BY equipment_name");
    $equipmentStmt->execute();
    $ocp_endpoint['equipment'] = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get employees for dropdown
    $employeesStmt = $pdo->prepare("
        SELECT id, employee_id, firstname, middlename, lastname, suffix, position 
        FROM employee 
        WHERE status = 'active'
        ORDER BY lastname, firstname
    ");
    $employeesStmt->execute();
    $ocp_endpoint['employees'] = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format employee names for display
    $ocp_endpoint['formatted_employees'] = [];
    foreach ($ocp_endpoint['employees'] as $emp) {
        $emp['display_name'] = formatEmployeeName($emp) . ' - ' . $emp['position'];
        $ocp_endpoint['formatted_employees'][] = $emp;
    }
    
    // Get mechanics for technician dropdown
    $mechanicsStmt = $pdo->prepare("
        SELECT id, employee_id, firstname, middlename, lastname, suffix, position 
        FROM employee 
        WHERE status = 'active' 
        AND position = 'Mechanic'
        ORDER BY lastname, firstname
    ");
    $mechanicsStmt->execute();
    $ocp_endpoint['mechanics'] = $mechanicsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format mechanic names for display
    $ocp_endpoint['formatted_mechanics'] = [];
    foreach ($ocp_endpoint['mechanics'] as $mech) {
        $mech['display_name'] = formatEmployeeName($mech) . ' - ' . $mech['position'];
        $ocp_endpoint['formatted_mechanics'][] = $mech;
    }
    
    // Get drivers for dropdown
    $driversStmt = $pdo->prepare("
        SELECT id, employee_id, firstname, middlename, lastname, suffix, position 
        FROM employee 
        WHERE status = 'active' 
        AND position = 'Driver'
        ORDER BY lastname, firstname
    ");
    $driversStmt->execute();
    $ocp_endpoint['drivers'] = $driversStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format driver names for display
    $ocp_endpoint['formatted_drivers'] = [];
    foreach ($ocp_endpoint['drivers'] as $drv) {
        $drv['display_name'] = formatEmployeeName($drv) . ' - ' . $drv['position'];
        $ocp_endpoint['formatted_drivers'][] = $drv;
    }
    
    // Get PR status counts for dashboard
    $statusStmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM spare_parts_pr 
        GROUP BY status
    ");
    $statusStmt->execute();
    $ocp_endpoint['status_counts'] = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total documents count
    $totalCountStmt = $pdo->prepare("SELECT COUNT(*) as total FROM spare_parts_pr");
    $totalCountStmt->execute();
    $ocp_endpoint['totalCount'] = $totalCountStmt->fetch(PDO::FETCH_ASSOC);
    $ocp_endpoint['total_requests'] = $ocp_endpoint['totalCount']['total'];
    
} catch (PDOException $e) {
    $ocp_endpoint['swal_data'] = array(
        'title' => 'Error!',
        'text' => 'Error fetching data: ' . $e->getMessage(),
        'icon' => 'error'
    );
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
