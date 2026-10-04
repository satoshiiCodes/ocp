<?php
session_start();
require_once '../includes/db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get request type
$request_type = $_POST['request_type'] ?? 'stock';
$year = date('Y');

if ($request_type === 'issue_materials') {
    // Generate Withdrawal Slip (WS) number for issue materials
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_pr WHERE YEAR(created_at) = ? AND request_type = 'issue_materials'");
    $stmt->execute([$year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sequence = $result['count'] + 1;
    $document_number = "PRWS-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
} 
elseif ($request_type === 'issue') {
    // Generate Issue Parts Purchase Request (IPPR) number for issue parts
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_pr WHERE YEAR(created_at) = ? AND request_type = 'issue'");
    $stmt->execute([$year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sequence = $result['count'] + 1;
    $document_number = "IPPR-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}
else {
    // Generate regular PR number for stock
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_pr WHERE YEAR(created_at) = ? AND request_type = 'stock'");
    $stmt->execute([$year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sequence = $result['count'] + 1;
    $document_number = "SPR-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

echo json_encode(['success' => true, 'document_number' => $document_number]);
?>