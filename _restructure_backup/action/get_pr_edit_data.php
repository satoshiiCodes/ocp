<?php
session_start();
require_once '../includes/db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (isset($_GET['id'])) {
    $pr_id = $_GET['id'];
    
    try {
        // Get purchase request details
        $prStmt = $pdo->prepare("
            SELECT pr.*, p.project_name 
            FROM purchase_requests pr 
            LEFT JOIN projects p ON pr.project_id = p.id 
            WHERE pr.id = :id
        ");
        $prStmt->bindParam(':id', $pr_id);
        $prStmt->execute();
        $pr = $prStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pr) {
            echo json_encode(['success' => false, 'message' => 'Purchase request not found']);
            exit();
        }
        
        // Get purchase request items - FIXED: No JOIN with item_names table
        $itemsStmt = $pdo->prepare("
            SELECT pri.*
            FROM purchase_request_items pri 
            WHERE pri.purchase_request_id = :pr_id
            ORDER BY pri.id
        ");
        $itemsStmt->bindParam(':pr_id', $pr_id);
        $itemsStmt->execute();
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'pr' => $pr,
            'items' => $items
        ]);
        
    } catch(PDOException $e) {
        error_log('Error fetching purchase request data: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
}