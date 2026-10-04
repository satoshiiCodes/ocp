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
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix, department, position, accounttype FROM users WHERE id = :id");
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

// Check if PR ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: purchase_request.php');
    exit();
}

$pr_id = $_GET['id'];

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

// Fetch PR details
try {
    $prStmt = $pdo->prepare("
        SELECT pr.*, 
               u.firstname, u.middlename, u.lastname, u.suffix, u.department,
               p.project_name, p.threshold_amount,
               s.supplier_name
        FROM purchase_requests pr
        LEFT JOIN users u ON pr.requested_by = u.id
        LEFT JOIN projects p ON pr.project_id = p.id
        LEFT JOIN suppliers s ON pr.supplier_id = s.id
        WHERE pr.id = ?
    ");
    $prStmt->execute([$pr_id]);
    $pr = $prStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pr) {
        $_SESSION['swal_data'] = array(
            'title' => 'Error!',
            'text' => 'Purchase Request not found.',
            'icon' => 'error'
        );
        header('Location: purchase_request.php');
        exit();
    }
    
    // Get document_type from purchase_requests table
    $document_type = $pr['document_type'] ?? 'pr_po'; // Default to pr_po if not set
    
    // Set request_type based on document_type
    $request_type = $pr['request_type']; // Keep original for supplier/project distinction
    
    // Determine flow type based on document_type
    if ($request_type === 'project') {
        switch($document_type) {
            case 'po_ws':
                $flow_type = 'project_po_ws';
                break;
            case 'ws':
                $flow_type = 'project_ws';
                break;
            case 'pr_po':
            default:
                $flow_type = 'project_po';
                break;
        }
    } else {
        // Supplier request
        $flow_type = 'supplier';
    }
    
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
                    // All items should go to withdrawal slip
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
                }
                // For document_type 'pr_po' - Pure Purchase Order flow
                elseif ($document_type === 'pr_po') {
                    // All items should go to purchase order
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
    
    // Determine show/hide for buttons based on document_type
    if ($request_type === 'project') {
        if ($document_type === 'ws') {
            $show_withdrawal_slip_button = true;
            $show_po_button = false;
        } elseif ($document_type === 'pr_po') {
            $show_withdrawal_slip_button = false;
            $show_po_button = true;
        } elseif ($document_type === 'po_ws') {
            $show_withdrawal_slip_button = true;
            $show_po_button = true;
        } else {
            $show_withdrawal_slip_button = false;
            $show_po_button = false;
        }
    } else {
        $show_po_button = !empty($items_for_po);
        $show_withdrawal_slip_button = false;
    }
    
    // Check if PO or WS already exists for this PR
    $po_exists_check = $pdo->prepare("SELECT COUNT(*) as po_count FROM purchase_orders WHERE pr_id = ?");
    $po_exists_check->execute([$pr_id]);
    $po_exists_result = $po_exists_check->fetch(PDO::FETCH_ASSOC);
    $po_exists = $po_exists_result['po_count'] > 0;
    
    $ws_exists_check = $pdo->prepare("SELECT COUNT(*) as ws_count FROM withdrawal_slips WHERE pr_id = ?");
    $ws_exists_check->execute([$pr_id]);
    $ws_exists_result = $ws_exists_check->fetch(PDO::FETCH_ASSOC);
    $ws_exists = $ws_exists_result['ws_count'] > 0;
    
    // Check if withdrawal slip has been approved and released
    $ws_approved = false;
    $ws_released = false;
    if ($ws_exists) {
        $wsStatusStmt = $pdo->prepare("SELECT status FROM withdrawal_slips WHERE pr_id = ? ORDER BY id DESC LIMIT 1");
        $wsStatusStmt->execute([$pr_id]);
        $ws_status_data = $wsStatusStmt->fetch(PDO::FETCH_ASSOC);
        if ($ws_status_data) {
            $ws_approved = ($ws_status_data['status'] === 'approved');
            $ws_released = ($ws_status_data['status'] === 'released');
        }
    }
    
    // Fetch routing history
    $routingStmt = $pdo->prepare("
        SELECT prh.*, 
               u.firstname, u.middlename, u.lastname, u.suffix, u.department, u.position
        FROM pr_routing_history prh
        LEFT JOIN users u ON prh.action_by = u.id
        WHERE prh.pr_id = ?
        ORDER BY prh.created_at ASC
    ");
    $routingStmt->execute([$pr_id]);
    $routing_history = $routingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current routing stage
    $currentStageStmt = $pdo->prepare("
        SELECT stage, status FROM pr_routing 
        WHERE pr_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $currentStageStmt->execute([$pr_id]);
    $current_stage_result = $currentStageStmt->fetch(PDO::FETCH_ASSOC);
    
    // Initialize current_stage with default values if not found
    $current_stage = $current_stage_result ?: ['stage' => 'requestor', 'status' => 'pending'];
    
    // Fetch existing POs for this PR
    $poStmt = $pdo->prepare("
        SELECT po.*, COUNT(poi.id) as item_count 
        FROM purchase_orders po 
        LEFT JOIN po_items poi ON po.id = poi.po_id 
        WHERE po.pr_id = ? 
        GROUP BY po.id 
        ORDER BY po.created_at DESC
    ");
    $poStmt->execute([$pr_id]);
    $existing_pos_raw = $poStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch existing Withdrawal Slips for this PR
    $wsStmt = $pdo->prepare("
        SELECT ws.*, COUNT(wsi.id) as item_count 
        FROM withdrawal_slips ws 
        LEFT JOIN withdrawal_slip_items wsi ON ws.id = wsi.withdrawal_slip_id 
        WHERE ws.pr_id = ? 
        GROUP BY ws.id 
        ORDER BY ws.created_at DESC
    ");
    $wsStmt->execute([$pr_id]);
    $existing_ws_raw = $wsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total amount for each PO based on status
    $existing_pos = [];
    foreach ($existing_pos_raw as $po) {
        // Fetch PO items with received quantities
        $poItemsStmt = $pdo->prepare("
            SELECT poi.quantity, poi.received_quantity, poi.unit_cost, poi.status
            FROM po_items poi
            WHERE poi.po_id = ?
        ");
        $poItemsStmt->execute([$po['id']]);
        $po_items = $poItemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate total amount based on status
        $total_amount = 0;
        foreach ($po_items as $item) {
            if ($item['status'] === 'partially_received' && $item['received_quantity'] > 0) {
                // Calculate based on received quantity for partially_received status
                $total_amount += ($item['received_quantity'] * $item['unit_cost']);
            } else {
                // Calculate based on original quantity for pending or delivered status
                $total_amount += ($item['quantity'] * $item['unit_cost']);
            }
        }
        
        // Add calculated total amount to PO data
        $po['calculated_total_amount'] = $total_amount;
        $existing_pos[] = $po;
    }
    
    // Calculate total amount for each Withdrawal Slip
    $existing_ws = [];
    foreach ($existing_ws_raw as $ws) {
        // Fetch WS items
        $wsItemsStmt = $pdo->prepare("
            SELECT wsi.quantity, wsi.unit_cost, wsi.total_cost, wsi.status
            FROM withdrawal_slip_items wsi
            WHERE wsi.withdrawal_slip_id = ?
        ");
        $wsItemsStmt->execute([$ws['id']]);
        $ws_items = $wsItemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate total amount
        $total_amount = 0;
        foreach ($ws_items as $item) {
            $total_amount += $item['total_cost'];
        }
        
        // Add calculated total amount to WS data
        $ws['calculated_total_amount'] = $total_amount;
        $existing_ws[] = $ws;
    }
    
    // Fetch PO items for view modal
    $po_items_details = [];
    if (!empty($existing_pos)) {
        foreach ($existing_pos as $po) {
            $poItemsStmt = $pdo->prepare("
                SELECT poi.*, i.item_code, i.item_name, w.warehouse_name, s.supplier_name
                FROM po_items poi
                LEFT JOIN item_names i ON poi.item_id = i.id
                LEFT JOIN warehouses w ON poi.warehouse_id = w.id
                LEFT JOIN suppliers s ON poi.supplier_id = s.id
                WHERE poi.po_id = ?
            ");
            $poItemsStmt->execute([$po['id']]);
            $po_items_details[$po['id']] = $poItemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Fetch Withdrawal Slip items for view modal
    $ws_items_details = [];
    if (!empty($existing_ws)) {
        foreach ($existing_ws as $ws) {
            $wsItemsStmt = $pdo->prepare("
                SELECT wsi.*, i.item_code, i.item_name, w.warehouse_name
                FROM withdrawal_slip_items wsi
                LEFT JOIN item_names i ON wsi.item_id = i.id
                LEFT JOIN warehouses w ON wsi.warehouse_id = w.id
                WHERE wsi.withdrawal_slip_id = ?
            ");
            $wsItemsStmt->execute([$ws['id']]);
            $ws_items_details[$ws['id']] = $wsItemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Fetch all suppliers for the PO modal
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name");
    $suppliersStmt->execute();
    $all_suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch PO items for warehouse receiving stage
    $po_items_for_receiving = [];
    if ($current_stage['stage'] === 'warehouse_receiving' && !empty($existing_pos)) {
        $latest_po = $existing_pos[0] ?? null;
        if ($latest_po) {
            $poItemsStmt = $pdo->prepare("
                SELECT poi.*, i.item_code, i.item_name, w.warehouse_name, s.supplier_name
                FROM po_items poi
                LEFT JOIN item_names i ON poi.item_id = i.id
                LEFT JOIN warehouses w ON poi.warehouse_id = w.id
                LEFT JOIN suppliers s ON poi.supplier_id = s.id
                WHERE poi.po_id = ?
            ");
            $poItemsStmt->execute([$latest_po['id']]);
            $po_items_for_receiving = $poItemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Fetch WS items for warehouse releasing stage
    $ws_items_for_releasing = [];
    if ($current_stage['stage'] === 'warehouse_releasing' && !empty($existing_ws)) {
        $latest_ws = $existing_ws[0] ?? null;
        if ($latest_ws) {
            $wsItemsStmt = $pdo->prepare("
                SELECT wsi.*, i.item_code, i.item_name, w.warehouse_name
                FROM withdrawal_slip_items wsi
                LEFT JOIN item_names i ON wsi.item_id = i.id
                LEFT JOIN warehouses w ON wsi.warehouse_id = w.id
                WHERE wsi.withdrawal_slip_id = ?
            ");
            $wsItemsStmt->execute([$latest_ws['id']]);
            $ws_items_for_releasing = $wsItemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Calculate total PO amount for threshold comparison
    $total_po_amount = 0;
    if (!empty($existing_pos)) {
        foreach ($existing_pos as $po) {
            $total_po_amount += $po['calculated_total_amount'];
        }
    }
    
    // Check threshold status - ONLY for project requests
    $threshold_amount = 0;
    $threshold_status = 'not_exceed';
    if ($request_type === 'project') {
        $threshold_amount = $pr['threshold_amount'] ?? 0;
        if ($total_po_amount > $threshold_amount) {
            $threshold_status = 'exceed';
        }
    }
    
    // Check if items have already been received for this PR
    $items_received_check = $pdo->prepare("
        SELECT COUNT(*) as received_count 
        FROM pr_routing_history 
        WHERE pr_id = ? AND action = 'Items Received'
    ");
    $items_received_check->execute([$pr_id]);
    $items_received_result = $items_received_check->fetch(PDO::FETCH_ASSOC);
    $items_already_received = $items_received_result['received_count'] > 0;
    
    // Check if threshold amount has been adjusted for this PR
    $threshold_adjusted_check = $pdo->prepare("
        SELECT COUNT(*) as threshold_adjusted_count 
        FROM pr_routing_history 
        WHERE pr_id = ? AND (action = 'Threshold Amount Added' OR action = 'Threshold Amount Subtracted')
    ");
    $threshold_adjusted_check->execute([$pr_id]);
    $threshold_adjusted_result = $threshold_adjusted_check->fetch(PDO::FETCH_ASSOC);
    $threshold_amount_adjusted = $threshold_adjusted_result['threshold_adjusted_count'] > 0;
    
    // Check if withdrawal slip has been processed
    $ws_processed_check = $pdo->prepare("
        SELECT COUNT(*) as processed_count 
        FROM pr_routing_history 
        WHERE pr_id = ? AND action = 'Withdrawal Slip Processed'
    ");
    $ws_processed_check->execute([$pr_id]);
    $ws_processed_result = $ws_processed_check->fetch(PDO::FETCH_ASSOC);
    $ws_already_processed = $ws_processed_result['processed_count'] > 0;
    
    // Get PO status from the latest PO or set as 'Not Created'
    $po_status = 'Not Created';
    if (!empty($existing_pos)) {
        $latest_po = $existing_pos[0] ?? null;
        if ($latest_po) {
            $po_status = ucfirst($latest_po['status'] ?? 'draft');
        }
    }
    
    // Get WS status from the latest WS or set as 'Not Created'
    $ws_status = 'Not Created';
    if (!empty($existing_ws)) {
        $latest_ws = $existing_ws[0] ?? null;
        if ($latest_ws) {
            $ws_status = ucfirst($latest_ws['status'] ?? 'draft');
        }
    }
    
} catch (PDOException $e) {
    $_SESSION['swal_data'] = array(
        'title' => 'Error!',
        'text' => 'Error fetching PR details: ' . $e->getMessage(),
        'icon' => 'error'
    );
    header('Location: purchase_request.php');
    exit();
}

// Check if user is in Warehouse department
$is_warehouse_user = ($user['department'] === 'Warehouse' && $user['accounttype'] === 'Admin');

// Process routing actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Record the action in history
        $historyStmt = $pdo->prepare("
            INSERT INTO pr_routing_history (pr_id, action, remarks, action_by, stage_from, stage_to)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        // Update routing based on action
        $routingStmt = $pdo->prepare("
            INSERT INTO pr_routing (pr_id, stage, status, action_by, remarks)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $success_message = '';
        
        switch ($action) {
            case 'forward_to_warehouse':
                // Only requestor can forward to warehouse
                if ($current_stage['stage'] === 'requestor' && $pr['requested_by'] == $user_id) {
                    $historyStmt->execute([
                        $pr_id, 
                        'Forwarded to Warehouse', 
                        $remarks, 
                        $user_id,
                        'requestor',
                        'warehouse'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'warehouse', 
                        'pending', 
                        $user_id,
                        $remarks
                    ]);
                    
                    // Update PR status to "processing" in purchase_requests table
                    $updatePRStmt = $pdo->prepare("UPDATE purchase_requests SET status = 'processing' WHERE id = ?");
                    $updatePRStmt->execute([$pr_id]);
                    
                    $success_message = 'PR forwarded to Warehouse Department successfully! PR status changed to Processing.';
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'approve_warehouse':
                // Only warehouse admin can approve
                if ($current_stage['stage'] === 'warehouse' && 
                    $user['department'] === 'Warehouse' && 
                    $user['accounttype'] === 'Admin') {
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Approved by Warehouse', 
                        $remarks, 
                        $user_id,
                        'warehouse',
                        'purchasing'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'purchasing', 
                        'pending', 
                        $user_id,
                        $remarks
                    ]);
                    
                    $success_message = 'PR approved and forwarded to Purchasing Department!';
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'reject_warehouse':
                // Only warehouse admin can reject
                if ($current_stage['stage'] === 'warehouse' && 
                    $user['department'] === 'Warehouse' && 
                    $user['accounttype'] === 'Admin') {
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Rejected by Warehouse', 
                        $remarks, 
                        $user_id,
                        'warehouse',
                        'rejected'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'rejected', 
                        'rejected', 
                        $user_id,
                        $remarks
                    ]);
                    
                    // Also update PR status in purchase_requests table
                    $updatePRStmt = $pdo->prepare("UPDATE purchase_requests SET status = 'rejected' WHERE id = ?");
                    $updatePRStmt->execute([$pr_id]);
                    
                    $success_message = 'PR rejected successfully!';
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'create_purchase_order':
                // Only purchasing admin can create PO
                if ($current_stage['stage'] === 'purchasing' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'Purchaser' && 
                    $user['accounttype'] === 'Admin') {
                    
                    // Check if PO already exists for this PR
                    if ($po_exists) {
                        throw new Exception('A purchase order already exists for this PR. Only one PO is allowed per PR.');
                    }
                    
                    // Check if document type allows PO creation
                    if ($request_type === 'project' && $document_type === 'ws') {
                        throw new Exception('Cannot create Purchase Order for Pure Withdrawal Slip (WS) document type.');
                    }
                    
                    // Validate selected items
                    if (!isset($_POST['selected_items']) || empty($_POST['selected_items'])) {
                        throw new Exception('Please select at least one item for the purchase order.');
                    }
                    
                    $selected_items = $_POST['selected_items'];
                    $expected_delivery = $_POST['expected_delivery'] ?? null;
                    $po_remarks = $_POST['po_remarks'] ?? '';
                    
                    // Generate PO number
                    $po_number = generatePONumber($pdo);
                    
                    // Create purchase order
                    if ($request_type === 'project') {
                        $poStmt = $pdo->prepare("
                            INSERT INTO purchase_orders 
                            (po_number, pr_id, po_date, expected_delivery, requested_by, project_id, remarks, status) 
                            VALUES (?, ?, CURDATE(), ?, ?, ?, ?, 'pending')
                        ");
                        $poStmt->execute([
                            $po_number, 
                            $pr_id, 
                            $expected_delivery, 
                            $pr['requested_by'], 
                            $pr['project_id'], 
                            $po_remarks
                        ]);
                    } else {
                        // For Stock PR, get supplier_id from the first selected item or form
                        // Default supplier from PR if available
                        $default_supplier_id = $pr['supplier_id'] ?? null;
                        
                        $poStmt = $pdo->prepare("
                            INSERT INTO purchase_orders 
                            (po_number, pr_id, po_date, expected_delivery, requested_by, supplier_id, remarks, status) 
                            VALUES (?, ?, CURDATE(), ?, ?, ?, ?, 'pending')
                        ");
                        $poStmt->execute([
                            $po_number, 
                            $pr_id, 
                            $expected_delivery, 
                            $pr['requested_by'], 
                            $default_supplier_id, 
                            $po_remarks
                        ]);
                    }
                    
                    $po_id = $pdo->lastInsertId();
                    $total_amount = 0;
                    
                    // Track if we need to update PR supplier
                    $pr_supplier_id = null;
                    $pr_total_cost = 0;
                    
                    // Add items to PO
                    foreach ($selected_items as $pr_item_id) {
                        // Find the item in items_for_po array
                        $po_item = null;
                        foreach ($items_for_po as $item) {
                            if ($item['pr_item_id'] == $pr_item_id) {
                                $po_item = $item;
                                break;
                            }
                        }
                        
                        // If not found in items_for_po, search in items (for Stock PR)
                        if (!$po_item && $request_type === 'supplier') {
                            foreach ($items as $item) {
                                if ($item['id'] == $pr_item_id) {
                                    $received_qty = $item['supplier_received_quantity'] ?? 0;
                                    $remaining_needed = ($item['quantity'] ?? 0) - $received_qty;
                                    
                                    $po_item = [
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
                                        'supplier_received_quantity' => $received_qty,
                                        'current_stock' => 0,
                                        'remaining_needed' => $remaining_needed,
                                        'quantity_to_order' => $remaining_needed,
                                        'unit_cost' => $item['unit_cost'] ?? 0
                                    ];
                                    break;
                                }
                            }
                        }
                        
                        if ($po_item) {
                            $quantity = $_POST['quantity_' . $pr_item_id] ?? $po_item['quantity_to_order'];
                            $unit_cost = $_POST['unit_cost_' . $pr_item_id] ?? $po_item['unit_cost'];
                            $supplier_id = $_POST['supplier_id_' . $pr_item_id] ?? $po_item['supplier_id'];
                            $total_cost = $quantity * $unit_cost;
                            
                            $poItemStmt = $pdo->prepare("
                                INSERT INTO po_items 
                                (po_id, pr_item_id, item_id, warehouse_id, supplier_id, quantity, unit_cost, total_cost, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                            ");
                            $poItemStmt->execute([
                                $po_id,
                                $pr_item_id,
                                $po_item['item_id'],
                                $po_item['warehouse_id'],
                                $supplier_id,
                                $quantity,
                                $unit_cost,
                                $total_cost
                            ]);
                            
                            // FIX: Update pr_items table with unit_cost and total_cost for ALL request types
                            // This includes both project and supplier PRs
                            $updatePrItemStmt = $pdo->prepare("
                                UPDATE pr_items 
                                SET unit_cost = :unit_cost, 
                                    total_cost = :total_cost 
                                WHERE id = :pr_item_id
                            ");
                            $updatePrItemStmt->bindParam(':unit_cost', $unit_cost);
                            $updatePrItemStmt->bindParam(':total_cost', $total_cost);
                            $updatePrItemStmt->bindParam(':pr_item_id', $pr_item_id);
                            $updatePrItemStmt->execute();
                            
                            // For Stock PR: Also update supplier_id
                            if ($request_type === 'supplier') {
                                $updatePrItemSupplierStmt = $pdo->prepare("
                                    UPDATE pr_items 
                                    SET supplier_id = :supplier_id 
                                    WHERE id = :pr_item_id
                                ");
                                $updatePrItemSupplierStmt->bindParam(':supplier_id', $supplier_id);
                                $updatePrItemSupplierStmt->bindParam(':pr_item_id', $pr_item_id);
                                $updatePrItemSupplierStmt->execute();
                                
                                // Track supplier and total cost for PR update
                                $pr_supplier_id = $supplier_id;
                                $pr_total_cost += $total_cost;
                            } else {
                                // For Project PR: Track total cost for possible PR update if needed
                                $pr_total_cost += $total_cost;
                            }
                            
                            $total_amount += $total_cost;
                        }
                    }
                    
                    // Update PO total amount
                    $updatePOStmt = $pdo->prepare("UPDATE purchase_orders SET total_amount = ? WHERE id = ?");
                    $updatePOStmt->execute([$total_amount, $po_id]);
                    
                    // For Stock PR: Update purchase_requests table with supplier_id and total_estimated_cost
                    if ($request_type === 'supplier') {
                        // Get the supplier_id from the first item if not already set
                        if (!$pr_supplier_id && !empty($selected_items)) {
                            // Get supplier from the first selected item
                            $firstItemStmt = $pdo->prepare("
                                SELECT supplier_id FROM pr_items WHERE id = ? LIMIT 1
                            ");
                            $firstItemStmt->execute([$selected_items[0]]);
                            $firstItem = $firstItemStmt->fetch(PDO::FETCH_ASSOC);
                            $pr_supplier_id = $firstItem['supplier_id'] ?? null;
                        }
                        
                        // Update purchase_requests table
                        $updatePRStmt = $pdo->prepare("
                            UPDATE purchase_requests 
                            SET supplier_id = :supplier_id, 
                                total_estimated_cost = :total_cost 
                            WHERE id = :pr_id
                        ");
                        $updatePRStmt->bindParam(':supplier_id', $pr_supplier_id);
                        $updatePRStmt->bindParam(':total_cost', $pr_total_cost);
                        $updatePRStmt->bindParam(':pr_id', $pr_id);
                        $updatePRStmt->execute();
                    }
                    
                    // For Project PR with document_type 'pr_po', update the total_estimated_cost in purchase_requests
                    if ($request_type === 'project' && $document_type === 'pr_po') {
                        $updatePRStmt = $pdo->prepare("
                            UPDATE purchase_requests 
                            SET total_estimated_cost = :total_cost 
                            WHERE id = :pr_id
                        ");
                        $updatePRStmt->bindParam(':total_cost', $pr_total_cost);
                        $updatePRStmt->bindParam(':pr_id', $pr_id);
                        $updatePRStmt->execute();
                    }
                    
                    // Stay in purchasing stage after creating PO
                    $historyStmt->execute([
                        $pr_id, 
                        'Purchase Order Created', 
                        "Purchase Order $po_number created with " . count($selected_items) . " items" . 
                        ($request_type === 'supplier' ? ". PR updated with supplier and total cost." : ". PR items updated with unit cost and total cost."), 
                        $user_id,
                        'purchasing',
                        'purchasing'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'purchasing', 
                        'pending', 
                        $user_id,
                        "Purchase Order $po_number created"
                    ]);
                    
                    $success_message = "Purchase Order $po_number created successfully! " . 
                                    ($request_type === 'supplier' ? "PR updated with supplier and total estimated cost. " : "PR items updated with unit cost and total cost. ") . 
                                    "You can now approve and forward to Accounting.";
                    
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'create_withdrawal_slip':
                // Only purchasing admin can create withdrawal slip
                if ($current_stage['stage'] === 'purchasing' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'Purchaser' && 
                    $user['accounttype'] === 'Admin') {
                    
                    // Check if Withdrawal Slip already exists for this PR
                    if ($ws_exists) {
                        throw new Exception('A withdrawal slip already exists for this PR. Only one withdrawal slip is allowed per PR.');
                    }
                    
                    // Check if document type allows WS creation
                    if ($request_type === 'project' && $document_type === 'pr_po') {
                        throw new Exception('Cannot create Withdrawal Slip for Pure Purchase Order (PR_PO) document type.');
                    }
                    
                    // Validate selected items
                    if (!isset($_POST['selected_withdrawal_items']) || empty($_POST['selected_withdrawal_items'])) {
                        throw new Exception('Please select at least one item for the withdrawal slip.');
                    }
                    
                    $selected_items = $_POST['selected_withdrawal_items'];
                    $ws_remarks = $_POST['ws_remarks'] ?? '';
                    
                    // Generate Withdrawal Slip number
                    $ws_number = generateWSNumber($pdo);
                    
                    // Get warehouse ID (use the first item's warehouse)
                    $first_item_id = $selected_items[0];
                    $warehouse_id = null;
                    foreach ($items_for_withdrawal as $item) {
                        if ($item['pr_item_id'] == $first_item_id) {
                            $warehouse_id = $item['warehouse_id'];
                            break;
                        }
                    }
                    
                    // Create withdrawal slip
                    $wsStmt = $pdo->prepare("
                        INSERT INTO withdrawal_slips 
                        (ws_number, pr_id, ws_date, requested_by, project_id, warehouse_id, remarks, status) 
                        VALUES (?, ?, CURDATE(), ?, ?, ?, ?, 'pending')
                    ");
                    $wsStmt->execute([
                        $ws_number, 
                        $pr_id, 
                        $pr['requested_by'], 
                        $pr['project_id'], 
                        $warehouse_id,
                        $ws_remarks
                    ]);
                    
                    $ws_id = $pdo->lastInsertId();
                    $total_amount = 0;
                    
                    // Add items to Withdrawal Slip
                    foreach ($selected_items as $pr_item_id) {
                        // Find the item in items_for_withdrawal array
                        $ws_item = null;
                        foreach ($items_for_withdrawal as $item) {
                            if ($item['pr_item_id'] == $pr_item_id) {
                                $ws_item = $item;
                                break;
                            }
                        }
                        
                        if ($ws_item) {
                            $quantity = $_POST['withdrawal_quantity_' . $pr_item_id] ?? $ws_item['quantity_to_withdraw'];
                            $unit_cost = $_POST['withdrawal_unit_cost_' . $pr_item_id] ?? $ws_item['unit_cost'];
                            $total_cost = $quantity * $unit_cost;
                            
                            $wsItemStmt = $pdo->prepare("
                                INSERT INTO withdrawal_slip_items 
                                (withdrawal_slip_id, pr_item_id, item_id, warehouse_id, quantity, unit_cost, total_cost, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                            ");
                            $wsItemStmt->execute([
                                $ws_id,
                                $pr_item_id,
                                $ws_item['item_id'],
                                $ws_item['warehouse_id'],
                                $quantity,
                                $unit_cost,
                                $total_cost
                            ]);
                            
                            $total_amount += $total_cost;
                        }
                    }
                    
                    // Update Withdrawal Slip total amount
                    $updateWSStmt = $pdo->prepare("UPDATE withdrawal_slips SET total_amount = ? WHERE id = ?");
                    $updateWSStmt->execute([$total_amount, $ws_id]);
                    
                    // Stay in purchasing stage after creating Withdrawal Slip
                    $historyStmt->execute([
                        $pr_id, 
                        'Withdrawal Slip Created', 
                        "Withdrawal Slip $ws_number created with " . count($selected_items) . " items", 
                        $user_id,
                        'purchasing',
                        'purchasing'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'purchasing', 
                        'pending', 
                        $user_id,
                        "Withdrawal Slip $ws_number created"
                    ]);
                    
                    $success_message = "Withdrawal Slip $ws_number created successfully! You can now approve and forward to Accounting.";
                    
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'approve_purchasing':
                // Only purchasing admin can approve
                if ($current_stage['stage'] === 'purchasing' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'Purchaser' && 
                    $user['accounttype'] === 'Admin') {
                    
                    // Check if required documents exist based on document_type
                    if ($request_type === 'project') {
                        if ($document_type === 'ws' && !$ws_exists) {
                            throw new Exception('Cannot approve PR: Withdrawal Slip is required for WS document type.');
                        }
                        if ($document_type === 'pr_po' && !$po_exists) {
                            throw new Exception('Cannot approve PR: Purchase Order is required for PR_PO document type.');
                        }
                        if ($document_type === 'po_ws' && (!$po_exists || !$ws_exists)) {
                            throw new Exception('Cannot approve PR: Both Purchase Order and Withdrawal Slip are required.');
                        }
                    } else {
                        // Supplier request
                        if (!$po_exists) {
                            throw new Exception('Cannot approve PR without creating a Purchase Order first.');
                        }
                    }
                    
                    // Update PO status to 'processing' if PO exists
                    if ($po_exists) {
                        $updatePOStatusStmt = $pdo->prepare("UPDATE purchase_orders SET status = 'processing' WHERE pr_id = ?");
                        $updatePOStatusStmt->execute([$pr_id]);
                        
                        $updatePOItemsStatusStmt = $pdo->prepare("UPDATE po_items SET status = 'processing' WHERE po_id IN (SELECT id FROM purchase_orders WHERE pr_id = ?)");
                        $updatePOItemsStatusStmt->execute([$pr_id]);
                    }
                    
                    // Update WS status to 'processing' if WS exists
                    if ($ws_exists) {
                        $updateWSStatusStmt = $pdo->prepare("UPDATE withdrawal_slips SET status = 'processing' WHERE pr_id = ?");
                        $updateWSStatusStmt->execute([$pr_id]);
                        
                        $updateWSItemsStatusStmt = $pdo->prepare("UPDATE withdrawal_slip_items SET status = 'processing' WHERE withdrawal_slip_id IN (SELECT id FROM withdrawal_slips WHERE pr_id = ?)");
                        $updateWSItemsStatusStmt->execute([$pr_id]);
                    }
                    
                    // DETERMINE NEXT STAGE BASED ON DOCUMENT_TYPE
                    if ($request_type === 'project') {
                        if ($document_type === 'ws') {
                            // Pure WS flow - go directly to Approver
                            $next_stage = 'approver';
                            $next_stage_label = 'Approver (CEO)';
                        } elseif ($document_type === 'pr_po') {
                            // Pure PO flow - go to Accounting
                            $next_stage = 'accounting';
                            $next_stage_label = 'Accounting';
                        } elseif ($document_type === 'po_ws') {
                            // Mixed PO+WS flow - go to Accounting first
                            $next_stage = 'accounting';
                            $next_stage_label = 'Accounting';
                        } else {
                            $next_stage = 'accounting';
                            $next_stage_label = 'Accounting';
                        }
                    } else {
                        // Supplier - go to Accounting
                        $next_stage = 'accounting';
                        $next_stage_label = 'Accounting';
                    }
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Approved by Purchasing', 
                        $remarks, 
                        $user_id,
                        'purchasing',
                        $next_stage
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        $next_stage, 
                        'pending', 
                        $user_id,
                        $remarks
                    ]);
                    
                    $success_message = 'PR approved and forwarded to ' . $next_stage_label . '! All documents status updated to Processing.';
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'reject_purchasing':
                // Only purchasing admin can reject
                if ($current_stage['stage'] === 'purchasing' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'Purchaser' && 
                    $user['accounttype'] === 'Admin') {
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Rejected by Purchasing', 
                        $remarks, 
                        $user_id,
                        'purchasing',
                        'rejected'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'rejected', 
                        'rejected', 
                        $user_id,
                        $remarks
                    ]);
                    
                    // Also update PR status in purchase_requests table
                    $updatePRStmt = $pdo->prepare("UPDATE purchase_requests SET status = 'rejected' WHERE id = ?");
                    $updatePRStmt->execute([$pr_id]);
                    
                    $success_message = 'PR rejected successfully!';
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'approve_accounting':
                // Only accounting admin can approve
                if ($current_stage['stage'] === 'accounting' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'Accounting' && 
                    $user['accounttype'] === 'Admin') {
                    
                    // Update PO status to 'processing' if PO exists
                    if ($po_exists) {
                        $updatePOStatusStmt = $pdo->prepare("UPDATE purchase_orders SET status = 'processing' WHERE pr_id = ?");
                        $updatePOStatusStmt->execute([$pr_id]);
                        
                        $updatePOItemsStatusStmt = $pdo->prepare("UPDATE po_items SET status = 'processing' WHERE po_id IN (SELECT id FROM purchase_orders WHERE pr_id = ?)");
                        $updatePOItemsStatusStmt->execute([$pr_id]);
                    }
                    
                    // Update WS status to 'processing' if WS exists
                    if ($ws_exists) {
                        $updateWSStatusStmt = $pdo->prepare("UPDATE withdrawal_slips SET status = 'processing' WHERE pr_id = ?");
                        $updateWSStatusStmt->execute([$pr_id]);
                        
                        $updateWSItemsStatusStmt = $pdo->prepare("UPDATE withdrawal_slip_items SET status = 'processing' WHERE withdrawal_slip_id IN (SELECT id FROM withdrawal_slips WHERE pr_id = ?)");
                        $updateWSItemsStatusStmt->execute([$pr_id]);
                    }
                    
                    // Go to Approver stage
                    $next_stage = 'approver';
                    $next_stage_label = 'Approver (CEO)';
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Approved by Accounting', 
                        $remarks, 
                        $user_id,
                        'accounting',
                        $next_stage
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        $next_stage, 
                        'pending', 
                        $user_id,
                        $remarks
                    ]);
                    
                    $success_message = "PR approved and forwarded to $next_stage_label! All documents status updated to Processing.";
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'reject_accounting':
                // Only accounting admin can reject
                if ($current_stage['stage'] === 'accounting' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'Accounting' && 
                    $user['accounttype'] === 'Admin') {
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Rejected by Accounting', 
                        $remarks, 
                        $user_id,
                        'accounting',
                        'rejected'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'rejected', 
                        'rejected', 
                        $user_id,
                        $remarks
                    ]);
                    
                    // Also update PR status in purchase_requests table
                    $updatePRStmt = $pdo->prepare("UPDATE purchase_requests SET status = 'rejected' WHERE id = ?");
                    $updatePRStmt->execute([$pr_id]);
                    
                    $success_message = 'PR rejected successfully!';
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;

            case 'adjust_threshold_amount':
                // Only CEO can adjust threshold amount
                if ($current_stage['stage'] === 'approver' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'CEO' && 
                    $user['accounttype'] === 'Admin') {
                    
                    // Check if this is a project request
                    if ($request_type !== 'project') {
                        throw new Exception('Threshold adjustment is only available for project requests.');
                    }
                    
                    $threshold_amount_to_adjust = $_POST['threshold_amount_to_adjust'] ?? 0;
                    $adjustment_type = $_POST['adjustment_type'] ?? 'add';
                    
                    // Validate threshold amount
                    if (empty($threshold_amount_to_adjust) || $threshold_amount_to_adjust == 0) {
                        throw new Exception('Please enter a valid amount to adjust.');
                    }
                    
                    // Validate threshold amount is numeric
                    if (!is_numeric($threshold_amount_to_adjust) || $threshold_amount_to_adjust <= 0) {
                        throw new Exception('Invalid threshold amount. Please enter a positive number.');
                    }
                    
                    // Calculate new threshold amount
                    if ($adjustment_type === 'add') {
                        $new_threshold = $threshold_amount + $threshold_amount_to_adjust;
                        $action_text = 'Added';
                        $history_action = 'Threshold Amount Added';
                        $message_action = 'added to';
                    } else {
                        // Check if subtraction would result in negative threshold
                        if ($threshold_amount_to_adjust > $threshold_amount) {
                            throw new Exception('Cannot subtract more than the current threshold amount.');
                        }
                        $new_threshold = $threshold_amount - $threshold_amount_to_adjust;
                        $action_text = 'Subtracted';
                        $history_action = 'Threshold Amount Subtracted';
                        $message_action = 'subtracted from';
                    }
                    
                    // Update project threshold
                    if ($request_type === 'project' && $pr['project_id']) {
                        $updateThresholdStmt = $pdo->prepare("UPDATE projects SET threshold_amount = ? WHERE id = ?");
                        $updateThresholdStmt->execute([$new_threshold, $pr['project_id']]);
                    }
                    
                    // Record the action in history
                    $historyStmt->execute([
                        $pr_id, 
                        $history_action, 
                        "{$action_text} ₱" . number_format($threshold_amount_to_adjust, 2) . " {$message_action} threshold. New total: ₱" . number_format($new_threshold, 2), 
                        $user_id,
                        'approver',
                        'approver'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'approver', 
                        'pending', 
                        $user_id,
                        "{$action_text} ₱" . number_format($threshold_amount_to_adjust, 2) . " {$message_action} threshold amount"
                    ]);
                    
                    $success_message = "Threshold amount {$message_action} successfully! New threshold: ₱" . number_format($new_threshold, 2);
                    
                    // Refresh PR data to get updated threshold
                    if ($request_type === 'project') {
                        $prStmt->execute([$pr_id]);
                        $pr = $prStmt->fetch(PDO::FETCH_ASSOC);
                        $threshold_amount = $pr['threshold_amount'] ?? 0;
                        
                        // Recalculate threshold status after adjustment
                        $threshold_status = 'not_exceed';
                        if ($total_po_amount > $threshold_amount) {
                            $threshold_status = 'exceed';
                        }
                    }
                    
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'approve_approver':
                // Only CEO can approve
                if ($current_stage['stage'] === 'approver' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'CEO' && 
                    $user['accounttype'] === 'Admin') {
                    
                    // For project requests: check threshold status
                    if ($request_type === 'project' && $document_type !== 'ws') {
                        // Check if threshold is exceeded
                        if ($threshold_status === 'exceed') {
                            // Check if threshold has been adjusted
                            if (!$threshold_amount_adjusted) {
                                throw new Exception('Cannot approve PR when threshold is exceeded. You must adjust the threshold amount first.');
                            }
                        }
                    }
                    
                    // Update PR status to 'approved' in purchase_requests table
                    $updatePRStmt = $pdo->prepare("UPDATE purchase_requests SET status = 'approved' WHERE id = ?");
                    $updatePRStmt->execute([$pr_id]);
                    
                    // Update PO status to 'approved' if PO exists
                    if ($po_exists) {
                        $updatePOStatusStmt = $pdo->prepare("UPDATE purchase_orders SET status = 'approved' WHERE pr_id = ?");
                        $updatePOStatusStmt->execute([$pr_id]);
                        
                        $updatePOItemsStatusStmt = $pdo->prepare("UPDATE po_items SET status = 'approved' WHERE po_id IN (SELECT id FROM purchase_orders WHERE pr_id = ?)");
                        $updatePOItemsStatusStmt->execute([$pr_id]);
                    }
                    
                    // DETERMINE NEXT STAGE BASED ON DOCUMENT_TYPE
                    if ($request_type === 'project') {
                        if ($document_type === 'ws') {
                            // Pure WS flow - go to Warehouse Releasing
                            $next_stage = 'warehouse_releasing';
                            $next_stage_label = 'Warehouse Releasing';
                            
                            // Update withdrawal slip status to 'approved'
                            if (!empty($existing_ws)) {
                                $latest_ws = $existing_ws[0] ?? null;
                                if ($latest_ws) {
                                    $updateWSStatusStmt = $pdo->prepare("UPDATE withdrawal_slips SET status = 'approved' WHERE id = ?");
                                    $updateWSStatusStmt->execute([$latest_ws['id']]);
                                    
                                    $updateWSItemsStmt = $pdo->prepare("UPDATE withdrawal_slip_items SET status = 'approved' WHERE withdrawal_slip_id = ?");
                                    $updateWSItemsStmt->execute([$latest_ws['id']]);
                                }
                            }
                        } elseif ($document_type === 'po_ws') {
                            // Mixed PO+WS flow - go to Warehouse Releasing (to process WS)
                            $next_stage = 'warehouse_releasing';
                            $next_stage_label = 'Warehouse Releasing';
                            
                            // Update withdrawal slip status to 'approved'
                            if (!empty($existing_ws)) {
                                $latest_ws = $existing_ws[0] ?? null;
                                if ($latest_ws) {
                                    $updateWSStatusStmt = $pdo->prepare("UPDATE withdrawal_slips SET status = 'approved' WHERE id = ?");
                                    $updateWSStatusStmt->execute([$latest_ws['id']]);
                                    
                                    $updateWSItemsStmt = $pdo->prepare("UPDATE withdrawal_slip_items SET status = 'approved' WHERE withdrawal_slip_id = ?");
                                    $updateWSItemsStmt->execute([$latest_ws['id']]);
                                }
                            }
                        } elseif ($document_type === 'pr_po') {
                            // Pure PO flow - go to Purchasing Final
                            $next_stage = 'purchasing_final';
                            $next_stage_label = 'Purchasing Final';
                        } else {
                            $next_stage = 'purchasing_final';
                            $next_stage_label = 'Purchasing Final';
                        }
                    } else {
                        // Supplier request - go to Purchasing Final
                        $next_stage = 'purchasing_final';
                        $next_stage_label = 'Purchasing Final';
                        
                        // Update PO status to 'approved' if PO exists
                        if ($po_exists) {
                            $updatePOStatusStmt = $pdo->prepare("UPDATE purchase_orders SET status = 'approved' WHERE pr_id = ?");
                            $updatePOStatusStmt->execute([$pr_id]);
                            
                            $updatePOItemsStatusStmt = $pdo->prepare("UPDATE po_items SET status = 'approved' WHERE po_id IN (SELECT id FROM purchase_orders WHERE pr_id = ?)");
                            $updatePOItemsStatusStmt->execute([$pr_id]);
                        }
                    }
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Approved by Approver (CEO)', 
                        $remarks, 
                        $user_id,
                        'approver',
                        $next_stage
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        $next_stage, 
                        'pending', 
                        $user_id,
                        $remarks
                    ]);
                    
                    $success_message = 'PR approved and forwarded to ' . $next_stage_label . '! PR Status, PO Status, and PO Items updated to Approved.';
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'reject_approver':
                // Only CEO can reject
                if ($current_stage['stage'] === 'approver' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'CEO' && 
                    $user['accounttype'] === 'Admin') {
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Rejected by Approver (CEO)', 
                        $remarks, 
                        $user_id,
                        'approver',
                        'rejected'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'rejected', 
                        'rejected', 
                        $user_id,
                        $remarks
                    ]);
                    
                    // Also update PR status in purchase_requests table
                    $updatePRStmt = $pdo->prepare("UPDATE purchase_requests SET status = 'rejected' WHERE id = ?");
                    $updatePRStmt->execute([$pr_id]);
                    
                    $success_message = 'PR rejected successfully!';
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;

            case 'approve_purchasing_final':
                // Only purchasing admin can approve in purchasing_final stage
                if ($current_stage['stage'] === 'purchasing_final' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'Purchaser' && 
                    $user['accounttype'] === 'Admin') {
                    
                    // Determine next stage based on document_type
                    if ($request_type === 'project' && $document_type === 'pr_po') {
                        $next_stage = 'warehouse_receiving';
                        $next_stage_label = 'Warehouse Receiving';
                    } else {
                        $next_stage = 'warehouse_receiving';
                        $next_stage_label = 'Warehouse Receiving';
                    }
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Approved by Purchasing (Final)', 
                        $remarks, 
                        $user_id,
                        'purchasing_final',
                        $next_stage
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        $next_stage, 
                        'pending', 
                        $user_id,
                        $remarks
                    ]);
                    
                    $success_message = 'PR approved and forwarded to ' . $next_stage_label . '!';
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'reject_purchasing_final':
                // Only purchasing admin can reject in purchasing_final stage
                if ($current_stage['stage'] === 'purchasing_final' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'Purchaser' && 
                    $user['accounttype'] === 'Admin') {
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Rejected by Purchasing (Final)', 
                        $remarks, 
                        $user_id,
                        'purchasing_final',
                        'rejected'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'rejected', 
                        'rejected', 
                        $user_id,
                        $remarks
                    ]);
                    
                    // Also update PR status in purchase_requests table
                    $updatePRStmt = $pdo->prepare("UPDATE purchase_requests SET status = 'rejected' WHERE id = ?");
                    $updatePRStmt->execute([$pr_id]);
                    
                    $success_message = 'PR rejected successfully!';
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'receive_items':
                // Only warehouse admin can receive items
                if ($current_stage['stage'] === 'warehouse_receiving' && 
                    $user['department'] === 'Warehouse' && 
                    $user['accounttype'] === 'Admin') {
                    
                    // Check if items have already been received
                    if ($items_already_received) {
                        throw new Exception('Items have already been received for this PR. You cannot receive items again.');
                    }
                    
                    // Check if document type allows receiving
                    if ($request_type === 'project' && $document_type === 'ws') {
                        throw new Exception('Warehouse Receiving stage is not applicable for Pure Withdrawal Slip (WS) document type.');
                    }
                    
                    // Process received items
                    if (isset($_POST['received_items']) && is_array($_POST['received_items'])) {
                        $latest_po = $existing_pos[0] ?? null;
                        if (!$latest_po) {
                            throw new Exception('No purchase order found.');
                        }
                        
                        foreach ($_POST['received_items'] as $po_item_id => $received_data) {
                            $quantity_received = floatval($received_data['quantity']);
                            $unit_cost = floatval($received_data['unit_cost']);
                            $batch_number = $received_data['batch_number'];
                            $received_date = $received_data['received_date'];
                            
                            if ($quantity_received > 0) {
                                // Get PO item details
                                $poItemStmt = $pdo->prepare("
                                    SELECT poi.*, i.item_name, w.warehouse_name, po.po_number, poi.pr_item_id
                                    FROM po_items poi
                                    LEFT JOIN item_names i ON poi.item_id = i.id
                                    LEFT JOIN warehouses w ON poi.warehouse_id = w.id
                                    LEFT JOIN purchase_orders po ON poi.po_id = po.id
                                    WHERE poi.id = ?
                                ");
                                $poItemStmt->execute([$po_item_id]);
                                $po_item = $poItemStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($po_item) {
                                    // Add to inventory batches
                                    $batchStmt = $pdo->prepare("
                                        INSERT INTO inventory_batches 
                                        (item_id, warehouse_id, quantity, unit_cost, batch_number, received_date, purchase_order, supplier_id) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                    ");
                                    $batchStmt->execute([
                                        $po_item['item_id'],
                                        $po_item['warehouse_id'],
                                        $quantity_received,
                                        $unit_cost,
                                        $batch_number,
                                        $received_date,
                                        $po_item['po_number'],
                                        $po_item['supplier_id']
                                    ]);
                                    
                                    // Update inventory table
                                    updateInventoryTable($pdo, $po_item['item_id'], $po_item['warehouse_id']);
                                    
                                    // Insert into stock_movements table
                                    $movementStmt = $pdo->prepare("
                                        INSERT INTO stock_movements 
                                        (item_id, supplier_id, warehouse_id, quantity, unit_cost, total_value, movement_type, movement_date, batch_number, purchase_order, purchase_request) 
                                        VALUES (?, ?, ?, ?, ?, ?, 'in', ?, ?, ?, ?)
                                    ");
                                    $total_value = $quantity_received * $unit_cost;
                                    $movementStmt->execute([
                                        $po_item['item_id'],
                                        $po_item['supplier_id'],
                                        $po_item['warehouse_id'],
                                        $quantity_received,
                                        $unit_cost,
                                        $total_value,
                                        $received_date,
                                        $batch_number,
                                        $po_item['po_number'],
                                        $pr['pr_number']
                                    ]);
                                    
                                    // Update PO item with received quantity
                                    $original_quantity = floatval($po_item['quantity']);
                                    $received_so_far = floatval($po_item['received_quantity'] ?? 0);
                                    $new_received_total = $received_so_far + $quantity_received;
                                    
                                    // Determine status based on received quantity
                                    if ($new_received_total >= $original_quantity) {
                                        $status = 'delivered';
                                    } else if ($new_received_total > 0) {
                                        $status = 'partially_received';
                                    } else {
                                        $status = 'pending';
                                    }
                                    
                                    // Update PO item
                                    $updatePOItemStmt = $pdo->prepare("
                                        UPDATE po_items 
                                        SET received_quantity = :received_quantity, 
                                            status = :status, 
                                            received_date = :received_date,
                                            total_cost = :total_cost
                                        WHERE id = :id
                                    ");
                                    $updatePOItemStmt->bindParam(':received_quantity', $new_received_total);
                                    $updatePOItemStmt->bindParam(':status', $status);
                                    $updatePOItemStmt->bindParam(':received_date', $received_date);
                                    $updatePOItemStmt->bindParam(':total_cost', $total_value);
                                    $updatePOItemStmt->bindParam(':id', $po_item_id);
                                    $updatePOItemStmt->execute();
                                    
                                    // ===== FIX: Update pr_items.supplier_received_quantity for ALL document types =====
                                    // This is the key fix - we need to update supplier_received_quantity for the PR item
                                    
                                    // Get current supplier_received_quantity from pr_items
                                    $prItemStmt = $pdo->prepare("
                                        SELECT supplier_received_quantity, delivered_quantity 
                                        FROM pr_items 
                                        WHERE id = ?
                                    ");
                                    $prItemStmt->execute([$po_item['pr_item_id']]);
                                    $pr_item = $prItemStmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    $current_supplier_received = floatval($pr_item['supplier_received_quantity'] ?? 0);
                                    $new_supplier_received_total = $current_supplier_received + $quantity_received;
                                    
                                    // Update pr_items table with supplier_received_quantity
                                    $updatePrItemStmt = $pdo->prepare("
                                        UPDATE pr_items 
                                        SET supplier_received_quantity = supplier_received_quantity + :quantity_received
                                        WHERE id = :pr_item_id
                                    ");
                                    $updatePrItemStmt->bindParam(':quantity_received', $quantity_received);
                                    $updatePrItemStmt->bindParam(':pr_item_id', $po_item['pr_item_id']);
                                    $updatePrItemStmt->execute();
                                    
                                    // For project PR_PO document type, also update delivered_quantity
                                    // (delivered_quantity is used for warehouse withdrawals in po_ws flow)
                                    if ($request_type === 'project' && $document_type === 'pr_po') {
                                        // For pure PO flow, delivered_quantity should track supplier received items
                                        $current_delivered = floatval($pr_item['delivered_quantity'] ?? 0);
                                        $new_delivered_total = $current_delivered + $quantity_received;
                                        
                                        $updatePrItemDeliveredStmt = $pdo->prepare("
                                            UPDATE pr_items 
                                            SET delivered_quantity = :delivered_quantity
                                            WHERE id = :pr_item_id
                                        ");
                                        $updatePrItemDeliveredStmt->bindParam(':delivered_quantity', $new_delivered_total);
                                        $updatePrItemDeliveredStmt->bindParam(':pr_item_id', $po_item['pr_item_id']);
                                        $updatePrItemDeliveredStmt->execute();
                                    }
                                    
                                    // For Stock PR, update delivered_quantity
                                    if ($request_type === 'supplier') {
                                        $current_delivered = floatval($pr_item['delivered_quantity'] ?? 0);
                                        $new_delivered_total = $current_delivered + $quantity_received;
                                        
                                        $updatePrItemDeliveredStmt = $pdo->prepare("
                                            UPDATE pr_items 
                                            SET delivered_quantity = :delivered_quantity
                                            WHERE id = :pr_item_id
                                        ");
                                        $updatePrItemDeliveredStmt->bindParam(':delivered_quantity', $new_delivered_total);
                                        $updatePrItemDeliveredStmt->bindParam(':pr_item_id', $po_item['pr_item_id']);
                                        $updatePrItemDeliveredStmt->execute();
                                    }
                                    
                                    // Check if all items for this PR have been fully received from supplier
                                    // For po_ws document type, we need to check both supplier_received_quantity AND if any items were withdrawn from warehouse
                                    $checkAllReceivedStmt = $pdo->prepare("
                                        SELECT 
                                            COUNT(*) as total_items,
                                            SUM(CASE 
                                                WHEN (quantity - COALESCE(supplier_received_quantity, 0) - COALESCE(delivered_quantity, 0)) <= 0 
                                                THEN 1 ELSE 0 END) as fully_fulfilled
                                        FROM pr_items 
                                        WHERE pr_id = ?
                                    ");
                                    $checkAllReceivedStmt->execute([$pr_id]);
                                    $fulfillment_status = $checkAllReceivedStmt->fetch(PDO::FETCH_ASSOC);

                                    // Only update PR status to completed if all items are fully fulfilled AND this is NOT a PR_PO document type
                                    // For PR_PO document type, we want to keep the status as 'approved' even when items are received
                                    if ($fulfillment_status['total_items'] == $fulfillment_status['fully_fulfilled']) {
                                        // For PR_PO document type, keep status as 'approved' (don't change to 'completed')
                                        if ($document_type !== 'pr_po') {
                                            $updatePRStatusStmt = $pdo->prepare("
                                                UPDATE purchase_requests 
                                                SET status = 'completed' 
                                                WHERE id = ?
                                            ");
                                            $updatePRStatusStmt->execute([$pr_id]);
                                        }
                                        // For PR_PO, we explicitly do NOT update the status - it stays as 'approved'
                                    }
                                    // ===== END FIX =====
                                }
                            }
                        }
                        
                        // Update the purchase_orders table status to 'confirmed' when items are received
                        $updatePOStmt = $pdo->prepare("
                            UPDATE purchase_orders 
                            SET status = 'confirmed' 
                            WHERE id = ? AND status != 'cancelled'
                        ");
                        $updatePOStmt->execute([$latest_po['id']]);
                        
                        // Record the items received action in routing history
                        $historyStmt->execute([
                            $pr_id, 
                            'Items Received', 
                            $remarks, 
                            $user_id,
                            'warehouse_receiving',
                            'warehouse_receiving'
                        ]);
                        
                        $routingStmt->execute([
                            $pr_id, 
                            'warehouse_receiving', 
                            'pending', 
                            $user_id,
                            $remarks
                        ]);
                        
                        $success_message = 'Items received and inventory updated successfully! PO status updated to confirmed.';
                        
                        // Add specific message for different document types
                        if ($request_type === 'project' && $document_type === 'po_ws') {
                            $success_message .= ' PR items updated with supplier received quantities.';
                        } elseif ($request_type === 'project' && $document_type === 'pr_po') {
                            $success_message .= ' PR items updated with delivered quantities.';
                        } elseif ($request_type === 'supplier') {
                            $success_message .= ' PR items updated with delivered quantities.';
                        }
                        
                    } else {
                        throw new Exception('No items received data provided.');
                    }
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'process_withdrawal_slip':
    // Only warehouse admin can process withdrawal slip
    if (($current_stage['stage'] ?? '') === 'warehouse_releasing' && 
        $user['department'] === 'Warehouse' && 
        $user['accounttype'] === 'Admin') {
        
        // Check if withdrawal slip has already been processed
        if ($ws_already_processed) {
            throw new Exception('Withdrawal slip has already been processed.');
        }
        
        // Check if withdrawal slip is approved first
        if (!$ws_approved) {
            throw new Exception('Withdrawal slip must be approved by Approver (CEO) before you can release items. Please wait for approval.');
        }
        
        // Check if document type allows WS processing
        if ($request_type === 'project' && $document_type === 'pr_po') {
            throw new Exception('Withdrawal Slip processing is not applicable for Pure Purchase Order (PR_PO) document type.');
        }
        
        // Process withdrawal slip items
        $ws_id = $_POST['ws_id'] ?? 0;
        $released_date = $_POST['released_date'] ?? date('Y-m-d');
        
        if ($ws_id <= 0) {
            throw new Exception('Invalid withdrawal slip ID.');
        }
        
        // Update withdrawal slip status to 'released'
        $updateWSSlipStmt = $pdo->prepare("
            UPDATE withdrawal_slips 
            SET released_date = :released_date, 
                status = 'released'
            WHERE id = :ws_id
        ");
        $updateWSSlipStmt->bindParam(':released_date', $released_date);
        $updateWSSlipStmt->bindParam(':ws_id', $ws_id);
        $updateWSSlipStmt->execute();
        
        // Get withdrawal slip items
        $wsItemsStmt = $pdo->prepare("
            SELECT wsi.*, i.item_name, w.warehouse_name
            FROM withdrawal_slip_items wsi
            LEFT JOIN item_names i ON wsi.item_id = i.id
            LEFT JOIN warehouses w ON wsi.warehouse_id = w.id
            WHERE wsi.withdrawal_slip_id = ?
        ");
        $wsItemsStmt->execute([$ws_id]);
        $ws_items = $wsItemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_withdrawn = 0;
        $batch_numbers_used = [];
        
        foreach ($ws_items as $ws_item) {
            $item_id = $ws_item['item_id'];
            $warehouse_id = $ws_item['warehouse_id'];
            $quantity_to_withdraw = $ws_item['quantity'];
            
            // Get available batches sorted by received_date (FIFO)
            $batchStmt = $pdo->prepare("
                SELECT id, quantity, unit_cost, batch_number 
                FROM inventory_batches 
                WHERE item_id = :item_id AND warehouse_id = :warehouse_id AND quantity > 0 
                ORDER BY received_date ASC
            ");
            $batchStmt->bindParam(':item_id', $item_id);
            $batchStmt->bindParam(':warehouse_id', $warehouse_id);
            $batchStmt->execute();
            $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate total available stock
            $total_available = 0;
            foreach ($batches as $batch) {
                $total_available += $batch['quantity'];
            }
            
            if ($total_available >= $quantity_to_withdraw) {
                $remaining_quantity = $quantity_to_withdraw;
                $item_batch_numbers = [];
                $item_actual_total_cost = 0; // Track actual total cost for this item
                $item_weighted_unit_cost = 0; // Will calculate after processing
                
                // Process each batch in FIFO order
                foreach ($batches as $batch) {
                    if ($remaining_quantity <= 0) break;
                    
                    $batch_id = $batch['id'];
                    $batch_qty = $batch['quantity'];
                    $batch_cost = $batch['unit_cost'];
                    $batch_number = $batch['batch_number'];
                    
                    // Determine how much to take from this batch
                    $take_qty = min($remaining_quantity, $batch_qty);
                    
                    // Record stock withdrawal
                    $withdrawalStmt = $pdo->prepare("
                        INSERT INTO stock_withdrawals 
                        (withdrawal_slip_id, withdrawal_slip_item_id, item_id, batch_id, quantity, unit_cost, total_cost, withdrawal_date, withdrawal_by, remarks) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $withdrawal_total = $take_qty * $batch_cost;
                    $withdrawalStmt->execute([
                        $ws_id,
                        $ws_item['id'],
                        $item_id,
                        $batch_id,
                        $take_qty,
                        $batch_cost,
                        $withdrawal_total,
                        $released_date,
                        $user_id,
                        "Withdrawn via Withdrawal Slip"
                    ]);
                    
                    // Update batch quantity
                    $updateStmt = $pdo->prepare("UPDATE inventory_batches SET quantity = quantity - :take_qty WHERE id = :batch_id");
                    $updateStmt->bindParam(':take_qty', $take_qty);
                    $updateStmt->bindParam(':batch_id', $batch_id);
                    $updateStmt->execute();
                    
                    // Record stock movement (out)
                    $movementStmt = $pdo->prepare("
                        INSERT INTO stock_movements 
                        (item_id, project_id, warehouse_id, quantity, unit_cost, movement_type, movement_date, batch_number, purchase_request) 
                        VALUES (:item_id, :project_id, :warehouse_id, :quantity, :unit_cost, 'out', :movement_date, :batch_number, :purchase_request)
                    ");
                    $movementStmt->bindParam(':item_id', $item_id);
                    $movementStmt->bindParam(':project_id', $pr['project_id']);
                    $movementStmt->bindParam(':warehouse_id', $warehouse_id);
                    $movementStmt->bindParam(':quantity', $take_qty);
                    $movementStmt->bindParam(':unit_cost', $batch_cost);
                    $movementStmt->bindParam(':movement_date', $released_date);
                    $movementStmt->bindParam(':batch_number', $batch_number);
                    $movementStmt->bindParam(':purchase_request', $pr['pr_number']);
                    $movementStmt->execute();
                    
                    $remaining_quantity -= $take_qty;
                    $item_batch_numbers[] = $batch_number;
                    $total_withdrawn += $withdrawal_total;
                    $item_actual_total_cost += $withdrawal_total;
                }
                
                // ===== FIX: Update withdrawal_slip_items with actual unit_cost and total_cost =====
                // Calculate weighted average unit cost based on actual batches used
                if ($quantity_to_withdraw > 0) {
                    $item_weighted_unit_cost = $item_actual_total_cost / $quantity_to_withdraw;
                    
                    // Update withdrawal slip item with actual costs based on FIFO
                    $updateWSItemCostStmt = $pdo->prepare("
                        UPDATE withdrawal_slip_items 
                        SET unit_cost = :unit_cost, 
                            total_cost = :total_cost,
                            released_date = :released_date, 
                            status = 'released'
                        WHERE id = :ws_item_id
                    ");
                    $updateWSItemCostStmt->bindParam(':unit_cost', $item_weighted_unit_cost);
                    $updateWSItemCostStmt->bindParam(':total_cost', $item_actual_total_cost);
                    $updateWSItemCostStmt->bindParam(':released_date', $released_date);
                    $updateWSItemCostStmt->bindParam(':ws_item_id', $ws_item['id']);
                    $updateWSItemCostStmt->execute();
                }
                
                // Update inventory table
                updateInventoryTable($pdo, $item_id, $warehouse_id);
                
                // Update withdrawal slip item with batch numbers
                $updateWSItemStmt = $pdo->prepare("
                    UPDATE withdrawal_slip_items 
                    SET batch_numbers = ?
                    WHERE id = ?
                ");
                $updateWSItemStmt->execute([json_encode($item_batch_numbers), $ws_item['id']]);
                
                // ===== FIX: Update pr_items.delivered_quantity for ALL document types =====
                // Get the pr_item_id from the withdrawal slip item
                $prItemIdStmt = $pdo->prepare("
                    SELECT pr_item_id FROM withdrawal_slip_items WHERE id = ?
                ");
                $prItemIdStmt->execute([$ws_item['id']]);
                $pr_item_data = $prItemIdStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($pr_item_data && $pr_item_data['pr_item_id']) {
                    $pr_item_id = $pr_item_data['pr_item_id'];
                    
                    // Get current delivered_quantity from pr_items
                    $prItemStmt = $pdo->prepare("
                        SELECT delivered_quantity 
                        FROM pr_items 
                        WHERE id = ?
                    ");
                    $prItemStmt->execute([$pr_item_id]);
                    $pr_item = $prItemStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $current_delivered = floatval($pr_item['delivered_quantity'] ?? 0);
                    $new_delivered_total = $current_delivered + $quantity_to_withdraw;
                    
                    // Update pr_items table with delivered_quantity
                    $updatePrItemStmt = $pdo->prepare("
                        UPDATE pr_items 
                        SET delivered_quantity = delivered_quantity + :quantity_to_withdraw
                        WHERE id = :pr_item_id
                    ");
                    $updatePrItemStmt->bindParam(':quantity_to_withdraw', $quantity_to_withdraw);
                    $updatePrItemStmt->bindParam(':pr_item_id', $pr_item_id);
                    $updatePrItemStmt->execute();
                    
                    // Check if all items for this PR have been fully fulfilled
                    $checkAllFulfilledStmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total_items,
                            SUM(CASE 
                                WHEN (quantity - COALESCE(delivered_quantity, 0)) <= 0 
                                THEN 1 ELSE 0 END) as fully_fulfilled
                        FROM pr_items 
                        WHERE pr_id = ?
                    ");
                    $checkAllFulfilledStmt->execute([$pr_id]);
                    $fulfillment_status = $checkAllFulfilledStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // If all items are fully fulfilled, update PR status to completed
                    if ($fulfillment_status['total_items'] == $fulfillment_status['fully_fulfilled']) {
                        $updatePRStatusStmt = $pdo->prepare("
                            UPDATE purchase_requests 
                            SET status = 'completed' 
                            WHERE id = ?
                        ");
                        $updatePRStatusStmt->execute([$pr_id]);
                    }
                }
                // ===== END FIX =====
                
            } else {
                throw new Exception("Insufficient stock available for item: " . $ws_item['item_name']);
            }
        }
        
        // Deduct the total withdrawn amount from project threshold
        if ($request_type === 'project' && $pr['project_id'] && $total_withdrawn > 0) {
            $deductStmt = $pdo->prepare("
                UPDATE projects 
                SET threshold_amount = GREATEST(0, threshold_amount - :total_withdrawn)
                WHERE id = :project_id
            ");
            $deductStmt->bindParam(':total_withdrawn', $total_withdrawn);
            $deductStmt->bindParam(':project_id', $pr['project_id']);
            $deductStmt->execute();
            
            // Refresh the PR data to get the updated threshold amount
            $prStmt->execute([$pr_id]);
            $pr = $prStmt->fetch(PDO::FETCH_ASSOC);
            $threshold_amount = $pr['threshold_amount'] ?? 0;
        }
        
        // Determine next stage based on document_type
        if ($request_type === 'project') {
            if ($document_type === 'ws') {
                // For Pure WS flow, after processing WS, stay in warehouse_releasing stage
                // User must manually complete the stage after items are released
                $next_stage = 'warehouse_releasing';
                $next_stage_label = 'Warehouse Releasing';
            } elseif ($document_type === 'po_ws') {
                // Mixed PO+WS flow - after processing WS, stay in warehouse_releasing
                // User must manually complete the stage after items are released
                $next_stage = 'warehouse_releasing';
                $next_stage_label = 'Warehouse Releasing';
            } else {
                // Should not reach here for other flows
                $next_stage = $current_stage['stage'];
                $next_stage_label = $current_stage['stage'];
            }
        } else {
            // Supplier - should not have WS
            $next_stage = 'completed';
            $next_stage_label = 'Completed';
            $updatePRStmt = $pdo->prepare("UPDATE purchase_requests SET status = 'completed' WHERE id = ?");
            $updatePRStmt->execute([$pr_id]);
        }
        
        // Record the action in routing history
        $historyStmt->execute([
            $pr_id, 
            'Withdrawal Slip Processed', 
            "Withdrawal slip processed. Total withdrawn: ₱" . number_format($total_withdrawn, 2) . 
            ($request_type === 'project' ? ". ₱" . number_format($total_withdrawn, 2) . " deducted from project threshold." : ""), 
            $user_id,
            $current_stage['stage'],
            $next_stage
        ]);
        
        // Update routing - stay in same stage (warehouse_releasing)
        $routingStmt->execute([
            $pr_id, 
            $next_stage, 
            'pending', 
            $user_id,
            "Withdrawal slip processed"
        ]);
        
        $success_message = 'Withdrawal slip processed successfully! Items released from inventory.' . 
            ($request_type === 'project' ? ' ₱' . number_format($total_withdrawn, 2) . ' deducted from project threshold.' : '');
        
    } else {
        throw new Exception('You are not authorized to perform this action.');
    }
    break;
                
            case 'complete_warehouse_receiving':
                // Only warehouse admin can complete warehouse receiving
                if ($current_stage['stage'] === 'warehouse_receiving' && 
                    $user['department'] === 'Warehouse' && 
                    $user['accounttype'] === 'Admin') {
                    
                    // Check if document type allows warehouse receiving completion
                    if ($request_type === 'project' && $document_type === 'ws') {
                        throw new Exception('Warehouse Receiving stage is not applicable for Pure Withdrawal Slip (WS) document type.');
                    }
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Completed Warehouse Receiving', 
                        $remarks, 
                        $user_id,
                        'warehouse_receiving',
                        'purchasing_completion'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'purchasing_completion', 
                        'pending', 
                        $user_id,
                        $remarks
                    ]);
                    
                    $success_message = 'Warehouse Receiving completed! Forwarded to Purchasing Completion.';
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'complete_warehouse_releasing':
                // Only warehouse admin can complete warehouse releasing
                if ($current_stage['stage'] === 'warehouse_releasing' && 
                    $user['department'] === 'Warehouse' && 
                    $user['accounttype'] === 'Admin') {
                    
                    // Check if withdrawal slip has been processed
                    if (!$ws_already_processed) {
                        throw new Exception('You must process the withdrawal slip before completing warehouse releasing.');
                    }
                    
                    // Determine next stage based on document_type
                    if ($request_type === 'project') {
                        if ($document_type === 'ws') {
                            // FIXED: For Pure WS flow, after warehouse releasing, go to Completed
                            $next_stage = 'completed';
                            $next_stage_label = 'Completed';
                            
                            // Update PR status to completed
                            $updatePRStmt = $pdo->prepare("UPDATE purchase_requests SET status = 'completed' WHERE id = ?");
                            $updatePRStmt->execute([$pr_id]);
                        } elseif ($document_type === 'po_ws') {
                            // For PO_WS flow, after warehouse releasing, go to Purchasing Final
                            $next_stage = 'purchasing_final';
                            $next_stage_label = 'Purchasing Final';
                            
                        } else {
                            // Should not reach here
                            $next_stage = 'completed';
                            $next_stage_label = 'Completed';
                            
                            // Update PR status to completed
                            $updatePRStmt = $pdo->prepare("UPDATE purchase_requests SET status = 'completed' WHERE id = ?");
                            $updatePRStmt->execute([$pr_id]);
                        }
                    } else {
                        // Supplier - should not have WS
                        $next_stage = 'completed';
                        $next_stage_label = 'Completed';
                        $updatePRStmt = $pdo->prepare("UPDATE purchase_requests SET status = 'completed' WHERE id = ?");
                        $updatePRStmt->execute([$pr_id]);
                    }
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Completed Warehouse Releasing', 
                        $remarks, 
                        $user_id,
                        'warehouse_releasing',
                        $next_stage
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        $next_stage, 
                        'pending', 
                        $user_id,
                        $remarks
                    ]);
                    
                    $success_message = 'Warehouse Releasing completed!';
                    
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'complete_purchasing':
                // Only purchasing admin can complete
                if ($current_stage['stage'] === 'purchasing_completion' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'Purchaser' && 
                    $user['accounttype'] === 'Admin') {
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Completed by Purchasing', 
                        $remarks, 
                        $user_id,
                        'purchasing_completion',
                        'accounting_final'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'accounting_final', 
                        'pending', 
                        $user_id,
                        $remarks
                    ]);
                    
                    $success_message = 'PR processing completed and forwarded to Accounting!';
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'finalize_accounting':
                // Only accounting admin can finalize
                if ($current_stage['stage'] === 'accounting_final' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'Accounting' && 
                    $user['accounttype'] === 'Admin') {
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Finalized by Accounting', 
                        $remarks, 
                        $user_id,
                        'accounting_final',
                        'completed'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'completed', 
                        'completed', 
                        $user_id,
                        $remarks
                    ]);
                    
                    // Also update PR status in purchase_requests table
                    $updatePRStmt = $pdo->prepare("UPDATE purchase_requests SET status = 'completed' WHERE id = ?");
                    $updatePRStmt->execute([$pr_id]);
                    
                    $success_message = 'PR finalized and completed successfully!';
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            default:
                throw new Exception('Invalid action specified.');
        }
        
        $pdo->commit();
        
        $_SESSION['swal_data'] = array(
            'title' => 'Success!',
            'text' => $success_message,
            'icon' => 'success'
        );
        
        // Refresh the page to show updated routing
        header("Location: pr_view_routing.php?id=" . $pr_id);
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_data'] = array(
            'title' => 'Error!',
            'text' => 'Failed to process action: ' . $e->getMessage(),
            'icon' => 'error'
        );
        header("Location: pr_view_routing.php?id=" . $pr_id);
        exit();
    }
}

// Function to get status badge class
function getStatusBadge($status) {
    switch ($status) {
        case 'approved':
            return 'badge bg-success';
        case 'rejected':
            return 'badge bg-danger';
        case 'pending':
            return 'badge bg-warning';
        case 'processing':
            return 'badge bg-info';
        case 'completed':
            return 'badge bg-primary';
        default:
            return 'badge bg-secondary';
    }
}

// Function to format user name
function formatUserName($user) {
    if (!$user) return 'Unknown User';
    
    $name = $user['firstname'] ?? '';
    if (!empty($user['middlename'])) {
        $name .= ' ' . substr($user['middlename'], 0, 1) . '.';
    }
    $name .= ' ' . ($user['lastname'] ?? '');
    if (!empty($user['suffix'])) {
        $name .= ' ' . $user['suffix'];
    }
    return $name;
}

// Function to get request type badge based on document_type
function getRequestTypeBadge($type, $document_type) {
    if ($type === 'project') {
        switch($document_type) {
            case 'ws':
                return '<span class="badge bg-success">Project WS</span>';
            case 'pr_po':
                return '<span class="badge bg-info">Project PO</span>';
            case 'po_ws':
                return '<span class="badge bg-warning">Project PO+WS</span>';
            default:
                return '<span class="badge bg-info">Project PR</span>';
        }
    } elseif ($type === 'supplier') {
        return '<span class="badge bg-primary">Stock PR</span>';
    } else {
        return '<span class="badge bg-secondary">' . ucfirst($type) . '</span>';
    }
}

// Function to format date to mm-dd-yyyy
function formatDateMDY($date) {
    if (!$date || $date == '0000-00-00') {
        return '-';
    }
    return date('m-d-Y', strtotime($date));
}

// Function to format datetime to mm-dd-yyyy hh:mm:ss
function formatDateTimeMDY($datetime) {
    if (!$datetime) {
        return '-';
    }
    return date('m-d-Y H:i:s', strtotime($datetime));
}

// Function to format item name with code
function formatItemNameWithCode($item_name, $item_code) {
    if (empty($item_code)) {
        return htmlspecialchars($item_name ?? 'N/A');
    }
    return htmlspecialchars($item_name ?? 'N/A') . ' (' . htmlspecialchars($item_code) . ')';
}

// Function to safely get array value with default
function safeArrayGet($array, $key, $default = '') {
    if (is_array($array) && isset($array[$key])) {
        return $array[$key];
    }
    return $default;
}

// Check for session-based SweetAlert data
$swal_data = array();
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

// Check if threshold is zero (needs to be set)
$threshold_needs_setting = ($request_type === 'project' && $threshold_amount == 0 && $document_type !== 'ws');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>
        <?php 
        if ($request_type === 'project') {
            switch($document_type) {
                case 'ws':
                    echo 'WS Routing - ' . htmlspecialchars(!empty($existing_ws) ? ($existing_ws[0]['ws_number'] ?? 'Not Created') : 'Not Created') . ' - OCP Construction';
                    break;
                case 'pr_po':
                    echo 'PR Routing - ' . htmlspecialchars($pr['pr_number'] ?? '') . ' - OCP Construction';
                    break;
                case 'po_ws':
                    echo 'PR Routing - ' . htmlspecialchars($pr['pr_number'] ?? '') . ' - OCP Construction';
                    break;
                default:
                    echo 'PR Routing - ' . htmlspecialchars($pr['pr_number'] ?? '') . ' - OCP Construction';
            }
        } else {
            echo 'PR Routing - ' . htmlspecialchars($pr['pr_number'] ?? '') . ' - OCP Construction';
        }
        ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .routing-timeline {
            position: relative;
            padding-left: 30px;
        }
        .routing-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #6c757d;
            border: 2px solid white;
        }
        .timeline-item.current::before {
            background-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .timeline-item.completed::before {
            background-color: #198754;
        }
        .action-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .item-row {
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
        }
        .item-row:last-child {
            border-bottom: none;
        }
        .stock-status {
            font-weight: bold;
        }
        .stock-available {
            color: #198754;
        }
        .stock-low {
            color: #fd7e14;
        }
        .stock-out {
            color: #dc3545;
        }
        .delivered {
            color: #198754;
        }
        .partially-delivered {
            color: #ffc107;
        }
        .po-item-row {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }
        .supplier-select {
            min-width: 200px;
        }
        .threshold-info {
            background-color: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .threshold-exceed {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .threshold-not-exceed {
            background-color: #d1e7dd;
            border-left: 4px solid #198754;
        }
        .threshold-form {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .received-alert {
            background-color: #d1e7dd;
            border-left: 4px solid #198754;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .receiving-status {
            font-weight: bold;
        }
        .status-pending {
            color: #6c757d;
        }
        .status-partial {
            color: #fd7e14;
        }
        .status-delivered {
            color: #198754;
        }
        .threshold-warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .adjust-threshold-form {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .view-only {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        .disabled-button {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .approval-restriction {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .adjustment-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .adjustment-buttons .btn {
            flex: 1;
        }
        .btn-add-threshold {
            background-color: #198754;
            border-color: #198754;
        }
        .btn-subtract-threshold {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .routing-actions-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .routing-actions-buttons .btn {
            flex: 1;
        }
        .single-action-button {
            margin-top: 15px;
        }
        .horizontal-timeline {
            display: flex;
            justify-content: space-between;
            overflow-x: auto;
            padding: 20px 0;
            position: relative;
            margin-bottom: 20px;
            min-width: 100%;
        }
        .horizontal-timeline::before {
            content: '';
            position: absolute;
            top: 40px;
            left: 0;
            right: 0;
            height: 3px;
            background-color: #dee2e6;
            z-index: 1;
        }
        .timeline-stage {
            flex: 0 0 auto;
            min-width: 140px;
            max-width: 160px;
            text-align: center;
            position: relative;
            padding: 0 5px;
            z-index: 2;
            margin: 0 2px;
        }
        .timeline-stage.completed .timeline-icon {
            background-color: #198754;
            border-color: #198754;
            color: white;
        }
        .timeline-stage.current .timeline-icon {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.2);
        }
        .timeline-stage.pending .timeline-icon {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: #6c757d;
        }
        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 3px solid white;
            margin: 0 auto 10px;
            position: relative;
            z-index: 3;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s ease;
        }
        .timeline-label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
            white-space: normal;
            line-height: 1.2;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            word-break: break-word;
            padding: 0 2px;
        }
        .timeline-status {
            font-size: 11px;
            color: #6c757d;
        }
        .timeline-stage.completed .timeline-status {
            color: #198754;
            font-weight: 600;
        }
        .timeline-stage.current .timeline-status {
            color: #0d6efd;
            font-weight: 600;
        }
        .final-status {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin-top: 20px;
        }
        .final-status.completed {
            background-color: #d1e7dd;
            border-left: 4px solid #198754;
        }
        .final-status.rejected {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .scroll-container {
            overflow-x: auto;
            padding: 10px 0;
            margin-bottom: 20px;
            -webkit-overflow-scrolling: touch;
        }
        .scroll-container::-webkit-scrollbar {
            height: 6px;
        }
        .scroll-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .scroll-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        .scroll-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .timeline-stage.completed .fa-user,
        .timeline-stage.completed .fa-warehouse,
        .timeline-stage.completed .fa-shopping-cart,
        .timeline-stage.completed .fa-calculator,
        .timeline-stage.completed .fa-user-check,
        .timeline-stage.completed .fa-truck-loading,
        .timeline-stage.completed .fa-clipboard-check {
            color: white;
        }
        .timeline-stage.current .fa-user,
        .timeline-stage.current .fa-warehouse,
        .timeline-stage.current .fa-shopping-cart,
        .timeline-stage.current .fa-calculator,
        .timeline-stage.current .fa-user-check,
        .timeline-stage.current .fa-truck-loading,
        .timeline-stage.current .fa-clipboard-check {
            color: white;
        }
        .timeline-stage.pending .fa-user,
        .timeline-stage.pending .fa-warehouse,
        .timeline-stage.pending .fa-shopping-cart,
        .timeline-stage.pending .fa-calculator,
        .timeline-stage.pending .fa-user-check,
        .timeline-stage.pending .fa-truck-loading,
        .timeline-stage.pending .fa-clipboard-check {
            color: #6c757d;
        }
        .request-type-badge {
            font-size: 0.9em;
            padding: 0.25em 0.6em;
        }
        .no-warehouse {
            color: #6c757d;
            font-style: italic;
        }
        .supplier-info {
            color: #0d6efd;
            font-weight: 500;
        }
        .ws-item-row {
            background-color: #e7f9ed;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .withdrawal-info {
            background-color: #e7f9ed;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .warehouse-releasing-info {
            background-color: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .delivery-requirement {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .hidden-actions {
            display: none;
        }
        .badge-ws-completed {
            background-color: #198754;
            color: white;
        }
        .flow-indicator {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            margin-left: 5px;
        }
        .flow-pows {
            background-color: #ffc107;
            color: #000;
        }
        .document-creation-buttons {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        .document-creation-buttons .btn {
            flex: 1;
        }
        .document-badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            margin-left: 5px;
        }
        .document-ws {
            background-color: #198754;
            color: white;
        }
        .document-pr_po {
            background-color: #0d6efd;
            color: white;
        }
        .document-po_ws {
            background-color: #ffc107;
            color: black;
        }
        .batch-column {
            display: none;
        }
        .approval-note {
            font-size: 0.9rem;
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 4px;
            padding: 10px;
            margin-top: 10px;
        }
        .badge-default {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php include 'includes/top_bar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'includes/side_menu.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-4">
                        <?php 
                        if ($request_type === 'project') {
                            switch($document_type) {
                                case 'ws':
                                    $ws_number = (!empty($existing_ws) && isset($existing_ws[0]['ws_number'])) ? $existing_ws[0]['ws_number'] : 'Not Created';
                                    echo 'Materials Request (PROJECT)';
                                    break;
                                case 'pr_po':
                                    echo 'Materials Request (PROJECT)';
                                    break;
                                case 'po_ws':
                                    echo 'Materials Request (PROJECT)';
                                    break;
                                default:
                                    echo 'Materials Request (PROJECT)';
                            }
                        } else {
                            echo 'Materials Request (STOCK)';
                        }
                        ?>
                    </h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="purchase_request.php">Purchase Requests</a></li>
                        <li class="breadcrumb-item active">
                            <?php 
                            if ($request_type === 'project') {
                                switch($document_type) {
                                    case 'ws':
                                        echo 'WS Routing (Warehouse)';
                                        break;
                                    case 'pr_po':
                                        echo 'PR Routing (Warehouse)';
                                        break;
                                    case 'po_ws':
                                        echo 'PR Routing (Warehouse)';
                                        break;
                                    default:
                                        echo 'PR Routing (Warehouse)';
                                }
                            } else {
                                echo 'PR Routing (Warehouse)';
                            }
                            ?>
                        </li>
                    </ol>

                    <!-- PR Details Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-info-circle me-1"></i>
                            <?php if ($request_type === 'project' && $document_type === 'ws'): ?>
                                Withdrawal Slip Details
                            <?php else: ?>
                                Purchase Request Details
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th>Document Type:</th>
                                            <td>
                                                <?php 
                                                if ($request_type === 'project') {
                                                    switch($document_type) {
                                                        case 'ws':
                                                            echo '<span class="badge bg-success">WS</span>';
                                                            break;
                                                        case 'pr_po':
                                                            echo '<span class="badge bg-danger">PR + PO</span>';
                                                            break;
                                                        case 'po_ws':
                                                            echo '<span class="badge bg-warning">PR + PO + WS</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="badge bg-secondary">' . htmlspecialchars($document_type ?? '') . '</span>';
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-primary">Stock PR</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        
                                        <?php if ($document_type !== 'ws'): ?>
                                        <tr>
                                            <th width="40%">PR Number:</th>
                                            <td><strong><?php echo htmlspecialchars($pr['pr_number'] ?? ''); ?></strong>
                                                <span class="<?php echo getStatusBadge($pr['status'] ?? 'pending'); ?>">
                                                    <?php echo ucfirst($pr['status'] ?? 'pending'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <!-- FIX: Always show PO Number for po_ws document type, even if not created -->
                                        <?php if ($document_type === 'po_ws' || $document_type === 'pr_po'): ?>
                                        <tr>
                                            <th width="40%">PO Number:</th>
                                            <td>
                                                <?php if (!empty($existing_pos)): ?>
                                                    <strong><?php echo htmlspecialchars($existing_pos[0]['po_number'] ?? ''); ?></strong>
                                                    <span class="badge
                                                        <?php 
                                                        switch(strtolower($po_status ?? 'not created')) {
                                                            case 'confirmed':
                                                            case 'delivered':
                                                                echo 'bg-success';
                                                                break;
                                                            case 'draft':
                                                            case 'pending':
                                                                echo 'bg-warning';
                                                                break;
                                                            case 'processing':
                                                                echo 'bg-info';
                                                                break;
                                                            case 'approved':
                                                                echo 'bg-success';
                                                                break;
                                                            case 'partially_received':
                                                                echo 'bg-warning';
                                                                break;
                                                            case 'cancelled':
                                                            case 'rejected':
                                                                echo 'bg-danger';
                                                                break;
                                                            case 'not created':
                                                                echo 'bg-info';
                                                                break;
                                                            default:
                                                                echo 'bg-secondary';
                                                        }
                                                        ?>">
                                                        <?php echo $po_status ?? 'Not Created'; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Not yet created</span>
                                                    <span class="badge bg-info">Not Created</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php elseif (($document_type === 'pr_po') && !empty($existing_pos)): ?>
                                        <tr>
                                            <th width="40%">PO Number:</th>
                                            <td>
                                                <strong><?php echo htmlspecialchars($existing_pos[0]['po_number'] ?? ''); ?></strong>
                                                <span class="badge
                                                    <?php 
                                                    switch(strtolower($po_status ?? 'not created')) {
                                                        case 'confirmed':
                                                        case 'delivered':
                                                            echo 'bg-success';
                                                            break;
                                                        case 'draft':
                                                        case 'pending':
                                                            echo 'bg-warning';
                                                            break;
                                                        case 'processing':
                                                            echo 'bg-info';
                                                            break;
                                                        case 'approved':
                                                            echo 'bg-success';
                                                            break;
                                                        case 'partially_received':
                                                            echo 'bg-warning';
                                                            break;
                                                        case 'cancelled':
                                                        case 'rejected':
                                                            echo 'bg-danger';
                                                            break;
                                                        case 'not created':
                                                            echo 'bg-info';
                                                            break;
                                                        default:
                                                            echo 'bg-secondary';
                                                    }
                                                    ?>">
                                                    <?php echo $po_status ?? 'Not Created'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <!-- FIX: Always show WS Number for po_ws document type, even if not created -->
                                        <?php if ($document_type === 'po_ws' || $document_type === 'ws'): ?>
                                        <tr>
                                            <th width="40%">WS Number:</th>
                                            <td>
                                                <?php if (!empty($existing_ws)): ?>
                                                    <strong><?php echo htmlspecialchars($existing_ws[0]['ws_number'] ?? ''); ?></strong>
                                                    <span class="badge
                                                        <?php 
                                                        switch(strtolower($ws_status ?? 'not created')) {
                                                            case 'confirmed':
                                                            case 'released':
                                                                echo 'bg-success';
                                                                break;
                                                            case 'approved':
                                                                echo 'bg-success';
                                                                break;
                                                            case 'processing':
                                                                echo 'bg-info';
                                                                break;
                                                            case 'pending':
                                                                echo 'bg-warning';
                                                                break;
                                                            case 'draft':
                                                                echo 'bg-secondary';
                                                                break;
                                                            case 'cancelled':
                                                            case 'rejected':
                                                                echo 'bg-danger';
                                                                break;
                                                            case 'not created':
                                                                echo 'bg-info';
                                                                break;
                                                            default:
                                                                echo 'bg-secondary';
                                                        }
                                                        ?>">
                                                        <?php echo $ws_status ?? 'Not Created'; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Not yet created</span>
                                                    <span class="badge bg-info">Not Created</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="40%">Request Date:</th>
                                            <td><?php echo formatDateMDY($pr['request_date'] ?? null); ?></td>
                                        </tr>
                                        
                                        <tr>
                                            <th>Requested By:</th>
                                            <td><?php echo formatUserName($pr); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Department:</th>
                                            <td><?php echo htmlspecialchars($pr['department'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <?php if ($request_type === 'project'): ?>
                                        <tr>
                                            <th>Project:</th>
                                            <td><?php echo htmlspecialchars($pr['project_name'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <?php else: ?>
                                        <tr>
                                            <th>Supplier:</th>
                                            <td><?php echo htmlspecialchars($pr['supplier_name'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <?php if ($request_type === 'project'): ?>
                                        <tr>
                                            <th>Threshold Amount:</th>
                                            <td>
                                                ₱<?php echo number_format($threshold_amount, 2); ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>

                            <!-- Show final status if PR is completed or rejected -->
                            <?php if (in_array($current_stage['stage'] ?? '', ['completed', 'rejected'])): ?>
                            <div class="final-status <?php echo $current_stage['stage'] ?? ''; ?>">
                                <h5 class="mb-2">
                                    <i class="fas <?php echo ($current_stage['stage'] ?? '') === 'completed' ? 'fa-check-circle' : 'fa-times-circle'; ?> me-2"></i>
                                    <?php 
                                    if (($current_stage['stage'] ?? '') === 'completed'): 
                                        if ($document_type === 'ws'):
                                            echo 'WITHDRAWAL SLIP COMPLETED';
                                        elseif ($document_type === 'po_ws'):
                                            echo 'PR COMPLETED';
                                        else:
                                            echo 'PR COMPLETED';
                                        endif;
                                    else: 
                                        echo 'PR REJECTED';
                                    endif; 
                                    ?>
                                </h5>
                                <p class="mb-0">
                                    <?php if (($current_stage['stage'] ?? '') === 'completed'): ?>
                                        <?php if ($document_type === 'ws'): ?>
                                            This withdrawal slip has been successfully processed and completed.
                                        <?php elseif ($document_type === 'po_ws'): ?>
                                            This purchase request has been successfully processed and completed.
                                        <?php else: ?>
                                            This purchase request has been successfully processed and completed.
                                        <?php endif; ?>
                                    <?php else: ?>
                                        This purchase request has been rejected during the routing process.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Horizontal Routing Progress -->
                            <div class="mt-4">
                                <h6 class="mb-3"><i class="fas fa-project-diagram me-1"></i> Routing Progress</h6>
                                <div class="scroll-container">
                                    <div class="horizontal-timeline">
                                        <?php
                                        // Define stages based on document_type
                                        if ($document_type === 'ws') {
                                            // Pure Withdrawal Slip flow
                                            $stages = [
                                                'requestor' => ['label' => 'Requestor', 'icon' => 'fa-user'],
                                                'warehouse' => ['label' => 'Warehouse', 'icon' => 'fa-warehouse'],
                                                'purchasing' => ['label' => 'Purchasing', 'icon' => 'fa-shopping-cart'],
                                                'approver' => ['label' => 'Approver (CEO)', 'icon' => 'fa-user-check'],
                                                'warehouse_releasing' => ['label' => 'Warehouse Releasing', 'icon' => 'fa-truck-loading'],
                                                'completed' => ['label' => 'Completed', 'icon' => 'fa-check-circle']
                                            ];
                                        } elseif ($document_type === 'pr_po') {
                                            // Pure Purchase Order flow
                                            $stages = [
                                                'requestor' => ['label' => 'Requestor', 'icon' => 'fa-user'],
                                                'warehouse' => ['label' => 'Warehouse', 'icon' => 'fa-warehouse'],
                                                'purchasing' => ['label' => 'Purchasing', 'icon' => 'fa-shopping-cart'],
                                                'accounting' => ['label' => 'Accounting', 'icon' => 'fa-calculator'],
                                                'approver' => ['label' => 'Approver (CEO)', 'icon' => 'fa-user-check'],
                                                'purchasing_final' => ['label' => 'Purchasing Final', 'icon' => 'fa-shopping-cart'],
                                                'warehouse_receiving' => ['label' => 'Warehouse Receiving', 'icon' => 'fa-truck-loading'],
                                                'purchasing_completion' => ['label' => 'Purchasing Completion', 'icon' => 'fa-clipboard-check'],
                                                'accounting_final' => ['label' => 'Accounting Final', 'icon' => 'fa-calculator'],
                                                'completed' => ['label' => 'Completed', 'icon' => 'fa-check-circle']
                                            ];
                                        } elseif ($document_type === 'po_ws') {
                                            // Mixed PO+WS flow
                                            $stages = [
                                                'requestor' => ['label' => 'Requestor', 'icon' => 'fa-user'],
                                                'warehouse' => ['label' => 'Warehouse', 'icon' => 'fa-warehouse'],
                                                'purchasing' => ['label' => 'Purchasing', 'icon' => 'fa-shopping-cart'],
                                                'accounting' => ['label' => 'Accounting', 'icon' => 'fa-calculator'],
                                                'approver' => ['label' => 'Approver (CEO)', 'icon' => 'fa-user-check'],
                                                'warehouse_releasing' => ['label' => 'Warehouse Releasing', 'icon' => 'fa-truck-loading'],
                                                'purchasing_final' => ['label' => 'Purchasing Final', 'icon' => 'fa-shopping-cart'],
                                                'warehouse_receiving' => ['label' => 'Warehouse Receiving', 'icon' => 'fa-truck-loading'],
                                                'purchasing_completion' => ['label' => 'Purchasing Completion', 'icon' => 'fa-clipboard-check'],
                                                'accounting_final' => ['label' => 'Accounting Final', 'icon' => 'fa-calculator'],
                                                'completed' => ['label' => 'Completed', 'icon' => 'fa-check-circle']
                                            ];
                                        } else {
                                            // Supplier request
                                            $stages = [
                                                'requestor' => ['label' => 'Requestor', 'icon' => 'fa-user'],
                                                'warehouse' => ['label' => 'Warehouse', 'icon' => 'fa-warehouse'],
                                                'purchasing' => ['label' => 'Purchasing', 'icon' => 'fa-shopping-cart'],
                                                'accounting' => ['label' => 'Accounting', 'icon' => 'fa-calculator'],
                                                'approver' => ['label' => 'Approver (CEO)', 'icon' => 'fa-user-check'],
                                                'purchasing_final' => ['label' => 'Purchasing Final', 'icon' => 'fa-shopping-cart'],
                                                'warehouse_receiving' => ['label' => 'Warehouse Receiving', 'icon' => 'fa-truck-loading'],
                                                'purchasing_completion' => ['label' => 'Purchasing Completion', 'icon' => 'fa-clipboard-check'],
                                                'accounting_final' => ['label' => 'Accounting Final', 'icon' => 'fa-calculator'],
                                                'completed' => ['label' => 'Completed', 'icon' => 'fa-check-circle']
                                            ];
                                        }
                                        
                                        // Get all completed stages from routing history
                                        $completed_stages = [];
                                        foreach ($routing_history as $history) {
                                            if (in_array($history['action'] ?? '', [
                                                'Forwarded to Warehouse', 
                                                'Approved by Warehouse', 
                                                'Approved by Purchasing', 
                                                'Approved by Accounting', 
                                                'Approved by Approver (CEO)', 
                                                'Approved by Purchasing (Final)',
                                                'Purchase Order Created',
                                                'Withdrawal Slip Created',
                                                'Items Received',
                                                'Withdrawal Slip Processed',
                                                'Completed by Purchasing',
                                                'Completed Warehouse Receiving',
                                                'Completed Warehouse Releasing',
                                                'Finalized by Accounting'
                                            ])) {
                                                $completed_stages[] = $history['stage_to'] ?? '';
                                            }
                                            
                                            // Special case: if an action was taken from a stage, that stage is considered visited
                                            if (!empty($history['stage_from']) && !in_array($history['stage_from'], $completed_stages)) {
                                                $completed_stages[] = $history['stage_from'];
                                            }
                                        }
                                        
                                        // Special case for requestor
                                        if (($current_stage['stage'] ?? 'requestor') !== 'requestor') {
                                            $completed_stages[] = 'requestor';
                                        }
                                        
                                        // Display all stages horizontally
                                        foreach ($stages as $stage => $stageInfo):
                                            $isCurrent = ($current_stage['stage'] ?? '') === $stage;
                                            $isCompleted = in_array($stage, $completed_stages);
                                            
                                            $stageClass = 'pending';
                                            if ($isCurrent && !in_array($current_stage['stage'] ?? '', ['completed', 'rejected'])) {
                                                $stageClass = 'current';
                                            } elseif ($isCompleted) {
                                                $stageClass = 'completed';
                                            }
                                        ?>
                                        <div class="timeline-stage <?php echo $stageClass; ?>">
                                            <div class="timeline-icon">
                                                <i class="fas <?php echo $stageInfo['icon']; ?>"></i>
                                            </div>
                                            <div class="timeline-label"><?php echo $stageInfo['label']; ?></div>
                                            <div class="timeline-status">
                                                <?php if ($isCurrent && !in_array($current_stage['stage'] ?? '', ['completed', 'rejected'])): ?>
                                                    Current
                                                <?php elseif ($isCompleted): ?>
                                                    Completed
                                                <?php else: ?>
                                                    Pending
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Purchase Orders - Only show if document_type allows PO -->
                    <?php if (!empty($existing_pos) && $document_type !== 'ws'): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-file-invoice-dollar me-1"></i>
                            Existing Purchase Orders
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>PO Number</th>
                                            <th>Date Created</th>
                                            <th>Expected Delivery</th>
                                            <!-- REMOVED: Total Amount column -->
                                            <th>Status</th>
                                            <th>Items</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($existing_pos as $po): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($po['po_number'] ?? ''); ?></strong></td>
                                            <td><?php echo formatDateMDY($po['po_date'] ?? null); ?></td>
                                            <td><?php echo isset($po['expected_delivery']) ? formatDateMDY($po['expected_delivery']) : 'Not set'; ?></td>
                                            <!-- REMOVED: Total Amount data cell -->
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch($po['status'] ?? '') {
                                                        case 'draft': echo 'bg-secondary'; break;
                                                        case 'sent': echo 'bg-info'; break;
                                                        case 'pending': echo 'bg-warning'; break;
                                                        case 'processing': echo 'bg-info'; break;
                                                        case 'approved': echo 'bg-success'; break;
                                                        case 'confirmed': echo 'bg-success'; break;
                                                        case 'delivered': echo 'bg-success'; break;
                                                        case 'partially_received': echo 'bg-warning'; break;
                                                        case 'cancelled': echo 'bg-danger'; break;
                                                        default: echo 'bg-secondary';
                                                    }
                                                    ?>
                                                ">
                                                    <?php echo ucfirst($po['status'] ?? 'draft'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $po['item_count'] ?? 0; ?> item/s</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary view-po-btn" 
                                                        data-po-id="<?php echo $po['id'] ?? 0; ?>"
                                                        data-po-number="<?php echo htmlspecialchars($po['po_number'] ?? ''); ?>"
                                                        data-po-date="<?php echo htmlspecialchars($po['po_date'] ?? ''); ?>"
                                                        data-expected-delivery="<?php echo htmlspecialchars($po['expected_delivery'] ?? 'Not set'); ?>"
                                                        data-po-status="<?php echo ucfirst($po['status'] ?? 'draft'); ?>"
                                                        data-po-remarks="<?php echo htmlspecialchars($po['remarks'] ?? ''); ?>">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Existing Withdrawal Slips - Only show if document_type allows WS -->
                    <?php if (!empty($existing_ws) && ($document_type === 'ws' || $document_type === 'po_ws')): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-file-invoice me-1"></i>
                            Existing Withdrawal Slips
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>WS Number</th>
                                            <th>Date Created</th>
                                            <th>Warehouse</th>
                                            <th>Status</th>
                                            <th>Items</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($existing_ws as $ws): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($ws['ws_number'] ?? ''); ?></strong></td>
                                            <td><?php echo formatDateMDY($ws['ws_date'] ?? null); ?></td>
                                            <td>
                                                <?php 
                                                $warehouse_name = 'N/A';
                                                if (!empty($ws['warehouse_id'])) {
                                                    $warehouseStmt = $pdo->prepare("SELECT warehouse_name FROM warehouses WHERE id = ?");
                                                    $warehouseStmt->execute([$ws['warehouse_id']]);
                                                    $warehouse = $warehouseStmt->fetch(PDO::FETCH_ASSOC);
                                                    $warehouse_name = $warehouse['warehouse_name'] ?? 'N/A';
                                                }
                                                echo htmlspecialchars($warehouse_name);
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch($ws['status'] ?? '') {
                                                        case 'draft': echo 'bg-secondary'; break;
                                                        case 'pending': echo 'bg-warning'; break;
                                                        case 'processing': echo 'bg-info'; break;
                                                        case 'approved': echo 'bg-success'; break;
                                                        case 'confirmed': echo 'bg-success'; break;
                                                        case 'released': echo 'bg-success'; break;
                                                        case 'cancelled': echo 'bg-danger'; break;
                                                        default: echo 'bg-secondary';
                                                    }
                                                    ?>
                                                ">
                                                    <?php echo ucfirst($ws['status'] ?? 'draft'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $ws['item_count'] ?? 0; ?> item/s</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary view-ws-btn" 
                                                        data-ws-id="<?php echo $ws['id'] ?? 0; ?>"
                                                        data-ws-number="<?php echo htmlspecialchars($ws['ws_number'] ?? ''); ?>"
                                                        data-ws-date="<?php echo htmlspecialchars($ws['ws_date'] ?? ''); ?>"
                                                        data-warehouse="<?php echo htmlspecialchars($warehouse_name); ?>"
                                                        data-ws-status="<?php echo ucfirst($ws['status'] ?? 'draft'); ?>"
                                                        data-ws-remarks="<?php echo htmlspecialchars($ws['remarks'] ?? ''); ?>">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </button>
                                                
                                                <?php if (($current_stage['stage'] ?? '') === 'warehouse_releasing' && 
                                                        $user['department'] === 'Warehouse' && 
                                                        $user['accounttype'] === 'Admin' &&
                                                        ($ws['status'] ?? '') === 'approved' &&
                                                        !$ws_already_processed): ?>
                                                <button type="button" class="btn btn-sm btn-success process-ws-btn" 
                                                        data-ws-id="<?php echo $ws['id'] ?? 0; ?>"
                                                        data-ws-number="<?php echo htmlspecialchars($ws['ws_number'] ?? ''); ?>">
                                                    <i class="fas fa-truck-loading me-1"></i> Release Items
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Warehouse Releasing Items (Withdrawal Slip) - Only show for WS or PO_WS -->
                    <?php if (($current_stage['stage'] ?? '') === 'warehouse_releasing' && !empty($ws_items_for_releasing) && ($document_type === 'ws' || $document_type === 'po_ws')): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-truck-loading me-1"></i>
                            Items to Release (Withdrawal Slip)
                        </div>
                        <div class="card-body">
                            <?php if ($ws_already_processed): ?>
                            <div class="received-alert">
                                <h6><i class="fas fa-check-circle me-2"></i>Withdrawal Slip Already Processed</h6>
                                <p class="mb-0">The items for this withdrawal slip have already been released.</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($is_warehouse_user): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Warehouse</th>
                                            <th>Quantity to Release</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ws_items_for_releasing as $ws_item): ?>
                                        <tr>
                                            <td><?php echo formatItemNameWithCode($ws_item['item_name'] ?? '', $ws_item['item_code'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($ws_item['warehouse_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $ws_item['quantity'] ?? 0; ?></td>
                                            <td>
                                                <span class="badge <?php echo ($ws_item['status'] ?? '') === 'released' ? 'bg-success' : ($ws_approved ? 'bg-primary' : 'bg-secondary'); ?>">
                                                    <?php 
                                                    if (($ws_item['status'] ?? '') === 'released') {
                                                        echo 'Released';
                                                    } elseif ($ws_approved) {
                                                        echo 'Approved';
                                                    } else {
                                                        echo 'Pending Approval';
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <?php if (!$ws_already_processed): ?>
                                    <?php if ($ws_approved): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-check-circle me-1"></i>
                                        <strong>Action Required:</strong> Withdrawal slip has been approved. Click the "Release Items" button in the Withdrawal Slips section above to release items from inventory.
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Withdrawal slip is pending approval from Approver (CEO). You cannot release items until the withdrawal slip has been approved.
                                    </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-1"></i>
                                    Items have been released. Click "Complete Warehouse Releasing" in the Routing Actions modal to finish this stage.
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Warehouse</th>
                                            <th>Quantity to Release</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ws_items_for_releasing as $ws_item): ?>
                                        <tr>
                                            <td><?php echo formatItemNameWithCode($ws_item['item_name'] ?? '', $ws_item['item_code'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($ws_item['warehouse_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $ws_item['quantity'] ?? 0; ?></td>
                                            <td>
                                                <span class="badge <?php echo ($ws_item['status'] ?? '') === 'released' ? 'bg-success' : ($ws_approved ? 'bg-primary' : 'bg-secondary'); ?>">
                                                    <?php 
                                                    if (($ws_item['status'] ?? '') === 'released') {
                                                        echo 'Released';
                                                    } elseif ($ws_approved) {
                                                        echo 'Approved';
                                                    } else {
                                                        echo 'Pending Approval';
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Only Warehouse Department users can release items. You are viewing this information in read-only mode.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Warehouse Receiving Items - Only show if document_type allows PO -->
                    <?php if (($current_stage['stage'] ?? '') === 'warehouse_receiving' && !empty($po_items_for_receiving) && $document_type !== 'ws'): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-truck-loading me-1"></i>
                            Items to Receive (Purchase Order)
                        </div>
                        <div class="card-body">
                            <?php if ($items_already_received): ?>
                            <div class="received-alert">
                                <h6><i class="fas fa-check-circle me-2"></i>Items Already Received</h6>
                                <p class="mb-0">The items for this purchase order have already been received.</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($is_warehouse_user): ?>
                            <form method="POST" action="" id="receivingForm">
                                <input type="hidden" name="action" value="receive_items">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Item Name</th>
                                                <th>Warehouse</th>
                                                <th>Quantity Ordered</th>
                                                <th>Quantity Received</th>
                                                <th>Remaining</th>
                                                <th>Unit Cost</th>
                                                <th>Total Cost</th>
                                                <th>Status</th>
                                                <th>Quantity to Receive</th>
                                                <th class="batch-column">Batch Number</th>
                                                <th>Received Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($po_items_for_receiving as $po_item): 
                                                $received_quantity = $po_item['received_quantity'] ?? 0;
                                                $remaining_quantity = ($po_item['quantity'] ?? 0) - $received_quantity;
                                                $status = $po_item['status'] ?? 'pending';
                                                
                                                $status_class = 'status-pending';
                                                $status_text = 'Pending';
                                                if ($status === 'partially_received') {
                                                    $status_class = 'status-partial';
                                                    $status_text = 'Partially Received';
                                                } elseif ($status === 'delivered') {
                                                    $status_class = 'status-delivered';
                                                    $status_text = 'Delivered';
                                                }
                                                
                                                // Calculate total cost based on ordered quantity for display purposes
                                                $total_cost_display = ($po_item['quantity'] ?? 0) * ($po_item['unit_cost'] ?? 0);
                                            ?>
                                            <tr>
                                                <td><?php echo formatItemNameWithCode($po_item['item_name'] ?? '', $po_item['item_code'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($po_item['warehouse_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo $po_item['quantity'] ?? 0; ?></td>
                                                <td><?php echo $received_quantity; ?></td>
                                                <td><?php echo $remaining_quantity; ?></td>
                                                <td>₱<?php echo number_format($po_item['unit_cost'] ?? 0, 2); ?></td>
                                                <td>₱<?php echo number_format($total_cost_display, 2); ?></td> <!-- FIXED: Now displays correct amount -->
                                                <td>
                                                    <span class="receiving-status <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($remaining_quantity > 0): ?>
                                                        <input type="number" 
                                                            name="received_items[<?php echo $po_item['id'] ?? 0; ?>][quantity]" 
                                                            value="<?php echo $remaining_quantity; ?>"
                                                            min="0" max="<?php echo $remaining_quantity; ?>"
                                                            class="form-control form-control-sm" 
                                                            <?php echo $items_already_received ? 'readonly' : 'required'; ?>>
                                                    <?php else: ?>
                                                        <span class="text-success">Fully Received</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="batch-column">
                                                    <?php if ($remaining_quantity > 0): ?>
                                                        <input type="text" 
                                                            name="received_items[<?php echo $po_item['id'] ?? 0; ?>][batch_number]" 
                                                            value="BATCH-<?php echo date('Ymd-His'); ?>-<?php echo $po_item['id'] ?? 0; ?>"
                                                            class="form-control form-control-sm" 
                                                            <?php echo $items_already_received ? 'readonly' : 'required'; ?>>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($remaining_quantity > 0): ?>
                                                        <input type="date" 
                                                            name="received_items[<?php echo $po_item['id'] ?? 0; ?>][received_date]" 
                                                            value="<?php echo date('Y-m-d'); ?>"
                                                            class="form-control form-control-sm" 
                                                            <?php echo $items_already_received ? 'readonly' : 'required'; ?>>
                                                        <input type="hidden" 
                                                            name="received_items[<?php echo $po_item['id'] ?? 0; ?>][unit_cost]" 
                                                            value="<?php echo $po_item['unit_cost'] ?? 0; ?>">
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" <?php echo $items_already_received ? 'readonly' : 'required'; ?>></textarea>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-success" <?php echo $items_already_received ? 'disabled' : ''; ?>>
                                        <i class="fas fa-check me-1"></i> Confirm Items Received
                                    </button>
                                </div>
                            </form>
                            <?php else: ?>
                            <!-- Read-only view for non-warehouse users -->
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Warehouse</th>
                                            <th>Quantity Ordered</th>
                                            <th>Quantity Received</th>
                                            <th>Remaining</th>
                                            <th>Unit Cost</th>
                                            <th>Total Cost</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($po_items_for_receiving as $po_item): 
                                            $received_quantity = $po_item['received_quantity'] ?? 0;
                                            $remaining_quantity = ($po_item['quantity'] ?? 0) - $received_quantity;
                                            $status = $po_item['status'] ?? 'pending';
                                            
                                            $status_class = 'status-pending';
                                            $status_text = 'Pending';
                                            if ($status === 'partially_received') {
                                                $status_class = 'status-partial';
                                                $status_text = 'Partially Received';
                                            } elseif ($status === 'delivered') {
                                                $status_class = 'status-delivered';
                                                $status_text = 'Delivered';
                                            }
                                            
                                            // Calculate total cost based on ordered quantity for display purposes
                                            $total_cost_display = ($po_item['quantity'] ?? 0) * ($po_item['unit_cost'] ?? 0);
                                        ?>
                                        <tr>
                                            <td><?php echo formatItemNameWithCode($po_item['item_name'] ?? '', $po_item['item_code'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($po_item['warehouse_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $po_item['quantity'] ?? 0; ?></td>
                                            <td><?php echo $received_quantity; ?></td>
                                            <td><?php echo $remaining_quantity; ?></td>
                                            <td>₱<?php echo number_format($po_item['unit_cost'] ?? 0, 2); ?></td>
                                            <td>₱<?php echo number_format($total_cost_display, 2); ?></td> <!-- FIXED: Now displays correct amount -->
                                            <td>
                                                <span class="receiving-status <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Only Warehouse Department users can receive items. You are viewing this information in read-only mode.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Items Requested -->
                        <div class="col-lg-12">
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-list me-1"></i>
                                        <?php if ($document_type === 'ws'): ?>
                                            Withdrawal Slip Items
                                        <?php else: ?>
                                            Items Requested
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if (($current_stage['stage'] ?? '') === 'approver' && 
                                                $user['department'] === 'Admin' && 
                                                $user['position'] === 'CEO' && 
                                                $user['accounttype'] === 'Admin' && 
                                                $request_type === 'project' &&
                                                $document_type !== 'ws'): ?>
                                        <button type="button" class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#adjustThresholdModal">
                                            <i class="fas fa-balance-scale me-1"></i> Adjust Threshold
                                        </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#routingActionsModal">
                                            <i class="fas fa-route me-1"></i> Routing Actions
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Item Name</th>
                                                    <?php if ($document_type === 'ws'): ?>
                                                        <!-- WS Document Type - Simplified Headers -->
                                                        <th>Quantity Requested</th>
                                                        <th>Warehouse</th>
                                                        <th>Current Stock</th>
                                                        <th>Released</th>
                                                        <th>Remaining Needed</th>
                                                        <th>Unit Cost</th>
                                                        <th>Total Cost</th>
                                                        <th>Status</th>
                                                        <th>Release Status</th>
                                                    <?php elseif ($request_type === 'project' && $document_type === 'pr_po'): ?>
                                                        <!-- PR_PO Document Type (Pure Purchase Order) - FIXED -->
                                                        <th>Quantity Requested</th>
                                                        <th>Quantity Received</th>
                                                        <th>Current Stock</th>
                                                        <th>Unit Cost</th>
                                                        <th>Total Cost</th>
                                                        <th>Status</th>
                                                        <th>Delivery Status</th>
                                                        <th>Received Date</th>
                                                    <?php elseif ($request_type === 'project' && $document_type === 'po_ws'): ?>
                                                        <!-- PO_WS Document Type (Mixed) -->
                                                        <th>Quantity Requested</th>
                                                        <th>Current Stock</th>
                                                        <th>Released</th>
                                                        <th>Received</th>
                                                        <th>Remaining Needed/Ordered</th>
                                                        <th>Unit Cost</th>
                                                        <th>Total Cost</th>
                                                        <th>Status</th>
                                                        <th>Release Status</th>
                                                        <th>Delivery Status</th>
                                                    <?php else: ?>
                                                        <!-- Stock PR -->
                                                        <th>Quantity Ordered</th>
                                                        <th>Quantity Received</th>
                                                        <th>Unit Cost</th>
                                                        <th>Total Cost</th>
                                                        <th>Delivery Status</th>
                                                        <th>Received Date</th>
                                                    <?php endif; ?>
                                                    <th class="hidden-actions">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                foreach ($items as $item): 
                                                    if ($request_type === 'supplier') {
                                                        $delivered_qty = $item['supplier_received_quantity'] ?? 0;
                                                    } else {
                                                        $delivered_qty = $item['db_delivered_quantity'] ?? 0;
                                                    }
                                                    
                                                    // Calculate remaining quantity needed
                                                    $remaining_needed = $item['quantity'] - $delivered_qty;
                                                    
                                                    $current_stock = $item['current_stock'] ?? 0;
                                                    $unit_cost = $item['unit_cost'] ?? 0;
                                                    
                                                    // FIX: Calculate total cost based on remaining needed quantity
                                                    // If fully delivered (remaining_needed = 0), total cost should be 0.00
                                                    $total_cost = ($remaining_needed > 0) ? ($remaining_needed * $unit_cost) : 0;
                                                    
                                                    // Determine status flags
                                                    $is_delivered = $delivered_qty >= $item['quantity'];
                                                    $is_partially_delivered = $delivered_qty > 0 && $delivered_qty < $item['quantity'];
                                                    
                                                    // Get received date for project PR_PO items
                                                    $po_received_date = '-';
                                                    if ($request_type === 'project' && $document_type === 'pr_po' && $delivered_qty > 0) {
                                                        $poReceivedDateStmt = $pdo->prepare("
                                                            SELECT MAX(received_date) as last_received 
                                                            FROM po_items 
                                                            WHERE pr_item_id = ? AND received_quantity > 0
                                                        ");
                                                        $poReceivedDateStmt->execute([$item['id']]);
                                                        $po_received_data = $poReceivedDateStmt->fetch(PDO::FETCH_ASSOC);
                                                        if ($po_received_data && $po_received_data['last_received']) {
                                                            $po_received_date = formatDateMDY($po_received_data['last_received']);
                                                        }
                                                    }
                                                    
                                                    // Status class for stock
                                                    if ($request_type === 'project') {
                                                        if ($current_stock >= $remaining_needed && $remaining_needed > 0) {
                                                            $status_class = 'stock-available';
                                                            $status_text = 'Available';
                                                        } elseif ($current_stock > 0 && $remaining_needed > 0) {
                                                            $status_class = 'stock-low';
                                                            $status_text = 'Low Stock';
                                                        } elseif ($remaining_needed > 0) {
                                                            $status_class = 'stock-out';
                                                            $status_text = 'Out of Stock';
                                                        } else {
                                                            $status_class = 'stock-available';
                                                            $status_text = 'Fully Released';
                                                        }
                                                    } else {
                                                        if ($remaining_needed > 0) {
                                                            $status_class = 'stock-available';
                                                            $status_text = 'Available';
                                                        } else {
                                                            $status_class = 'stock-available';
                                                            $status_text = 'Fully Received';
                                                        }
                                                    }
                                                    
                                                    // Delivery status
                                                    if ($request_type === 'project') {
                                                        if ($is_delivered) {
                                                            $delivery_status_class = 'delivered';
                                                            $delivery_status_text = 'Released';
                                                            $delivery_status_icon = 'fa-check-circle';
                                                        } elseif ($is_partially_delivered) {
                                                            $delivery_status_class = 'partially-delivered';
                                                            $delivery_status_text = 'Partially Released';
                                                            $delivery_status_icon = 'fa-clock';
                                                        } else {
                                                            $delivery_status_class = 'text-warning';
                                                            $delivery_status_text = 'Pending';
                                                            $delivery_status_icon = 'fa-clock';
                                                        }
                                                    } else {
                                                        // Stock PR delivery status
                                                        if (($pr['status'] ?? '') === 'rejected') {
                                                            $delivery_status_class = 'text-danger';
                                                            $delivery_status_text = 'Rejected';
                                                            $delivery_status_icon = 'fa-times-circle';
                                                        } elseif ($is_delivered) {
                                                            $delivery_status_class = 'delivered';
                                                            $delivery_status_text = 'Fully Received';
                                                            $delivery_status_icon = 'fa-check-circle';
                                                        } elseif ($is_partially_delivered) {
                                                            $delivery_status_class = 'partially-delivered';
                                                            $delivery_status_text = 'Partially Received';
                                                            $delivery_status_icon = 'fa-clock';
                                                        } else {
                                                            $delivery_status_class = 'text-warning';
                                                            $delivery_status_text = 'Pending';
                                                            $delivery_status_icon = 'fa-clock';
                                                        }
                                                    }

                                                    // Get received date from stock movements (for Stock PR)
                                                    $received_date = '-';
                                                    if ($request_type !== 'project' && $delivered_qty > 0) {
                                                        $receivedDateStmt = $pdo->prepare("
                                                            SELECT MAX(movement_date) as last_received 
                                                            FROM stock_movements 
                                                            WHERE item_id = ? AND purchase_request = ? AND movement_type = 'in'
                                                        ");
                                                        $receivedDateStmt->execute([$item['item_id'], $pr['pr_number']]);
                                                        $received_data = $receivedDateStmt->fetch(PDO::FETCH_ASSOC);
                                                        if ($received_data && $received_data['last_received']) {
                                                            $received_date = formatDateMDY($received_data['last_received']);
                                                        }
                                                    }
                                                ?>
                                                <tr>
                                                    <td><?php echo formatItemNameWithCode($item['item_name'] ?? '', $item['item_code'] ?? ''); ?></td>
                                                    
                                                    <?php if ($document_type === 'ws'): ?>
                                                        <!-- WS Document Type -->
                                                        <?php
                                                        // Calculate FIFO-based cost for display purposes
                                                        $quantity_to_withdraw = $remaining_needed;
                                                        $fifo_unit_cost = 0;
                                                        $fifo_total_cost = 0;
                                                        
                                                        if ($current_stock > 0 && $quantity_to_withdraw > 0) {
                                                            $fifoBatchesStmt = $pdo->prepare("
                                                                SELECT id, quantity, unit_cost, batch_number, received_date 
                                                                FROM inventory_batches 
                                                                WHERE item_id = :item_id AND warehouse_id = :warehouse_id AND quantity > 0 
                                                                ORDER BY received_date ASC, id ASC
                                                            ");
                                                            $fifoBatchesStmt->bindParam(':item_id', $item['item_id']);
                                                            $fifoBatchesStmt->bindParam(':warehouse_id', $item['warehouse_id']);
                                                            $fifoBatchesStmt->execute();
                                                            $fifo_batches = $fifoBatchesStmt->fetchAll(PDO::FETCH_ASSOC);
                                                            
                                                            $remaining_to_withdraw = $quantity_to_withdraw;
                                                            $total_cost_fifo = 0;
                                                            
                                                            foreach ($fifo_batches as $batch) {
                                                                if ($remaining_to_withdraw <= 0) break;
                                                                
                                                                $batch_qty = floatval($batch['quantity']);
                                                                $batch_cost = floatval($batch['unit_cost']);
                                                                
                                                                $take_qty = min($remaining_to_withdraw, $batch_qty);
                                                                $total_cost_fifo += $take_qty * $batch_cost;
                                                                
                                                                $remaining_to_withdraw -= $take_qty;
                                                            }
                                                            
                                                            if ($quantity_to_withdraw > 0) {
                                                                $fifo_unit_cost = $total_cost_fifo / $quantity_to_withdraw;
                                                                $fifo_total_cost = $total_cost_fifo;
                                                            }
                                                        }
                                                        
                                                        // If fully delivered, show 0.00
                                                        if ($quantity_to_withdraw <= 0) {
                                                            $fifo_unit_cost = 0;
                                                            $fifo_total_cost = 0;
                                                        } else if ($fifo_unit_cost == 0) {
                                                            $fifo_unit_cost = $unit_cost;
                                                            $fifo_total_cost = $unit_cost * $quantity_to_withdraw;
                                                        }
                                                        ?>
                                                        <td><?php echo htmlspecialchars($item['quantity'] ?? 0); ?></td>
                                                        <td><?php echo htmlspecialchars($item['warehouse_name'] ?? 'No Warehouse'); ?></td>
                                                        <td><?php echo htmlspecialchars($current_stock); ?></td>
                                                        <td><?php echo htmlspecialchars($delivered_qty); ?></td>
                                                        <td><?php echo htmlspecialchars($remaining_needed); ?></td>
                                                        <td>₱<?php echo number_format($fifo_unit_cost, 2); ?></td>
                                                        <td>₱<?php echo number_format($fifo_total_cost, 2); ?></td>
                                                        <td>
                                                            <span class="stock-status <?php echo $status_class; ?>">
                                                                <?php echo $status_text; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="<?php echo $delivery_status_class; ?>">
                                                                <i class="fas <?php echo $delivery_status_icon; ?> me-1"></i>
                                                                <?php echo $delivery_status_text; ?>
                                                            </span>
                                                        </td>
                                                        
                                                    <?php elseif ($request_type === 'project' && $document_type === 'pr_po'): ?>
                                                        <!-- PR_PO Document Type - FIXED: Use remaining needed for total cost -->
                                                        <td><?php echo htmlspecialchars($item['quantity'] ?? 0); ?></td>
                                                        <td><?php echo htmlspecialchars($delivered_qty); ?></td>
                                                        <td><?php echo htmlspecialchars($current_stock); ?></td>
                                                        <td>
                                                            <?php 
                                                            // If fully delivered, show 0.00
                                                            if ($remaining_needed <= 0) {
                                                                echo '₱0.00';
                                                            } else {
                                                                echo '₱' . number_format($unit_cost, 2);
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>₱<?php echo number_format($total_cost, 2); ?></td> <!-- FIXED: Now 0.00 when fully delivered -->
                                                        <td>
                                                            <?php 
                                                            if ($current_stock >= $remaining_needed && $remaining_needed > 0) {
                                                                echo '<span class="stock-status stock-available">Available</span>';
                                                            } elseif ($current_stock > 0 && $remaining_needed > 0) {
                                                                echo '<span class="stock-status stock-low">Low Stock</span>';
                                                            } elseif ($remaining_needed > 0) {
                                                                echo '<span class="stock-status stock-out">Out of Stock</span>';
                                                            } else {
                                                                echo '<span class="stock-status stock-available">Fully Received</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            if ($is_delivered) {
                                                                echo '<span class="delivered"><i class="fas fa-check-circle me-1"></i>Fully Received</span>';
                                                            } elseif ($is_partially_delivered) {
                                                                echo '<span class="partially-delivered"><i class="fas fa-clock me-1"></i>Partially Received (' . $delivered_qty . ' of ' . $item['quantity'] . ')</span>';
                                                            } elseif (($pr['status'] ?? '') === 'rejected') {
                                                                echo '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Rejected</span>';
                                                            } else {
                                                                echo '<span class="text-warning"><i class="fas fa-clock me-1"></i>Pending</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?php echo $po_received_date; ?></td>
                                                        
                                                    <?php elseif ($request_type === 'project' && $document_type === 'po_ws'): ?>
                                                        <!-- PO_WS Document Type (Mixed) - FIXED: Combined batches + PO cost for Partial Stock -->
                                                        <?php
                                                        $supplier_received = $item['supplier_received_quantity'] ?? 0;
                                                        $delivered_from_warehouse = $item['db_delivered_quantity'] ?? 0;
                                                        $total_fulfilled = $supplier_received + $delivered_from_warehouse;
                                                        $remaining_needed_po_ws = $item['quantity'] - $total_fulfilled;
                                                        
                                                        // Calculate the actual cost based on batches that will be used in FIFO order + PO cost
                                                        $actual_unit_cost = 0;
                                                        $actual_total_cost = 0;
                                                        
                                                        if ($remaining_needed_po_ws > 0) {
                                                            $total_cost_combined = 0;
                                                            $remaining_to_calculate = $remaining_needed_po_ws;
                                                            
                                                            // PART 1: Get cost from inventory batches (FIFO)
                                                            if ($current_stock > 0) {
                                                                $fifoBatchesStmt = $pdo->prepare("
                                                                    SELECT id, quantity, unit_cost, batch_number, received_date 
                                                                    FROM inventory_batches 
                                                                    WHERE item_id = :item_id AND warehouse_id = :warehouse_id AND quantity > 0 
                                                                    ORDER BY received_date ASC, id ASC
                                                                ");
                                                                $fifoBatchesStmt->bindParam(':item_id', $item['item_id']);
                                                                $fifoBatchesStmt->bindParam(':warehouse_id', $item['warehouse_id']);
                                                                $fifoBatchesStmt->execute();
                                                                $fifo_batches = $fifoBatchesStmt->fetchAll(PDO::FETCH_ASSOC);
                                                                
                                                                $batches_used = [];
                                                                
                                                                // Take from batches first (FIFO order)
                                                                foreach ($fifo_batches as $batch) {
                                                                    if ($remaining_to_calculate <= 0) break;
                                                                    
                                                                    $batch_qty = floatval($batch['quantity']);
                                                                    $batch_cost = floatval($batch['unit_cost']);
                                                                    
                                                                    $take_qty = min($remaining_to_calculate, $batch_qty);
                                                                    $total_cost_combined += $take_qty * $batch_cost;
                                                                    
                                                                    $remaining_to_calculate -= $take_qty;
                                                                }
                                                            }
                                                            
                                                            // PART 2: Remaining quantity will come from Purchase Order
                                                            if ($remaining_to_calculate > 0) {
                                                                // Use the unit cost from PR item for PO portion
                                                                $total_cost_combined += $remaining_to_calculate * $unit_cost;
                                                            }
                                                            
                                                            if ($remaining_needed_po_ws > 0) {
                                                                $actual_unit_cost = $total_cost_combined / $remaining_needed_po_ws;
                                                                $actual_total_cost = $total_cost_combined;
                                                            }
                                                        }
                                                        
                                                        // Determine release status
                                                        if ($delivered_from_warehouse >= $item['quantity']) {
                                                            $release_status_class = 'delivered';
                                                            $release_status_icon = 'fa-check-circle';
                                                            $release_status_text = 'Released';
                                                        } elseif ($delivered_from_warehouse > 0) {
                                                            $release_status_class = 'partially-delivered';
                                                            $release_status_icon = 'fa-clock';
                                                            $release_status_text = 'Partial Release';
                                                        } else {
                                                            $release_status_class = 'text-warning';
                                                            $release_status_icon = 'fa-clock';
                                                            $release_status_text = 'Pending';
                                                        }
                                                        
                                                        // Determine supplier delivery status
                                                        $quantity_to_purchase = $item['quantity'] - $delivered_from_warehouse; // This is what needs to be purchased
                                                        
                                                        if ($quantity_to_purchase <= 0) {
                                                            $supplier_status_class = 'text-muted';
                                                            $supplier_status_icon = 'fa-minus-circle';
                                                            $supplier_status_text = 'Not Required';
                                                        } elseif ($supplier_received >= $quantity_to_purchase) {
                                                            $supplier_status_class = 'delivered';
                                                            $supplier_status_icon = 'fa-check-circle';
                                                            $supplier_status_text = 'Received';
                                                        } elseif ($supplier_received > 0) {
                                                            $supplier_status_class = 'partially-delivered';
                                                            $supplier_status_icon = 'fa-clock';
                                                            $supplier_status_text = 'Partial';
                                                        } else {
                                                            $supplier_status_class = 'text-warning';
                                                            $supplier_status_icon = 'fa-clock';
                                                            $supplier_status_text = 'Pending';
                                                        }
                                                        
                                                        // Determine stock status text with proper class
                                                        if ($remaining_needed_po_ws <= 0) {
                                                            $status_class = 'stock-available';
                                                            $status_text = 'Fulfilled';
                                                        } elseif ($current_stock >= $remaining_needed_po_ws && $remaining_needed_po_ws > 0) {
                                                            $status_class = 'stock-available';
                                                            $status_text = 'In Stock';
                                                        } elseif ($current_stock > 0 && $remaining_needed_po_ws > 0) {
                                                            $status_class = 'stock-low';
                                                            $status_text = 'Partial Stock'; // Combined from batches + PO
                                                        } else {
                                                            $status_class = 'stock-out';
                                                            $status_text = 'Need Purchase';
                                                        }
                                                        ?>
                                                        <td><?php echo htmlspecialchars($item['quantity'] ?? 0); ?></td>
                                                        <td><?php echo htmlspecialchars($current_stock); ?></td>
                                                        <td><?php echo htmlspecialchars($delivered_from_warehouse); ?></td>
                                                        <td><?php echo htmlspecialchars($supplier_received); ?></td>
                                                        <td><?php echo $remaining_needed_po_ws; ?></td>
                                                        <td>₱<?php echo number_format($actual_unit_cost, 2); ?></td> <!-- FIXED: Combined batches + PO cost -->
                                                        <td>₱<?php echo number_format($actual_total_cost, 2); ?></td> <!-- FIXED: Combined batches + PO total -->
                                                        <td>
                                                            <span class="stock-status <?php echo $status_class; ?>">
                                                                <?php echo $status_text; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="<?php echo $release_status_class; ?>">
                                                                <i class="fas <?php echo $release_status_icon; ?> me-1"></i>
                                                                <?php echo $release_status_text; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="<?php echo $supplier_status_class; ?>">
                                                                <i class="fas <?php echo $supplier_status_icon; ?> me-1"></i>
                                                                <?php echo $supplier_status_text; ?>
                                                                <?php if ($supplier_status_text === 'Partial'): ?>
                                                                    (<?php echo $supplier_received; ?> of <?php echo $quantity_to_purchase; ?>)
                                                                <?php endif; ?>
                                                            </span>
                                                        </td>
                                                        
                                                    <?php else: ?>
                                                        <!-- Stock PR - FIXED: Use remaining needed for total cost -->
                                                        <td><?php echo htmlspecialchars($item['quantity'] ?? 0); ?></td>
                                                        <td><?php echo htmlspecialchars($delivered_qty); ?></td>
                                                        <td>
                                                            <?php 
                                                            // If fully delivered, show 0.00
                                                            if ($remaining_needed <= 0) {
                                                                echo '₱0.00';
                                                            } else {
                                                                echo '₱' . number_format($unit_cost, 2);
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>₱<?php echo number_format($total_cost, 2); ?></td> <!-- FIXED: Now 0.00 when fully delivered -->
                                                        <td>
                                                            <span class="<?php echo $delivery_status_class; ?>">
                                                                <i class="fas <?php echo $delivery_status_icon; ?> me-1"></i>
                                                                <?php echo $delivery_status_text; ?>
                                                                <?php if ($is_partially_delivered): ?>
                                                                    (<?php echo $delivered_qty; ?> of <?php echo $item['quantity']; ?>)
                                                                <?php endif; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $received_date; ?></td>
                                                    <?php endif; ?>
                                                    
                                                    <td class="hidden-actions">
                                                        <!-- Keep existing actions code -->
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Routing Actions Modal -->
                    <div class="modal fade" id="routingActionsModal" tabindex="-1" aria-labelledby="routingActionsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-l">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="routingActionsModalLabel">
                                        <i class="fas fa-route me-1"></i>
                                        Routing Actions
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <?php if (($current_stage['stage'] ?? '') === 'requestor' && ($pr['requested_by'] ?? 0) == $user_id): ?>
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="forward_to_warehouse">
                                            <div class="mb-3">
                                                <label for="remarks" class="form-label">Remarks (Optional)</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                                            </div>
                                            <div class="single-action-button">
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="fas fa-forward me-1"></i> Forward to Warehouse Department
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'warehouse' && 
                                               $user['department'] === 'Warehouse' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <form method="POST" action="" id="warehouseActionsForm">
                                            <div class="mb-3">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="routing-actions-buttons">
                                                <button type="submit" name="action" value="approve_warehouse" class="btn btn-success">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject_warehouse" class="btn btn-danger">
                                                    <i class="fas fa-times me-1"></i> Reject
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'purchasing' && 
                                               $user['department'] === 'Admin' && 
                                               $user['position'] === 'Purchaser' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <form method="POST" action="" id="purchasingActionsForm">
                                            <div class="mb-3">
                                                <?php 
                                                $can_approve = true;
                                                $approve_disabled_reason = '';
                                                
                                                if ($request_type === 'project') {
                                                    if ($document_type === 'ws' && !$ws_exists) {
                                                        $can_approve = false;
                                                        $approve_disabled_reason = 'Withdrawal Slip is required for WS document type.';
                                                    } elseif ($document_type === 'pr_po' && !$po_exists) {
                                                        $can_approve = false;
                                                        $approve_disabled_reason = 'Purchase Order is required for PR_PO document type.';
                                                    } elseif ($document_type === 'po_ws' && (!$po_exists || !$ws_exists)) {
                                                        $can_approve = false;
                                                        $approve_disabled_reason = 'Both Purchase Order and Withdrawal Slip are required.';
                                                    }
                                                } else {
                                                    if (!$po_exists) {
                                                        $can_approve = false;
                                                        $approve_disabled_reason = 'Purchase Order is required for this PR.';
                                                    }
                                                }
                                                ?>
                                                <?php if (!$can_approve): ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    <?php echo $approve_disabled_reason; ?>
                                                </div>
                                                <?php endif; ?>
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="routing-actions-buttons">
                                                <button type="submit" name="action" value="approve_purchasing" class="btn btn-success" <?php echo !$can_approve ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject_purchasing" class="btn btn-danger">
                                                    <i class="fas fa-times me-1"></i> Reject
                                                </button>
                                            </div>
                                        </form>
                                        
                                        <hr class="my-3">
                                        
                                        <?php if ($request_type === 'project'): ?>
                                            <?php if ($document_type === 'ws' && !$ws_exists): ?>
                                                <div class="withdrawal-info mb-3">
                                                    <h6><i class="fas fa-info-circle me-2"></i>Pure Withdrawal Slip Flow (WS)</h6>
                                                    <p class="mb-0">Create a Withdrawal Slip for this PR.</p>
                                                </div>
                                                <div class="single-action-button">
                                                    <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#createWSModal">
                                                        <i class="fas fa-file-invoice me-1"></i> Create Withdrawal Slip
                                                    </button>
                                                </div>
                                            <?php elseif ($document_type === 'pr_po' && !$po_exists): ?>
                                                <div class="mb-3">
                                                    <label for="po_remarks" class="form-label">PO Remarks (Optional)</label>
                                                    <textarea class="form-control" id="po_remarks" name="po_remarks" rows="2"></textarea>
                                                </div>
                                                <div class="single-action-button">
                                                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                                        <i class="fas fa-file-invoice-dollar me-1"></i> Create Purchase Order
                                                    </button>
                                                </div>
                                            <?php elseif ($document_type === 'po_ws'): ?>
                                                <div class="alert alert-warning mb-3">
                                                    <h6><i class="fas fa-info-circle me-2"></i>PO & WS</h6>
                                                    <p class="mb-0">This PR has items with stock and items that need purchasing. Create BOTH documents.</p>
                                                </div>
                                                
                                                <div class="document-creation-buttons">
                                                    <!-- Withdrawal Slip Button -->
                                                    <?php if (!$ws_exists): ?>
                                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createWSModal">
                                                        <i class="fas fa-file-invoice me-1"></i> Create Withdrawal Slip
                                                    </button>
                                                    <?php else: ?>
                                                    <button type="button" class="btn btn-success" disabled>
                                                        <i class="fas fa-check me-1"></i> Withdrawal Slip Created
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Purchase Order Button -->
                                                    <?php if (!$po_exists): ?>
                                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                                        <i class="fas fa-file-invoice-dollar me-1"></i> Create Purchase Order
                                                    </button>
                                                    <?php else: ?>
                                                    <button type="button" class="btn btn-primary" disabled>
                                                        <i class="fas fa-check me-1"></i> Purchase Order Created
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if ($ws_exists && $po_exists): ?>
                                                <div class="alert alert-success mt-3">
                                                    <i class="fas fa-check-circle me-1"></i>
                                                    Both documents have been created successfully! You can now approve the PR.
                                                </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($show_po_button && !$po_exists && !$ws_exists): ?>
                                                <div class="mb-3">
                                                    <label for="po_remarks" class="form-label">PO Remarks (Optional)</label>
                                                    <textarea class="form-control" id="po_remarks" name="po_remarks" rows="2"></textarea>
                                                </div>
                                                <div class="single-action-button">
                                                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                                        <i class="fas fa-file-invoice-dollar me-1"></i> Create Purchase Order
                                                    </button>
                                                </div>
                                            <?php elseif ($po_exists): ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Purchase Order already created for this PR.
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'accounting' && 
                                               $user['department'] === 'Admin' && 
                                               $user['position'] === 'Accounting' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <form method="POST" action="" id="accountingActionsForm">
                                            <div class="mb-3">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="routing-actions-buttons">
                                                <button type="submit" name="action" value="approve_accounting" class="btn btn-success">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject_accounting" class="btn btn-danger">
                                                    <i class="fas fa-times me-1"></i> Reject
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'approver' && 
                                               $user['department'] === 'Admin' && 
                                               $user['position'] === 'CEO' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <?php if ($request_type === 'project' && $threshold_status === 'exceed' && $document_type !== 'ws'): ?>
                                        <div class="approval-restriction">
                                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Approval Restriction</h6>
                                            <p class="mb-0">The total PO amount (₱<?php echo number_format($total_po_amount, 2); ?>) exceeds the project threshold (₱<?php echo number_format($threshold_amount, 2); ?>). You must adjust the threshold amount before you can approve this PR.</p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="" id="approverActionsForm">
                                            <div class="mb-3">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="routing-actions-buttons">
                                                <button type="submit" name="action" value="approve_approver" class="btn btn-success" <?php echo ($request_type === 'project' && $threshold_status === 'exceed' && !$threshold_amount_adjusted && $document_type !== 'ws') ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject_approver" class="btn btn-danger">
                                                    <i class="fas fa-times me-1"></i> Reject
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'purchasing_final' && 
                                               $user['department'] === 'Admin' && 
                                               $user['position'] === 'Purchaser' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <form method="POST" action="" id="purchasingFinalActionsForm">
                                            <div class="mb-3">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="routing-actions-buttons">
                                                <button type="submit" name="action" value="approve_purchasing_final" class="btn btn-success">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject_purchasing_final" class="btn btn-danger">
                                                    <i class="fas fa-times me-1"></i> Reject
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'warehouse_receiving' && 
                                               $user['department'] === 'Warehouse' && 
                                               $user['accounttype'] === 'Admin' && 
                                               $document_type !== 'ws'): ?>
                                        <form method="POST" action="" id="warehouseReceivingActionsForm">
                                            <div class="mb-3">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="single-action-button">
                                                <button type="submit" name="action" value="complete_warehouse_receiving" class="btn btn-success">
                                                    <i class="fas fa-check me-1"></i> Complete
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'warehouse_releasing' && 
                                               $user['department'] === 'Warehouse' && 
                                               $user['accounttype'] === 'Admin' &&
                                               ($document_type === 'ws' || $document_type === 'po_ws')): ?>
                                        <div class="warehouse-releasing-info mb-3">
                                            <h6><i class="fas fa-info-circle me-2"></i>Warehouse Releasing Stage</h6>
                                            <p class="mb-0">Process the withdrawal slip to release items from inventory.</p>
                                        </div>
                                        
                                        <?php if ($request_type === 'project' && $ws_exists): 
                                            $wsStatusStmt = $pdo->prepare("SELECT status FROM withdrawal_slips WHERE pr_id = ? ORDER BY id DESC LIMIT 1");
                                            $wsStatusStmt->execute([$pr_id]);
                                            $ws_status_check = $wsStatusStmt->fetch(PDO::FETCH_ASSOC);
                                        ?>
                                            <?php if (!$ws_status_check || ($ws_status_check['status'] ?? '') !== 'released'): ?>
                                            <div class="delivery-requirement">
                                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Withdrawal Slip Processing Required</h6>
                                                <?php if (!$ws_approved): ?>
                                                <p class="mb-0">This withdrawal slip is pending approval from Approver (CEO). You must wait for approval before you can release items.</p>
                                                <?php else: ?>
                                                <p class="mb-0">You must process the withdrawal slip before completing warehouse releasing. Use the "Release Items" button in the Withdrawal Slips section above.</p>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="" id="warehouseReleasingActionsForm">
                                            <div class="mb-3">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="single-action-button">
                                                <?php if ($request_type === 'project' && $ws_exists): ?>
                                                    <?php 
                                                    $wsStatusStmt = $pdo->prepare("SELECT status FROM withdrawal_slips WHERE pr_id = ? ORDER BY id DESC LIMIT 1");
                                                    $wsStatusStmt->execute([$pr_id]);
                                                    $ws_status_check = $wsStatusStmt->fetch(PDO::FETCH_ASSOC);
                                                    ?>
                                                    <?php if ($ws_status_check && ($ws_status_check['status'] ?? '') === 'released'): ?>
                                                        <button type="submit" name="action" value="complete_warehouse_releasing" class="btn btn-success">
                                                            <i class="fas fa-check me-1"></i> Complete Warehouse Releasing
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" name="action" value="complete_warehouse_releasing" class="btn btn-success disabled-button" disabled>
                                                            <i class="fas fa-check me-1"></i> Complete Warehouse Releasing
                                                        </button>
                                                        <small class="d-block text-muted mt-2">
                                                            <?php if (!$ws_approved): ?>
                                                                Withdrawal slip must be approved by Approver first.
                                                            <?php else: ?>
                                                                Items must be released first.
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="complete_warehouse_releasing" class="btn btn-success disabled-button" disabled>
                                                        <i class="fas fa-check me-1"></i> Complete Warehouse Releasing
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'purchasing_completion' && 
                                               $user['department'] === 'Admin' && 
                                               $user['position'] === 'Purchaser' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <form method="POST" action="" id="completionActionsForm">
                                            <div class="mb-3">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="single-action-button">
                                                <button type="submit" name="action" value="complete_purchasing" class="btn btn-success">
                                                    <i class="fas fa-check me-1"></i> Complete
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'accounting_final' && 
                                               $user['department'] === 'Admin' && 
                                               $user['position'] === 'Accounting' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <form method="POST" action="" id="accountingFinalActionsForm">
                                            <div class="mb-3">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="single-action-button">
                                                <button type="submit" name="action" value="finalize_accounting" class="btn btn-success">
                                                    <i class="fas fa-check me-1"></i> Finalize
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php else: ?>
                                        <div class="alert alert-info text-center">
                                            <i class="fas fa-info-circle me-1"></i>
                                            No actions available for you at this stage.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Adjust Threshold Amount Modal - Only show for project and not WS -->
                    <?php if ($request_type === 'project' && $document_type !== 'ws'): ?>
                    <div class="modal fade" id="adjustThresholdModal" tabindex="-1" aria-labelledby="adjustThresholdModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="adjustThresholdModalLabel">
                                        <i class="fas fa-balance-scale me-1"></i>
                                        Adjust Threshold Amount
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <?php if ($threshold_status === 'exceed'): ?>
                                    <div class="threshold-warning mb-3">
                                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Threshold Exceeded</h6>
                                        <p class="mb-0">The total PO amount (₱<?php echo number_format($total_po_amount, 2); ?>) exceeds the project threshold (₱<?php echo number_format($threshold_amount, 2); ?>). You must adjust the threshold before approving.</p>
                                    </div>
                                    <?php elseif ($threshold_needs_setting): ?>
                                    <div class="threshold-warning mb-3">
                                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Threshold Not Set</h6>
                                        <p class="mb-0">The project threshold amount is ₱0.00. You may set a threshold amount if needed.</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info mb-3">
                                        <h6><i class="fas fa-info-circle me-2"></i>Threshold Information</h6>
                                        <p class="mb-0">The total PO amount is within the project threshold. You may adjust the threshold if needed, but it's not required for approval.</p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" action="" id="adjustThresholdForm">
                                        <input type="hidden" name="action" value="adjust_threshold_amount">
                                        <div class="mb-3">
                                            <label for="threshold_amount_to_adjust" class="form-label">Amount to Adjust (₱)</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="threshold_amount_to_adjust" 
                                                   name="threshold_amount_to_adjust" 
                                                   placeholder="Enter amount to add or subtract">
                                            <div class="form-text">
                                                Current threshold: ₱<?php echo number_format($threshold_amount, 2); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="routing-actions-buttons">
                                            <button type="submit" name="adjustment_type" value="add" class="btn btn-success">
                                                <i class="fas fa-plus-circle me-1"></i> Add
                                            </button>
                                            <button type="submit" name="adjustment_type" value="subtract" class="btn btn-danger">
                                                <i class="fas fa-minus-circle me-1"></i> Subtract
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Create Purchase Order Modal - Only show if document_type allows PO -->
                    <?php if ($request_type === 'project' ? ($document_type === 'pr_po' || $document_type === 'po_ws') : true): ?>
                    <div class="modal fade" id="createPOModal" tabindex="-1" aria-labelledby="createPOModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="createPOModalLabel">Create Purchase Order</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="" id="createPOForm">
                                    <input type="hidden" name="action" value="create_purchase_order">
                                    <div class="modal-body">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <?php if ($request_type === 'project'): ?>
                                                This will create a purchase order for items that need to be purchased.
                                            <?php else: ?>
                                                This will create a purchase order for all supplier-requested items.
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="expected_delivery" class="form-label">Expected Delivery Date</label>
                                                <input type="date" class="form-control" id="expected_delivery" name="expected_delivery" 
                                                        min="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="po_remarks" class="form-label">PO Remarks</label>
                                                <textarea class="form-control" id="po_remarks" name="po_remarks" rows="2"></textarea>
                                            </div>
                                        </div>
                                        
                                        <h6>Items for Purchase Order:</h6>
                                        <div class="table-responsive" style="max-height: 400px;">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th width="30">
                                                            <input type="checkbox" id="selectAllPOItems">
                                                        </th>
                                                        <th>Item Name</th>
                                                        <?php if ($request_type === 'project'): ?>
                                                        <th>Warehouse</th>
                                                        <?php endif; ?>
                                                        <th>Supplier</th>
                                                        <th>Qty to Order</th>
                                                        <th>Unit Cost</th>
                                                        <th>Total Cost</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    if ($request_type === 'supplier' || !empty($items_for_po)): 
                                                        if ($request_type === 'supplier') {
                                                            $supplier_items_for_po = [];
                                                            foreach ($items as $item) {
                                                                $received_qty = $item['supplier_received_quantity'] ?? 0;
                                                                $remaining_needed = ($item['quantity'] ?? 0) - $received_qty;
                                                                
                                                                if ($remaining_needed > 0) {
                                                                    $supplier_items_for_po[] = [
                                                                        'pr_item_id' => $item['id'] ?? 0,
                                                                        'item_id' => $item['item_id'] ?? 0,
                                                                        'item_code' => $item['item_code'] ?? '',
                                                                        'item_name' => $item['item_name'] ?? '',
                                                                        'warehouse_id' => $item['warehouse_id'] ?? 0,
                                                                        'warehouse_name' => $item['warehouse_name'] ?? '',
                                                                        'supplier_id' => $item['supplier_id'] ?? 0,
                                                                        'item_supplier_name' => $item['item_supplier_name'] ?? '',
                                                                        'requested_quantity' => $item['quantity'] ?? 0,
                                                                        'delivered_quantity' => 0,
                                                                        'supplier_received_quantity' => $received_qty,
                                                                        'current_stock' => 0,
                                                                        'remaining_needed' => $remaining_needed,
                                                                        'quantity_to_order' => $remaining_needed,
                                                                        'unit_cost' => $item['unit_cost'] ?? 0
                                                                    ];
                                                                }
                                                            }
                                                            $display_items = $supplier_items_for_po;
                                                        } else {
                                                            $display_items = $items_for_po;
                                                        }
                                                        
                                                        if (!empty($display_items)): 
                                                            foreach ($display_items as $item): 
                                                    ?>
                                                    <tr class="po-item-row">
                                                        <td>
                                                            <input type="checkbox" name="selected_items[]" value="<?php echo $item['pr_item_id'] ?? 0; ?>" 
                                                                class="po-item-checkbox" data-item-id="<?php echo $item['pr_item_id'] ?? 0; ?>" checked>
                                                        </td>
                                                        <td><?php echo formatItemNameWithCode($item['item_name'] ?? '', $item['item_code'] ?? ''); ?></td>
                                                        <?php if ($request_type === 'project'): ?>
                                                        <td><?php echo htmlspecialchars($item['warehouse_name'] ?? 'N/A'); ?></td>
                                                        <?php endif; ?>
                                                        <td>
                                                            <select name="supplier_id_<?php echo $item['pr_item_id'] ?? 0; ?>" class="form-select form-select-sm supplier-select" required>
                                                                <option value="">Select Supplier</option>
                                                                <?php foreach ($all_suppliers as $supplier): ?>
                                                                <option value="<?php echo $supplier['id'] ?? 0; ?>" 
                                                                    <?php echo (($item['supplier_id'] ?? 0) == ($supplier['id'] ?? 0)) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($supplier['supplier_name'] ?? ''); ?>
                                                                </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="number" name="quantity_<?php echo $item['pr_item_id'] ?? 0; ?>" 
                                                                value="<?php echo $item['quantity_to_order'] ?? 0; ?>" 
                                                                min="1" max="<?php echo $item['quantity_to_order'] ?? 0; ?>" 
                                                                class="form-control form-control-sm po-quantity-input" 
                                                                data-item-id="<?php echo $item['pr_item_id'] ?? 0; ?>"
                                                                style="width: 80px;">
                                                        </td>
                                                        <td>
                                                            <input type="number" name="unit_cost_<?php echo $item['pr_item_id'] ?? 0; ?>" 
                                                                value="<?php echo $item['unit_cost'] ?? 0; ?>" 
                                                                step="0.01" min="0.01" 
                                                                class="form-control form-control-sm po-unit-cost-input" 
                                                                data-item-id="<?php echo $item['pr_item_id'] ?? 0; ?>"
                                                                style="width: 100px;" required>
                                                        </td>
                                                        <td>
                                                            <span class="po-total-cost" id="po_total_<?php echo $item['pr_item_id'] ?? 0; ?>">
                                                                ₱<?php echo number_format(($item['quantity_to_order'] ?? 0) * ($item['unit_cost'] ?? 0), 2); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <?php 
                                                            endforeach;
                                                        else: 
                                                    ?>
                                                    <tr>
                                                        <td colspan="<?php echo $request_type === 'project' ? '7' : '6'; ?>" class="text-center py-3">
                                                            <i class="fas fa-check-circle text-success me-2"></i>
                                                            <?php if ($request_type === 'project'): ?>
                                                                All items are sufficiently stocked. No purchase order needed.
                                                            <?php else: ?>
                                                                All items have been fully received. No purchase order needed.
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php else: ?>
                                                    <tr>
                                                        <td colspan="<?php echo $request_type === 'project' ? '7' : '6'; ?>" class="text-center py-3">
                                                            <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                                            No items available for purchase order.
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <strong>Total Items Selected: <span id="poSelectedCount">0</span></strong>
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <strong>Grand Total: ₱<span id="poGrandTotal">0.00</span></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success" id="createPOBtn" 
                                            <?php 
                                            $has_items_for_po = false;
                                            if ($request_type === 'project') {
                                                $has_items_for_po = !empty($items_for_po);
                                            } else {
                                                foreach ($items as $item) {
                                                    $received_qty = $item['supplier_received_quantity'] ?? 0;
                                                    if (($item['quantity'] ?? 0) - $received_qty > 0) {
                                                        $has_items_for_po = true;
                                                        break;
                                                    }
                                                }
                                            }
                                            echo $has_items_for_po ? '' : 'disabled';
                                            ?>>
                                            <i class="fas fa-file-invoice-dollar me-1"></i> Create Purchase Order
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Create Withdrawal Slip Modal - Only show if document_type allows WS -->
                    <?php if ($request_type === 'project' && ($document_type === 'ws' || $document_type === 'po_ws')): ?>
                    <div class="modal fade" id="createWSModal" tabindex="-1" aria-labelledby="createWSModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="createWSModalLabel">Create Withdrawal Slip</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="" id="createWSForm">
                                    <input type="hidden" name="action" value="create_withdrawal_slip">
                                    <div class="modal-body">
                                        <div class="alert alert-success">
                                            <i class="fas fa-info-circle me-1"></i>
                                            This will create a withdrawal slip to withdraw items from inventory.
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <label for="ws_remarks" class="form-label">Withdrawal Slip Remarks</label>
                                                <textarea class="form-control" id="ws_remarks" name="ws_remarks" rows="2"></textarea>
                                            </div>
                                        </div>
                                        
                                        <h6>Items for Withdrawal Slip:</h6>
                                        <div class="table-responsive" style="max-height: 400px;">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th width="30">
                                                            <input type="checkbox" id="selectAllWSItems">
                                                        </th>
                                                        <th>Item Name</th>
                                                        <th>Warehouse</th>
                                                        <th>Requested</th>
                                                        <th>Released</th>
                                                        <th>Remaining</th>
                                                        <th>Current Stock</th>
                                                        <th>Qty to Withdraw</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($items_for_withdrawal)): 
                                                        foreach ($items_for_withdrawal as $item): 
                                                    ?>
                                                    <tr class="ws-item-row">
                                                        <td>
                                                            <input type="checkbox" name="selected_withdrawal_items[]" value="<?php echo $item['pr_item_id'] ?? 0; ?>" 
                                                                   class="ws-item-checkbox" data-item-id="<?php echo $item['pr_item_id'] ?? 0; ?>" checked>
                                                        </td>
                                                        <td><?php echo formatItemNameWithCode($item['item_name'] ?? '', $item['item_code'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($item['warehouse_name'] ?? 'N/A'); ?></td>
                                                        <td><?php echo $item['requested_quantity'] ?? 0; ?></td>
                                                        <td><?php echo $item['delivered_quantity'] ?? 0; ?></td>
                                                        <td><?php echo $item['remaining_needed'] ?? 0; ?></td>
                                                        <td><?php echo $item['current_stock'] ?? 0; ?></td>
                                                        <td>
                                                            <input type="number" name="withdrawal_quantity_<?php echo $item['pr_item_id'] ?? 0; ?>" 
                                                                   value="<?php echo $item['quantity_to_withdraw'] ?? 0; ?>" 
                                                                   min="1" max="<?php echo $item['quantity_to_withdraw'] ?? 0; ?>" 
                                                                   class="form-control form-control-sm ws-quantity-input" 
                                                                   data-item-id="<?php echo $item['pr_item_id'] ?? 0; ?>"
                                                                   style="width: 80px;">
                                                        </td>
                                                    </tr>
                                                    <?php 
                                                        endforeach;
                                                    else: 
                                                    ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center py-3">
                                                            <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                                            No items available for withdrawal slip.
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <strong>Total Items Selected: <span id="wsSelectedCount">0</span></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success" id="createWSBtn" 
                                            <?php echo !empty($items_for_withdrawal) ? '' : 'disabled'; ?>>
                                            <i class="fas fa-file-invoice me-1"></i> Create Withdrawal Slip
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- View Purchase Order Modal -->
                    <div class="modal fade" id="viewPOModal" tabindex="-1" aria-labelledby="viewPOModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="viewPOModalLabel">Purchase Order Details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <table class="table table-borderless">
                                                <tr>
                                                    <th width="40%">PO Number:</th>
                                                    <td id="modal-po-number">-</td>
                                                </tr>
                                                <tr>
                                                    <th>Expected Delivery:</th>
                                                    <td id="modal-expected-delivery">-</td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-borderless">
                                                <tr>
                                                    <th>PO Date:</th>
                                                    <td id="modal-po-date">-</td>
                                                </tr>
                                                <tr>
                                                    <th width="40%">Total Amount:</th>
                                                    <td id="modal-total-amount">-</td>
                                                </tr>
                                                <tr>
                                                    <th>Remarks:</th>
                                                    <td id="modal-po-remarks">-</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>

                                    <h6>PO Items:</h6>
                                    <div class="table-responsive" style="max-height: 400px;">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Item Name</th>
                                                    <?php if ($request_type === 'project'): ?>
                                                    <th>Warehouse</th>
                                                    <?php endif; ?>
                                                    <th>Supplier</th>
                                                    <th>Quantity</th>
                                                    <th>Received</th>
                                                    <th>Remaining</th>
                                                    <th>Unit Cost</th>
                                                    <th>Total Cost</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="modal-po-items">
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- View Withdrawal Slip Modal -->
                    <div class="modal fade" id="viewWSModal" tabindex="-1" aria-labelledby="viewWSModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="viewWSModalLabel">Withdrawal Slip Details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <table class="table table-borderless">
                                                <tr>
                                                    <th width="40%">WS Number:</th>
                                                    <td id="modal-ws-number">-</td>
                                                </tr>
                                                <tr>
                                                    <th width="40%">Status:</th>
                                                    <td id="modal-ws-status">-</td>
                                                </tr>
                                                <tr>
                                                    <th>Warehouse:</th>
                                                    <td id="modal-ws-warehouse">-</td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-borderless">
                                                <tr>
                                                    <th>WS Date:</th>
                                                    <td id="modal-ws-date">-</td>
                                                </tr>
                                                <tr>
                                                    <th>Remarks:</th>
                                                    <td id="modal-ws-remarks">-</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>

                                    <h6>Withdrawal Slip Items:</h6>
                                    <div class="table-responsive" style="max-height: 400px;">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Item Name</th>
                                                    <th>Warehouse</th>
                                                    <th>Quantity</th>
                                                    <th>Unit Cost</th>
                                                    <th>Total Cost</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="modal-ws-items">
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Process Withdrawal Slip Modal - Only show if document_type allows WS -->
                    <?php if ($request_type === 'project' && ($document_type === 'ws' || $document_type === 'po_ws')): ?>
                    <div class="modal fade" id="processWSModal" tabindex="-1" aria-labelledby="processWSModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="processWSModalLabel">Withdrawal Slip Items Release</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="" id="processWSForm">
                                    <input type="hidden" name="action" value="process_withdrawal_slip">
                                    <input type="hidden" name="ws_id" id="process_ws_id">
                                    <div class="modal-body">
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            This will process the withdrawal slip and withdraw items from inventory using FIFO method. This action cannot be undone.
                                        </div>
                                        
                                        <?php if (!$ws_approved): ?>
                                        <div class="alert alert-danger">
                                            <i class="fas fa-exclamation-circle me-1"></i>
                                            <strong>Cannot release items:</strong> This withdrawal slip has not been approved by Approver (CEO) yet. Please wait for approval.
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Withdrawal Slip Number:</label>
                                            <input type="text" class="form-control" id="process_ws_number" readonly>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="released_date" class="form-label">Released Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="released_date" name="released_date" 
                                                   value="<?php echo date('Y-m-d'); ?>" <?php echo !$ws_approved ? 'disabled' : ''; ?> required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success" <?php echo !$ws_approved ? 'disabled' : ''; ?>>
                                            Release Items
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Deliver Item Modal -->
                    <div class="modal fade" id="deliverItemModal" tabindex="-1" aria-labelledby="deliverItemModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="deliverItemModalLabel">Release Item to Project (FIFO)</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="" id="deliverForm">
                                    <input type="hidden" name="action" id="deliver_action" value="deliver_to_project">
                                    <input type="hidden" name="item_id" id="deliver_item_id">
                                    <input type="hidden" name="warehouse_id" id="deliver_warehouse_id">
                                    <input type="hidden" name="requested_quantity" id="deliver_requested_quantity">
                                    <input type="hidden" name="quantity" id="deliver_quantity">
                                    <div class="modal-body">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <span id="delivery-method-text">This will release the item to the project using FIFO (First-In, First-Out) method.</span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Item:</label>
                                            <input type="text" class="form-control" id="deliver_item_name" readonly>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Warehouse:</label>
                                            <input type="text" class="form-control" id="deliver_warehouse_name" readonly>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Requested Quantity:</label>
                                            <input type="text" class="form-control" id="deliver_requested_quantity_display" readonly>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Available Stock:</label>
                                            <input type="text" class="form-control" id="deliver_available_stock" readonly>
                                        </div>
                                        
                                        <div class="mb-3" id="deliver_quantity_field">
                                            <label for="deliver_quantity_input" class="form-label">Quantity to Release <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="deliver_quantity_input" name="deliver_quantity" 
                                                   min="1">
                                            <div class="form-text">Enter the quantity you want to release</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="deliver_date_issued" class="form-label">Date Issued <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="deliver_date_issued" name="date_issued" 
                                                   value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="deliver_remarks" class="form-label">Remarks (Optional)</label>
                                            <textarea class="form-control" id="deliver_remarks" name="remarks" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success" id="deliver_submit_btn">Release to Project</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Routing History -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-history me-1"></i>
                            Routing History
                        </div>
                        <div class="card-body">
                            <?php if (!empty($routing_history)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Action</th>
                                            <th>Performed By</th>
                                            <th>Department</th>
                                            <th>Remarks</th>
                                            <th>From Stage</th>
                                            <th>To Stage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($routing_history as $history): ?>
                                        <tr>
                                            <td><?php echo formatDateTimeMDY($history['created_at'] ?? null); ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    if (strpos($history['action'] ?? '', 'Approved') !== false) echo 'bg-success';
                                                    elseif (strpos($history['action'] ?? '', 'Rejected') !== false) echo 'bg-danger';
                                                    elseif (strpos($history['action'] ?? '', 'Delivered') !== false) echo 'bg-info';
                                                    elseif (strpos($history['action'] ?? '', 'Purchase Order') !== false) echo 'bg-warning';
                                                    elseif (strpos($history['action'] ?? '', 'Withdrawal Slip') !== false) echo 'bg-info';
                                                    elseif (strpos($history['action'] ?? '', 'Received') !== false) echo 'bg-primary';
                                                    elseif (strpos($history['action'] ?? '', 'Completed') !== false) echo 'bg-success';
                                                    elseif (strpos($history['action'] ?? '', 'Finalized') !== false) echo 'bg-success';
                                                    elseif (strpos($history['action'] ?? '', 'Threshold Amount Added') !== false) echo 'bg-success';
                                                    elseif (strpos($history['action'] ?? '', 'Threshold Amount Subtracted') !== false) echo 'bg-danger';
                                                    else echo 'bg-info';
                                                    ?>
                                                ">
                                                    <?php echo htmlspecialchars($history['action'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatUserName($history); ?></td>
                                            <td><?php echo htmlspecialchars($history['department'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($history['remarks'] ?? ''); ?></td>
                                            <td>
                                                <?php 
                                                switch($history['stage_from'] ?? '') {
                                                    case 'requestor': echo 'Requestor'; break;
                                                    case 'warehouse': echo 'Warehouse'; break;
                                                    case 'purchasing': echo 'Purchasing'; break;
                                                    case 'accounting': echo 'Accounting'; break;
                                                    case 'approver': echo 'Approver (CEO)'; break;
                                                    case 'purchasing_final': echo 'Purchasing Final'; break;
                                                    case 'warehouse_receiving': echo 'Warehouse Receiving'; break;
                                                    case 'warehouse_releasing': echo 'Warehouse Releasing'; break;
                                                    case 'purchasing_completion': echo 'Purchasing Completion'; break;
                                                    case 'accounting_final': echo 'Accounting Final'; break;
                                                    case 'completed': echo 'Completed'; break;
                                                    case 'rejected': echo 'Rejected'; break;
                                                    default: echo ucfirst($history['stage_from'] ?? 'Unknown');
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                switch($history['stage_to'] ?? '') {
                                                    case 'requestor': echo 'Requestor'; break;
                                                    case 'warehouse': echo 'Warehouse'; break;
                                                    case 'purchasing': echo 'Purchasing'; break;
                                                    case 'accounting': echo 'Accounting'; break;
                                                    case 'approver': echo 'Approver (CEO)'; break;
                                                    case 'purchasing_final': echo 'Purchasing Final'; break;
                                                    case 'warehouse_receiving': echo 'Warehouse Receiving'; break;
                                                    case 'warehouse_releasing': echo 'Warehouse Releasing'; break;
                                                    case 'purchasing_completion': echo 'Purchasing Completion'; break;
                                                    case 'accounting_final': echo 'Accounting Final'; break;
                                                    case 'completed': echo 'Completed'; break;
                                                    case 'rejected': echo 'Rejected'; break;
                                                    default: echo ucfirst($history['stage_to'] ?? 'Unknown');
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <h5>No Routing History</h5>
                                <p class="text-muted">Routing actions will appear here once performed.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (!empty($swal_data)): ?>
            Swal.fire({
                title: '<?php echo addslashes($swal_data['title']); ?>',
                text: '<?php echo addslashes($swal_data['text']); ?>',
                icon: '<?php echo addslashes($swal_data['icon']); ?>',
                confirmButtonText: 'OK'
            });
        <?php endif; ?>

        function formatNumberWithCommas(number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function parseNumberWithCommas(formattedNumber) {
            return parseFloat(formattedNumber.replace(/,/g, ''));
        }

        function formatCurrency(amount) {
            return '₱' + formatNumberWithCommas(parseFloat(amount).toFixed(2));
        }

        function formatDateMMDDYYYY(dateString) {
            if (!dateString || dateString === 'Not set' || dateString === '-') return dateString;
            
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return dateString;
                
                const month = (date.getMonth() + 1).toString().padStart(2, '0');
                const day = date.getDate().toString().padStart(2, '0');
                const year = date.getFullYear();
                
                return month + '-' + day + '-' + year;
            } catch(e) {
                return dateString;
            }
        }

        function calculatePOTotals() {
            let grandTotal = 0;
            let selectedCount = 0;
            
            document.querySelectorAll('.po-item-checkbox:checked').forEach(checkbox => {
                selectedCount++;
                const itemId = checkbox.getAttribute('data-item-id');
                const quantity = parseFloat(document.querySelector(`.po-quantity-input[data-item-id="${itemId}"]`).value) || 0;
                const unitCost = parseFloat(document.querySelector(`.po-unit-cost-input[data-item-id="${itemId}"]`).value) || 0;
                const total = quantity * unitCost;
                
                const totalElement = document.getElementById(`po_total_${itemId}`);
                if (totalElement) {
                    totalElement.textContent = formatCurrency(total);
                }
                grandTotal += total;
            });
            
            const selectedCountElement = document.getElementById('poSelectedCount');
            if (selectedCountElement) {
                selectedCountElement.textContent = selectedCount;
            }
            
            const grandTotalElement = document.getElementById('poGrandTotal');
            if (grandTotalElement) {
                grandTotalElement.textContent = formatNumberWithCommas(grandTotal.toFixed(2));
            }
            
            const createPOBtn = document.getElementById('createPOBtn');
            if (createPOBtn) {
                createPOBtn.disabled = selectedCount === 0;
            }
        }
        
        const selectAllPOCheckbox = document.getElementById('selectAllPOItems');
        if (selectAllPOCheckbox) {
            selectAllPOCheckbox.addEventListener('change', function() {
                document.querySelectorAll('.po-item-checkbox').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                calculatePOTotals();
            });
        }
        
        document.querySelectorAll('.po-item-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', calculatePOTotals);
        });
        
        document.querySelectorAll('.po-quantity-input, .po-unit-cost-input').forEach(input => {
            input.addEventListener('input', calculatePOTotals);
        });

        function calculateWSTotals() {
            let selectedCount = 0;
            
            document.querySelectorAll('.ws-item-checkbox:checked').forEach(checkbox => {
                selectedCount++;
            });
            
            const selectedCountElement = document.getElementById('wsSelectedCount');
            if (selectedCountElement) {
                selectedCountElement.textContent = selectedCount;
            }
            
            const createWSBtn = document.getElementById('createWSBtn');
            if (createWSBtn) {
                createWSBtn.disabled = selectedCount === 0;
            }
        }
        
        const selectAllWSCheckbox = document.getElementById('selectAllWSItems');
        if (selectAllWSCheckbox) {
            selectAllWSCheckbox.addEventListener('change', function() {
                document.querySelectorAll('.ws-item-checkbox').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                calculateWSTotals();
            });
        }
        
        document.querySelectorAll('.ws-item-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', calculateWSTotals);
        });
        
        document.querySelectorAll('.ws-quantity-input').forEach(input => {
            input.addEventListener('input', calculateWSTotals);
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof calculatePOTotals === 'function') {
                calculatePOTotals();
            }
            
            if (typeof calculateWSTotals === 'function') {
                calculateWSTotals();
            }
            
            const createPOBtn = document.getElementById('createPOBtn');
            if (createPOBtn) {
                const requestType = '<?php echo $request_type; ?>';
                const documentType = '<?php echo $document_type; ?>';
                const itemsAvailable = <?php 
                    if ($request_type === 'project') {
                        echo !empty($items_for_po) ? 'true' : 'false';
                    } else {
                        $has_items_to_order = false;
                        foreach ($items as $item) {
                            $received_qty = $item['supplier_received_quantity'] ?? 0;
                            if (($item['quantity'] ?? 0) - $received_qty > 0) {
                                $has_items_to_order = true;
                                break;
                            }
                        }
                        echo $has_items_to_order ? 'true' : 'false';
                    }
                ?>;
                
                if (itemsAvailable) {
                    createPOBtn.disabled = false;
                }
            }
            
            const createWSBtn = document.getElementById('createWSBtn');
            if (createWSBtn) {
                const wsItemsAvailable = <?php echo !empty($items_for_withdrawal) ? 'true' : 'false'; ?>;
                if (wsItemsAvailable) {
                    createWSBtn.disabled = false;
                }
            }
            
            // Hide Batch Number column using CSS
            const style = document.createElement('style');
            style.textContent = '.batch-column { display: none; }';
            document.head.appendChild(style);
        });

        document.addEventListener('DOMContentLoaded', function() {
            const viewButtons = document.querySelectorAll('.view-po-btn');
            const viewModal = new bootstrap.Modal(document.getElementById('viewPOModal'));
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const poId = this.getAttribute('data-po-id');
                    const poNumber = this.getAttribute('data-po-number');
                    const poDate = formatDateMMDDYYYY(this.getAttribute('data-po-date'));
                    const expectedDelivery = formatDateMMDDYYYY(this.getAttribute('data-expected-delivery'));
                    const totalAmount = this.getAttribute('data-total-amount');
                    const poStatus = this.getAttribute('data-po-status');
                    const poRemarks = this.getAttribute('data-po-remarks');
                    
                    // Format PO number with status badge
                    const statusClass = getPOStatusBadgeClass(poStatus);
                    document.getElementById('modal-po-number').innerHTML = poNumber + ' <span class="badge ' + statusClass + '">' + poStatus + '</span>';
                    
                    document.getElementById('modal-po-date').textContent = poDate || '-';
                    document.getElementById('modal-expected-delivery').textContent = expectedDelivery || '-';
                    document.getElementById('modal-total-amount').textContent = totalAmount ? '₱' + formatNumberWithCommas(parseFloat(totalAmount).toFixed(2)) : '-';
                    
                    // Remove the status row from the table if it exists
                    const statusRow = document.querySelector('#modal-po-status')?.closest('tr');
                    if (statusRow) {
                        statusRow.style.display = 'none';
                    }
                    
                    document.getElementById('modal-po-remarks').textContent = poRemarks || '-';
                    
                    const itemsContainer = document.getElementById('modal-po-items');
                    itemsContainer.innerHTML = '';
                    
                    const poItems = <?php echo json_encode($po_items_details); ?>;
                    
                    if (poItems[poId] && poItems[poId].length > 0) {
                        poItems[poId].forEach(item => {
                            const received = parseFloat(item.received_quantity || 0);
                            const remaining = parseFloat(item.quantity || 0) - received;
                            const status = item.status || 'pending';
                            const unitCost = parseFloat(item.unit_cost || 0);
                            const quantity = parseFloat(item.quantity || 0);
                            
                            let totalCostToDisplay;
                            if (received === 0) {
                                totalCostToDisplay = quantity * unitCost;
                            } else {
                                totalCostToDisplay = received * unitCost;
                            }
                            
                            function formatItemNameWithCode(itemName, itemCode) {
                                if (!itemCode) {
                                    return itemName || 'N/A';
                                }
                                return (itemName || 'N/A') + ' (' + itemCode + ')';
                            }

                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${formatItemNameWithCode(item.item_name, item.item_code)}</td>
                                ${<?php echo $request_type === 'project' ? 'true' : 'false'; ?> ? `<td>${item.warehouse_name || 'N/A'}</td>` : ''}
                                <td>${item.supplier_name || 'N/A'}</td>
                                <td>${quantity}</td>
                                <td>${received.toFixed(2)}</td>
                                <td>${remaining.toFixed(2)}</td>
                                <td>${formatCurrency(unitCost)}</td>
                                <td>${formatCurrency(totalCostToDisplay)}</td>
                                <td>
                                    <span class="badge ${getPOStatusBadgeClass(status)}">
                                        ${status}
                                    </span>
                                </td>
                            `;
                            itemsContainer.appendChild(row);
                        });
                    } else {
                        const colspanValue = <?php echo $request_type === 'project' ? '9' : '8'; ?>;
                        itemsContainer.innerHTML = `
                            <tr>
                                <td colspan="${colspanValue}" class="text-center py-3">
                                    <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                    No items found for this purchase order.
                                </td>
                            </tr>
                        `;
                    }
                    
                    viewModal.show();
                });
            });
            
            // Function to get PO status badge class
            function getPOStatusBadgeClass(status) {
                status = (status || '').toLowerCase();
                switch(status) {
                    case 'approved':
                        return 'bg-success';
                    case 'confirmed':
                    case 'delivered':
                    case 'completed':
                        return 'bg-success';
                    case 'pending':
                    case 'draft':
                        return 'bg-warning';
                    case 'rejected':
                    case 'cancelled':
                        return 'bg-danger';
                    case 'processing':
                        return 'bg-info';
                    case 'partially_received':
                        return 'bg-warning';
                    default:
                        return 'bg-secondary';
                }
            }
            
            function formatNumberWithCommas(number) {
                return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
            
            function formatCurrency(amount) {
                return '₱' + formatNumberWithCommas(parseFloat(amount).toFixed(2));
            }
            
            function formatDateMMDDYYYY(dateString) {
                if (!dateString || dateString === 'Not set' || dateString === '-') return dateString;
                
                try {
                    const date = new Date(dateString);
                    if (isNaN(date.getTime())) return dateString;
                    
                    const month = (date.getMonth() + 1).toString().padStart(2, '0');
                    const day = date.getDate().toString().padStart(2, '0');
                    const year = date.getFullYear();
                    
                    return month + '-' + day + '-' + year;
                } catch(e) {
                    return dateString;
                }
            }
        });

            document.addEventListener('DOMContentLoaded', function() {
        const viewWSButtons = document.querySelectorAll('.view-ws-btn');
        const viewWSModal = new bootstrap.Modal(document.getElementById('viewWSModal'));
        
        viewWSButtons.forEach(button => {
            button.addEventListener('click', function() {
                const wsId = this.getAttribute('data-ws-id');
                const wsNumber = this.getAttribute('data-ws-number');
                const wsDate = formatDateMMDDYYYY(this.getAttribute('data-ws-date'));
                const warehouse = this.getAttribute('data-warehouse');
                const wsStatus = this.getAttribute('data-ws-status');
                const wsRemarks = this.getAttribute('data-ws-remarks');
                
                // FIX: Set WS Number with status badge
                const statusClass = getWSStatusBadgeClass(wsStatus);
                document.getElementById('modal-ws-number').innerHTML = wsNumber + ' <span class="badge ' + statusClass + '">' + wsStatus + '</span>';
                
                document.getElementById('modal-ws-date').textContent = wsDate || '-';
                document.getElementById('modal-ws-warehouse').textContent = warehouse || '-';
                
                // Hide the separate status row since we now show it next to the WS number
                const statusRow = document.querySelector('#modal-ws-status')?.closest('tr');
                if (statusRow) {
                    statusRow.style.display = 'none';
                }
                
                document.getElementById('modal-ws-remarks').textContent = wsRemarks || '-';
                
                // Rest of the code remains the same...
                const itemsContainer = document.getElementById('modal-ws-items');
                itemsContainer.innerHTML = '';
                
                const wsItems = <?php echo json_encode($ws_items_details); ?>;
                
                if (wsItems[wsId] && wsItems[wsId].length > 0) {
                    wsItems[wsId].forEach(item => {
                        const status = item.status || 'pending';
                        const statusClass = getWSStatusBadgeClass(status);
                        const quantity = parseFloat(item.quantity || 0);
                        const unitCost = parseFloat(item.unit_cost || 0);
                        const totalCost = parseFloat(item.total_cost || quantity * unitCost);
                        
                        function formatItemNameWithCode(itemName, itemCode) {
                            if (!itemCode) {
                                return itemName || 'N/A';
                            }
                            return (itemName || 'N/A') + ' (' + itemCode + ')';
                        }

                        function formatCurrency(amount) {
                            return '₱' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                        }

                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${formatItemNameWithCode(item.item_name, item.item_code)}</td>
                            <td>${item.warehouse_name || 'N/A'}</td>
                            <td>${quantity}</td>
                            <td>${formatCurrency(unitCost)}</td>
                            <td>${formatCurrency(totalCost)}</td>
                            <td>
                                <span class="badge ${statusClass}">
                                    ${status}
                                </span>
                            </td>
                        `;
                        itemsContainer.appendChild(row);
                    });
                } else {
                    itemsContainer.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center py-3">
                                <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                No items found for this withdrawal slip.
                            </td>
                        </tr>
                    `;
                }
                
                viewWSModal.show();
            });
        });
        
        // Function to get WS status badge class
        function getWSStatusBadgeClass(status) {
            status = (status || '').toLowerCase();
            switch(status) {
                case 'confirmed':
                case 'released':
                    return 'bg-success';
                case 'approved':
                    return 'bg-success';
                case 'processing':
                    return 'bg-info';
                case 'pending':
                    return 'bg-warning';
                case 'draft':
                    return 'bg-secondary';
                case 'cancelled':
                case 'rejected':
                    return 'bg-danger';
                default:
                    return 'bg-secondary';
            }
        }
    });

        document.addEventListener('DOMContentLoaded', function() {
            const processWSButtons = document.querySelectorAll('.process-ws-btn');
            const processWSModal = new bootstrap.Modal(document.getElementById('processWSModal'));
            
            processWSButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const wsId = this.getAttribute('data-ws-id');
                    const wsNumber = this.getAttribute('data-ws-number');
                    
                    document.getElementById('process_ws_id').value = wsId || '';
                    document.getElementById('process_ws_number').value = wsNumber || '';
                    
                    processWSModal.show();
                });
            });
        });

        <?php if ($request_type === 'project'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const deliverButtons = document.querySelectorAll('.deliver-item');
            const deliverModal = new bootstrap.Modal(document.getElementById('deliverItemModal'));
            const deliverForm = document.getElementById('deliverForm');
            const deliverAction = document.getElementById('deliver_action');
            const deliverQuantityInput = document.getElementById('deliver_quantity_input');
            const deliverQuantityField = document.getElementById('deliver_quantity_field');
            const deliveryMethodText = document.getElementById('delivery-method-text');
            const deliverSubmitBtn = document.getElementById('deliver_submit_btn');
            const availableStockInput = document.getElementById('deliver_available_stock');
            
            deliverButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-item-id');
                    const itemName = this.getAttribute('data-item-name');
                    const warehouseId = this.getAttribute('data-warehouse-id');
                    const warehouseName = this.getAttribute('data-warehouse-name');
                    const requestedQuantity = this.getAttribute('data-quantity');
                    const availableStock = this.getAttribute('data-available-stock');
                    const deliveryType = this.getAttribute('data-delivery-type');
                    
                    document.getElementById('deliver_item_id').value = itemId || '0';
                    document.getElementById('deliver_item_name').value = itemName || '';
                    document.getElementById('deliver_warehouse_id').value = warehouseId || '0';
                    document.getElementById('deliver_warehouse_name').value = warehouseName || '';
                    document.getElementById('deliver_requested_quantity').value = requestedQuantity || '0';
                    document.getElementById('deliver_requested_quantity_display').value = requestedQuantity || '0';
                    document.getElementById('deliver_available_stock').value = availableStock || '0';
                    
                    if (deliveryType === 'full') {
                        if (deliverAction) deliverAction.value = 'deliver_to_project';
                        if (document.getElementById('deliver_quantity')) document.getElementById('deliver_quantity').value = requestedQuantity || '0';
                        if (deliverQuantityField) deliverQuantityField.style.display = 'none';
                        if (deliveryMethodText) deliveryMethodText.textContent = 'This will release the full requested quantity to the project using FIFO (First-In, First-Out) method.';
                        if (deliverSubmitBtn) deliverSubmitBtn.textContent = 'Release Full Quantity';
                    } else {
                        if (deliverAction) deliverAction.value = 'deliver_partial';
                        if (deliverQuantityField) deliverQuantityField.style.display = 'block';
                        if (deliverQuantityInput) {
                            deliverQuantityInput.value = '';
                            deliverQuantityInput.max = Math.min(parseInt(requestedQuantity) || 0, parseInt(availableStock) || 0);
                        }
                        if (deliveryMethodText) deliveryMethodText.textContent = 'This will release the specified quantity to the project using FIFO (First-In, First-Out) method.';
                        if (deliverSubmitBtn) deliverSubmitBtn.textContent = 'Release Partial Quantity';
                    }
                    
                    deliverModal.show();
                });
            });
            
            if (deliverForm) {
                deliverForm.addEventListener('submit', function(e) {
                    const deliverActionValue = document.getElementById('deliver_action').value;
                    const requestedQuantity = parseInt(document.getElementById('deliver_requested_quantity').value) || 0;
                    const quantityElement = document.getElementById('deliver_quantity');
                    
                    if (deliverActionValue === 'deliver_partial') {
                        const deliverQuantity = parseInt(document.getElementById('deliver_quantity_input').value) || 0;
                        if (deliverQuantity > requestedQuantity) {
                            e.preventDefault();
                            alert('Release quantity cannot exceed requested quantity.');
                            return;
                        }
                        if (quantityElement) {
                            quantityElement.value = deliverQuantity;
                        }
                    }
                });
            }
        });
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', function() {
            const thresholdInput = document.getElementById('threshold_amount_to_adjust');
            const adjustThresholdForm = document.getElementById('adjustThresholdForm');
            
            if (thresholdInput) {
                thresholdInput.addEventListener('input', function(e) {
                    let value = this.value.replace(/[^\d.]/g, '');
                    
                    const decimalCount = (value.match(/\./g) || []).length;
                    if (decimalCount > 1) {
                        value = value.substring(0, value.lastIndexOf('.'));
                    }
                    
                    let parts = value.split('.');
                    let wholePart = parts[0];
                    let decimalPart = parts.length > 1 ? '.' + parts[1] : '';
                    
                    if (wholePart) {
                        wholePart = wholePart.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                    }
                    
                    this.value = wholePart + decimalPart;
                });
                
                if (adjustThresholdForm) {
                    adjustThresholdForm.addEventListener('submit', function(e) {
                        const submitButton = e.submitter;
                        const adjustmentType = submitButton.value;
                        
                        const formattedValue = thresholdInput.value.trim();
                        
                        if (formattedValue === '') {
                            e.preventDefault();
                            Swal.fire({
                                title: 'No Amount',
                                text: 'Please enter an amount to adjust the threshold.',
                                icon: 'info',
                                confirmButtonText: 'OK'
                            });
                            thresholdInput.focus();
                            return;
                        }
                        
                        const numericValue = parseNumberWithCommas(formattedValue);
                        
                        if (isNaN(numericValue) || numericValue <= 0) {
                            e.preventDefault();
                            Swal.fire({
                                title: 'Invalid Amount',
                                text: 'Please enter a valid positive amount.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                            thresholdInput.focus();
                            return;
                        }
                        
                        if (adjustmentType === 'subtract') {
                            const currentThreshold = <?php echo $threshold_amount; ?>;
                            if (numericValue > currentThreshold) {
                                e.preventDefault();
                                Swal.fire({
                                    title: 'Invalid Subtraction',
                                    text: 'Cannot subtract more than the current threshold amount.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                                thresholdInput.focus();
                                return;
                            }
                        }
                        
                        thresholdInput.value = numericValue.toFixed(2);
                        
                        const adjustmentTypeInput = document.createElement('input');
                        adjustmentTypeInput.type = 'hidden';
                        adjustmentTypeInput.name = 'adjustment_type';
                        adjustmentTypeInput.value = adjustmentType;
                        adjustThresholdForm.appendChild(adjustmentTypeInput);
                    });
                }
            }
        });

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
    </script>
</body>
</html>