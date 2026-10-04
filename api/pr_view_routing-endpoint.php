<?php
/**
 * api/pr_view_routing-endpoint.php
 *
 * Every read for pr_view_routing.php lives in this one file: the request being routed with its
 * items, the stock position of each one, what documents already exist for it, and the
 * flags that decide which of the routing buttons the page offers.
 *
 * The page pulls this in instead of querying the database itself, so all of the page's
 * fetching is in one place. It runs in the page's scope and returns an array of the
 * variables the markup and the handlers need; the page unpacks that array.
 *
 * The read block below is lifted verbatim from pr_view_routing.php. It is wrapped in a function so
 * that the block - which assigns and reassigns its working variables as it works out
 * what the request can become - keeps its own variables and hands its final state back
 * in one place, rather than each assignment being rewritten to address the return
 * array. One read is a redirect: when the request cannot be fetched, this sets the
 * session message and sends the browser back to the request list, exactly as before.
 *
 * Reads from the page's scope: pdo, pr_id
 *
 * Returns
 *   pr
 *   document_type
 *   request_type
 *   flow_type
 *   items
 *   items_with_sufficient_stock
 *   items_with_insufficient_stock
 *   items_with_no_stock
 *   items_for_po
 *   items_for_withdrawal
 *   remaining_needed
 *   current_stock
 *   quantity_to_order
 *   show_withdrawal_slip_button
 *   show_po_button
 *   po_exists_check
 *   po_exists_result
 *   po_exists
 *   ws_exists_check
 *   ws_exists_result
 *   ws_exists
 *   ws_approved
 *   ws_released
 *   ws_status_data
 *   routing_history
 *   current_stage_result
 *   current_stage
 *   existing_pos_raw
 *   existing_ws_raw
 *   existing_pos
 *   po_items
 *   total_amount
 *   po
 *   existing_ws
 *   ws_items
 *   ws
 *   po_items_details
 *   ws_items_details
 *   all_suppliers
 *   po_items_for_receiving
 *   latest_po
 *   ws_items_for_releasing
 *   latest_ws
 *   total_po_amount
 *   threshold_amount
 *   threshold_status
 *   items_received_check
 *   items_received_result
 *   items_already_received
 *   threshold_adjusted_check
 *   threshold_adjusted_result
 *   threshold_amount_adjusted
 *   ws_processed_check
 *   ws_processed_result
 *   ws_already_processed
 *   po_status
 *   ws_status
 *   is_warehouse_user
 */

$ocp_endpoint = [
    'pr' => null,
    'document_type' => null,
    'request_type' => null,
    'flow_type' => null,
    'items' => [],
    'items_with_sufficient_stock' => [],
    'items_with_insufficient_stock' => [],
    'items_with_no_stock' => [],
    'items_for_po' => [],
    'items_for_withdrawal' => [],
    'remaining_needed' => [],
    'current_stock' => [],
    'quantity_to_order' => [],
    'show_withdrawal_slip_button' => false,
    'show_po_button' => false,
    'po_exists_check' => [],
    'po_exists_result' => [],
    'po_exists' => false,
    'ws_exists_check' => [],
    'ws_exists_result' => [],
    'ws_exists' => false,
    'ws_approved' => [],
    'ws_released' => [],
    'ws_status_data' => [],
    'routing_history' => [],
    'current_stage_result' => [],
    'current_stage' => null,
    'existing_pos_raw' => [],
    'existing_ws_raw' => [],
    'existing_pos' => [],
    'po_items' => [],
    'total_amount' => 0,
    'po' => null,
    'existing_ws' => [],
    'ws_items' => [],
    'ws' => null,
    'po_items_details' => [],
    'ws_items_details' => [],
    'all_suppliers' => [],
    'po_items_for_receiving' => [],
    'latest_po' => null,
    'ws_items_for_releasing' => [],
    'latest_ws' => null,
    'total_po_amount' => [],
    'threshold_amount' => 0,
    'threshold_status' => null,
    'items_received_check' => [],
    'items_received_result' => [],
    'items_already_received' => [],
    'threshold_adjusted_check' => [],
    'threshold_adjusted_result' => [],
    'threshold_amount_adjusted' => [],
    'ws_processed_check' => [],
    'ws_processed_result' => [],
    'ws_already_processed' => [],
    'po_status' => null,
    'ws_status' => null,
    'is_warehouse_user' => false,
];

// Standalone guard: the read runs in the page's scope, where the connection is already
// open. Requested on its own there is none, so it answers with the empty result.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

require_once __DIR__ . '/../includes/pr_view_routing-functions.php';

$ocp_read = function ($pdo, $pr_id) {
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
        
        // Fetch the request's items and sort them into the groups the markup and the
        // handlers work from. The body of this used to sit here; it moved to
        // includes/pr_view_routing-functions.php as ocp_routing_item_groups() because the
        // actions file needs the same groups and runs before this file is loaded - which is
        // why "Create Purchase Order" produced a purchase order with no po_items rows: it
        // looked each chosen item up in $items_for_po, which did not exist yet.
        $ocp_item_groups = ocp_routing_item_groups($pdo, $pr_id, $request_type, $document_type);
        $items = $ocp_item_groups['items'];
        $items_with_sufficient_stock = $ocp_item_groups['items_with_sufficient_stock'];
        $items_with_insufficient_stock = $ocp_item_groups['items_with_insufficient_stock'];
        $items_with_no_stock = $ocp_item_groups['items_with_no_stock'];
        $items_for_po = $ocp_item_groups['items_for_po'];
        $items_for_withdrawal = $ocp_item_groups['items_for_withdrawal'];
        unset($ocp_item_groups);
        
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
        
        // Get current routing stage. Ties are broken by id because created_at is a
        // second-precision TIMESTAMP: a request moved twice within the same second has two
        // rows with the same value, and ordered by created_at alone the newest is then
        // whichever the engine returns - which showed the page a stage the request had
        // already left, so the next approval refused itself.
        $currentStageStmt = $pdo->prepare("
            SELECT stage, status FROM pr_routing 
            WHERE pr_id = ? 
            ORDER BY created_at DESC, id DESC 
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


    // Hand back the block's own variables, which is its final state. The names carried
    // in are dropped so the caller's own values are not overwritten by copies - and the
    // scratch names, including this array itself, with them. They are dropped in a loop
    // rather than one unset() list, because unsetting $ocp_out inside its own unset()
    // destroys it before the return reads it.
    $ocp_out = get_defined_vars();
    foreach (['pdo', 'pr_id', 'ocp_out', 'ocp_read', 'ocp_key', 'ocp_value', 'ocp_drop'] as $ocp_drop) {
        unset($ocp_out[$ocp_drop]);
    }
    return $ocp_out;
};

foreach ($ocp_read($pdo, $pr_id) as $ocp_key => $ocp_value) {
    $ocp_endpoint[$ocp_key] = $ocp_value;
}
unset($ocp_read, $ocp_key, $ocp_value);

$is_warehouse_user = ($user['department'] === 'Warehouse' && $user['accounttype'] === 'Admin');
$ocp_endpoint['is_warehouse_user'] = $is_warehouse_user;

return $ocp_endpoint;
