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
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit();
    }
    
    try {
        // Optional: Check if this expense type is being used in any expenses
        // Uncomment if you have an expenses table
        /*
        $checkStmt = $pdo->prepare("SELECT id FROM expenses WHERE expense_type_id = :id LIMIT 1");
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete this expense type because it is being used in expenses.']);
            exit();
        }
        */
        
        $deleteStmt = $pdo->prepare("DELETE FROM expenses_type WHERE id = :id");
        $deleteStmt->bindParam(':id', $id);
        
        if ($deleteStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Expense type deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting expense type.']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>