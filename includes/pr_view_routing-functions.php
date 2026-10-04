<?php
/**
 * includes/pr_view_routing-functions.php
 *
 * The helpers pr_view_routing.php and its actions file share: updateInventoryTable(), generatePONumber(), generateWSNumber().
 *
 * They live in their own file because both entry points need them. The routing handlers
 * call them after they write; the page's template calls the generators while rendering
 * its forms. They must therefore be loaded before whichever runs first, which is why
 * actions/pr_view_routing-actions.php and api/pr_view_routing-endpoint.php both require this file.
 *
 * The bodies below are lifted verbatim from pr_view_routing.php.
 */

if (defined('OCP_PR_VIEW_ROUTING_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_PR_VIEW_ROUTING_FUNCTIONS_LOADED', true);

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

// Function to generate PO Number
function generatePONumber($pdo) {
    // Get the latest PO number
    $stmt = $pdo->prepare("SELECT po_number FROM purchase_orders ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $lastPO = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastPO) {
        // Extract the number and increment it
        $lastNumber = intval($lastPO['po_number']);
        $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '000001';
    }
    
    return $newNumber;
}

// Function to generate Withdrawal Slip Number
function generateWSNumber($pdo) {
    $year = date('Y');
    
    // Get the latest WS number for this year
    $stmt = $pdo->prepare("SELECT ws_number FROM withdrawal_slips WHERE ws_number LIKE ? ORDER BY id DESC LIMIT 1");
    $likePattern = "WS-$year-%";
    $stmt->execute([$likePattern]);
    $lastWS = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastWS) {
        $lastNumber = intval(substr($lastWS['ws_number'], -4));
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return "WS-$year-$newNumber";
}


// Function to fetch a purchase request's items and sort them into the groups the routing
// works from: what can be served from stock, what is short, and what must be ordered.
//
// Returns every group. Both api/pr_view_routing-endpoint.php, which renders them, and
// actions/pr_view_routing-actions.php, which turns the chosen ones into po_items and
// withdrawal_slip_items rows, call it - the handler needs the same arrays the page showed,
// and it runs before the endpoint is loaded.
function ocp_routing_item_groups($pdo, $pr_id, $request_type, $document_type) {
    // Fetch PR items with stock information
    $itemsStmt = $pdo->prepare("
        SELECT pri.*, 
               i.item_code, i.item_name,
               w.warehouse_name, w.location,
               s.supplier_name as item_supplier_name,
               pri.delivered_quantity as db_delivered_quantity,
               COALESCE((
                   SELECT SUM(quantity) 
                   FROM inventory_batches 
                   WHERE item_id = pri.item_id AND warehouse_id = pri.warehouse_id
               ), 0) as current_stock,
               COALESCE((
                    SELECT SUM(quantity) 
                    FROM stock_movements 
                    WHERE item_id = pri.item_id AND purchase_request = pr.pr_number AND movement_type = 'out'
                ), 0) as calculated_delivered_quantity,
               COALESCE((
                   SELECT SUM(quantity) 
                   FROM stock_movements 
                   WHERE item_id = pri.item_id AND purchase_request = pr.pr_number AND movement_type = 'in'
               ), 0) as supplier_received_quantity
        FROM pr_items pri
        LEFT JOIN item_names i ON pri.item_id = i.id
        LEFT JOIN warehouses w ON pri.warehouse_id = w.id
        LEFT JOIN suppliers s ON pri.supplier_id = s.id
        LEFT JOIN purchase_requests pr ON pri.pr_id = pr.id
        WHERE pri.pr_id = ?
    ");
    $itemsStmt->execute([$pr_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Categorize items based on stock status and document_type
    $items_with_sufficient_stock = [];
    $items_with_insufficient_stock = [];
    $items_with_no_stock = [];
    $items_for_po = [];
    $items_for_withdrawal = [];

    foreach ($items as $item) {
        // For supplier PR, use supplier_received_quantity instead of delivered_quantity
        if ($request_type === 'supplier') {
            $remaining_needed = $item['quantity'] - $item['supplier_received_quantity'];
        } else {
            // For Project PR, use delivered_quantity
            $remaining_needed = $item['quantity'] - $item['delivered_quantity'];
        }

        $current_stock = $item['current_stock'];

        // Only process if still needed
        if ($remaining_needed > 0) {
            if ($request_type === 'project') {
                // For document_type 'ws' - Pure Withdrawal Slip flow
                if ($document_type === 'ws') {
                    // A withdrawal slip can only hand out what the warehouse holds. This
                    // used to take the whole remaining request whatever the stock, so a
                    // slip was drawn for 12 against 3 on hand and processing it could
                    // only ever fail with "Insufficient stock available for item: ...".
                    // The slip now covers what is there, and the shortfall is what it is.
                    $to_withdraw = min($remaining_needed, $current_stock);
                    if ($to_withdraw > 0) {
                        $items_for_withdrawal[] = [
                            'pr_item_id' => $item['id'],
                            'item_id' => $item['item_id'],
                            'item_code' => $item['item_code'],
                            'item_name' => $item['item_name'],
                            'warehouse_id' => $item['warehouse_id'],
                            'warehouse_name' => $item['warehouse_name'],
                            'requested_quantity' => $item['quantity'],
                            'delivered_quantity' => $item['delivered_quantity'],
                            'current_stock' => $current_stock,
                            'remaining_needed' => $remaining_needed,
                            'quantity_to_withdraw' => $to_withdraw,
                            'quantity_short' => $remaining_needed - $to_withdraw,
                            'unit_cost' => $item['unit_cost']
                        ];
                    }
                    if ($current_stock >= $remaining_needed) {
                        $items_with_sufficient_stock[] = $item;
                    } elseif ($current_stock > 0) {
                        $items_with_insufficient_stock[] = $item;
                    } else {
                        $items_with_no_stock[] = $item;
                    }
                }
                // For document_type 'pr_po' - Pure Purchase Order flow
                elseif ($document_type === 'pr_po') {
                    // Everything is ordered from the supplier, whatever the stock: that is
                    // what a pure purchase-order request asks for. The stock is ignored on
                    // purpose, so the whole remaining quantity is ordered.
                    $items_with_no_stock[] = $item;
                    $items_for_po[] = [
                        'pr_item_id' => $item['id'],
                        'item_id' => $item['item_id'],
                        'item_code' => $item['item_code'],
                        'item_name' => $item['item_name'],
                        'warehouse_id' => $item['warehouse_id'],
                        'warehouse_name' => $item['warehouse_name'],
                        'supplier_id' => $item['supplier_id'],
                        'supplier_name' => $item['item_supplier_name'],
                        'requested_quantity' => $item['quantity'],
                        'delivered_quantity' => $item['delivered_quantity'],
                        'supplier_received_quantity' => $item['supplier_received_quantity'] ?? 0,
                        'current_stock' => $current_stock,
                        'remaining_needed' => $remaining_needed,
                        'quantity_to_order' => $remaining_needed,
                        'unit_cost' => $item['unit_cost']
                    ];
                }
                // For document_type 'po_ws' - Mixed PO+WS flow
                elseif ($document_type === 'po_ws') {
                    // Check stock status
                    if ($current_stock >= $remaining_needed) {
                        // Sufficient stock
                        $items_with_sufficient_stock[] = $item;
                        $items_for_withdrawal[] = [
                            'pr_item_id' => $item['id'],
                            'item_id' => $item['item_id'],
                            'item_code' => $item['item_code'],
                            'item_name' => $item['item_name'],
                            'warehouse_id' => $item['warehouse_id'],
                            'warehouse_name' => $item['warehouse_name'],
                            'requested_quantity' => $item['quantity'],
                            'delivered_quantity' => $item['delivered_quantity'],
                            'current_stock' => $current_stock,
                            'remaining_needed' => $remaining_needed,
                            'quantity_to_withdraw' => $remaining_needed,
                            'unit_cost' => $item['unit_cost']
                        ];
                    } elseif ($current_stock > 0) {
                        // Insufficient stock (some stock available)
                        $items_with_insufficient_stock[] = $item;
                        $quantity_to_order = $remaining_needed - $current_stock;
                        $items_for_po[] = [
                            'pr_item_id' => $item['id'],
                            'item_id' => $item['item_id'],
                            'item_code' => $item['item_code'],
                            'item_name' => $item['item_name'],
                            'warehouse_id' => $item['warehouse_id'],
                            'warehouse_name' => $item['warehouse_name'],
                            'supplier_id' => $item['supplier_id'],
                            'supplier_name' => $item['item_supplier_name'],
                            'requested_quantity' => $item['quantity'],
                            'delivered_quantity' => $item['delivered_quantity'],
                            'supplier_received_quantity' => $item['supplier_received_quantity'] ?? 0,
                            'current_stock' => $current_stock,
                            'remaining_needed' => $remaining_needed,
                            'quantity_to_order' => $quantity_to_order,
                            'quantity_to_withdraw' => $current_stock,
                            'unit_cost' => $item['unit_cost']
                        ];
                        $items_for_withdrawal[] = [
                            'pr_item_id' => $item['id'],
                            'item_id' => $item['item_id'],
                            'item_code' => $item['item_code'],
                            'item_name' => $item['item_name'],
                            'warehouse_id' => $item['warehouse_id'],
                            'warehouse_name' => $item['warehouse_name'],
                            'requested_quantity' => $item['quantity'],
                            'delivered_quantity' => $item['delivered_quantity'],
                            'current_stock' => $current_stock,
                            'remaining_needed' => $remaining_needed,
                            'quantity_to_withdraw' => $current_stock,
                            'unit_cost' => $item['unit_cost']
                        ];
                    } else {
                        // No stock
                        $items_with_no_stock[] = $item;
                        $items_for_po[] = [
                            'pr_item_id' => $item['id'],
                            'item_id' => $item['item_id'],
                            'item_code' => $item['item_code'],
                            'item_name' => $item['item_name'],
                            'warehouse_id' => $item['warehouse_id'],
                            'warehouse_name' => $item['warehouse_name'],
                            'supplier_id' => $item['supplier_id'],
                            'supplier_name' => $item['item_supplier_name'],
                            'requested_quantity' => $item['quantity'],
                            'delivered_quantity' => $item['delivered_quantity'],
                            'supplier_received_quantity' => $item['supplier_received_quantity'] ?? 0,
                            'current_stock' => 0,
                            'remaining_needed' => $remaining_needed,
                            'quantity_to_order' => $remaining_needed,
                            'unit_cost' => $item['unit_cost']
                        ];
                    }
                }
            } else {
                // Supplier request: Add to PO if not fully received
                $items_for_po[] = [
                    'pr_item_id' => $item['id'],
                    'item_id' => $item['item_id'],
                    'item_code' => $item['item_code'],
                    'item_name' => $item['item_name'],
                    'warehouse_id' => $item['warehouse_id'],
                    'warehouse_name' => $item['warehouse_name'],
                    'supplier_id' => $item['supplier_id'],
                    'supplier_name' => $item['item_supplier_name'],
                    'requested_quantity' => $item['quantity'],
                    'delivered_quantity' => 0,
                    'supplier_received_quantity' => $item['supplier_received_quantity'] ?? 0,
                    'current_stock' => 0,
                    'remaining_needed' => $remaining_needed,
                    'quantity_to_order' => $remaining_needed,
                    'unit_cost' => $item['unit_cost']
                ];
            }
        }
    }

    return [
        'items' => $items,
        'items_with_sufficient_stock' => $items_with_sufficient_stock,
        'items_with_insufficient_stock' => $items_with_insufficient_stock,
        'items_with_no_stock' => $items_with_no_stock,
        'items_for_po' => $items_for_po,
        'items_for_withdrawal' => $items_for_withdrawal,
    ];
}
