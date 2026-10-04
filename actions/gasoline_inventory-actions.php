<?php
/**
 * actions/gasoline_inventory-actions.php
 *
 * Every action for gasoline_inventory.php lives in this one file: gasoline in from a
 * supplier, gasoline out, a transfer between tanks, setting a minimum level, and an
 * opening stock entry.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. Each branch
 * reports through $swal_data and redirects or re-renders, so the messages the page
 * shows are unchanged.
 *
 * The block below is lifted verbatim from gasoline_inventory.php: the queries, the
 * batch handling and the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_GASOLINE_INVENTORY_ACTIONS_RAN')) {
    return;
}
define('OCP_GASOLINE_INVENTORY_ACTIONS_RAN', true);

// The message state the markup reads, filled in below.
$swal_data = array();
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
