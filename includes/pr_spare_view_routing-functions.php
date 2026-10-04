<?php
/**
 * includes/pr_spare_view_routing-functions.php
 *
 * The helpers pr_spare_view_routing.php and its actions file share: the document-number generators,
 * the date, request-type and status formatters, the stock-availability check, and
 * getStatusBadge() and formatUserName(), which the markup calls too.
 *
 * They live in their own file because both entry points need them. The routing handlers
 * call several of them after they write; the page's template calls the rest while
 * rendering. They must therefore be loaded before whichever runs first, which is why
 * actions/pr_spare_view_routing-actions.php and api/pr_spare_view_routing-endpoint.php both require this file.
 *
 * The bodies below are lifted verbatim from pr_spare_view_routing.php. Nothing here prints, so this
 * file cannot disturb the page's output.
 */

if (defined('OCP_PR_SPARE_VIEW_ROUTING_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_PR_SPARE_VIEW_ROUTING_FUNCTIONS_LOADED', true);

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
            return 'badge badge-primary';
        case 'issue':
            return 'badge badge-success';
        case 'issue_materials':
            return 'badge badge-warning';
        default:
            return 'badge badge-neutral';
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


// Fetches a spare parts request's items, with the stock availability each routing decision is
// made against. Both api/pr_spare_view_routing-endpoint.php, which renders them, and
// actions/pr_spare_view_routing-actions.php, which turns the chosen ones into spare_part_po_items,
// spare_parts_job_order_items and spare_parts_withdrawal_slip_items rows, call it.
//
// The handler needs the same array the page showed, and it runs *before* the endpoint is
// loaded, so it cannot read the endpoint's $items. Without this the three create actions ran
// their lookup loop over a null $items, found no matching row for any selected item, skipped
// every insert, and still reported "Purchase Order ... created successfully" - the PO header
// was written and spare_part_po_items stayed empty.
function getSparePartsRoutingItems($pdo, $pr_id, $is_issue_type) {
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

    // The stock fields the handlers read. For issue requests the stock check decides how much
    // of each part a job order or withdrawal slip may carry; for stock requests there is
    // nothing on hand to draw, so the same keys are present and read zero.
    foreach ($items as &$item) {
        if ($is_issue_type) {
            $item['stock_info'] = checkStockAvailability($pdo, $item['part_id'], $item['quantity'], $item['id']);
        } else {
            $item['stock_info'] = [
                'available' => false,
                'stock_quantity' => 0,
                'enough_stock' => false,
                'message' => 'N/A (Stock Purchase)',
                'status' => 'N/A'
            ];
        }
    }
    unset($item);

    return $items;
}

function getStatusBadge($status) {
    switch ($status) {
        case 'approved':
        case 'Fully Available':
        case 'Fully Delivered':
        case 'Delivered':
            return 'badge badge-success';
        case 'rejected':
        case 'Rejected':
        case 'Out of Stock':
            return 'badge badge-danger';
        case 'pending':
        case 'Pending':
            return 'badge badge-warning';
        case 'processing':
        case 'Processing':
        case 'Partially Available':
        case 'Partially Delivered':
            return 'badge badge-info';
        case 'completed':
        case 'Completed':
            return 'badge badge-primary';
        default:
            return 'badge badge-neutral';
    }
}

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
