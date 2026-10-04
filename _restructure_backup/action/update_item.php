<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Database connection
require_once '../includes/db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $item_code = trim($_POST['item_code']);
    $item_name = trim($_POST['item_name']);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $unit_of_measure = trim($_POST['unit_of_measure']);
    $min_stock_level = isset($_POST['min_stock_level']) ? (int)$_POST['min_stock_level'] : 0;
    
    // Basic validation - now using category_id instead of item_type
    if (empty($item_code) || empty($item_name) || empty($category_id) || empty($unit_of_measure)) {
        $response = [
            'success' => false,
            'message' => 'All fields are required.'
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    if ($min_stock_level < 0) {
        $response = [
            'success' => false,
            'message' => 'Minimum stock level cannot be negative.'
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    try {
        // Check if item code already exists for another item
        $checkStmt = $pdo->prepare("SELECT id FROM item_names WHERE item_code = :item_code AND id != :id");
        $checkStmt->bindParam(':item_code', $item_code);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $response = [
                'success' => false,
                'message' => 'Item code already exists. Please use a different code.'
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Update item with category_id instead of item_type
        $updateStmt = $pdo->prepare("UPDATE item_names SET item_code = :item_code, item_name = :item_name, category_id = :category_id, unit_of_measure = :unit_of_measure, min_stock_level = :min_stock_level WHERE id = :id");
        $updateStmt->bindParam(':item_code', $item_code);
        $updateStmt->bindParam(':item_name', $item_name);
        $updateStmt->bindParam(':category_id', $category_id);
        $updateStmt->bindParam(':unit_of_measure', $unit_of_measure);
        $updateStmt->bindParam(':min_stock_level', $min_stock_level);
        $updateStmt->bindParam(':id', $id);
        
        if ($updateStmt->execute()) {
            $response = [
                'success' => true,
                'message' => 'Item updated successfully!'
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Error updating item. Please try again.'
            ];
        }
    } catch(PDOException $e) {
        $response = [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>