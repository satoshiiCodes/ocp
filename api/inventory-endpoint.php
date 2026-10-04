<?php
/**
 * api/inventory-endpoint.php
 *
 * Every read for inventory.php lives in this one file: the items, suppliers, projects,
 * subcons, warehouses and employees for its dropdowns, the inventory listing, the
 * movement history, and the single-movement lookup its JavaScript makes.
 *
 * Before reading it recomputes the inventory table, exactly as the page did, so the
 * listing is consistent with the movements on record.
 *
 * The page pulls this in for its listing: it runs in the page's scope and returns an
 * array of the variables the markup needs, which the page unpacks. The lookup is a
 * separate HTTP request from the page's own script, so it is detected here by its
 * "?id=" parameter and answers JSON instead - it lived in api/get_movement_details.php,
 * which this file replaces.
 *
 * Requires the shared helper include, because the recompute is one of those helpers.
 *
 * Returns
 *   items
 *   suppliers
 *   projects
 *   subcons
 *   warehouses
 *   inventory_batches
 *   inventory
 *   low_stock_items
 *   movements
 *   new_batch_number
 *   swal_data
 */

// ---------------------------------------------------------------------------
// The single-movement lookup the page's JavaScript performs: ?id=<movement id>.
// Lifted from api/get_movement_details.php, which this file replaces.
// ---------------------------------------------------------------------------
if (isset($_GET['id'])) {
    $ocp_dir = __DIR__;
    for ($ocp_i = 0; $ocp_i < 4 && !is_file($ocp_dir . '/config/db_config.php'); $ocp_i++) {
        $ocp_parent = dirname($ocp_dir);
        if ($ocp_parent === $ocp_dir) {
            break;
        }
        $ocp_dir = $ocp_parent;
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($pdo)) {
        require_once $ocp_dir . '/config/db_config.php';
    }
    unset($ocp_dir, $ocp_i, $ocp_parent);

    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }


if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

if (isset($_GET['id'])) {
    $movement_id = $_GET['id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT sm.*, i.item_code, i.item_name, 
                   s.supplier_name, p.project_name, 
                   w.warehouse_name, w.location,
                   sm.transfer_from, sm.transfer_to,
                   w_from.warehouse_name as from_warehouse_name,
                   w_to.warehouse_name as to_warehouse_name
            FROM stock_movements sm
            JOIN item_names i ON sm.item_id = i.id
            LEFT JOIN suppliers s ON sm.supplier_id = s.id
            LEFT JOIN projects p ON sm.project_id = p.id
            LEFT JOIN warehouses w ON sm.warehouse_id = w.id
            LEFT JOIN warehouses w_from ON sm.transfer_from = w_from.id
            LEFT JOIN warehouses w_to ON sm.transfer_to = w_to.id
            WHERE sm.id = :id
        ");
        $stmt->bindParam(':id', $movement_id);
        $stmt->execute();
        $movement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($movement) {
            echo json_encode(['success' => true, 'movement' => $movement]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Movement not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
}

    exit();
}

$ocp_endpoint = [
    'items' => [],
    'suppliers' => [],
    'projects' => [],
    'subcons' => [],
    'warehouses' => [],
    'inventory_batches' => [],
    'inventory' => [],
    'low_stock_items' => [],
    'movements' => [],
    'new_batch_number' => '',
    // Seeded from the actions file, so a message it set is not wiped out by the
    // endpoint running after it; the read block overwrites it only on a fetch failure.
    'swal_data' => $swal_data ?? [],
];

// Standalone guard: the read block runs in the page's scope, where the connection is
// already open. Requested on its own there is none, so it answers with the empty result.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

require_once __DIR__ . '/../includes/inventory-functions.php';

// Update all inventory records at the start to ensure data consistency
updateAllInventory($pdo);

// Fetch data for dropdowns and tables
try {
    // Get items with minimum stock level
    $itemsStmt = $pdo->prepare("SELECT id, item_code, item_name, min_stock_level FROM item_names ORDER BY item_name");
    $itemsStmt->execute();
    $ocp_endpoint['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get suppliers
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name");
    $suppliersStmt->execute();
    $ocp_endpoint['suppliers'] = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get projects
    $projectsStmt = $pdo->prepare("SELECT id, project_name, threshold_amount FROM projects ORDER BY project_name");
    $projectsStmt->execute();
    $ocp_endpoint['projects'] = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get subcons
    $subconsStmt = $pdo->prepare("SELECT id, subcon_name FROM subcons ORDER BY subcon_name");
    $subconsStmt->execute();
    $ocp_endpoint['subcons'] = $subconsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get warehouses
    $warehousesStmt = $pdo->prepare("SELECT id, warehouse_name, location FROM warehouses ORDER by warehouse_name");
    $warehousesStmt->execute();
    $ocp_endpoint['warehouses'] = $warehousesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get inventory batches for FIFO tracking
    $batchesStmt = $pdo->prepare("
        SELECT ib.*, i.item_code, i.item_name, w.warehouse_name, w.location, s.supplier_name
        FROM inventory_batches ib
        JOIN item_names i ON ib.item_id = i.id
        JOIN warehouses w ON ib.warehouse_id = w.id
        LEFT JOIN suppliers s ON ib.supplier_id = s.id
        WHERE ib.quantity > 0
        ORDER BY ib.item_id, ib.warehouse_id, ib.received_date DESC, ib.id DESC
    ");
    $batchesStmt->execute();
    $ocp_endpoint['inventory_batches'] = $batchesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get inventory summary from inventory table (now reading from actual inventory table)
    $inventoryStmt = $pdo->prepare("
        SELECT i.id, i.item_code, i.item_name, i.min_stock_level, 
               w.warehouse_name, w.location, 
               inv.quantity,
               inv.unit_cost,
               inv.total_value,
               CASE 
                 WHEN i.min_stock_level > 0 AND inv.quantity <= 0 THEN 'out-of-stock'
                 WHEN i.min_stock_level > 0 AND inv.quantity <= i.min_stock_level THEN 'low-stock'
                 ELSE 'normal'
               END AS stock_status
        FROM inventory inv
        JOIN item_names i ON inv.item_id = i.id
        JOIN warehouses w ON inv.warehouse_id = w.id
        WHERE inv.quantity > 0
        ORDER BY i.item_name, w.warehouse_name
    ");
    $inventoryStmt->execute();
    $ocp_endpoint['inventory'] = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get low stock alerts (from inventory table)
    $lowStockStmt = $pdo->prepare("
        SELECT i.id, i.item_code, i.item_name, i.min_stock_level, 
               w.warehouse_name, w.location, inv.quantity, 
               inv.unit_cost, inv.total_value
        FROM inventory inv
        JOIN item_names i ON inv.item_id = i.id
        JOIN warehouses w ON inv.warehouse_id = w.id
        WHERE i.min_stock_level > 0 AND inv.quantity <= i.min_stock_level
        ORDER BY inv.quantity ASC, i.item_name
    ");
    $lowStockStmt->execute();
    $ocp_endpoint['low_stock_items'] = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent stock movements
    $movementsStmt = $pdo->prepare("
        SELECT sm.*, i.item_code, i.item_name, 
               s.supplier_name, p.project_name, 
               sc.subcon_name,
               w.warehouse_name,
               sm.transfer_from, sm.transfer_to,
               w_from.warehouse_name as from_warehouse_name,
               w_to.warehouse_name as to_warehouse_name
        FROM stock_movements sm
        JOIN item_names i ON sm.item_id = i.id
        LEFT JOIN suppliers s ON sm.supplier_id = s.id
        LEFT JOIN projects p ON sm.project_id = p.id
        LEFT JOIN subcons sc ON sm.subcon_id = sc.id
        LEFT JOIN warehouses w ON sm.warehouse_id = w.id
        LEFT JOIN warehouses w_from ON sm.transfer_from = w_from.id
        LEFT JOIN warehouses w_to ON sm.transfer_to = w_to.id
        ORDER BY sm.movement_date DESC, sm.created_at DESC
        LIMIT 50
    ");
    $movementsStmt->execute();
    $ocp_endpoint['movements'] = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate a new batch number for stock in form
    $ocp_endpoint['new_batch_number'] = 'BATCH-' . date('YmdHis');
} catch(PDOException $e) {
    $ocp_endpoint['swal_data'] = array(
        'title' => 'Error!',
        'text' => 'Error fetching data: ' . $e->getMessage(),
        'icon' => 'error'
    );
}

// --- the signed-in user, whose name the side menu prints ---------------------
$ocp_user_id = $_SESSION['user_id'] ?? null;
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
$stmt->bindParam(':id', $ocp_user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $display_name = $user['firstname'];
    if (!empty($user['middlename'])) {
        $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
    }
    $display_name .= ' ' . $user['lastname'];
    if (!empty($user['suffix'])) {
        $display_name .= ' ' . $user['suffix'];
    }
    $ocp_endpoint['display_name'] = $display_name;
}
unset($ocp_user_id, $user, $display_name);

return $ocp_endpoint;
