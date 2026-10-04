<?php
/**
 * api/pr_spare_view_routing-endpoint.php
 *
 * Every read for pr_spare_view_routing.php lives in this one file: the request being routed with its
 * items, the stock position of each one, what documents already exist for it, and the
 * flags that decide which of the routing buttons the page offers.
 *
 * The page pulls this in instead of querying the database itself, so all of the page's
 * fetching is in one place. It runs in the page's scope and returns an array of the
 * variables the markup and the handlers need; the page unpacks that array.
 *
 * The read block below is lifted verbatim from pr_spare_view_routing.php. It is wrapped in a function so
 * that the block - which assigns and reassigns its working variables as it works out
 * what the request can become - keeps its own variables and hands its final state back
 * in one place, rather than each assignment being rewritten to address the return
 * array. One read is a redirect: when the request cannot be fetched, this sets the
 * session message and sends the browser back to the request list, exactly as before.
 *
 * Reads from the page's scope: pdo, pr_id, user
 *
 * Returns
 *   pr
 *   is_issue_type
 *   is_issue_materials
 *   employee_display_name
 *   items
 *   stock_info
 *   item
 *   delivery_info
 *   pr_item_delivered_quantity
 *   remaining_needed
 *   fulfillable_quantity
 *   available_batches
 *   quantity_needed
 *   total_fifo_cost
 *   remaining_to_calculate
 *   batches_to_use
 *   batch_quantity
 *   batch_price
 *   quantity_from_this_batch
 *   cost_from_this_batch
 *   fifo_unit_cost
 *   total_estimated_cost
 *   routing_history
 *   history
 *   current_stage_result
 *   current_stage
 *   existing_pos_raw
 *   existing_pos
 *   po
 *   po_items
 *   total_amount
 *   po_items_details
 *   all_suppliers
 *   po_items_for_receiving
 *   latest_po
 *   total_po_amount
 *   items_received_check
 *   items_received_result
 *   items_already_received
 *   items_delivered_check
 *   items_delivered_result
 *   items_already_delivered
 *   items_released_check
 *   items_released_result
 *   items_already_released
 *   releasing_completed_check
 *   releasing_completed_result
 *   releasing_already_completed
 *   withdrawal_slip_exists
 *   withdrawal_slip_details
 *   withdrawal_slip_items
 *   job_order_exists
 *   job_order_details
 *   job_order_items
 *   po_status
 *   po_items_costs
 *   po_exists
 *   is_motorpool_user
 *   all_items_fully_available
 *   has_partially_available_items
 *   has_out_of_stock_items
 */

$ocp_endpoint = [
    'pr' => null,
    'is_issue_type' => false,
    'is_issue_materials' => false,
    'employee_display_name' => [],
    'items' => [],
    'stock_info' => [],
    'item' => [],
    'delivery_info' => [],
    'pr_item_delivered_quantity' => [],
    'remaining_needed' => [],
    'fulfillable_quantity' => [],
    'available_batches' => [],
    'quantity_needed' => [],
    'total_fifo_cost' => [],
    'remaining_to_calculate' => [],
    'batches_to_use' => [],
    'batch_quantity' => [],
    'batch_price' => [],
    'quantity_from_this_batch' => [],
    'cost_from_this_batch' => [],
    'fifo_unit_cost' => [],
    'total_estimated_cost' => [],
    'routing_history' => [],
    'history' => [],
    'current_stage_result' => [],
    'current_stage' => null,
    'existing_pos_raw' => [],
    'existing_pos' => [],
    'po' => null,
    'po_items' => [],
    'total_amount' => 0,
    'po_items_details' => [],
    'all_suppliers' => [],
    'po_items_for_receiving' => [],
    'latest_po' => null,
    'total_po_amount' => [],
    'items_received_check' => [],
    'items_received_result' => [],
    'items_already_received' => [],
    'items_delivered_check' => [],
    'items_delivered_result' => [],
    'items_already_delivered' => [],
    'items_released_check' => [],
    'items_released_result' => [],
    'items_already_released' => [],
    'releasing_completed_check' => [],
    'releasing_completed_result' => [],
    'releasing_already_completed' => [],
    'withdrawal_slip_exists' => false,
    'withdrawal_slip_details' => [],
    'withdrawal_slip_items' => [],
    'job_order_exists' => false,
    'job_order_details' => [],
    'job_order_items' => [],
    'po_status' => null,
    'po_items_costs' => [],
    'po_exists' => false,
    'is_motorpool_user' => false,
    'all_items_fully_available' => false,
    'has_partially_available_items' => false,
    'has_out_of_stock_items' => false,
];

// Standalone guard: the read runs in the page's scope, where the connection is already
// open. Requested on its own there is none, so it answers with the empty result.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

require_once __DIR__ . '/../includes/pr_spare_view_routing-functions.php';

$ocp_read = function ($pdo, $pr_id, $user) {
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
            // Ties are broken by id: created_at is stored to the second, so a request moved
            // twice in the same second has two rows with the same value, and ordering by the
            // timestamp alone can return the older one - showing the page a stage the request
            // had already left.
            $currentStageStmt = $pdo->prepare("
                SELECT stage, status FROM spare_parts_pr_routing 
                WHERE pr_id = ? 
                ORDER BY created_at DESC, id DESC 
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


    // Hand back the block's own variables, which is its final state. The names carried
    // in are dropped so the caller's own values are not overwritten by copies - and the
    // scratch names, including this array itself, with them.
    $ocp_out = get_defined_vars();
    foreach (['pdo', 'pr_id', 'user', 'ocp_out', 'ocp_read', 'ocp_key', 'ocp_value'] as $ocp_drop) {
        unset($ocp_out[$ocp_drop]);
    }
    unset($ocp_out['ocp_drop']);
    return $ocp_out;
};

foreach ($ocp_read($pdo, $pr_id, $user) as $ocp_key => $ocp_value) {
    $ocp_endpoint[$ocp_key] = $ocp_value;
}
unset($ocp_read, $ocp_key, $ocp_value);

// This page has no warehouse flag: its routing turns on $is_motorpool_user, which the
// block above computes from $user and hands back with everything else.

return $ocp_endpoint;
