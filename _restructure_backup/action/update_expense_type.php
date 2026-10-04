<?php
session_start();
require_once '../includes/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $expense_name = trim($_POST['expense_name']);
    $description = trim($_POST['description']);
    
    if ($id <= 0 || empty($expense_name)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }
    
    try {
        // Check if expense name already exists for other records
        $checkStmt = $pdo->prepare("SELECT id FROM expenses_type WHERE expense_name = :expense_name AND id != :id");
        $checkStmt->bindParam(':expense_name', $expense_name);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Expense name already exists. Please use a different name.']);
            exit();
        }
        
        $updateStmt = $pdo->prepare("UPDATE expenses_type SET expense_name = :expense_name, description = :description, updated_at = NOW() WHERE id = :id");
        $updateStmt->bindParam(':expense_name', $expense_name);
        $updateStmt->bindParam(':description', $description);
        $updateStmt->bindParam(':id', $id);
        
        if ($updateStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Expense type updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating expense type.']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>