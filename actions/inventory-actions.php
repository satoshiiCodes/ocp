<?php
/**
 * actions/inventory-actions.php
 *
 * Every action for inventory.php lives in this one file: deleting a movement, the
 * stock movements themselves (in, out, transfer and adjustment), and editing a
 * movement.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. Each branch
 * reports through $swal_data and redirects or re-renders, so the messages the page
 * shows are unchanged.
 *
 * The helpers it calls live in includes/inventory-functions.php, required here so they
 * are defined whichever entry point runs first.
 *
 * The blocks below are lifted verbatim from inventory.php: the queries, the stock
 * arithmetic and the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_INVENTORY_ACTIONS_RAN')) {
    return;
}
define('OCP_INVENTORY_ACTIONS_RAN', true);

require_once __DIR__ . '/../includes/inventory-functions.php';

// The message state the markup reads, filled in below.
$message = '';
$message_type = '';
$swal_data = array();

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

// The movement block below handles these six values, and its first act is to read
// $_POST['action'] without a default - so it is entered only when one of them is
// present. A post that carries none of them (the page's own JavaScript always sends
// one) would otherwise trip over the missing key.
$ocp_movement_actions = ['stock_in', 'stock_out', 'stock_out_subcon', 'transfer', 'set_min_stock', 'initial_stock'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', $ocp_movement_actions, true)) {
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

        // Moving stock to the warehouse it is already in does nothing, but the FIFO code
        // below still decrements the source batch and adds the same quantity back while
        // writing an OUT and an IN movement - so refuse it before anything is written.
        if ((string) $from_warehouse_id === (string) $to_warehouse_id) {
            $_SESSION['swal_data'] = array(
                'title' => 'Error!',
                'text' => 'The source and destination warehouse must be different.',
                'icon' => 'error'
            );
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

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

