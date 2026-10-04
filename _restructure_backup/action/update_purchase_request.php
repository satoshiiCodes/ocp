<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get form data
$pr_id = $_POST['pr_id'] ?? '';
$date = $_POST['date'] ?? '';
$cin = $_POST['cin'] ?? '';
$project_id = $_POST['project_id'] ?? '';
$purchase_request_no = $_POST['purchase_request_no'] ?? '';
$item_type = $_POST['item_type'] ?? '';

// Validate required fields
if (empty($pr_id) || empty($date) || empty($cin) || empty($project_id) || empty($purchase_request_no) || empty($item_type)) {
    echo json_encode(['success' => false, 'message' => 'All basic fields are required.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Check if CIN already exists (excluding current PR)
    $checkCinStmt = $pdo->prepare("SELECT id FROM purchase_requests WHERE cin = :cin AND id != :pr_id");
    $checkCinStmt->bindParam(':cin', $cin);
    $checkCinStmt->bindParam(':pr_id', $pr_id);
    $checkCinStmt->execute();
    
    if ($checkCinStmt->rowCount() > 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'CIN already exists. Please use a different CIN.']);
        exit();
    }
    
    // Check if purchase request number already exists (excluding current PR)
    $checkPrNoStmt = $pdo->prepare("SELECT id FROM purchase_requests WHERE purchase_request_no = :purchase_request_no AND id != :pr_id");
    $checkPrNoStmt->bindParam(':purchase_request_no', $purchase_request_no);
    $checkPrNoStmt->bindParam(':pr_id', $pr_id);
    $checkPrNoStmt->execute();
    
    if ($checkPrNoStmt->rowCount() > 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Purchase Request Number already exists. Please use a different number.']);
        exit();
    }

    // Update main purchase request
    $updateStmt = $pdo->prepare("UPDATE purchase_requests 
        SET date = :date, cin = :cin, project_id = :project_id, 
            purchase_request_no = :purchase_request_no, item_type = :item_type, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = :pr_id");
    
    $updateStmt->bindParam(':date', $date);
    $updateStmt->bindParam(':cin', $cin);
    $updateStmt->bindParam(':project_id', $project_id);
    $updateStmt->bindParam(':purchase_request_no', $purchase_request_no);
    $updateStmt->bindParam(':item_type', $item_type);
    $updateStmt->bindParam(':pr_id', $pr_id);
    
    if (!$updateStmt->execute()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error updating purchase request header.']);
        exit();
    }

    // Process items
    $item_ids = $_POST['item_ids'] ?? [];
    $edit_quantities = $_POST['edit_quantity'] ?? [];
    $edit_unit_types = $_POST['edit_unit_type'] ?? []; // Added unit_type
    $edit_item_ids = $_POST['edit_item_id'] ?? [];
    $edit_suppliers = $_POST['edit_supplier'] ?? [];
    $edit_unit_prices = $_POST['edit_unit_price'] ?? [];

    $success_count = 0;
    $error_count = 0;

    // First, delete all existing items for this PR
    $deleteStmt = $pdo->prepare("DELETE FROM purchase_request_items WHERE purchase_request_id = :pr_id");
    $deleteStmt->bindParam(':pr_id', $pr_id);
    
    if (!$deleteStmt->execute()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error clearing existing items.']);
        exit();
    }

    // Then insert updated items
    if (!empty($edit_quantities) && is_array($edit_quantities)) {
        for ($i = 0; $i < count($edit_quantities); $i++) {
            $quantity = (int)($edit_quantities[$i] ?? 0);
            $unit_type = trim($edit_unit_types[$i] ?? ''); // Get unit_type
            $item_id = trim($edit_item_ids[$i] ?? '');
            $supplier = trim($edit_suppliers[$i] ?? '');
            $unit_price = floatval($edit_unit_prices[$i] ?? 0);
            
            // Skip empty or invalid entries
            if (empty($quantity) || empty($unit_type) || empty($item_id) || empty($supplier)) {
                $error_count++;
                continue;
            }
            
            if ($quantity <= 0) {
                $error_count++;
                continue;
            }
            
            // Insert purchase request item with unit_type
            $itemStmt = $pdo->prepare("INSERT INTO purchase_request_items 
                (purchase_request_id, quantity, unit_type, item_id, supplier, unit_price) 
                VALUES (:purchase_request_id, :quantity, :unit_type, :item_id, :supplier, :unit_price)");
            
            $itemStmt->bindParam(':purchase_request_id', $pr_id);
            $itemStmt->bindParam(':quantity', $quantity);
            $itemStmt->bindParam(':unit_type', $unit_type); // Bind unit_type
            $itemStmt->bindParam(':item_id', $item_id);
            $itemStmt->bindParam(':supplier', $supplier);
            $itemStmt->bindParam(':unit_price', $unit_price);
            
            if ($itemStmt->execute()) {
                $success_count++;
            } else {
                $error_count++;
                error_log("Error inserting item: " . implode(", ", $itemStmt->errorInfo()));
            }
        }
    }

    if ($success_count > 0) {
        $pdo->commit();
        
        // Add update history
        try {
            $historyStmt = $pdo->prepare("INSERT INTO pr_routing_history 
                (purchase_request_id, action, action_by, remarks) 
                VALUES (:purchase_request_id, 'updated', :action_by, 'Purchase Request updated with $success_count item(s)')");
            
            $historyStmt->bindParam(':purchase_request_id', $pr_id);
            $historyStmt->bindParam(':action_by', $_SESSION['user_id']);
            $historyStmt->execute();
        } catch (Exception $e) {
            error_log('Error adding update history: ' . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "Purchase Request updated successfully! $success_count item(s) processed." . 
                        ($error_count > 0 ? " $error_count item(s) failed." : "")
        ]);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error updating purchase request. No valid items were provided.']);
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Database error in update_purchase_request: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Error in update_purchase_request: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>