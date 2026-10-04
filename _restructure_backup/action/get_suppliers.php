<?php
session_start();
require_once '../includes/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$item_type = $_GET['item_type'] ?? '';

if (empty($item_type)) {
    echo json_encode(['success' => false, 'message' => 'Item type is required']);
    exit();
}

try {
    switch($item_type) {
        case 'Materials':
            $stmt = $pdo->prepare("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name");
            break;
        case 'Gasoline':
            $stmt = $pdo->prepare("SELECT id, supplier_name FROM gasoline_suppliers ORDER BY supplier_name");
            break;
        case 'Spare Parts':
            $stmt = $pdo->prepare("SELECT id, supplier_name FROM spare_parts_suppliers ORDER BY supplier_name");
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid item type']);
            exit();
    }
    
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'suppliers' => $suppliers]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>