<?php
session_start();
require_once 'includes/db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

if (isset($_GET['id'])) {
    $movement_id = $_GET['id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT sm.*, i.item_code, i.item_name, 
                   s.supplier_name, p.project_name, 
                   w.warehouse_name, w.location,
                   sm.transfer_from, sm.transfer_to,
                   w_from.warehouse_name as from_warehouse_name,
                   w_to.warehouse_name as to_warehouse_name
            FROM stock_movements sm
            JOIN item_names i ON sm.item_id = i.id
            LEFT JOIN suppliers s ON sm.supplier_id = s.id
            LEFT JOIN projects p ON sm.project_id = p.id
            LEFT JOIN warehouses w ON sm.warehouse_id = w.id
            LEFT JOIN warehouses w_from ON sm.transfer_from = w_from.id
            LEFT JOIN warehouses w_to ON sm.transfer_to = w_to.id
            WHERE sm.id = :id
        ");
        $stmt->bindParam(':id', $movement_id);
        $stmt->execute();
        $movement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($movement) {
            echo json_encode(['success' => true, 'movement' => $movement]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Movement not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
}
?>