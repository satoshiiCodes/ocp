<?php
/**
 * actions/pr_spare_view_routing-actions.php
 *
 * Every action for pr_spare_view_routing.php lives in this one file: the routing decisions - approving,
 * rejecting, converting a request into a purchase order or a withdrawal slip, and the
 * per-item stock handling that goes with them.
 *
 * The page pulls this file in at the top, so it runs in the page's scope - which is what
 * gives it $pr_id, $user, $user_id, $is_warehouse_user and the fetched request it routes.
 *
 * The helpers it calls live in includes/pr_spare_view_routing-functions.php, required here so they are
 * defined whichever entry point runs first.
 *
 * The block below is lifted verbatim from pr_spare_view_routing.php: the queries, the routing rules and
 * the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_PR_SPARE_VIEW_ROUTING_ACTIONS_RAN')) {
    return;
}
define('OCP_PR_SPARE_VIEW_ROUTING_ACTIONS_RAN', true);

require_once __DIR__ . '/../includes/pr_spare_view_routing-functions.php';

// The routing rules below authorize against the request and its current stage:
//     $current_stage['stage'] === 'requestor' && $pr['requested_by'] == $user_id
// Both are read from the page's scope. They are also computed by
// api/pr_spare_view_routing-endpoint.php - but the page loads that file AFTER this one, so
// on a POST they did not exist yet, the tests read NULL, and every action was refused with
// "You are not authorized to perform this action." even for the user who raised the
// request. Fetch them here when the page has not already provided them.
if (!isset($user_id) || $user_id === '' || $user_id === null) {
    $user_id = $_SESSION['user_id'] ?? 0;
}
if (!isset($pr_id) || $pr_id === '' || $pr_id === null) {
    $pr_id = $_POST['pr_id'] ?? $_GET['id'] ?? 0;
}
if (!isset($current_stage) || !is_array($current_stage)) {
    // Ties are broken by id: created_at is stored to the second, so a request moved twice in
    // the same second has two rows with the same value and ordering by the timestamp alone
    // can return the older one - the page is then told the request is still on a stage it has
    // already left, and the next approval refuses itself.
    $ocp_stage_stmt = $pdo->prepare("SELECT stage, status FROM spare_parts_pr_routing WHERE pr_id = ? ORDER BY created_at DESC, id DESC LIMIT 1");
    $ocp_stage_stmt->execute([$pr_id]);
    $ocp_stage_row = $ocp_stage_stmt->fetch(PDO::FETCH_ASSOC);
    // No routing row yet means the request is still with whoever raised it.
    $current_stage = $ocp_stage_row ?: ['stage' => 'requestor', 'status' => 'pending'];
    unset($ocp_stage_stmt, $ocp_stage_row);
}
if (!isset($pr) || !is_array($pr)) {
    $ocp_pr_stmt = $pdo->prepare("SELECT * FROM spare_parts_pr WHERE id = ?");
    $ocp_pr_stmt->execute([$pr_id]);
    $pr = $ocp_pr_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    unset($ocp_pr_stmt);
}

// The rest of the state the routing rules read, all of which is computed by
// api/pr_spare_view_routing-endpoint.php - a file the page requires AFTER this one. On a POST
// none of it exists yet, and every test on it reads null:
//   $items                     the request's lines, which the create/ receive/ release handlers
//                              look each submitted id up in
//   $is_issue_type             issue and issue_materials flows, against issue_materials alone
//   $po_exists, $job_order_exists, $withdrawal_slip_exists
//   $existing_pos, $latest_po, $job_order_details, $withdrawal_slip_details
//   $items_already_received, $items_already_released, $releasing_already_completed
// These are fetched here, each only when the page has not already supplied it.
if (!isset($is_issue_type)) {
    $is_issue_type = in_array($pr['request_type'] ?? '', ['issue', 'issue_materials'], true);
}
if (!isset($is_issue_materials)) {
    $is_issue_materials = (($pr['request_type'] ?? '') === 'issue_materials');
}
if (!isset($items) || !is_array($items)) {
    // The same array the page renders and the handlers write from. Without it the create
    // actions below matched no submitted id, inserted no spare_part_po_items row, and still
    // reported success.
    $items = getSparePartsRoutingItems($pdo, $pr_id, $is_issue_type);
}
if (!isset($existing_pos) || !is_array($existing_pos)) {
    $ocp_pos_stmt = $pdo->prepare("SELECT * FROM spare_part_po WHERE pr_id = ? ORDER BY id DESC");
    $ocp_pos_stmt->execute([$pr_id]);
    $existing_pos = $ocp_pos_stmt->fetchAll(PDO::FETCH_ASSOC);
    unset($ocp_pos_stmt);
}
if (!isset($po_exists)) {
    $po_exists = !empty($existing_pos);
}
if (!isset($latest_po)) {
    $latest_po = $existing_pos[0] ?? null;
}
if (!isset($job_order_exists)) {
    $job_order_exists = false;
    $job_order_details = [];
    // Job orders carry issue requests, as opposed to issue_materials, which are withdrawn.
    if ($is_issue_type && !$is_issue_materials) {
        $ocp_jo_stmt = $pdo->prepare("SELECT * FROM spare_parts_job_orders WHERE pr_id = ?");
        $ocp_jo_stmt->execute([$pr_id]);
        $job_order_details = $ocp_jo_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $job_order_exists = !empty($job_order_details);
        unset($ocp_jo_stmt);
    }
}
if (!isset($withdrawal_slip_exists)) {
    $withdrawal_slip_exists = false;
    $withdrawal_slip_details = [];
    if ($is_issue_materials) {
        $ocp_ws_stmt = $pdo->prepare("SELECT * FROM spare_parts_withdrawal_slips WHERE pr_id = ?");
        $ocp_ws_stmt->execute([$pr_id]);
        $withdrawal_slip_details = $ocp_ws_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $withdrawal_slip_exists = !empty($withdrawal_slip_details);
        unset($ocp_ws_stmt);
    }
}
if (!isset($items_already_received)) {
    $ocp_recv_stmt = $pdo->prepare("SELECT COUNT(*) FROM spare_parts_pr_routing_history WHERE pr_id = ? AND action LIKE '%Items Received%'");
    $ocp_recv_stmt->execute([$pr_id]);
    $items_already_received = ((int) $ocp_recv_stmt->fetchColumn()) > 0;
    unset($ocp_recv_stmt);
}
if (!isset($items_already_released)) {
    $ocp_rel_stmt = $pdo->prepare("SELECT COUNT(*) FROM spare_parts_pr_routing_history WHERE pr_id = ? AND action LIKE '%Items Released%'");
    $ocp_rel_stmt->execute([$pr_id]);
    $items_already_released = ((int) $ocp_rel_stmt->fetchColumn()) > 0;
    unset($ocp_rel_stmt);
}
if (!isset($releasing_already_completed)) {
    $ocp_relc_stmt = $pdo->prepare("SELECT COUNT(*) FROM spare_parts_pr_routing_history WHERE pr_id = ? AND action = 'Completed Motorpool Releasing'");
    $ocp_relc_stmt->execute([$pr_id]);
    $releasing_already_completed = ((int) $ocp_relc_stmt->fetchColumn()) > 0;
    unset($ocp_relc_stmt);
}

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
