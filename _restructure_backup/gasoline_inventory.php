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

// Process gasoline stock movements
$swal_data = array(); // For SweetAlert2 data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'gas_in') {
        // Process gasoline in from supplier
        $gasoline_type = $_POST['gasoline_type'];
        $supplier_id = $_POST['supplier_id'];
        $tank_id = $_POST['tank_id'];
        $quantity_liters = $_POST['quantity_liters'];
        $price_per_liter = $_POST['price_per_liter'];
        $date_received = $_POST['date_received'];
        $purchase_order = $_POST['purchase_order'] ?? '';
        $purchase_request = $_POST['purchase_request'] ?? ''; // NEW: Purchase Request
        
        try {
            // Insert gasoline in record
            $insertStmt = $pdo->prepare("INSERT INTO gasoline_movements (gasoline_type, supplier_id, tank_id, quantity_liters, price_per_liter, movement_type, movement_date, purchase_order, purchase_request) 
                                        VALUES (:gasoline_type, :supplier_id, :tank_id, :quantity_liters, :price_per_liter, 'in', :date_received, :purchase_order, :purchase_request)");
            $insertStmt->bindParam(':gasoline_type', $gasoline_type);
            $insertStmt->bindParam(':supplier_id', $supplier_id);
            $insertStmt->bindParam(':tank_id', $tank_id);
            $insertStmt->bindParam(':quantity_liters', $quantity_liters);
            $insertStmt->bindParam(':price_per_liter', $price_per_liter);
            $insertStmt->bindParam(':date_received', $date_received);
            $insertStmt->bindParam(':purchase_order', $purchase_order);
            $insertStmt->bindParam(':purchase_request', $purchase_request); // NEW: Purchase Request
            
            if ($insertStmt->execute()) {
                // Insert into gasoline_batches for FIFO tracking
                $batchStmt = $pdo->prepare("INSERT INTO gasoline_batches (gasoline_type, tank_id, quantity_liters, price_per_liter, date_received, purchase_order, purchase_request, supplier_id) 
                                          VALUES (:gasoline_type, :tank_id, :quantity_liters, :price_per_liter, :date_received, :purchase_order, :purchase_request, :supplier_id)");
                $batchStmt->bindParam(':gasoline_type', $gasoline_type);
                $batchStmt->bindParam(':tank_id', $tank_id);
                $batchStmt->bindParam(':quantity_liters', $quantity_liters);
                $batchStmt->bindParam(':price_per_liter', $price_per_liter);
                $batchStmt->bindParam(':date_received', $date_received);
                $batchStmt->bindParam(':purchase_order', $purchase_order);
                $batchStmt->bindParam(':purchase_request', $purchase_request); // NEW: Purchase Request
                $batchStmt->bindParam(':supplier_id', $supplier_id);
                $batchStmt->execute();
                
                // Update gasoline inventory levels (for quick summary)
                // Calculate weighted average price
                $avgPriceStmt = $pdo->prepare("
                    SELECT 
                        SUM(quantity_liters * price_per_liter) / SUM(quantity_liters) as avg_price,
                        SUM(quantity_liters) as total_quantity
                    FROM gasoline_batches 
                    WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id AND quantity_liters > 0
                ");
                $avgPriceStmt->bindParam(':gasoline_type', $gasoline_type);
                $avgPriceStmt->bindParam(':tank_id', $tank_id);
                $avgPriceStmt->execute();
                $avgPriceData = $avgPriceStmt->fetch(PDO::FETCH_ASSOC);
                
                $weighted_avg_price = $avgPriceData['avg_price'] ?? $price_per_liter;
                
                $updateStmt = $pdo->prepare("INSERT INTO gasoline_inventory (gasoline_type, tank_id, quantity_liters, price_per_liter) 
                                            VALUES (:gasoline_type, :tank_id, :quantity_liters, :price_per_liter)
                                            ON DUPLICATE KEY UPDATE 
                                            quantity_liters = quantity_liters + :quantity_liters,
                                            price_per_liter = :price_per_liter");
                $updateStmt->bindParam(':gasoline_type', $gasoline_type);
                $updateStmt->bindParam(':tank_id', $tank_id);
                $updateStmt->bindParam(':quantity_liters', $quantity_liters);
                $updateStmt->bindParam(':price_per_liter', $weighted_avg_price);
                $updateStmt->execute();
                
                $swal_data = array(
                    'title' => 'Success!',
                    'text' => 'Gasoline added successfully!',
                    'icon' => 'success'
                );
            } else {
                $swal_data = array(
                    'title' => 'Error!',
                    'text' => 'Error adding gasoline. Please try again.',
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
    } elseif ($action === 'gas_out') {
        // Process gasoline out to vehicle or equipment
        $gasoline_type = $_POST['gasoline_type'];
        $tank_id = $_POST['tank_id'];
        $quantity_liters = $_POST['quantity_liters'];
        $vehicle_id = !empty($_POST['vehicle_id']) ? $_POST['vehicle_id'] : null;
        $equipment_id = !empty($_POST['equipment_id']) ? $_POST['equipment_id'] : null;
        $driver_operator = $_POST['driver_operator'];
        $purpose = $_POST['purpose'];
        $date_issued = $_POST['date_issued'];
        $odometer_reading = !empty($_POST['odometer_reading']) ? $_POST['odometer_reading'] : null;
        
        // Validate that at least one of vehicle or equipment is selected
        if (empty($vehicle_id) && empty($equipment_id)) {
            $swal_data = array(
                'title' => 'Validation Error!',
                'text' => 'Please select either a vehicle or equipment.',
                'icon' => 'error'
            );
        } else {
            try {
                // Check if enough gasoline is available
                $checkStmt = $pdo->prepare("SELECT SUM(quantity_liters) as total_quantity FROM gasoline_batches 
                                          WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id AND quantity_liters > 0");
                $checkStmt->bindParam(':gasoline_type', $gasoline_type);
                $checkStmt->bindParam(':tank_id', $tank_id);
                $checkStmt->execute();
                $current_stock = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($current_stock && $current_stock['total_quantity'] >= $quantity_liters) {
                    // Get the oldest batches first (FIFO)
                    $batchStmt = $pdo->prepare("SELECT id, quantity_liters, price_per_liter FROM gasoline_batches 
                                              WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id AND quantity_liters > 0 
                                              ORDER BY date_received ASC, id ASC");
                    $batchStmt->bindParam(':gasoline_type', $gasoline_type);
                    $batchStmt->bindParam(':tank_id', $tank_id);
                    $batchStmt->execute();
                    $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $remaining_quantity = $quantity_liters;
                    $total_cost = 0;
                    $batch_usage = []; // Track batch usage for detailed records
                    
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    foreach ($batches as $batch) {
                        if ($remaining_quantity <= 0) break;
                        
                        $batch_quantity_used = min($remaining_quantity, $batch['quantity_liters']);
                        $batch_cost = $batch_quantity_used * $batch['price_per_liter'];
                        $total_cost += $batch_cost;
                        
                        // Record batch usage
                        $batch_usage[] = [
                            'batch_id' => $batch['id'],
                            'quantity_used' => $batch_quantity_used,
                            'price_per_liter' => $batch['price_per_liter']
                        ];
                        
                        // Update the batch quantity
                        $updateBatchStmt = $pdo->prepare("UPDATE gasoline_batches SET quantity_liters = quantity_liters - :quantity_used 
                                                         WHERE id = :batch_id");
                        $updateBatchStmt->bindParam(':quantity_used', $batch_quantity_used);
                        $updateBatchStmt->bindParam(':batch_id', $batch['id']);
                        $updateBatchStmt->execute();
                        
                        $remaining_quantity -= $batch_quantity_used;
                    }
                    
                    // Calculate average price per liter for this transaction
                    $avg_price_per_liter = $quantity_liters > 0 ? $total_cost / $quantity_liters : 0;
                    
                    // Insert detailed gasoline out records for each batch used
                    foreach ($batch_usage as $batch) {
                        $insertStmt = $pdo->prepare("INSERT INTO gasoline_movements (gasoline_type, tank_id, quantity_liters, price_per_liter, movement_type, movement_date, vehicle_id, equipment_id, driver_operator, purpose, odometer_reading, batch_id) 
                                                    VALUES (:gasoline_type, :tank_id, :quantity_liters, :price_per_liter, 'out', :date_issued, :vehicle_id, :equipment_id, :driver_operator, :purpose, :odometer_reading, :batch_id)");
                        $insertStmt->bindParam(':gasoline_type', $gasoline_type);
                        $insertStmt->bindParam(':tank_id', $tank_id);
                        $insertStmt->bindParam(':quantity_liters', $batch['quantity_used']);
                        $insertStmt->bindParam(':price_per_liter', $batch['price_per_liter']);
                        $insertStmt->bindParam(':date_issued', $date_issued);
                        $insertStmt->bindParam(':vehicle_id', $vehicle_id);
                        $insertStmt->bindParam(':equipment_id', $equipment_id);
                        $insertStmt->bindParam(':driver_operator', $driver_operator);
                        $insertStmt->bindParam(':purpose', $purpose);
                        $insertStmt->bindParam(':odometer_reading', $odometer_reading);
                        $insertStmt->bindParam(':batch_id', $batch['batch_id']);
                        $insertStmt->execute();
                    }
                    
                    // Calculate new weighted average price after issuing gasoline
                    $avgPriceStmt = $pdo->prepare("
                        SELECT 
                            SUM(quantity_liters * price_per_liter) / SUM(quantity_liters) as avg_price
                        FROM gasoline_batches 
                        WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id AND quantity_liters > 0
                    ");
                    $avgPriceStmt->bindParam(':gasoline_type', $gasoline_type);
                    $avgPriceStmt->bindParam(':tank_id', $tank_id);
                    $avgPriceStmt->execute();
                    $avgPriceData = $avgPriceStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $weighted_avg_price = $avgPriceData['avg_price'] ?? 0;
                    
                    // Update inventory levels
                    $updateStmt = $pdo->prepare("UPDATE gasoline_inventory SET quantity_liters = quantity_liters - :quantity_liters, price_per_liter = :price_per_liter
                                                WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id");
                    $updateStmt->bindParam(':gasoline_type', $gasoline_type);
                    $updateStmt->bindParam(':tank_id', $tank_id);
                    $updateStmt->bindParam(':quantity_liters', $quantity_liters);
                    $updateStmt->bindParam(':price_per_liter', $weighted_avg_price);
                    $updateStmt->execute();
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $swal_data = array(
                        'title' => 'Success!',
                        'text' => 'Gasoline issued successfully!',
                        'icon' => 'success'
                    );
                } else {
                    $swal_data = array(
                        'title' => 'Insufficient Stock!',
                        'text' => 'Insufficient gasoline available.',
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
        }
    } elseif ($action === 'gas_transfer') {
        // Process gasoline transfer between tanks
        $gasoline_type = $_POST['gasoline_type'];
        $from_tank_id = $_POST['from_tank_id'];
        $to_tank_id = $_POST['to_tank_id'];
        $quantity_liters = $_POST['quantity_liters'];
        $transfer_date = $_POST['transfer_date'];
        $reason = $_POST['reason'] ?? '';
        
        try {
            // Check if enough gasoline is available in source tank
            $checkStmt = $pdo->prepare("SELECT SUM(quantity_liters) as total_quantity FROM gasoline_batches 
                                      WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id AND quantity_liters > 0");
            $checkStmt->bindParam(':gasoline_type', $gasoline_type);
            $checkStmt->bindParam(':tank_id', $from_tank_id);
            $checkStmt->execute();
            $current_stock = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($current_stock && $current_stock['total_quantity'] >= $quantity_liters) {
                // Get the oldest batches first (FIFO)
                $batchStmt = $pdo->prepare("SELECT id, quantity_liters, price_per_liter, supplier_id, purchase_order, purchase_request FROM gasoline_batches 
                                          WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id AND quantity_liters > 0 
                                          ORDER BY date_received ASC, id ASC");
                $batchStmt->bindParam(':gasoline_type', $gasoline_type);
                $batchStmt->bindParam(':tank_id', $from_tank_id);
                $batchStmt->execute();
                $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $remaining_quantity = $quantity_liters;
                $total_cost = 0;
                $batch_usage = []; // Track batch usage for detailed records
                
                // Begin transaction
                $pdo->beginTransaction();
                
                foreach ($batches as $batch) {
                    if ($remaining_quantity <= 0) break;
                    
                    $batch_quantity_used = min($remaining_quantity, $batch['quantity_liters']);
                    $batch_cost = $batch_quantity_used * $batch['price_per_liter'];
                    $total_cost += $batch_cost;
                    
                    // Record batch usage
                    $batch_usage[] = [
                        'batch_id' => $batch['id'],
                        'quantity_used' => $batch_quantity_used,
                        'price_per_liter' => $batch['price_per_liter'],
                        'supplier_id' => $batch['supplier_id'],
                        'purchase_order' => $batch['purchase_order'],
                        'purchase_request' => $batch['purchase_request'] // NEW: Purchase Request
                    ];
                    
                    // Update the source batch quantity
                    $updateBatchStmt = $pdo->prepare("UPDATE gasoline_batches SET quantity_liters = quantity_liters - :quantity_used 
                                                     WHERE id = :batch_id");
                    $updateBatchStmt->bindParam(':quantity_used', $batch_quantity_used);
                    $updateBatchStmt->bindParam(':batch_id', $batch['id']);
                    $updateBatchStmt->execute();
                    
                    // Create a new batch in the destination tank with the same properties
                    $newBatchStmt = $pdo->prepare("INSERT INTO gasoline_batches (gasoline_type, tank_id, quantity_liters, price_per_liter, date_received, purchase_order, purchase_request, supplier_id, transfer_from) 
                                                  VALUES (:gasoline_type, :tank_id, :quantity_liters, :price_per_liter, :date_received, :purchase_order, :purchase_request, :supplier_id, :transfer_from)");
                    $newBatchStmt->bindParam(':gasoline_type', $gasoline_type);
                    $newBatchStmt->bindParam(':tank_id', $to_tank_id);
                    $newBatchStmt->bindParam(':quantity_liters', $batch_quantity_used);
                    $newBatchStmt->bindParam(':price_per_liter', $batch['price_per_liter']);
                    $newBatchStmt->bindParam(':date_received', $transfer_date);
                    $newBatchStmt->bindParam(':purchase_order', $batch['purchase_order']);
                    $newBatchStmt->bindParam(':purchase_request', $batch['purchase_request']); // NEW: Purchase Request
                    $newBatchStmt->bindParam(':supplier_id', $batch['supplier_id']);
                    $newBatchStmt->bindParam(':transfer_from', $from_tank_id);
                    $newBatchStmt->execute();
                    
                    $remaining_quantity -= $batch_quantity_used;
                }
                
                // Calculate average price per liter for this transfer
                $avg_price_per_liter = $quantity_liters > 0 ? $total_cost / $quantity_liters : 0;
                
                // Record gasoline out from source tank (detailed records for each batch)
                foreach ($batch_usage as $batch) {
                    $outStmt = $pdo->prepare("INSERT INTO gasoline_movements (gasoline_type, tank_id, quantity_liters, price_per_liter, movement_type, movement_date, transfer_to, notes, batch_id) 
                                             VALUES (:gasoline_type, :tank_id, :quantity_liters, :price_per_liter, 'out', :transfer_date, :transfer_to, :notes, :batch_id)");
                    $outStmt->bindParam(':gasoline_type', $gasoline_type);
                    $outStmt->bindParam(':tank_id', $from_tank_id);
                    $outStmt->bindParam(':quantity_liters', $batch['quantity_used']);
                    $outStmt->bindParam(':price_per_liter', $batch['price_per_liter']);
                    $outStmt->bindParam(':transfer_date', $transfer_date);
                    $outStmt->bindParam(':transfer_to', $to_tank_id);
                    $outStmt->bindParam(':notes', $reason);
                    $outStmt->bindParam(':batch_id', $batch['batch_id']);
                    $outStmt->execute();
                }
                
                // Record gasoline in to destination tank (detailed records for each batch)
                foreach ($batch_usage as $batch) {
                    $inStmt = $pdo->prepare("INSERT INTO gasoline_movements (gasoline_type, tank_id, quantity_liters, price_per_liter, movement_type, movement_date, transfer_from, notes) 
                                            VALUES (:gasoline_type, :tank_id, :quantity_liters, :price_per_liter, 'in', :transfer_date, :transfer_from, :notes)");
                    $inStmt->bindParam(':gasoline_type', $gasoline_type);
                    $inStmt->bindParam(':tank_id', $to_tank_id);
                    $inStmt->bindParam(':quantity_liters', $batch['quantity_used']);
                    $inStmt->bindParam(':price_per_liter', $batch['price_per_liter']);
                    $inStmt->bindParam(':transfer_date', $transfer_date);
                    $inStmt->bindParam(':transfer_from', $from_tank_id);
                    $inStmt->bindParam(':notes', $reason);
                    $inStmt->execute();
                }
                
                // Calculate new weighted average prices for both tanks
                $avgPriceFromStmt = $pdo->prepare("
                    SELECT 
                        SUM(quantity_liters * price_per_liter) / SUM(quantity_liters) as avg_price
                    FROM gasoline_batches 
                    WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id AND quantity_liters > 0
                ");
                $avgPriceFromStmt->bindParam(':gasoline_type', $gasoline_type);
                $avgPriceFromStmt->bindParam(':tank_id', $from_tank_id);
                $avgPriceFromStmt->execute();
                $avgPriceFromData = $avgPriceFromStmt->fetch(PDO::FETCH_ASSOC);
                
                $avgPriceToStmt = $pdo->prepare("
                    SELECT 
                        SUM(quantity_liters * price_per_liter) / SUM(quantity_liters) as avg_price
                    FROM gasoline_batches 
                    WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id AND quantity_liters > 0
                ");
                $avgPriceToStmt->bindParam(':gasoline_type', $gasoline_type);
                $avgPriceToStmt->bindParam(':tank_id', $to_tank_id);
                $avgPriceToStmt->execute();
                $avgPriceToData = $avgPriceToStmt->fetch(PDO::FETCH_ASSOC);
                
                $weighted_avg_price_from = $avgPriceFromData['avg_price'] ?? 0;
                $weighted_avg_price_to = $avgPriceToData['avg_price'] ?? $avg_price_per_liter;
                
                // Update inventory levels
                // Decrease from source tank
                $updateFromStmt = $pdo->prepare("UPDATE gasoline_inventory SET quantity_liters = quantity_liters - :quantity_liters, price_per_liter = :price_per_liter
                                                WHERE gasoline_type = :gasoline_type AND tank_id = :tank_id");
                $updateFromStmt->bindParam(':gasoline_type', $gasoline_type);
                $updateFromStmt->bindParam(':tank_id', $from_tank_id);
                $updateFromStmt->bindParam(':quantity_liters', $quantity_liters);
                $updateFromStmt->bindParam(':price_per_liter', $weighted_avg_price_from);
                $updateFromStmt->execute();
                
                // Increase in destination tank
                $updateToStmt = $pdo->prepare("INSERT INTO gasoline_inventory (gasoline_type, tank_id, quantity_liters, price_per_liter) 
                                              VALUES (:gasoline_type, :tank_id, :quantity_liters, :price_per_liter)
                                              ON DUPLICATE KEY UPDATE 
                                              quantity_liters = quantity_liters + :quantity_liters,
                                              price_per_liter = :price_per_liter");
                $updateToStmt->bindParam(':gasoline_type', $gasoline_type);
                $updateToStmt->bindParam(':tank_id', $to_tank_id);
                $updateToStmt->bindParam(':quantity_liters', $quantity_liters);
                $updateToStmt->bindParam(':price_per_liter', $weighted_avg_price_to);
                $updateToStmt->execute();
                
                // Commit transaction
                $pdo->commit();
                
                $swal_data = array(
                    'title' => 'Success!',
                    'text' => 'Gasoline transferred successfully!',
                    'icon' => 'success'
                );
            } else {
                $swal_data = array(
                    'title' => 'Insufficient Stock!',
                    'text' => 'Insufficient gasoline available in the source tank.',
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
    } elseif ($action === 'set_min_gas') {
        // Process setting minimum gasoline level
        $tank_id = $_POST['tank_id'];
        $gasoline_type = $_POST['gasoline_type'];
        $min_stock_liters = $_POST['min_stock_liters'];
        
        try {
            $updateStmt = $pdo->prepare("INSERT INTO gasoline_min_levels (tank_id, gasoline_type, min_stock_liters) 
                                        VALUES (:tank_id, :gasoline_type, :min_stock_liters)
                                        ON DUPLICATE KEY UPDATE min_stock_liters = :min_stock_liters");
            $updateStmt->bindParam(':tank_id', $tank_id);
            $updateStmt->bindParam(':gasoline_type', $gasoline_type);
            $updateStmt->bindParam(':min_stock_liters', $min_stock_liters);
            
            if ($updateStmt->execute()) {
                $swal_data = array(
                    'title' => 'Success!',
                    'text' => 'Minimum gasoline level updated successfully!',
                    'icon' => 'success'
                );
            } else {
                $swal_data = array(
                    'title' => 'Error!',
                    'text' => 'Error updating minimum gasoline level. Please try again.',
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
    } elseif ($action === 'initial_gas') {
        // Process initial gasoline for tanks
        $gasoline_type = $_POST['gasoline_type'];
        $tank_id = $_POST['tank_id'];
        $quantity_liters = $_POST['quantity_liters'];
        $price_per_liter = $_POST['price_per_liter'];
        $date_added = $_POST['date_added'];
        
        try {
            // Insert initial gasoline record
            $insertStmt = $pdo->prepare("INSERT INTO gasoline_movements (gasoline_type, tank_id, quantity_liters, price_per_liter, movement_type, movement_date, notes) 
                                        VALUES (:gasoline_type, :tank_id, :quantity_liters, :price_per_liter, 'in', :date_added, 'Initial gasoline')");
            $insertStmt->bindParam(':gasoline_type', $gasoline_type);
            $insertStmt->bindParam(':tank_id', $tank_id);
            $insertStmt->bindParam(':quantity_liters', $quantity_liters);
            $insertStmt->bindParam(':price_per_liter', $price_per_liter);
            $insertStmt->bindParam(':date_added', $date_added);
            
            if ($insertStmt->execute()) {
                // Insert into gasoline_batches for FIFO tracking
                $batchStmt = $pdo->prepare("INSERT INTO gasoline_batches (gasoline_type, tank_id, quantity_liters, price_per_liter, date_received, notes) 
                                          VALUES (:gasoline_type, :tank_id, :quantity_liters, :price_per_liter, :date_added, 'Initial gasoline')");
                $batchStmt->bindParam(':gasoline_type', $gasoline_type);
                $batchStmt->bindParam(':tank_id', $tank_id);
                $batchStmt->bindParam(':quantity_liters', $quantity_liters);
                $batchStmt->bindParam(':price_per_liter', $price_per_liter);
                $batchStmt->bindParam(':date_added', $date_added);
                $batchStmt->execute();
                
                // Update inventory levels
                $updateStmt = $pdo->prepare("INSERT INTO gasoline_inventory (gasoline_type, tank_id, quantity_liters, price_per_liter) 
                                            VALUES (:gasoline_type, :tank_id, :quantity_liters, :price_per_liter)
                                            ON DUPLICATE KEY UPDATE 
                                            quantity_liters = quantity_liters + :quantity_liters,
                                            price_per_liter = :price_per_liter");
                $updateStmt->bindParam(':gasoline_type', $gasoline_type);
                $updateStmt->bindParam(':tank_id', $tank_id);
                $updateStmt->bindParam(':quantity_liters', $quantity_liters);
                $updateStmt->bindParam(':price_per_liter', $price_per_liter);
                $updateStmt->execute();
                
                $swal_data = array(
                    'title' => 'Success!',
                    'text' => 'Initial gasoline added successfully!',
                    'icon' => 'success'
                );
            } else {
                $swal_data = array(
                    'title' => 'Error!',
                    'text' => 'Error adding initial gasoline. Please try again.',
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
    }
}

// Fetch data for dropdowns and tables
try {
    // Gasoline types
    $gasolineTypes = ['Unleaded', 'Premium', 'Diesel'];
    
    // Get suppliers
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM gasoline_suppliers WHERE supplier_type = 'fuel' OR supplier_type IS NULL ORDER BY supplier_name");
    $suppliersStmt->execute();
    $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get tanks
    $tanksStmt = $pdo->prepare("SELECT id, tank_name, location, capacity_liters FROM gasoline_tanks ORDER BY tank_name");
    $tanksStmt->execute();
    $tanks = $tanksStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get vehicles
    $vehiclesStmt = $pdo->prepare("SELECT id, vehicle_name, plate_number FROM vehicles WHERE fuel_type = 'gasoline' OR fuel_type = 'diesel' ORDER BY vehicle_name");
    $vehiclesStmt->execute();
    $vehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get equipment
    $equipmentStmt = $pdo->prepare("SELECT id, equipment_name FROM equipment WHERE fuel_type = 'gasoline' OR fuel_type = 'diesel' ORDER by equipment_name");
    $equipmentStmt->execute();
    $equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get gasoline inventory summary with minimum level check
    // Calculate weighted average price per liter for each gasoline type in each tank
    $inventoryStmt = $pdo->prepare("
        SELECT 
            gi.gasoline_type, 
            gi.tank_id, 
            gi.quantity_liters, 
            gi.price_per_liter,
            gt.tank_name, 
            gt.location, 
            gt.capacity_liters,
            COALESCE(gml.min_stock_liters, 0) as min_stock_liters,
            CASE 
                WHEN gi.quantity_liters <= 0 THEN 'out-of-stock'
                WHEN gi.quantity_liters <= COALESCE(gml.min_stock_liters, 0) AND COALESCE(gml.min_stock_liters, 0) > 0 THEN 'low-stock'
                ELSE 'normal'
            END AS stock_status
        FROM gasoline_inventory gi
        JOIN gasoline_tanks gt ON gi.tank_id = gt.id
        LEFT JOIN gasoline_min_levels gml ON gi.tank_id = gml.tank_id AND gi.gasoline_type = gml.gasoline_type
        ORDER BY gi.gasoline_type, gt.tank_name
    ");
    $inventoryStmt->execute();
    $gasoline_inventory = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get low gasoline alerts
    $lowGasStmt = $pdo->prepare("
        SELECT gi.gasoline_type, gi.tank_id, gi.quantity_liters, gi.price_per_liter,
               gt.tank_name, gt.location, gt.capacity_liters,
               gml.min_stock_liters
        FROM gasoline_inventory gi
        JOIN gasoline_tanks gt ON gi.tank_id = gt.id
        JOIN gasoline_min_levels gml ON gi.tank_id = gml.tank_id AND gi.gasoline_type = gml.gasoline_type
        WHERE gi.quantity_liters <= gml.min_stock_liters
        ORDER BY gi.quantity_liters ASC, gi.gasoline_type
    ");
    $lowGasStmt->execute();
    $low_gas_items = $lowGasStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent gasoline movements
    $movementsStmt = $pdo->prepare("
        SELECT gm.*, 
               s.supplier_name,
               v.vehicle_name, v.plate_number,
               e.equipment_name,
               gt.tank_name,
               gt_from.tank_name as from_tank_name,
               gt_to.tank_name as to_tank_name,
               gb.price_per_liter as batch_price
        FROM gasoline_movements gm
        LEFT JOIN gasoline_suppliers s ON gm.supplier_id = s.id
        LEFT JOIN vehicles v ON gm.vehicle_id = v.id
        LEFT JOIN equipment e ON gm.equipment_id = e.id
        LEFT JOIN gasoline_tanks gt ON gm.tank_id = gt.id
        LEFT JOIN gasoline_tanks gt_from ON gm.transfer_from = gt_from.id
        LEFT JOIN gasoline_tanks gt_to ON gm.transfer_to = gt_to.id
        LEFT JOIN gasoline_batches gb ON gm.batch_id = gb.id
        ORDER BY gm.movement_date DESC, gm.created_at DESC
        LIMIT 50
    ");
    $movementsStmt->execute();
    $gasoline_movements = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get gasoline batches for FIFO tracking
    $batchesStmt = $pdo->prepare("
        SELECT gb.*, gt.tank_name, gt.location, s.supplier_name
        FROM gasoline_batches gb
        JOIN gasoline_tanks gt ON gb.tank_id = gt.id
        LEFT JOIN gasoline_suppliers s ON gb.supplier_id = s.id
        WHERE gb.quantity_liters > 0
        ORDER BY gb.gasoline_type, gb.tank_id, gb.date_received DESC, gb.id DESC
    ");
    $batchesStmt->execute();
    $gasoline_batches = $batchesStmt->fetchAll(PDO::FETCH_ASSOC);
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
        <title>Gasoline Inventory Management - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
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
            .tank-capacity-bar {
                height: 20px;
                background-color: #e9ecef;
                border-radius: 4px;
                overflow: hidden;
            }
            .tank-capacity-fill {
                height: 100%;
                background-color: #0d6efd;
                transition: width 0.3s ease;
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
        </style>
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Gasoline Inventory Management</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class='breadcrumb-item active'>Gasoline Inventory</li>
                        </ol>
                        
                        <!-- Low Gasoline Alerts -->
                        <?php if (!empty($low_gas_items)): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Low Gasoline Alert</h5>
                            <p>The following gasoline types are below their minimum levels:</p>
                            <ul class="mb-0">
                                <?php foreach ($low_gas_items as $item): 
                                    $status = $item['quantity_liters'] <= 0 ? 'out-of-stock' : 'low-stock';
                                    $percent = ($item['quantity_liters'] / $item['capacity_liters']) * 100;
                                ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($item['gasoline_type']); ?></strong> 
                                    in <?php echo htmlspecialchars($item['tank_name']); ?>: 
                                    <?php echo number_format($item['quantity_liters'], 2); ?>L 
                                    (Min: <?php echo $item['min_stock_liters']; ?>L, 
                                    Capacity: <?php echo number_format($item['capacity_liters'], 2); ?>L,
                                    <?php echo number_format($percent, 1); ?>% full)
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
                            <!-- Gasoline In Operations -->
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card bg-primary text-white action-card">
                                    <div class="card-body text-center">
                                        <div class="action-icon">
                                            <i class="fas fa-arrow-down"></i>
                                        </div>
                                        <h5 class="card-title">Gasoline In Operations</h5>
                                        <p class="card-text">Manage incoming gasoline from suppliers and initial stock</p>
                                        <div class="d-grid gap-2 d-md-block mt-3">
                                            <button class="btn btn-light btn-sm me-2" data-bs-toggle="modal" data-bs-target="#gasInModal">
                                                <i class="fas fa-truck-loading me-1"></i> From Supplier
                                            </button>
                                            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#initialGasModal">
                                                <i class="fas fa-gas-pump me-1"></i> Initial Stock
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Gasoline Out Operations -->
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card bg-warning text-dark action-card">
                                    <div class="card-body text-center">
                                        <div class="action-icon">
                                            <i class="fas fa-arrow-up"></i>
                                        </div>
                                        <h5 class="card-title">Gasoline Out Operations</h5>
                                        <p class="card-text">Issue gasoline to vehicles and equipment using FIFO</p>
                                        <div class="d-grid gap-2 d-md-block mt-3">
                                            <button class="btn btn-dark btn-sm me-2" data-bs-toggle="modal" data-bs-target="#gasOutModal">
                                                <i class="fas fa-car me-1"></i> To Vehicle/Heavy Equipment
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Transfer & Management -->
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card bg-success text-white action-card">
                                    <div class="card-body text-center">
                                        <div class="action-icon">
                                            <i class="fas fa-exchange-alt"></i>
                                        </div>
                                        <h5 class="card-title">Transfer & Management</h5>
                                        <p class="card-text">Transfer between tanks and manage inventory settings</p>
                                        <div class="d-grid gap-2 d-md-block mt-3">
                                            <button class="btn btn-light btn-sm me-2" data-bs-toggle="modal" data-bs-target="#transferModal">
                                                <i class="fas fa-sync-alt me-1"></i> Transfer
                                            </button>
                                            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#minGasModal">
                                                <i class="fas fa-sliders-h me-1"></i> Min Levels
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Gasoline Inventory Summary -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-gas-pump me-1"></i>
                                Current Gasoline Inventory Summary
                                <button class="btn btn-sm btn-outline-primary float-end" data-bs-toggle="modal" data-bs-target="#minGasModal">
                                    <i class="fas fa-cog"></i> Manage Minimum Levels
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($gasoline_inventory)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover inventory-table" id="inventoryTable">
                                        <thead>
                                            <tr>
                                                <th>Gasoline Type</th>
                                                <th>Tank</th>
                                                <th>Location</th>
                                                <th>Quantity (L)</th>
                                                <th>Capacity (L)</th>
                                                <th>Fill Level</th>
                                                <th>Min Level</th>
                                                <th>Status</th>
                                                <th>Avg Price/Liter</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($gasoline_inventory as $item): 
                                                $total_value = $item['quantity_liters'] * $item['price_per_liter'];
                                                $row_class = '';
                                                $status_badge = '';
                                                $percent = $item['capacity_liters'] > 0 ? ($item['quantity_liters'] / $item['capacity_liters']) * 100 : 0;
                                                
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
                                                <td><?php echo htmlspecialchars($item['gasoline_type']); ?></td>
                                                <td><?php echo htmlspecialchars($item['tank_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['location']); ?></td>
                                                <td><?php echo number_format($item['quantity_liters'], 2); ?></td>
                                                <td><?php echo number_format($item['capacity_liters'], 2); ?></td>
                                                <td>
                                                    <div class="tank-capacity-bar">
                                                        <div class="tank-capacity-fill" style="width: <?php echo $percent; ?>%"></div>
                                                    </div>
                                                    <small><?php echo number_format($percent, 1); ?>%</small>
                                                </td>
                                                <td><?php echo $item['min_stock_liters'] > 0 ? number_format($item['min_stock_liters'], 2) . 'L' : 'Not Set'; ?></td>
                                                <td><?php echo $status_badge; ?></td>
                                                <td>₱<?php echo number_format($item['price_per_liter'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No gasoline inventory data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Gasoline Batches (FIFO Tracking) -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-layer-group me-1"></i>
                                Gasoline Batches (FIFO Tracking)
                            </div>
                            <div class="card-body">
                                <?php if (!empty($gasoline_batches)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="batchesTable">
                                        <thead>
                                            <tr>
                                                <th>Date Received</th>
                                                <th>Gasoline Type</th>
                                                <th>Tank</th>
                                                <th>Supplier</th>
                                                <th>Quantity (L)</th>
                                                <th>Price/Liter</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($gasoline_batches as $batch): 
                                                $total_value = $batch['quantity_liters'] * $batch['price_per_liter'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($batch['date_received']); ?></td>
                                                <td><?php echo htmlspecialchars($batch['gasoline_type']); ?></td>
                                                <td><?php echo htmlspecialchars($batch['tank_name'] . ' - ' . $batch['location']); ?></td>
                                                <td><?php echo htmlspecialchars($batch['supplier_name'] ?? 'Initial Stock'); ?></td>
                                                <td><?php echo number_format($batch['quantity_liters'], 2); ?></td>
                                                <td>₱<?php echo number_format($batch['price_per_liter'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No gasoline batches available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Gasoline Movements -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-exchange-alt me-1"></i>
                                Recent Gasoline Movements
                            </div>
                            <div class="card-body">
                                <?php if (!empty($gasoline_movements)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover movements-table" id="movementsTable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Gasoline Type</th>
                                                <th>Type</th>
                                                <th>Source/Destination</th>
                                                <th>Tank</th>
                                                <th>Quantity (L)</th>
                                                <th>Price/Liter</th>
                                                <th>Total Value</th>
                                                <th>Purchase Order</th>
                                                <th>Purchase Request</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($gasoline_movements as $movement): 
                                                $movement_type = $movement['movement_type'];
                                                $type_class = $movement_type === 'in' ? 'text-success' : 'text-danger';
                                                $type_icon = $movement_type === 'in' ? 'fa-arrow-down' : 'fa-arrow-up';
                                                
                                                // Determine source/target information
                                                $source_target = '';
                                                if ($movement_type === 'in') {
                                                    if ($movement['supplier_name']) {
                                                        $source_target = 'From: ' . $movement['supplier_name'];
                                                    } elseif ($movement['transfer_from']) {
                                                        $source_target = 'Transfer From: ' . $movement['from_tank_name'];
                                                    } else {
                                                        $source_target = 'From: Initial Stock';
                                                    }
                                                } else {
                                                    if ($movement['vehicle_name']) {
                                                        $source_target = 'To Vehicle: ' . $movement['vehicle_name'] . ' (' . $movement['plate_number'] . ')';
                                                    } elseif ($movement['equipment_name']) {
                                                        $source_target = 'To Equipment: ' . $movement['equipment_name'];
                                                    } elseif ($movement['transfer_to']) {
                                                        $source_target = 'Transfer To: ' . $movement['to_tank_name'];
                                                    } else {
                                                        $source_target = 'To: Unknown';
                                                    }
                                                }
                                                
                                                $tank_info = $movement['tank_name'] ?? 'Unknown';
                                                
                                                // Use batch price if available (for out movements), otherwise use movement price
                                                $price_per_liter = !empty($movement['batch_price']) ? $movement['batch_price'] : $movement['price_per_liter'];
                                                $total_value = $movement['quantity_liters'] * $price_per_liter;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($movement['movement_date']); ?></td>
                                                <td><?php echo htmlspecialchars($movement['gasoline_type']); ?></td>
                                                <td class="<?php echo $type_class; ?>">
                                                    <i class="fas <?php echo $type_icon; ?>"></i> 
                                                    <?php echo strtoupper($movement_type); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($source_target); ?></td>
                                                <td><?php echo htmlspecialchars($tank_info); ?></td>
                                                <td><?php echo number_format($movement['quantity_liters'], 2); ?></td>
                                                <td>₱<?php echo number_format($price_per_liter, 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                                <td><?php echo htmlspecialchars($movement['purchase_order'] ?? 'N/A'); ?></td> 
                                                <td><?php echo htmlspecialchars($movement['purchase_request'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No gasoline movements recorded yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Gasoline In Modal -->
        <div class="modal fade" id="gasInModal" tabindex="-1" aria-labelledby="gasInModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="gasInModalLabel">Gasoline In from Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="gas_in">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="gasoline_type" name="gasoline_type" required>
                                    <option value="">Select Gasoline Type</option>
                                    <?php foreach ($gasolineTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="gasoline_type">Gasoline Type <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <select class="form-select" id="supplier_id" name="supplier_id" required>
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>">
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <select class="form-select" id="tank_id" name="tank_id" required>
                                    <option value="">Select Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>" data-capacity="<?php echo $tank['capacity_liters']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location'] . ' (' . number_format($tank['capacity_liters'], 2) . 'L)'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="tank_id">Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="quantity_liters" name="quantity_liters" step="0.01" min="0.01" required>
                                <label for="quantity_liters">Quantity (Liters) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="price_per_liter" name="price_per_liter" step="0.01" min="0" required>
                                <label for="price_per_liter">Price per Liter (₱) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="purchase_order" name="purchase_order">
                                <label for="purchase_order">Purchase Order #</label>
                            </div>
                            
                            <!-- NEW: Purchase Request Input -->
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="purchase_request" name="purchase_request">
                                <label for="purchase_request">Purchase Request #</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="date_received" name="date_received" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <label for="date_received">Date Received <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Record Gasoline In</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Gasoline Out Modal -->
        <div class="modal fade" id="gasOutModal" tabindex="-1" aria-labelledby="gasOutModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="gasOutModalLabel">Gasoline Out to Vehicle/Heavy Equipment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="gasOutForm">
                        <input type="hidden" name="action" value="gas_out">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="out_gasoline_type" name="gasoline_type" required>
                                    <option value="">Select Gasoline Type</option>
                                    <?php foreach ($gasolineTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="out_gasoline_type">Gasoline Type <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <select class="form-select" id="out_tank_id" name="tank_id" required>
                                    <option value="">Select Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="out_tank_id">From Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="vehicle_id" name="vehicle_id">
                                            <option value="">Select Vehicle (Optional)</option>
                                            <?php foreach ($vehicles as $vehicle): ?>
                                            <option value="<?php echo $vehicle['id']; ?>">
                                                <?php echo htmlspecialchars($vehicle['vehicle_name'] . ' (' . $vehicle['plate_number'] . ')'); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="vehicle_id">Vehicle</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="equipment_id" name="equipment_id">
                                            <option value="">Select Equipment (Optional)</option>
                                            <?php foreach ($equipment as $eq): ?>
                                            <option value="<?php echo $eq['id']; ?>">
                                                <?php echo htmlspecialchars($eq['equipment_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="equipment_id">Equipment</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="driver_operator" name="driver_operator" required>
                                <label for="driver_operator">Driver/Operator Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="purpose" name="purpose" required>
                                <label for="purpose">Purpose <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="out_quantity_liters" name="quantity_liters" step="0.01" min="0.01" required>
                                <label for="out_quantity_liters">Quantity (Liters) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="odometer_reading" name="odometer_reading">
                                <label for="odometer_reading">Odometer Reading (Optional)</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="date_issued" name="date_issued" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <label for="date_issued">Date Issued <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Record Gasoline Out</button>
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
                        <h5 class="modal-title" id="transferModalLabel">Transfer Between Tanks</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="gas_transfer">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="transfer_gasoline_type" name="gasoline_type" required>
                                    <option value="">Select Gasoline Type</option>
                                    <?php foreach ($gasolineTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="transfer_gasoline_type">Gasoline Type <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <select class="form-select" id="from_tank_id" name="from_tank_id" required>
                                    <option value="">Select Source Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for='from_tank_id'>From Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <select class="form-select" id="to_tank_id" name="to_tank_id" required>
                                    <option value="">Select Destination Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="to_tank_id">To Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="transfer_quantity_liters" name="quantity_liters" step="0.01" min="0.01" required>
                                <label for="transfer_quantity_liters">Quantity (Liters) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="reason" name="reason">
                                <label for="reason">Reason for Transfer (Optional)</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="transfer_date" name="transfer_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <label for="transfer_date">Transfer Date <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Transfer Gasoline</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Set Minimum Gasoline Level Modal -->
        <div class="modal fade" id="minGasModal" tabindex="-1" aria-labelledby="minGasModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="minGasModalLabel">Set Minimum Gasoline Level</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="set_min_gas">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="min_gas_tank_id" name="tank_id" required>
                                    <option value="">Select Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="min_gas_tank_id">Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <select class="form-select" id="min_gas_type" name="gasoline_type" required>
                                    <option value="">Select Gasoline Type</option>
                                    <?php foreach ($gasolineTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="min_gas_type">Gasoline Type <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="min_stock_liters" name="min_stock_liters" step="0.01" min="0" required>
                                <label for="min_stock_liters">Minimum Stock Level (Liters) <span class="text-danger">*</span></label>
                                <div class="form-text ms-2">Set to 0 to disable low stock alerts for this gasoline type in this tank</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Minimum Level</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Initial Gasoline Modal -->
        <div class="modal fade" id="initialGasModal" tabindex="-1" aria-labelledby="initialGasModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="initialGasModalLabel">Add Initial Gasoline</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="initial_gas">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <select class="form-select" id="initial_gasoline_type" name="gasoline_type" required>
                                    <option value="">Select Gasoline Type</option>
                                    <?php foreach ($gasolineTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="initial_gasoline_type">Gasoline Type <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <select class="form-select" id="initial_tank_id" name="tank_id" required>
                                    <option value="">Select Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="initial_tank_id">Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="initial_quantity_liters" name="quantity_liters" step="0.01" min="0.01" required>
                                <label for="initial_quantity_liters">Quantity (Liters) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="initial_price_per_liter" name="price_per_liter" step="0.01" min="0" required>
                                <label for="initial_price_per_liter">Price per Liter (₱) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="date" class="form-control" id="date_added" name="date_added" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <label for="date_added">Date Added <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Initial Gasoline</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script>
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const inventoryTable = document.getElementById('inventoryTable');
                if (inventoryTable) {
                    new simpleDatatables.DataTable(inventoryTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
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
                        labels: {
                            placeholder: "Search...",
                            perPage: "{select} entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                // Show SweetAlert2 notifications
                <?php if (!empty($swal_data)): ?>
                    Swal.fire({
                        title: '<?php echo $swal_data['title']; ?>',
                        text: '<?php echo $swal_data['text']; ?>',
                        icon: '<?php echo $swal_data['icon']; ?>',
                        confirmButtonText: 'OK'
                    });
                <?php endif; ?>
                
                // Show modal if there was an error with form submission
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $swal_data['icon'] === 'error'): ?>
                    <?php if ($_POST['action'] === 'gas_in'): ?>
                        var gasInModal = new bootstrap.Modal(document.getElementById('gasInModal'));
                        gasInModal.show();
                    <?php elseif ($_POST['action'] === 'gas_out'): ?>
                        var gasOutModal = new bootstrap.Modal(document.getElementById('gasOutModal'));
                        gasOutModal.show();
                    <?php elseif ($_POST['action'] === 'gas_transfer'): ?>
                        var transferModal = new bootstrap.Modal(document.getElementById('transferModal'));
                        transferModal.show();
                    <?php elseif ($_POST['action'] === 'set_min_gas'): ?>
                        var minGasModal = new bootstrap.Modal(document.getElementById('minGasModal'));
                        minGasModal.show();
                    <?php elseif ($_POST['action'] === 'initial_gas'): ?>
                        var initialGasModal = new bootstrap.Modal(document.getElementById('initialGasModal'));
                        initialGasModal.show();
                    <?php endif; ?>
                <?php endif; ?>
                
                // Tank capacity validation for gas in
                document.getElementById('tank_id').addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const capacity = parseFloat(selectedOption.getAttribute('data-capacity')) || 0;
                    const quantityInput = document.getElementById('quantity_liters');
                    
                    if (capacity > 0) {
                        quantityInput.setAttribute('max', capacity);
                    }
                });
                
                // Form validation for gas out - ensure at least one of vehicle or equipment is selected
                document.getElementById('gasOutForm').addEventListener('submit', function(e) {
                    const vehicleId = document.getElementById('vehicle_id').value;
                    const equipmentId = document.getElementById('equipment_id').value;
                    
                    if (!vehicleId && !equipmentId) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please select either a vehicle or equipment.'
                        });
                        return false;
                    }
                });
                
                // Disable Equipment field when Vehicle is selected and vice versa
                const vehicleSelect = document.getElementById('vehicle_id');
                const equipmentSelect = document.getElementById('equipment_id');
                
                if (vehicleSelect && equipmentSelect) {
                    vehicleSelect.addEventListener('change', function() {
                        if (this.value) {
                            equipmentSelect.disabled = true;
                            equipmentSelect.value = '';
                        } else {
                            equipmentSelect.disabled = false;
                        }
                    });
                    
                    equipmentSelect.addEventListener('change', function() {
                        if (this.value) {
                            vehicleSelect.disabled = true;
                            vehicleSelect.value = '';
                        } else {
                            vehicleSelect.disabled = false;
                        }
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