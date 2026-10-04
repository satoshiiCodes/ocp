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
            $stmt = $pdo->prepare("SELECT id, item_name, item_code FROM item_names ORDER BY item_name");
            break;
        case 'Gasoline':
            // For gasoline, we'll return predefined types
            $gasoline_types = [
                ['id' => 'unleaded', 'item_name' => 'Unleaded Gasoline', 'item_code' => 'GAS-UNL'],
                ['id' => 'premium', 'item_name' => 'Premium Gasoline', 'item_code' => 'GAS-PRM'],
                ['id' => 'diesel', 'item_name' => 'Diesel', 'item_code' => 'GAS-DSL']
            ];
            echo json_encode(['success' => true, 'items' => $gasoline_types]);
            exit();
        case 'Spare Parts':
            $stmt = $pdo->prepare("SELECT id, part_name as item_name, part_number as item_code FROM spare_parts ORDER BY part_name");
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid item type']);
            exit();
    }
    
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'items' => $items]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>