<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../includes/db_config.php';

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
?>