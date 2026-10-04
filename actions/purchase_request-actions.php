<?php
/**
 * actions/purchase_request-actions.php
 *
 * Every action for purchase_request.php lives in this one file: creating a purchase
 * request, updating one, changing its status, and deleting it.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. Every
 * branch reports through the session and then redirects or re-renders, so the
 * messages the page shows are unchanged.
 *
 * The two helpers the handlers call (generatePRNumber and checkStockAvailability)
 * live here with them, because that is what they serve.
 *
 * The bodies below are lifted verbatim from purchase_request.php: the queries, the
 * stock checks and the messages are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}
if (defined('OCP_PURCHASE_REQUEST_ACTIONS_RAN')) {
    return;
}
define('OCP_PURCHASE_REQUEST_ACTIONS_RAN', true);

// The message state the markup reads, filled in below.
$message = '';
$message_type = '';
$swal_data = array();

// Check for session-based SweetAlert data
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

// The two helpers the handlers call. They are shared with the page, which calls
// generatePRNumber() while rendering its form, so they live in their own include
// that both files require.
require_once __DIR__ . '/../includes/purchase_request-functions.php';


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
