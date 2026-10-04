<?php
/**
 * actions/issue_materials-actions.php
 *
 * Every action for issue_materials lives in this one file.
 *
 * The page pulls this file in at the top, so it runs in the page's scope: the
 * database handle, the session and $_POST behave exactly as they did when this
 * code sat inline. A rejected submission leaves the message variables set and the
 * markup below shows them; a success redirects.
 *
 * The blocks below are lifted verbatim from issue_materials: the queries, the messages
 * and the validation are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_ISSUE_MATERIALS_ACTIONS_RAN')) {
    return;
}
define('OCP_ISSUE_MATERIALS_ACTIONS_RAN', true);

// The message variables the page's markup reads. Both are filled in below.
$message = '';
$message_type = '';
$swal_data = []; // For SweetAlert2 data

// -------------------------------------------------------------------- issue_materials
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'issue_to_employee') {
        // Process parts issuance to employee
        $employee_id = $_POST['employee_id'];
        $purpose = $_POST['purpose'];
        $date_issued = $_POST['date_issued'];
        
        // Get array of parts data
        $part_ids = $_POST['part_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            $total_issued_items = 0;
            $overall_total_cost = 0;
            $issuance_details = []; // Store details for success message
            
            // Process each part
            foreach ($part_ids as $index => $part_id) {
                if (empty($part_id) || empty($quantities[$index]) || $quantities[$index] <= 0) {
                    continue; // Skip empty entries
                }
                
                $quantity = (int)$quantities[$index];
                
                // Check if enough parts are available
                $checkStmt = $pdo->prepare("SELECT SUM(quantity) as total_quantity FROM spare_parts_batches 
                                          WHERE part_id = :part_id AND quantity > 0");
                $checkStmt->bindParam(':part_id', $part_id);
                $checkStmt->execute();
                $current_stock = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$current_stock || $current_stock['total_quantity'] < $quantity) {
                    throw new Exception("Insufficient stock for part ID: $part_id. Available: " . ($current_stock['total_quantity'] ?? 0) . ", Requested: $quantity");
                }
                
                // Get the oldest batches first (FIFO)
                $batchStmt = $pdo->prepare("SELECT id, quantity, price_per_unit FROM spare_parts_batches 
                                          WHERE part_id = :part_id AND quantity > 0 
                                          ORDER BY date_received ASC, id ASC");
                $batchStmt->bindParam(':part_id', $part_id);
                $batchStmt->execute();
                $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $remaining_quantity = $quantity;
                $part_total_cost = 0;
                $batch_usage = []; // Track batch usage for detailed records
                $price_details = []; // Store individual price details for display
                
                foreach ($batches as $batch) {
                    if ($remaining_quantity <= 0) break;
                    
                    $batch_quantity_used = min($remaining_quantity, $batch['quantity']);
                    $batch_cost = $batch_quantity_used * $batch['price_per_unit'];
                    $part_total_cost += $batch_cost;
                    
                    // Record batch usage
                    $batch_usage[] = [
                        'batch_id' => $batch['id'],
                        'quantity_used' => $batch_quantity_used,
                        'price_per_unit' => $batch['price_per_unit'],
                        'batch_cost' => $batch_cost
                    ];
                    
                    // Store price details for display
                    $price_details[] = [
                        'quantity' => $batch_quantity_used,
                        'price_per_unit' => $batch['price_per_unit'],
                        'subtotal' => $batch_cost
                    ];
                    
                    // Update the batch quantity
                    $updateBatchStmt = $pdo->prepare("UPDATE spare_parts_batches SET quantity = quantity - :quantity_used 
                                                     WHERE id = :batch_id");
                    $updateBatchStmt->bindParam(':quantity_used', $batch_quantity_used);
                    $updateBatchStmt->bindParam(':batch_id', $batch['id']);
                    $updateBatchStmt->execute();
                    
                    $remaining_quantity -= $batch_quantity_used;
                }
                
                // Insert detailed parts issuance records for each batch used
                foreach ($batch_usage as $batch) {
                    $insertStmt = $pdo->prepare("INSERT INTO spare_parts_movements (part_id, employee_id, quantity, price_per_unit, movement_type, movement_date, purpose, batch_id) 
                                                VALUES (:part_id, :employee_id, :quantity, :price_per_unit, 'out', :date_issued, :purpose, :batch_id)");
                    $insertStmt->bindParam(':part_id', $part_id);
                    $insertStmt->bindParam(':employee_id', $employee_id);
                    $insertStmt->bindParam(':quantity', $batch['quantity_used']);
                    $insertStmt->bindParam(':price_per_unit', $batch['price_per_unit']);
                    $insertStmt->bindParam(':date_issued', $date_issued);
                    $insertStmt->bindParam(':purpose', $purpose);
                    $insertStmt->bindParam(':batch_id', $batch['batch_id']);
                    $insertStmt->execute();
                }
                
                // Calculate new weighted average price after issuing parts for inventory
                $avgPriceStmt = $pdo->prepare("
                    SELECT 
                        SUM(quantity * price_per_unit) / SUM(quantity) as avg_price
                    FROM spare_parts_batches 
                    WHERE part_id = :part_id AND quantity > 0
                ");
                $avgPriceStmt->bindParam(':part_id', $part_id);
                $avgPriceStmt->execute();
                $avgPriceData = $avgPriceStmt->fetch(PDO::FETCH_ASSOC);
                
                $weighted_avg_price = $avgPriceData['avg_price'] ?? 0;
                
                // Update inventory levels
                $updateStmt = $pdo->prepare("UPDATE spare_parts_inventory SET quantity = quantity - :quantity, price_per_unit = :price_per_unit
                                            WHERE part_id = :part_id");
                $updateStmt->bindParam(':part_id', $part_id);
                $updateStmt->bindParam(':quantity', $quantity);
                $updateStmt->bindParam(':price_per_unit', $weighted_avg_price);
                $updateStmt->execute();
                
                // Get part details for display
                $partInfoStmt = $pdo->prepare("SELECT part_number, part_name FROM spare_parts WHERE id = :part_id");
                $partInfoStmt->bindParam(':part_id', $part_id);
                $partInfoStmt->execute();
                $part_info = $partInfoStmt->fetch(PDO::FETCH_ASSOC);
                
                // Build price breakdown for this part
                $price_breakdown = '';
                foreach ($price_details as $batch_index => $detail) {
                    $price_breakdown .= 'Batch ' . ($batch_index + 1) . ': ' . number_format($detail['quantity'], 0) . 
                                      ' × ₱' . number_format($detail['price_per_unit'], 2) . 
                                      ' = ₱' . number_format($detail['subtotal'], 2) . '<br>';
                }
                
                // Store issuance details
                $issuance_details[] = [
                    'part_name' => $part_info['part_name'] . ' (' . $part_info['part_number'] . ')',
                    'quantity' => $quantity,
                    'price_breakdown' => $price_breakdown,
                    'part_total' => $part_total_cost
                ];
                
                // Also create a record in employee_materials_issued table for tracking with actual prices
                // We'll create separate records for each batch price
                foreach ($batch_usage as $batch) {
                    $employeeIssueStmt = $pdo->prepare("INSERT INTO employee_materials_issued (employee_id, part_id, quantity, price_per_unit, total_price, purpose, date_issued, batch_id) 
                                                       VALUES (:employee_id, :part_id, :quantity, :price_per_unit, :total_price, :purpose, :date_issued, :batch_id)");
                    $employeeIssueStmt->bindParam(':employee_id', $employee_id);
                    $employeeIssueStmt->bindParam(':part_id', $part_id);
                    $employeeIssueStmt->bindParam(':quantity', $batch['quantity_used']);
                    $employeeIssueStmt->bindParam(':price_per_unit', $batch['price_per_unit']);
                    $employeeIssueStmt->bindParam(':total_price', $batch['batch_cost']);
                    $employeeIssueStmt->bindParam(':purpose', $purpose);
                    $employeeIssueStmt->bindParam(':date_issued', $date_issued);
                    $employeeIssueStmt->bindParam(':batch_id', $batch['batch_id']);
                    $employeeIssueStmt->execute();
                }
                
                $total_issued_items += $quantity;
                $overall_total_cost += $part_total_cost;
            }
            
            if ($total_issued_items === 0) {
                throw new Exception("No valid parts selected for issuance.");
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Build success message with all issuance details
            $success_html = 'Materials issued to employee successfully!<br><br>';
            $success_html .= '<div class="text-start">';
            $success_html .= '<strong>Issuance Summary:</strong><br>';
            
            foreach ($issuance_details as $index => $detail) {
                $success_html .= '<div class="mb-2">';
                $success_html .= '<strong>Item ' . ($index + 1) . ':</strong> ' . $detail['part_name'] . '<br>';
                $success_html .= '<strong>Quantity:</strong> ' . number_format($detail['quantity'], 0) . '<br>';
                $success_html .= '<strong>Price Breakdown (FIFO):</strong><br>' . $detail['price_breakdown'];
                $success_html .= '<strong>Item Total:</strong> ₱' . number_format($detail['part_total'], 2) . '<br>';
                $success_html .= '</div>';
            }
            
            $success_html .= '<hr>';
            $success_html .= '<strong>Total Items Issued:</strong> ' . number_format($total_issued_items, 0) . '<br>';
            $success_html .= '<strong>Grand Total Price:</strong> ₱' . number_format($overall_total_cost, 2) . '<br>';
            $success_html .= '<small class="text-muted">Note: Materials issued from oldest batches first.</small>';
            $success_html .= '</div>';
            
            $swal_data = [
                'title' => 'Success!',
                'html' => $success_html,
                'icon' => 'success'
            ];
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $swal_data = [
                'title' => 'Error!',
                'text' => 'Error: ' . $e->getMessage(),
                'icon' => 'error'
            ];
        } catch(PDOException $e) {
            $pdo->rollBack();
            $swal_data = [
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            ];
        }
    }
}
