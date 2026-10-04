<?php
/**
 * actions/pr_view_routing-actions.php
 *
 * Every action for pr_view_routing.php lives in this one file: the routing decisions - approving,
 * rejecting, converting a request into a purchase order or a withdrawal slip, and the
 * per-item stock handling that goes with them.
 *
 * The page pulls this file in at the top, so it runs in the page's scope - which is what
 * gives it $pr_id, $user, $user_id, $is_warehouse_user and the fetched request it routes.
 *
 * The helpers it calls live in includes/pr_view_routing-functions.php, required here so they are
 * defined whichever entry point runs first.
 *
 * The block below is lifted verbatim from pr_view_routing.php: the queries, the routing rules and
 * the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_PR_VIEW_ROUTING_ACTIONS_RAN')) {
    return;
}
define('OCP_PR_VIEW_ROUTING_ACTIONS_RAN', true);


require_once __DIR__ . '/../includes/pr_view_routing-functions.php';

// The routing rules below authorize against the request and its current stage:
//     $current_stage['stage'] === 'requestor' && $pr['requested_by'] == $user_id
// Both are read from the page's scope. They are also computed by
// api/pr_view_routing-endpoint.php - but the page loads that file AFTER this one, so on a
// POST they did not exist yet, the tests read NULL, and every action was refused with
// "You are not authorized to perform this action." even for the user who raised the
// request. Fetch them here when the page has not already provided them.
if (!isset($user_id) || $user_id === '' || $user_id === null) {
    $user_id = $_SESSION['user_id'] ?? 0;
}
if (!isset($pr_id) || $pr_id === '' || $pr_id === null) {
    $pr_id = $_POST['pr_id'] ?? $_GET['id'] ?? 0;
}
if (!isset($current_stage) || !is_array($current_stage)) {
    $ocp_stage_stmt = $pdo->prepare("SELECT stage, status FROM pr_routing WHERE pr_id = ? ORDER BY created_at DESC, id DESC LIMIT 1");
    $ocp_stage_stmt->execute([$pr_id]);
    $ocp_stage_row = $ocp_stage_stmt->fetch(PDO::FETCH_ASSOC);
    // No routing row yet means the request is still with whoever raised it.
    $current_stage = $ocp_stage_row ?: ['stage' => 'requestor', 'status' => 'pending'];
    unset($ocp_stage_stmt, $ocp_stage_row);
}
if (!isset($pr) || !is_array($pr)) {
    $ocp_pr_stmt = $pdo->prepare("SELECT * FROM purchase_requests WHERE id = ?");
    $ocp_pr_stmt->execute([$pr_id]);
    $pr = $ocp_pr_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    unset($ocp_pr_stmt);
}

// The rest of the state the routing rules read, all of which was computed above the switch in
// the original single-file page and now lives in api/pr_view_routing-endpoint.php - a file the
// page requires AFTER this one. On a POST none of it exists yet, and every test on it reads
// null: `!$po_exists` is true whatever the truth is, so approve_purchasing refused a request
// whose purchase order was sitting right there, and the document_type branches all took their
// else path. These are fetched here, each only when the page has not already supplied it.
//   $request_type, $document_type   which flow this request follows
//   $po_exists, $ws_exists          whether the documents it needs have been raised
//   $existing_pos, $existing_ws     the documents themselves, newest first
//   $ws_approved, $ws_already_processed, $items_already_received, $is_warehouse_user
//   $threshold_amount_adjusted, $total_po_amount   the project threshold checks
if (!isset($request_type) || $request_type === '') {
    $request_type = $pr['request_type'] ?? '';
}
if (!isset($document_type) || $document_type === '') {
    // the endpoint defaults to pr_po when the column is empty, and so does this
    $document_type = ($pr['document_type'] ?? '') !== '' ? $pr['document_type'] : 'pr_po';
}
if (!isset($po_exists)) {
    $ocp_po_stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE pr_id = ?");
    $ocp_po_stmt->execute([$pr_id]);
    $po_exists = ((int) $ocp_po_stmt->fetchColumn()) > 0;
    unset($ocp_po_stmt);
}
if (!isset($ws_exists)) {
    $ocp_ws_stmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawal_slips WHERE pr_id = ?");
    $ocp_ws_stmt->execute([$pr_id]);
    $ws_exists = ((int) $ocp_ws_stmt->fetchColumn()) > 0;
    unset($ocp_ws_stmt);
}
if (!isset($existing_pos) || !is_array($existing_pos)) {
    $ocp_pos_stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE pr_id = ? ORDER BY id DESC");
    $ocp_pos_stmt->execute([$pr_id]);
    $existing_pos = $ocp_pos_stmt->fetchAll(PDO::FETCH_ASSOC);
    unset($ocp_pos_stmt);
}
if (!isset($existing_ws) || !is_array($existing_ws)) {
    $ocp_ws_rows_stmt = $pdo->prepare("SELECT * FROM withdrawal_slips WHERE pr_id = ? ORDER BY id DESC");
    $ocp_ws_rows_stmt->execute([$pr_id]);
    $existing_ws = $ocp_ws_rows_stmt->fetchAll(PDO::FETCH_ASSOC);
    unset($ocp_ws_rows_stmt);
}
if (!isset($ws_approved)) {
    $ws_approved = false;
    foreach ($existing_ws as $ocp_ws_row) {
        if (in_array($ocp_ws_row['status'] ?? '', ['approved', 'released', 'processing', 'completed'], true)) {
            $ws_approved = true;
            break;
        }
    }
    unset($ocp_ws_row);
}
if (!isset($is_warehouse_user)) {
    $is_warehouse_user = (($user['department'] ?? '') === 'Warehouse' && ($user['accounttype'] ?? '') === 'Admin');
}
if (!isset($total_po_amount)) {
    $ocp_total_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_orders WHERE pr_id = ?");
    $ocp_total_stmt->execute([$pr_id]);
    $total_po_amount = (float) $ocp_total_stmt->fetchColumn();
    unset($ocp_total_stmt);
}
if (!isset($threshold_amount)) {
    // the project's threshold, which adjust_threshold_amount adds to and subtracts from. The
    // endpoint computes it for the markup, but the page loads this file first, so on a POST it
    // was still unset and the subtraction test below read null.
    $threshold_amount = 0;
    if ($request_type === 'project' && !empty($pr['project_id'])) {
        $ocp_threshold_stmt = $pdo->prepare("SELECT threshold_amount FROM projects WHERE id = ?");
        $ocp_threshold_stmt->execute([$pr['project_id']]);
        $threshold_amount = (float) $ocp_threshold_stmt->fetchColumn();
        unset($ocp_threshold_stmt);
    }
}
if (!isset($threshold_amount_adjusted)) {
    // whether the threshold was already adjusted for this request
    $ocp_adj_stmt = $pdo->prepare("SELECT COUNT(*) FROM pr_routing_history WHERE pr_id = ? AND action LIKE '%threshold%'");
    $ocp_adj_stmt->execute([$pr_id]);
    $threshold_amount_adjusted = ((int) $ocp_adj_stmt->fetchColumn()) > 0;
    unset($ocp_adj_stmt);
}
if (!isset($items_already_received)) {
    // whether the goods have already been booked in, which decides whether they can be again
    $ocp_recv_stmt = $pdo->prepare("SELECT COUNT(*) FROM pr_routing_history WHERE pr_id = ? AND action = 'Items Received'");
    $ocp_recv_stmt->execute([$pr_id]);
    $items_already_received = ((int) $ocp_recv_stmt->fetchColumn()) > 0;
    unset($ocp_recv_stmt);
}
if (!isset($ws_already_processed)) {
    // whether the withdrawal slip has already been actioned
    $ocp_wsp_stmt = $pdo->prepare("SELECT COUNT(*) FROM pr_routing_history WHERE pr_id = ? AND action = 'Withdrawal Slip Processed'");
    $ocp_wsp_stmt->execute([$pr_id]);
    $ws_already_processed = ((int) $ocp_wsp_stmt->fetchColumn()) > 0;
    unset($ocp_wsp_stmt);
}

// The request's items, sorted into the same groups the page renders: what stock covers, what is
// short, and what has to be ordered. create_purchase_order writes one po_items row per chosen
// item after looking it up in $items_for_po, and create_withdrawal_slip does the same against
// $items_for_withdrawal - but both arrays were built in api/pr_view_routing-endpoint.php, which
// the page requires AFTER this file. On the POST that submits those forms neither existed, the
// lookup found nothing, and the loop body never ran: the order or slip was created with no
// items in it. Building them here, from the same shared function the endpoint now uses, is what
// makes the chosen items reach po_items.
if (!isset($items_for_po) || !is_array($items_for_po)
    || !isset($items_for_withdrawal) || !is_array($items_for_withdrawal)) {
    $ocp_groups = ocp_routing_item_groups($pdo, $pr_id, $request_type, $document_type);
    if (!isset($items) || !is_array($items)) { $items = $ocp_groups['items']; }
    $items_with_sufficient_stock = $ocp_groups['items_with_sufficient_stock'];
    $items_with_insufficient_stock = $ocp_groups['items_with_insufficient_stock'];
    $items_with_no_stock = $ocp_groups['items_with_no_stock'];
    $items_for_po = $ocp_groups['items_for_po'];
    $items_for_withdrawal = $ocp_groups['items_for_withdrawal'];
    unset($ocp_groups);
}

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
        
        // Re-reads the request together with the project threshold. threshold_amount lives on
        // projects, not on purchase_requests, so it has to come from the join: the early $pr
        // fetch above is a plain SELECT * FROM purchase_requests and carries no such column.
        // The threshold is re-read after it is changed, by adjust_threshold_amount and by
        // release_ws_items. Preparing it once here is what those two reads call, and without it
        // release_ws_items died on "Call to a member function execute() on null".
        $prStmt = $pdo->prepare("
            SELECT pr.*, p.threshold_amount
            FROM purchase_requests pr
            LEFT JOIN projects p ON pr.project_id = p.id
            WHERE pr.id = ?
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
