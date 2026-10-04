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
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
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

// Process purchase request actions
$message = '';
$message_type = '';
$swal_data = array();

// Check for session-based SweetAlert data
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

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

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_pr'])) {
        // Create new purchase request
        $pr_number = generatePRNumber($pdo);
        $requested_by = $_SESSION['user_id'];
        $project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
        $supplier_id = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null;
        $request_type = $_POST['request_type']; // 'project' or 'supplier'
        $request_date = $_POST['request_date'];
        $status = 'pending';
        $document_type = null;
        
        // Validate: project_id is required for project requests, supplier_id is optional for supplier requests
        if ($request_type === 'project' && empty($project_id)) {
            $swal_data = array(
                'title' => 'Error!',
                'text' => 'Project is required for project purchase requests.',
                'icon' => 'error'
            );
        } else {
            // Process the form - supplier_id can be empty for supplier requests
            try {
                $pdo->beginTransaction();
                
                // For project requests, check stock availability and set document_type
                // For supplier requests, set document_type to 'pr_po'
                if ($request_type === 'project' && isset($_POST['items']) && is_array($_POST['items'])) {
                    // Group items by warehouse
                    $items_by_warehouse = [];
                    foreach ($_POST['items'] as $item) {
                        if (!empty($item['item_id']) && !empty($item['quantity']) && !empty($item['warehouse_id'])) {
                            $warehouse_id = $item['warehouse_id'];
                            if (!isset($items_by_warehouse[$warehouse_id])) {
                                $items_by_warehouse[$warehouse_id] = [];
                            }
                            $items_by_warehouse[$warehouse_id][] = $item;
                        }
                    }
                    
                    // Check stock for each warehouse and determine overall document type
                    $overall_document_type = 'ws'; // Default to ws
                    
                    foreach ($items_by_warehouse as $warehouse_id => $warehouse_items) {
                        $stock_check = checkStockAvailability($pdo, $warehouse_items, $warehouse_id);
                        if ($stock_check['document_type'] === 'pr_po') {
                            $overall_document_type = 'pr_po';
                        } elseif ($stock_check['document_type'] === 'po_ws' && $overall_document_type !== 'pr_po') {
                            $overall_document_type = 'po_ws';
                        }
                    }
                    
                    $document_type = $overall_document_type;
                } elseif ($request_type === 'supplier') {
                    // For supplier requests, always set document_type to 'pr_po'
                    $document_type = 'pr_po';
                }
                
                // Insert purchase request with document_type
                $stmt = $pdo->prepare("INSERT INTO purchase_requests 
                    (pr_number, requested_by, project_id, supplier_id, request_type, document_type, request_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$pr_number, $requested_by, $project_id, $supplier_id, $request_type, $document_type, $request_date, $status]);
                $pr_id = $pdo->lastInsertId();
                
                // Insert PR items WITH supplier_id and warehouse
                if (isset($_POST['items']) && is_array($_POST['items'])) {
                    foreach ($_POST['items'] as $item) {
                        if (!empty($item['item_id']) && !empty($item['quantity']) && !empty($item['warehouse_id'])) {
                            $item_id = $item['item_id'];
                            $warehouse_id = $item['warehouse_id'];
                            $quantity = $item['quantity'];
                            
                            // For project requests, unit_cost should be 0 or null
                            // For supplier requests, use the provided unit_cost
                            $unit_cost = 0;
                            if ($request_type === 'supplier' && !empty($item['unit_cost'])) {
                                $unit_cost = $item['unit_cost'];
                            }
                            
                            // If request type is supplier, save supplier_id in pr_items (can be null)
                            $item_supplier_id = ($request_type === 'supplier') ? $supplier_id : null;
                            
                            $itemStmt = $pdo->prepare("INSERT INTO pr_items 
                                (pr_id, item_id, warehouse_id, quantity, unit_cost, supplier_id) 
                                VALUES (?, ?, ?, ?, ?, ?)");
                            $itemStmt->execute([$pr_id, $item_id, $warehouse_id, $quantity, $unit_cost, $item_supplier_id]);
                        } else {
                            // Throw exception if warehouse is missing
                            throw new PDOException("Warehouse is required for all items.");
                        }
                    }
                }
                
                $pdo->commit();
                
                // Prepare success message with document type info
                $success_text = 'Purchase Request created successfully!';
                if ($document_type) {
                    $doc_type_labels = [
                        'ws' => 'Document Type: Warehouse Stock Only',
                        'po_ws' => 'Document Type: PO with Warehouse Stock',
                        'pr_po' => 'Document Type: PR to PO'
                    ];
                    $success_text .= ' ' . ($doc_type_labels[$document_type] ?? '');
                }
                
                $_SESSION['swal_data'] = array(
                    'title' => 'Success!',
                    'text' => $success_text,
                    'icon' => 'success'
                );
                header("Location: purchase_request.php");
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $swal_data = array(
                    'title' => 'Error!',
                    'text' => 'Failed to create purchase request: ' . $e->getMessage(),
                    'icon' => 'error'
                );
            }
        }
    } elseif (isset($_POST['update_pr'])) {
        // Update purchase request
        $pr_id = $_POST['pr_id'];
        $project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
        $supplier_id = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null;
        $request_type = $_POST['request_type'];
        $request_date = $_POST['request_date'];
        $document_type = null;
        
        try {
            $pdo->beginTransaction();
            
            // For project requests, check stock availability and set document_type
            // For supplier requests, set document_type to 'pr_po'
            if ($request_type === 'project' && isset($_POST['items']) && is_array($_POST['items'])) {
                // Group items by warehouse
                $items_by_warehouse = [];
                foreach ($_POST['items'] as $item) {
                    if (!empty($item['item_id']) && !empty($item['quantity']) && !empty($item['warehouse_id'])) {
                        $warehouse_id = $item['warehouse_id'];
                        if (!isset($items_by_warehouse[$warehouse_id])) {
                            $items_by_warehouse[$warehouse_id] = [];
                        }
                        $items_by_warehouse[$warehouse_id][] = $item;
                    }
                }
                
                // Check stock for each warehouse and determine overall document type
                $overall_document_type = 'ws';
                
                foreach ($items_by_warehouse as $warehouse_id => $warehouse_items) {
                    $stock_check = checkStockAvailability($pdo, $warehouse_items, $warehouse_id);
                    if ($stock_check['document_type'] === 'pr_po') {
                        $overall_document_type = 'pr_po';
                    } elseif ($stock_check['document_type'] === 'po_ws' && $overall_document_type !== 'pr_po') {
                        $overall_document_type = 'po_ws';
                    }
                }
                
                $document_type = $overall_document_type;
            } elseif ($request_type === 'supplier') {
                // For supplier requests, always set document_type to 'pr_po'
                $document_type = 'pr_po';
            }
            
            // Update purchase request with document_type
            $stmt = $pdo->prepare("UPDATE purchase_requests 
                SET project_id = ?, supplier_id = ?, request_type = ?, document_type = ?, request_date = ? 
                WHERE id = ?");
            $stmt->execute([$project_id, $supplier_id, $request_type, $document_type, $request_date, $pr_id]);
            
            // Delete existing items
            $deleteStmt = $pdo->prepare("DELETE FROM pr_items WHERE pr_id = ?");
            $deleteStmt->execute([$pr_id]);
            
            // Insert updated items WITH supplier_id and warehouse
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if (!empty($item['item_id']) && !empty($item['quantity']) && !empty($item['warehouse_id'])) {
                        $item_id = $item['item_id'];
                        $warehouse_id = $item['warehouse_id'];
                        $quantity = $item['quantity'];
                        
                        // For project requests, unit_cost should be 0 or null
                        // For supplier requests, use the provided unit_cost
                        $unit_cost = 0;
                        if ($request_type === 'supplier' && !empty($item['unit_cost'])) {
                            $unit_cost = $item['unit_cost'];
                        }
                        
                        // If request type is supplier, save supplier_id in pr_items (can be null)
                        $item_supplier_id = ($request_type === 'supplier') ? $supplier_id : null;
                        
                        $itemStmt = $pdo->prepare("INSERT INTO pr_items 
                            (pr_id, item_id, warehouse_id, quantity, unit_cost, supplier_id) 
                            VALUES (?, ?, ?, ?, ?, ?)");
                        $itemStmt->execute([$pr_id, $item_id, $warehouse_id, $quantity, $unit_cost, $item_supplier_id]);
                    } else {
                        // Throw exception if warehouse is missing
                        throw new PDOException("Warehouse is required for all items.");
                    }
                }
            }
            
            $pdo->commit();
            
            $success_text = 'Purchase Request updated successfully!';
            if ($document_type) {
                $doc_type_labels = [
                    'ws' => 'Document Type: Warehouse Stock Only',
                    'po_ws' => 'Document Type: PO with Warehouse Stock',
                    'pr_po' => 'Document Type: PR to PO'
                ];
                $success_text .= ' ' . ($doc_type_labels[$document_type] ?? '');
            }
            
            $_SESSION['swal_data'] = array(
                'title' => 'Success!',
                'text' => $success_text,
                'icon' => 'success'
            );
            header("Location: purchase_request.php");
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Error!',
                'text' => 'Failed to update purchase request: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
    } elseif (isset($_POST['update_pr_status'])) {
        // Update PR status
        $pr_id = $_POST['pr_id'];
        $status = $_POST['status'];
        $remarks = $_POST['remarks'] ?? '';
        
        try {
            $stmt = $pdo->prepare("UPDATE purchase_requests SET status = ?, remarks = ? WHERE id = ?");
            $stmt->execute([$status, $remarks, $pr_id]);
            
            $_SESSION['swal_data'] = array(
                'title' => 'Success!',
                'text' => 'Purchase Request status updated successfully!',
                'icon' => 'success'
            );
            header("Location: purchase_request.php");
            exit();
            
        } catch (PDOException $e) {
            $swal_data = array(
                'title' => 'Error!',
                'text' => 'Failed to update status: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
    } elseif (isset($_POST['delete_pr'])) {
        // Delete purchase request
        $pr_id = $_POST['pr_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Delete items first
            $deleteItemsStmt = $pdo->prepare("DELETE FROM pr_items WHERE pr_id = ?");
            $deleteItemsStmt->execute([$pr_id]);
            
            // Delete PR
            $deletePRStmt = $pdo->prepare("DELETE FROM purchase_requests WHERE id = ?");
            $deletePRStmt->execute([$pr_id]);
            
            $pdo->commit();
            
            $_SESSION['swal_data'] = array(
                'title' => 'Success!',
                'text' => 'Purchase Request deleted successfully!',
                'icon' => 'success'
            );
            header("Location: purchase_request.php");
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Error!',
                'text' => 'Failed to delete purchase request: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
    }
}

// Fetch data
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
    $purchase_requests = $prStmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get projects for dropdown
    $projectsStmt = $pdo->prepare("SELECT id, project_name FROM projects ORDER BY project_name");
    $projectsStmt->execute();
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get warehouses for dropdown
    $warehousesStmt = $pdo->prepare("SELECT id, warehouse_name, location FROM warehouses ORDER BY warehouse_name");
    $warehousesStmt->execute();
    $warehouses = $warehousesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get suppliers for dropdown
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name");
    $suppliersStmt->execute();
    $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get PR status counts for dashboard
    $statusStmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM purchase_requests 
        GROUP BY status
    ");
    $statusStmt->execute();
    $status_counts = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $swal_data = array(
        'title' => 'Error!',
        'text' => 'Error fetching data: ' . $e->getMessage(),
        'icon' => 'error'
    );
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

// Function to get document type badge
function getDocumentTypeBadge($document_type) {
    switch ($document_type) {
        case 'ws':
            return '<span class="badge bg-success" title="Warehouse Stock Only">WS</span>';
        case 'po_ws':
            return '<span class="badge bg-warning" title="PO with Warehouse Stock">PO/WS</span>';
        case 'pr_po':
            return '<span class="badge bg-danger" title="PR to PO">PR/PO</span>';
        case 'direct_po':
            return '<span class="badge bg-info" title="Direct Purchase Order">Direct PO</span>';
        default:
            return '<span class="badge bg-secondary">N/A</span>';
    }
}

// Function to format requester name
function formatRequesterName($user) {
    $name = $user['firstname'];
    if (!empty($user['middlename'])) {
        $name .= ' ' . substr($user['middlename'], 0, 1) . '.';
    }
    $name .= ' ' . $user['lastname'];
    if (!empty($user['suffix'])) {
        $name .= ' ' . $user['suffix'];
    }
    return $name;
}

// Function to get request type badge
function getRequestTypeBadge($type) {
    switch ($type) {
        case 'project':
            return '<span class="badge bg-info">Project</span>';
        case 'supplier':
            return '<span class="badge bg-primary">Stock</span>';
        default:
            return '<span class="badge bg-secondary">' . ucfirst($type) . '</span>';
    }
}

// Function to format date as mm-dd-yyyy
function formatDate($date) {
    if (empty($date)) return 'N/A';
    return date('m-d-Y', strtotime($date));
}

// Generate PR number for the form
$pr_number = generatePRNumber($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=yes" />
    <title>Warehouse PR - OCP Construction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .status-badge, .doc-type-badge {
            font-size: 0.8rem;
        }
        .pr-card {
            transition: transform 0.2s ease-in-out;
        }
        .pr-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .item-row {
            border-bottom: 1px solid #dee2e6;
            padding: 15px 0;
            position: relative;
        }
        .item-row:last-child {
            border-bottom: none;
        }
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: center;
        }
        .action-buttons .btn {
            min-width: 35px;
            padding: 0.25rem 0.5rem;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .request-type-selector {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .request-type-option {
            padding: 0.5rem;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 0.25rem;
            margin: 0.25rem;
        }
        .request-type-option:hover {
            background-color: #f8f9fa;
        }
        .request-type-option.active {
            background-color: #e7f1ff;
            border-color: #0d6efd;
        }
        .type-dependent-field {
            display: none;
        }
        
        /* Document type tooltips */
        .doc-type-info {
            border-bottom: 1px dashed #6c757d;
            cursor: help;
        }
        
        /* Improved item row layout for better button positioning */
        .item-row {
            position: relative;
            padding-right: 70px;
        }
        
        .remove-btn-container {
            position: absolute;
            top: 15px;
            right: 10px;
            z-index: 5;
        }
        
        /* Document numbers styling */
        .document-numbers {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .document-number-item {
            font-size: 0.9rem;
        }
        .document-number-item i {
            width: 16px;
            margin-right: 4px;
            font-size: 0.8rem;
        }
        .pr-number {
            color: #0d6efd;
        }
        .po-number {
            color: #6f42c1;
        }
        .ws-number {
            color: #fd7e14;
        }
        
        /* Desktop styles - optimized for modal */
        @media (min-width: 992px) {
            .item-row .row {
                display: flex;
                flex-wrap: wrap;
                margin-right: -5px;
                margin-left: -5px;
            }
            
            .item-row [class*="col-"] {
                padding-right: 5px;
                padding-left: 5px;
            }
            
            .item-col { 
                width: 40%; 
            }
            .qty-col { 
                width: 15%; 
            }
            .unit-cost-col { 
                width: 15%; 
            }
            .warehouse-col { 
                width: 25%; 
            }
            
            /* Project mode desktop - hide unit cost, adjust widths */
            .project-mode .item-col { 
                width: 48%; 
            }
            .project-mode .qty-col { 
                width: 18%; 
            }
            .project-mode .warehouse-col { 
                width: 30%; 
            }
            .project-mode .unit-cost-col { 
                display: none; 
            }
        }
        
        /* Tablet styles */
        @media (min-width: 768px) and (max-width: 991px) {
            .item-row {
                padding-right: 60px;
            }
            
            .item-row .row > div {
                width: 50%;
            }
            
            .item-col, .qty-col, .unit-cost-col, .warehouse-col {
                width: 100%;
            }
        }
        
        /* Mobile styles */
        @media (max-width: 767px) {
            .item-row {
                padding-right: 50px;
                padding-top: 20px;
            }
            
            .remove-btn-container {
                top: 10px;
                right: 10px;
            }
            
            .item-row .row > div {
                width: 100%;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
            
            .form-floating {
                margin-bottom: 1rem;
            }
            
            .request-type-option {
                margin: 0.5rem 0;
            }
        }
        
        /* Status cards responsive */
        @media (max-width: 576px) {
            .card .card-body {
                padding: 0.75rem;
            }
            
            .card .card-title {
                font-size: 1.25rem;
                margin-bottom: 0.25rem;
            }
            
            .card .card-text {
                font-size: 0.75rem;
            }
        }
        
        /* Modal improvements */
        .modal-dialog {
            max-height: 90vh;
        }
        
        .modal-body {
            max-height: calc(90vh - 120px);
            overflow-y: auto;
            padding-bottom: 80px;
        }
        
        /* Required field indicator */
        .required-field label::after {
            content: " *";
            color: red;
        }
        
        /* Improved modal footer */
        .modal-footer {
            position: sticky;
            bottom: 0;
            background-color: white;
            border-top: 1px solid #dee2e6;
            z-index: 10;
        }
        
        /* Better spacing for form elements */
        .form-floating > label {
            padding-left: 0.75rem;
        }
        
        /* Stock preview styling */
        #stockPreview {
            margin-top: 15px;
            margin-bottom: 15px;
        }
        
        /* Withdrawal slip specific styling */
        .ws-icon {
            color: white;
        }
        
        /* Table styling for view modal */
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .table td, .table th {
            padding: 10px;
            vertical-align: middle;
        }
        .table tbody tr:hover {
            background-color: #f5f5f5;
        }
        /* PDF Dropdown styling */
        .btn-group .dropdown-menu {
            position: absolute !important;
            z-index: 1000;
            min-width: 200px;
        }

        .action-buttons .btn-group {
            display: inline-block;
            vertical-align: middle;
        }

        /* Ensure dropdown button is properly sized */
        .action-buttons .btn-group .btn {
            min-width: 35px;
            padding: 0.25rem 0.5rem;
        }

        /* Mobile responsiveness for dropdown */
        @media (max-width: 768px) {
            .btn-group .dropdown-menu {
                position: fixed !important;
                top: auto !important;
                left: 50% !important;
                transform: translateX(-50%) !important;
                width: 90% !important;
                max-width: 300px;
                z-index: 9999;
            }
            
            .btn-group .dropdown-menu.show {
                display: block !important;
            }
        }

        /* Dropdown item styling */
        .dropdown-item {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .dropdown-divider {
            margin: 0.25rem 0;
        }
        
        /* Searchable dropdown styles - UPDATED for the new display format */
        .searchable-dropdown-container {
            position: relative;
            width: 100%;
            margin-bottom: 1rem;
        }
        
        .searchable-dropdown-input {
            width: 100%;
            padding: 1rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            height: calc(3.5rem + 2px);
            white-space: pre-wrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .searchable-dropdown-input:focus {
            color: #212529;
            background-color: #fff;
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.25);
        }
        
        .searchable-dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 300px;
            overflow-y: auto;
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            z-index: 1000;
            display: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .searchable-dropdown-list.show {
            display: block;
        }
        
        .searchable-dropdown-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            flex-direction: column;
        }
        
        .searchable-dropdown-item:hover,
        .searchable-dropdown-item.selected {
            background-color: #e7f1ff;
        }
        
        .searchable-dropdown-item:last-child {
            border-bottom: none;
        }
        
        /* Updated styles for the new item display format */
        .searchable-dropdown-item .item-code {
            font-weight: 600;
            color: #0d6efd;
            display: block;
            font-size: 1.1rem;
            margin-bottom: 2px;
        }
        
        .searchable-dropdown-item .item-name {
            color: #212529;
            font-weight: 500;
            display: block;
            margin-bottom: 2px;
        }
        
        .searchable-dropdown-item .item-category {
            color: #6c757d;
            font-size: 0.85rem;
            display: block;
        }
        
        .searchable-dropdown-no-results {
            padding: 0.5rem 1rem;
            color: #6c757d;
            font-style: italic;
        }
        
        /* Adjust item-col for searchable dropdown */
        .item-col {
            position: relative;
        }
        
        /* For the selected item display in the input - NEW FORMAT */
        .searchable-dropdown-input.item-selected {
            background-color: #f8f9fa;
            font-family: inherit;
            line-height: 1.5;
            padding: 0.75rem;
            white-space: nowrap;
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
                    <h1 class="mt-4">Warehouse PR</h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="inventory.php">Warehouse Inventory</a></li>
                        <li class="breadcrumb-item active">Purchase Requests</li>
                    </ol>

                    <!-- Status Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-2 col-md-4 col-6 mb-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h4 class="card-title">
                                        <?php 
                                            $pending_count = 0;
                                            foreach ($status_counts as $status) {
                                                if ($status['status'] == 'pending') {
                                                    $pending_count = $status['count'];
                                                    break;
                                                }
                                            }
                                            echo $pending_count;
                                        ?>
                                    </h4>
                                    <p class="card-text">Pending</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-4">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h4 class="card-title">
                                        <?php 
                                            $processing_count = 0;
                                            foreach ($status_counts as $status) {
                                                if ($status['status'] == 'processing') {
                                                    $processing_count = $status['count'];
                                                    break;
                                                }
                                            }
                                            echo $processing_count;
                                        ?>
                                    </h4>
                                    <p class="card-text">Processing</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h4 class="card-title">
                                        <?php 
                                            $approved_count = 0;
                                            foreach ($status_counts as $status) {
                                                if ($status['status'] == 'approved') {
                                                    $approved_count = $status['count'];
                                                    break;
                                                }
                                            }
                                            echo $approved_count;
                                        ?>
                                    </h4>
                                    <p class="card-text">Approved</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-4">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h4 class="card-title">
                                        <?php 
                                            $rejected_count = 0;
                                            foreach ($status_counts as $status) {
                                                if ($status['status'] == 'rejected') {
                                                    $rejected_count = $status['count'];
                                                    break;
                                                }
                                            }
                                            echo $rejected_count;
                                        ?>
                                    </h4>
                                    <p class="card-text">Rejected</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h4 class="card-title">
                                        <?php 
                                            $completed_count = 0;
                                            foreach ($status_counts as $status) {
                                                if ($status['status'] == 'completed') {
                                                    $completed_count = $status['count'];
                                                    break;
                                                }
                                            }
                                            echo $completed_count;
                                        ?>
                                    </h4>
                                    <p class="card-text">Completed</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-4">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h4 class="card-title"><?php echo count($purchase_requests); ?></h4>
                                    <p class="card-text">Total PRs</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Purchase Requests List -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-table me-1"></i>
                                Purchase Requests
                            </div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPRModal">
                                <i class="fas fa-plus me-1"></i> Create PR
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($purchase_requests)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover" id="prTable">
                                    <thead>
                                        <tr>
                                            <th>Document #</th>
                                            <th>Requested By</th>
                                            <th>Type</th>
                                            <th>Project/Supplier</th>
                                            <th>Request Date</th>
                                            <th>Doc Type</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($purchase_requests as $pr): 
                                            // Determine project/supplier info
                                            $target_info = '';
                                            if ($pr['request_type'] === 'project') {
                                                $target_info = $pr['project_name'] ?: 'N/A';
                                            } elseif ($pr['request_type'] === 'supplier') {
                                                $target_info = $pr['supplier_name'] ?: 'N/A';
                                            }
                                            
                                            // Format PR, PO and WS numbers for display
                                            $pr_display = ($pr['document_type'] === 'ws') ? '' : htmlspecialchars($pr['pr_number']);
                                            $po_display = !empty($pr['po_number']) ? htmlspecialchars($pr['po_number']) : '';
                                            $ws_display = !empty($pr['ws_number']) ? htmlspecialchars($pr['ws_number']) : '';
                                            
                                            // Build document numbers display
                                            $document_numbers = '<div class="document-numbers">';
                                            
                                            if (!empty($pr_display)) {
                                                $document_numbers .= '<div class="document-number-item pr-number">' . $pr_display . '</div>';
                                            }
                                            if (!empty($po_display)) {
                                                $document_numbers .= '<div class="document-number-item po-number">' . $po_display . '</div>';
                                            }
                                            if (!empty($ws_display)) {
                                                $document_numbers .= '<div class="document-number-item ws-number">' . $ws_display . '</div>';
                                            }
                                            
                                            if (empty($pr_display) && empty($po_display) && empty($ws_display)) {
                                                $document_numbers .= '<span class="text-muted">N/A</span>';
                                            }
                                            
                                            $document_numbers .= '</div>';
                                        ?>
                                        <tr>
                                            <td><?php echo $document_numbers; ?></td>
                                            <td><?php echo formatRequesterName($pr); ?></td>
                                            <td><?php echo getRequestTypeBadge($pr['request_type']); ?></td>
                                            <td><?php echo htmlspecialchars($target_info); ?></td>
                                            <td><?php echo formatDate($pr['request_date']); ?></td>
                                            <td>
                                                <?php 
                                                    if (!empty($pr['document_type'])) {
                                                        echo getDocumentTypeBadge($pr['document_type']);
                                                    } else {
                                                        echo '<span class="badge bg-secondary">N/A</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="<?php echo getStatusBadge($pr['status']); ?> status-badge">
                                                    <?php echo ucfirst($pr['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <!-- PDF Dropdown -->
                                                    <?php 
                                                    // Determine which PDF options are available
                                                    $has_pr_pdf = false;
                                                    $has_po_pdf = false;
                                                    $has_ws_pdf = false;

                                                    if ($pr['document_type'] == 'ws') {
                                                        $has_ws_pdf = !empty($pr['ws_number']);
                                                    } elseif ($pr['document_type'] == 'po_ws') {
                                                        $has_pr_pdf = true; // PR always exists for PO/WS
                                                        $has_po_pdf = !empty($pr['po_number']);
                                                        $has_ws_pdf = !empty($pr['ws_number']);
                                                    } elseif ($pr['document_type'] == 'pr_po') {
                                                        $has_pr_pdf = true; // PR always exists
                                                        $has_po_pdf = !empty($pr['po_number']);
                                                        if ($pr['request_type'] == 'project') {
                                                            $has_ws_pdf = !empty($pr['ws_number']);
                                                        }
                                                    } else {
                                                        $has_pr_pdf = true; // Default - PR always exists
                                                        $has_po_pdf = !empty($pr['po_number']);
                                                        $has_ws_pdf = !empty($pr['ws_number']);
                                                    }

                                                    // Count available PDFs
                                                    $available_pdfs = ($has_pr_pdf ? 1 : 0) + ($has_po_pdf ? 1 : 0) + ($has_ws_pdf ? 1 : 0);
                                                    ?>

                                                    <?php if ($available_pdfs > 0): ?>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-danger dropdown-toggle" data-bs-toggle="dropdown" 
                                                                aria-expanded="false" data-bs-toggle="tooltip" data-bs-placement="top" title="Generate PDF">
                                                            <i class="fas fa-file-pdf"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php if ($pr['document_type'] == 'ws'): ?>
                                                                <!-- For WS document type, only show WS PDF if available -->
                                                                <?php if ($has_ws_pdf): ?>
                                                                <li>
                                                                    <a class="dropdown-item" href="generate_ws_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                        <i class="fas fa-file-pdf text-danger me-2"></i> Withdrawal Slip (WS)
                                                                    </a>
                                                                </li>
                                                                <?php endif; ?>
                                                            <?php elseif ($pr['document_type'] == 'po_ws'): ?>
                                                                <!-- For PO/WS document type, show PR, PO, and WS options if available -->
                                                                <?php if ($has_pr_pdf): ?>
                                                                <li>
                                                                    <a class="dropdown-item" href="generate_pr_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                        <i class="fas fa-file-pdf text-danger me-2"></i> PR (Project)
                                                                    </a>
                                                                </li>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($has_po_pdf): ?>
                                                                <li>
                                                                    <a class="dropdown-item" href="generate_po_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                        <i class="fas fa-file-pdf text-danger me-2"></i> PO (Project)
                                                                    </a>
                                                                </li>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($has_ws_pdf): ?>
                                                                <li>
                                                                    <a class="dropdown-item" href="generate_ws_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                        <i class="fas fa-file-pdf text-danger me-2"></i> Withdrawal Slip (WS)
                                                                    </a>
                                                                </li>
                                                                <?php endif; ?>
                                                            <?php elseif ($pr['document_type'] == 'pr_po'): ?>
                                                                <!-- For PR/PO document type, show PR and PO options if available -->
                                                                <?php if ($pr['request_type'] == 'project'): ?>
                                                                    <?php if ($has_pr_pdf): ?>
                                                                    <li>
                                                                        <a class="dropdown-item" href="generate_pr_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger me-2"></i> PR (Project)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php if ($has_po_pdf): ?>
                                                                    <li>
                                                                        <a class="dropdown-item" href="generate_po_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger me-2"></i> PO (Project)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php if ($has_ws_pdf): ?>
                                                                    <li>
                                                                        <a class="dropdown-item" href="generate_ws_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger me-2"></i> Withdrawal Slip (WS)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <?php if ($has_pr_pdf): ?>
                                                                    <li>
                                                                        <a class="dropdown-item" href="generate_pr_supplier_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger me-2"></i> PR (Supplier)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php if ($has_po_pdf): ?>
                                                                    <li>
                                                                        <a class="dropdown-item" href="generate_po_supplier_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger me-2"></i> PO (Supplier)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <!-- Default/fallback options -->
                                                                <?php if ($pr['request_type'] == 'project'): ?>
                                                                    <?php if ($has_pr_pdf): ?>
                                                                    <li>
                                                                        <a class="dropdown-item" href="generate_pr_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger me-2"></i> PR (Project)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <?php if ($has_pr_pdf): ?>
                                                                    <li>
                                                                        <a class="dropdown-item" href="generate_pr_supplier_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger me-2"></i> PR (Supplier)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($has_po_pdf): ?>
                                                                    <li><hr class="dropdown-divider"></li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="generate_po_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger me-2"></i> PO (Project)
                                                                        </a>
                                                                    </li>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($has_ws_pdf): ?>
                                                                    <li><hr class="dropdown-divider"></li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="generate_ws_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger me-2"></i> Withdrawal Slip (WS)
                                                                        </a>
                                                                    </li>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                    <?php endif; ?>

                                                    <button class="btn btn-sm btn-info view-pr" data-id="<?php echo $pr['id']; ?>" 
                                                            data-bs-toggle="tooltip" data-bs-placement="top" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <a href="pr_view_routing.php?id=<?php echo $pr['id']; ?>" class="btn btn-sm btn-warning" 
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="PR File Routing">
                                                        <i class="fas fa-route"></i>
                                                    </a>
                                                    
                                                    <?php if ($pr['status'] == 'pending'): ?>
                                                    <button class="btn btn-sm btn-danger delete-pr" data-id="<?php echo $pr['id']; ?>" 
                                                            data-pr-number="<?php echo $pr['pr_number']; ?>"
                                                            data-bs-toggle="tooltip" data-bs-placement="top" title="Delete PR">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5>No Purchase Requests Found</h5>
                                <p class="text-muted">Create your first purchase request to get started.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPRModal">
                                    <i class="fas fa-plus me-1"></i> Create First PR
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <!-- Create PR Modal -->
    <div class="modal fade" id="createPRModal" tabindex="-1" aria-labelledby="createPRModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPRModalLabel">Create New Purchase Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="createPRForm">
                    <input type="hidden" name="create_pr" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12 col-sm-6" hidden>
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" value="<?php echo $pr_number; ?>" readonly disabled>
                                    <label>PR Number</label>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" value="<?php echo $display_name; ?>" readonly disabled>
                                    <label>Requested By</label>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" name="request_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    <label>Request Date <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-floating mb-3">
                                    <input type="hidden" name="request_type" id="request_type" value="project" required>
                                    <input type="text" class="form-control" id="request_type_display" value="Project" readonly>
                                    <label>Request Type</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Request Type Selector -->
                        <div class="request-type-selector mb-3">
                            <h6>Select Request Type <span class="text-danger">*</span></h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="request-type-option active" data-type="project" onclick="selectRequestType('project')">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="fas fa-project-diagram fa-2x text-info"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Project Purchase Request</h6>
                                                <p class="mb-0 text-muted small d-none d-md-block">For items needed for specific projects</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="request-type-option" data-type="supplier" onclick="selectRequestType('supplier')">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="fas fa-truck fa-2x text-primary"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Stock Purchase Request</h6>
                                                <p class="mb-0 text-muted small d-none d-md-block">For items needed from specific suppliers</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Project Field (shown by default) -->
                        <div class="type-dependent-field" id="project-field" style="display: block;">
                            <div class="form-floating mb-3">
                                <select class="form-select" name="project_id" id="project_id" required>
                                    <option value="">Select Project</option>
                                    <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>">
                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="project_id">Project <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        
                        <!-- Supplier Field (hidden by default) -->
                        <div class="type-dependent-field" id="supplier-field" style="display: none;">
                            <div class="form-floating mb-3">
                                <select class="form-select" name="supplier_id" id="supplier_id">
                                    <option value="">Select Supplier (Optional)</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>">
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="supplier_id">Supplier</label>
                            </div>
                        </div>
                        
                        <!-- Stock Status Preview -->
                        <div id="stockPreview" class="alert alert-info" style="display: none;" hidden>
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="stockMessage"></span>
                        </div>
                        
                        <!-- Items Section -->
                        <div class="mb-3">
                            <h6>Items Requested <span class="text-danger">*</span></h6>
                            <div id="itemsContainer">
                                <!-- First item row -->
                                <div class="item-row mb-2 p-3 border rounded position-relative" data-item-index="0">
                                    <div class="remove-btn-container">
                                        <button type="button" class="btn btn-danger btn-sm remove-item" style="display: none;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="row align-items-start">
                                        <div class="col-lg-5 col-md-6 col-12 item-col">
                                            <!-- Searchable dropdown container for item - UPDATED for the new display format -->
                                            <div class="searchable-dropdown-container" id="item-searchable-container-0">
                                                <input type="text" 
                                                       class="searchable-dropdown-input item-search-input" 
                                                       id="item-search-input-0" 
                                                       placeholder="Type to search items..."
                                                       autocomplete="off"
                                                       data-item-index="0">
                                                <input type="hidden" name="items[0][item_id]" id="item-id-0" class="item-id-hidden" required>
                                                <div class="searchable-dropdown-list" id="item-dropdown-list-0"></div>
                                            </div>
                                        </div>
                                        <div class="col-lg-2 col-md-6 col-12 qty-col">
                                            <div class="form-floating mb-3">
                                                <input type="number" class="form-control" name="items[0][quantity]" min="1" required>
                                                <label>Quantity <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-lg-2 col-md-6 col-12 unit-cost-col" style="display: none;">
                                            <div class="form-floating mb-3">
                                                <input type="number" class="form-control" name="items[0][unit_cost]" step="0.01" min="0" value="0">
                                                <label>Unit Cost (₱)</label>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6 col-12 warehouse-col">
                                            <div class="form-floating mb-3">
                                                <select class="form-select warehouse-select" name="items[0][warehouse_id]" required>
                                                    <option value="">Select Warehouse</option>
                                                    <?php foreach ($warehouses as $warehouse): ?>
                                                    <option value="<?php echo $warehouse['id']; ?>">
                                                        <?php echo htmlspecialchars($warehouse['warehouse_name'] . ' - ' . $warehouse['location']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Warehouse <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addItem">
                                    <i class="fas fa-plus me-1"></i> Add Another Item
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="checkStockBtn" onclick="checkStockAvailability()" hidden>
                                    <i class="fas fa-search me-1"></i> Check Stock
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Purchase Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View PR Modal -->
    <div class="modal fade" id="viewPRModal" tabindex="-1" aria-labelledby="viewPRModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPRModalLabel">Purchase Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="prDetails">
                    <!-- Details will be loaded via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete PR Modal -->
    <div class="modal fade" id="deletePRModal" tabindex="-1" aria-labelledby="deletePRModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePRModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="delete_pr" value="1">
                    <input type="hidden" name="pr_id" id="delete_pr_id">
                    <div class="modal-body">
                        <p>Are you sure you want to delete this purchase request?</p>
                        <p><strong id="delete_pr_number"></strong></p>
                        <p class="text-danger">Warning: This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete PR</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
    <script>
        // PHP items data for JavaScript - NOW INCLUDING CATEGORY
        const itemsData = <?php echo json_encode($items); ?>;
        
        // Track selected items to prevent duplicates
        const selectedItems = {};

        // Format date as mm-dd-yyyy
        function formatDate(date) {
            if (!date) return 'N/A';
            var d = new Date(date);
            var month = '' + (d.getMonth() + 1);
            var day = '' + d.getDate();
            var year = d.getFullYear();
            
            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;
            
            return month + '-' + day + '-' + year;
        }
        
        // Format datetime as mm-dd-yyyy hh:mm:ss
        function formatDateTime(datetime) {
            if (!datetime) return 'N/A';
            var d = new Date(datetime);
            var month = '' + (d.getMonth() + 1);
            var day = '' + d.getDate();
            var year = d.getFullYear();
            var hours = d.getHours();
            var minutes = d.getMinutes();
            var seconds = d.getSeconds();
            
            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;
            hours = hours < 10 ? '0' + hours : hours;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            
            return month + '-' + day + '-' + year + ' ' + hours + ':' + minutes + ':' + seconds;
        }

        // Function to create searchable dropdown for an item row - UPDATED for the new display format
        function createSearchableDropdown(rowIndex) {
            const searchInput = document.getElementById(`item-search-input-${rowIndex}`);
            const hiddenInput = document.getElementById(`item-id-${rowIndex}`);
            const dropdownList = document.getElementById(`item-dropdown-list-${rowIndex}`);
            
            if (!searchInput || !hiddenInput || !dropdownList) return;
            
            // Function to render dropdown items based on search term
            function renderDropdown(searchTerm = '') {
                const searchLower = searchTerm.toLowerCase();
                
                // Filter items based on search term
                const filteredItems = itemsData.filter(item => 
                    item.item_code.toLowerCase().includes(searchLower) ||
                    item.item_name.toLowerCase().includes(searchLower) ||
                    (item.category_name && item.category_name.toLowerCase().includes(searchLower))
                );
                
                if (filteredItems.length === 0) {
                    dropdownList.innerHTML = '<div class="searchable-dropdown-no-results">No items found</div>';
                    return;
                }
                
                let html = '';
                filteredItems.forEach(item => {
                    // Skip if this item is already selected in another row
                    const isSelected = selectedItems[item.id] && selectedItems[item.id] !== rowIndex;
                    
                    // Format category name - default to 'Uncategorized' if not set
                    const categoryName = item.category_name || 'Uncategorized';
                    
                    html += `
                        <div class="searchable-dropdown-item ${isSelected ? 'disabled' : ''}" 
                             data-id="${item.id}" 
                             data-code="${item.item_code}"
                             data-name="${item.item_name}"
                             data-category="${categoryName}"
                             ${isSelected ? 'style="opacity:0.5; pointer-events:none;"' : ''}>
                            <span class="item-code">${item.item_code}</span>
                            <span class="item-name">${item.item_name}</span>
                            <span class="item-category">${categoryName}</span>
                        </div>
                    `;
                });
                dropdownList.innerHTML = html;
                
                // Add click handlers to dropdown items
                document.querySelectorAll(`#item-dropdown-list-${rowIndex} .searchable-dropdown-item`).forEach(item => {
                    if (!item.classList.contains('disabled')) {
                        item.addEventListener('click', function() {
                            const id = this.dataset.id;
                            const code = this.dataset.code;
                            const name = this.dataset.name;
                            const category = this.dataset.category;
                            
                            // Clear any previous selection for this row
                            if (hiddenInput.value) {
                                delete selectedItems[hiddenInput.value];
                            }
                            
                            // Set hidden input value
                            hiddenInput.value = id;
                            
                            // Update search input with selected item in the requested format: "001 - Bakal 2mm (Materials)"
                            searchInput.value = `${code} - ${name} (${category})`;
                            searchInput.classList.add('item-selected');
                            
                            // Mark this item as selected
                            selectedItems[id] = rowIndex;
                            
                            // Hide dropdown
                            dropdownList.classList.remove('show');
                            
                            // Update all dropdowns to reflect new selections
                            refreshAllDropdowns();
                        });
                    }
                });
            }
            
            // Handle input events for searching
            searchInput.addEventListener('input', function() {
                if (this.value.trim() === '') {
                    // Clear selection if input is empty
                    if (hiddenInput.value) {
                        delete selectedItems[hiddenInput.value];
                        hiddenInput.value = '';
                    }
                    this.classList.remove('item-selected');
                }
                renderDropdown(this.value);
                dropdownList.classList.add('show');
            });
            
            // Show dropdown on focus
            searchInput.addEventListener('focus', function() {
                // If input is empty, show all items
                if (this.value.trim() === '') {
                    renderDropdown('');
                } else {
                    renderDropdown(this.value);
                }
                dropdownList.classList.add('show');
            });
            
            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                const container = document.getElementById(`item-searchable-container-${rowIndex}`);
                if (container && !container.contains(e.target)) {
                    dropdownList.classList.remove('show');
                }
            });
            
            // Handle keyboard navigation
            searchInput.addEventListener('keydown', function(e) {
                const items = dropdownList.querySelectorAll('.searchable-dropdown-item:not(.disabled)');
                const selectedItem = dropdownList.querySelector('.searchable-dropdown-item.selected');
                let index = -1;
                
                if (selectedItem) {
                    index = Array.from(items).indexOf(selectedItem);
                }
                
                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        if (items.length > 0) {
                            if (selectedItem) {
                                selectedItem.classList.remove('selected');
                                index = (index + 1) % items.length;
                            } else {
                                index = 0;
                            }
                            items[index].classList.add('selected');
                            items[index].scrollIntoView({ block: 'nearest' });
                        }
                        break;
                        
                    case 'ArrowUp':
                        e.preventDefault();
                        if (items.length > 0) {
                            if (selectedItem) {
                                selectedItem.classList.remove('selected');
                                index = index - 1;
                                if (index < 0) index = items.length - 1;
                            } else {
                                index = items.length - 1;
                            }
                            items[index].classList.add('selected');
                            items[index].scrollIntoView({ block: 'nearest' });
                        }
                        break;
                        
                    case 'Enter':
                        e.preventDefault();
                        if (selectedItem) {
                            selectedItem.click();
                        } else if (items.length > 0) {
                            items[0].click();
                        }
                        break;
                        
                    case 'Escape':
                        dropdownList.classList.remove('show');
                        break;
                }
            });
            
            // Initialize with empty state
            renderDropdown('');
        }

        // Function to refresh all dropdowns (update disabled states)
        function refreshAllDropdowns() {
            const rows = document.querySelectorAll('.item-row');
            rows.forEach((row, index) => {
                const dropdownList = document.getElementById(`item-dropdown-list-${index}`);
                if (dropdownList) {
                    const searchTerm = document.getElementById(`item-search-input-${index}`).value;
                    renderDropdownForRow(index, searchTerm);
                }
            });
        }

        // Helper function to render dropdown for a specific row - UPDATED for the new display format
        function renderDropdownForRow(rowIndex, searchTerm) {
            const dropdownList = document.getElementById(`item-dropdown-list-${rowIndex}`);
            if (!dropdownList) return;
            
            const searchLower = searchTerm.toLowerCase();
            
            const filteredItems = itemsData.filter(item => 
                item.item_code.toLowerCase().includes(searchLower) ||
                item.item_name.toLowerCase().includes(searchLower) ||
                (item.category_name && item.category_name.toLowerCase().includes(searchLower))
            );
            
            if (filteredItems.length === 0) {
                dropdownList.innerHTML = '<div class="searchable-dropdown-no-results">No items found</div>';
                return;
            }
            
            let html = '';
            filteredItems.forEach(item => {
                // Skip if this item is already selected in another row
                const isSelected = selectedItems[item.id] && selectedItems[item.id] !== rowIndex;
                
                // Format category name - default to 'Uncategorized' if not set
                const categoryName = item.category_name || 'Uncategorized';
                
                html += `
                    <div class="searchable-dropdown-item ${isSelected ? 'disabled' : ''}" 
                         data-id="${item.id}" 
                         data-code="${item.item_code}"
                         data-name="${item.item_name}"
                         data-category="${categoryName}"
                         ${isSelected ? 'style="opacity:0.5; pointer-events:none;"' : ''}>
                        <span class="item-code">${item.item_code}</span>
                        <span class="item-name">${item.item_name}</span>
                        <span class="item-category">${categoryName}</span>
                    </div>
                `;
            });
            dropdownList.innerHTML = html;
            
            // Add click handlers to dropdown items
            document.querySelectorAll(`#item-dropdown-list-${rowIndex} .searchable-dropdown-item`).forEach(item => {
                if (!item.classList.contains('disabled')) {
                    item.addEventListener('click', function() {
                        const id = this.dataset.id;
                        const code = this.dataset.code;
                        const name = this.dataset.name;
                        const category = this.dataset.category;
                        
                        // Clear any previous selection for this row
                        const hiddenInput = document.getElementById(`item-id-${rowIndex}`);
                        if (hiddenInput.value) {
                            delete selectedItems[hiddenInput.value];
                        }
                        
                        // Set hidden input value
                        hiddenInput.value = id;
                        
                        // Update search input with selected item in the requested format: "001 - Bakal 2mm (Materials)"
                        const searchInput = document.getElementById(`item-search-input-${rowIndex}`);
                        searchInput.value = `${code} - ${name} (${category})`;
                        searchInput.classList.add('item-selected');
                        
                        // Mark this item as selected
                        selectedItems[id] = rowIndex;
                        
                        // Hide dropdown
                        dropdownList.classList.remove('show');
                        
                        // Update all dropdowns to reflect new selections
                        refreshAllDropdowns();
                    });
                }
            });
        }

        // Initialize DataTables
        window.addEventListener('DOMContentLoaded', event => {
            const prTable = document.getElementById('prTable');
            if (prTable) {
                new simpleDatatables.DataTable(prTable, {
                    perPage: 10,
                    perPageSelect: [10, 25, 50, 100],
                    labels: {
                        placeholder: "Search...",
                        perPage: "{select} entries per page",
                        noRows: "No entries to show",
                        info: "Showing {start} to {end} of {rows} entries"
                    },
                    responsive: true,
                    columnDefs: [
                        { targets: 0, width: "15%" }, // Document # column
                        { targets: 1, width: "15%" }, // Requested By
                        { targets: 2, width: "8%" },  // Type
                        { targets: 3, width: "20%" }, // Project/Supplier
                        { targets: 4, width: "8%" },  // Request Date
                        { targets: 5, width: "8%" },  // Doc Type
                        { targets: 6, width: "10%" },  // Status
                        { targets: 7, width: "16%" }  // Actions
                    ]
                });
            }

            // Initialize Bootstrap tooltips
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

            // Show SweetAlert2 notifications
            <?php if (!empty($swal_data)): ?>
                Swal.fire({
                    title: <?php echo json_encode($swal_data['title']); ?>,
                    text: <?php echo json_encode($swal_data['text']); ?>,
                    icon: <?php echo json_encode($swal_data['icon']); ?>,
                    confirmButtonText: 'OK'
                });
            <?php endif; ?>

            // Initialize first searchable dropdown
            createSearchableDropdown(0);

            // Initialize request type selection
            selectRequestType('project');

            // Item management
            let itemCount = 1;
            document.getElementById('addItem').addEventListener('click', function() {
                const container = document.getElementById('itemsContainer');
                const newItem = document.createElement('div');
                newItem.className = 'item-row mb-2 p-3 border rounded position-relative';
                newItem.setAttribute('data-item-index', itemCount);
                
                let warehouseOptions = '<option value="">Select Warehouse</option>';
                <?php foreach ($warehouses as $warehouse): ?>
                warehouseOptions += '<option value="<?php echo $warehouse['id']; ?>"><?php echo addslashes($warehouse['warehouse_name'] . ' - ' . $warehouse['location']); ?></option>';
                <?php endforeach; ?>
                
                // Determine if we should show/hide unit cost based on current request type
                const unitCostDisplay = document.getElementById('request_type').value === 'project' ? 'none' : 'block';
                
                newItem.innerHTML = `
                    <div class="remove-btn-container">
                        <button type="button" class="btn btn-danger btn-sm remove-item">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="row align-items-start">
                        <div class="col-lg-5 col-md-6 col-12 item-col">
                            <div class="searchable-dropdown-container" id="item-searchable-container-${itemCount}">
                                <input type="text" 
                                       class="searchable-dropdown-input item-search-input" 
                                       id="item-search-input-${itemCount}" 
                                       placeholder="Type to search items..."
                                       autocomplete="off"
                                       data-item-index="${itemCount}">
                                <input type="hidden" name="items[${itemCount}][item_id]" id="item-id-${itemCount}" class="item-id-hidden" required>
                                <div class="searchable-dropdown-list" id="item-dropdown-list-${itemCount}"></div>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-6 col-12 qty-col">
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" name="items[${itemCount}][quantity]" min="1" required>
                                <label>Quantity <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-6 col-12 unit-cost-col" style="display: ${unitCostDisplay};">
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" name="items[${itemCount}][unit_cost]" step="0.01" min="0" value="0">
                                <label>Unit Cost (₱)</label>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 col-12 warehouse-col">
                            <div class="form-floating mb-3">
                                <select class="form-select warehouse-select" name="items[${itemCount}][warehouse_id]" required>
                                    ${warehouseOptions}
                                </select>
                                <label>Warehouse <span class="text-danger">*</span></label>
                            </div>
                        </div>
                    </div>
                `;
                container.appendChild(newItem);
                
                // Initialize searchable dropdown for this new item
                createSearchableDropdown(itemCount);
                
                itemCount++;

                // Show remove buttons for all items (including the first one)
                document.querySelectorAll('.remove-item').forEach(btn => {
                    btn.style.display = 'block';
                });
                
                // Apply current column sizing for desktop
                if (window.innerWidth >= 992) {
                    adjustColumnWidths(document.getElementById('request_type').value);
                }
                
                // Hide stock preview when items change
                document.getElementById('stockPreview').style.display = 'none';
            });

            // Remove item
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-item') || e.target.closest('.remove-item')) {
                    const btn = e.target.classList.contains('remove-item') ? e.target : e.target.closest('.remove-item');
                    const itemRow = btn.closest('.item-row');
                    if (document.querySelectorAll('.item-row').length > 1) {
                        // Remove from selected items tracking
                        const hiddenInput = itemRow.querySelector('.item-id-hidden');
                        if (hiddenInput && hiddenInput.value) {
                            delete selectedItems[hiddenInput.value];
                        }
                        
                        itemRow.remove();
                        
                        // Refresh all dropdowns to update disabled states
                        refreshAllDropdowns();
                    }
                    
                    // If only one item remains, hide its remove button
                    if (document.querySelectorAll('.item-row').length === 1) {
                        document.querySelectorAll('.remove-item').forEach(btn => {
                            btn.style.display = 'none';
                        });
                    }
                    
                    // Hide stock preview when items change
                    document.getElementById('stockPreview').style.display = 'none';
                }
            });

            // Use event delegation for view-pr buttons (FIX FOR PAGINATION ISSUE)
            document.addEventListener('click', function(e) {
                // Check if the clicked element or its parent has the view-pr class
                const viewButton = e.target.closest('.view-pr');
                if (viewButton) {
                    const prId = viewButton.getAttribute('data-id');
                    
                    fetch('action/get_pr_details.php?id=' + prId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const pr = data.pr;
                                const items = data.items;
                                
                                let itemsHtml = '';
                                
                                // Build items table WITH Category column
                                itemsHtml = '<div class="table-responsive">';
                                itemsHtml += '<table class="table table-bordered table-striped table-hover">';
                                itemsHtml += '<thead class="table-light">';
                                itemsHtml += '<tr>';
                                itemsHtml += '<th>Item</th>';
                                itemsHtml += '<th>Category</th>'; // New category column
                                itemsHtml += '<th class="text-end">Quantity</th>';
                                itemsHtml += '<th>Warehouse</th>';
                                itemsHtml += '</tr>';
                                itemsHtml += '</thead>';
                                itemsHtml += '<tbody>';

                                items.forEach(item => {
                                    // Get category from item data (assuming it's available)
                                    // If category is not in the item object, you'll need to modify get_pr_details.php to include it
                                    const category = item.category_name || 'N/A';
                                    
                                    itemsHtml += `<tr>`;
                                    itemsHtml += `<td>${item.item_name} ${item.item_code ? '(' + item.item_code + ')' : ''}</td>`;
                                    itemsHtml += `<td>${category}</td>`; // New category column
                                    itemsHtml += `<td class="text-end">${item.quantity}</td>`;
                                    itemsHtml += `<td>${item.warehouse_name || 'N/A'}</td>`;
                                    itemsHtml += `</tr>`;
                                });
                                
                                itemsHtml += '</tbody>';
                                itemsHtml += '</table>';
                                itemsHtml += '</div>';
                                
                                // Determine target info based on request type
                                let targetInfo = '';
                                if (pr.request_type === 'project') {
                                    targetInfo = `<strong>Project:</strong> ${pr.project_name || 'N/A'}`;
                                } else if (pr.request_type === 'supplier') {
                                    targetInfo = `<strong>Supplier:</strong> ${pr.supplier_name || 'N/A'}`;
                                }
                                
                                // Get document type display
                                let docTypeDisplay = '';
                                if (pr.document_type) {
                                    const docTypeLabels = {
                                        'ws': '<span class="badge bg-success" title="Warehouse Stock Only">WS</span>',
                                        'po_ws': '<span class="badge bg-warning" title="PO with Warehouse Stock">PO/WS</span>',
                                        'pr_po': '<span class="badge bg-danger" title="PR to PO">PR + PO</span>',
                                        'direct_po': '<span class="badge bg-info" title="Direct Purchase Order">Direct PO</span>'
                                    };
                                    docTypeDisplay = docTypeLabels[pr.document_type] || pr.document_type;
                                } else {
                                    docTypeDisplay = '<span class="badge bg-secondary">N/A</span>';
                                }
                                
                                // Get PR, PO and WS numbers for display
                                let documentNumbers = [];

                                // For WS document type, only show WS number
                                if (pr.document_type === 'ws') {
                                    if (pr.ws_number) {
                                        documentNumbers.push(`<span class="ws-number">${pr.ws_number}</span>`);
                                    } else {
                                        documentNumbers.push(`<span class="text-muted fst-italic">Not yet created</span>`);
                                    }
                                } else {
                                    // For other document types, show PR, PO, and WS as appropriate
                                    if (pr.pr_number) {
                                        documentNumbers.push(`<span class="pr-number">${pr.pr_number}</span>`);
                                    }
                                    if (pr.po_number) {
                                        documentNumbers.push(`<span class="po-number">${pr.po_number}</span>`);
                                    }
                                    if (pr.ws_number) {
                                        documentNumbers.push(`<span class="ws-number">${pr.ws_number}</span>`);
                                    }
                                }

                                const documentNumbersDisplay = documentNumbers.length > 0 
                                    ? documentNumbers.join(', ') 
                                    : 'N/A';
                                
                                // Helper function to capitalize first letter of each word
                                const capitalizeStatus = (status) => {
                                    if (!status) return '';
                                    return status.split(/_|\s|-/).map(word => {
                                        return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
                                    }).join(' ');
                                };
                                
                                // Get status badges for PO and WS with proper capitalization
                                const getStatusBadge = (status) => {
                                    if (!status) return '<span class="badge bg-secondary">N/A</span>';
                                    
                                    const displayStatus = capitalizeStatus(status);
                                    const statusLower = status.toLowerCase();
                                    
                                    let badgeClass = 'bg-secondary';
                                    if (statusLower === 'approved') {
                                        badgeClass = 'bg-primary';
                                    } else if (statusLower === 'confirmed') {
                                        badgeClass = 'bg-success';
                                    } else if (statusLower === 'rejected') {
                                        badgeClass = 'bg-danger';
                                    } else if (statusLower === 'pending') {
                                        badgeClass = 'bg-warning';
                                    } else if (statusLower === 'processing') {
                                        badgeClass = 'bg-info';
                                    } else if (statusLower === 'completed') {
                                        badgeClass = 'bg-primary';
                                    } else if (statusLower === 'released') {
                                        badgeClass = 'bg-success';
                                    }
                                    
                                    return `<span class="badge ${badgeClass}">${displayStatus}</span>`;
                                };
                                
                                // Get PR status badge
                                const prStatusDisplay = pr.status ? capitalizeStatus(pr.status) : 'N/A';
                                let prBadgeClass = 'bg-secondary';
                                if (pr.status) {
                                    const statusLower = pr.status.toLowerCase();
                                    if (statusLower === 'approved') {
                                        prBadgeClass = 'bg-success';
                                    } else if (statusLower === 'rejected') {
                                        prBadgeClass = 'bg-danger';
                                    } else if (statusLower === 'pending') {
                                        prBadgeClass = 'bg-warning';
                                    } else if (statusLower === 'processing') {
                                        prBadgeClass = 'bg-info';
                                    } else if (statusLower === 'completed') {
                                        prBadgeClass = 'bg-primary';
                                    }
                                }
                                
                                const detailsHtml = `
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <strong>Document #:</strong> 
                                            ${documentNumbersDisplay}
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Type:</strong> ${pr.request_type === 'project' ? 
                                                '<span class="badge bg-info">Project</span>' : 
                                                '<span class="badge bg-primary">Stock</span>'}
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Request Date:</strong> ${formatDate(pr.request_date)}
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Document Type:</strong> ${docTypeDisplay}
                                        </div>
                                        <div class="col-md-6">
                                            ${targetInfo}
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Status:</strong> 
                                            <span class="badge ${prBadgeClass}">${prStatusDisplay}</span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Requested By:</strong> ${pr.firstname} ${pr.middlename ? pr.middlename.charAt(0) + '.' : ''} ${pr.lastname} ${pr.suffix || ''}
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Created At:</strong> ${formatDateTime(pr.created_at)}
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Last Updated:</strong> ${pr.updated_at ? formatDateTime(pr.updated_at) : 'N/A'}
                                        </div>
                                    </div>
                                    <hr>
                                    <h6>Items Requested:</h6>
                                    ${itemsHtml}
                                `;
                                
                                document.getElementById('prDetails').innerHTML = detailsHtml;
                                const viewModal = new bootstrap.Modal(document.getElementById('viewPRModal'));
                                viewModal.show();
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'Failed to load PR details.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error!',
                                text: 'Failed to load PR details.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });

            // Use event delegation for delete-pr buttons (FIX FOR PAGINATION ISSUE)
            document.addEventListener('click', function(e) {
                const deleteButton = e.target.closest('.delete-pr');
                if (deleteButton) {
                    const prId = deleteButton.getAttribute('data-id');
                    const prNumber = deleteButton.getAttribute('data-pr-number');
                    
                    document.getElementById('delete_pr_id').value = prId;
                    document.getElementById('delete_pr_number').textContent = prNumber;
                    
                    const deleteModal = new bootstrap.Modal(document.getElementById('deletePRModal'));
                    deleteModal.show();
                }
            });

            function getStatusBadgeClass(status) {
                switch (status) {
                    case 'approved': return 'badge bg-success';
                    case 'rejected': return 'badge bg-danger';
                    case 'pending': return 'badge bg-warning';
                    case 'processing': return 'badge bg-info';
                    case 'completed': return 'badge bg-primary';
                    default: return 'badge bg-secondary';
                }
            }
        });

        // Request type selection function
        function selectRequestType(type) {
            // Update hidden input and display field
            document.getElementById('request_type').value = type;
            document.getElementById('request_type_display').value = type === 'project' ? 'Project' : 'Stock';
            
            // Update active class on options
            document.querySelectorAll('.request-type-option').forEach(option => {
                option.classList.remove('active');
            });
            document.querySelector(`.request-type-option[data-type="${type}"]`).classList.add('active');
            
            // Show/hide dependent fields
            if (type === 'project') {
                document.getElementById('project-field').style.display = 'block';
                document.getElementById('supplier-field').style.display = 'none';
                document.getElementById('project_id').required = true;
                document.getElementById('supplier_id').required = false;
                document.getElementById('supplier_id').value = '';
                
                // Hide all unit cost fields
                document.querySelectorAll('.unit-cost-col').forEach(field => {
                    field.style.display = 'none';
                });
                
                // Show stock preview and check stock button
                document.getElementById('stockPreview').style.display = 'block';
                document.getElementById('stockMessage').innerHTML = 'Click "Check Stock" to determine the document type based on current stock levels.';
                document.getElementById('checkStockBtn').style.display = 'inline-block';
            } else {
                document.getElementById('project-field').style.display = 'none';
                document.getElementById('supplier-field').style.display = 'block';
                document.getElementById('project_id').required = false;
                document.getElementById('supplier_id').required = false;
                document.getElementById('project_id').value = '';
                
                // Show all unit cost fields
                document.querySelectorAll('.unit-cost-col').forEach(field => {
                    field.style.display = 'block';
                });
                
                // Hide stock preview and check stock button for supplier requests
                document.getElementById('stockPreview').style.display = 'none';
                document.getElementById('checkStockBtn').style.display = 'none';
            }
            
            // Adjust column widths for desktop only
            if (window.innerWidth >= 992) {
                adjustColumnWidths(type);
            }
        }

        // Function to adjust column widths for desktop
        function adjustColumnWidths(type) {
            const itemsContainer = document.getElementById('itemsContainer');
            
            if (type === 'project') {
                itemsContainer.classList.add('project-mode');
            } else {
                itemsContainer.classList.remove('project-mode');
            }
        }

        // Function to check stock availability and preview document type
        function checkStockAvailability() {
            const requestType = document.getElementById('request_type').value;
            
            if (requestType !== 'project') {
                Swal.fire({
                    title: 'Info',
                    text: 'Stock checking is only available for Project Purchase Requests.',
                    icon: 'info',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Get all items
            const itemRows = document.querySelectorAll('.item-row');
            const items = [];
            
            let hasValidItems = false;
            itemRows.forEach((row, index) => {
                const itemId = row.querySelector(`.item-id-hidden`).value;
                const quantityInput = row.querySelector(`input[name*="[quantity]"]`);
                const warehouseSelect = row.querySelector(`select[name*="[warehouse_id]"]`);
                
                if (itemId && quantityInput && warehouseSelect) {
                    const quantity = quantityInput.value;
                    const warehouseId = warehouseSelect.value;
                    
                    if (itemId && quantity && warehouseId) {
                        hasValidItems = true;
                        items.push({
                            item_id: itemId,
                            quantity: quantity,
                            warehouse_id: warehouseId
                        });
                    }
                }
            });
            
            if (!hasValidItems) {
                Swal.fire({
                    title: 'Warning!',
                    text: 'Please add at least one item with item, quantity, and warehouse selected.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Show loading
            Swal.fire({
                title: 'Checking Stock...',
                html: 'Please wait while we check stock availability.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Make AJAX call to check stock
            fetch('action/check_stock_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    items: items
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    let documentType = data.document_type;
                    let docTypeLabel = '';
                    let docTypeClass = '';
                    let docTypeDescription = '';
                    
                    switch(documentType) {
                        case 'ws':
                            docTypeLabel = 'Warehouse Stock Only (WS)';
                            docTypeClass = 'bg-success';
                            docTypeDescription = 'All items have sufficient stock in the selected warehouses. This PR will be processed as Warehouse Stock.';
                            break;
                        case 'po_ws':
                            docTypeLabel = 'PR/WS';
                            docTypeClass = 'bg-warning';
                            docTypeDescription = 'Some items have insufficient stock or are out of stock. This PR will require a Purchase Order for the shortfall.';
                            break;
                        case 'pr_po':
                            docTypeLabel = 'PR to PO (PR/PO)';
                            docTypeClass = 'bg-danger';
                            docTypeDescription = 'All items are out of stock. This PR will be converted to a Purchase Order.';
                            break;
                    }
                    
                    // Build stock details HTML
                    let stockDetailsHtml = '';
                    if (data.stock_details && data.stock_details.length > 0) {
                        stockDetailsHtml = '<div class="mt-3"><h6>Stock Details:</h6><table class="table table-sm table-bordered">';
                        stockDetailsHtml += '<thead><tr><th>Item</th><th>Requested</th><th>Available</th><th>Status</th></tr></thead><tbody>';
                        
                        data.stock_details.forEach(detail => {
                            let status = '';
                            let statusClass = '';
                            if (detail.available == 0) {
                                status = 'Out of Stock';
                                statusClass = 'text-danger';
                            } else if (detail.available < detail.requested) {
                                status = 'Insufficient';
                                statusClass = 'text-warning';
                            } else {
                                status = 'Sufficient';
                                statusClass = 'text-success';
                            }
                            
                            stockDetailsHtml += `<tr>
                                <td>${detail.item_name}</td>
                                <td>${detail.requested}</td>
                                <td>${detail.available}</td>
                                <td class="${statusClass}"><strong>${status}</strong></td>
                            </tr>`;
                        });
                        
                        stockDetailsHtml += '</tbody></table></div>';
                    }
                    
                    // Update stock preview
                    document.getElementById('stockPreview').style.display = 'block';
                    document.getElementById('stockMessage').innerHTML = `
                        <strong>Document Type:</strong> <span class="badge ${docTypeClass}">${docTypeLabel}</span><br>
                        <span class="mt-2 d-block">${docTypeDescription}</span>
                        ${stockDetailsHtml}
                    `;
                    
                    Swal.fire({
                        title: 'Stock Check Complete',
                        html: `
                            <div class="text-center">
                                <span class="badge ${docTypeClass} fs-5 mb-3">${docTypeLabel}</span>
                                <p>${docTypeDescription}</p>
                            </div>
                        `,
                        icon: 'info',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Failed to check stock availability.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to check stock availability.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        }

        // Handle window resize for responsive adjustments
        window.addEventListener('resize', function() {
            const currentType = document.getElementById('request_type').value;
            
            if (window.innerWidth >= 992) {
                adjustColumnWidths(currentType);
            } else {
                // Remove project-mode class on tablet/mobile
                document.getElementById('itemsContainer').classList.remove('project-mode');
            }
        });

        // Form validation
        document.getElementById('createPRForm').addEventListener('submit', function(e) {
            const requestType = document.getElementById('request_type').value;
            const projectId = document.getElementById('project_id').value;
            const supplierId = document.getElementById('supplier_id').value;
            
            if (requestType === 'project' && !projectId) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error!',
                    text: 'Please select a project for the purchase request.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            // Validate at least one item
            const itemRows = document.querySelectorAll('.item-row');
            if (itemRows.length === 0) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error!',
                    text: 'Please add at least one item to the purchase request.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            // Validate item selection for each row
            let itemsValid = true;
            itemRows.forEach((row, index) => {
                const itemId = row.querySelector('.item-id-hidden').value;
                if (!itemId) {
                    itemsValid = false;
                    Swal.fire({
                        title: 'Error!',
                        text: `Please select an item for Row ${index + 1}.`,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
            
            if (!itemsValid) {
                e.preventDefault();
                return false;
            }
            
            // Validate warehouse selection for each item
            let warehouseValid = true;
            itemRows.forEach((row, index) => {
                const warehouseSelect = row.querySelector('select[name*="[warehouse_id]"]');
                if (!warehouseSelect || warehouseSelect.value === '') {
                    warehouseValid = false;
                    Swal.fire({
                        title: 'Error!',
                        text: `Please select a warehouse for Item ${index + 1}.`,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
            
            if (!warehouseValid) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });

        // Reset modal when closed
        document.getElementById('createPRModal').addEventListener('hidden.bs.modal', function() {
            // Clear selected items
            Object.keys(selectedItems).forEach(key => delete selectedItems[key]);
            
            // Reset item rows
            const container = document.getElementById('itemsContainer');
            while (container.children.length > 1) {
                container.removeChild(container.lastChild);
            }
            
            // Reset first item row
            const firstRow = container.querySelector('.item-row');
            if (firstRow) {
                const searchInput = firstRow.querySelector('.item-search-input');
                const hiddenInput = firstRow.querySelector('.item-id-hidden');
                const quantityInput = firstRow.querySelector('input[name*="quantity"]');
                const warehouseSelect = firstRow.querySelector('select[name*="[warehouse_id]"]');
                
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.classList.remove('item-selected');
                }
                if (hiddenInput) hiddenInput.value = '';
                if (quantityInput) quantityInput.value = '';
                if (warehouseSelect) warehouseSelect.value = '';
            }
            
            // Reset item count
            itemCount = 1;
            
            // Reset request type to project
            selectRequestType('project');
        });

        // Logout function
        const logoutLink = document.getElementById('logoutLink');
        if (logoutLink) {
            logoutLink.addEventListener('click', function(e) {
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
        }
    </script>
</body>
</html>