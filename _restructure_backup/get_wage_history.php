<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Database connection
require_once 'includes/db_config.php';

if (isset($_GET['employee_id'])) {
    $employee_id = $_GET['employee_id'];
    
    try {
        // Get employee name
        $stmt = $pdo->prepare("SELECT firstname, lastname FROM employee WHERE id = :id");
        $stmt->bindParam(':id', $employee_id);
        $stmt->execute();
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit();
        }
        
        $employee_name = $employee['lastname'] . ', ' . $employee['firstname'];
        
        // Get wage history
        $stmt = $pdo->prepare("
            SELECT wh.*, u.firstname as changed_by_firstname, u.lastname as changed_by_lastname
            FROM wage_history wh
            JOIN users u ON wh.changed_by = u.id
            WHERE wh.employee_id = :employee_id
            ORDER BY wh.changed_at DESC
        ");
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->execute();
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        $formatted_history = [];
        foreach ($history as $record) {
            $formatted_history[] = [
                'changed_at' => date('M d, Y h:i A', strtotime($record['changed_at'])),
                'old_wage' => $record['old_wage'],
                'new_wage' => $record['new_wage'],
                'change_amount' => $record['change_amount'],
                'change_percentage' => $record['change_percentage'],
                'change_type' => $record['change_type'],
                'changed_by' => $record['changed_by_firstname'] . ' ' . $record['changed_by_lastname'],
                'change_reason' => $record['change_reason']
            ];
        }
        
        echo json_encode([
            'success' => true, 
            'employee_name' => $employee_name,
            'history' => $formatted_history
        ]);
        
    } catch(PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Employee ID not provided']);
}
?>