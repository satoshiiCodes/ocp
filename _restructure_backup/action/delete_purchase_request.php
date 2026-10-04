<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$pr_id = $_POST['id'];

try {
    $pdo->beginTransaction();
    
    // Get all purchase order IDs related to this purchase request
    $getPoIdsStmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE purchase_request_id = ?");
    $getPoIdsStmt->execute([$pr_id]);
    $po_ids = $getPoIdsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 1. Delete from purchase_order_history
    if (!empty($po_ids)) {
        $placeholders = str_repeat('?,', count($po_ids) - 1) . '?';
        $deletePurchaseOrderHistoryStmt = $pdo->prepare("DELETE FROM purchase_order_history WHERE purchase_order_id IN ($placeholders)");
        $deletePurchaseOrderHistoryStmt->execute($po_ids);
    }
    
    // 2. Delete from purchase_order_items
    if (!empty($po_ids)) {
        $placeholders = str_repeat('?,', count($po_ids) - 1) . '?';
        $deletePurchaseOrderItemsStmt = $pdo->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id IN ($placeholders)");
        $deletePurchaseOrderItemsStmt->execute($po_ids);
    }
    
    // 3. Delete from purchase_orders table
    $deletePurchaseOrdersStmt = $pdo->prepare("DELETE FROM purchase_orders WHERE purchase_request_id = ?");
    $deletePurchaseOrdersStmt->execute([$pr_id]);
    
    // 4. Delete from pr_routing_history table
    $deletePrRoutingHistoryStmt = $pdo->prepare("DELETE FROM pr_routing_history WHERE purchase_request_id = ?");
    $deletePrRoutingHistoryStmt->execute([$pr_id]);
    
    // 5. Delete from pr_routing table
    $deletePrRoutingStmt = $pdo->prepare("DELETE FROM pr_routing WHERE purchase_request_id = ?");
    $deletePrRoutingStmt->execute([$pr_id]);
    
    // 6. Delete from purchase_request_items table
    $deleteItemsStmt = $pdo->prepare("DELETE FROM purchase_request_items WHERE purchase_request_id = ?");
    $deleteItemsStmt->execute([$pr_id]);
    
    // 7. Finally delete from purchase_requests table
    $deleteStmt = $pdo->prepare("DELETE FROM purchase_requests WHERE id = ?");
    $deleteStmt->execute([$pr_id]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Purchase request and all related records deleted successfully']);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>