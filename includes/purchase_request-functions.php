<?php
/**
 * includes/purchase_request-functions.php
 *
 * The two helpers purchase_request.php and its actions file share: generatePRNumber(),
 * which builds the next PR number, and checkStockAvailability(), which tests the
 * chosen items against a warehouse's stock before a request is saved.
 *
 * They live in their own file because both entry points need them. The page calls
 * generatePRNumber() while rendering the form; the handlers call both. They must
 * therefore be loaded before whichever runs first, which is why
 * actions/purchase_request-actions.php and api/purchase_request-endpoint.php both
 * require this file.
 *
 * The bodies below are lifted verbatim from purchase_request.php; nothing changed.
 */

if (defined('OCP_PURCHASE_REQUEST_FUNCTIONS_LOADED')) {
    return;
}
define('OCP_PURCHASE_REQUEST_FUNCTIONS_LOADED', true);
// Generate PR number
function generatePRNumber($pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchase_requests WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sequence = $result['count'] + 1;
    return "PR-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

/**
 * Check stock availability for items and determine document type
 * Returns array with document_type and stock_status details
 */
function checkStockAvailability($pdo, $items, $warehouse_id) {
    $total_items = count($items);
    $insufficient_stock = 0;
    $out_of_stock = 0;
    $stock_details = [];
    
    foreach ($items as $item) {
        if (empty($item['item_id']) || empty($item['quantity'])) {
            continue;
        }
        
        $item_id = $item['item_id'];
        $requested_qty = $item['quantity'];
        
        // Check current stock in warehouse
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) as total_stock 
            FROM inventory 
            WHERE item_id = ? AND warehouse_id = ? AND quantity > 0
        ");
        $stmt->execute([$item_id, $warehouse_id]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        $available_stock = $stock['total_stock'];
        
        $stock_details[] = [
            'item_id' => $item_id,
            'requested' => $requested_qty,
            'available' => $available_stock
        ];
        
        if ($available_stock == 0) {
            $out_of_stock++;
        } elseif ($available_stock < $requested_qty) {
            $insufficient_stock++;
        }
    }
    
    // Determine document type based on stock status
    if ($out_of_stock == $total_items) {
        $document_type = 'pr_po'; // All items out of stock
    } elseif ($insufficient_stock > 0 || $out_of_stock > 0) {
        $document_type = 'po_ws'; // Some items insufficient or out of stock
    } else {
        $document_type = 'ws'; // All items have sufficient stock
    }
    
    return [
        'document_type' => $document_type,
        'total_items' => $total_items,
        'insufficient_stock' => $insufficient_stock,
        'out_of_stock' => $out_of_stock,
        'stock_details' => $stock_details
    ];
}
