<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Get user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Format the display name
$display_name = $user['firstname'];

if (!empty($user['middlename'])) {
    $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
}

$display_name .= ' ' . $user['lastname'];

if (!empty($user['suffix'])) {
    $display_name .= ' ' . $user['suffix'];
}

// Function to update inventory table
function updateInventoryTable($pdo, $item_id, $warehouse_id) {
    try {
        // Calculate current inventory from batches
        $calcStmt = $pdo->prepare("
            SELECT 
                SUM(quantity) as total_quantity,
                CASE 
                    WHEN SUM(quantity) > 0 THEN SUM(quantity * unit_cost) / SUM(quantity)
                    ELSE 0
                END as avg_unit_cost,
                SUM(quantity * unit_cost) as total_value
            FROM inventory_batches 
            WHERE item_id = :item_id AND warehouse_id = :warehouse_id
        ");
        $calcStmt->bindParam(':item_id', $item_id);
        $calcStmt->bindParam(':warehouse_id', $warehouse_id);
        $calcStmt->execute();
        $inventory_data = $calcStmt->fetch(PDO::FETCH_ASSOC);
        
        $quantity = $inventory_data['total_quantity'] ?? 0;
        $unit_cost = $inventory_data['avg_unit_cost'] ?? 0;
        $total_value = $inventory_data['total_value'] ?? 0;
        
        // Check if record exists in inventory table
        $checkStmt = $pdo->prepare("SELECT id FROM inventory WHERE item_id = :item_id AND warehouse_id = :warehouse_id");
        $checkStmt->bindParam(':item_id', $item_id);
        $checkStmt->bindParam(':warehouse_id', $warehouse_id);
        $checkStmt->execute();
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing record
            $updateStmt = $pdo->prepare("
                UPDATE inventory 
                SET quantity = :quantity, unit_cost = :unit_cost, total_value = :total_value, last_updated = CURRENT_TIMESTAMP
                WHERE item_id = :item_id AND warehouse_id = :warehouse_id
            ");
            $updateStmt->bindParam(':quantity', $quantity);
            $updateStmt->bindParam(':unit_cost', $unit_cost);
            $updateStmt->bindParam(':total_value', $total_value);
            $updateStmt->bindParam(':item_id', $item_id);
            $updateStmt->bindParam(':warehouse_id', $warehouse_id);
            $updateStmt->execute();
        } else {
            // Insert new record
            $insertStmt = $pdo->prepare("
                INSERT INTO inventory (item_id, warehouse_id, quantity, unit_cost, total_value)
                VALUES (:item_id, :warehouse_id, :quantity, :unit_cost, :total_value)
            ");
            $insertStmt->bindParam(':item_id', $item_id);
            $insertStmt->bindParam(':warehouse_id', $warehouse_id);
            $insertStmt->bindParam(':quantity', $quantity);
            $insertStmt->bindParam(':unit_cost', $unit_cost);
            $insertStmt->bindParam(':total_value', $total_value);
            $insertStmt->execute();
        }
        
        return true;
    } catch(PDOException $e) {
        error_log("Error updating inventory table: " . $e->getMessage());
        return false;
    }
}

// Function to update all inventory records
function updateAllInventory($pdo) {
    try {
        // Get all unique item-warehouse combinations from batches
        $combinationsStmt = $pdo->prepare("
            SELECT DISTINCT item_id, warehouse_id 
            FROM inventory_batches 
            WHERE quantity > 0
            UNION
            SELECT DISTINCT item_id, warehouse_id 
            FROM inventory
        ");
        $combinationsStmt->execute();
        $combinations = $combinationsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($combinations as $combo) {
            updateInventoryTable($pdo, $combo['item_id'], $combo['warehouse_id']);
        }
        
        // Also remove inventory records where quantity is 0 and no batches exist
        $cleanupStmt = $pdo->prepare("
            DELETE FROM inventory 
            WHERE quantity = 0 
            AND NOT EXISTS (
                SELECT 1 FROM inventory_batches 
                WHERE inventory_batches.item_id = inventory.item_id 
                AND inventory_batches.warehouse_id = inventory.warehouse_id
            )
        ");
        $cleanupStmt->execute();
        
        return true;
    } catch(PDOException $e) {
        error_log("Error updating all inventory: " . $e->getMessage());
        return false;
    }
}

// Process stock movements
$message = '';
$message_type = ''; // success or danger
$swal_data = array(); // For SweetAlert2 data

// Check for session-based SweetAlert data (for redirects)
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

// Process delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_movement'])) {
    $movement_id = $_POST['movement_id'];
    
    try {
        // Get movement details to determine type and reverse the action
        $stmt = $pdo->prepare("SELECT * FROM stock_movements WHERE id = :id");
        $stmt->bindParam(':id', $movement_id);
        $stmt->execute();
        $movement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($movement) {
            $pdo->beginTransaction();
            
            if ($movement['movement_type'] === 'in') {
                // For stock in, we need to remove the batch and reduce inventory
                $batchStmt = $pdo->prepare("SELECT id, quantity FROM inventory_batches 
                                           WHERE item_id = :item_id AND warehouse_id = :warehouse_id 
                                           AND batch_number = :batch_number");
                $batchStmt->bindParam(':item_id', $movement['item_id']);
                $batchStmt->bindParam(':warehouse_id', $movement['warehouse_id']);
                $batchStmt->bindParam(':batch_number', $movement['batch_number']);
                $batchStmt->execute();
                $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($batch) {
                    // Update batch quantity
                    $updateStmt = $pdo->prepare("UPDATE inventory_batches SET quantity = quantity - :quantity 
                                               WHERE id = :id AND quantity >= :quantity");
                    $updateStmt->bindParam(':quantity', $movement['quantity']);
                    $updateStmt->bindParam(':id', $batch['id']);
                    $updateStmt->execute();
                    
                    // If batch quantity becomes zero or negative, delete it
                    if ($batch['quantity'] <= $movement['quantity']) {
                        $deleteStmt = $pdo->prepare("DELETE FROM inventory_batches WHERE id = :id");
                        $deleteStmt->bindParam(':id', $batch['id']);
                        $deleteStmt->execute();
                    }
                }
            } else {
                // For stock out, we need to return the stock to the appropriate batch
                // Find the original batch or create a new one if needed
                $batchStmt = $pdo->prepare("SELECT id FROM inventory_batches 
                                           WHERE item_id = :item_id AND warehouse_id = :warehouse_id 
                                           AND batch_number = :batch_number");
                $batchStmt->bindParam(':item_id', $movement['item_id']);
                $batchStmt->bindParam(':warehouse_id', $movement['warehouse_id']);
                $batchStmt->bindParam(':batch_number', $movement['batch_number']);
                $batchStmt->execute();
                $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($batch) {
                    // Update existing batch
                    $updateStmt = $pdo->prepare("UPDATE inventory_batches SET quantity = quantity + :quantity 
                                               WHERE id = :id");
                    $updateStmt->bindParam(':quantity', $movement['quantity']);
                    $updateStmt->bindParam(':id', $batch['id']);
                    $updateStmt->execute();
                } else {
                    // Create new batch with original values
                    $insertStmt = $pdo->prepare("INSERT INTO inventory_batches 
                        (item_id, warehouse_id, quantity, unit_cost, batch_number, received_date) 
                        VALUES (:item_id, :warehouse_id, :quantity, :unit_cost, :batch_number, CURDATE())");
                    $insertStmt->bindParam(':item_id', $movement['item_id']);
                    $insertStmt->bindParam(':warehouse_id', $movement['warehouse_id']);
                    $insertStmt->bindParam(':quantity', $movement['quantity']);
                    $insertStmt->bindParam(':unit_cost', $movement['unit_cost']);
                    $insertStmt->bindParam(':batch_number', $movement['batch_number']);
                    $insertStmt->execute();
                }
            }
            
            // Delete the movement record
            $deleteStmt = $pdo->prepare("DELETE FROM stock_movements WHERE id = :id");
            $deleteStmt->bindParam(':id', $movement_id);
            $deleteStmt->execute();
            
            // Update inventory table
            updateInventoryTable($pdo, $movement['item_id'], $movement['warehouse_id']);
            
            $pdo->commit();
            
            $_SESSION['swal_data'] = array(
                'title' => 'Success!',
                'text' => 'Stock movement deleted successfully!',
                'icon' => 'success'
            );
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $_SESSION['swal_data'] = array(
                'title' => 'Error!',
                'text' => 'Stock movement not found.',
                'icon' => 'error'
            );
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } catch(PDOException $e) {
        $pdo->rollBack();
        $_SESSION['swal_data'] = array(
            'title' => 'Database Error!',
            'text' => 'Database error: ' . $e->getMessage(),
            'icon' => 'error'
        );
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'stock_in') {
        // Process stock in from supplier
        $item_id = $_POST['item_id'];
        $supplier_id = $_POST['supplier_id'];
        $warehouse_id = $_POST['warehouse_id'];
        $quantity = $_POST['quantity'];
        $unit_cost = $_POST['unit_cost'];
        $date_received = $_POST['date_received'];
        $batch_number = $_POST['batch_number'];
        $purchase_order = $_POST['purchase_order'] ?? '';
        $purchase_request = $_POST['purchase_request'] ?? '';
        
        try {
            $pdo->beginTransaction();
            
            // Insert stock in record
            $insertStmt = $pdo->prepare("INSERT INTO stock_movements (item_id, supplier_id, warehouse_id, quantity, unit_cost, movement_type, movement_date, batch_number, purchase_order, purchase_request) 
                                        VALUES (:item_id, :supplier_id, :warehouse_id, :quantity, :unit_cost, 'in', :date_received, :batch_number, :purchase_order, :purchase_request)");
            $insertStmt->bindParam(':item_id', $item_id);
            $insertStmt->bindParam(':supplier_id', $supplier_id);
            $insertStmt->bindParam(':warehouse_id', $warehouse_id);
            $insertStmt->bindParam(':quantity', $quantity);
            $insertStmt->bindParam(':unit_cost', $unit_cost);
            $insertStmt->bindParam(':date_received', $date_received);
            $insertStmt->bindParam(':batch_number', $batch_number);
            $insertStmt->bindParam(':purchase_order', $purchase_order);
            $insertStmt->bindParam(':purchase_request', $purchase_request);
            $insertStmt->execute();
            
            // Update inventory levels - Add new batch
            $updateStmt = $pdo->prepare("INSERT INTO inventory_batches 
                (item_id, warehouse_id, quantity, unit_cost, batch_number, received_date, purchase_order, purchase_request) 
                VALUES (:item_id, :warehouse_id, :quantity, :unit_cost, :batch_number, :received_date, :purchase_order, :purchase_request)");
            $updateStmt->bindParam(':item_id', $item_id);
            $updateStmt->bindParam(':warehouse_id', $warehouse_id);
            $updateStmt->bindParam(':quantity', $quantity);
            $updateStmt->bindParam(':unit_cost', $unit_cost);
            $updateStmt->bindParam(':batch_number', $batch_number);
            $updateStmt->bindParam(':received_date', $date_received);
            $updateStmt->bindParam(':purchase_order', $purchase_order);
            $updateStmt->bindParam(':purchase_request', $purchase_request);
            $updateStmt->execute();
            
            // Update inventory table
            updateInventoryTable($pdo, $item_id, $warehouse_id);
            
            $pdo->commit();
            
            $_SESSION['swal_data'] = array(
                'title' => 'Success!',
                'text' => 'Stock added successfully!',
                'icon' => 'success'
            );
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch(PDOException $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
    } elseif ($action === 'stock_out') {
        // Process stock out to project using FIFO
        $item_id = $_POST['item_id'];
        $project_id = $_POST['project_id'];
        $warehouse_id = $_POST['warehouse_id'];
        $quantity = $_POST['quantity'];
        $date_issued = $_POST['date_issued'];
        
        try {
            // First, get the project's current threshold_amount
            $projectStmt = $pdo->prepare("SELECT threshold_amount FROM projects WHERE id = :project_id");
            $projectStmt->bindParam(':project_id', $project_id);
            $projectStmt->execute();
            $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project) {
                $swal_data = array(
                    'title' => 'Error!',
                    'text' => 'Project not found.',
                    'icon' => 'error'
                );
            } else {
                // Get available batches sorted by received_date (FIFO)
                $batchStmt = $pdo->prepare("SELECT id, quantity, unit_cost, batch_number FROM inventory_batches 
                                           WHERE item_id = :item_id AND warehouse_id = :warehouse_id AND quantity > 0 
                                           ORDER BY received_date ASC");
                $batchStmt->bindParam(':item_id', $item_id);
                $batchStmt->bindParam(':warehouse_id', $warehouse_id);
                $batchStmt->execute();
                $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Calculate total available stock
                $total_available = 0;
                foreach ($batches as $batch) {
                    $total_available += $batch['quantity'];
                }
                
                if ($total_available >= $quantity) {
                    $remaining_quantity = $quantity;
                    $total_cost = 0; // To track total cost for threshold deduction
                    
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Process each batch in FIFO order
                    foreach ($batches as $batch) {
                        if ($remaining_quantity <= 0) break;
                        
                        $batch_id = $batch['id'];
                        $batch_qty = $batch['quantity'];
                        $batch_cost = $batch['unit_cost'];
                        $batch_number = $batch['batch_number'];
                        
                        // Determine how much to take from this batch
                        $take_qty = min($remaining_quantity, $batch_qty);
                        
                        // Calculate cost for this portion
                        $batch_portion_cost = $take_qty * $batch_cost;
                        $total_cost += $batch_portion_cost;
                        
                        // Insert stock out record for this batch
                        $insertStmt = $pdo->prepare("INSERT INTO stock_movements (item_id, project_id, warehouse_id, quantity, unit_cost, movement_type, movement_date, batch_number) 
                                                    VALUES (:item_id, :project_id, :warehouse_id, :quantity, :unit_cost, 'out', :date_issued, :batch_number)");
                        $insertStmt->bindParam(':item_id', $item_id);
                        $insertStmt->bindParam(':project_id', $project_id);
                        $insertStmt->bindParam(':warehouse_id', $warehouse_id);
                        $insertStmt->bindParam(':quantity', $take_qty);
                        $insertStmt->bindParam(':unit_cost', $batch_cost);
                        $insertStmt->bindParam(':date_issued', $date_issued);
                        $insertStmt->bindParam(':batch_number', $batch_number);
                        $insertStmt->execute();
                        
                        // Update batch quantity
                        $updateStmt = $pdo->prepare("UPDATE inventory_batches SET quantity = quantity - :take_qty WHERE id = :batch_id");
                        $updateStmt->bindParam(':take_qty', $take_qty);
                        $updateStmt->bindParam(':batch_id', $batch_id);
                        $updateStmt->execute();
                        
                        $remaining_quantity -= $take_qty;
                    }
                    
                    // Update inventory table
                    updateInventoryTable($pdo, $item_id, $warehouse_id);
                    
                    // Update project's threshold_amount
                    $new_threshold = $project['threshold_amount'] - $total_cost;
                    $updateProjectStmt = $pdo->prepare("UPDATE projects SET threshold_amount = :threshold_amount WHERE id = :project_id");
                    $updateProjectStmt->bindParam(':threshold_amount', $new_threshold);
                    $updateProjectStmt->bindParam(':project_id', $project_id);
                    $updateProjectStmt->execute();
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $_SESSION['swal_data'] = array(
                        'title' => 'Success!',
                        'text' => 'Stock issued successfully using FIFO method! Total cost: ₱' . number_format($total_cost, 2) . ' deducted from project threshold.',
                        'icon' => 'success'
                    );
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $swal_data = array(
                        'title' => 'Insufficient Stock!',
                        'text' => 'Insufficient stock available for this item.',
                        'icon' => 'warning'
                    );
                }
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
    } elseif ($action === 'stock_out_subcon') {
        // Process stock out to project with subcon using FIFO
        $item_id = $_POST['item_id'];
        $project_id = $_POST['project_id'];
        $subcon_id = $_POST['subcon_id'];
        $warehouse_id = $_POST['warehouse_id'];
        $quantity = $_POST['quantity'];
        $date_issued = $_POST['date_issued'];
        
        try {
            // First, get the project's current threshold_amount
            $projectStmt = $pdo->prepare("SELECT threshold_amount FROM projects WHERE id = :project_id");
            $projectStmt->bindParam(':project_id', $project_id);
            $projectStmt->execute();
            $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project) {
                $swal_data = array(
                    'title' => 'Error!',
                    'text' => 'Project not found.',
                    'icon' => 'error'
                );
            } else {
                // Get available batches sorted by received_date (FIFO)
                $batchStmt = $pdo->prepare("SELECT id, quantity, unit_cost, batch_number FROM inventory_batches 
                                           WHERE item_id = :item_id AND warehouse_id = :warehouse_id AND quantity > 0 
                                           ORDER BY received_date ASC");
                $batchStmt->bindParam(':item_id', $item_id);
                $batchStmt->bindParam(':warehouse_id', $warehouse_id);
                $batchStmt->execute();
                $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Calculate total available stock
                $total_available = 0;
                foreach ($batches as $batch) {
                    $total_available += $batch['quantity'];
                }
                
                if ($total_available >= $quantity) {
                    $remaining_quantity = $quantity;
                    $total_cost = 0; // To track total cost for threshold deduction
                    
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Process each batch in FIFO order
                    foreach ($batches as $batch) {
                        if ($remaining_quantity <= 0) break;
                        
                        $batch_id = $batch['id'];
                        $batch_qty = $batch['quantity'];
                        $batch_cost = $batch['unit_cost'];
                        $batch_number = $batch['batch_number'];
                        
                        // Determine how much to take from this batch
                        $take_qty = min($remaining_quantity, $batch_qty);
                        
                        // Calculate cost for this portion
                        $batch_portion_cost = $take_qty * $batch_cost;
                        $total_cost += $batch_portion_cost;
                        
                        // Insert stock out record for this batch with subcon
                        $insertStmt = $pdo->prepare("INSERT INTO stock_movements (item_id, project_id, subcon_id, warehouse_id, quantity, unit_cost, movement_type, movement_date, batch_number) 
                                                    VALUES (:item_id, :project_id, :subcon_id, :warehouse_id, :quantity, :unit_cost, 'out', :date_issued, :batch_number)");
                        $insertStmt->bindParam(':item_id', $item_id);
                        $insertStmt->bindParam(':project_id', $project_id);
                        $insertStmt->bindParam(':subcon_id', $subcon_id);
                        $insertStmt->bindParam(':warehouse_id', $warehouse_id);
                        $insertStmt->bindParam(':quantity', $take_qty);
                        $insertStmt->bindParam(':unit_cost', $batch_cost);
                        $insertStmt->bindParam(':date_issued', $date_issued);
                        $insertStmt->bindParam(':batch_number', $batch_number);
                        $insertStmt->execute();
                        
                        // Update batch quantity
                        $updateStmt = $pdo->prepare("UPDATE inventory_batches SET quantity = quantity - :take_qty WHERE id = :batch_id");
                        $updateStmt->bindParam(':take_qty', $take_qty);
                        $updateStmt->bindParam(':batch_id', $batch_id);
                        $updateStmt->execute();
                        
                        $remaining_quantity -= $take_qty;
                    }
                    
                    // Update inventory table
                    updateInventoryTable($pdo, $item_id, $warehouse_id);
                    
                    // Update project's threshold_amount
                    $new_threshold = $project['threshold_amount'] - $total_cost;
                    $updateProjectStmt = $pdo->prepare("UPDATE projects SET threshold_amount = :threshold_amount WHERE id = :project_id");
                    $updateProjectStmt->bindParam(':threshold_amount', $new_threshold);
                    $updateProjectStmt->bindParam(':project_id', $project_id);
                    $updateProjectStmt->execute();
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $_SESSION['swal_data'] = array(
                        'title' => 'Success!',
                        'text' => 'Stock issued to project with subcon successfully using FIFO method! Total cost: ₱' . number_format($total_cost, 2) . ' deducted from project threshold.',
                        'icon' => 'success'
                    );
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $swal_data = array(
                        'title' => 'Insufficient Stock!',
                        'text' => 'Insufficient stock available for this item.',
                        'icon' => 'warning'
                    );
                }
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
    } elseif ($action === 'transfer') {
        // Process stock transfer between warehouses using FIFO
        $item_id = $_POST['item_id'];
        $from_warehouse_id = $_POST['from_warehouse_id'];
        $to_warehouse_id = $_POST['to_warehouse_id'];
        $quantity = $_POST['quantity'];
        $transfer_date = $_POST['transfer_date'];
        
        try {
            // Get available batches sorted by received_date (FIFO)
            $batchStmt = $pdo->prepare("SELECT id, quantity, unit_cost, batch_number, received_date FROM inventory_batches 
                                       WHERE item_id = :item_id AND warehouse_id = :warehouse_id AND quantity > 0 
                                       ORDER BY received_date ASC");
            $batchStmt->bindParam(':item_id', $item_id);
            $batchStmt->bindParam(':warehouse_id', $from_warehouse_id);
            $batchStmt->execute();
            $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate total available stock
            $total_available = 0;
            foreach ($batches as $batch) {
                $total_available += $batch['quantity'];
            }
            
            if ($total_available >= $quantity) {
                $remaining_quantity = $quantity;
                
                // Begin transaction
                $pdo->beginTransaction();
                
                // Process each batch in FIFO order
                foreach ($batches as $batch) {
                    if ($remaining_quantity <= 0) break;
                    
                    $batch_id = $batch['id'];
                    $batch_qty = $batch['quantity'];
                    $batch_cost = $batch['unit_cost'];
                    $batch_number = $batch['batch_number'];
                    $received_date = $batch['received_date'];
                    
                    // Determine how much to transfer from this batch
                    $take_qty = min($remaining_quantity, $batch_qty);
                    
                    // Record stock out from source warehouse
                    $outStmt = $pdo->prepare("INSERT INTO stock_movements (item_id, warehouse_id, quantity, unit_cost, movement_type, movement_date, transfer_to, batch_number) 
                                             VALUES (:item_id, :warehouse_id, :quantity, :unit_cost, 'out', :transfer_date, :transfer_to, :batch_number)");
                    $outStmt->bindParam(':item_id', $item_id);
                    $outStmt->bindParam(':warehouse_id', $from_warehouse_id);
                    $outStmt->bindParam(':quantity', $take_qty);
                    $outStmt->bindParam(':unit_cost', $batch_cost);
                    $outStmt->bindParam(':transfer_date', $transfer_date);
                    $outStmt->bindParam(':transfer_to', $to_warehouse_id);
                    $outStmt->bindParam(':batch_number', $batch_number);
                    $outStmt->execute();
                    
                    // Update source batch quantity
                    $updateFromStmt = $pdo->prepare("UPDATE inventory_batches SET quantity = quantity - :take_qty WHERE id = :batch_id");
                    $updateFromStmt->bindParam(':take_qty', $take_qty);
                    $updateFromStmt->bindParam(':batch_id', $batch_id);
                    $updateFromStmt->execute();
                    
                    // Add to destination warehouse with same batch details
                    // Check if batch already exists in destination
                    $checkBatchStmt = $pdo->prepare("SELECT id, quantity FROM inventory_batches 
                                                   WHERE item_id = :item_id AND warehouse_id = :warehouse_id AND batch_number = :batch_number");
                    $checkBatchStmt->bindParam(':item_id', $item_id);
                    $checkBatchStmt->bindParam(':warehouse_id', $to_warehouse_id);
                    $checkBatchStmt->bindParam(':batch_number', $batch_number);
                    $checkBatchStmt->execute();
                    $existing_batch = $checkBatchStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_batch) {
                        // Update existing batch
                        $updateToStmt = $pdo->prepare("UPDATE inventory_batches SET quantity = quantity + :take_qty 
                                                      WHERE id = :batch_id");
                        $updateToStmt->bindParam(':take_qty', $take_qty);
                        $updateToStmt->bindParam(':batch_id', $existing_batch['id']);
                        $updateToStmt->execute();
                    } else {
                        // Create new batch in destination
                        $insertToStmt = $pdo->prepare("INSERT INTO inventory_batches 
                            (item_id, warehouse_id, quantity, unit_cost, batch_number, received_date, purchase_order, purchase_request) 
                            VALUES (:item_id, :warehouse_id, :quantity, :unit_cost, :batch_number, :received_date, :purchase_order, :purchase_request)");
                        $insertToStmt->bindParam(':item_id', $item_id);
                        $insertToStmt->bindParam(':warehouse_id', $to_warehouse_id);
                        $insertToStmt->bindParam(':quantity', $take_qty);
                        $insertToStmt->bindParam(':unit_cost', $batch_cost);
                        $insertToStmt->bindParam(':batch_number', $batch_number);
                        $insertToStmt->bindParam(':received_date', $received_date);
                        $insertToStmt->bindParam(':purchase_order', $batch['purchase_order']);
                        $insertToStmt->bindParam(':purchase_request', $batch['purchase_request']);
                        $insertToStmt->execute();
                    }
                    
                    // Record stock in to destination warehouse
                    $inStmt = $pdo->prepare("INSERT INTO stock_movements (item_id, warehouse_id, quantity, unit_cost, movement_type, movement_date, transfer_from, batch_number) 
                                           VALUES (:item_id, :warehouse_id, :quantity, :unit_cost, 'in', :transfer_date, :transfer_from, :batch_number)");
                    $inStmt->bindParam(':item_id', $item_id);
                    $inStmt->bindParam(':warehouse_id', $to_warehouse_id);
                    $inStmt->bindParam(':quantity', $take_qty);
                    $inStmt->bindParam(':unit_cost', $batch_cost);
                    $inStmt->bindParam(':transfer_date', $transfer_date);
                    $inStmt->bindParam(':transfer_from', $from_warehouse_id);
                    $inStmt->bindParam(':batch_number', $batch_number);
                    $inStmt->execute();
                    
                    $remaining_quantity -= $take_qty;
                }
                
                // Update inventory tables for both warehouses
                updateInventoryTable($pdo, $item_id, $from_warehouse_id);
                updateInventoryTable($pdo, $item_id, $to_warehouse_id);
                
                // Commit transaction
                $pdo->commit();
                
                $_SESSION['swal_data'] = array(
                    'title' => 'Success!',
                    'text' => 'Stock transferred successfully using FIFO method!',
                    'icon' => 'success'
                );
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $swal_data = array(
                    'title' => 'Insufficient Stock!',
                    'text' => 'Insufficient stock available in the source warehouse.',
                    'icon' => 'warning'
                );
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
    } elseif ($action === 'set_min_stock') {
        // Process setting minimum stock level
        $item_id = $_POST['item_id'];
        $min_stock_level = $_POST['min_stock_level'];
        
        try {
            $updateStmt = $pdo->prepare("UPDATE item_names SET min_stock_level = :min_stock_level WHERE id = :item_id");
            $updateStmt->bindParam(':min_stock_level', $min_stock_level);
            $updateStmt->bindParam(':item_id', $item_id);
            
            if ($updateStmt->execute()) {
                $_SESSION['swal_data'] = array(
                    'title' => 'Success!',
                    'text' => 'Minimum stock level updated successfully!',
                    'icon' => 'success'
                );
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $swal_data = array(
                    'title' => 'Error!',
                    'text' => 'Error updating minimum stock level. Please try again.',
                    'icon' => 'error'
                );
            }
        } catch(PDOException $e) {
            $swal_data = array(
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
    } elseif ($action === 'initial_stock') {
        // Process initial stock for warehouses
        $item_id = $_POST['item_id'];
        $warehouse_id = $_POST['warehouse_id'];
        $quantity = $_POST['quantity'];
        $unit_cost = $_POST['unit_cost'];
        $date_added = $_POST['date_added'];
        $batch_number = 'INITIAL-' . date('YmdHis');
        
        try {
            $pdo->beginTransaction();
            
            // Insert initial stock record
            $insertStmt = $pdo->prepare("INSERT INTO stock_movements (item_id, warehouse_id, quantity, unit_cost, movement_type, movement_date, notes, batch_number) 
                                        VALUES (:item_id, :warehouse_id, :quantity, :unit_cost, 'in', :date_added, 'Initial stock', :batch_number)");
            $insertStmt->bindParam(':item_id', $item_id);
            $insertStmt->bindParam(':warehouse_id', $warehouse_id);
            $insertStmt->bindParam(':quantity', $quantity);
            $insertStmt->bindParam(':unit_cost', $unit_cost);
            $insertStmt->bindParam(':date_added', $date_added);
            $insertStmt->bindParam(':batch_number', $batch_number);
            $insertStmt->execute();
            
            // Update inventory levels - Add initial batch
            $updateStmt = $pdo->prepare("INSERT INTO inventory_batches (item_id, warehouse_id, quantity, unit_cost, batch_number, received_date) 
                                        VALUES (:item_id, :warehouse_id, :quantity, :unit_cost, :batch_number, :date_added)");
            $updateStmt->bindParam(':item_id', $item_id);
            $updateStmt->bindParam(':warehouse_id', $warehouse_id);
            $updateStmt->bindParam(':quantity', $quantity);
            $updateStmt->bindParam(':unit_cost', $unit_cost);
            $updateStmt->bindParam(':batch_number', $batch_number);
            $updateStmt->bindParam(':date_added', $date_added);
            $updateStmt->execute();
            
            // Update inventory table
            updateInventoryTable($pdo, $item_id, $warehouse_id);
            
            $pdo->commit();
            
            $_SESSION['swal_data'] = array(
                'title' => 'Success!',
                'text' => 'Initial stock added successfully!',
                'icon' => 'success'
            );
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch(PDOException $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
    }
}

// Process edit action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_movement'])) {
    $movement_id = $_POST['movement_id'];
    $quantity = $_POST['quantity'];
    $unit_cost = $_POST['unit_cost'];
    $movement_date = $_POST['movement_date'];
    $purchase_order = $_POST['purchase_order'] ?? '';
    $purchase_request = $_POST['purchase_request'] ?? '';
    
    try {
        // Get the original movement to calculate the difference
        $stmt = $pdo->prepare("SELECT * FROM stock_movements WHERE id = :id");
        $stmt->bindParam(':id', $movement_id);
        $stmt->execute();
        $original_movement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($original_movement) {
            $pdo->beginTransaction();
            
            // Update the movement record
            $updateStmt = $pdo->prepare("UPDATE stock_movements 
                                        SET quantity = :quantity, unit_cost = :unit_cost, 
                                            movement_date = :movement_date, 
                                            purchase_order = :purchase_order, 
                                            purchase_request = :purchase_request 
                                        WHERE id = :id");
            $updateStmt->bindParam(':quantity', $quantity);
            $updateStmt->bindParam(':unit_cost', $unit_cost);
            $updateStmt->bindParam(':movement_date', $movement_date);
            $updateStmt->bindParam(':purchase_order', $purchase_order);
            $updateStmt->bindParam(':purchase_request', $purchase_request);
            $updateStmt->bindParam(':id', $movement_id);
            $updateStmt->execute();
            
            // Calculate the quantity difference
            $quantity_diff = $quantity - $original_movement['quantity'];
            
            if ($quantity_diff != 0 || $unit_cost != $original_movement['unit_cost'] || 
                $purchase_order != $original_movement['purchase_order'] || 
                $purchase_request != $original_movement['purchase_request']) {
                
                if ($original_movement['movement_type'] === 'in') {
                    // For stock in, update the batch quantity and details
                    $batchStmt = $pdo->prepare("SELECT id FROM inventory_batches 
                                               WHERE item_id = :item_id AND warehouse_id = :warehouse_id 
                                               AND batch_number = :batch_number");
                    $batchStmt->bindParam(':item_id', $original_movement['item_id']);
                    $batchStmt->bindParam(':warehouse_id', $original_movement['warehouse_id']);
                    $batchStmt->bindParam(':batch_number', $original_movement['batch_number']);
                    $batchStmt->execute();
                    $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($batch) {
                        // Update batch quantity, unit cost, purchase order, and purchase request
                        $updateBatchStmt = $pdo->prepare("UPDATE inventory_batches 
                                                         SET quantity = quantity + :quantity_diff,
                                                             unit_cost = :unit_cost,
                                                             purchase_order = :purchase_order,
                                                             purchase_request = :purchase_request
                                                         WHERE id = :id");
                        $updateBatchStmt->bindParam(':quantity_diff', $quantity_diff);
                        $updateBatchStmt->bindParam(':unit_cost', $unit_cost);
                        $updateBatchStmt->bindParam(':purchase_order', $purchase_order);
                        $updateBatchStmt->bindParam(':purchase_request', $purchase_request);
                        $updateBatchStmt->bindParam(':id', $batch['id']);
                        $updateBatchStmt->execute();
                        
                        // Update inventory table
                        updateInventoryTable($pdo, $original_movement['item_id'], $original_movement['warehouse_id']);
                    }
                } else {
                    // For stock out, we need to handle this carefully
                    // This is a complex operation that might require additional logic
                    // For now, we'll just show a warning
                    $pdo->rollBack();
                    
                    // Store the error message in session for display after redirect
                    $_SESSION['swal_data'] = array(
                        'title' => 'Warning!',
                        'text' => 'Editing stock out quantities is complex and may affect inventory accuracy. Consider deleting and recreating the movement instead.',
                        'icon' => 'warning'
                    );
                    
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }
            }
            
            $pdo->commit();
            
            // Store success message in session for display after redirect
            $_SESSION['swal_data'] = array(
                'title' => 'Success!',
                'text' => 'Stock movement updated successfully!',
                'icon' => 'success'
            );
            
            // Refresh the page to show updated data
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            // Store error message in session for display after redirect
            $_SESSION['swal_data'] = array(
                'title' => 'Error!',
                'text' => 'Stock movement not found.',
                'icon' => 'error'
            );
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } catch(PDOException $e) {
        $pdo->rollBack();
        
        // Store error message in session for display after redirect
        $_SESSION['swal_data'] = array(
            'title' => 'Database Error!',
            'text' => 'Database error: ' . $e->getMessage(),
            'icon' => 'error'
        );
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Update all inventory records at the start to ensure data consistency
updateAllInventory($pdo);

// Fetch data for dropdowns and tables
try {
    // Get items with minimum stock level
    $itemsStmt = $pdo->prepare("SELECT id, item_code, item_name, min_stock_level FROM item_names ORDER BY item_name");
    $itemsStmt->execute();
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get suppliers
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name");
    $suppliersStmt->execute();
    $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get projects
    $projectsStmt = $pdo->prepare("SELECT id, project_name, threshold_amount FROM projects ORDER BY project_name");
    $projectsStmt->execute();
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get subcons
    $subconsStmt = $pdo->prepare("SELECT id, subcon_name FROM subcons ORDER BY subcon_name");
    $subconsStmt->execute();
    $subcons = $subconsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get warehouses
    $warehousesStmt = $pdo->prepare("SELECT id, warehouse_name, location FROM warehouses ORDER by warehouse_name");
    $warehousesStmt->execute();
    $warehouses = $warehousesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get inventory batches for FIFO tracking
    $batchesStmt = $pdo->prepare("
        SELECT ib.*, i.item_code, i.item_name, w.warehouse_name, w.location, s.supplier_name
        FROM inventory_batches ib
        JOIN item_names i ON ib.item_id = i.id
        JOIN warehouses w ON ib.warehouse_id = w.id
        LEFT JOIN suppliers s ON ib.supplier_id = s.id
        WHERE ib.quantity > 0
        ORDER BY ib.item_id, ib.warehouse_id, ib.received_date DESC, ib.id DESC
    ");
    $batchesStmt->execute();
    $inventory_batches = $batchesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get inventory summary from inventory table (now reading from actual inventory table)
    $inventoryStmt = $pdo->prepare("
        SELECT i.id, i.item_code, i.item_name, i.min_stock_level, 
               w.warehouse_name, w.location, 
               inv.quantity,
               inv.unit_cost,
               inv.total_value,
               CASE 
                 WHEN i.min_stock_level > 0 AND inv.quantity <= 0 THEN 'out-of-stock'
                 WHEN i.min_stock_level > 0 AND inv.quantity <= i.min_stock_level THEN 'low-stock'
                 ELSE 'normal'
               END AS stock_status
        FROM inventory inv
        JOIN item_names i ON inv.item_id = i.id
        JOIN warehouses w ON inv.warehouse_id = w.id
        WHERE inv.quantity > 0
        ORDER BY i.item_name, w.warehouse_name
    ");
    $inventoryStmt->execute();
    $inventory = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get low stock alerts (from inventory table)
    $lowStockStmt = $pdo->prepare("
        SELECT i.id, i.item_code, i.item_name, i.min_stock_level, 
               w.warehouse_name, w.location, inv.quantity, 
               inv.unit_cost, inv.total_value
        FROM inventory inv
        JOIN item_names i ON inv.item_id = i.id
        JOIN warehouses w ON inv.warehouse_id = w.id
        WHERE i.min_stock_level > 0 AND inv.quantity <= i.min_stock_level
        ORDER BY inv.quantity ASC, i.item_name
    ");
    $lowStockStmt->execute();
    $low_stock_items = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent stock movements
    $movementsStmt = $pdo->prepare("
        SELECT sm.*, i.item_code, i.item_name, 
               s.supplier_name, p.project_name, 
               sc.subcon_name,
               w.warehouse_name,
               sm.transfer_from, sm.transfer_to,
               w_from.warehouse_name as from_warehouse_name,
               w_to.warehouse_name as to_warehouse_name
        FROM stock_movements sm
        JOIN item_names i ON sm.item_id = i.id
        LEFT JOIN suppliers s ON sm.supplier_id = s.id
        LEFT JOIN projects p ON sm.project_id = p.id
        LEFT JOIN subcons sc ON sm.subcon_id = sc.id
        LEFT JOIN warehouses w ON sm.warehouse_id = w.id
        LEFT JOIN warehouses w_from ON sm.transfer_from = w_from.id
        LEFT JOIN warehouses w_to ON sm.transfer_to = w_to.id
        ORDER BY sm.movement_date DESC, sm.created_at DESC
        LIMIT 50
    ");
    $movementsStmt->execute();
    $movements = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate a new batch number for stock in form
    $new_batch_number = 'BATCH-' . date('YmdHis');
} catch(PDOException $e) {
    $swal_data = array(
        'title' => 'Error!',
        'text' => 'Error fetching data: ' . $e->getMessage(),
        'icon' => 'error'
    );
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Inventory Management - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            .table-responsive {
                overflow-x: auto;
            }
            .dataTable-top {
                padding: 8px 0;
            }
            .dataTable-bottom {
                padding: 8px 0;
            }
            .inventory-table th, .movements-table th {
                background-color: #f8f9fa;
                font-weight: 600;
            }
            .text-success {
                color: #198754 !important;
            }
            .text-danger {
                color: #dc3545 !important;
            }
            .text-warning {
                color: #fd7e14 !important;
            }
            .card-title {
                font-size: 1.1rem;
                font-weight: 600;
            }
            .stock-out {
                background-color: #ffcccc !important;
            }
            .stock-low {
                background-color: #fff3cd !important;
            }
            .badge-out-of-stock {
                background-color: #dc3545;
                color: white;
            }
            .badge-low-stock {
                background-color: #fd7e14;
                color: white;
            }
            .badge-normal-stock {
                background-color: #198754;
                color: white;
            }
            .form-floating > .form-select {
                padding-top: 1.625rem;
                padding-bottom: 0.625rem;
            }
            .btn-group .btn:last-child {
                margin-right: 5px;
            }
            .action-card {
                transition: transform 0.2s ease-in-out;
                height: 100%;
            }
            .action-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }
            .action-card .card-body {
                padding: 1.5rem;
            }
            .action-icon {
                font-size: 2rem;
                margin-bottom: 1rem;
            }
            /* Fix for DataTables search */
            .dataTable-input {
                padding: 0.375rem 0.75rem;
                border: 1px solid #ced4da;
                border-radius: 0.375rem;
            }
            /* Ensure action buttons work after search */
            .datatable-table td .btn-group {
                white-space: nowrap;
            }
            /* Custom search styles */
            .search-container {
                margin-bottom: 1rem;
            }
            .search-box {
                max-width: 400px;
                margin-left: auto;
            }
            .dataTable-top {
                display: none !important;
            }
            .dataTable-bottom {
                display: none !important;
            }
            /* Enhanced Select2 dropdown with more height */
            .select2-container--bootstrap-5 .select2-dropdown {
                border-color: #dee2e6;
                box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
                max-height: 500px !important; /* Increased max height */
                overflow-y: auto;
            }

            .select2-container--bootstrap-5 .select2-results__options {
                max-height: 450px !important; /* Increased results height to show more items */
                overflow-y: auto;
            }

            /* Make the dropdown wider */
            .select2-dropdown--large {
                min-width: 450px !important;
                max-width: 600px !important;
            }

            /* Better option styling */
            .select2-container--bootstrap-5 .select2-results__option {
                padding: 0.6rem 1rem;
                font-size: 0.95rem;
                line-height: 1.5;
                border-bottom: 1px solid #f0f0f0;
            }

            .select2-container--bootstrap-5 .select2-results__option:last-child {
                border-bottom: none;
            }

            .select2-container--bootstrap-5 .select2-results__option--highlighted {
                background-color: #0d6efd;
                color: white;
            }

            /* Search box styling */
            .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
                padding: 0.6rem 0.75rem;
                border-radius: 0.375rem;
                border: 1px solid #dee2e6;
                font-size: 0.95rem;
            }

            .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field:focus {
                border-color: #86b7fe;
                box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
                outline: none;
            }

            /* Selection box styling */
            .select2-container--bootstrap-5 .select2-selection--single {
                height: auto !important;
                min-height: 42px;
                padding: 0.375rem 0.75rem;
                border: 1px solid #dee2e6;
                border-radius: 0.375rem;
            }

            .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
                line-height: 30px;
                padding-left: 0;
                color: #212529;
            }

            .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
                height: 40px;
            }

            .select2-container--bootstrap-5 .select2-selection--single .select2-selection__placeholder {
                color: #6c757d;
            }

            /* Loading and no results styling */
            .select2-container--bootstrap-5 .select2-results__message {
                padding: 1rem;
                color: #6c757d;
                font-style: italic;
            }

            /* Custom scrollbar for the dropdown */
            .select2-container--bootstrap-5 .select2-results__options::-webkit-scrollbar {
                width: 8px;
            }

            .select2-container--bootstrap-5 .select2-results__options::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }

            .select2-container--bootstrap-5 .select2-results__options::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 4px;
            }

            .select2-container--bootstrap-5 .select2-results__options::-webkit-scrollbar-thumb:hover {
                background: #555;
            }
        </style>
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Warehouse Inventory Management</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Inventory</li>
                        </ol>
                        
                        <!-- Low Stock Alerts -->
                        <?php if (!empty($low_stock_items)): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h5>
                            <p>The following items are below their minimum stock levels:</p>
                            <ul class="mb-0">
                                <?php foreach ($low_stock_items as $item): 
                                    $status = $item['quantity'] <= 0 ? 'out-of-stock' : 'low-stock';
                                ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($item['item_code'] . ' - ' . $item['item_name']); ?></strong> 
                                    at <?php echo htmlspecialchars($item['warehouse_name']); ?>: 
                                    <?php echo $item['quantity']; ?> in stock 
                                    (Min: <?php echo $item['min_stock_level']; ?>)
                                    <span class="badge badge-<?php echo $status === 'out-of-stock' ? 'out-of-stock' : 'low-stock'; ?>">
                                        <?php echo $status === 'out-of-stock' ? 'Out of Stock' : 'Low Stock'; ?>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Quick Actions Cards -->
                        <div class="row mb-4">
                            <!-- Stock In Actions -->
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card bg-primary text-white action-card">
                                    <div class="card-body text-center">
                                        <div class="action-icon">
                                            <i class="fas fa-arrow-down"></i>
                                        </div>
                                        <h5 class="card-title">Stock In Operations</h5>
                                        <p class="card-text">Manage incoming inventory</p>
                                        <div class="d-grid gap-2 d-md-block mt-3">
                                            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#initialStockModal">
                                                <i class="fas fa-boxes me-1"></i> Initial Stock
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Stock Out Actions -->
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card bg-warning text-dark action-card">
                                    <div class="card-body text-center">
                                        <div class="action-icon">
                                            <i class="fas fa-arrow-up"></i>
                                        </div>
                                        <h5 class="card-title">Stock Out Operations</h5>
                                        <p class="card-text">Issue inventory to projects and subcontractors using FIFO</p>
                                        <div class="d-grid gap-2 d-md-block mt-3">
                                            <button class="btn btn-dark btn-sm" data-bs-toggle="modal" data-bs-target="#stockOutSubconModal">
                                                <i class="fas fa-hard-hat me-1"></i> With Subcon
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Transfer & Management Actions -->
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card bg-success text-white action-card">
                                    <div class="card-body text-center">
                                        <div class="action-icon">
                                            <i class="fas fa-exchange-alt"></i>
                                        </div>
                                        <h5 class="card-title">Transfer & Management</h5>
                                        <p class="card-text">Transfer between warehouses</p>
                                        <div class="d-grid gap-2 d-md-block mt-3">
                                            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#transferModal">
                                                <i class="fas fa-warehouse me-1"></i> Transfer Stock
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Inventory Summary -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    Current Inventory Summary (From Inventory Table)
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="search-container">
                                    <div class="input-group search-box">
                                        <input type="text" class="form-control" id="inventorySearch" placeholder="Search inventory...">
                                    </div>
                                </div>
                                <?php if (!empty($inventory)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover inventory-table" id="inventoryTable">
                                        <thead>
                                            <tr>
                                                <th>Item Code</th>
                                                <th>Item Name</th>
                                                <th>Warehouse</th>
                                                <th>Location</th>
                                                <th>Quantity</th>
                                                <th>Min Stock</th>
                                                <th>Status</th>
                                                <th>Avg Unit Cost</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($inventory as $item): 
                                                $total_value = $item['quantity'] * $item['unit_cost'];
                                                $row_class = '';
                                                $status_badge = '';
                                                
                                                if ($item['stock_status'] === 'out-of-stock') {
                                                    $row_class = 'stock-out';
                                                    $status_badge = '<span class="badge badge-out-of-stock">Out of Stock</span>';
                                                } elseif ($item['stock_status'] === 'low-stock') {
                                                    $row_class = 'stock-low';
                                                    $status_badge = '<span class="badge badge-low-stock">Low Stock</span>';
                                                } else {
                                                    $status_badge = '<span class="badge badge-normal-stock">Normal</span>';
                                                }
                                            ?>
                                            <tr class="<?php echo $row_class; ?>">
                                                <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['warehouse_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['location']); ?></td>
                                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                                <td><?php echo $item['min_stock_level'] > 0 ? $item['min_stock_level'] : 'Not Set'; ?></td>
                                                <td><?php echo $status_badge; ?></td>
                                                <td>₱<?php echo number_format($item['unit_cost'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No inventory data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Inventory Batches (FIFO Tracking) -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-layer-group me-1"></i>
                                    Inventory Batches (FIFO Tracking)
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="search-container">
                                    <div class="input-group search-box">
                                        <input type="text" class="form-control" id="batchesSearch" placeholder="Search batches...">
                                    </div>
                                </div>
                                <?php if (!empty($inventory_batches)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="batchesTable">
                                        <thead>
                                            <tr>
                                                <th>Date Received</th>
                                                <th>Item Code</th>
                                                <th>Item Name</th>
                                                <th>Warehouse</th>
                                                <th>Location</th>
                                                <th>Quantity</th>
                                                <th>Unit Cost</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($inventory_batches as $batch): 
                                                $total_value = $batch['quantity'] * $batch['unit_cost'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($batch['received_date']); ?></td>
                                                <td><?php echo htmlspecialchars($batch['item_code']); ?></td>
                                                <td><?php echo htmlspecialchars($batch['item_name']); ?></td>
                                                <td><?php echo htmlspecialchars($batch['warehouse_name']); ?></td>
                                                <td><?php echo htmlspecialchars($batch['location']); ?></td>
                                                <td><?php echo number_format($batch['quantity'], 2); ?></td>
                                                <td>₱<?php echo number_format($batch['unit_cost'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No inventory batches available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Stock Movements -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    Recent Stock Movements
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="search-container">
                                    <div class="input-group search-box">
                                        <input type="text" class="form-control" id="movementsSearch" placeholder="Search movements...">
                                    </div>
                                </div>
                                <?php if (!empty($movements)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover movements-table" id="movementsTable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Item</th>
                                                <th>Type</th>
                                                <th>Supplier/Project/Subcon</th>
                                                <th>Warehouse</th>
                                                <th>Quantity</th>
                                                <th>Unit Cost</th>
                                                <th>Total Value</th>
                                                <th>Purchase Order</th>
                                                <th>Purchase Request</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($movements as $movement): 
                                                $movement_type = $movement['movement_type'];
                                                $type_class = $movement_type === 'in' ? 'text-success' : 'text-danger';
                                                $type_icon = $movement_type === 'in' ? 'fa-arrow-down' : 'fa-arrow-up';
                                                
                                                // Determine source/target information
                                                $source_target = '';
                                                if ($movement_type === 'in') {
                                                    if ($movement['supplier_name']) {
                                                        $source_target = 'From: ' . $movement['supplier_name'];
                                                    } elseif ($movement['transfer_from']) {
                                                        $source_target = 'Transfer From: ' . $movement['from_warehouse_name'];
                                                    } else {
                                                        $source_target = 'From: Initial Stock';
                                                    }
                                                } else {
                                                    if ($movement['project_name'] && $movement['subcon_name']) {
                                                        $source_target = 'To: ' . $movement['project_name'] . ' (Subcon: ' . $movement['subcon_name'] . ')';
                                                    } elseif ($movement['project_name']) {
                                                        $source_target = 'To: ' . $movement['project_name'];
                                                    } elseif ($movement['subcon_name']) {
                                                        $source_target = 'To: Subcon: ' . $movement['subcon_name'];
                                                    } elseif ($movement['transfer_to']) {
                                                        $source_target = 'Transfer To: ' . $movement['to_warehouse_name'];
                                                    } else {
                                                        $source_target = 'To: Unknown';
                                                    }
                                                }
                                                
                                                // Determine warehouse information
                                                $warehouse_info = '';
                                                if ($movement['transfer_from'] || $movement['transfer_to']) {
                                                    // This is a transfer operation
                                                    if ($movement_type === 'in') {
                                                        // For incoming transfers, show the destination warehouse with "To: " prefix
                                                        $warehouse_info = 'To: ' . ($movement['warehouse_name'] ?? 'Unknown');
                                                    } else {
                                                        // For outgoing transfers, show the source warehouse with "From: " prefix
                                                        $warehouse_info = 'From: ' . ($movement['warehouse_name'] ?? 'Unknown');
                                                    }
                                                } else {
                                                    // Regular stock in/out operations
                                                    if ($movement_type === 'in') {
                                                        $warehouse_info = 'To: ' . ($movement['warehouse_name'] ?? 'Unknown');
                                                    } else {
                                                        $warehouse_info = 'From: ' . ($movement['warehouse_name'] ?? 'Unknown');
                                                    }
                                                }
                                                
                                                $total_value = $movement['quantity'] * $movement['unit_cost'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($movement['movement_date']); ?></td>
                                                <td><?php echo htmlspecialchars($movement['item_code'] . ' - ' . $movement['item_name']); ?></td>
                                                <td class="<?php echo $type_class; ?>">
                                                    <i class="fas <?php echo $type_icon; ?>"></i> 
                                                    <?php echo strtoupper($movement_type); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($source_target); ?></td>
                                                <td><?php echo htmlspecialchars($warehouse_info); ?></td>
                                                <td><?php echo htmlspecialchars($movement['quantity']); ?></td>
                                                <td>₱<?php echo number_format($movement['unit_cost'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                                <td><?php echo htmlspecialchars($movement['purchase_order'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($movement['purchase_request'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-info view-movement" data-id="<?php echo $movement['id']; ?>" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-warning edit-movement" data-id="<?php echo $movement['id']; ?>" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger delete-movement" data-id="<?php echo $movement['id']; ?>" data-description="<?php echo htmlspecialchars($movement['item_code'] . ' - ' . $movement['item_name'] . ' (' . $movement['movement_date'] . ')'); ?>" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No stock movements recorded yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Initial Stock Modal -->
        <div class="modal fade" id="initialStockModal" tabindex="-1" aria-labelledby="initialStockModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="initialStockModalLabel">Add Initial Stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="initial_stock">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="initial_item_id" class="form-label">Item <span class="text-danger">*</span></label>
                                <select class="form-select select2-search" id="initial_item_id" name="item_id" required style="width: 100%;">
                                    <option value="">Search for an item...</option>
                                    <?php foreach ($items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['item_code'] . ' - ' . $item['item_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="initial_warehouse_id" class="form-label">Warehouse <span class="text-danger">*</span></label>
                                <select class="form-select" id="initial_warehouse_id" name="warehouse_id" required>
                                    <option value="">Select Warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo $warehouse['id']; ?>">
                                        <?php echo htmlspecialchars($warehouse['warehouse_name'] . ' - ' . $warehouse['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="initial_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="initial_quantity" name="quantity" min="1" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="initial_unit_cost" class="form-label">Unit Cost (₱) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="initial_unit_cost" name="unit_cost" step="0.01" min="0" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="date_added" class="form-label">Date Added <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_added" name="date_added" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Initial Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Stock Out to Project with Subcon Modal -->
        <div class="modal fade" id="stockOutSubconModal" tabindex="-1" aria-labelledby="stockOutSubconModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="stockOutSubconModalLabel">Stock Out to Project with Subcon (FIFO)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="stock_out_subcon">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="subcon_item_id" class="form-label">Item <span class="text-danger">*</span></label>
                                <select class="form-select select2-search" id="subcon_item_id" name="item_id" required style="width: 100%;">
                                    <option value="">Search for an item...</option>
                                    <?php foreach ($items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['item_code'] . ' - ' . $item['item_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subcon_project_id" class="form-label">Project <span class="text-danger">*</span></label>
                                <select class="form-select" id="subcon_project_id" name="project_id" required>
                                    <option value="">Select Project</option>
                                    <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" data-threshold="<?php echo $project['threshold_amount']; ?>">
                                        <?php echo htmlspecialchars($project['project_name'] . ' (Threshold: ₱' . number_format($project['threshold_amount'], 2) . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subcon_id" class="form-label">Subcontractor <span class="text-danger">*</span></label>
                                <select class="form-select" id="subcon_id" name="subcon_id" required>
                                    <option value="">Select Subcontractor</option>
                                    <?php foreach ($subcons as $subcon): ?>
                                    <option value="<?php echo $subcon['id']; ?>">
                                        <?php echo htmlspecialchars($subcon['subcon_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subcon_warehouse_id" class="form-label">From Warehouse <span class="text-danger">*</span></label>
                                <select class="form-select" id="subcon_warehouse_id" name="warehouse_id" required>
                                    <option value="">Select Warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo $warehouse['id']; ?>">
                                        <?php echo htmlspecialchars($warehouse['warehouse_name'] . ' - ' . $warehouse['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subcon_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="subcon_quantity" name="quantity" min="1" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subcon_date_issued" class="form-label">Date Issued <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="subcon_date_issued" name="date_issued" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> The total cost will be deducted from the project's threshold amount.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Record Stock Out to Subcon</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Transfer Modal -->
        <div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="transferModalLabel">Transfer Between Warehouses (FIFO)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="transfer">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="transfer_item_id" class="form-label">Item <span class="text-danger">*</span></label>
                                <select class="form-select select2-search" id="transfer_item_id" name="item_id" required style="width: 100%;">
                                    <option value="">Search for an item...</option>
                                    <?php foreach ($items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['item_code'] . ' - ' . $item['item_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="from_warehouse_id" class="form-label">From Warehouse <span class="text-danger">*</span></label>
                                <select class="form-select" id="from_warehouse_id" name="from_warehouse_id" required>
                                    <option value="">Select Source Warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo $warehouse['id']; ?>">
                                        <?php echo htmlspecialchars($warehouse['warehouse_name'] . ' - ' . $warehouse['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="to_warehouse_id" class="form-label">To Warehouse <span class="text-danger">*</span></label>
                                <select class="form-select" id="to_warehouse_id" name="to_warehouse_id" required>
                                    <option value="">Select Destination Warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo $warehouse['id']; ?>">
                                        <?php echo htmlspecialchars($warehouse['warehouse_name'] . ' - ' . $warehouse['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="transfer_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="transfer_quantity" name="quantity" min="1" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="transfer_date" class="form-label">Transfer Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="transfer_date" name="transfer_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Transfer Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- View Movement Modal -->
        <div class="modal fade" id="viewMovementModal" tabindex="-1" aria-labelledby="viewMovementModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewMovementModalLabel">Stock Movement Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="movementDetails">
                        <!-- Details will be loaded via JavaScript -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Movement Modal -->
        <div class="modal fade" id="editMovementModal" tabindex="-1" aria-labelledby="editMovementModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editMovementModalLabel">Edit Stock Movement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editMovementForm">
                        <input type="hidden" name="edit_movement" value="1">
                        <input type="hidden" name="movement_id" id="edit_movement_id">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_item_info" readonly disabled>
                                <label for="edit_item_info">Item</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_movement_type" readonly disabled>
                                <label for="edit_movement_type">Movement Type</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_source_target" readonly disabled>
                                <label for="edit_source_target">Source/Target</label>
                            </div>
                            
                            <!-- Add Warehouse information -->
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_warehouse_info" readonly disabled>
                                <label for="edit_warehouse_info">Warehouse</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="edit_quantity" name="quantity" min="1" required>
                                <label for="edit_quantity">Quantity <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="edit_unit_cost" name="unit_cost" step="0.01" min="0" required>
                                <label for="edit_unit_cost">Unit Cost (₱) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="edit_movement_date" name="movement_date" required>
                                <label for="edit_movement_date">Movement Date <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_purchase_order" name="purchase_order">
                                <label for="edit_purchase_order">Purchase Order (Optional)</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_purchase_request" name="purchase_request">
                                <label for="edit_purchase_request">Purchase Request (Optional)</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="confirmEditBtn">Update Movement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Delete Movement Modal -->
        <div class="modal fade" id="deleteMovementModal" tabindex="-1" aria-labelledby="deleteMovementModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteMovementModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="delete_movement" value="1">
                        <input type="hidden" name="movement_id" id="delete_movement_id">
                        <div class="modal-body">
                            <p>Are you sure you want to delete this stock movement?</p>
                            <p><strong id="delete_movement_description"></strong></p>
                            <p class="text-danger">Warning: This action cannot be undone and will affect inventory levels.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Movement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script>
            // Initialize DataTables and Select2
            window.addEventListener('DOMContentLoaded', event => {
                // Initialize Select2 for all searchable dropdowns
                function initializeSelect2() {
                    // Common Select2 configuration
                    const select2Config = {
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Search for an item...',
                        allowClear: true,
                        dropdownAutoWidth: false,
                        minimumResultsForSearch: 1,
                        // Increase the number of visible options
                        dropdownCssClass: 'select2-dropdown--large'
                    };
                    
                    // Initialize with modal-specific dropdown parents
                    const modalConfigs = [
                        { selector: '#initialStockModal .select2-search', parent: '#initialStockModal' },
                        { selector: '#stockOutSubconModal .select2-search', parent: '#stockOutSubconModal' },
                        { selector: '#transferModal .select2-search', parent: '#transferModal' }
                    ];
                    
                    modalConfigs.forEach(config => {
                        $(config.selector).select2({
                            ...select2Config,
                            dropdownParent: $(config.parent)
                        });
                    });
                    
                    // Initialize any other select2-search elements not in modals
                    $('.select2-search').not('#initialStockModal .select2-search, #stockOutSubconModal .select2-search, #transferModal .select2-search').select2(select2Config);
                }
                
                initializeSelect2();
                
                const inventoryTable = document.getElementById('inventoryTable');
                if (inventoryTable) {
                    new simpleDatatables.DataTable(inventoryTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        searchable: false,
                        labels: {
                            placeholder: "Search...",
                            perPage: "{select} entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                const batchesTable = document.getElementById('batchesTable');
                if (batchesTable) {
                    new simpleDatatables.DataTable(batchesTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        searchable: false, 
                        labels: {
                            placeholder: "Search...",
                            perPage: "{select} entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                const movementsTable = document.getElementById('movementsTable');
                if (movementsTable) {
                    new simpleDatatables.DataTable(movementsTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        searchable: false, 
                        labels: {
                            placeholder: "Search...",
                            perPage: "{select} entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                // Hide DataTables search boxes
                document.querySelectorAll('.dataTable-input').forEach(input => {
                    input.style.display = 'none';
                });
                document.querySelectorAll('.dataTable-top').forEach(top => {
                    top.style.display = 'none';
                });
                document.querySelectorAll('.dataTable-bottom').forEach(bottom => {
                    bottom.style.display = 'none';
                });

                // Real-time search functionality
                function setupRealTimeSearch(searchInputId, tableId) {
                    const searchInput = document.getElementById(searchInputId);
                    const table = document.getElementById(tableId);
                    
                    if (!searchInput || !table) return;
                    
                    searchInput.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase().trim();
                        const rows = table.querySelectorAll('tbody tr');
                        
                        rows.forEach(row => {
                            const rowText = row.textContent.toLowerCase();
                            if (rowText.includes(searchTerm)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    });
                    
                    // Clear search functionality
                    const clearButton = document.getElementById('clear' + searchInputId.replace('Search', 'Search'));
                    if (clearButton) {
                        clearButton.addEventListener('click', function() {
                            searchInput.value = '';
                            const rows = table.querySelectorAll('tbody tr');
                            rows.forEach(row => {
                                row.style.display = '';
                            });
                        });
                    }
                }
                
                // Setup real-time search for all tables
                setupRealTimeSearch('inventorySearch', 'inventoryTable');
                setupRealTimeSearch('batchesSearch', 'batchesTable');
                setupRealTimeSearch('movementsSearch', 'movementsTable');
                
                // Show SweetAlert2 notifications
                <?php if (!empty($swal_data)): ?>
                    Swal.fire({
                        title: '<?php echo $swal_data['title']; ?>',
                        text: '<?php echo $swal_data['text']; ?>',
                        icon: '<?php echo $swal_data['icon']; ?>',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        // If it was an error and we need to show a specific modal
                        <?php if (isset($_POST['action']) && $swal_data['icon'] === 'error'): ?>
                            <?php if ($_POST['action'] === 'stock_in'): ?>
                                var stockInModal = new bootstrap.Modal(document.getElementById('stockInModal'));
                                stockInModal.show();
                            <?php elseif ($_POST['action'] === 'stock_out'): ?>
                                var stockOutModal = new bootstrap.Modal(document.getElementById('stockOutModal'));
                                stockOutModal.show();
                            <?php elseif ($_POST['action'] === 'stock_out_subcon'): ?>
                                var stockOutSubconModal = new bootstrap.Modal(document.getElementById('stockOutSubconModal'));
                                stockOutSubconModal.show();
                                // Reinitialize Select2 when modal is shown
                                $('#stockOutSubconModal').on('shown.bs.modal', function () {
                                    $('#subcon_item_id').select2({
                                        theme: 'bootstrap-5',
                                        width: '100%',
                                        placeholder: 'Search for an item...',
                                        allowClear: true,
                                        dropdownParent: $('#stockOutSubconModal')
                                    });
                                });
                            <?php elseif ($_POST['action'] === 'transfer'): ?>
                                var transferModal = new bootstrap.Modal(document.getElementById('transferModal'));
                                transferModal.show();
                                // Reinitialize Select2 when modal is shown
                                $('#transferModal').on('shown.bs.modal', function () {
                                    $('#transfer_item_id').select2({
                                        theme: 'bootstrap-5',
                                        width: '100%',
                                        placeholder: 'Search for an item...',
                                        allowClear: true,
                                        dropdownParent: $('#transferModal')
                                    });
                                });
                            <?php elseif ($_POST['action'] === 'set_min_stock'): ?>
                                var minStockModal = new bootstrap.Modal(document.getElementById('minStockModal'));
                                minStockModal.show();
                            <?php elseif ($_POST['action'] === 'initial_stock'): ?>
                                var initialStockModal = new bootstrap.Modal(document.getElementById('initialStockModal'));
                                initialStockModal.show();
                                // Reinitialize Select2 when modal is shown
                                $('#initialStockModal').on('shown.bs.modal', function () {
                                    $('#initial_item_id').select2({
                                        theme: 'bootstrap-5',
                                        width: '100%',
                                        placeholder: 'Search for an item...',
                                        allowClear: true,
                                        dropdownParent: $('#initialStockModal')
                                    });
                                });
                            <?php elseif (isset($_POST['edit_movement'])): ?>
                                var editModal = new bootstrap.Modal(document.getElementById('editMovementModal'));
                                editModal.show();
                            <?php endif; ?>
                        <?php endif; ?>
                    });
                <?php endif; ?>
                
                // Show modal if there was an error with form submission
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($swal_data['icon']) && $swal_data['icon'] === 'error'): ?>
                    <?php if ($_POST['action'] === 'stock_in'): ?>
                        var stockInModal = new bootstrap.Modal(document.getElementById('stockInModal'));
                        stockInModal.show();
                    <?php elseif ($_POST['action'] === 'stock_out'): ?>
                        var stockOutModal = new bootstrap.Modal(document.getElementById('stockOutModal'));
                        stockOutModal.show();
                    <?php elseif ($_POST['action'] === 'stock_out_subcon'): ?>
                        var stockOutSubconModal = new bootstrap.Modal(document.getElementById('stockOutSubconModal'));
                        stockOutSubconModal.show();
                        // Reinitialize Select2 when modal is shown
                        $('#stockOutSubconModal').on('shown.bs.modal', function () {
                            $('#subcon_item_id').select2({
                                theme: 'bootstrap-5',
                                width: '100%',
                                placeholder: 'Search for an item...',
                                allowClear: true,
                                dropdownParent: $('#stockOutSubconModal')
                            });
                        });
                    <?php elseif ($_POST['action'] === 'transfer'): ?>
                        var transferModal = new bootstrap.Modal(document.getElementById('transferModal'));
                        transferModal.show();
                        // Reinitialize Select2 when modal is shown
                        $('#transferModal').on('shown.bs.modal', function () {
                            $('#transfer_item_id').select2({
                                theme: 'bootstrap-5',
                                width: '100%',
                                placeholder: 'Search for an item...',
                                allowClear: true,
                                dropdownParent: $('#transferModal')
                            });
                        });
                    <?php elseif ($_POST['action'] === 'set_min_stock'): ?>
                        var minStockModal = new bootstrap.Modal(document.getElementById('minStockModal'));
                        minStockModal.show();
                    <?php elseif ($_POST['action'] === 'initial_stock'): ?>
                        var initialStockModal = new bootstrap.Modal(document.getElementById('initialStockModal'));
                        initialStockModal.show();
                        // Reinitialize Select2 when modal is shown
                        $('#initialStockModal').on('shown.bs.modal', function () {
                            $('#initial_item_id').select2({
                                theme: 'bootstrap-5',
                                width: '100%',
                                placeholder: 'Search for an item...',
                                allowClear: true,
                                dropdownParent: $('#initialStockModal')
                            });
                        });
                    <?php endif; ?>
                <?php endif; ?>
                
                // Function to attach event listeners to action buttons
                function attachEventListeners() {
                    // View movement details
                    document.querySelectorAll('.view-movement').forEach(button => {
                        button.addEventListener('click', function() {
                            const movementId = this.getAttribute('data-id');
                            
                            // Fetch movement details via AJAX
                            fetch('get_movement_details.php?id=' + movementId)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        const movement = data.movement;
                                        let detailsHtml = `
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Date:</strong> ${movement.movement_date}
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Type:</strong> ${movement.movement_type.toUpperCase()}
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <strong>Item:</strong> ${movement.item_code} - ${movement.item_name}
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Quantity:</strong> ${movement.quantity}
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Unit Cost:</strong> ₱${parseFloat(movement.unit_cost).toFixed(2)}
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Total Value:</strong> ₱${(movement.quantity * movement.unit_cost).toFixed(2)}
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Batch Number:</strong> ${movement.batch_number || 'N/A'}
                                                </div>
                                            </div>
                                        `;
                                        
                                        if (movement.movement_type === 'in') {
                                            if (movement.supplier_name) {
                                                detailsHtml += `
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Supplier:</strong> ${movement.supplier_name}
                                                        </div>
                                                    </div>
                                                `;
                                            } else if (movement.transfer_from) {
                                                detailsHtml += `
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Transfer From:</strong> ${movement.from_warehouse_name}
                                                        </div>
                                                    </div>
                                                `;
                                            }
                                        } else {
                                            if (movement.project_name && movement.subcon_name) {
                                                detailsHtml += `
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Project:</strong> ${movement.project_name}
                                                        </div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Subcontractor:</strong> ${movement.subcon_name}
                                                        </div>
                                                    </div>
                                                `;
                                            } else if (movement.project_name) {
                                                detailsHtml += `
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Project:</strong> ${movement.project_name}
                                                        </div>
                                                    </div>
                                                `;
                                            } else if (movement.subcon_name) {
                                                detailsHtml += `
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Subcontractor:</strong> ${movement.subcon_name}
                                                        </div>
                                                    </div>
                                                `;
                                            } else if (movement.transfer_to) {
                                                detailsHtml += `
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Transfer To:</strong> ${movement.to_warehouse_name}
                                                        </div>
                                                    </div>
                                                `;
                                            }
                                        }
                                        
                                        detailsHtml += `
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Warehouse:</strong> ${movement.warehouse_name || 'N/A'}
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Location:</strong> ${movement.location || 'N/A'}
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Purchase Order:</strong> ${movement.purchase_order || 'N/A'}
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Purchase Request:</strong> ${movement.purchase_request || 'N/A'}
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <strong>Created At:</strong> ${movement.created_at}
                                                </div>
                                            </div>
                                        `;
                                        
                                        document.getElementById('movementDetails').innerHTML = detailsHtml;
                                        const viewModal = new bootstrap.Modal(document.getElementById('viewMovementModal'));
                                        viewModal.show();
                                    } else {
                                        Swal.fire({
                                            title: 'Error!',
                                            text: 'Failed to load movement details.',
                                            icon: 'error',
                                            confirmButtonText: 'OK'
                                        });
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    Swal.fire({
                                        title: 'Error!',
                                        text: 'Failed to load movement details.',
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                });
                        });
                    });
                    
                    // Edit movement functionality
                    document.querySelectorAll('.edit-movement').forEach(button => {
                        button.addEventListener('click', function() {
                            const movementId = this.getAttribute('data-id');
                            
                            // Fetch movement details via AJAX
                            fetch('get_movement_details.php?id=' + movementId)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        const movement = data.movement;
                                        
                                        // Populate the edit form
                                        document.getElementById('edit_movement_id').value = movement.id;
                                        document.getElementById('edit_item_info').value = movement.item_code + ' - ' + movement.item_name;
                                        document.getElementById('edit_movement_type').value = movement.movement_type.toUpperCase();
                                        
                                        // Determine source/target information
                                        let sourceTarget = '';
                                        if (movement.movement_type === 'in') {
                                            if (movement.supplier_name) {
                                                sourceTarget = 'From: ' + movement.supplier_name;
                                            } else if (movement.transfer_from) {
                                                sourceTarget = 'Transfer From: ' + movement.from_warehouse_name;
                                            } else {
                                                sourceTarget = 'From: Initial Stock';
                                            }
                                        } else {
                                            if (movement.project_name && movement.subcon_name) {
                                                sourceTarget = 'To: ' + movement.project_name + ' (Subcon: ' + movement.subcon_name + ')';
                                            } else if (movement.project_name) {
                                                sourceTarget = 'To: ' + movement.project_name;
                                            } else if (movement.subcon_name) {
                                                sourceTarget = 'To: Subcon: ' + movement.subcon_name;
                                            } else if (movement.transfer_to) {
                                                sourceTarget = 'Transfer To: ' + movement.to_warehouse_name;
                                            } else {
                                                sourceTarget = 'To: Unknown';
                                            }
                                        }
                                        
                                        document.getElementById('edit_source_target').value = sourceTarget;
                                        
                                        // Add warehouse information
                                        let warehouseInfo = '';
                                        if (movement.transfer_from || movement.transfer_to) {
                                            // This is a transfer operation
                                            if (movement.movement_type === 'in') {
                                                // For incoming transfers, show the destination warehouse with "To: " prefix
                                                warehouseInfo = 'To: ' + (movement.warehouse_name || 'Unknown');
                                            } else {
                                                // For outgoing transfers, show the source warehouse with "From: " prefix
                                                warehouseInfo = 'From: ' + (movement.warehouse_name || 'Unknown');
                                            }
                                        } else {
                                            // Regular stock in/out operations
                                            if (movement.movement_type === 'in') {
                                                warehouseInfo = 'To: ' + (movement.warehouse_name || 'Unknown');
                                            } else {
                                                warehouseInfo = 'From: ' + (movement.warehouse_name || 'Unknown');
                                            }
                                        }

                                        document.getElementById('edit_warehouse_info').value = warehouseInfo;
                                        
                                        document.getElementById('edit_quantity').value = movement.quantity;
                                        document.getElementById('edit_unit_cost').value = movement.unit_cost;
                                        document.getElementById('edit_movement_date').value = movement.movement_date;
                                        document.getElementById('edit_purchase_order').value = movement.purchase_order || '';
                                        document.getElementById('edit_purchase_request').value = movement.purchase_request || '';
                                        
                                        // Show the edit modal
                                        const editModal = new bootstrap.Modal(document.getElementById('editMovementModal'));
                                        editModal.show();
                                    } else {
                                        Swal.fire({
                                            title: 'Error!',
                                            text: 'Failed to load movement details for editing.',
                                            icon: 'error',
                                            confirmButtonText: 'OK'
                                        });
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    Swal.fire({
                                        title: 'Error!',
                                        text: 'Failed to load movement details for editing.',
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                });
                        });
                    });

                    // Confirm edit with SweetAlert2
                    document.getElementById('confirmEditBtn').addEventListener('click', function() {
                        Swal.fire({
                            title: 'Are you sure?',
                            text: 'You are about to update this stock movement. This action will also update the inventory batches.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, update it!'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                Swal.fire({
                                    title: "Updated!",
                                    text: "The stock movement has been updated successfully.",
                                    icon: "success"
                                }).then(() => {
                                    document.getElementById('editMovementForm').submit();
                                });
                            }
                        });
                    });
                    
                    // Delete movement
                    document.querySelectorAll('.delete-movement').forEach(button => {
                        button.addEventListener('click', function() {
                            const movementId = this.getAttribute('data-id');
                            const movementDescription = this.getAttribute('data-description');
                            
                            document.getElementById('delete_movement_id').value = movementId;
                            document.getElementById('delete_movement_description').textContent = movementDescription;
                            
                            const deleteModal = new bootstrap.Modal(document.getElementById('deleteMovementModal'));
                            deleteModal.show();
                        });
                    });
                }
                
                // Attach event listeners initially
                attachEventListeners();
                
                // Re-attach event listeners when DataTables redraws (after search, pagination, etc.)
                if (movementsTable) {
                    movementsTable.addEventListener('datatable.init', function() {
                        setTimeout(attachEventListeners, 100);
                    });
                }
            });
            
            // Logout function with SweetAlert2
            document.getElementById('logoutLink').addEventListener('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'You want to logout from the system.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, logout!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'action/logout.php';
                    }
                });
            });
            
            // Simple sidebar toggle functionality
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.body.classList.toggle('sb-sidenav-toggled');
            });
        </script>
    </body>
</html>