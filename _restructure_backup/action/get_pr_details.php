<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_GET['id'])) {
    $pr_id = $_GET['id'];
    
    try {
        // Get PR details with PO and WS numbers and statuses
        $prStmt = $pdo->prepare("
            SELECT pr.*, 
                   u.firstname, u.middlename, u.lastname, u.suffix,
                   p.project_name,
                   s.supplier_name,
                   (SELECT po.po_number FROM purchase_orders po WHERE po.pr_id = pr.id ORDER BY po.id DESC LIMIT 1) as po_number,
                   (SELECT ws.ws_number FROM withdrawal_slips ws WHERE ws.pr_id = pr.id ORDER BY ws.id DESC LIMIT 1) as ws_number,
                   (SELECT po.status FROM purchase_orders po WHERE po.pr_id = pr.id ORDER BY po.id DESC LIMIT 1) as po_status,
                   (SELECT ws.status FROM withdrawal_slips ws WHERE ws.pr_id = pr.id ORDER BY ws.id DESC LIMIT 1) as ws_status
            FROM purchase_requests pr
            LEFT JOIN users u ON pr.requested_by = u.id
            LEFT JOIN projects p ON pr.project_id = p.id
            LEFT JOIN suppliers s ON pr.supplier_id = s.id
            WHERE pr.id = ?
        ");
        $prStmt->execute([$pr_id]);
        $pr = $prStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pr) {
            // Get PR items with warehouse and category details
            $itemsStmt = $pdo->prepare("
                SELECT pri.*, 
                       i.item_code, i.item_name,
                       w.warehouse_name, w.location,
                       s.supplier_name,
                       ic.category_name,
                       ic.id as category_id
                FROM pr_items pri
                JOIN item_names i ON pri.item_id = i.id
                LEFT JOIN items_categories ic ON i.category_id = ic.id
                JOIN warehouses w ON pri.warehouse_id = w.id
                LEFT JOIN suppliers s ON pri.supplier_id = s.id
                WHERE pri.pr_id = ?
            ");
            $itemsStmt->execute([$pr_id]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'pr' => $pr,
                'items' => $items
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Purchase request not found'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No ID provided'
    ]);
}
?>