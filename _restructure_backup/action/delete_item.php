<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    $response = [
        'success' => false,
        'message' => 'Unauthorized access.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Database connection
require_once '../includes/db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM item_names WHERE id = :id");
        $deleteStmt->bindParam(':id', $id);
        
        if ($deleteStmt->execute()) {
            $response = [
                'success' => true,
                'message' => 'Item deleted successfully!'
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Error deleting item. Please try again.'
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