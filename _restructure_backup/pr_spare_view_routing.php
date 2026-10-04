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
    header('Location: purchase_request_spare_parts.php');
    exit();
}

$pr_id = $_GET['id'];

// Function to generate PO Number for spare parts
function generateSparePartsPONumber($pdo) {
    // Get the latest PO number from spare_part_po table
    $stmt = $pdo->prepare("SELECT po_number FROM spare_part_po ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $lastPO = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastPO) {
        // Extract the numeric part (assuming it's stored as just numbers like "000001")
        $lastNumber = intval($lastPO['po_number']);
        $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '000001';
    }
    
    return $newNumber;
}

// Function to generate Job Order Number
function generateJobOrderNumber($pdo) {
    $year = date('Y');
    
    // Get the latest Job Order number for this year from spare_parts_job_orders table
    $stmt = $pdo->prepare("SELECT job_order_number FROM spare_parts_job_orders WHERE job_order_number LIKE ? ORDER BY id DESC LIMIT 1");
    $likePattern = "JO-$year-%";
    $stmt->execute([$likePattern]);
    $lastJO = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastJO) {
        $lastNumber = intval(substr($lastJO['job_order_number'], -4));
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return "JO-$year-$newNumber";
}

// Function to generate Withdrawal Slip Number
function generateWithdrawalSlipNumber($pdo) {
    $year = date('Y');
    
    // Get the latest Withdrawal Slip number for this year
    $stmt = $pdo->prepare("SELECT withdrawal_slip_number FROM spare_parts_withdrawal_slips WHERE withdrawal_slip_number LIKE ? ORDER BY id DESC LIMIT 1");
    $likePattern = "WS-$year-%";
    $stmt->execute([$likePattern]);
    $lastWS = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastWS) {
        $lastNumber = intval(substr($lastWS['withdrawal_slip_number'], -4));
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return "WS-$year-$newNumber";
}

// Function to format date as mm-dd-yyyy with better error handling
function formatDateMDY($dateString) {
    // Check if date is empty or invalid
    if (empty($dateString) || $dateString == '0000-00-00' || $dateString == '0000-00-00 00:00:00' || $dateString == '1970-01-01') {
        return '—'; // Return em dash instead of 'N/A' or 'Invalid Date'
    }
    
    // If it's already in mm-dd-yyyy format, return as is
    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dateString)) {
        return $dateString;
    }
    
    // If it's in Y-m-d format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString)) {
        $parts = explode('-', $dateString);
        return $parts[1] . '-' . $parts[2] . '-' . $parts[0]; // mm-dd-yyyy
    }
    
    // Try to parse with DateTime
    try {
        $date = new DateTime($dateString);
        return $date->format('m-d-Y');
    } catch (Exception $e) {
        return '—'; // Return em dash on error
    }
}

// Function to format datetime as mm-dd-yyyy h:i A with better error handling
function formatDateTimeMDY($dateTimeString) {
    if (empty($dateTimeString) || $dateTimeString == '0000-00-00 00:00:00' || $dateTimeString == '1970-01-01 00:00:00') {
        return '—';
    }
    
    try {
        $date = new DateTime($dateTimeString);
        return $date->format('m-d-Y h:i A');
    } catch (Exception $e) {
        return '—';
    }
}

// Function to get request type text
function getRequestTypeText($type) {
    switch ($type) {
        case 'stock':
            return 'Stock Purchase Request';
        case 'issue':
            return 'Issue Parts Purchase Request';
        case 'issue_materials':
            return 'Issue Materials Purchase Request';
        default:
            return ucfirst($type);
    }
}

// Function to get request type badge
function getRequestTypeBadge($type) {
    switch ($type) {
        case 'stock':
            return 'badge bg-primary';
        case 'issue':
            return 'badge bg-success';
        case 'issue_materials':
            return 'badge bg-warning text-black';
        default:
            return 'badge bg-secondary';
    }
}

// Function to check stock availability for parts and get delivery status
function checkStockAvailability($pdo, $part_id, $requested_quantity, $pr_item_id = null) {
    // Check if spare_parts_inventory table exists
    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'spare_parts_inventory'");
    $tableExists = $checkTableStmt->fetch();
    
    if (!$tableExists) {
        return [
            'available' => false,
            'stock_quantity' => 0,
            'enough_stock' => false,
            'message' => 'Inventory table not found',
            'status' => 'Out of Stock'
        ];
    }
    
    // Get current stock from inventory
    $stockStmt = $pdo->prepare("SELECT quantity FROM spare_parts_inventory WHERE part_id = ?");
    $stockStmt->execute([$part_id]);
    $stock = $stockStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stock || floatval($stock['quantity']) == 0) {
        return [
            'available' => false,
            'stock_quantity' => 0,
            'enough_stock' => false,
            'message' => 'Out of Stock',
            'status' => 'Out of Stock'
        ];
    }
    
    $stock_quantity = floatval($stock['quantity']);
    $enough_stock = $stock_quantity >= $requested_quantity;
    
    // Determine status based on stock availability
    if ($enough_stock) {
        $status = 'Fully Available';
        $message = 'In stock';
    } else {
        $status = 'Partially Available';
        $message = 'Insufficient stock';
    }
    
    return [
        'available' => true,
        'stock_quantity' => $stock_quantity,
        'enough_stock' => $enough_stock,
        'message' => $message,
        'status' => $status
    ];
}

// Function to get delivery status for PR items
function getDeliveryStatus($pdo, $pr_item_id, $pr_id) {
    // Check if there's any PO for this PR
    $poStmt = $pdo->prepare("
        SELECT po.status as po_status, poi.status as poi_status, poi.received_quantity, poi.quantity as po_quantity
        FROM spare_part_po po
        LEFT JOIN spare_part_po_items poi ON po.id = poi.po_id
        WHERE po.pr_id = ? AND poi.pr_item_id = ?
        ORDER BY po.created_at DESC
        LIMIT 1
    ");
    $poStmt->execute([$pr_id, $pr_item_id]);
    $po_data = $poStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$po_data) {
        return [
            'status' => 'Pending',
            'delivered_quantity' => 0,
            'po_quantity' => 0
        ];
    }
    
    $po_status = $po_data['po_status'] ?? 'pending';
    $poi_status = $po_data['poi_status'] ?? 'pending';
    $delivered_quantity = floatval($po_data['received_quantity'] ?? 0);
    $po_quantity = floatval($po_data['po_quantity'] ?? 0);
    
    // Determine delivery status
    $status = 'Pending';
    if ($po_status === 'cancelled' || $po_status === 'rejected') {
        $status = 'Rejected';
    } elseif ($po_status === 'completed' || $po_status === 'confirmed' || $poi_status === 'delivered') {
        $status = 'Delivered';
    } elseif ($po_status === 'approved' || $po_status === 'confirmed') {
        $status = 'Processing';
    } elseif ($po_status === 'pending' || $po_status === 'draft') {
        $status = 'Pending';
    }
    
    return [
        'status' => $status,
        'delivered_quantity' => $delivered_quantity,
        'po_quantity' => $po_quantity
    ];
}

// Fetch PR details - ADDED JOIN FOR TECHNICIAN AND DRIVER
try {
    $prStmt = $pdo->prepare("
        SELECT pr.*, 
               u.firstname, u.middlename, u.lastname, u.suffix, u.department,
               s.supplier_name,
               v.vehicle_name, v.plate_number,
               e.equipment_name,
               emp.id as emp_code, emp.firstname as emp_firstname, emp.middlename as emp_middlename, 
               emp.lastname as emp_lastname, emp.suffix as emp_suffix, emp.position as emp_position,
               tech.firstname as tech_firstname, tech.middlename as tech_middlename, 
               tech.lastname as tech_lastname, tech.suffix as tech_suffix, tech.position as tech_position,
               driver.firstname as driver_firstname, driver.middlename as driver_middlename,  -- NEW: Driver join
               driver.lastname as driver_lastname, driver.suffix as driver_suffix, 
               driver.position as driver_position
        FROM spare_parts_pr pr
        LEFT JOIN users u ON pr.requested_by = u.id
        LEFT JOIN spare_parts_suppliers s ON pr.supplier_id = s.id
        LEFT JOIN vehicles v ON pr.vehicle_id = v.id
        LEFT JOIN equipment e ON pr.equipment_id = e.id
        LEFT JOIN employee emp ON pr.employee_id = emp.id
        LEFT JOIN employee tech ON pr.technician = tech.id
        LEFT JOIN employee driver ON pr.driver_id = driver.id  -- NEW JOIN for driver
        WHERE pr.id = ?
    ");
    $prStmt->execute([$pr_id]);
    $pr = $prStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pr) {
        $_SESSION['swal_data'] = array(
            'title' => 'Error!',
            'text' => 'Spare Parts Purchase Request not found.',
            'icon' => 'error'
        );
        header('Location: purchase_request_spare_parts.php');
        exit();
    }
    
    // Format PR dates
    $pr['request_date'] = formatDateMDY($pr['request_date']);
    
    // Check if this is an issue or issue_materials request
    $is_issue_type = in_array($pr['request_type'], ['issue', 'issue_materials']);
    
    // Check specifically if it's issue_materials
    $is_issue_materials = ($pr['request_type'] === 'issue_materials');
    
    // Format employee name for display if employee exists
    if (!empty($pr['emp_firstname'])) {
        $employee_display_name = $pr['emp_firstname'];
        if (!empty($pr['emp_middlename'])) {
            $employee_display_name .= ' ' . substr($pr['emp_middlename'], 0, 1) . '.';
        }
        $employee_display_name .= ' ' . $pr['emp_lastname'];
        if (!empty($pr['emp_suffix'])) {
            $employee_display_name .= ' ' . $pr['emp_suffix'];
        }
        // Add employee position if available
        if (!empty($pr['emp_position'])) {
            $employee_display_name .= ' (' . $pr['emp_position'] . ')';
        }
        $pr['employee_display_name'] = $employee_display_name;
    } else {
        $pr['employee_display_name'] = 'N/A';
    }
    
    // Fetch PR items - ALREADY includes delivered_quantity from spare_parts_pr_items table (pri.*)
    $itemsStmt = $pdo->prepare("
        SELECT pri.*, 
            sp.part_number, sp.part_name,
            spc.category_name,
            v.vehicle_name, v.plate_number,
            e.equipment_name
        FROM spare_parts_pr_items pri
        LEFT JOIN spare_parts sp ON pri.part_id = sp.id
        LEFT JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN vehicles v ON pri.vehicle_id = v.id
        LEFT JOIN equipment e ON pri.equipment_id = e.id
        WHERE pri.pr_id = ?
    ");
    $itemsStmt->execute([$pr_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // For issue and issue_materials requests, get detailed stock and delivery information
    if ($is_issue_type) {
        foreach ($items as &$item) {
            // Get stock availability
            $stock_info = checkStockAvailability($pdo, $item['part_id'], $item['quantity'], $item['id']);
            $item['stock_info'] = $stock_info;
            
            // Get delivery status
            $delivery_info = getDeliveryStatus($pdo, $item['id'], $pr_id);
            $item['delivery_info'] = $delivery_info;
            
            // Get delivered_quantity from spare_parts_pr_items table
            $pr_item_delivered_quantity = floatval($item['delivered_quantity'] ?? 0);
            
            // Calculate remaining needed
            $remaining_needed = $item['quantity'] - $pr_item_delivered_quantity;
            $item['remaining_needed'] = max(0, $remaining_needed);

            // Calculate fulfillable quantity based on available stock
            $fulfillable_quantity = min($remaining_needed, $stock_info['stock_quantity']);
            $item['fulfillable_quantity'] = $fulfillable_quantity;

            // Calculate unit cost based on FIFO (already done in your code)
            // Calculate total cost based on fulfillable quantity
            $item['actual_total_cost'] = $fulfillable_quantity * ($item['unit_cost'] ?? 0);
            
            // Determine overall status
            if ($stock_info['status'] === 'Out of Stock') {
                $item['overall_status'] = 'Out of Stock';
            } elseif ($pr_item_delivered_quantity >= $item['quantity']) {
                $item['overall_status'] = 'Fully Delivered';
            } elseif ($pr_item_delivered_quantity > 0) {
                $item['overall_status'] = 'Partially Delivered';
            } else {
                $item['overall_status'] = $stock_info['status'];
            }
            
            // Determine vehicle/equipment display text
            if (!empty($item['vehicle_id']) && !empty($item['vehicle_name'])) {
                $item['vehicle_equipment_display'] = htmlspecialchars($item['vehicle_name'] . ' (' . $item['plate_number'] . ')');
            } elseif (!empty($item['equipment_id']) && !empty($item['equipment_name'])) {
                $item['vehicle_equipment_display'] = htmlspecialchars($item['equipment_name']);
            } else {
                // Fallback to PR-level vehicle/equipment if item-level not set
                if (!empty($pr['vehicle_id']) && !empty($pr['vehicle_name'])) {
                    $item['vehicle_equipment_display'] = htmlspecialchars($pr['vehicle_name'] . ' (' . $pr['plate_number'] . ')');
                } elseif (!empty($pr['equipment_id']) && !empty($pr['equipment_name'])) {
                    $item['vehicle_equipment_display'] = htmlspecialchars($pr['equipment_name']);
                } else {
                    $item['vehicle_equipment_display'] = 'N/A';
                }
            }
            
            // Format part name display for issue requests
            $item['part_name_display'] = htmlspecialchars($item['part_name'] . ' (' . $item['part_number'] . ')');
            
            // ========================================================
            // FIX: Calculate FIFO cost based on batches that will be used
            // ========================================================
            if ($stock_info['available'] && $stock_info['stock_quantity'] > 0) {
                // Get all available batches with positive quantity, ordered by date (oldest first)
                $batchStmt = $pdo->prepare("
                    SELECT id, batch_number, quantity, price_per_unit, date_received 
                    FROM spare_parts_batches 
                    WHERE part_id = ? AND quantity > 0 
                    ORDER BY date_received ASC, id ASC
                ");
                $batchStmt->execute([$item['part_id']]);
                $available_batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
                $item['available_batches'] = $available_batches;
                
                // Calculate how many we need to fulfill (remaining_needed)
                $quantity_needed = $remaining_needed;
                
                if ($quantity_needed > 0 && !empty($available_batches)) {
                    $total_fifo_cost = 0;
                    $remaining_to_calculate = $quantity_needed;
                    $batches_to_use = [];
                    
                    // Simulate FIFO consumption to calculate cost
                    foreach ($available_batches as $batch) {
                        if ($remaining_to_calculate <= 0) break;
                        
                        $batch_quantity = floatval($batch['quantity']);
                        $batch_price = floatval($batch['price_per_unit']);
                        
                        $quantity_from_this_batch = min($batch_quantity, $remaining_to_calculate);
                        $cost_from_this_batch = $quantity_from_this_batch * $batch_price;
                        
                        $total_fifo_cost += $cost_from_this_batch;
                        $remaining_to_calculate -= $quantity_from_this_batch;
                        
                        // Store batch info for display
                        $batches_to_use[] = [
                            'batch_number' => $batch['batch_number'],
                            'quantity' => $quantity_from_this_batch,
                            'price' => $batch_price,
                            'date' => $batch['date_received']
                        ];
                    }
                    
                    // Calculate weighted average cost based on FIFO consumption
                    if ($quantity_needed > 0) {
                        $fifo_unit_cost = $total_fifo_cost / $quantity_needed;
                        $item['unit_cost'] = round($fifo_unit_cost, 2); // Round to 2 decimal places
                        
                        // Store detailed FIFO information for tooltip or additional display
                        $item['fifo_details'] = [
                            'total_cost' => $total_fifo_cost,
                            'quantity_needed' => $quantity_needed,
                            'batches_used' => $batches_to_use
                        ];
                    } else {
                        $item['unit_cost'] = 0;
                    }
                } else {
                    $item['unit_cost'] = 0;
                    $item['available_batches'] = [];
                }
            } else {
                $item['unit_cost'] = 0;
                $item['available_batches'] = [];
            }
        }
        unset($item); // Unset reference
    } else {
        // For stock requests, set default values and get delivery status
        foreach ($items as &$item) {
            $item['stock_info'] = [
                'available' => false,
                'stock_quantity' => 0,
                'enough_stock' => false,
                'message' => 'N/A (Stock Purchase)',
                'status' => 'N/A'
            ];
            
            // Get delivery status for stock requests
            $delivery_info = getDeliveryStatus($pdo, $item['id'], $pr_id);
            $item['delivery_info'] = $delivery_info;
            
            $item['remaining_needed'] = $item['quantity'];
            $item['overall_status'] = 'N/A';
            $item['vehicle_equipment_display'] = 'N/A (Stock Purchase)';
            $item['part_name_display'] = htmlspecialchars($item['part_name']);
            $item['available_batches'] = [];
            $item['unit_cost'] = $item['unit_cost'] ?? 0; // Keep original unit_cost for stock requests
        }
        unset($item); // Unset reference
    }
    
    // Calculate total estimated cost based on actual quantities that can be fulfilled
    $total_estimated_cost = 0;
    foreach ($items as $item) {
        if ($is_issue_type) {
            // For issue/issue_materials: use MIN(remaining_needed, current_stock)
            $fulfillable_quantity = min(
                $item['remaining_needed'] ?? $item['quantity'], 
                $item['stock_info']['stock_quantity'] ?? 0
            );
            $total_estimated_cost += ($fulfillable_quantity * ($item['unit_cost'] ?: 0));
        } else {
            // For stock requests: use full quantity
            $total_estimated_cost += ($item['quantity'] * ($item['unit_cost'] ?: 0));
        }
    }
    
    // Fetch routing history
    // First, check if the routing history table exists
    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'spare_parts_pr_routing_history'");
    $tableExists = $checkTableStmt->fetch();
    
    if ($tableExists) {
        $routingStmt = $pdo->prepare("
            SELECT prh.*, 
                   u.firstname, u.middlename, u.lastname, u.suffix, u.department, u.position
            FROM spare_parts_pr_routing_history prh
            LEFT JOIN users u ON prh.action_by = u.id
            WHERE prh.pr_id = ?
            ORDER BY prh.created_at ASC
        ");
        $routingStmt->execute([$pr_id]);
        $routing_history = $routingStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format routing history dates
        foreach ($routing_history as &$history) {
            $history['created_at'] = formatDateTimeMDY($history['created_at']);
        }
        unset($history); // Unset reference
    } else {
        $routing_history = [];
    }
    
    // Get current routing stage
    // First check if the routing table exists
    $checkRoutingTableStmt = $pdo->query("SHOW TABLES LIKE 'spare_parts_pr_routing'");
    $routingTableExists = $checkRoutingTableStmt->fetch();
    
    if ($routingTableExists) {
        $currentStageStmt = $pdo->prepare("
            SELECT stage, status FROM spare_parts_pr_routing 
            WHERE pr_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $currentStageStmt->execute([$pr_id]);
        $current_stage_result = $currentStageStmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $current_stage_result = false;
    }
    
    // Initialize current_stage with default values if not found
    $current_stage = $current_stage_result ?: ['stage' => 'requestor', 'status' => 'pending'];
    
    // Fetch existing POs for this PR from spare_part_po table
    $poStmt = $pdo->prepare("
        SELECT po.*, COUNT(poi.id) as item_count 
        FROM spare_part_po po 
        LEFT JOIN spare_part_po_items poi ON po.id = poi.po_id 
        WHERE po.pr_id = ?
        GROUP BY po.id 
        ORDER BY po.created_at DESC
    ");
    $poStmt->execute([$pr_id]);
    $existing_pos_raw = $poStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format PO dates and calculate total amount for each PO based on status
    $existing_pos = [];
    foreach ($existing_pos_raw as $po) {
        // Format dates
        $po['po_date'] = formatDateMDY($po['po_date']);
        if (!empty($po['expected_delivery'])) {
            $po['expected_delivery'] = formatDateMDY($po['expected_delivery']);
        }
        
        // Fetch PO items with received quantities
        $poItemsStmt = $pdo->prepare("
            SELECT poi.quantity, poi.received_quantity, poi.unit_cost, poi.total_cost, poi.status, poi.received_date
            FROM spare_part_po_items poi
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
    
    // Fetch PO items for view modal
    $po_items_details = [];
    if (!empty($existing_pos)) {
        foreach ($existing_pos as $po) {
            $poItemsStmt = $pdo->prepare("
                SELECT poi.*, sp.part_number, sp.part_name, spc.category_name
                FROM spare_part_po_items poi
                LEFT JOIN spare_parts sp ON poi.part_id = sp.id
                LEFT JOIN spare_parts_categories spc ON sp.category_id = spc.id
                WHERE poi.po_id = ?
            ");
            $poItemsStmt->execute([$po['id']]);
            $po_items = $poItemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format received_date for each PO item
            foreach ($po_items as &$item) {
                if (!empty($item['received_date'])) {
                    $item['received_date'] = formatDateMDY($item['received_date']);
                }
            }
            unset($item); // Unset reference
            
            $po_items_details[$po['id']] = $po_items;
        }
    }
    
    // Fetch all suppliers for the PO modal
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM spare_parts_suppliers ORDER BY supplier_name");
    $suppliersStmt->execute();
    $all_suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch PO items for Motorpool receiving stage
    $po_items_for_receiving = [];
    if ($current_stage['stage'] === 'warehouse_receiving' && !empty($existing_pos)) {
        $latest_po = $existing_pos[0]; // Get the latest PO
        $poItemsStmt = $pdo->prepare("
            SELECT poi.*, sp.part_number, sp.part_name, spc.category_name
            FROM spare_part_po_items poi
            LEFT JOIN spare_parts sp ON poi.part_id = sp.id
            LEFT JOIN spare_parts_categories spc ON sp.category_id = spc.id
            WHERE poi.po_id = ?
        ");
        $poItemsStmt->execute([$latest_po['id']]);
        $po_items_for_receiving = $poItemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format received_date for receiving items
        foreach ($po_items_for_receiving as &$item) {
            if (!empty($item['received_date'])) {
                $item['received_date'] = formatDateMDY($item['received_date']);
            }
        }
        unset($item); // Unset reference
    }
    
    // Calculate total PO amount
    $total_po_amount = 0;
    if (!empty($existing_pos)) {
        foreach ($existing_pos as $po) {
            $total_po_amount += $po['calculated_total_amount'];
        }
    }
    
    // Check if items have already been received for this PR
    if ($tableExists) {
        $items_received_check = $pdo->prepare("
            SELECT COUNT(*) as received_count 
            FROM spare_parts_pr_routing_history 
            WHERE pr_id = ? AND action = 'Items Received'
        ");
        $items_received_check->execute([$pr_id]);
        $items_received_result = $items_received_check->fetch(PDO::FETCH_ASSOC);
        $items_already_received = $items_received_result['received_count'] > 0;
    } else {
        $items_already_received = false;
    }
    
    // Check if items have been delivered for this PR (for Motorpool receiving stage)
    if ($tableExists) {
        $items_delivered_check = $pdo->prepare("
            SELECT COUNT(*) as delivered_count 
            FROM spare_parts_pr_routing_history 
            WHERE pr_id = ? AND (action = 'Delivered to Motorpool (FIFO)' OR action = 'Partial Delivery to Motorpool')
        ");
        $items_delivered_check->execute([$pr_id]);
        $items_delivered_result = $items_delivered_check->fetch(PDO::FETCH_ASSOC);
        $items_already_delivered = $items_delivered_result['delivered_count'] > 0;
    } else {
        $items_already_delivered = false;
    }
    
    // NEW: Check if items have been released for issue and issue_materials requests
    if ($is_issue_type && $tableExists) {
        // Check if there's a history of items being released for this PR
        $items_released_check = $pdo->prepare("
            SELECT COUNT(*) as released_count 
            FROM spare_parts_pr_routing_history 
            WHERE pr_id = ? AND action LIKE '%Items Released%'
        ");
        $items_released_check->execute([$pr_id]);
        $items_released_result = $items_released_check->fetch(PDO::FETCH_ASSOC);
        $items_already_released = $items_released_result['released_count'] > 0;
    } else {
        $items_already_released = false;
    }
    
    // NEW: Check if releasing has been completed for issue and issue_materials requests
    if ($is_issue_type && $tableExists) {
        // Check if there's a history of releasing being completed for this PR
        $releasing_completed_check = $pdo->prepare("
            SELECT COUNT(*) as completed_count 
            FROM spare_parts_pr_routing_history 
            WHERE pr_id = ? AND action = 'Completed Motorpool Releasing'
        ");
        $releasing_completed_check->execute([$pr_id]);
        $releasing_completed_result = $releasing_completed_check->fetch(PDO::FETCH_ASSOC);
        $releasing_already_completed = $releasing_completed_result['completed_count'] > 0;
    } else {
        $releasing_already_completed = false;
    }
    
    // NEW: Check if Withdrawal Slip has been created for issue_materials requests with fully available stock
    $withdrawal_slip_exists = false;
    $withdrawal_slip_details = [];
    if ($is_issue_materials) {
        // Check if spare_parts_withdrawal_slips table exists
        $checkWithdrawalSlipTable = $pdo->query("SHOW TABLES LIKE 'spare_parts_withdrawal_slips'");
        $withdrawalSlipTableExists = $checkWithdrawalSlipTable->fetch();
        
        if ($withdrawalSlipTableExists) {
            // Check if withdrawal slip exists for this PR
            // FIXED: Join with employee table instead of users table
            $withdrawalSlipStmt = $pdo->prepare("
                SELECT ws.*, 
                    emp.id as employee_id, emp.firstname as emp_firstname, emp.middlename as emp_middlename, 
                    emp.lastname as emp_lastname, emp.suffix as emp_suffix, emp.position as emp_position
                FROM spare_parts_withdrawal_slips ws
                LEFT JOIN employee emp ON ws.employee_id = emp.id  -- CHANGED FROM users TO employee
                WHERE ws.pr_id = ?
            ");
            $withdrawalSlipStmt->execute([$pr_id]);
            $withdrawal_slip_details = $withdrawalSlipStmt->fetch(PDO::FETCH_ASSOC);
            $withdrawal_slip_exists = $withdrawal_slip_details ? true : false;
            
            // If withdrawal slip exists, fetch its items
            if ($withdrawal_slip_exists) {
                $withdrawalSlipItemsStmt = $pdo->prepare("
                    SELECT wsi.*, sp.part_number, sp.part_name, spc.category_name
                    FROM spare_parts_withdrawal_slip_items wsi
                    LEFT JOIN spare_parts sp ON wsi.part_id = sp.id
                    LEFT JOIN spare_parts_categories spc ON sp.category_id = spc.id
                    WHERE wsi.withdrawal_slip_id = ?
                ");
                $withdrawalSlipItemsStmt->execute([$withdrawal_slip_details['id']]);
                $withdrawal_slip_items = $withdrawalSlipItemsStmt->fetchAll(PDO::FETCH_ASSOC);
                $withdrawal_slip_details['items'] = $withdrawal_slip_items;
                
                // Format employee name for display
                if (!empty($withdrawal_slip_details['emp_firstname'])) {
                    $employee_display_name = $withdrawal_slip_details['emp_firstname'];
                    if (!empty($withdrawal_slip_details['emp_middlename'])) {
                        $employee_display_name .= ' ' . substr($withdrawal_slip_details['emp_middlename'], 0, 1) . '.';
                    }
                    $employee_display_name .= ' ' . $withdrawal_slip_details['emp_lastname'];
                    if (!empty($withdrawal_slip_details['emp_suffix'])) {
                        $employee_display_name .= ' ' . $withdrawal_slip_details['emp_suffix'];
                    }
                    // Add employee position if available
                    if (!empty($withdrawal_slip_details['emp_position'])) {
                        $employee_display_name .= ' (' . $withdrawal_slip_details['emp_position'] . ')';
                    }
                    $withdrawal_slip_details['employee_display_name'] = $employee_display_name;
                } else {
                    $withdrawal_slip_details['employee_display_name'] = 'N/A';
                }
            }
        }
    }
    
    // Check if Job Order has been created for issue requests with fully available stock
    $job_order_exists = false;
    $job_order_details = [];
    if ($is_issue_type && !$is_issue_materials) {
        // Check if spare_parts_job_orders table exists
        $checkJobOrderTable = $pdo->query("SHOW TABLES LIKE 'spare_parts_job_orders'");
        $jobOrderTableExists = $checkJobOrderTable->fetch();
        
        if ($jobOrderTableExists) {
            // Check if job order exists for this PR
            $jobOrderStmt = $pdo->prepare("SELECT * FROM spare_parts_job_orders WHERE pr_id = ?");
            $jobOrderStmt->execute([$pr_id]);
            $job_order_details = $jobOrderStmt->fetch(PDO::FETCH_ASSOC);
            $job_order_exists = $job_order_details ? true : false;
            
            // If job order exists, fetch its items
            if ($job_order_exists) {
                $jobOrderItemsStmt = $pdo->prepare("
                    SELECT joi.*, sp.part_number, sp.part_name, spc.category_name
                    FROM spare_parts_job_order_items joi
                    LEFT JOIN spare_parts sp ON joi.part_id = sp.id
                    LEFT JOIN spare_parts_categories spc ON sp.category_id = spc.id
                    WHERE joi.job_order_id = ?
                ");
                $jobOrderItemsStmt->execute([$job_order_details['id']]);
                $job_order_items = $jobOrderItemsStmt->fetchAll(PDO::FETCH_ASSOC);
                $job_order_details['items'] = $job_order_items;
            }
        }
    }
    
    // Get PO status from the latest PO or set as 'Not Created'
    $po_status = 'Not Created';
    if (!empty($existing_pos)) {
        $latest_po = $existing_pos[0];
        $po_status = ucfirst($latest_po['status'] ?? 'draft');
    }
    
    // UPDATE: After PO is created, we need to update the unit costs and total costs in PR items
    // Get the latest PO to update PR item costs
    if (!empty($existing_pos)) {
        $latest_po = $existing_pos[0];
        
        // Fetch PO items with their costs
        $poItemsCostStmt = $pdo->prepare("
            SELECT poi.pr_item_id, poi.unit_cost, poi.quantity 
            FROM spare_part_po_items poi 
            WHERE poi.po_id = ?
        ");
        $poItemsCostStmt->execute([$latest_po['id']]);
        $po_items_costs = $poItemsCostStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update the items array with PO costs if available
        foreach ($items as &$item) {
            foreach ($po_items_costs as $po_item) {
                if ($item['id'] == $po_item['pr_item_id']) {
                    // Update the unit cost from PO
                    $item['unit_cost'] = $po_item['unit_cost'];
                    // Note: We don't update quantity as it might be different in PO
                    break;
                }
            }
        }
        unset($item); // Unset reference
        
        // Recalculate total estimated cost with updated PO costs
        $total_estimated_cost = 0;
        foreach ($items as $item) {
            $total_estimated_cost += ($item['quantity'] * ($item['unit_cost'] ?: 0));
        }
    }
    
} catch (PDOException $e) {
    $_SESSION['swal_data'] = array(
        'title' => 'Error!',
        'text' => 'Error fetching PR details: ' . $e->getMessage(),
        'icon' => 'error'
    );
    header('Location: purchase_request_spare_parts.php');
    exit();
}

// Check if PO already exists for this PR
$po_exists = false;
if (!empty($existing_pos)) {
    $po_exists = true;
}

// Check if user is in Motorpool department
$is_motorpool_user = ($user['department'] === 'Motorpool' && $user['accounttype'] === 'Admin');

// NEW: Check if all items are "Fully Available" for issue and issue_materials requests
$all_items_fully_available = false;
if ($is_issue_type && !empty($items)) {
    $all_items_fully_available = true;
    foreach ($items as $item) {
        if ($item['overall_status'] !== 'Fully Available') {
            $all_items_fully_available = false;
            break;
        }
    }
}

// NEW: Check if any items have "Partially Available" stock
$has_partially_available_items = false;
if ($is_issue_type && !empty($items)) {
    foreach ($items as $item) {
        if ($item['overall_status'] === 'Partially Available') {
            $has_partially_available_items = true;
            break;
        }
    }
}

// NEW: Check if any items are "Out of Stock"
$has_out_of_stock_items = false;
if ($is_issue_type && !empty($items)) {
    foreach ($items as $item) {
        if ($item['overall_status'] === 'Out of Stock') {
            $has_out_of_stock_items = true;
            break;
        }
    }
}

// Process routing actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Record the action in history
        $historyStmt = $pdo->prepare("
            INSERT INTO spare_parts_pr_routing_history (pr_id, action, remarks, action_by, stage_from, stage_to)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        // Update routing based on action
        $routingStmt = $pdo->prepare("
            INSERT INTO spare_parts_pr_routing (pr_id, stage, status, action_by, remarks)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $success_message = '';
        
        switch ($action) {
            case 'forward_to_warehouse':
                // Only requestor can forward to Motorpool
                if ($current_stage['stage'] === 'requestor' && $pr['requested_by'] == $user_id) {
                    // FIX: Allow forwarding even if some items are "Out of Stock" as long as there are items with available stock
                    if ($is_issue_type) {
                        $has_any_stock = false;
                        $out_of_stock_items = [];
                        $items_with_stock = [];
                        
                        foreach ($items as $item) {
                            $stock_info = $item['stock_info'];
                            if ($stock_info['available'] && $stock_info['stock_quantity'] > 0) {
                                $has_any_stock = true;
                                $items_with_stock[] = [
                                    'part_name' => $item['part_name'],
                                    'requested' => $item['quantity'],
                                    'available' => $stock_info['stock_quantity'],
                                    'status' => $item['overall_status']
                                ];
                            } else {
                                $out_of_stock_items[] = [
                                    'part_name' => $item['part_name'],
                                    'requested' => $item['quantity'],
                                    'available' => 0,
                                    'status' => 'Out of Stock'
                                ];
                            }
                        }
                        
                        // Allow forwarding if there's at least one item with available stock
                        // Even if some items are out of stock
                        if (!$has_any_stock) {
                            throw new Exception('Cannot forward to Motorpool. No items have available stock.');
                        }
                        
                        // Show warning but allow forwarding
                        if (!empty($out_of_stock_items)) {
                            $success_message = 'PR forwarded to Motorpool Department successfully! PR status changed to Processing. Note: Some items are out of stock and will require a Purchase Order.';
                        } else {
                            $success_message = 'PR forwarded to Motorpool Department successfully! PR status changed to Processing.';
                        }
                    }
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Forwarded to Motorpool', 
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
                    
                    // Update PR status to "processing"
                    $updatePRStmt = $pdo->prepare("UPDATE spare_parts_pr SET status = 'processing' WHERE id = ?");
                    $updatePRStmt->execute([$pr_id]);
                    
                    if (empty($success_message)) {
                        $success_message = 'PR forwarded to Motorpool Department successfully! PR status changed to Processing.';
                    }
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'approve_warehouse':
                // Only Motorpool admin can approve
                if ($current_stage['stage'] === 'warehouse' && 
                    $user['department'] === 'Motorpool' && 
                    $user['accounttype'] === 'Admin') {
                    
                    // FIX: For issue and issue_materials requests, check if there's any stock available (including partial)
                    if ($is_issue_type) {
                        $no_stock_at_all = true;
                        foreach ($items as $item) {
                            $stock_info = $item['stock_info'];
                            if ($stock_info['available'] && $stock_info['stock_quantity'] > 0) {
                                $no_stock_at_all = false;
                                break;
                            }
                        }
                        
                        if ($no_stock_at_all) {
                            throw new Exception('Cannot approve. No stock available for any requested items.');
                        }
                    }
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Approved by Motorpool', 
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
                // Only Motorpool admin can reject
                if ($current_stage['stage'] === 'warehouse' && 
                    $user['department'] === 'Motorpool' && 
                    $user['accounttype'] === 'Admin') {
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Rejected by Motorpool', 
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
                    
                    // Also update PR status in spare_parts_pr table
                    $updatePRStmt = $pdo->prepare("UPDATE spare_parts_pr SET status = 'rejected' WHERE id = ?");
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
                    
                    // Validate selected items
                    if (!isset($_POST['selected_items']) || empty($_POST['selected_items'])) {
                        throw new Exception('Please select at least one item for the purchase order.');
                    }
                    
                    $selected_items = $_POST['selected_items'];
                    $expected_delivery = $_POST['expected_delivery'] ?? null;
                    $po_remarks = $_POST['po_remarks'] ?? '';
                    
                    // Generate PO number
                    $po_number = generateSparePartsPONumber($pdo);
                    
                    // Determine supplier_id from the first selected item (or you might want to use the most common supplier)
                    $first_item_id = $selected_items[0];
                    $supplier_id = $_POST['supplier_id_' . $first_item_id] ?? $pr['supplier_id'];
                    
                    // UPDATE: Update the spare_parts_pr table with the new supplier and recalculate total_estimated_cost
                    $total_estimated_cost = 0;
                    $updatePRDetailsStmt = $pdo->prepare("
                        UPDATE spare_parts_pr 
                        SET supplier_id = ?,
                            total_estimated_cost = ? 
                        WHERE id = ?
                    ");
                    
                    // Create purchase order in spare_part_po table
                    $poStmt = $pdo->prepare("
                        INSERT INTO spare_part_po 
                        (po_number, pr_id, supplier_id, po_date, expected_delivery, status, remarks) 
                        VALUES (?, ?, ?, CURDATE(), ?, 'pending', ?)
                    ");
                    $poStmt->execute([
                        $po_number, 
                        $pr_id, 
                        $supplier_id, 
                        $expected_delivery, 
                        $po_remarks
                    ]);
                    
                    $po_id = $pdo->lastInsertId();
                    $total_amount = 0;
                    $pr_total_estimated_cost = 0;
                    
                    // Add items to spare_part_po_items
                    foreach ($selected_items as $pr_item_id) {
                        // Find the item in items array
                        $po_item = null;
                        foreach ($items as $item) {
                            if ($item['id'] == $pr_item_id) {
                                $po_item = $item;
                                break;
                            }
                        }
                        
                        if ($po_item) {
                            $quantity = $_POST['quantity_' . $pr_item_id] ?? $po_item['quantity'];
                            $unit_cost = $_POST['unit_cost_' . $pr_item_id] ?? $po_item['unit_cost'];
                            $total_cost = $quantity * $unit_cost;
                            
                            $poItemStmt = $pdo->prepare("
                                INSERT INTO spare_part_po_items 
                                (po_id, pr_item_id, part_id, quantity, unit_cost, total_cost, status) 
                                VALUES (?, ?, ?, ?, ?, ?, 'pending')
                            ");
                            $poItemStmt->execute([
                                $po_id,
                                $pr_item_id,
                                $po_item['part_id'],
                                $quantity,
                                $unit_cost,
                                $total_cost
                            ]);
                            
                            // UPDATE: Update the unit cost in the spare_parts_pr_items table
                            $updatePRItemStmt = $pdo->prepare("
                                UPDATE spare_parts_pr_items 
                                SET unit_cost = ? 
                                WHERE id = ?
                            ");
                            $updatePRItemStmt->execute([
                                $unit_cost,
                                $pr_item_id
                            ]);
                            
                            $total_amount += $total_cost;
                            
                            // Calculate total estimated cost for PR using updated unit costs
                            $pr_total_estimated_cost += ($po_item['quantity'] * $unit_cost);
                        }
                    }
                    
                    // Update PO total amount
                    $updatePOStmt = $pdo->prepare("UPDATE spare_part_po SET total_amount = ? WHERE id = ?");
                    $updatePOStmt->execute([$total_amount, $po_id]);
                    
                    // UPDATE: Update spare_parts_pr with supplier_id and total_estimated_cost
                    $updatePRDetailsStmt->execute([$supplier_id, $pr_total_estimated_cost, $pr_id]);
                    
                    // Stay in purchasing stage after creating PO
                    $historyStmt->execute([
                        $pr_id, 
                        'Purchase Order Created', 
                        "Purchase Order $po_number created with " . count($selected_items) . " items. Supplier updated, total estimated cost: ₱" . number_format($pr_total_estimated_cost, 2), 
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
                    
                    $success_message = "Purchase Order $po_number created successfully!";
                    
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'create_job_order':
                // Only purchasing admin can create Job Order for issue requests (not issue_materials) with fully available stock
                if ($current_stage['stage'] === 'purchasing' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'Purchaser' && 
                    $user['accounttype'] === 'Admin' &&
                    $is_issue_type && !$is_issue_materials) {
                    
                    // FIX: Allow Job Order creation for items with "Fully Available" OR "Partially Available" stock
                    // Check if at least some items have available stock
                    $has_available_stock = false;
                    foreach ($items as $item) {
                        if ($item['stock_info']['available'] && $item['stock_info']['stock_quantity'] > 0) {
                            $has_available_stock = true;
                            break;
                        }
                    }
                    
                    if (!$has_available_stock) {
                        throw new Exception('Cannot create Job Order. No items have available stock.');
                    }
                    
                    // Check if Job Order already exists for this PR
                    if ($job_order_exists) {
                        throw new Exception('A Job Order already exists for this PR. Only one Job Order is allowed per PR.');
                    }
                    
                    // Validate selected items
                    if (!isset($_POST['selected_items']) || empty($_POST['selected_items'])) {
                        throw new Exception('Please select at least one item for the Job Order.');
                    }
                    
                    $selected_items = $_POST['selected_items'];
                    $job_order_date = $_POST['job_order_date'] ?? null;
                    $job_order_remarks = $_POST['job_order_remarks'] ?? '';
                    
                    // Generate Job Order number
                    $job_order_number = generateJobOrderNumber($pdo);
                    
                    // Create Job Order in spare_parts_job_orders table
                    $jobOrderStmt = $pdo->prepare("
                        INSERT INTO spare_parts_job_orders 
                        (job_order_number, pr_id, technician, purpose, job_order_date, status, remarks, created_by) 
                        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
                    ");
                    $jobOrderStmt->execute([
                        $job_order_number, 
                        $pr_id, 
                        $pr['technician'] ?? 'N/A',
                        $pr['purpose'] ?? 'Issue from PR',
                        $job_order_date, 
                        $job_order_remarks,
                        $user_id
                    ]);
                    
                    $job_order_id = $pdo->lastInsertId();
                    
                    // Add items to spare_parts_job_order_items
                    foreach ($selected_items as $pr_item_id) {
                        // Find the item in items array
                        $job_item = null;
                        foreach ($items as $item) {
                            if ($item['id'] == $pr_item_id) {
                                $job_item = $item;
                                break;
                            }
                        }
                        
                        if ($job_item) {
                            // FIX: For "Partially Available" items, only allow quantity up to available stock
                            $max_quantity = $job_item['stock_info']['stock_quantity'];
                            $requested_quantity = $job_item['quantity'];
                            
                            // Use the available stock if less than requested, otherwise use requested quantity
                            $quantity = min($max_quantity, $requested_quantity);
                            
                            // If user provided a quantity, use that but cap it at available stock
                            if (isset($_POST['quantity_' . $pr_item_id])) {
                                $user_quantity = $_POST['quantity_' . $pr_item_id];
                                $quantity = min($user_quantity, $max_quantity);
                            }
                            
                            // Don't add items with zero quantity
                            if ($quantity > 0) {
                                $jobItemStmt = $pdo->prepare("
                                    INSERT INTO spare_parts_job_order_items 
                                    (job_order_id, pr_item_id, part_id, quantity, status) 
                                    VALUES (?, ?, ?, ?, 'pending')
                                ");
                                $jobItemStmt->execute([
                                    $job_order_id,
                                    $pr_item_id,
                                    $job_item['part_id'],
                                    $quantity
                                ]);
                            }
                        }
                    }
                    
                    // Stay in purchasing stage after creating Job Order
                    $historyStmt->execute([
                        $pr_id, 
                        'Job Order Created', 
                        "Job Order $job_order_number created with " . count($selected_items) . " items", 
                        $user_id,
                        'purchasing',
                        'purchasing'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'purchasing', 
                        'pending', 
                        $user_id,
                        "Job Order $job_order_number created"
                    ]);
                    
                    $success_message = "Job Order $job_order_number created successfully! You can now approve and forward to Approver.";
                    
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'create_withdrawal_slip':
                // Only purchasing admin can create Withdrawal Slip for issue_materials requests with fully available stock
                if ($current_stage['stage'] === 'purchasing' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'Purchaser' && 
                    $user['accounttype'] === 'Admin' &&
                    $is_issue_materials) {
                    
                    // FIX: Allow Withdrawal Slip creation for items with "Fully Available" OR "Partially Available" stock
                    // Check if at least some items have available stock
                    $has_available_stock = false;
                    foreach ($items as $item) {
                        if ($item['stock_info']['available'] && $item['stock_info']['stock_quantity'] > 0) {
                            $has_available_stock = true;
                            break;
                        }
                    }
                    
                    if (!$has_available_stock) {
                        throw new Exception('Cannot create Withdrawal Slip. No items have available stock.');
                    }
                    
                    // Check if Withdrawal Slip already exists for this PR
                    if ($withdrawal_slip_exists) {
                        throw new Exception('A Withdrawal Slip already exists for this PR. Only one Withdrawal Slip is allowed per PR.');
                    }
                    
                    // Validate selected items
                    if (!isset($_POST['selected_items']) || empty($_POST['selected_items'])) {
                        throw new Exception('Please select at least one item for the Withdrawal Slip.');
                    }
                    
                    $selected_items = $_POST['selected_items'];
                    $withdrawal_slip_date = $_POST['withdrawal_slip_date'] ?? null;
                    $withdrawal_slip_remarks = $_POST['withdrawal_slip_remarks'] ?? '';
                    
                    // Generate Withdrawal Slip number
                    $withdrawal_slip_number = generateWithdrawalSlipNumber($pdo);
                    
                    // Create Withdrawal Slip in spare_parts_withdrawal_slips table
                    // UPDATED: Include employee_id in the INSERT statement
                    $withdrawalSlipStmt = $pdo->prepare("
                        INSERT INTO spare_parts_withdrawal_slips 
                        (withdrawal_slip_number, pr_id, requested_by, employee_id, purpose, withdrawal_date, status, remarks, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
                    ");
                    $withdrawalSlipStmt->execute([
                        $withdrawal_slip_number, 
                        $pr_id, 
                        $pr['requested_by'] ?? $user_id,
                        $pr['employee_id'] ?? null, // ADDED: Save employee_id from PR
                        $pr['purpose'] ?? 'Materials withdrawal from PR',
                        $withdrawal_slip_date, 
                        $withdrawal_slip_remarks,
                        $user_id
                    ]);
                    
                    $withdrawal_slip_id = $pdo->lastInsertId();
                    
                    // Add items to spare_parts_withdrawal_slip_items
                    foreach ($selected_items as $pr_item_id) {
                        // Find the item in items array
                        $withdrawal_item = null;
                        foreach ($items as $item) {
                            if ($item['id'] == $pr_item_id) {
                                $withdrawal_item = $item;
                                break;
                            }
                        }
                        
                        if ($withdrawal_item) {
                            // FIX: For "Partially Available" items, only allow quantity up to available stock
                            $max_quantity = $withdrawal_item['stock_info']['stock_quantity'];
                            $requested_quantity = $withdrawal_item['quantity'];
                            
                            // Use the available stock if less than requested, otherwise use requested quantity
                            $quantity = min($max_quantity, $requested_quantity);
                            
                            // If user provided a quantity, use that but cap it at available stock
                            if (isset($_POST['quantity_' . $pr_item_id])) {
                                $user_quantity = $_POST['quantity_' . $pr_item_id];
                                $quantity = min($user_quantity, $max_quantity);
                            }
                            
                            // Don't add items with zero quantity
                            if ($quantity > 0) {
                                $withdrawalItemStmt = $pdo->prepare("
                                    INSERT INTO spare_parts_withdrawal_slip_items 
                                    (withdrawal_slip_id, pr_item_id, part_id, quantity, status) 
                                    VALUES (?, ?, ?, ?, 'pending')
                                ");
                                $withdrawalItemStmt->execute([
                                    $withdrawal_slip_id,
                                    $pr_item_id,
                                    $withdrawal_item['part_id'],
                                    $quantity
                                ]);
                            }
                        }
                    }
                    
                    // Stay in purchasing stage after creating Withdrawal Slip
                    $historyStmt->execute([
                        $pr_id, 
                        'Withdrawal Slip Created', 
                        "Withdrawal Slip $withdrawal_slip_number created with " . count($selected_items) . " items", 
                        $user_id,
                        'purchasing',
                        'purchasing'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'purchasing', 
                        'pending', 
                        $user_id,
                        "Withdrawal Slip $withdrawal_slip_number created"
                    ]);
                    
                    $success_message = "Withdrawal Slip $withdrawal_slip_number created successfully! You can now approve and forward to Approver.";
                    
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
                    
                    // FIX: For issue and issue_materials requests with any available stock, require Job Order or Withdrawal Slip before approval
                    if ($is_issue_type) {
                        // Check if there's any available stock
                        $has_available_stock = false;
                        foreach ($items as $item) {
                            if ($item['stock_info']['available'] && $item['stock_info']['stock_quantity'] > 0) {
                                $has_available_stock = true;
                                break;
                            }
                        }
                        
                        if ($has_available_stock) {
                            if ($is_issue_materials) {
                                // Check if Withdrawal Slip has been created for issue_materials with available stock
                                if (!$withdrawal_slip_exists) {
                                    throw new Exception('Cannot approve PR without creating a Withdrawal Slip first. Please create a Withdrawal Slip for the available items.');
                                }
                                
                                $historyStmt->execute([
                                    $pr_id, 
                                    'Approved by Purchasing (With Withdrawal Slip)', 
                                    $remarks, 
                                    $user_id,
                                    'purchasing',
                                    'approver'
                                ]);
                                
                                $routingStmt->execute([
                                    $pr_id, 
                                    'approver', 
                                    'pending', 
                                    $user_id,
                                    $remarks
                                ]);
                                
                                // Update Withdrawal Slip status to 'pending' (waiting for approver)
                                $updateWSStmt = $pdo->prepare("UPDATE spare_parts_withdrawal_slips SET status = 'pending' WHERE pr_id = ?");
                                $updateWSStmt->execute([$pr_id]);
                                
                                $success_message = 'PR approved (with Withdrawal Slip) and forwarded to Approver (CEO)! Withdrawal Slip status updated to Pending.';
                            } else {
                                // Check if Job Order has been created for issue with available stock
                                if (!$job_order_exists) {
                                    throw new Exception('Cannot approve PR without creating a Job Order first. Please create a Job Order for the available items.');
                                }
                                
                                $historyStmt->execute([
                                    $pr_id, 
                                    'Approved by Purchasing (With Job Order)', 
                                    $remarks, 
                                    $user_id,
                                    'purchasing',
                                    'approver'
                                ]);
                                
                                $routingStmt->execute([
                                    $pr_id, 
                                    'approver', 
                                    'pending', 
                                    $user_id,
                                    $remarks
                                ]);
                                
                                // Update Job Order status to 'pending' (waiting for approver)
                                $updateJOStmt = $pdo->prepare("UPDATE spare_parts_job_orders SET status = 'pending' WHERE pr_id = ?");
                                $updateJOStmt->execute([$pr_id]);
                                
                                $success_message = 'PR approved (with Job Order) and forwarded to Approver (CEO)! Job Order status updated to Pending.';
                            }
                        } else {
                            // No available stock - create PO instead
                            if (!$po_exists) {
                                throw new Exception('Cannot approve PR without creating a Purchase Order first.');
                            }
                            
                            $historyStmt->execute([
                                $pr_id, 
                                'Approved by Purchasing', 
                                $remarks, 
                                $user_id,
                                'purchasing',
                                'approver'
                            ]);
                            
                            $routingStmt->execute([
                                $pr_id, 
                                'approver', 
                                'pending', 
                            $user_id,
                                $remarks
                            ]);
                            
                            // Update PO status to 'pending' (waiting for approver)
                            $updatePOStmt = $pdo->prepare("UPDATE spare_part_po SET status = 'pending' WHERE pr_id = ?");
                            $updatePOStmt->execute([$pr_id]);
                            
                            $success_message = 'PR approved and forwarded to Approver (CEO)! PO status updated to Pending.';
                        }
                    } else {
                        // For stock requests, check if PO exists
                        if (!$po_exists) {
                            throw new Exception('Cannot approve PR without creating a Purchase Order first.');
                        }
                        
                        $historyStmt->execute([
                            $pr_id, 
                            'Approved by Purchasing', 
                            $remarks, 
                            $user_id,
                            'purchasing',
                            'approver'
                        ]);
                        
                        $routingStmt->execute([
                            $pr_id, 
                            'approver', 
                            'pending', 
                        $user_id,
                            $remarks
                        ]);
                        
                        // Update PO status to 'pending' (waiting for approver)
                        $updatePOStmt = $pdo->prepare("UPDATE spare_part_po SET status = 'pending' WHERE pr_id = ?");
                        $updatePOStmt->execute([$pr_id]);
                        
                        $success_message = 'PR approved and forwarded to Approver (CEO)! PO status updated to Pending.';
                    }
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
                    
                    // Update PO status to 'cancelled' if exists
                    if ($po_exists) {
                        $updatePOStmt = $pdo->prepare("UPDATE spare_part_po SET status = 'cancelled' WHERE pr_id = ?");
                        $updatePOStmt->execute([$pr_id]);
                        
                        // Update PO items status to 'cancelled'
                        $updatePOItemsStmt = $pdo->prepare("
                            UPDATE spare_part_po_items poi
                            JOIN spare_part_po po ON poi.po_id = po.id
                            SET poi.status = 'cancelled'
                            WHERE po.pr_id = ?
                        ");
                        $updatePOItemsStmt->execute([$pr_id]);
                    }
                    
                    // Update Job Order status to 'cancelled' if exists
                    if ($job_order_exists) {
                        $updateJOStmt = $pdo->prepare("UPDATE spare_parts_job_orders SET status = 'cancelled' WHERE pr_id = ?");
                        $updateJOStmt->execute([$pr_id]);
                    }
                    
                    // Update Withdrawal Slip status to 'cancelled' if exists
                    if ($withdrawal_slip_exists) {
                        $updateWSStmt = $pdo->prepare("UPDATE spare_parts_withdrawal_slips SET status = 'cancelled' WHERE pr_id = ?");
                        $updateWSStmt->execute([$pr_id]);
                    }
                    
                    // Also update PR status in spare_parts_pr table
                    $updatePRStmt = $pdo->prepare("UPDATE spare_parts_pr SET status = 'rejected' WHERE id = ?");
                    $updatePRStmt->execute([$pr_id]);
                    
                    $success_message = 'PR rejected successfully!' . ($po_exists ? ' PO and items marked as cancelled.' : '') . ($job_order_exists ? ' Job Order marked as cancelled.' : '') . ($withdrawal_slip_exists ? ' Withdrawal Slip marked as cancelled.' : '');
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'approve_approver':
                // Only CEO admin can approve
                if ($current_stage['stage'] === 'approver' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'CEO' && 
                    $user['accounttype'] === 'Admin') {
                    
                    // Determine next stage based on request type
                    if ($is_issue_type) {
                        $next_stage = 'warehouse_releasing';  // Changed for issue and issue_materials requests
                        $next_stage_label = 'Motorpool Releasing';
                    } else {
                        $next_stage = 'warehouse_receiving';
                        $next_stage_label = 'Motorpool Receiving';
                    }
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Approved by Approver', 
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
                    
                    // FIXED: Update PR status to 'approved' when Approver (CEO) approves
                    $updatePRStmt = $pdo->prepare("UPDATE spare_parts_pr SET status = 'approved' WHERE id = ?");
                    $updatePRStmt->execute([$pr_id]);
                    
                    // FIXED: Update PO status to 'approved' when Approver (CEO) approves, only if PO exists
                    if ($po_exists) {
                        $updatePOStmt = $pdo->prepare("UPDATE spare_part_po SET status = 'approved' WHERE pr_id = ?");
                        $updatePOStmt->execute([$pr_id]);
                        
                        // FIXED: Update PO items status to 'approved' when Approver (CEO) approves
                        $updatePOItemsStmt = $pdo->prepare("
                            UPDATE spare_part_po_items poi
                            JOIN spare_part_po po ON poi.po_id = po.id
                            SET poi.status = 'approved'
                            WHERE po.pr_id = ?
                        ");
                        $updatePOItemsStmt->execute([$pr_id]);
                    }
                    
                    // Update Job Order status to 'approved' if exists (for issue requests)
                    if ($job_order_exists) {
                        $updateJOStmt = $pdo->prepare("UPDATE spare_parts_job_orders SET status = 'approved' WHERE pr_id = ?");
                        $updateJOStmt->execute([$pr_id]);
                        
                        // Update Job Order items status to 'approved'
                        $updateJOItemsStmt = $pdo->prepare("
                            UPDATE spare_parts_job_order_items joi
                            JOIN spare_parts_job_orders jo ON joi.job_order_id = jo.id
                            SET joi.status = 'approved'
                            WHERE jo.pr_id = ?
                        ");
                        $updateJOItemsStmt->execute([$pr_id]);
                        
                        $success_message = "PR approved and forwarded to $next_stage_label! Job Order status updated to Approved.";
                    } 
                    // Update Withdrawal Slip status to 'approved' if exists (for issue_materials requests)
                    elseif ($withdrawal_slip_exists) {
                        $updateWSStmt = $pdo->prepare("UPDATE spare_parts_withdrawal_slips SET status = 'approved' WHERE pr_id = ?");
                        $updateWSStmt->execute([$pr_id]);
                        
                        // Update Withdrawal Slip items status to 'approved'
                        $updateWSItemsStmt = $pdo->prepare("
                            UPDATE spare_parts_withdrawal_slip_items wsi
                            JOIN spare_parts_withdrawal_slips ws ON wsi.withdrawal_slip_id = ws.id
                            SET wsi.status = 'approved'
                            WHERE ws.pr_id = ?
                        ");
                        $updateWSItemsStmt->execute([$pr_id]);
                        
                        $success_message = "PR approved and forwarded to $next_stage_label! Withdrawal Slip status updated to Approved.";
                    }
                    elseif ($po_exists) {
                        $success_message = "PR approved and forwarded to $next_stage_label! PO and items status updated to Approved.";
                    } else {
                        $success_message = "PR approved and forwarded to $next_stage_label! (No PO or Withdrawal Slip required for this request)";
                    }
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'reject_approver':
                // Only CEO admin can reject
                if ($current_stage['stage'] === 'approver' && 
                    $user['department'] === 'Admin' && 
                    $user['position'] === 'CEO' && 
                    $user['accounttype'] === 'Admin') {
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Rejected by Approver', 
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
                    
                    // Update PO status to 'cancelled' if exists
                    if ($po_exists) {
                        $updatePOStmt = $pdo->prepare("UPDATE spare_part_po SET status = 'cancelled' WHERE pr_id = ?");
                        $updatePOStmt->execute([$pr_id]);
                        
                        // Update PO items status to 'cancelled'
                        $updatePOItemsStmt = $pdo->prepare("
                            UPDATE spare_part_po_items poi
                            JOIN spare_part_po po ON poi.po_id = po.id
                            SET poi.status = 'cancelled'
                            WHERE po.pr_id = ?
                        ");
                        $updatePOItemsStmt->execute([$pr_id]);
                    }
                    
                    // Update Job Order status to 'cancelled' if exists
                    if ($job_order_exists) {
                        $updateJOStmt = $pdo->prepare("UPDATE spare_parts_job_orders SET status = 'cancelled' WHERE pr_id = ?");
                        $updateJOStmt->execute([$pr_id]);
                    }
                    
                    // Update Withdrawal Slip status to 'cancelled' if exists
                    if ($withdrawal_slip_exists) {
                        $updateWSStmt = $pdo->prepare("UPDATE spare_parts_withdrawal_slips SET status = 'cancelled' WHERE pr_id = ?");
                        $updateWSStmt->execute([$pr_id]);
                    }
                    
                    // Also update PR status in spare_parts_pr table
                    $updatePRStmt = $pdo->prepare("UPDATE spare_parts_pr SET status = 'rejected' WHERE id = ?");
                    $updatePRStmt->execute([$pr_id]);
                    
                    $success_message = 'PR rejected successfully!' . ($po_exists ? ' PO and items marked as cancelled.' : '') . ($job_order_exists ? ' Job Order marked as cancelled.' : '') . ($withdrawal_slip_exists ? ' Withdrawal Slip marked as cancelled.' : '');
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'receive_items':
                // Only warehouse admin can receive items (for stock requests)
                if ($current_stage['stage'] === 'warehouse_receiving' && 
                    $user['department'] === 'Motorpool' && 
                    $user['accounttype'] === 'Admin' &&
                    $pr['request_type'] === 'stock') {
                    
                    // Check if items have already been received
                    if ($items_already_received) {
                        throw new Exception('Items have already been received for this PR. You cannot receive items again.');
                    }
                    
                    // Process received items
                    if (isset($_POST['received_items']) && is_array($_POST['received_items'])) {
                        $latest_po = $existing_pos[0]; // Get the latest PO
                        
                        foreach ($_POST['received_items'] as $po_item_id => $received_data) {
                            $quantity_received = floatval($received_data['quantity']);
                            $unit_cost = floatval($received_data['unit_cost']);
                            
                            // FIXED: Get batch_number and received_date from form data
                            $batch_number = isset($received_data['batch_number']) ? $received_data['batch_number'] : '';
                            $received_date = isset($received_data['received_date']) ? $received_data['received_date'] : date('Y-m-d');
                            
                            if ($quantity_received > 0) {
                                // Get PO item details
                                $poItemStmt = $pdo->prepare("
                                    SELECT poi.*, sp.part_number, sp.part_name, po.po_number, po.pr_id
                                    FROM spare_part_po_items poi
                                    LEFT JOIN spare_parts sp ON poi.part_id = sp.id
                                    LEFT JOIN spare_part_po po ON poi.po_id = po.id
                                    WHERE poi.id = ?
                                ");
                                $poItemStmt->execute([$po_item_id]);
                                $po_item = $poItemStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($po_item) {
                                    // Generate batch number if not provided
                                    if (empty($batch_number)) {
                                        $batch_number = "BATCH-" . date('Ymd-His') . "-" . $po_item_id;
                                    }
                                    
                                    // FIXED: Insert into spare_parts_batches table (not spare_parts_inventory)
                                    $batchStmt = $pdo->prepare("
                                        INSERT INTO spare_parts_batches 
                                        (part_id, quantity, price_per_unit, date_received, purchase_order, batch_number, supplier_id, pr_item_id, purchase_request) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ");
                                    $batchStmt->execute([
                                        $po_item['part_id'],
                                        $quantity_received,
                                        $unit_cost,
                                        $received_date,
                                        $po_item['po_number'],
                                        $batch_number,
                                        $pr['supplier_id'],
                                        $po_item['pr_item_id'],
                                        $pr['pr_number']
                                    ]);
                                    
                                    // ========================================================
                                    // NEW: Insert into spare_parts_movements table
                                    // ========================================================
                                    $movementStmt = $pdo->prepare("
                                        INSERT INTO spare_parts_movements 
                                        (part_id, pr_item_id, quantity, price_per_unit, movement_type, movement_date, supplier_id) 
                                        VALUES (?, ?, ?, ?, 'in', ?, ?)
                                    ");
                                    $movementStmt->execute([
                                        $po_item['part_id'],
                                        $po_item['pr_item_id'],
                                        $quantity_received,
                                        $unit_cost,
                                        $received_date,
                                        $pr['supplier_id']
                                    ]);
                                    
                                    // Update spare parts inventory (if you have this table)
                                    // Check if spare_parts_inventory table exists
                                    $checkInventoryTable = $pdo->query("SHOW TABLES LIKE 'spare_parts_inventory'");
                                    if ($checkInventoryTable->fetch()) {
                                        // Check if part already exists in inventory
                                        $checkPartStmt = $pdo->prepare("SELECT id, quantity FROM spare_parts_inventory WHERE part_id = ?");
                                        $checkPartStmt->execute([$po_item['part_id']]);
                                        $existing_part = $checkPartStmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        if ($existing_part) {
                                            // Update existing inventory - FIXED: Use last_updated column instead of updated_at
                                            $updateInventoryStmt = $pdo->prepare("
                                                UPDATE spare_parts_inventory 
                                                SET quantity = quantity + ?, 
                                                    price_per_unit = ?,
                                                    last_updated = NOW()
                                                WHERE part_id = ?
                                            ");
                                            $updateInventoryStmt->execute([
                                                $quantity_received,
                                                $unit_cost,
                                                $po_item['part_id']
                                            ]);
                                        } else {
                                            // Insert new inventory record - FIXED: Use correct column names from your table structure
                                            $insertInventoryStmt = $pdo->prepare("
                                                INSERT INTO spare_parts_inventory 
                                                (part_id, quantity, price_per_unit, last_updated) 
                                                VALUES (?, ?, ?, NOW())
                                            ");
                                            $insertInventoryStmt->execute([
                                                $po_item['part_id'],
                                                $quantity_received,
                                                $unit_cost
                                            ]);
                                        }
                                    }
                                    
                                    // Update PO item status based on quantity received
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
                                    
                                    // ========================================================
                                    // FIX: Update unit_cost and total_cost in spare_part_po_items table
                                    // ========================================================
                                    // Calculate new total cost based on received quantity
                                    $new_total_cost = $new_received_total * $unit_cost;
                                    
                                    // FIX: Save received_date, update unit_cost and total_cost in spare_part_po_items table
                                    $updatePOItemStmt = $pdo->prepare("
                                        UPDATE spare_part_po_items 
                                        SET received_quantity = ?, 
                                            received_date = ?, 
                                            status = ?,
                                            unit_cost = ?,          -- FIX: Update unit_cost
                                            total_cost = ?          -- FIX: Update total_cost based on received quantity
                                        WHERE id = ?
                                    ");
                                    $updatePOItemStmt->execute([
                                        $new_received_total, 
                                        $received_date,
                                        $status,
                                        $unit_cost,                  // Update unit_cost
                                        $new_total_cost,             // Update total_cost based on received quantity
                                        $po_item_id
                                    ]);
                                }
                            }
                        }
                        
                        // Update the spare_part_po table status to 'confirmed' when items are received
                        $updatePOStmt = $pdo->prepare("
                            UPDATE spare_part_po 
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
                        
                        $success_message = 'Items received and added to batches! Unit cost and total cost updated in PO items. Received date saved in PO items.';
                    } else {
                        throw new Exception('No items received data provided.');
                    }
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;

            case 'release_items':
                // Only warehouse admin can release items (for issue and issue_materials requests)
                if ($current_stage['stage'] === 'warehouse_releasing' && 
                    $user['department'] === 'Motorpool' && 
                    $user['accounttype'] === 'Admin' &&
                    $is_issue_type) {
                    
                    // NEW: Check if items have already been released
                    if ($items_already_released) {
                        throw new Exception('Items have already been released for this PR. You cannot release items again.');
                    }
                    
                    // Process released items using FIFO
                    if (isset($_POST['released_items']) && is_array($_POST['released_items'])) {
                        
                        foreach ($_POST['released_items'] as $pr_item_id => $released_data) {
                            $quantity_released = floatval($released_data['quantity']);
                            // FIX: Get the release date from form data
                            $release_date = isset($released_data['release_date']) ? $released_data['release_date'] : date('Y-m-d');
                            
                            if ($quantity_released > 0) {
                                // Find the item in items array
                                $item = null;
                                foreach ($items as $it) {
                                    if ($it['id'] == $pr_item_id) {
                                        $item = $it;
                                        break;
                                    }
                                }
                                
                                if ($item) {
                                    // FIX: Check if the requested release quantity is available
                                    $available_stock = $item['stock_info']['stock_quantity'] ?? 0;
                                    
                                    // For "Partially Available" items, only allow release up to available stock
                                    if ($quantity_released > $available_stock) {
                                        throw new Exception('Cannot release ' . $quantity_released . ' for part: ' . $item['part_name'] . '. Only ' . $available_stock . ' available.');
                                    }
                                    
                                    // Get available batches for this part (oldest first)
                                    $batchStmt = $pdo->prepare("
                                        SELECT id, batch_number, quantity, price_per_unit, date_received 
                                        FROM spare_parts_batches 
                                        WHERE part_id = ? AND quantity > 0 
                                        ORDER BY date_received ASC, id ASC
                                    ");
                                    $batchStmt->execute([$item['part_id']]);
                                    $available_batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (empty($available_batches)) {
                                        throw new Exception('No stock available for part: ' . $item['part_name']);
                                    }
                                    
                                    $remaining_to_release = $quantity_released;
                                    $total_cost = 0;
                                    
                                    // ========================================================
                                    // FIX: Calculate weighted average cost for this release
                                    // ========================================================
                                    $total_quantity_from_batches = 0;
                                    $total_cost_from_batches = 0;
                                    
                                    // Process batches using FIFO
                                    foreach ($available_batches as $batch) {
                                        if ($remaining_to_release <= 0) break;
                                        
                                        $batch_quantity_used = min($remaining_to_release, $batch['quantity']);
                                        $batch_cost = $batch_quantity_used * $batch['price_per_unit'];
                                        $total_cost += $batch_cost;
                                        
                                        // Accumulate for weighted average calculation
                                        $total_quantity_from_batches += $batch_quantity_used;
                                        $total_cost_from_batches += $batch_cost;
                                        
                                        // Update batch quantity
                                        $updateBatchStmt = $pdo->prepare("
                                            UPDATE spare_parts_batches 
                                            SET quantity = quantity - ? 
                                            WHERE id = ?
                                        ");
                                        $updateBatchStmt->execute([$batch_quantity_used, $batch['id']]);
                                        
                                        // Record movement (out) for this batch
                                        $movementStmt = $pdo->prepare("
                                            INSERT INTO spare_parts_movements 
                                            (part_id, employee_id, pr_item_id, quantity, price_per_unit, movement_type, movement_date, supplier_id, vehicle_id, equipment_id, technician, purpose, work_order, purchase_order, purchase_request, batch_number, batch_id, notes) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                        ");
                                        
                                        // Get employee_id from PR (for issue_materials requests)
                                        $employee_id = null;
                                        if ($is_issue_materials) {
                                            // For issue_materials, use the employee_id from the PR
                                            $employee_id = $pr['employee_id'] ?? null;
                                        }
                                        
                                        // Get vehicle/equipment details from PR
                                        $vehicle_id = $pr['vehicle_id'] ?? null;
                                        $equipment_id = $pr['equipment_id'] ?? null;
                                        $technician = $pr['technician'] ?? null;
                                        $purpose = $pr['purpose'] ?? 'Issue from PR';
                                        $work_order = $pr['work_order'] ?? null;
                                        $purchase_order = null; // Not applicable for releases
                                        $purchase_request = $pr['pr_number'] ?? null;
                                        $notes = $remarks ?? 'Released from warehouse using FIFO';
                                        $supplier_id = null; // Not applicable for releases
                                        $movement_date = date('Y-m-d H:i:s'); // Current datetime
                                        
                                        $movementStmt->execute([
                                            $item['part_id'],           // part_id
                                            $employee_id,                // employee_id
                                            $pr_item_id,                 // pr_item_id
                                            $batch_quantity_used,        // quantity
                                            $batch['price_per_unit'],    // price_per_unit
                                            'out',                       // movement_type
                                            $movement_date,              // movement_date
                                            $supplier_id,                // supplier_id
                                            $vehicle_id,                 // vehicle_id
                                            $equipment_id,               // equipment_id
                                            $technician,                 // technician
                                            $purpose,                    // purpose
                                            $work_order,                 // work_order
                                            $purchase_order,             // purchase_order
                                            $purchase_request,           // purchase_request
                                            $batch['batch_number'],      // batch_number
                                            $batch['id'],                // batch_id
                                            $notes                       // notes
                                        ]);
                                        
                                        $remaining_to_release -= $batch_quantity_used;
                                    }
                                    
                                    if ($remaining_to_release > 0) {
                                        throw new Exception('Insufficient stock for part: ' . $item['part_name'] . '. Requested: ' . $quantity_released . ', Available: ' . ($quantity_released - $remaining_to_release));
                                    }
                                    
                                    // ========================================================
                                    // FIX: Calculate weighted average unit cost
                                    // ========================================================
                                    $weighted_avg_unit_cost = 0;
                                    if ($total_quantity_from_batches > 0) {
                                        $weighted_avg_unit_cost = $total_cost_from_batches / $total_quantity_from_batches;
                                    }
                                    
                                    // Update spare parts inventory
                                    $checkInventoryTable = $pdo->query("SHOW TABLES LIKE 'spare_parts_inventory'");
                                    if ($checkInventoryTable->fetch()) {
                                        // Get current inventory
                                        $invStmt = $pdo->prepare("SELECT quantity, price_per_unit FROM spare_parts_inventory WHERE part_id = ?");
                                        $invStmt->execute([$item['part_id']]);
                                        $inventory = $invStmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        if ($inventory) {
                                            $new_quantity = floatval($inventory['quantity']) - $quantity_released;
                                            if ($new_quantity < 0) $new_quantity = 0;
                                            
                                            // Calculate new weighted average price
                                            $avgPriceStmt = $pdo->prepare("
                                                SELECT 
                                                    SUM(quantity * price_per_unit) / SUM(quantity) as avg_price
                                                FROM spare_parts_batches 
                                                WHERE part_id = ? AND quantity > 0
                                            ");
                                            $avgPriceStmt->execute([$item['part_id']]);
                                            $avgPriceData = $avgPriceStmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            $weighted_avg_price = $avgPriceData['avg_price'] ?? $inventory['price_per_unit'];
                                            
                                            // Update inventory
                                            $updateInventoryStmt = $pdo->prepare("
                                                UPDATE spare_parts_inventory 
                                                SET quantity = ?, price_per_unit = ?, last_updated = NOW()
                                                WHERE part_id = ?
                                            ");
                                            $updateInventoryStmt->execute([$new_quantity, $weighted_avg_price, $item['part_id']]);
                                        }
                                    }
                                    
                                    // Update PR item delivered quantity - THIS UPDATES THE spare_parts_pr_items TABLE
                                    $updatePRItemStmt = $pdo->prepare("
                                        UPDATE spare_parts_pr_items 
                                        SET delivered_quantity = COALESCE(delivered_quantity, 0) + ?
                                        WHERE id = ?
                                    ");
                                    $updatePRItemStmt->execute([$quantity_released, $pr_item_id]);
                                    
                                    // ========================================================
                                    // FIXED: Update Job Order item quantity and status if Job Order exists (for issue requests)
                                    // ========================================================
                                    if ($job_order_exists) {
                                        // Calculate total cost for this item
                                        $total_cost_for_item = $weighted_avg_unit_cost * $quantity_released;
                                        
                                        // Get the release date from the form data
                                        $updateJOItemStmt = $pdo->prepare("
                                            UPDATE spare_parts_job_order_items 
                                            SET quantity = ?,
                                                status = 'released', 
                                                released_date = ?,
                                                unit_cost = ?,
                                                total_cost = ?
                                            WHERE pr_item_id = ? AND job_order_id = ?
                                        ");
                                        $updateJOItemStmt->execute([
                                            $quantity_released,                 // Update the quantity to match what was released
                                            $release_date, 
                                            $weighted_avg_unit_cost,            // Save the weighted average unit cost
                                            $total_cost_for_item,               // Save the total cost
                                            $pr_item_id, 
                                            $job_order_details['id']
                                        ]);
                                    }
                                    
                                    // ========================================================
                                    // FIX: Update Withdrawal Slip item quantity, status, unit_cost, and total_cost
                                    // ========================================================
                                    if ($withdrawal_slip_exists) {
                                        // Calculate total cost for this item
                                        $total_cost_for_item = $weighted_avg_unit_cost * $quantity_released;
                                        
                                        $updateWSItemStmt = $pdo->prepare("
                                            UPDATE spare_parts_withdrawal_slip_items 
                                            SET quantity = ?,
                                                status = 'released', 
                                                released_date = ?,
                                                unit_cost = ?,
                                                total_cost = ?
                                            WHERE pr_item_id = ? AND withdrawal_slip_id = ?
                                        ");
                                        $updateWSItemStmt->execute([
                                            $quantity_released,              // Update the quantity to match what was released
                                            $release_date, 
                                            $weighted_avg_unit_cost,         // Save the weighted average unit cost
                                            $total_cost_for_item,            // Save the total cost
                                            $pr_item_id, 
                                            $withdrawal_slip_details['id']
                                        ]);
                                    }
                                }
                            }
                        }
                        
                        // Record the items released action in routing history
                        $historyStmt->execute([
                            $pr_id, 
                            'Items Released (FIFO)', 
                            $remarks, 
                            $user_id,
                            'warehouse_releasing',
                            'warehouse_releasing'
                        ]);
                        
                        $routingStmt->execute([
                            $pr_id, 
                            'warehouse_releasing', 
                            'pending', 
                            $user_id,
                            $remarks
                        ]);
                        
                        // Set items_already_released flag for this PR
                        $items_already_released = true;
                        
                        // Update Job Order status to 'released' if Job Order exists (for issue requests)
                        if ($job_order_exists) {
                            $updateJOStmt = $pdo->prepare("UPDATE spare_parts_job_orders SET status = 'released' WHERE pr_id = ?");
                            $updateJOStmt->execute([$pr_id]);
                        }
                        
                        // Update Withdrawal Slip status to 'released' if Withdrawal Slip exists (for issue_materials requests)
                        if ($withdrawal_slip_exists) {
                            $updateWSStmt = $pdo->prepare("UPDATE spare_parts_withdrawal_slips SET status = 'released' WHERE pr_id = ?");
                            $updateWSStmt->execute([$pr_id]);
                        }
                        
                        $success_message = 'Items released from inventory using FIFO method!' . 
                                        ($job_order_exists ? ' Job Order quantity and status updated to Released with unit cost and total cost.' : '') . 
                                        ($withdrawal_slip_exists ? ' Withdrawal Slip quantity, status, unit cost, and total cost updated to Released.' : '');
                    } else {
                        throw new Exception('No items released data provided.');
                    }
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;
                
            case 'complete_warehouse_receiving':
                // Only warehouse admin can complete warehouse receiving (for stock requests)
                if ($current_stage['stage'] === 'warehouse_receiving' && 
                    $user['department'] === 'Motorpool' && 
                    $user['accounttype'] === 'Admin' &&
                    $pr['request_type'] === 'stock') {
                    
                    // Check if items have been received before completing
                    if (!$items_already_received) {
                        throw new Exception('Cannot complete Motorpool receiving. Items must be received first using "Confirm Items Received".');
                    }
                    
                    $historyStmt->execute([
                        $pr_id, 
                        'Completed Motorpool Receiving', 
                        $remarks, 
                        $user_id,
                        'warehouse_receiving',
                        'completed'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'completed', 
                        'completed', 
                        $user_id,
                        $remarks
                    ]);
                    
                    // Update PO status to 'completed' if exists
                    if ($po_exists) {
                        $updatePOStmt = $pdo->prepare("UPDATE spare_part_po SET status = 'confirmed' WHERE pr_id = ?");
                        $updatePOStmt->execute([$pr_id]);
                        
                        // Update PO items status to 'completed'
                        $updatePOItemsStmt = $pdo->prepare("
                            UPDATE spare_part_po_items poi
                            JOIN spare_part_po po ON poi.po_id = po.id
                            SET poi.status = 'confirmed'
                            WHERE po.pr_id = ?
                        ");
                        $updatePOItemsStmt->execute([$pr_id]);
                    }
                    
                    // Also update PR status in spare_parts_pr table
                    $updatePRStmt = $pdo->prepare("UPDATE spare_parts_pr SET status = 'completed' WHERE id = ?");
                    $updatePRStmt->execute([$pr_id]);
                    
                    $success_message = 'Motorpool Receiving completed! PR and ' . ($po_exists ? 'PO are now finalized.' : 'PR is now finalized.');
                } else {
                    throw new Exception('You are not authorized to perform this action.');
                }
                break;

            case 'complete_warehouse_releasing':
            // Only Motorpool admin can complete warehouse releasing (for issue and issue_materials requests)
            if ($current_stage['stage'] === 'warehouse_releasing' && 
                $user['department'] === 'Motorpool' && 
                $user['accounttype'] === 'Admin' &&
                $is_issue_type) {
                
                // NEW: Check if releasing has already been completed
                if ($releasing_already_completed) {
                    throw new Exception('Motorpool releasing has already been completed for this PR. You cannot complete it again.');
                }
                
                // NEW: Check if items have been released before completing
                if (!$items_already_released) {
                    throw new Exception('Cannot complete Motorpool releasing. Items must be released first using "Confirm Items Released (FIFO)".');
                }
                
                $historyStmt->execute([
                    $pr_id, 
                    'Completed Motorpool Releasing', 
                    $remarks, 
                    $user_id,
                    'warehouse_releasing',
                    'completed'
                ]);
                
                $routingStmt->execute([
                    $pr_id, 
                    'completed', 
                    'completed', 
                    $user_id,
                    $remarks
                ]);
                
                // Update Job Order status to 'confirmed' if exists (for issue requests) - CHANGED FROM 'completed' TO 'confirmed'
                if ($job_order_exists) {
                    $updateJOStmt = $pdo->prepare("UPDATE spare_parts_job_orders SET status = 'confirmed' WHERE pr_id = ?");
                    $updateJOStmt->execute([$pr_id]);
                    
                    // Update Job Order items status to 'confirmed'
                    $updateJOItemsStmt = $pdo->prepare("
                        UPDATE spare_parts_job_order_items joi
                        JOIN spare_parts_job_orders jo ON joi.job_order_id = jo.id
                        SET joi.status = 'confirmed'
                        WHERE jo.pr_id = ?
                    ");
                    $updateJOItemsStmt->execute([$pr_id]);
                }
                
                // Update Withdrawal Slip status to 'confirmed' if exists (for issue_materials requests)
                if ($withdrawal_slip_exists) {
                    $updateWSStmt = $pdo->prepare("UPDATE spare_parts_withdrawal_slips SET status = 'released' WHERE pr_id = ?");
                    $updateWSStmt->execute([$pr_id]);
                    
                    // Update Withdrawal Slip items status to 'confirmed'
                    $updateWSItemsStmt = $pdo->prepare("
                        UPDATE spare_parts_withdrawal_slip_items wsi
                        JOIN spare_parts_withdrawal_slips ws ON wsi.withdrawal_slip_id = ws.id
                        SET wsi.status = 'released'
                        WHERE ws.pr_id = ?
                    ");
                    $updateWSItemsStmt->execute([$pr_id]);
                }
                
                // Also update PR status in spare_parts_pr table
                $updatePRStmt = $pdo->prepare("UPDATE spare_parts_pr SET status = 'completed' WHERE id = ?");
                $updatePRStmt->execute([$pr_id]);
                
                $success_message = 'Motorpool Releasing completed! PR is now finalized.' . 
                                ($job_order_exists ? ' Job Order status updated to Confirmed.' : '') .
                                ($withdrawal_slip_exists ? ' Withdrawal Slip status updated to Confirmed.' : '');
            } else {
                throw new Exception('You are not authorized to perform this action.');
            }
            break;
                
            case 'update_po_quantity':
                // Only Motorpool admin can update PO quantities during Motorpool receiving stage
                if ($current_stage['stage'] === 'warehouse_receiving' && 
                    $user['department'] === 'Motorpool' && 
                    $user['accounttype'] === 'Admin') {
                    
                    $po_id = $_POST['po_id'] ?? 0;
                    $po_item_id = $_POST['po_item_id'] ?? 0;
                    $new_quantity = $_POST['new_quantity'] ?? 0;
                    
                    // Validate inputs
                    if ($po_id <= 0 || $po_item_id <= 0 || $new_quantity <= 0) {
                        throw new Exception('Invalid input parameters.');
                    }
                    
                    // Get current PO item details
                    $currentItemStmt = $pdo->prepare("
                        SELECT poi.quantity, poi.unit_cost, poi.total_cost, po.total_amount 
                        FROM spare_part_po_items poi 
                        LEFT JOIN spare_part_po po ON poi.po_id = po.id 
                        WHERE poi.id = ? AND poi.po_id = ?
                    ");
                    $currentItemStmt->execute([$po_item_id, $po_id]);
                    $current_item = $currentItemStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$current_item) {
                        throw new Exception('PO item not found.');
                    }
                    
                    $old_quantity = $current_item['quantity'];
                    $old_total_cost = $current_item['total_cost'];
                    $unit_cost = $current_item['unit_cost'];
                    $new_total_cost = $new_quantity * $unit_cost;
                    
                    // Update PO item quantity and total cost
                    $updateItemStmt = $pdo->prepare("
                        UPDATE spare_part_po_items 
                        SET quantity = ?, total_cost = ? 
                        WHERE id = ? AND po_id = ?
                    ");
                    $updateItemStmt->execute([$new_quantity, $new_total_cost, $po_item_id, $po_id]);
                    
                    // Calculate new PO total amount
                    $newTotalStmt = $pdo->prepare("
                        SELECT SUM(total_cost) as new_total_amount 
                        FROM spare_part_po_items 
                        WHERE po_id = ?
                    ");
                    $newTotalStmt->execute([$po_id]);
                    $new_total = $newTotalStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Update PO total amount
                    $updatePOStmt = $pdo->prepare("UPDATE spare_part_po SET total_amount = ? WHERE id = ?");
                    $updatePOStmt->execute([$new_total['new_total_amount'], $po_id]);
                    
                    // Record the action in history
                    $historyStmt->execute([
                        $pr_id, 
                        'PO Quantity Updated', 
                        "PO item quantity updated from $old_quantity to $new_quantity. Total cost changed from ₱" . number_format($old_total_cost, 2) . " to ₱" . number_format($new_total_cost, 2), 
                        $user_id,
                        'warehouse_receiving',
                        'warehouse_receiving'
                    ]);
                    
                    $routingStmt->execute([
                        $pr_id, 
                        'warehouse_receiving', 
                        'pending', 
                        $user_id,
                        "PO item quantity updated"
                    ]);
                    
                    $success_message = 'PO quantity updated successfully!';
                    
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
        header("Location: pr_spare_view_routing.php?id=" . $pr_id);
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_data'] = array(
            'title' => 'Error!',
            'text' => 'Failed to process action: ' . $e->getMessage(),
            'icon' => 'error'
        );
        header("Location: pr_spare_view_routing.php?id=" . $pr_id);
        exit();
    }
}

// Function to get status badge class
function getStatusBadge($status) {
    switch ($status) {
        case 'approved':
        case 'Fully Available':
        case 'Fully Delivered':
        case 'Delivered':
            return 'badge bg-success';
        case 'rejected':
        case 'Rejected':
        case 'Out of Stock':
            return 'badge bg-danger';
        case 'pending':
        case 'Pending':
            return 'badge bg-warning';
        case 'processing':
        case 'Processing':
        case 'Partially Available':
        case 'Partially Delivered':
            return 'badge bg-info';
        case 'completed':
        case 'Completed':
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

// Check for session-based SweetAlert data
$swal_data = array();
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>PR Routing - <?php echo htmlspecialchars($pr['pr_number']); ?> - OCP Construction</title>
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
        .view-only {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        .disabled-button {
            opacity: 0.6;
            cursor: not-allowed;
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
        /* Horizontal timeline styles */
        .horizontal-timeline {
            display: flex;
            justify-content: space-between;
            padding: 20px 0;
            position: relative;
            margin-bottom: 20px;
            width: 100%;
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
            flex: 1;
            text-align: center;
            position: relative;
            padding: 0 5px;
            z-index: 2;
            min-width: 0;
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
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid white;
            margin: 0 auto 10px;
            position: relative;
            z-index: 3;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: all 0.3s ease;
        }
        .timeline-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .timeline-status {
            font-size: 12px;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            height: 20px;
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
        /* Icon colors for completed stages */
        .timeline-stage.completed .fa-user {
            color: white;
        }
        .timeline-stage.completed .fa-warehouse {
            color: white;
        }
        .timeline-stage.completed .fa-shopping-cart {
            color: white;
        }
        .timeline-stage.completed .fa-user-check {
            color: white;
        }
        .timeline-stage.completed .fa-truck-loading {
            color: white;
        }
        .timeline-stage.completed .fa-truck {
            color: white;
        }
        /* Icon colors for current stage */
        .timeline-stage.current .fa-user {
            color: white;
        }
        .timeline-stage.current .fa-warehouse {
            color: white;
        }
        .timeline-stage.current .fa-shopping-cart {
            color: white;
        }
        .timeline-stage.current .fa-user-check {
            color: white;
        }
        .timeline-stage.current .fa-truck-loading {
            color: white;
        }
        .timeline-stage.current .fa-truck {
            color: white;
        }
        /* Icon colors for pending stages */
        .timeline-stage.pending .fa-user {
            color: #6c757d;
        }
        .timeline-stage.pending .fa-warehouse {
            color: #6c757d;
        }
        .timeline-stage.pending .fa-shopping-cart {
            color: #6c757d;
        }
        .timeline-stage.pending .fa-user-check {
            color: #6c757d;
        }
        .timeline-stage.pending .fa-truck-loading {
            color: #6c757d;
        }
        .timeline-stage.pending .fa-truck {
            color: #6c757d;
        }
        .supplier-info {
            color: #0d6efd;
            font-weight: 500;
        }
        .spare-parts-badge {
            background-color: #6f42c1;
        }
        .request-type-badge {
            font-size: 0.7rem;
        }
        /* Stock status styles */
        .stock-status {
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
        }
        .stock-available {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .stock-insufficient {
            background-color: #f8d7da;
            color: #842029;
        }
        .stock-na {
            background-color: #e2e3e5;
            color: #41464b;
        }
        /* Action button styles */
        .action-buttons-cell {
            white-space: nowrap;
        }
        .action-buttons-cell .btn {
            margin: 2px;
            padding: 3px 8px;
            font-size: 12px;
        }
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .timeline-label {
                font-size: 12px;
            }
            .timeline-status {
                font-size: 11px;
            }
        }
        @media (max-width: 992px) {
            .timeline-icon {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            .timeline-label {
                font-size: 11px;
            }
            .timeline-status {
                font-size: 10px;
            }
        }
        @media (max-width: 768px) {
            .horizontal-timeline {
                flex-wrap: wrap;
                justify-content: flex-start;
            }
            .horizontal-timeline::before {
                display: none;
            }
            .timeline-stage {
                flex: 0 0 20%;
                margin-bottom: 15px;
            }
            .table-responsive {
                font-size: 12px;
            }
            .action-buttons-cell .btn {
                font-size: 10px;
                padding: 2px 5px;
            }
        }
        @media (max-width: 576px) {
            .table-responsive {
                font-size: 11px;
            }
            .action-buttons-cell {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }
            .action-buttons-cell .btn {
                width: 100%;
                margin: 1px 0;
            }
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
                        if ($pr['request_type'] === 'stock'): 
                            echo 'Motorpool Spare Parts/Materials (STOCK)'; 
                        elseif ($pr['request_type'] === 'issue'): 
                            echo 'Motorpool Spare Parts (JO)'; 
                        elseif ($pr['request_type'] === 'issue_materials'): 
                            echo 'Motorpool Materials (WS)'; 
                        else: 
                            echo 'PR Routing - ' . htmlspecialchars($pr['pr_number']); 
                        endif; 
                        ?>
                    </h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item">
                            <a href="purchase_request_spare_parts.php">
                                <?php 
                                if ($pr['request_type'] === 'stock'): 
                                    echo 'Spare Parts & Materials PR';
                                elseif ($pr['request_type'] === 'issue'): 
                                    echo 'Spare Parts JO';
                                elseif ($pr['request_type'] === 'issue_materials'): 
                                    echo 'Materials WS';
                                else: 
                                    echo 'Spare Parts & Materials PR';
                                endif; 
                                ?>
                            </a>
                        </li>
                        <li class="breadcrumb-item active">
                            <?php 
                            if ($pr['request_type'] === 'stock'): 
                                echo 'PR Routing';
                            elseif ($pr['request_type'] === 'issue'): 
                                echo 'JO Routing';
                            elseif ($pr['request_type'] === 'issue_materials'): 
                                echo 'WS Routing';
                            else: 
                                echo 'PR Routing';
                            endif; 
                            ?>
                        </li>
                    </ol>

                    <!-- PR Details Card - ADD ROUTING ACTIONS BUTTON HERE -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-info-circle me-1"></i>
                            Spare Parts Purchase Request Details
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th>Request Type:</th>
                                            <td>
                                                <span class="<?php echo getRequestTypeBadge($pr['request_type'] ?? 'stock'); ?> request-type-badge">
                                                    <?php echo getRequestTypeText($pr['request_type'] ?? 'stock'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php 
                                        // Check request type to determine if we should show the number at all
                                        $show_number_line = true;
                                        $number_label = 'PR Number:';

                                        if ($pr['request_type'] === 'issue_materials'): 
                                            $show_number_line = false;
                                        elseif ($pr['request_type'] === 'issue'): 
                                            // Hide the number line completely for Issue Parts Purchase Request
                                            $show_number_line = false;
                                        endif; 

                                        if ($show_number_line): 
                                        ?>
                                        <tr>
                                            <th width="40%"><?php echo $number_label; ?></th>
                                            <td>
                                                <?php echo htmlspecialchars($pr['pr_number']); ?>
                                                <span class="<?php echo getStatusBadge($pr['status']); ?> ms-2">
                                                    <?php echo ucfirst($pr['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <!-- ADD JOB ORDER NUMBER FOR ISSUE REQUESTS -->
                                        <?php if ($pr['request_type'] === 'issue' && $job_order_exists): ?>
                                        <tr>
                                            <th>Job Order Number:</th>
                                            <td>
                                                <?php echo htmlspecialchars($job_order_details['job_order_number']); ?>
                                                <span class="badge 
                                                    <?php 
                                                    switch(strtolower($job_order_details['status'])) {
                                                        case 'draft': echo 'bg-primary'; break;
                                                        case 'pending': echo 'bg-warning'; break;
                                                        case 'approved': echo 'bg-success'; break;
                                                        case 'released': echo 'bg-success'; break;
                                                        case 'confirmed': echo 'bg-success'; break;
                                                        case 'completed': echo 'bg-success'; break;
                                                        case 'cancelled': echo 'bg-danger'; break;
                                                        default: echo 'bg-secondary';
                                                    }
                                                    ?> ms-2">
                                                    <?php echo ucfirst($job_order_details['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <!-- ADD PO NUMBER WITH STATUS -->
                                        <?php if (!empty($existing_pos)): ?>
                                        <tr>
                                            <th>PO Number:</th>
                                            <td>
                                                <?php 
                                                $po_numbers = array();
                                                foreach ($existing_pos as $po) {
                                                    $po_status_class = '';
                                                    switch($po['status']) {
                                                        case 'draft': $po_status_class = 'bg-secondary'; break;
                                                        case 'pending': $po_status_class = 'bg-warning'; break;
                                                        case 'approved': $po_status_class = 'bg-success'; break;
                                                        case 'confirmed': $po_status_class = 'bg-success'; break;
                                                        case 'delivered': $po_status_class = 'bg-success'; break;
                                                        case 'partially_received': $po_status_class = 'bg-warning'; break;
                                                        case 'cancelled': $po_status_class = 'bg-danger'; break;
                                                        case 'completed': $po_status_class = 'bg-success'; break;
                                                        default: $po_status_class = 'bg-secondary';
                                                    }
                                                    echo htmlspecialchars($po['po_number']) . ' <span class="badge ' . $po_status_class . '">' . ucfirst($po['status']) . '</span><br>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>

                                        <!-- ADD THIS BLOCK FOR WS NUMBER - Display only for Issue Materials Purchase Request -->
                                        <?php if ($pr['request_type'] === 'issue_materials'): ?>
                                            <?php if (!empty($withdrawal_slip_details) && !empty($withdrawal_slip_details['withdrawal_slip_number'])): ?>
                                            <tr>
                                                <th>WS Number:</th>
                                                <td>
                                                    <?php echo htmlspecialchars($withdrawal_slip_details['withdrawal_slip_number']); ?>
                                                    <span class="badge 
                                                        <?php 
                                                        switch(strtolower($withdrawal_slip_details['status'] ?? '')) {
                                                            case 'draft': echo 'bg-info'; break;
                                                            case 'pending': echo 'bg-warning'; break;
                                                            case 'approved': echo 'bg-success'; break;
                                                            case 'released': echo 'bg-success'; break;
                                                            case 'completed': echo 'bg-success'; break;
                                                            case 'cancelled': echo 'bg-danger'; break;
                                                            default: echo 'bg-secondary';
                                                        }
                                                        ?> ms-2">
                                                        <?php echo ucfirst($withdrawal_slip_details['status'] ?? 'Pending'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        
                                        <?php if ($is_issue_type): ?>
                                            <!-- For issue and issue_materials requests: Show technician and purpose -->
                                            
                                            <!-- Hide Technician for Issue Materials -->
                                            <!-- Display Technician for Issue Parts Purchase Request -->
                                        <?php if (!$is_issue_materials): ?>
                                        <tr>
                                            <th>Technician:</th>
                                            <td>
                                                <?php 
                                                // Check if we have technician data from the employee table
                                                if (!empty($pr['tech_firstname'])) {
                                                    $technician_name = $pr['tech_firstname'];
                                                    if (!empty($pr['tech_middlename'])) {
                                                        $technician_name .= ' ' . substr($pr['tech_middlename'], 0, 1) . '.';
                                                    }
                                                    $technician_name .= ' ' . $pr['tech_lastname'];
                                                    if (!empty($pr['tech_suffix'])) {
                                                        $technician_name .= ' ' . $pr['tech_suffix'];
                                                    }
                                                    // Optionally add position
                                                    if (!empty($pr['tech_position'])) {
                                                        $technician_name .= ' (' . $pr['tech_position'] . ')';
                                                    }
                                                    echo htmlspecialchars($technician_name);
                                                } else {
                                                    // If no technician data found, show the ID or N/A
                                                    echo htmlspecialchars($pr['technician'] ?? 'N/A');
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>

                                            <tr>
                                                <th>Purpose:</th>
                                                <td><?php echo htmlspecialchars($pr['purpose'] ?? 'N/A'); ?></td>
                                            </tr>

                                        <?php else: ?>
                                            <!-- For stock requests: Show supplier -->
                                            <tr>
                                                <th>Supplier:</th>
                                                <td><?php echo htmlspecialchars($pr['supplier_name'] ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="40%">Request Date:</th>
                                            <td><?php echo $pr['request_date']; ?></td>
                                        </tr>
                                        
                                        <!-- Show different labels based on request type -->
                                        <?php if ($is_issue_materials): ?>
                                            <!-- For Issue Materials Request: Show "Prepared By:" -->
                                            <tr>
                                                <th>Prepared By:</th>
                                                <td><?php echo formatUserName($pr); ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <!-- For other request types: Show "Requested By:" -->
                                            <tr>
                                                <th>Requested By:</th>
                                                <td><?php echo formatUserName($pr); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        
                                        <!-- Hide Department for Issue Materials -->
                                        <?php if (!$is_issue_materials): ?>
                                        <tr>
                                            <th>Department:</th>
                                            <td><?php echo htmlspecialchars($pr['department'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <?php endif; ?>

                                        <?php if ($is_issue_materials): ?>
                                        <!-- For issue_materials requests: Show Employee -->
                                        <tr>
                                            <th>Issue to Employee:</th>
                                            <td>
                                                <?php 
                                                // FIXED: Check if employee_display_name exists and is not empty
                                                if (!empty($pr['employee_display_name']) && $pr['employee_display_name'] !== 'N/A') {
                                                    echo htmlspecialchars($pr['employee_display_name']);
                                                } else {
                                                    // Try to format the employee name from the fetched data
                                                    if (!empty($pr['emp_firstname'])) {
                                                        $employee_name = $pr['emp_firstname'];
                                                        if (!empty($pr['emp_middlename'])) {
                                                            $employee_name .= ' ' . substr($pr['emp_middlename'], 0, 1) . '.';
                                                        }
                                                        $employee_name .= ' ' . $pr['emp_lastname'];
                                                        if (!empty($pr['emp_suffix'])) {
                                                            $employee_name .= ' ' . $pr['emp_suffix'];
                                                        }
                                                        echo htmlspecialchars($employee_name);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>

                                         <!-- Add Vehicle/Equipment for Issue Parts Purchase Request -->
                                        <?php if ($pr['request_type'] === 'issue'): ?>
                                        <tr>
                                            <th>Vehicle/Equipment:</th>
                                            <td>
                                                <?php 
                                                if (!empty($pr['vehicle_name']) && !empty($pr['plate_number'])) {
                                                    echo htmlspecialchars($pr['vehicle_name'] . ' (' . $pr['plate_number'] . ')');
                                                } elseif (!empty($pr['equipment_name'])) {
                                                    echo htmlspecialchars($pr['equipment_name']);
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>

                                        <!-- NEW: Add Driver field here -->
                                        <?php if ($pr['request_type'] === 'issue'): ?>
                                        <tr>
                                            <th>Driver:</th>
                                            <td>
                                                <?php 
                                                // Check if we have driver data from the employee table
                                                if (!empty($pr['driver_firstname'])) {
                                                    $driver_name = $pr['driver_firstname'];
                                                    if (!empty($pr['driver_middlename'])) {
                                                        $driver_name .= ' ' . substr($pr['driver_middlename'], 0, 1) . '.';
                                                    }
                                                    $driver_name .= ' ' . $pr['driver_lastname'];
                                                    if (!empty($pr['driver_suffix'])) {
                                                        $driver_name .= ' ' . $pr['driver_suffix'];
                                                    }
                                                    // Optionally add position
                                                    if (!empty($pr['driver_position'])) {
                                                        $driver_name .= ' (' . $pr['driver_position'] . ')';
                                                    }
                                                    echo htmlspecialchars($driver_name);
                                                } else {
                                                    // If no driver data found, show the ID or N/A
                                                    if (!empty($pr['driver_id'])) {
                                                        // Try to fetch driver name directly if not in join
                                                        echo 'Driver ID: ' . htmlspecialchars($pr['driver_id']);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <?php if (!$is_issue_type): ?>
                                        <tr>
                                            <th>Total Estimated Cost:</th>
                                            <td>₱<?php echo number_format($total_estimated_cost, 2); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Horizontal Routing Progress -->
                            <div class="mt-4">
                                <h6 class="mb-3"><i class="fas fa-project-diagram me-1"></i> Routing Progress</h6>
                                <div class="horizontal-timeline">
                                    <?php
                                    // Define stages based on request type
                                    $stages = [];
                                    if ($is_issue_type) {
                                        $stages = [
                                            'requestor' => ['label' => 'Requestor', 'icon' => 'fa-user'],
                                            'warehouse' => ['label' => 'Motorpool', 'icon' => 'fa-warehouse'],
                                            'purchasing' => ['label' => 'Purchasing', 'icon' => 'fa-shopping-cart'],
                                            'approver' => ['label' => 'Approver (CEO)', 'icon' => 'fa-user-check'],
                                            'warehouse_releasing' => ['label' => 'Motorpool Releasing', 'icon' => 'fa-truck']
                                        ];
                                    } else {
                                        $stages = [
                                            'requestor' => ['label' => 'Requestor', 'icon' => 'fa-user'],
                                            'warehouse' => ['label' => 'Motorpool', 'icon' => 'fa-warehouse'],
                                            'purchasing' => ['label' => 'Purchasing', 'icon' => 'fa-shopping-cart'],
                                            'approver' => ['label' => 'Approver (CEO)', 'icon' => 'fa-user-check'],
                                            'warehouse_receiving' => ['label' => 'Motorpool Receiving', 'icon' => 'fa-truck-loading']
                                        ];
                                    }
                                    
                                    // Get all completed stages from routing history
                                    $completed_stages = [];
                                    foreach ($routing_history as $history) {
                                        if (in_array($history['action'], [
                                            'Forwarded to Motorpool', 
                                            'Approved by Motorpool', 
                                            'Approved by Purchasing', 
                                            'Approved by Approver',
                                            'Purchase Order Created',
                                            'Job Order Created',
                                            'Withdrawal Slip Created',
                                            'Items Received',
                                            'Items Released',
                                            'Completed Motorpool Receiving',
                                            'Completed Motorpool Releasing'
                                        ])) {
                                            $completed_stages[] = $history['stage_to'];
                                        }
                                        
                                        // Special case: if an action was taken from a stage, that stage is considered visited
                                        if ($history['stage_from'] && !in_array($history['stage_from'], $completed_stages)) {
                                            $completed_stages[] = $history['stage_from'];
                                        }
                                    }
                                    
                                    // Special case for requestor - always considered completed if we're beyond that stage
                                    if ($current_stage['stage'] !== 'requestor') {
                                        $completed_stages[] = 'requestor';
                                    }
                                    
                                    // Display all stages horizontally
                                    foreach ($stages as $stage => $stageInfo):
                                        $isCurrent = $current_stage['stage'] === $stage;
                                        $isCompleted = in_array($stage, $completed_stages);
                                        
                                        $stageClass = 'pending';
                                        if ($isCurrent && !in_array($current_stage['stage'], ['completed', 'rejected'])) {
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
                                            <?php if ($isCurrent && !in_array($current_stage['stage'], ['completed', 'rejected'])): ?>
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
                                
                                <!-- Show final status if PR is completed or rejected -->
                                <?php if (in_array($current_stage['stage'], ['completed', 'rejected'])): ?>
                                <div class="final-status <?php echo $current_stage['stage']; ?>">
                                    <h5 class="mb-2">
                                        <i class="fas <?php echo $current_stage['stage'] === 'completed' ? 'fa-check-circle' : 'fa-times-circle'; ?> me-2"></i>
                                        <?php 
                                        if ($current_stage['stage'] === 'completed') {
                                            if ($pr['request_type'] === 'issue') {
                                                echo 'JOB ORDER CONFIRMED';
                                            } elseif ($pr['request_type'] === 'issue_materials') {
                                                echo 'WITHDRAWAL SLIP COMPLETED';
                                            } else {
                                                echo 'PR COMPLETED';
                                            }
                                        } else {
                                            echo 'PR REJECTED';
                                        }
                                        ?>
                                    </h5>
                                    <p class="mb-0">
                                        <?php 
                                        if ($current_stage['stage'] === 'completed') {
                                            if ($pr['request_type'] === 'issue') {
                                                echo 'The job order for this spare parts request has been confirmed and processed successfully.';
                                            } elseif ($pr['request_type'] === 'issue_materials') {
                                                echo 'The withdrawal slip for this materials request has been confirmed and processed successfully.';
                                            } else {
                                                echo 'This spare parts purchase request has been successfully processed and completed.';
                                            }
                                        } else {
                                            echo 'This spare parts purchase request has been rejected during the routing process.';
                                        }
                                        ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Job Order (for issue requests with fully available stock) -->
                    <?php if ($is_issue_type && !$is_issue_materials && $job_order_exists): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-tools me-1"></i>
                            Existing Job Order
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Job Order Number</th>
                                            <th>Date Created</th>
                                            <th>Technician</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                            <th>Items</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php echo htmlspecialchars($job_order_details['job_order_number']); ?></td>
                                            <td><?php echo formatDateMDY($job_order_details['job_order_date']); ?></td>
                                            <td>
                                                <?php 
                                                // Check if we have technician data from the PR query
                                                if (!empty($pr['tech_firstname'])) {
                                                    $technician_name = $pr['tech_firstname'];
                                                    if (!empty($pr['tech_middlename'])) {
                                                        $technician_name .= ' ' . substr($pr['tech_middlename'], 0, 1) . '.';
                                                    }
                                                    $technician_name .= ' ' . $pr['tech_lastname'];
                                                    if (!empty($pr['tech_suffix'])) {
                                                        $technician_name .= ' ' . $pr['tech_suffix'];
                                                    }
                                                    echo htmlspecialchars($technician_name);
                                                } else {
                                                    // Fallback to the stored technician value if the detailed data isn't available
                                                    echo htmlspecialchars($job_order_details['technician'] ?? 'N/A');
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($job_order_details['purpose']); ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch(strtolower($job_order_details['status'])) {
                                                        case 'draft': echo 'bg-primary'; break;
                                                        case 'pending': echo 'bg-warning'; break;
                                                        case 'approved': echo 'bg-success'; break;
                                                        case 'released': echo 'bg-success'; break;
                                                        case 'confirmed': echo 'bg-success'; break;
                                                        case 'completed': echo 'bg-success'; break;
                                                        case 'cancelled': echo 'bg-danger'; break;
                                                        default: echo 'bg-secondary';
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst($job_order_details['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo count($job_order_details['items'] ?? []); ?> item/s</td>
                                            <td>
                                                <!-- View Job Order Button -->
                                                <button type="button" class="btn btn-sm btn-primary view-job-order-btn" 
                                                        data-job-order-id="<?php echo $job_order_details['id']; ?>"
                                                        data-job-order-number="<?php echo htmlspecialchars($job_order_details['job_order_number']); ?>"
                                                        data-job-order-date="<?php echo formatDateMDY($job_order_details['job_order_date']); ?>"
                                                        data-technician="<?php echo htmlspecialchars($job_order_details['technician']); ?>"
                                                        data-purpose="<?php echo htmlspecialchars($job_order_details['purpose']); ?>"
                                                        data-job-order-status="<?php echo ucfirst($job_order_details['status']); ?>"
                                                        data-job-order-remarks="<?php echo htmlspecialchars($job_order_details['remarks'] ?? ''); ?>">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Existing Withdrawal Slip (for issue_materials requests with fully available stock) -->
                    <?php if ($is_issue_materials && $withdrawal_slip_exists): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-file-invoice me-1"></i>
                            Existing Withdrawal Slip
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Withdrawal Slip Number</th>
                                            <th>Date Created</th>
                                            <th>Prepared By</th>
                                            <th>Issue to Employee</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                            <th>Items</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php echo htmlspecialchars($withdrawal_slip_details['withdrawal_slip_number']); ?></td>
                                            <td><?php echo formatDateMDY($withdrawal_slip_details['withdrawal_date']); ?></td>
                                            <td><?php echo formatUserName($pr); ?></td>
                                            <td><?php echo htmlspecialchars($withdrawal_slip_details['employee_display_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($withdrawal_slip_details['purpose']); ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch(strtolower($withdrawal_slip_details['status'])) {
                                                        case 'draft': echo 'bg-info'; break;
                                                        case 'pending': echo 'bg-warning'; break;
                                                        case 'approved': echo 'bg-success'; break;
                                                        case 'released': echo 'bg-success'; break;
                                                        case 'completed': echo 'bg-success'; break;
                                                        case 'cancelled': echo 'bg-danger'; break;
                                                        default: echo 'bg-secondary';
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst($withdrawal_slip_details['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo count($withdrawal_slip_details['items'] ?? []); ?> item/s</td>
                                            <td>
                                                <!-- View Withdrawal Slip Button -->
                                                <button type="button" class="btn btn-sm btn-primary view-withdrawal-slip-btn" 
                                                        data-withdrawal-slip-id="<?php echo $withdrawal_slip_details['id']; ?>"
                                                        data-withdrawal-slip-number="<?php echo htmlspecialchars($withdrawal_slip_details['withdrawal_slip_number']); ?>"
                                                        data-withdrawal-slip-date="<?php echo formatDateMDY($withdrawal_slip_details['withdrawal_date']); ?>"
                                                        data-requested-by="<?php echo formatUserName($pr); ?>"
                                                        data-employee="<?php echo htmlspecialchars($withdrawal_slip_details['employee_display_name'] ?? 'N/A'); ?>"
                                                        data-purpose="<?php echo htmlspecialchars($withdrawal_slip_details['purpose']); ?>"
                                                        data-withdrawal-slip-status="<?php echo ucfirst($withdrawal_slip_details['status']); ?>"
                                                        data-withdrawal-slip-remarks="<?php echo htmlspecialchars($withdrawal_slip_details['remarks'] ?? ''); ?>">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Existing Purchase Orders -->
                    <?php if (!empty($existing_pos)): ?>
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
                                            <th>Status</th>
                                            <th>Items</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($existing_pos as $po): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                                            <td><?php echo $po['po_date']; ?></td>
                                            <td><?php echo $po['expected_delivery'] ?? 'Not set'; ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch($po['status']) {
                                                        case 'draft': echo 'bg-secondary'; break;
                                                        case 'pending': echo 'bg-warning'; break;
                                                        case 'approved': echo 'bg-success'; break;
                                                        case 'confirmed': echo 'bg-success'; break;
                                                        case 'delivered': echo 'bg-success'; break;
                                                        case 'partially_received': echo 'bg-warning'; break;
                                                        case 'cancelled': echo 'bg-danger'; break;
                                                        case 'completed': echo 'bg-success'; break;
                                                        default: echo 'bg-secondary';
                                                    }
                                                    ?>
                                                ">
                                                    <?php echo ucfirst($po['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $po['item_count']; ?> item/s</td>
                                            <td>
                                                <!-- View PO Button -->
                                                <button type="button" class="btn btn-sm btn-primary view-po-btn" 
                                                        data-po-id="<?php echo $po['id']; ?>"
                                                        data-po-number="<?php echo htmlspecialchars($po['po_number']); ?>"
                                                        data-po-date="<?php echo htmlspecialchars($po['po_date']); ?>"
                                                        data-expected-delivery="<?php echo htmlspecialchars($po['expected_delivery'] ?? 'Not set'); ?>"
                                                        data-total-amount="<?php echo number_format($po['calculated_total_amount'], 2); ?>"
                                                        data-po-status="<?php echo ucfirst($po['status']); ?>"
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

                    <!-- Motorpool Processing (Releasing for Issue and Issue Materials Requests) -->
                    <?php if ($current_stage['stage'] === 'warehouse_releasing' && $is_issue_type && !empty($items)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-truck me-1"></i>
                            Items to Release (<?php echo $pr['request_type'] === 'issue' ? 'Issue Parts Request' : 'Issue Materials Request'; ?>) - FIFO Method
                        </div>
                        <div class="card-body">
                            <!-- Show alert if items already processed -->
                            <?php if ($items_already_released): ?>
                            <div class="received-alert">
                                <h6><i class="fas fa-check-circle me-2"></i>Items Already Released</h6>
                                <p class="mb-0">The items for this <?php echo $pr['request_type'] === 'issue' ? 'issue' : 'issue materials'; ?> request have already been released using FIFO method.</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($is_motorpool_user): ?>
                            <!-- Motorpool User - Show form with FIFO batch details -->
                            <?php if (!$items_already_released): ?>
                            <form method="POST" action="" id="warehouseProcessingForm">
                                <input type="hidden" name="action" value="release_items">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Part Name</th>
                                                <th>Category</th>
                                                <th>Quantity Requested</th>
                                                <th>Available Stock</th>
                                                <th>Quantity Released</th>
                                                <th>Status</th>
                                                <th>Release Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item): 
                                                // Get delivered_quantity from spare_parts_pr_items table
                                                $delivered_quantity = floatval($item['delivered_quantity'] ?? 0);
                                                $requested_quantity = floatval($item['quantity']);
                                                $remaining_quantity = $requested_quantity - $delivered_quantity;
                                                $available_stock = $item['stock_info']['stock_quantity'] ?? 0;
                                                
                                                // Determine status based on delivered_quantity vs requested_quantity
                                                $status_class = 'status-pending';
                                                $status_text = 'Pending';
                                                
                                                if ($delivered_quantity > 0) {
                                                    if ($delivered_quantity >= $requested_quantity) {
                                                        $status_class = 'status-delivered';
                                                        $status_text = 'Fully Released';
                                                    } else {
                                                        $status_class = 'status-partial';
                                                        $status_text = 'Partially Released';
                                                    }
                                                } elseif ($available_stock >= $remaining_quantity) {
                                                    $status_class = 'status-delivered';
                                                    $status_text = 'Ready for Release';
                                                } elseif ($available_stock > 0) {
                                                    $status_class = 'status-partial';
                                                    $status_text = 'Partially Available';
                                                } else {
                                                    $status_class = 'status-pending';
                                                    $status_text = 'Out of Stock';
                                                }
                                                
                                                // FIX: For "Partially Available" items, only allow release up to available stock
                                                $max_release = min($remaining_quantity, $available_stock);
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['part_name_display']); ?></td>
                                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                                <!-- FIXED: Display Quantity Requested with .00 -->
                                                <td><?php echo number_format($requested_quantity, 2); ?></td>
                                                <td><?php echo number_format($available_stock, 2); ?></td>
                                                <td>
                                                    <?php if ($remaining_quantity > 0 && $available_stock > 0): ?>
                                                        <input type="number" 
                                                            name="released_items[<?php echo $item['id']; ?>][quantity]" 
                                                            value="<?php echo $max_release; ?>"
                                                            min="1" max="<?php echo $max_release; ?>"
                                                            step="0.01"
                                                            class="form-control form-control-sm" required>
                                                    <?php elseif ($remaining_quantity > 0): ?>
                                                        <span class="text-danger">Out of stock</span>
                                                    <?php else: ?>
                                                        <span class="text-success"><?php echo number_format($delivered_quantity, 2); ?> released</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="receiving-status <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($remaining_quantity > 0 && $available_stock > 0): ?>
                                                        <input type="date" 
                                                            name="released_items[<?php echo $item['id']; ?>][release_date]" 
                                                            value="<?php echo date('Y-m-d'); ?>"
                                                            class="form-control form-control-sm date-input" 
                                                            required>
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
                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                </div>
                                
                                <div class="mt-3">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>FIFO Method:</strong> Items will be released from the oldest batches first. The system will automatically track which batches are used.
                                        <br><strong>Note:</strong> For "Partially Available" items, only the available stock can be released.
                                    </div>
                                    
                                    <!-- Show release button only if items haven't been released yet -->
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check me-1"></i> Confirm Items Released (FIFO)
                                    </button>
                                </div>
                            </form>
                            <?php else: ?>
                            <!-- READ-ONLY VIEW AFTER ITEMS ARE RELEASED - UPDATED TO SHOW released_date FROM WITHDRAWAL SLIP ITEMS -->
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Part Name</th>
                                            <th>Category</th>
                                            <th>Quantity Requested</th>
                                            <th>Quantity Released</th>
                                            <th>Remaining</th>
                                            <th>Status</th>
                                            <th>Release Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): 
                                            // Get delivered_quantity from spare_parts_pr_items table
                                            $delivered_quantity = floatval($item['delivered_quantity'] ?? 0);
                                            $requested_quantity = floatval($item['quantity']);
                                            $remaining_quantity = $requested_quantity - $delivered_quantity;
                                            
                                            // Determine status based on delivered_quantity vs requested_quantity
                                            $status_class = 'status-pending';
                                            $status_text = 'Pending';
                                            
                                            if ($delivered_quantity > 0) {
                                                if ($delivered_quantity >= $requested_quantity) {
                                                    $status_class = 'status-delivered';
                                                    $status_text = 'Fully Released';
                                                } else {
                                                    $status_class = 'status-partial';
                                                    $status_text = 'Partially Released';
                                                }
                                            } else {
                                                $status_class = 'status-pending';
                                                $status_text = 'Not Released';
                                            }
                                            
                                            // ========================================================
                                            // FIX: Get release date from withdrawal slip items
                                            // ========================================================
                                            $release_date = '';
                                            
                                            // For issue_materials requests, get date from withdrawal slip items
                                            if ($is_issue_materials && $withdrawal_slip_exists && !empty($withdrawal_slip_details['items'])) {
                                                foreach ($withdrawal_slip_details['items'] as $ws_item) {
                                                    if ($ws_item['pr_item_id'] == $item['id'] && !empty($ws_item['released_date'])) {
                                                        // Format the date if it's in Y-m-d format
                                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ws_item['released_date'])) {
                                                            $date = new DateTime($ws_item['released_date']);
                                                            $release_date = $date->format('m-d-Y');
                                                        } else {
                                                            // If it's already formatted or in another format, use as is
                                                            $release_date = $ws_item['released_date'];
                                                        }
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            // For issue requests (JO), get date from job order items
                                            if (!$is_issue_materials && $job_order_exists && !empty($job_order_details['items'])) {
                                                foreach ($job_order_details['items'] as $jo_item) {
                                                    if ($jo_item['pr_item_id'] == $item['id'] && !empty($jo_item['released_date'])) {
                                                        // Format the date if it's in Y-m-d format
                                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $jo_item['released_date'])) {
                                                            $date = new DateTime($jo_item['released_date']);
                                                            $release_date = $date->format('m-d-Y');
                                                        } else {
                                                            // If it's already formatted or in another format, use as is
                                                            $release_date = $jo_item['released_date'];
                                                        }
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            // Fallback to legacy method if no date found in items
                                            if (empty($release_date)) {
                                                if ($job_order_exists && !empty($job_order_details['items'])) {
                                                    foreach ($job_order_details['items'] as $jo_item) {
                                                        if ($jo_item['pr_item_id'] == $item['id'] && !empty($jo_item['released_date'])) {
                                                            $release_date = formatDateMDY($jo_item['released_date']);
                                                            break;
                                                        }
                                                    }
                                                }
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['part_name_display']); ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <!-- FIXED: Display Quantity Requested with .00 -->
                                            <td><?php echo number_format($requested_quantity, 2); ?></td>
                                            <td><?php echo number_format($delivered_quantity, 2); ?></td>
                                            <td><?php echo number_format($remaining_quantity, 2); ?></td>
                                            <td>
                                                <span class="receiving-status <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($release_date)): ?>
                                                    <span class="text-muted"><?php echo $release_date; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <!-- Non-Motorpool User - Show read-only view (before items are released) -->
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Part Name</th>
                                            <th>Category</th>
                                            <th>Quantity Requested</th>
                                            <th>Available Stock</th>
                                            <th>Quantity Released</th>
                                            <th>Status</th>
                                            <th>Release Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): 
                                            // Get delivered_quantity from spare_parts_pr_items table
                                            $delivered_quantity = floatval($item['delivered_quantity'] ?? 0);
                                            $requested_quantity = floatval($item['quantity']);
                                            $remaining_quantity = $requested_quantity - $delivered_quantity;
                                            $available_stock = $item['stock_info']['stock_quantity'] ?? 0;
                                            
                                            // Determine status based on delivered_quantity vs requested_quantity
                                            $status_class = 'status-pending';
                                            $status_text = 'Pending';
                                            
                                            if ($delivered_quantity > 0) {
                                                if ($delivered_quantity >= $requested_quantity) {
                                                    $status_class = 'status-delivered';
                                                    $status_text = 'Fully Released';
                                                } else {
                                                    $status_class = 'status-partial';
                                                    $status_text = 'Partially Released';
                                                }
                                            } elseif ($available_stock >= $remaining_quantity) {
                                                $status_class = 'status-delivered';
                                                $status_text = 'Ready for Release';
                                            } elseif ($available_stock > 0) {
                                                $status_class = 'status-partial';
                                                $status_text = 'Partially Available';
                                            } else {
                                                $status_class = 'status-pending';
                                                $status_text = 'Out of Stock';
                                            }
                                            
                                            // Get release date from withdrawal slip items if available (for read-only view)
                                            $release_date = '';
                                            if ($is_issue_materials && $withdrawal_slip_exists && !empty($withdrawal_slip_details['items'])) {
                                                foreach ($withdrawal_slip_details['items'] as $ws_item) {
                                                    if ($ws_item['pr_item_id'] == $item['id'] && !empty($ws_item['released_date'])) {
                                                        // Format the date if it's in Y-m-d format
                                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ws_item['released_date'])) {
                                                            $date = new DateTime($ws_item['released_date']);
                                                            $release_date = $date->format('m-d-Y');
                                                        } else {
                                                            // If it's already formatted or in another format, use as is
                                                            $release_date = $ws_item['released_date'];
                                                        }
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            // For issue requests (JO), get date from job order items
                                            if (!$is_issue_materials && $job_order_exists && !empty($job_order_details['items'])) {
                                                foreach ($job_order_details['items'] as $jo_item) {
                                                    if ($jo_item['pr_item_id'] == $item['id'] && !empty($jo_item['released_date'])) {
                                                        // Format the date if it's in Y-m-d format
                                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $jo_item['released_date'])) {
                                                            $date = new DateTime($jo_item['released_date']);
                                                            $release_date = $date->format('m-d-Y');
                                                        } else {
                                                            // If it's already formatted or in another format, use as is
                                                            $release_date = $jo_item['released_date'];
                                                        }
                                                        break;
                                                    }
                                                }
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['part_name_display']); ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <!-- FIXED: Display Quantity Requested with .00 -->
                                            <td><?php echo number_format($requested_quantity, 2); ?></td>
                                            <td><?php echo number_format($available_stock, 2); ?></td>
                                            <td>
                                                <?php if ($remaining_quantity > 0 && $available_stock > 0): ?>
                                                    <input type="number" 
                                                        value="<?php echo min($remaining_quantity, $available_stock); ?>"
                                                        class="form-control form-control-sm view-only" 
                                                        readonly>
                                                <?php elseif ($remaining_quantity > 0): ?>
                                                    <span class="text-danger">Out of stock</span>
                                                <?php else: ?>
                                                    <span class="text-success"><?php echo number_format($delivered_quantity, 2); ?> released</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="receiving-status <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($release_date)): ?>
                                                    <span class="text-muted"><?php echo $release_date; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Only Motorpool Department users can release items using FIFO method. You are viewing this information in read-only mode.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Motorpool Processing (Receiving for Stock Requests) -->
                    <?php if ($current_stage['stage'] === 'warehouse_receiving' && $pr['request_type'] === 'stock' && !empty($items)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-truck-loading me-1"></i>
                            Items to Receive (Purchase Order)
                        </div>
                        <div class="card-body">
                            <!-- Show alert if items already processed -->
                            <?php if ($items_already_received): ?>
                            <div class="received-alert">
                                <h6><i class="fas fa-check-circle me-2"></i>Items Already Received</h6>
                                <p class="mb-0">The items for this purchase order have already been received. You cannot receive them again.</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($is_motorpool_user): ?>
                            <!-- Motorpool User - Show form with inputs -->
                            <?php if (!$items_already_received): ?>
                            <form method="POST" action="" id="warehouseProcessingForm">
                                <input type="hidden" name="action" value="receive_items">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Part Name</th>
                                                <th>Category</th>
                                                <th>Quantity Ordered</th>
                                                <th>Quantity Received</th>
                                                <th>Remaining</th>
                                                <th>Unit Cost</th>
                                                <th>Total Cost</th>
                                                <th>Status</th>
                                                <th>Quantity to Receive</th>
                                                <th>Received Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            if (!empty($po_items_for_receiving)) {
                                                $items_to_process = $po_items_for_receiving;
                                            } else {
                                                $items_to_process = $items;
                                            }
                                            
                                            foreach ($items_to_process as $item): 
                                                $received_quantity = $item['received_quantity'] ?? 0;
                                                $remaining_quantity = $item['quantity'] - $received_quantity;
                                                $status = $item['status'] ?? 'pending';
                                                $received_date = $item['received_date'] ?? '';
                                                $item_id = isset($item['pr_item_id']) ? $item['id'] : $item['id'];
                                                $is_po_item = isset($item['pr_item_id']);
                                                
                                                // Format part name with part number in parentheses
                                                $part_name_display = htmlspecialchars($item['part_name']);
                                                if (!empty($item['part_number'])) {
                                                    $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                                }
                                                
                                                // Determine status class
                                                $status_class = 'status-pending';
                                                $status_text = 'Pending';
                                                if ($status === 'partially_received') {
                                                    $status_class = 'status-partial';
                                                    $status_text = 'Partially Received';
                                                } elseif ($status === 'delivered') {
                                                    $status_class = 'status-delivered';
                                                    $status_text = 'Delivered';
                                                }
                                                
                                                // Calculate total cost for display - FIXED
                                                $receiving_quantity = $remaining_quantity > 0 ? min($remaining_quantity, $item['quantity']) : 0;
                                                $total_cost_display = $item['unit_cost'] * $receiving_quantity;
                                            ?>
                                            <tr>
                                                <td><?php echo $part_name_display; ?></td>
                                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                                <td><?php echo number_format($item['quantity'], 2); ?></td>
                                                <td><?php echo number_format($received_quantity, 2); ?></td>
                                                <td><?php echo number_format($remaining_quantity, 2); ?></td>
                                                <td>₱<?php echo number_format($item['unit_cost'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_cost_display, 2); ?></td>
                                                <td>
                                                    <span class="receiving-status <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($remaining_quantity > 0): ?>
                                                        <input type="number" 
                                                            name="received_items[<?php echo $item_id; ?>][quantity]" 
                                                            value="<?php echo $remaining_quantity; ?>"
                                                            min="0" max="<?php echo $remaining_quantity; ?>"
                                                            step="0.01"
                                                            class="form-control form-control-sm" required>
                                                        <!-- Hidden batch number field - still required for backend -->
                                                        <input type="hidden" 
                                                            name="received_items[<?php echo $item_id; ?>][batch_number]" 
                                                            value="BATCH-<?php echo date('m-d-Y-His'); ?>-<?php echo $item_id; ?>">
                                                    <?php else: ?>
                                                        <span class="text-success">Fully Received</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($remaining_quantity > 0): ?>
                                                        <!-- Show existing received_date if available, otherwise use today's date -->
                                                        <?php 
                                                        $today_formatted = date('Y-m-d');
                                                        $default_date = !empty($received_date) ? $received_date : $today_formatted;
                                                        ?>
                                                        <input type="date" 
                                                            name="received_items[<?php echo $item_id; ?>][received_date]" 
                                                            value="<?php echo $default_date; ?>"
                                                            class="form-control form-control-sm date-input" 
                                                            required>
                                                        <input type="hidden" 
                                                            name="received_items[<?php echo $item_id; ?>][unit_cost]" 
                                                            value="<?php echo $item['unit_cost']; ?>">
                                                    <?php else: ?>
                                                        <!-- Show the received_date if item is fully received -->
                                                        <?php if (!empty($received_date)): ?>
                                                            <span class="text-muted"><?php echo formatDateMDY($received_date); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                </div>
                                
                                <div class="mt-3">
                                    <!-- Disable button if items already processed -->
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check me-1"></i> Confirm Items Received
                                    </button>
                                </div>
                            </form>
                            <?php else: ?>
                            
                            <!-- Non-Motorpool User - Show read-only view -->
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Part Name</th>
                                            <th>Category</th>
                                            <th>Quantity Ordered</th>
                                            <th>Quantity Received</th>
                                            <th>Remaining</th>
                                            <th>Unit Cost</th>
                                            <th>Total Cost</th>
                                            <th>Status</th>
                                            <th>Quantity to Receive</th>
                                            <th>Received Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // IMPORTANT: Use $po_items_for_receiving which already has formatted dates
                                        // Only fall back to $items if $po_items_for_receiving is empty
                                        if (!empty($po_items_for_receiving)) {
                                            $items_to_process = $po_items_for_receiving;
                                        } else {
                                            $items_to_process = $items;
                                        }
                                        
                                        foreach ($items_to_process as $item): 
                                            $received_quantity = floatval($item['received_quantity'] ?? 0);
                                            $remaining_quantity = floatval($item['quantity']) - $received_quantity;
                                            $status = $item['status'] ?? 'pending';
                                            $received_date = $item['received_date'] ?? '';
                                            
                                            // Format part name with part number in parentheses
                                            $part_name_display = htmlspecialchars($item['part_name']);
                                            if (!empty($item['part_number'])) {
                                                $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                            }
                                            
                                            // Determine status class
                                            $status_class = 'status-pending';
                                            $status_text = 'Pending';
                                            if ($status === 'partially_received') {
                                                $status_class = 'status-partial';
                                                $status_text = 'Partially Received';
                                            } elseif ($status === 'delivered' || $status === 'confirmed') {
                                                $status_class = 'status-delivered';
                                                $status_text = 'Delivered';
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo $part_name_display; ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo number_format($item['quantity'], 2); ?></td>
                                            <td><?php echo number_format($received_quantity, 2); ?></td>
                                            <td><?php echo number_format($remaining_quantity, 2); ?></td>
                                            <td>₱<?php echo number_format($item['unit_cost'] ?? 0, 2); ?></td>
                                            <td>₱<?php echo number_format(($item['unit_cost'] ?? 0) * $received_quantity, 2); ?></td>
                                            <td>
                                                <span class="receiving-status <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($remaining_quantity > 0): ?>
                                                    <input type="number" 
                                                        value="<?php echo $remaining_quantity; ?>"
                                                        class="form-control form-control-sm view-only" 
                                                        readonly>
                                                <?php else: ?>
                                                    <span class="text-success">Fully Received</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                // Check if we have a valid received date
                                                if (!empty($received_date) && $received_date != '0000-00-00' && $received_date != '1970-01-01'): 
                                                    // If it's already formatted (mm-dd-yyyy), display as is
                                                    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $received_date)) {
                                                        echo '<span class="text-muted">' . $received_date . '</span>';
                                                    } else {
                                                        // Otherwise format it
                                                        try {
                                                            $date = new DateTime($received_date);
                                                            echo '<span class="text-muted">' . $date->format('m-d-Y') . '</span>';
                                                        } catch (Exception $e) {
                                                            echo '<span class="text-muted">-</span>';
                                                        }
                                                    }
                                                else: 
                                                ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <!-- Non-Motorpool User - Show read-only view -->
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Part Name</th>
                                            <th>Category</th>
                                            <th>Quantity Requested</th>
                                            <th>Quantity Received</th>
                                            <th>Remaining</th>
                                            <th>Unit Cost</th>
                                            <th>Total Cost</th>
                                            <th>Status</th>
                                            <th>Quantity to Receive</th>
                                            <th>Received Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        if (!empty($po_items_for_receiving)) {
                                            $items_to_process = $po_items_for_receiving;
                                        } else {
                                            $items_to_process = $items;
                                        }
                                        
                                        foreach ($items_to_process as $item): 
                                            $received_quantity = $item['received_quantity'] ?? 0;
                                            $remaining_quantity = $item['quantity'] - $received_quantity;
                                            $status = $item['status'] ?? 'pending';
                                            $received_date = $item['received_date'] ?? '';
                                            
                                            // Format part name with part number in parentheses
                                            $part_name_display = htmlspecialchars($item['part_name']);
                                            if (!empty($item['part_number'])) {
                                                $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                            }
                                            
                                            // Determine status class
                                            $status_class = 'status-pending';
                                            $status_text = 'Pending';
                                            if ($status === 'partially_received') {
                                                $status_class = 'status-partial';
                                                $status_text = 'Partially Received';
                                            } elseif ($status === 'delivered') {
                                                $status_class = 'status-delivered';
                                                $status_text = 'Delivered';
                                            }
                                        ?>
                                        <tr>
                                            <!-- REMOVED: Part Number column -->
                                            <td><?php echo $part_name_display; ?></td> <!-- Now shows "Motolite 2sm (0012)" -->
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo number_format($item['quantity'], 2); ?></td>
                                            <td><?php echo number_format($received_quantity, 2); ?></td>
                                            <td><?php echo number_format($remaining_quantity, 2); ?></td>
                                            <td>₱<?php echo number_format($item['unit_cost'], 2); ?></td>
                                            <td>₱<?php echo number_format($item['unit_cost'] * ($received_quantity), 2); ?></td>
                                            <td>
                                                <span class="receiving-status <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($remaining_quantity > 0): ?>
                                                    <input type="number" 
                                                        value="<?php echo $remaining_quantity; ?>"
                                                        class="form-control form-control-sm view-only" 
                                                        readonly>
                                                <?php else: ?>
                                                    <span class="text-success">Fully Received</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($received_date)): ?>
                                                    <span class="text-muted"><?php echo formatDateMDY($received_date); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Only Motorpool Department users can receive items. You are viewing this information in read-only mode.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Items Requested -->
                        <div class="col-lg-12">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-list me-1"></i>
                                    <!-- CHANGED: Show "Materials Requested" for Issue Materials Request -->
                                    <?php if ($is_issue_materials): ?>
                                        Materials Requested
                                    <?php else: ?>
                                        Spare Parts Requested
                                    <?php endif; ?>
                                    <!-- ADD ROUTING ACTIONS BUTTON -->
                                    <?php if (!in_array($current_stage['stage'], ['completed', 'rejected'])): ?>
                                    <button type="button" class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#routingActionsModal">
                                        <i class="fas fa-route me-1"></i> Routing Actions
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    
                                    <?php if ($all_items_fully_available && $is_issue_type): ?>
                                        <div class="mt-2 alert alert-success">
                                            <i class="fas fa-check-circle me-1"></i>
                                            <strong>All items are fully available in stock!</strong> A <?php echo $is_issue_materials ? 'Withdrawal Slip' : 'Job Order'; ?> must be created before approval.
                                        </div>
                                    <?php elseif ($has_partially_available_items && $is_issue_type): ?>
                                        <div class="mt-2 alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <strong>Some items are partially available in stock.</strong> Only available stock can be released. A <?php echo $is_issue_materials ? 'Withdrawal Slip' : 'Job Order'; ?> must be created before approval.
                                        </div>
                                    <?php elseif ($has_out_of_stock_items && $is_issue_type): ?>
                                        <div class="mt-2 alert alert-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <strong>Some items are out of stock.</strong> These items will require a Purchase Order.
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($is_issue_type): ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong><?php echo $pr['request_type'] === 'issue' ? 'Issue Parts Request' : 'Issue Materials Request'; ?></strong> - Showing detailed stock and delivery information for each requested <?php echo $is_issue_materials ? 'material' : 'part'; ?>.
                                        <?php if ($has_out_of_stock_items): ?>
                                            <br><strong>Note:</strong> Items marked as "Out of Stock" can still be forwarded to Motorpool. They will require a Purchase Order.
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <?php if ($is_issue_type): ?>
                                                    <!-- For Issue and Issue Materials requests -->
                                                    <?php if (!$is_issue_materials): ?>
                                                    <!-- Only show Vehicle/Equipment for Issue Parts Request, NOT for Issue Materials -->
                                                    <th>Vehicle/Equipment</th>
                                                    <?php endif; ?>
                                                    <th>Part Name</th> <!-- This will display "Part Name (Part Number)" -->
                                                    <th>Category</th>
                                                    <th>Current Stock</th>
                                                    <th>Quantity Requested</th>
                                                    <th>Quantity Released</th>
                                                    <th>Remaining Needed</th>
                                                    <th>Unit Cost</th>
                                                    <th>Total Cost</th>
                                                    <th>Stock Status</th>
                                                    <th>Release Status</th>
                                                    <?php else: ?>
                                                    <!-- For Stock requests - UPDATED: Added Quantity Received column -->
                                                    <th>Part Name</th>
                                                    <th>Category</th>
                                                    <th>Quantity Ordered</th>
                                                    <th>Quantity Received</th>
                                                    <th>Unit Cost</th>
                                                    <th>Total Cost</th>
                                                    <th>Delivery Status</th>
                                                    <th>Received Date</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($items as $item): 
                                                    // Calculate total cost based on remaining needed for issue types, 
                                                    // or full quantity for stock types
                                                    if ($is_issue_type) {
                                                        // For issue/issue_materials: use remaining needed
                                                        $quantity_for_cost = $item['remaining_needed'] ?? $item['quantity'];
                                                    } else {
                                                        // For stock requests: use full quantity
                                                        $quantity_for_cost = $item['quantity'];
                                                    }
                                                    $item_total_cost = $quantity_for_cost * ($item['unit_cost'] ?: 0);
                                                    $stock_info = $item['stock_info'];
                                                    $delivery_info = $item['delivery_info'];
                                                    $remaining_needed = $item['remaining_needed'];
                                                    $overall_status = $item['overall_status'];
                                                    // Get delivered_quantity from spare_parts_pr_items table
                                                    $delivered_quantity = floatval($item['delivered_quantity'] ?? 0);
                                                    
                                                    // Format part name with part number in parentheses
                                                    $part_name_display = htmlspecialchars($item['part_name']);
                                                    if (!empty($item['part_number'])) {
                                                        $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                                    }
                                                    
                                                    // FIXED: Determine release status with icons and proper colors
                                                    $release_status = 'Pending';
                                                    $release_icon = 'fa-clock';
                                                    $release_color_class = 'text-warning';
                                                    
                                                    if ($delivered_quantity > 0) {
                                                        if ($delivered_quantity >= $item['quantity']) {
                                                            $release_status = 'Released';
                                                            $release_icon = 'fa-check-circle';
                                                            $release_color_class = 'text-success';
                                                        } else {
                                                            $release_status = 'Partially Released';
                                                            $release_icon = 'fa-clock';
                                                            $release_color_class = 'text-warning';
                                                        }
                                                    } elseif ($overall_status === 'Out of Stock') {
                                                        $release_status = 'Rejected';
                                                        $release_icon = 'fa-times-circle';
                                                        $release_color_class = 'text-danger';
                                                    } elseif ($overall_status === 'Partially Available') {
                                                        $release_status = 'Pending';
                                                        $release_icon = 'fa-clock';
                                                        $release_color_class = 'text-warning';
                                                    } elseif ($overall_status === 'Fully Available') {
                                                        $release_status = 'Pending';
                                                        $release_icon = 'fa-clock';
                                                        $release_color_class = 'text-warning';
                                                    }
                                                ?>
                                                <tr>
                                                    <?php if ($is_issue_type): ?>
                                                    <!-- For Issue and Issue Materials requests -->
                                                    <?php if (!$is_issue_materials): ?>
                                                    <!-- Only show Vehicle/Equipment for Issue Parts Request -->
                                                    <td><?php echo htmlspecialchars($item['vehicle_equipment_display']); ?></td>
                                                    <?php endif; ?>
                                                    <!-- Part Name with number -->
                                                    <td><?php echo $part_name_display; ?></td>
                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                                    <!-- Current Stock -->
                                                    <td><?php echo $stock_info['available'] ? number_format($stock_info['stock_quantity'], 2) : '0.00'; ?></td>
                                                    <!-- Quantity Requested -->
                                                    <td><?php echo number_format($item['quantity'], 2); ?></td>
                                                    <!-- Quantity Released -->
                                                    <td><?php echo number_format($delivered_quantity, 2); ?></td>
                                                    <!-- Remaining Needed -->
                                                    <td><?php echo number_format($remaining_needed, 2); ?></td>
                                                    <!-- Unit Cost -->
                                                    <td>
                                                        <?php 
                                                        if ($is_issue_type): 
                                                            // For issue/issue_materials: show unit cost based on FIFO calculation
                                                            $unit_cost_display = $item['unit_cost'] ?? 0;
                                                            echo '₱' . number_format($unit_cost_display, 2);
                                                        else: 
                                                            // For stock requests
                                                            echo '₱' . number_format($item['unit_cost'] ?? 0, 2);
                                                        endif; 
                                                        ?>
                                                    </td>
                                                    <!-- Total Cost -->
                                                    <td>
                                                        <?php 
                                                        if ($is_issue_type): 
                                                            // Calculate actual fulfillable quantity = MIN(Remaining Needed, Current Stock)
                                                            $fulfillable_quantity = min($remaining_needed, $stock_info['stock_quantity']);
                                                            
                                                            // Calculate total cost based on fulfillable quantity
                                                            $actual_total_cost = $fulfillable_quantity * ($item['unit_cost'] ?? 0);
                                                            
                                                            // Show the total cost with the fulfillable quantity
                                                            echo '₱' . number_format($actual_total_cost, 2);
                                                        else: 
                                                            // For stock requests, show the original total cost
                                                            echo '₱' . number_format($item_total_cost, 2);
                                                        endif; 
                                                        ?>
                                                    </td>
                                                    <!-- Stock Status -->
                                                    <td class="<?php 
                                                        // Determine text color class based on stock availability and release status
                                                        if ($delivered_quantity >= $item['quantity']) {
                                                            echo 'text-success';
                                                            $stock_text = 'Fully Released';
                                                        } elseif ($delivered_quantity > 0) {
                                                            echo 'text-warning';
                                                            $stock_text = 'Partially Released';
                                                        } elseif ($overall_status === 'Out of Stock') {
                                                            echo 'text-danger';
                                                            $stock_text = 'Out of Stock';
                                                        } elseif ($stock_info['available'] && $stock_info['stock_quantity'] > 0) {
                                                            echo 'text-success';
                                                            $stock_text = 'Available';
                                                        } else {
                                                            echo 'text-warning';
                                                            $stock_text = 'Pending';
                                                        }
                                                    ?>">
                                                        <?php echo $stock_text; ?>
                                                    </td>
                                                    <!-- FIXED: Release Status with icon only (no badge) -->
                                                    <td class="<?php echo $release_color_class; ?>">
                                                        <i class="fas <?php echo $release_icon; ?> me-1"></i>
                                                        <?php echo $release_status; ?>
                                                    </td>
                                                    <?php else: ?>
                                                    <!-- For Stock requests - UPDATED: Added Quantity Received column -->
                                                    <td><?php echo $part_name_display; ?></td>
                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                                    <td><?php echo number_format($item['quantity'], 2); ?></td>
                                                    <td>
                                                        <?php 
                                                        // Get received quantity from delivery_info or directly from item
                                                        $received_qty = 0;
                                                        if (isset($item['delivered_quantity']) && $item['delivered_quantity'] > 0) {
                                                            $received_qty = $item['delivered_quantity'];
                                                        } elseif (isset($delivery_info['delivered_quantity']) && $delivery_info['delivered_quantity'] > 0) {
                                                            $received_qty = $delivery_info['delivered_quantity'];
                                                        }
                                                        echo number_format($received_qty, 2);
                                                        ?>
                                                    </td>
                                                    <td>₱<?php echo number_format($item['unit_cost'] ?? 0, 2); ?></td>
                                                    <td>₱<?php echo number_format($item_total_cost, 2); ?></td>
                                                    <!-- Delivery Status with icons -->
                                                    <td>
                                                        <?php 
                                                        // Get the raw delivery status
                                                        $delivery_status = $delivery_info['status'] ?? 'Pending';
                                                        
                                                        // Map the status to the desired display values
                                                        $display_status = 'Pending';
                                                        $delivery_icon = 'fa-clock';
                                                        $delivery_color_class = 'text-warning';
                                                        
                                                        // Get delivered quantity to help determine status
                                                        $received_qty = 0;
                                                        if (isset($item['delivered_quantity']) && $item['delivered_quantity'] > 0) {
                                                            $received_qty = $item['delivered_quantity'];
                                                        } elseif (isset($delivery_info['delivered_quantity']) && $delivery_info['delivered_quantity'] > 0) {
                                                            $received_qty = $delivery_info['delivered_quantity'];
                                                        }
                                                        
                                                        // Determine the correct status based on delivered quantity and raw status
                                                        if ($delivery_status === 'Rejected' || $delivery_status === 'cancelled') {
                                                            $display_status = 'Rejected';
                                                            $delivery_icon = 'fa-times-circle';
                                                            $delivery_color_class = 'text-danger';
                                                        } elseif ($received_qty >= $item['quantity']) {
                                                            $display_status = 'Fully Received';
                                                            $delivery_icon = 'fa-check-circle';
                                                            $delivery_color_class = 'text-success';
                                                        } elseif ($received_qty > 0) {
                                                            $display_status = 'Partially Received';
                                                            $delivery_icon = 'fa-check-circle';
                                                            $delivery_color_class = 'text-warning';
                                                        } else {
                                                            // Check if there's a PO but no items received yet
                                                            if (!empty($existing_pos)) {
                                                                // PO exists but no items received
                                                                $display_status = 'Pending';
                                                                $delivery_icon = 'fa-clock';
                                                                $delivery_color_class = 'text-warning';
                                                            } else {
                                                                // No PO created yet
                                                                $display_status = 'Pending';
                                                                $delivery_icon = 'fa-clock';
                                                                $delivery_color_class = 'text-warning';
                                                            }
                                                        }
                                                        ?>
                                                        <span class="<?php echo $delivery_color_class; ?>">
                                                            <i class="fas <?php echo $delivery_icon; ?> me-1"></i>
                                                            <?php echo htmlspecialchars($display_status); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-muted">
                                                        <?php 
                                                        // Get the received date with better handling
                                                        $received_date_display = '—';
                                                        
                                                        // Check if we have a received date in the item
                                                        if (!empty($item['received_date']) && $item['received_date'] != '0000-00-00') {
                                                            $received_date_display = formatDateMDY($item['received_date']);
                                                        } 
                                                        // Try to get it from PO items
                                                        else if (!empty($existing_pos)) {
                                                            foreach ($existing_pos as $po) {
                                                                if (isset($po_items_details[$po['id']])) {
                                                                    foreach ($po_items_details[$po['id']] as $po_item) {
                                                                        if ($po_item['pr_item_id'] == $item['id'] && 
                                                                            !empty($po_item['received_date']) && 
                                                                            $po_item['received_date'] != '0000-00-00') {
                                                                            $received_date_display = formatDateMDY($po_item['received_date']);
                                                                            break 2;
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        
                                                        echo $received_date_display;
                                                        ?>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ROUTING ACTIONS MODAL (NEW) -->
                        <div class="modal fade" id="routingActionsModal" tabindex="-1" aria-labelledby="routingActionsModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="routingActionsModalLabel">
                                            <i class="fas fa-route me-2"></i>
                                            Routing Actions - <?php echo htmlspecialchars($pr['pr_number']); ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <!-- CURRENT STAGE INDICATOR -->
                                        <div class="alert alert-info mb-4">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Current Stage:</strong> 
                                            <?php 
                                            switch($current_stage['stage']) {
                                                case 'requestor': echo 'Requestor'; break;
                                                case 'warehouse': echo 'Motorpool Department'; break;
                                                case 'purchasing': echo 'Purchasing Department'; break;
                                                case 'approver': echo 'Approver (CEO)'; break;
                                                case 'warehouse_receiving': echo 'Motorpool Receiving'; break;
                                                case 'warehouse_releasing': echo 'Motorpool Releasing'; break;
                                                case 'completed': echo 'Completed'; break;
                                                case 'rejected': echo 'Rejected'; break;
                                                default: echo ucfirst($current_stage['stage']);
                                            }
                                            ?>
                                        </div>

                                        <!-- ROUTING ACTION FORMS (MOVED FROM THE COLUMN) -->
                                        <?php if ($current_stage['stage'] === 'requestor' && $pr['requested_by'] == $user_id): ?>
                                            <!-- Requestor Actions - Single Button -->
                                            <form method="POST" action="">
                                                <input type="hidden" name="action" value="forward_to_warehouse">
                                                
                                                <?php if ($is_issue_type): ?>
                                                <?php
                                                // FIX: Check only for "Out of Stock" items, allow "Partially Available"
                                                $has_any_stock = false;
                                                $out_of_stock_items = [];
                                                $items_with_stock = [];
                                                foreach ($items as $item) {
                                                    $stock_info = $item['stock_info'];
                                                    if ($stock_info['available'] && $stock_info['stock_quantity'] > 0) {
                                                        $has_any_stock = true;
                                                        $items_with_stock[] = [
                                                            'part_name' => $item['part_name'],
                                                            'requested' => $item['quantity'],
                                                            'available' => $stock_info['stock_quantity'],
                                                            'status' => $item['overall_status']
                                                        ];
                                                    } else {
                                                        $out_of_stock_items[] = [
                                                            'part_name' => $item['part_name'],
                                                            'requested' => $item['quantity'],
                                                            'available' => 0,
                                                            'status' => 'Out of Stock'
                                                        ];
                                                    }
                                                }
                                                
                                                // Allow forwarding if there's at least one item with available stock
                                                // Even if some items are out of stock
                                                if (!$has_any_stock): ?>
                                                <div class="alert alert-danger mb-3">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <strong>Cannot Forward:</strong> No items have available stock. All items are out of stock.
                                                </div>
                                                <?php elseif (!empty($out_of_stock_items)): ?>
                                                <div class="alert alert-warning mb-3">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <strong>Note:</strong> <?php echo count($out_of_stock_items); ?> item(s) are out of stock and will require a Purchase Order.
                                                    <div class="mt-2">
                                                        <strong>Items with stock available:</strong>
                                                        <ul class="mb-0">
                                                            <?php foreach ($items_with_stock as $item_with_stock): ?>
                                                            <li><?php echo htmlspecialchars($item_with_stock['part_name']); ?>: <?php echo $item_with_stock['available']; ?> available (<?php echo $item_with_stock['status']; ?>)</li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <div class="mb-3">
                                                    <label for="remarks" class="form-label">Remarks (Optional)</label>
                                                    <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                                                </div>
                                                <div class="d-grid">
                                                    <button type="submit" class="btn btn-primary" <?php echo ($is_issue_type && !$has_any_stock) ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-forward me-1"></i> Forward to Motorpool Department
                                                    </button>
                                                </div>
                                            </form>
                                            
                                        <?php elseif ($current_stage['stage'] === 'warehouse' && 
                                                $user['department'] === 'Motorpool' && 
                                                $user['accounttype'] === 'Admin'): ?>
                                            <!-- Motorpool Actions - Two Buttons -->
                                            <form method="POST" action="" id="warehouseActionsForm">
                                                <?php if ($is_issue_type): ?>
                                                <?php
                                                // FIX: Check if there's any available stock
                                                $no_stock_at_all = true;
                                                foreach ($items as $item) {
                                                    $stock_info = $item['stock_info'];
                                                    if ($stock_info['available'] && $stock_info['stock_quantity'] > 0) {
                                                        $no_stock_at_all = false;
                                                        break;
                                                    }
                                                }
                                                
                                                if ($no_stock_at_all): ?>
                                                <div class="alert alert-danger mb-3">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <strong>Cannot Approve:</strong> No stock available for any requested items.
                                                </div>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <div class="mb-3">
                                                    <label for="remarks" class="form-label">Remarks</label>
                                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6">
                                                        <button type="submit" name="action" value="approve_warehouse" class="btn btn-success w-100" <?php echo ($is_issue_type && $no_stock_at_all) ? 'disabled' : ''; ?>>
                                                            <i class="fas fa-check me-1"></i> Approve
                                                        </button>
                                                    </div>
                                                    <div class="col-6">
                                                        <button type="submit" name="action" value="reject_warehouse" class="btn btn-danger w-100">
                                                            <i class="fas fa-times me-1"></i> Reject
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                            
                                        <?php elseif ($current_stage['stage'] === 'purchasing' && 
                                                $user['department'] === 'Admin' && 
                                                $user['position'] === 'Purchaser' && 
                                                $user['accounttype'] === 'Admin'): ?>
                                            <!-- Purchasing Department Actions -->
                                            <?php if ($is_issue_type): ?>
                                                <!-- For issue and issue_materials requests - Check if there's any available stock -->
                                                <?php
                                                $has_available_stock = false;
                                                foreach ($items as $item) {
                                                    if ($item['stock_info']['available'] && $item['stock_info']['stock_quantity'] > 0) {
                                                        $has_available_stock = true;
                                                        break;
                                                    }
                                                }
                                                ?>
                                                
                                                <?php if ($has_available_stock): ?>
                                                    <!-- If there's available stock (fully or partially), Job Order or Withdrawal Slip is required -->
                                                    <form method="POST" action="" id="purchasingActionsForm">
                                                        <div class="mb-3">
                                                            <?php if (!$job_order_exists && !$withdrawal_slip_exists): ?>
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                                You must create a <?php echo $is_issue_materials ? 'Withdrawal Slip' : 'Job Order'; ?> before approving.
                                                            </div>
                                                            <?php endif; ?>
                                                            <label for="remarks" class="form-label">Remarks</label>
                                                            <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <button type="submit" name="action" value="approve_purchasing" class="btn btn-success w-100" <?php echo (!$job_order_exists && !$withdrawal_slip_exists) ? 'disabled' : ''; ?>>
                                                                    <i class="fas fa-check me-1"></i> Approve (With <?php echo $is_issue_materials ? 'Withdrawal Slip' : 'Job Order'; ?>)
                                                                </button>
                                                            </div>
                                                            <div class="col-6">
                                                                <button type="submit" name="action" value="reject_purchasing" class="btn btn-danger w-100">
                                                                    <i class="fas fa-times me-1"></i> Reject
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                    
                                                    <hr class="my-3">
                                                    
                                                    <!-- Create Job Order or Withdrawal Slip Section - Show for issue and issue_materials requests with available stock -->
                                                    <?php if ($is_issue_materials && !$withdrawal_slip_exists): ?>
                                                    <div class="mb-3">
                                                        <label for="withdrawal_slip_remarks" class="form-label">Withdrawal Slip Remarks (Optional)</label>
                                                        <textarea class="form-control" id="withdrawal_slip_remarks" name="withdrawal_slip_remarks" rows="2"></textarea>
                                                    </div>
                                                    <div class="d-grid">
                                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createWithdrawalSlipModal">
                                                            <i class="fas fa-file-invoice me-1"></i> Create Withdrawal Slip
                                                        </button>
                                                    </div>
                                                    <?php elseif (!$is_issue_materials && !$job_order_exists): ?>
                                                    <div class="mb-3">
                                                        <label for="job_order_remarks" class="form-label">Job Order Remarks (Optional)</label>
                                                        <textarea class="form-control" id="job_order_remarks" name="job_order_remarks" rows="2"></textarea>
                                                    </div>
                                                    <div class="d-grid">
                                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createJobOrderModal">
                                                            <i class="fas fa-tools me-1"></i> Create Job Order
                                                        </button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        <?php echo $is_issue_materials ? 'Withdrawal Slip' : 'Job Order'; ?> has been created. You can now approve the request.
                                                    </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <!-- No available stock at all - PO is required -->
                                                    <form method="POST" action="" id="purchasingActionsForm">
                                                        <div class="mb-3">
                                                            <?php if (!$po_exists): ?>
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                                You must create a Purchase Order before approving.
                                                            </div>
                                                            <?php endif; ?>
                                                            <label for="remarks" class="form-label">Remarks</label>
                                                            <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <button type="submit" name="action" value="approve_purchasing" class="btn btn-success w-100" <?php echo !$po_exists ? 'disabled' : ''; ?>>
                                                                    <i class="fas fa-check me-1"></i> Approve
                                                                </button>
                                                            </div>
                                                            <div class="col-6">
                                                                <button type="submit" name="action" value="reject_purchasing" class="btn btn-danger w-100">
                                                                    <i class="fas fa-times me-1"></i> Reject
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                    
                                                    <hr class="my-3">
                                                    
                                                    <!-- Create PO Section - Only show if no PO exists -->
                                                    <?php if (!$po_exists): ?>
                                                    <div class="mb-3">
                                                        <label for="po_remarks" class="form-label">PO Remarks (Optional)</label>
                                                        <textarea class="form-control" id="po_remarks" name="po_remarks" rows="2"></textarea>
                                                    </div>
                                                    <div class="d-grid">
                                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                                            <i class="fas fa-file-invoice-dollar me-1"></i> Create Purchase Order
                                                        </button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        Purchase Order has been created. You can now approve the request.
                                                    </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- For stock requests -->
                                                <form method="POST" action="" id="purchasingActionsForm">
                                                    <div class="mb-3">
                                                        <?php if (!$po_exists): ?>
                                                        <div class="alert alert-warning">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                                            You must create a Purchase Order before approving.
                                                        </div>
                                                        <?php endif; ?>
                                                        <label for="remarks" class="form-label">Remarks</label>
                                                        <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <button type="submit" name="action" value="approve_purchasing" class="btn btn-success w-100" <?php echo !$po_exists ? 'disabled' : ''; ?>>
                                                                <i class="fas fa-check me-1"></i> Approve
                                                            </button>
                                                        </div>
                                                        <div class="col-6">
                                                            <button type="submit" name="action" value="reject_purchasing" class="btn btn-danger w-100">
                                                                <i class="fas fa-times me-1"></i> Reject
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                                
                                                <hr class="my-3">
                                                
                                                <!-- Create PO Section - Only show for stock requests if no PO exists -->
                                                <?php if (!$po_exists): ?>
                                                <div class="mb-3">
                                                    <label for="po_remarks" class="form-label">PO Remarks (Optional)</label>
                                                    <textarea class="form-control" id="po_remarks" name="po_remarks" rows="2"></textarea>
                                                </div>
                                                <div class="d-grid">
                                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                                        <i class="fas fa-file-invoice-dollar me-1"></i> Create Purchase Order
                                                    </button>
                                                </div>
                                                <?php else: ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    Purchase Order has been created. You can now approve the request.
                                                </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                        <?php elseif ($current_stage['stage'] === 'approver' && 
                                                $user['department'] === 'Admin' && 
                                                $user['position'] === 'CEO' && 
                                                $user['accounttype'] === 'Admin'): ?>
                                            <!-- Approver Actions - Two Buttons -->
                                            <form method="POST" action="" id="approverActionsForm">
                                                <div class="mb-3">
                                                    <label for="remarks" class="form-label">Remarks</label>
                                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6">
                                                        <button type="submit" name="action" value="approve_approver" class="btn btn-success w-100">
                                                            <i class="fas fa-check me-1"></i> Approve
                                                        </button>
                                                    </div>
                                                    <div class="col-6">
                                                        <button type="submit" name="action" value="reject_approver" class="btn btn-danger w-100">
                                                            <i class="fas fa-times me-1"></i> Reject
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                            
                                        <?php elseif ($current_stage['stage'] === 'warehouse_receiving' && 
                                                $user['department'] === 'Motorpool' && 
                                                $user['accounttype'] === 'Admin' &&
                                                $pr['request_type'] === 'stock'): ?>
                                            <!-- Motorpool Receiving Actions - Single Button -->
                                            <form method="POST" action="" id="warehouseReceivingActionsForm">
                                                <div class="mb-3">
                                                    <label for="remarks" class="form-label">Remarks</label>
                                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                </div>
                                                <div class="d-grid">
                                                    <button type="submit" name="action" value="complete_warehouse_receiving" class="btn btn-success" <?php echo $items_already_received ? '' : 'disabled'; ?>>
                                                        <i class="fas fa-check me-1"></i> Complete Receiving
                                                    </button>
                                                </div>
                                                <?php if (!$items_already_received): ?>
                                                <div class="alert alert-warning mt-3">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <strong>Action Required:</strong> You must first receive the items using "Confirm Items Received" before you can complete the receiving process.
                                                </div>
                                                <?php endif; ?>
                                            </form>
                                            
                                        <?php elseif ($current_stage['stage'] === 'warehouse_releasing' && 
                                                $user['department'] === 'Motorpool' && 
                                                $user['accounttype'] === 'Admin' &&
                                                $is_issue_type): ?>
                                            <!-- Motorpool Releasing Actions - Single Button -->
                                            <form method="POST" action="" id="warehouseReleasingActionsForm">
                                                <div class="mb-3">
                                                    <label for="remarks" class="form-label">Remarks</label>
                                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                </div>
                                                <div class="d-grid">
                                                    <button type="submit" name="action" value="complete_warehouse_releasing" class="btn btn-success" <?php echo ($items_already_released && !$releasing_already_completed) ? '' : 'disabled'; ?>>
                                                        <i class="fas fa-check me-1"></i> Complete Releasing
                                                    </button>
                                                </div>
                                                <?php if (!$items_already_released): ?>
                                                <div class="alert alert-warning mt-3">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <strong>Action Required:</strong> You must first release the items using "Confirm Items Released (FIFO)" before you can complete the releasing process.
                                                </div>
                                                <?php elseif ($releasing_already_completed): ?>
                                                <div class="alert alert-info mt-3">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    <strong>Releasing Completed:</strong> The Motorpool releasing process has already been completed for this PR.
                                                </div>
                                                <?php endif; ?>
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
                                            <td><?php echo $history['created_at']; ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    if (strpos($history['action'], 'Approved') !== false) echo 'bg-success';
                                                    elseif (strpos($history['action'], 'Rejected') !== false) echo 'bg-danger';
                                                    elseif (strpos($history['action'], 'Delivered') !== false) echo 'bg-info';
                                                    elseif (strpos($history['action'], 'Purchase Order') !== false) echo 'bg-warning';
                                                    elseif (strpos($history['action'], 'Job Order') !== false) echo 'bg-info';
                                                    elseif (strpos($history['action'], 'Withdrawal Slip') !== false) echo 'bg-info';
                                                    elseif (strpos($history['action'], 'Received') !== false) echo 'bg-primary';
                                                    elseif (strpos($history['action'], 'Released') !== false) echo 'bg-primary';
                                                    elseif (strpos($history['action'], 'Completed') !== false) echo 'bg-success';
                                                    elseif (strpos($history['action'], 'Forwarded') !== false) echo 'bg-info';
                                                    else echo 'bg-secondary';
                                                    ?>
                                                ">
                                                    <?php echo htmlspecialchars($history['action']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatUserName($history); ?></td>
                                            <td><?php echo htmlspecialchars($history['department'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($history['remarks']); ?></td>
                                            <td>
                                                <?php 
                                                switch($history['stage_from'] ?? '') {
                                                    case 'requestor': echo 'Requestor'; break;
                                                    case 'warehouse': echo 'Motorpool Department'; break;
                                                    case 'purchasing': echo 'Purchasing'; break;
                                                    case 'approver': echo 'Approver'; break;
                                                    case 'warehouse_receiving': echo 'Motorpool Receiving'; break;
                                                    case 'warehouse_releasing': echo 'Motorpool Releasing'; break;
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
                                                    case 'warehouse': echo 'Motorpool Department'; break;
                                                    case 'purchasing': echo 'Purchasing'; break;
                                                    case 'approver': echo 'Approver'; break;
                                                    case 'warehouse_receiving': echo 'Motorpool Receiving'; break;
                                                    case 'warehouse_releasing': echo 'Motorpool Releasing'; break;
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

    <!-- Create Purchase Order Modal -->
    <div class="modal fade" id="createPOModal" tabindex="-1" aria-labelledby="createPOModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPOModalLabel">Create Purchase Order for Spare Parts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="createPOForm">
                    <input type="hidden" name="action" value="create_purchase_order">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>
                            This will create a purchase order for the selected spare parts.
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="expected_delivery" class="form-label">Expected Delivery Date</label>
                                <input type="date" class="form-control" id="expected_delivery" name="expected_delivery" required>
                            </div>
                            <div class="col-md-6">
                                <label for="po_remarks" class="form-label">PO Remarks</label>
                                <textarea class="form-control" id="po_remarks" name="po_remarks" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <h6>Spare Parts for Purchase Order:</h6>
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" id="selectAllItemsPO">
                                        </th>
                                        <th>Part Name</th>
                                        <th>Category</th>
                                        <th>Supplier</th>
                                        <th>Quantity</th>
                                        <th>Unit Cost</th>
                                        <th>Total Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($items)): ?>
                                        <?php foreach ($items as $item): 
                                            // Format part name with part number in parentheses
                                            $part_name_display = htmlspecialchars($item['part_name']);
                                            if (!empty($item['part_number'])) {
                                                $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                            }
                                        ?>
                                        <tr class="po-item-row">
                                            <td>
                                                <input type="checkbox" name="selected_items[]" value="<?php echo $item['id']; ?>" 
                                                    class="item-checkbox-po" data-item-id="<?php echo $item['id']; ?>" checked>
                                            </td>
                                            <td><?php echo $part_name_display; ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td>
                                                <!-- NEW: Supplier dropdown for each item -->
                                                <select name="supplier_id_<?php echo $item['id']; ?>" 
                                                        class="form-select form-select-sm supplier-select-po" 
                                                        style="min-width: 150px;"
                                                        data-item-id="<?php echo $item['id']; ?>" required>
                                                    <option value="">Select Supplier</option>
                                                    <?php foreach ($all_suppliers as $supplier): ?>
                                                        <option value="<?php echo $supplier['id']; ?>" 
                                                            <?php echo ($supplier['id'] == $pr['supplier_id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" name="quantity_<?php echo $item['id']; ?>" 
                                                    value="<?php echo $item['quantity']; ?>" 
                                                    min="1" max="<?php echo $item['quantity']; ?>" 
                                                    class="form-control form-control-sm quantity-input-po" 
                                                    data-item-id="<?php echo $item['id']; ?>"
                                                    style="width: 80px;">
                                            </td>
                                            <td>
                                                <input type="number" name="unit_cost_<?php echo $item['id']; ?>" 
                                                    value="<?php echo $item['unit_cost']; ?>" 
                                                    step="0.01" min="0.01" 
                                                    class="form-control form-control-sm unit-cost-input-po" 
                                                    data-item-id="<?php echo $item['id']; ?>"
                                                    style="width: 100px;" required>
                                            </td>
                                            <td>
                                                <span class="total-cost-po" id="total_po_<?php echo $item['id']; ?>">
                                                    ₱<?php echo number_format($item['quantity'] * $item['unit_cost'], 2); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-3"> <!-- Updated colspan from 6 to 7 -->
                                                <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                                No spare parts found for this purchase request.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <strong>Total Items Selected: <span id="selectedCountPO"><?php echo count($items); ?></span></strong>
                            </div>
                            <div class="col-md-6 text-end">
                                <strong>Grand Total: ₱<span id="grandTotalPO"><?php echo number_format($total_estimated_cost, 2); ?></span></strong>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="createPOBtn" <?php echo empty($items) ? 'disabled' : ''; ?>>
                            <i class="fas fa-file-invoice-dollar me-1"></i> Create Purchase Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Job Order Modal (only for issue requests) -->
    <div class="modal fade" id="createJobOrderModal" tabindex="-1" aria-labelledby="createJobOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createJobOrderModalLabel">Create Job Order for Spare Parts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="createJobOrderForm">
                    <input type="hidden" name="action" value="create_job_order">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>
                            This will create a Job Order for the selected spare parts (for issue requests with available stock).
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="job_order_date" class="form-label">Job Order Date</label>
                                <input type="date" class="form-control" id="job_order_date" name="job_order_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="job_order_remarks" class="form-label">Job Order Remarks</label>
                                <textarea class="form-control" id="job_order_remarks" name="job_order_remarks" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <h6>Spare Parts for Job Order:</h6>
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" id="selectAllItemsJO">
                                        </th>
                                        <th>Part Name</th>
                                        <th>Category</th>
                                        <th>Quantity Requested</th>
                                        <th>Available Stock</th>
                                        <th>Quantity for Job Order</th>
                                        <th>Vehicle/Equipment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($items)): ?>
                                        <?php foreach ($items as $item): 
                                            $available_stock = $item['stock_info']['stock_quantity'] ?? 0;
                                            $requested_quantity = $item['quantity'];
                                            // FIX: For "Partially Available" items, only allow up to available stock
                                            $max_quantity = min($available_stock, $requested_quantity);
                                            
                                            // Format part name with part number in parentheses
                                            $part_name_display = htmlspecialchars($item['part_name']);
                                            if (!empty($item['part_number'])) {
                                                $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                            }
                                        ?>
                                        <tr class="po-item-row">
                                            <td>
                                                <input type="checkbox" name="selected_items[]" value="<?php echo $item['id']; ?>" 
                                                    class="item-checkbox-jo" data-item-id="<?php echo $item['id']; ?>" 
                                                    <?php echo ($available_stock > 0) ? 'checked' : 'disabled'; ?>>
                                            </td>
                                            <td><?php echo $part_name_display; ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo number_format($requested_quantity, 2); ?></td>
                                            <td><?php echo number_format($available_stock, 2); ?></td>
                                            <td>
                                                <?php if ($available_stock > 0): ?>
                                                    <input type="number" name="quantity_<?php echo $item['id']; ?>" 
                                                        value="<?php echo $max_quantity; ?>" 
                                                        min="0.01" max="<?php echo $max_quantity; ?>" 
                                                        step="0.01"
                                                        class="form-control form-control-sm quantity-input-jo" 
                                                        data-item-id="<?php echo $item['id']; ?>"
                                                        data-max-stock="<?php echo $available_stock; ?>"
                                                        style="width: 80px;">
                                                <?php else: ?>
                                                    <span class="text-danger">Out of stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['vehicle_equipment_display']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-3">
                                                <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                                No spare parts found for this purchase request.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <strong>Total Items Selected: <span id="selectedCountJO">0</span></strong>
                            </div>
                            <div class="col-md-6">
                                <strong>Purpose: <?php echo htmlspecialchars($pr['purpose'] ?? 'N/A'); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="createJobOrderBtn" disabled>
                            <i class="fas fa-tools me-1"></i> Create Job Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Withdrawal Slip Modal (only for issue_materials requests) -->
    <div class="modal fade" id="createWithdrawalSlipModal" tabindex="-1" aria-labelledby="createWithdrawalSlipModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createWithdrawalSlipModalLabel">
                        <?php echo $is_issue_materials ? 'Create Withdrawal Slip for Materials' : 'Create Withdrawal Slip for Spare Parts'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="createWithdrawalSlipForm">
                    <input type="hidden" name="action" value="create_withdrawal_slip">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>
                            This will create a Withdrawal Slip for the selected <?php echo $is_issue_materials ? 'materials' : 'spare parts'; ?> 
                            (for <?php echo $is_issue_materials ? 'issue materials' : 'issue'; ?> requests with available stock).
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="withdrawal_slip_date" class="form-label">Withdrawal Date</label>
                                <input type="date" class="form-control" id="withdrawal_slip_date" name="withdrawal_slip_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="withdrawal_slip_remarks" class="form-label">Withdrawal Slip Remarks</label>
                                <textarea class="form-control" id="withdrawal_slip_remarks" name="withdrawal_slip_remarks" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <h6>Materials for Withdrawal Slip:</h6>
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" id="selectAllItemsWS">
                                        </th>
                                        <th>Part Name</th>
                                        <th>Category</th>
                                        <th>Quantity Requested</th>
                                        <th>Available Stock</th>
                                        <th>Quantity for Withdrawal</th>
                                        <?php if (!$is_issue_materials): ?>
                                        <!-- Only show Vehicle/Equipment for Issue Parts Request, NOT for Issue Materials -->
                                        <th>Vehicle/Equipment</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($items)): ?>
                                        <?php foreach ($items as $item): 
                                            $available_stock = $item['stock_info']['stock_quantity'] ?? 0;
                                            $requested_quantity = $item['quantity'];
                                            // For "Partially Available" items, only allow up to available stock
                                            $max_quantity = min($available_stock, $requested_quantity);
                                            
                                            // Format part name with part number in parentheses
                                            $part_name_display = htmlspecialchars($item['part_name']);
                                            if (!empty($item['part_number'])) {
                                                $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                            }
                                        ?>
                                        <tr class="po-item-row">
                                            <td>
                                                <input type="checkbox" name="selected_items[]" value="<?php echo $item['id']; ?>" 
                                                    class="item-checkbox-ws" data-item-id="<?php echo $item['id']; ?>" 
                                                    <?php echo ($available_stock > 0) ? 'checked' : 'disabled'; ?>>
                                            </td>
                                            <td><?php echo $part_name_display; ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo number_format($requested_quantity, 2); ?></td>
                                            <td><?php echo number_format($available_stock, 2); ?></td>
                                            <td>
                                                <?php if ($available_stock > 0): ?>
                                                    <input type="number" name="quantity_<?php echo $item['id']; ?>" 
                                                        value="<?php echo $max_quantity; ?>" 
                                                        min="0.01" max="<?php echo $max_quantity; ?>" 
                                                        step="0.01"
                                                        class="form-control form-control-sm quantity-input-ws" 
                                                        data-item-id="<?php echo $item['id']; ?>"
                                                        data-max-stock="<?php echo $available_stock; ?>">
                                                <?php else: ?>
                                                    <span class="text-danger">Out of stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if (!$is_issue_materials): ?>
                                            <!-- Only show Vehicle/Equipment for Issue Parts Request, NOT for Issue Materials -->
                                            <td><?php echo htmlspecialchars($item['vehicle_equipment_display']); ?></td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <?php if ($is_issue_materials): ?>
                                            <td colspan="6" class="text-center py-3">
                                                <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                                No spare parts found for this purchase request.
                                            </td>
                                            <?php else: ?>
                                            <td colspan="7" class="text-center py-3">
                                                <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                                No spare parts found for this purchase request.
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <strong>Total Items Selected: <span id="selectedCountWS">0</span></strong>
                            </div>
                            <div class="col-md-6">
                                <strong>Purpose: <?php echo htmlspecialchars($pr['purpose'] ?? 'N/A'); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="createWithdrawalSlipBtn" disabled>
                            <i class="fas fa-file-invoice me-1"></i> Create Withdrawal Slip for <?php echo $is_issue_materials ? 'Materials' : 'Spare Parts'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
                                    <th>Part Name</th>
                                    <th>Category</th>
                                    <th>Supplier</th> <!-- ADDED Supplier column -->
                                    <th>Quantity</th>
                                    <th>Received</th>
                                    <th>Remaining</th>
                                    <th>Unit Cost</th>
                                    <th>Total Cost</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="modal-po-items">
                                <!-- PO items will be populated by JavaScript -->
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

    <!-- View Job Order Modal -->
    <div class="modal fade" id="viewJobOrderModal" tabindex="-1" aria-labelledby="viewJobOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewJobOrderModalLabel">Job Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Job Order Number:</th>
                                    <td id="modal-job-order-number">-</td>
                                </tr>
                                <tr>
                                    <th>Technician:</th>
                                    <td id="modal-technician">-</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Job Order Date:</th>
                                    <td id="modal-job-order-date">-</td>
                                </tr>
                                <tr>
                                    <th width="40%">Purpose:</th>
                                    <td id="modal-purpose">-</td>
                                </tr>
                                <tr>
                                    <th>Remarks:</th>
                                    <td id="modal-job-order-remarks">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Update the thead section in the Job Order Details modal -->
                    <h6>Job Order Items:</h6>
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Vehicle/Equipment</th>
                                    <th>Part Name</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total Cost</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="modal-job-order-items">
                                <!-- Job Order items will be populated by JavaScript -->
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

    <!-- View Withdrawal Slip Modal - UPDATED TO HIDE VEHICLE/EQUIPMENT FOR ISSUE MATERIALS -->
    <div class="modal fade" id="viewWithdrawalSlipModal" tabindex="-1" aria-labelledby="viewWithdrawalSlipModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewWithdrawalSlipModalLabel">Withdrawal Slip Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Withdrawal Slip Number:</th>
                                    <td id="modal-withdrawal-slip-number">-</td>
                                </tr>
                                <tr>
                                    <th>Prepared By:</th>
                                    <td id="modal-requested-by">-</td>
                                </tr>
                                <tr>
                                    <th>Issue to Employee:</th>
                                    <td id="modal-employee">-</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Withdrawal Date:</th>
                                    <td id="modal-withdrawal-slip-date">-</td>
                                </tr>
                                <tr>
                                    <th width="40%">Purpose:</th>
                                    <td id="modal-ws-purpose">-</td>
                                </tr>
                                <tr>
                                    <th>Remarks:</th>
                                    <td id="modal-withdrawal-slip-remarks">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <h6>Withdrawal Slip Items:</h6>
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Part Name</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total Cost</th>
                                    <?php if (!$is_issue_materials): ?>
                                    <!-- Only show Vehicle/Equipment for Issue Parts Request, NOT for Issue Materials -->
                                    <th>Vehicle/Equipment</th>
                                    <?php endif; ?>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="modal-withdrawal-slip-items">
                                <!-- Withdrawal Slip items will be populated by JavaScript -->
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

    <!-- Edit Purchase Order Modal -->
    <div class="modal fade" id="editPOModal" tabindex="-1" aria-labelledby="editPOModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPOModalLabel">Edit Purchase Order Quantities</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="editPOForm">
                    <input type="hidden" name="action" value="update_po_quantity">
                    <input type="hidden" name="po_id" id="edit_po_id">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            You can only edit quantities for PO items. Other fields are read-only.
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">PO Number:</th>
                                        <td id="edit-modal-po-number">-</td>
                                    </tr>
                                    <tr>
                                        <th>PO Date:</th>
                                        <td id="edit-modal-po-date">-</td>
                                    </tr>
                                    <tr>
                                        <th>Expected Delivery:</th>
                                        <td id="edit-modal-expected-delivery">-</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Total Amount:</th>
                                        <td id="edit-modal-total-amount">-</td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td id="edit-modal-po-status">-</td>
                                    </tr>
                                    <tr>
                                        <th>Remarks:</th>
                                        <td id="edit-modal-po-remarks">-</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <h6>Edit PO Item Quantities:</h6>
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Part Number</th>
                                        <th>Part Name</th>
                                        <th>Category</th>
                                        <th>Current Quantity</th>
                                        <th>New Quantity</th>
                                        <th>Unit Cost</th>
                                        <th>Total Cost</th>
                                    </tr>
                                </thead>
                                <tbody id="edit-modal-po-items">
                                    <!-- PO items for editing will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Update Quantities
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show SweetAlert2 notifications
        <?php if (!empty($swal_data)): ?>
            Swal.fire({
                title: '<?php echo $swal_data['title']; ?>',
                text: '<?php echo $swal_data['text']; ?>',
                icon: '<?php echo $swal_data['icon']; ?>',
                confirmButtonText: 'OK'
            });
        <?php endif; ?>

        // Format number with commas for display
        function formatNumberWithCommas(number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // Remove commas and convert to number for calculation
        function parseNumberWithCommas(formattedNumber) {
            return parseFloat(formattedNumber.replace(/,/g, ''));
        }

        // Format currency with peso sign and commas
        function formatCurrency(amount) {
            return '₱' + formatNumberWithCommas(parseFloat(amount).toFixed(2));
        }

        // Action button functions
        function requestReplenishment(itemId, partName) {
            Swal.fire({
                title: 'Request Stock Replenishment',
                text: 'Are you sure you want to request stock replenishment for "' + partName + '"?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, request',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // In a real application, you would make an AJAX call here
                    // For now, just show a success message
                    Swal.fire({
                        title: 'Request Sent!',
                        text: 'Stock replenishment request has been sent for ' + partName,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function addToJobOrder(itemId, partName) {
            Swal.fire({
                title: 'Add to Job Order',
                text: 'Add "' + partName + '" to the Job Order?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, add',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Check if Job Order modal is already open, if not open it
                    const createJobOrderModal = new bootstrap.Modal(document.getElementById('createJobOrderModal'));
                    createJobOrderModal.show();
                    
                    // After a short delay, check the checkbox for this item
                    setTimeout(() => {
                        const checkbox = document.querySelector(`.item-checkbox-jo[data-item-id="${itemId}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            // Trigger change event to recalculate totals
                            checkbox.dispatchEvent(new Event('change'));
                        }
                    }, 500);
                }
            });
        }

        function addToWithdrawalSlip(itemId, partName) {
            Swal.fire({
                title: 'Add to Withdrawal Slip',
                text: 'Add "' + partName + '" to the Withdrawal Slip?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, add',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Check if Withdrawal Slip modal is already open, if not open it
                    const createWithdrawalSlipModal = new bootstrap.Modal(document.getElementById('createWithdrawalSlipModal'));
                    createWithdrawalSlipModal.show();
                    
                    // After a short delay, check the checkbox for this item
                    setTimeout(() => {
                        const checkbox = document.querySelector(`.item-checkbox-ws[data-item-id="${itemId}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            // Trigger change event to recalculate totals
                            checkbox.dispatchEvent(new Event('change'));
                        }
                    }, 500);
                }
            });
        }

        function addToPO(itemId, partName) {
            Swal.fire({
                title: 'Add to Purchase Order',
                text: 'Add "' + partName + '" to the purchase order?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, add',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Check if PO modal is already open, if not open it
                    const createPOModal = new bootstrap.Modal(document.getElementById('createPOModal'));
                    createPOModal.show();
                    
                    // After a short delay, check the checkbox for this item
                    setTimeout(() => {
                        const checkbox = document.querySelector(`.item-checkbox-po[data-item-id="${itemId}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            // Trigger change event to recalculate totals
                            checkbox.dispatchEvent(new Event('change'));
                        }
                    }, 500);
                }
            });
        }

        function receiveItem(itemId, partName) {
            Swal.fire({
                title: 'Receive Item',
                text: 'Mark "' + partName + '" as received?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, receive',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // In a real application, you would make an AJAX call here
                    // For now, just show a success message
                    Swal.fire({
                        title: 'Item Received!',
                        text: partName + ' has been marked as received',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function releaseItem(itemId, partName) {
            Swal.fire({
                title: 'Release Item',
                text: 'Mark "' + partName + '" as released?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, release',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // In a real application, you would make an AJAX call here
                    // For now, just show a success message
                    Swal.fire({
                        title: 'Item Released!',
                        text: partName + ' has been marked as released',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        // Purchase Order functionality
        // Calculate totals for PO items
        function calculatePOTotals() {
            let grandTotal = 0;
            let selectedCount = 0;
            
            document.querySelectorAll('.item-checkbox-po:checked').forEach(checkbox => {
                selectedCount++;
                const itemId = checkbox.getAttribute('data-item-id');
                const quantity = parseFloat(document.querySelector(`.quantity-input-po[data-item-id="${itemId}"]`).value) || 0;
                const unitCost = parseFloat(document.querySelector(`.unit-cost-input-po[data-item-id="${itemId}"]`).value) || 0;
                const total = quantity * unitCost;
                
                document.getElementById(`total_po_${itemId}`).textContent = formatCurrency(total);
                grandTotal += total;
            });
            
            document.getElementById('selectedCountPO').textContent = selectedCount;
            document.getElementById('grandTotalPO').textContent = formatNumberWithCommas(grandTotal.toFixed(2));
            
            // Enable/disable create button based on selection
            document.getElementById('createPOBtn').disabled = selectedCount === 0;
        }
        
        // Select all items for PO
        const selectAllCheckboxPO = document.getElementById('selectAllItemsPO');
        if (selectAllCheckboxPO) {
            selectAllCheckboxPO.addEventListener('change', function() {
                document.querySelectorAll('.item-checkbox-po').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                calculatePOTotals();
            });
        }
        
        // Individual item selection for PO
        document.querySelectorAll('.item-checkbox-po').forEach(checkbox => {
            checkbox.addEventListener('change', calculatePOTotals);
        });
        
        // Quantity and unit cost changes for PO
        document.querySelectorAll('.quantity-input-po, .unit-cost-input-po').forEach(input => {
            input.addEventListener('input', calculatePOTotals);
        });
        
        // Initial calculation for PO
        calculatePOTotals();

        // Job Order functionality
        // Calculate totals for Job Order items
        function calculateJOTotals() {
            let selectedCount = 0;
            let totalQuantity = 0;
            
            document.querySelectorAll('.item-checkbox-jo:checked').forEach(checkbox => {
                selectedCount++;
                const itemId = checkbox.getAttribute('data-item-id');
                const quantity = parseFloat(document.querySelector(`.quantity-input-jo[data-item-id="${itemId}"]`).value) || 0;
                totalQuantity += quantity;
            });
            
            document.getElementById('selectedCountJO').textContent = selectedCount;
            
            // Enable/disable create button based on selection and quantity > 0
            const createBtn = document.getElementById('createJobOrderBtn');
            createBtn.disabled = selectedCount === 0 || totalQuantity === 0;
            
            // Update button text with total quantity
            if (selectedCount > 0 && totalQuantity > 0) {
                createBtn.innerHTML = `<i class="fas fa-tools me-1"></i> Create Job Order (${totalQuantity.toFixed(2)} items)`;
            } else {
                createBtn.innerHTML = `<i class="fas fa-tools me-1"></i> Create Job Order`;
            }
        }
        
        // Select all items for Job Order (only those with available stock)
        const selectAllCheckboxJO = document.getElementById('selectAllItemsJO');
        if (selectAllCheckboxJO) {
            selectAllCheckboxJO.addEventListener('change', function() {
                document.querySelectorAll('.item-checkbox-jo:not(:disabled)').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                calculateJOTotals();
            });
        }
        
        // Individual item selection for Job Order
        document.querySelectorAll('.item-checkbox-jo').forEach(checkbox => {
            checkbox.addEventListener('change', calculateJOTotals);
        });
        
        // Quantity changes for Job Order - limit to available stock
        document.querySelectorAll('.quantity-input-jo').forEach(input => {
            input.addEventListener('input', function() {
                const itemId = this.getAttribute('data-item-id');
                const maxStock = parseFloat(this.getAttribute('data-max-stock')) || 0;
                const quantity = parseFloat(this.value) || 0;
                
                // Ensure quantity doesn't exceed available stock
                if (quantity > maxStock) {
                    this.value = maxStock;
                    Swal.fire({
                        title: 'Quantity Limit',
                        text: `Cannot exceed available stock of ${maxStock}`,
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                }
                
                // Ensure quantity is at least 0.01 if positive
                if (quantity > 0 && quantity < 0.01) {
                    this.value = 0.01;
                }
                
                calculateJOTotals();
            });
        });
        
        // Initial calculation for Job Order
        calculateJOTotals();

        // Withdrawal Slip functionality
        // Calculate totals for Withdrawal Slip items
        function calculateWSTotals() {
            let selectedCount = 0;
            let totalQuantity = 0;
            
            document.querySelectorAll('.item-checkbox-ws:checked').forEach(checkbox => {
                selectedCount++;
                const itemId = checkbox.getAttribute('data-item-id');
                const quantity = parseFloat(document.querySelector(`.quantity-input-ws[data-item-id="${itemId}"]`).value) || 0;
                totalQuantity += quantity;
            });
            
            document.getElementById('selectedCountWS').textContent = selectedCount;
            
            // Enable/disable create button based on selection and quantity > 0
            const createBtn = document.getElementById('createWithdrawalSlipBtn');
            createBtn.disabled = selectedCount === 0 || totalQuantity === 0;
            
            // Update button text with total quantity
            if (selectedCount > 0 && totalQuantity > 0) {
                createBtn.innerHTML = `<i class="fas fa-file-invoice me-1"></i> Create Withdrawal Slip for <?php echo $is_issue_materials ? 'Materials' : 'Spare Parts'; ?> (${totalQuantity.toFixed(2)} items)`;
            } else {
                createBtn.innerHTML = `<i class="fas fa-file-invoice me-1"></i> Create Withdrawal Slip for <?php echo $is_issue_materials ? 'Materials' : 'Spare Parts'; ?>`;
            }
        }
        
        // Select all items for Withdrawal Slip (only those with available stock)
        const selectAllCheckboxWS = document.getElementById('selectAllItemsWS');
        if (selectAllCheckboxWS) {
            selectAllCheckboxWS.addEventListener('change', function() {
                document.querySelectorAll('.item-checkbox-ws:not(:disabled)').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                calculateWSTotals();
            });
        }
        
        // Individual item selection for Withdrawal Slip
        document.querySelectorAll('.item-checkbox-ws').forEach(checkbox => {
            checkbox.addEventListener('change', calculateWSTotals);
        });
        
        // Quantity changes for Withdrawal Slip - limit to available stock
        document.querySelectorAll('.quantity-input-ws').forEach(input => {
            input.addEventListener('input', function() {
                const itemId = this.getAttribute('data-item-id');
                const maxStock = parseFloat(this.getAttribute('data-max-stock')) || 0;
                const quantity = parseFloat(this.value) || 0;
                
                // Ensure quantity doesn't exceed available stock
                if (quantity > maxStock) {
                    this.value = maxStock;
                    Swal.fire({
                        title: 'Quantity Limit',
                        text: `Cannot exceed available stock of ${maxStock}`,
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                }
                
                // Ensure quantity is at least 0.01 if positive
                if (quantity > 0 && quantity < 0.01) {
                    this.value = 0.01;
                }
                
                calculateWSTotals();
            });
        });
        
        // Initial calculation for Withdrawal Slip
        calculateWSTotals();

        // View PO Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const viewButtons = document.querySelectorAll('.view-po-btn');
            const viewModal = new bootstrap.Modal(document.getElementById('viewPOModal'));
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const poId = this.getAttribute('data-po-id');
                    const poNumber = this.getAttribute('data-po-number');
                    const poDate = this.getAttribute('data-po-date');
                    const expectedDelivery = this.getAttribute('data-expected-delivery');
                    const totalAmount = this.getAttribute('data-total-amount');
                    const poStatus = this.getAttribute('data-po-status');
                    const poRemarks = this.getAttribute('data-po-remarks');
                    
                    // Set basic PO information with status badge next to PO number
                    document.getElementById('modal-po-number').innerHTML = poNumber + ' <span class="badge ' + getPOStatusBadgeClass(poStatus.toLowerCase()) + '">' + poStatus + '</span>';
                    document.getElementById('modal-po-date').textContent = poDate;
                    document.getElementById('modal-expected-delivery').textContent = expectedDelivery;
                    document.getElementById('modal-total-amount').textContent = '₱' + formatNumberWithCommas(parseFloat(totalAmount).toFixed(2));
                    document.getElementById('modal-po-remarks').textContent = poRemarks || '-';
                    
                    // Clear previous items
                    const itemsContainer = document.getElementById('modal-po-items');
                    itemsContainer.innerHTML = '';
                    
                    // Get PO items from PHP data
                    const poItems = <?php echo json_encode($po_items_details); ?>;
                    
                    if (poItems[poId] && poItems[poId].length > 0) {
                        poItems[poId].forEach(item => {
                            const received = parseFloat(item.received_quantity || 0);
                            const remaining = parseFloat(item.quantity) - received;
                            const status = item.status || 'pending';
                            const unitCost = parseFloat(item.unit_cost);
                            const totalCost = parseFloat(item.total_cost);
                            const quantity = parseFloat(item.quantity);
                            
                            // Calculate total cost based on the condition
                            let totalCostToDisplay;
                            if (received === 0) {
                                totalCostToDisplay = totalCost;
                            } else {
                                totalCostToDisplay = received * unitCost;
                            }
                            
                            // Get supplier name from the PR data or from the item
                            // You can get the supplier from the existing PO data
                            const supplierName = '<?php echo addslashes($pr['supplier_name'] ?? 'N/A'); ?>';
                            
                            const row = document.createElement('tr');
                            // FIXED: Added Supplier column after Category
                            row.innerHTML = `
                                <td>${item.part_name || 'N/A'} (${item.part_number || 'N/A'})</td>
                                <td>${item.category_name || 'N/A'}</td>
                                <td>${supplierName}</td>
                                <td>${parseFloat(item.quantity)}</td>
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
                        itemsContainer.innerHTML = `
                            <tr>
                                <td colspan="9" class="text-center py-3"> <!-- Updated colspan from 8 to 9 -->
                                    <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                    No items found for this purchase order.
                                </td>
                            </tr>
                        `;
                    }
                    
                    viewModal.show();
                });
            });
            
            // Helper function to get badge class for PO status
            function getPOStatusBadgeClass(status) {
                switch(status) {
                    case 'draft': return 'bg-secondary';
                    case 'pending': return 'bg-warning';
                    case 'approved': return 'bg-success';
                    case 'confirmed': return 'bg-success';
                    case 'delivered': return 'bg-success';
                    case 'partially_received': return 'bg-warning';
                    case 'cancelled': return 'bg-danger';
                    case 'completed': return 'bg-success';
                    default: return 'bg-secondary';
                }
            }
        });

        // View Job Order Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const viewJobOrderButtons = document.querySelectorAll('.view-job-order-btn');
            const viewJobOrderModal = new bootstrap.Modal(document.getElementById('viewJobOrderModal'));
            
            viewJobOrderButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const jobOrderId = this.getAttribute('data-job-order-id');
                    const jobOrderNumber = this.getAttribute('data-job-order-number');
                    const jobOrderDate = this.getAttribute('data-job-order-date');
                    const technician = this.getAttribute('data-technician');
                    const purpose = this.getAttribute('data-purpose');
                    const jobOrderStatus = this.getAttribute('data-job-order-status');
                    const jobOrderRemarks = this.getAttribute('data-job-order-remarks');
                    
                    // Set basic Job Order information
                    document.getElementById('modal-job-order-number').innerHTML = jobOrderNumber + ' <span class="badge ' + getJobOrderStatusBadgeClass(jobOrderStatus.toLowerCase()) + '">' + jobOrderStatus + '</span>';
                    document.getElementById('modal-job-order-date').textContent = jobOrderDate;

                    // Format technician name from the PHP data
                    <?php
                    $technician_formatted = 'N/A';
                    if (!empty($pr['tech_firstname'])) {
                        $technician_formatted = $pr['tech_firstname'];
                        if (!empty($pr['tech_middlename'])) {
                            $technician_formatted .= ' ' . substr($pr['tech_middlename'], 0, 1) . '.';
                        }
                        $technician_formatted .= ' ' . $pr['tech_lastname'];
                        if (!empty($pr['tech_suffix'])) {
                            $technician_formatted .= ' ' . $pr['tech_suffix'];
                        }
                    }
                    ?>
                    document.getElementById('modal-technician').textContent = '<?php echo $technician_formatted; ?>';

                    document.getElementById('modal-purpose').textContent = purpose;
                    document.getElementById('modal-job-order-remarks').textContent = jobOrderRemarks || '-';
                    
                    // Clear previous items
                    const itemsContainer = document.getElementById('modal-job-order-items');
                    itemsContainer.innerHTML = '';
                    
                    // Get Job Order items from PHP data
                    const jobOrderItems = <?php echo json_encode($job_order_details['items'] ?? []); ?>;

                    // Get vehicle/equipment display from PR data
                    const vehicleEquipmentDisplay = '<?php 
                        if (!empty($pr['vehicle_name'])) {
                            echo addslashes(htmlspecialchars($pr['vehicle_name'] . ' (' . $pr['plate_number'] . ')'));
                        } elseif (!empty($pr['equipment_name'])) {
                            echo addslashes(htmlspecialchars($pr['equipment_name']));
                        } else {
                            echo 'N/A';
                        }
                    ?>';

                    if (jobOrderItems && jobOrderItems.length > 0) {
                        jobOrderItems.forEach(item => {
                            const status = item.status || 'pending';
                            const quantity = parseFloat(item.quantity);
                            // Get unit cost and calculate total cost
                            // You may need to adjust these based on your data structure
                            const unitCost = parseFloat(item.unit_cost) || 0;
                            const totalCost = quantity * unitCost;
                            
                            const row = document.createElement('tr');
                            // FIXED: Reordered columns - Vehicle/Equipment first, then Part Name, Category, Quantity, Unit Cost, Total Cost, Status
                            row.innerHTML = `
                                <td>${vehicleEquipmentDisplay}</td>
                                <td>${item.part_name || 'N/A'} (${item.part_number || 'N/A'})</td>
                                <td>${item.category_name || 'N/A'}</td>
                                <td>${quantity.toFixed(2)}</td>
                                <td>${formatCurrency(unitCost)}</td>
                                <td>${formatCurrency(totalCost)}</td>
                                <td>
                                    <span class="badge ${getJobOrderStatusBadge(status)}">
                                        ${status}
                                    </span>
                                </td>
                            `;
                            itemsContainer.appendChild(row);
                        });
                    } else {
                        itemsContainer.innerHTML = `
                            <tr>
                                <td colspan="7" class="text-center py-3">
                                    <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                    No items found for this job order.
                                </td>
                            </tr>
                        `;
                    }
                    
                    viewJobOrderModal.show();
                });
            });
            
            function getJobOrderStatusBadge(status) {
                switch(status) {
                    case 'draft': return 'bg-primary';
                    case 'pending': return 'bg-warning';
                    case 'approved': return 'bg-success';
                    case 'released': return 'bg-success';
                    case 'confirmed': return 'bg-success';
                    case 'completed': return 'bg-success';
                    case 'cancelled': return 'bg-danger';
                    default: return 'bg-secondary';
                }
            }
            
            function getJobOrderStatusBadgeClass(status) {
                switch(status) {
                    case 'draft': return 'bg-primary';
                    case 'pending': return 'bg-warning';
                    case 'approved': return 'bg-success';
                    case 'released': return 'bg-success';
                    case 'confirmed': return 'bg-success';
                    case 'completed': return 'bg-success';
                    case 'cancelled': return 'bg-danger';
                    default: return 'bg-secondary';
                }
            }
        });

        // View Withdrawal Slip Modal functionality - UPDATED FOR EMPLOYEE AND TO HIDE VEHICLE/EQUIPMENT
        document.addEventListener('DOMContentLoaded', function() {
            const viewWithdrawalSlipButtons = document.querySelectorAll('.view-withdrawal-slip-btn');
            const viewWithdrawalSlipModal = new bootstrap.Modal(document.getElementById('viewWithdrawalSlipModal'));
            
            viewWithdrawalSlipButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const withdrawalSlipId = this.getAttribute('data-withdrawal-slip-id');
                    const withdrawalSlipNumber = this.getAttribute('data-withdrawal-slip-number');
                    const withdrawalSlipDate = this.getAttribute('data-withdrawal-slip-date');
                    const requestedBy = this.getAttribute('data-requested-by');
                    const employee = this.getAttribute('data-employee');
                    const purpose = this.getAttribute('data-purpose');
                    const withdrawalSlipStatus = this.getAttribute('data-withdrawal-slip-status');
                    const withdrawalSlipRemarks = this.getAttribute('data-withdrawal-slip-remarks');
                    
                    // FIX 1: Add status badge next to Withdrawal Slip Number
                    const statusBadgeClass = getWithdrawalSlipStatusBadgeClass(withdrawalSlipStatus.toLowerCase());
                    document.getElementById('modal-withdrawal-slip-number').innerHTML = withdrawalSlipNumber + ' <span class="badge ' + statusBadgeClass + '">' + withdrawalSlipStatus + '</span>';
                    
                    document.getElementById('modal-withdrawal-slip-date').textContent = withdrawalSlipDate;
                    document.getElementById('modal-requested-by').textContent = requestedBy;
                    document.getElementById('modal-employee').textContent = employee || 'N/A';
                    document.getElementById('modal-ws-purpose').textContent = purpose;
                    document.getElementById('modal-withdrawal-slip-remarks').textContent = withdrawalSlipRemarks || '-';
                    
                    // Clear previous items
                    const itemsContainer = document.getElementById('modal-withdrawal-slip-items');
                    itemsContainer.innerHTML = '';
                    
                    // Get Withdrawal Slip items from PHP data
                    const withdrawalSlipItems = <?php echo json_encode($withdrawal_slip_details['items'] ?? []); ?>;
                    
                    if (withdrawalSlipItems && withdrawalSlipItems.length > 0) {
                        withdrawalSlipItems.forEach(item => {
                            const status = item.status || 'pending';
                            const quantity = parseFloat(item.quantity);
                            
                            // FIX: Get unit_cost and total_cost from the item data
                            // If not available in the item, calculate from available data
                            let unitCost = parseFloat(item.unit_cost) || 0;
                            let totalCost = parseFloat(item.total_cost) || (unitCost * quantity);
                            
                            // If totalCost is still 0, calculate from other sources if available
                            if (totalCost === 0 && unitCost > 0) {
                                totalCost = unitCost * quantity;
                            }
                            
                            const row = document.createElement('tr');
                            
                            // Determine column span and content based on request type
                            <?php if ($is_issue_materials): ?>
                            // For Issue Materials - no Vehicle/Equipment column
                            row.innerHTML = `
                                <td>${item.part_name || 'N/A'} (${item.part_number || 'N/A'})</td>
                                <td>${item.category_name || 'N/A'}</td>
                                <td>${quantity.toFixed(2)}</td>
                                <td>${formatCurrency(unitCost)}</td>
                                <td>${formatCurrency(totalCost)}</td>
                                <td>
                                    <span class="badge ${getWithdrawalSlipStatusBadge(status)}">
                                        ${status}
                                    </span>
                                </td>
                            `;
                            <?php else: ?>
                            // For Issue Parts - include Vehicle/Equipment column
                            row.innerHTML = `
                                <td>${item.part_name || 'N/A'} (${item.part_number || 'N/A'})</td>
                                <td>${item.category_name || 'N/A'}</td>
                                <td>${quantity.toFixed(2)}</td>
                                <td>${formatCurrency(unitCost)}</td>
                                <td>${formatCurrency(totalCost)}</td>
                                <td><?php echo htmlspecialchars($pr['vehicle_name'] ?? $pr['equipment_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge ${getWithdrawalSlipStatusBadge(status)}">
                                        ${status}
                                    </span>
                                </td>
                            `;
                            <?php endif; ?>
                            
                            itemsContainer.appendChild(row);
                        });
                    } else {
                        <?php if ($is_issue_materials): ?>
                        // For Issue Materials: Part Name, Category, Quantity, Unit Cost, Total Cost, Status
                        let colspan = 6;
                        <?php else: ?>
                        // For Issue Parts: Part Name, Category, Quantity, Unit Cost, Total Cost, Vehicle/Equipment, Status
                        let colspan = 7;
                        <?php endif; ?>

                        itemsContainer.innerHTML = `
                            <tr>
                                <td colspan="${colspan}" class="text-center py-3">
                                    <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                    No items found for this withdrawal slip.
                                </td>
                            </tr>
                        `;
                    }
                    
                    viewWithdrawalSlipModal.show();
                });
            });
            
            function getWithdrawalSlipStatusBadge(status) {
                switch(status) {
                    case 'draft': return 'bg-info';
                    case 'pending': return 'bg-warning';
                    case 'approved': return 'bg-success';
                    case 'released': return 'bg-success';
                    case 'completed': return 'bg-success';
                    case 'cancelled': return 'bg-danger';
                    default: return 'bg-secondary';
                }
            }
            
            // Helper function for status badge class
            function getWithdrawalSlipStatusBadgeClass(status) {
                switch(status) {
                    case 'draft': return 'bg-info';
                    case 'pending': return 'bg-warning';
                    case 'approved': return 'bg-success';
                    case 'released': return 'bg-success';
                    case 'completed': return 'bg-success';
                    case 'cancelled': return 'bg-danger';
                    default: return 'bg-secondary';
                }
            }
        });

        // Make sure to add the formatCurrency function if it doesn't exist
        function formatCurrency(amount) {
            return '₱' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // Edit PO Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const editButtons = document.querySelectorAll('.edit-po-btn');
            const editModal = new bootstrap.Modal(document.getElementById('editPOModal'));
            const editForm = document.getElementById('editPOForm');
            
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const poId = this.getAttribute('data-po-id');
                    const poNumber = this.getAttribute('data-po-number');
                    const poDate = this.getAttribute('data-po-date');
                    const expectedDelivery = this.getAttribute('data-expected-delivery');
                    const totalAmount = this.getAttribute('data-total-amount');
                    const poStatus = this.getAttribute('data-po-status');
                    const poRemarks = this.getAttribute('data-po-remarks');
                    
                    // Set basic PO information
                    document.getElementById('edit_po_id').value = poId;
                    document.getElementById('edit-modal-po-number').textContent = poNumber;
                    document.getElementById('edit-modal-po-date').textContent = poDate;
                    document.getElementById('edit-modal-expected-delivery').textContent = expectedDelivery;
                    document.getElementById('edit-modal-total-amount').textContent = '₱' + formatNumberWithCommas(parseFloat(totalAmount).toFixed(2));
                    document.getElementById('edit-modal-po-status').textContent = poStatus;
                    document.getElementById('edit-modal-po-remarks').textContent = poRemarks || '-';
                    
                    // Clear previous items
                    const itemsContainer = document.getElementById('edit-modal-po-items');
                    itemsContainer.innerHTML = '';
                    
                    // Get PO items from PHP data
                    const poItems = <?php echo json_encode($po_items_details); ?>;
                    
                    if (poItems[poId] && poItems[poId].length > 0) {
                        poItems[poId].forEach(item => {
                            const unitCost = parseFloat(item.unit_cost);
                            const currentTotal = parseFloat(item.total_cost);
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.part_number || 'N/A'}</td>
                                <td>${item.part_name || 'N/A'}</td>
                                <td>${item.category_name || 'N/A'}</td>
                                <td>${parseFloat(item.quantity)}</td>
                                <td>
                                    <input type="number" 
                                           name="new_quantity" 
                                           value="${parseFloat(item.quantity)}"
                                           min="1" 
                                           class="form-control form-control-sm"
                                           data-po-item-id="${item.id}"
                                           data-original-value="${parseFloat(item.quantity)}"
                                           required>
                                    <input type="hidden" name="po_item_id" value="${item.id}">
                                </td>
                                <td>${formatCurrency(unitCost)}</td>
                                <td>${formatCurrency(currentTotal)}</td>
                            `;
                            itemsContainer.appendChild(row);
                        });
                    } else {
                        itemsContainer.innerHTML = `
                            <tr>
                                <td colspan="7" class="text-center py-3">
                                    <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                    No items found for this purchase order.
                                </td>
                            </tr>
                        `;
                    }
                    
                    editModal.show();
                });
            });
            
            // Handle edit form submission
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    // Validate that at least one quantity is changed
                    let hasChanges = false;
                    const quantityInputs = document.querySelectorAll('#edit-modal-po-items input[type="number"]');
                    
                    quantityInputs.forEach(input => {
                        const originalValue = input.getAttribute('data-original-value');
                        if (input.value !== originalValue) {
                            hasChanges = true;
                        }
                    });
                    
                    if (!hasChanges) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'No Changes',
                            text: 'No quantity changes detected. Please modify at least one quantity before submitting.',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }
        });

        // Date handling for forms
        document.addEventListener('DOMContentLoaded', function() {
            // Set today's date in yyyy-mm-dd format (HTML5 date input format)
            const today = new Date();
            const todayFormatted = today.toISOString().split('T')[0];
            
            // Set expected delivery field (for PO) - default to today + 7 days
            const expectedDeliveryField = document.getElementById('expected_delivery');
            if (expectedDeliveryField) {
                const nextWeek = new Date();
                nextWeek.setDate(today.getDate() + 7);
                expectedDeliveryField.value = nextWeek.toISOString().split('T')[0];
            }
            
            // Set job order date field - default to today
            const jobOrderDateField = document.getElementById('job_order_date');
            if (jobOrderDateField) {
                jobOrderDateField.value = todayFormatted;
            }
            
            // Set withdrawal slip date field - default to today
            const withdrawalSlipDateField = document.getElementById('withdrawal_slip_date');
            if (withdrawalSlipDateField) {
                withdrawalSlipDateField.value = todayFormatted;
            }
            
            // Set minimum date for all date inputs to today
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                input.min = todayFormatted;
            });
        });

        // Logout function
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