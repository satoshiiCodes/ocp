<?php
/**
 * api/purchase_request-endpoint.php
 *
 * Every read for purchase_request.php lives in this one file: the page's own listing
 * with its filters and dropdowns, and the single-record lookup the page's JavaScript
 * makes for one purchase request.
 *
 * The page pulls this in for its listing: it runs in the page's scope and returns an
 * array of the variables the markup needs, which the page unpacks. The lookup is a
 * separate HTTP request from the page's own script, so it is detected here by its
 * "?id=" parameter and answers JSON instead - which is why this file both returns an
 * array and, on that one path, prints.
 *
 * Reads from the page's scope:
 *   $pdo  the connection, opened by the page
 *
 * Returns (listing)

 */

// ---------------------------------------------------------------------------
// The single-record lookup the page's JavaScript performs: ?id=<pr id>.
// Lifted from api/get_pr_details.php, which this file replaces. The page pulls this
// file in with no ?id=, so that request falls through to the listing below.
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

    $pr_id = $_GET['id'];
    try {
        // Get PR details with PO and WS numbers and statuses
        $prStmt = $pdo->prepare("
            SELECT pr.*, 
                   u.firstname, u.middlename, u.lastname, u.suffix,
                   p.project_name,
                   s.supplier_name,
                   (SELECT po.po_number FROM purchase_orders po WHERE po.pr_id = pr.id ORDER BY po.id DESC LIMIT 1) as po_number,
                   (SELECT ws.ws_number FROM withdrawal_slips ws WHERE ws.pr_id = pr.id ORDER BY ws.id DESC LIMIT 1) as ws_number,
                   (SELECT po.status FROM purchase_orders po WHERE po.pr_id = pr.id ORDER BY po.id DESC LIMIT 1) as po_status,
                   (SELECT ws.status FROM withdrawal_slips ws WHERE ws.pr_id = pr.id ORDER BY ws.id DESC LIMIT 1) as ws_status
            FROM purchase_requests pr
            LEFT JOIN users u ON pr.requested_by = u.id
            LEFT JOIN projects p ON pr.project_id = p.id
            LEFT JOIN suppliers s ON pr.supplier_id = s.id
            WHERE pr.id = ?
        ");
        $prStmt->execute([$pr_id]);
        $pr = $prStmt->fetch(PDO::FETCH_ASSOC);
        if ($pr) {
            // Get PR items with warehouse and category details
            $itemsStmt = $pdo->prepare("
                SELECT pri.*, 
                       i.item_code, i.item_name,
                       w.warehouse_name, w.location,
                       s.supplier_name,
                       ic.category_name,
                       ic.id as category_id
                FROM pr_items pri
                JOIN item_names i ON pri.item_id = i.id
                LEFT JOIN items_categories ic ON i.category_id = ic.id
                JOIN warehouses w ON pri.warehouse_id = w.id
                LEFT JOIN suppliers s ON pri.supplier_id = s.id
                WHERE pri.pr_id = ?
            ");
            $itemsStmt->execute([$pr_id]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'pr' => $pr,
                'items' => $items
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Purchase request not found'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit();
}

// The helpers the actions file and the page both call: generatePRNumber() is used
// while the page renders its form, so it has to be defined for either entry point.
require_once __DIR__ . '/../includes/purchase_request-functions.php';

// ---------------------------------------------------------------------------
// The listing the page renders.
// ---------------------------------------------------------------------------
$ocp_endpoint = [
    'purchase_requests' => null,
    'items' => null,
    'projects' => null,
    'warehouses' => null,
    'suppliers' => null,
    'status_counts' => null,
    // Seeded with whatever the actions file already read from the session, so a
    // message it set is not wiped out by the endpoint running after it.
    'swal_data' => $swal_data ?? [],
];

// Standalone guard: the listing runs in the page's scope, where the connection is
// already open. Requested on its own there is none, so it answers with the empty
// result rather than erroring.
if (!isset($pdo)) {
    return $ocp_endpoint;
}

try {
    // Get purchase requests with supplier information and document_type
    $prStmt = $pdo->prepare("
        SELECT pr.*, 
            u.firstname, u.middlename, u.lastname, u.suffix,
            p.project_name,
            s.supplier_name,
            COUNT(pri.id) as item_count,
            (SELECT po.po_number FROM purchase_orders po WHERE po.pr_id = pr.id LIMIT 1) as po_number,
            (SELECT ws.ws_number FROM withdrawal_slips ws WHERE ws.pr_id = pr.id LIMIT 1) as ws_number,
            (SELECT po.status FROM purchase_orders po WHERE po.pr_id = pr.id LIMIT 1) as po_status,
            (SELECT ws.status FROM withdrawal_slips ws WHERE ws.pr_id = pr.id LIMIT 1) as ws_status
        FROM purchase_requests pr
        LEFT JOIN users u ON pr.requested_by = u.id
        LEFT JOIN projects p ON pr.project_id = p.id
        LEFT JOIN suppliers s ON pr.supplier_id = s.id
        LEFT JOIN pr_items pri ON pr.id = pri.pr_id
        GROUP BY pr.id
        ORDER BY pr.created_at DESC
    ");
    $prStmt->execute();
    $ocp_endpoint['purchase_requests'] = $prStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get items for dropdown WITH CATEGORY INFORMATION
    $itemsStmt = $pdo->prepare("
        SELECT 
            i.id, 
            i.item_code, 
            i.item_name,
            i.category_id,
            c.category_name
        FROM item_names i
        LEFT JOIN items_categories c ON i.category_id = c.id
        ORDER BY i.item_name
    ");
    $itemsStmt->execute();
    $ocp_endpoint['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get projects for dropdown
    $projectsStmt = $pdo->prepare("SELECT id, project_name FROM projects ORDER BY project_name");
    $projectsStmt->execute();
    $ocp_endpoint['projects'] = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get warehouses for dropdown
    $warehousesStmt = $pdo->prepare("SELECT id, warehouse_name, location FROM warehouses ORDER BY warehouse_name");
    $warehousesStmt->execute();
    $ocp_endpoint['warehouses'] = $warehousesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get suppliers for dropdown
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name");
    $suppliersStmt->execute();
    $ocp_endpoint['suppliers'] = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get PR status counts for dashboard
    $statusStmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM purchase_requests 
        GROUP BY status
    ");
    $statusStmt->execute();
    $ocp_endpoint['status_counts'] = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $ocp_endpoint['swal_data'] = array(
        'title' => 'Error!',
        'text' => 'Error fetching data: ' . $e->getMessage(),
        'icon' => 'error'
    );
}

return $ocp_endpoint;
