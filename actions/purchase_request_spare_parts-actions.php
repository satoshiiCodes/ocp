<?php
/**
 * actions/purchase_request_spare_parts-actions.php
 *
 * Every action for purchase_request_spare_parts.php lives in this one file: creating
 * a purchase request, updating one, changing its status, deleting it, and handing the
 * page's JavaScript the next document numbers it asks for.
 *
 * The page pulls this file in at the top, so it runs in the page's scope. Each branch
 * reports through the session and then redirects or re-renders, so the messages the
 * page shows are unchanged.
 *
 * The document-number action is a separate HTTP request from the page's JavaScript, so
 * it is detected by its "?generate=" parameter and answers JSON - it lived in
 * actions/generate_document_number.php, which this file replaces.
 *
 * The helpers this calls live in includes/purchase_request_spare_parts-functions.php,
 * required here so they are defined whichever entry point runs first.
 *
 * The blocks below are lifted verbatim from purchase_request_spare_parts.php and
 * generate_document_number.php: the queries and the messages are unchanged.
 */

if (defined('OCP_PURCHASE_REQUEST_SPARE_PARTS_ACTIONS_RAN')) {
    return;
}
define('OCP_PURCHASE_REQUEST_SPARE_PARTS_ACTIONS_RAN', true);

require_once __DIR__ . '/../includes/purchase_request_spare_parts-functions.php';

// ---------------------------------------------------------------------------
// The document-number lookup the page's JavaScript performs. It posts only
// request_type, so that - and the absence of the form's own submit keys - is what
// identifies it. Lifted from actions/generate_document_number.php, which this file
// replaces and which answered JSON unconditionally.
// ---------------------------------------------------------------------------
$ocp_is_doc_number = isset($_POST['request_type'])
    && !isset($_POST['create_pr']) && !isset($_POST['update_pr'])
    && !isset($_POST['update_pr_status']) && !isset($_POST['delete_pr'])
    && !isset($_POST['convert_to_po']);

if ($ocp_is_doc_number) {
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

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit();
    }

    // Get request type
$request_type = $_POST['request_type'] ?? 'stock';
$year = date('Y');

if ($request_type === 'issue_materials') {
    // Generate Withdrawal Slip (WS) number for issue materials
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_pr WHERE YEAR(created_at) = ? AND request_type = 'issue_materials'");
    $stmt->execute([$year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sequence = $result['count'] + 1;
    $document_number = "PRWS-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
} 
elseif ($request_type === 'issue') {
    // Generate Issue Parts Purchase Request (IPPR) number for issue parts
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_pr WHERE YEAR(created_at) = ? AND request_type = 'issue'");
    $stmt->execute([$year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sequence = $result['count'] + 1;
    $document_number = "IPPR-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}
else {
    // Generate regular PR number for stock
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_pr WHERE YEAR(created_at) = ? AND request_type = 'stock'");
    $stmt->execute([$year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sequence = $result['count'] + 1;
    $document_number = "SPR-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

echo json_encode(['success' => true, 'document_number' => $document_number]);

    exit();
}

// ---------------------------------------------------------------------------
// The form submissions.
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
}

// The message state the markup reads, filled in below.
$message = '';
$message_type = '';
$swal_data = array();

// Check for session-based SweetAlert data
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_pr'])) {
        // Get request type first to generate proper number
        $request_type = !empty($_POST['request_type']) ? $_POST['request_type'] : 'stock';
        
        // Generate appropriate number based on request type
        $pr_number = generateSparePartsPRNumber($pdo, $request_type);
        
        $requested_by = $_SESSION['user_id'];
        $supplier_id = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null;
        $request_date = $_POST['request_date'];
        $expected_delivery_date = !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null;
        $status = 'pending';
        $remarks = !empty($_POST['remarks']) ? $_POST['remarks'] : '';
        $vehicle_id = !empty($_POST['vehicle_id']) ? $_POST['vehicle_id'] : null;
        $equipment_id = !empty($_POST['equipment_id']) ? $_POST['equipment_id'] : null;
        $employee_id = !empty($_POST['employee_id']) ? $_POST['employee_id'] : null;
        $technician = !empty($_POST['technician']) ? $_POST['technician'] : null;
        $driver_id = !empty($_POST['driver_id']) ? $_POST['driver_id'] : null;
        
        // Capture purpose from the correct field
        $purpose = null;
        if ($request_type === 'issue_materials') {
            $purpose = !empty($_POST['materials_purpose']) ? $_POST['materials_purpose'] : (!empty($_POST['purpose']) ? $_POST['purpose'] : null);
        } else {
            $purpose = !empty($_POST['purpose']) ? $_POST['purpose'] : null;
        }
        
        $work_order = !empty($_POST['work_order']) ? $_POST['work_order'] : null;
        
        try {
            $pdo->beginTransaction();
            
            // Insert purchase request
            $stmt = $pdo->prepare("INSERT INTO spare_parts_pr 
                (pr_number, requested_by, supplier_id, request_date, expected_delivery_date, status, remarks, request_type, 
                 vehicle_id, equipment_id, employee_id, technician, driver_id, purpose, work_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$pr_number, $requested_by, $supplier_id, $request_date, $expected_delivery_date, $status, $remarks, $request_type,
                           $vehicle_id, $equipment_id, $employee_id, $technician, $driver_id, $purpose, $work_order]);
            $pr_id = $pdo->lastInsertId();
            
            // Record status history
            $historyStmt = $pdo->prepare("INSERT INTO spare_parts_pr_status_history (pr_id, status, changed_by) VALUES (?, ?, ?)");
            $historyStmt->execute([$pr_id, $status, $requested_by]);
            
            // Insert PR items
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                $total_cost = 0;
                foreach ($_POST['items'] as $item) {
                    if (!empty($item['part_id']) && !empty($item['quantity'])) {
                        $part_id = $item['part_id'];
                        $quantity = $item['quantity'];
                        $unit_cost = !empty($item['unit_cost']) ? $item['unit_cost'] : 0;
                        
                        $itemCost = $quantity * $unit_cost;
                        $total_cost += $itemCost;
                        
                        // Check if request type is 'issue' or 'issue_materials' to include issue details in items table
                        if ($request_type === 'issue' || $request_type === 'issue_materials') {
                            $itemStmt = $pdo->prepare("INSERT INTO spare_parts_pr_items 
                                (pr_id, part_id, quantity, unit_cost, vehicle_id, equipment_id, employee_id, technician, driver_id, purpose, work_order) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $itemStmt->execute([$pr_id, $part_id, $quantity, $unit_cost, $vehicle_id, $equipment_id, $employee_id, $technician, $driver_id, $purpose, $work_order]);
                        } else {
                            $itemStmt = $pdo->prepare("INSERT INTO spare_parts_pr_items 
                                (pr_id, part_id, quantity, unit_cost) 
                                VALUES (?, ?, ?, ?)");
                            $itemStmt->execute([$pr_id, $part_id, $quantity, $unit_cost]);
                        }
                    }
                }
                
                // Update total estimated cost (only for stock type)
                if ($request_type === 'stock') {
                    $updateStmt = $pdo->prepare("UPDATE spare_parts_pr SET total_estimated_cost = ? WHERE id = ?");
                    $updateStmt->execute([$total_cost, $pr_id]);
                }
            }
            
            $pdo->commit();
            
            $_SESSION['swal_data'] = array(
                'title' => 'Success!',
                'text' => ($request_type === 'issue_materials' ? 'Withdrawal Slip' : ($request_type === 'issue' ? 'Issue Parts Purchase Request' : 'Purchase Request')) . ' created successfully!',
                'icon' => 'success'
            );
            header("Location: purchase_request_spare_parts.php");
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Error!',
                'text' => 'Failed to create ' . ($request_type === 'issue_materials' ? 'withdrawal slip' : ($request_type === 'issue' ? 'issue parts purchase request' : 'purchase request')) . ': ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
    }
    elseif (isset($_POST['delete_pr'])) {
        // Delete purchase request
        $pr_id = $_POST['pr_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Delete from job orders if exists
            $deleteJOStmt = $pdo->prepare("DELETE FROM spare_parts_job_orders WHERE pr_id = ?");
            $deleteJOStmt->execute([$pr_id]);
            
            // Delete from withdrawal slips if exists
            $deleteWSStmt = $pdo->prepare("DELETE FROM spare_parts_withdrawal_slips WHERE pr_id = ?");
            $deleteWSStmt->execute([$pr_id]);
            
            // Delete items first
            $deleteItemsStmt = $pdo->prepare("DELETE FROM spare_parts_pr_items WHERE pr_id = ?");
            $deleteItemsStmt->execute([$pr_id]);
            
            // Delete status history
            $deleteHistoryStmt = $pdo->prepare("DELETE FROM spare_parts_pr_status_history WHERE pr_id = ?");
            $deleteHistoryStmt->execute([$pr_id]);
            
            // Delete PR
            $deletePRStmt = $pdo->prepare("DELETE FROM spare_parts_pr WHERE id = ?");
            $deletePRStmt->execute([$pr_id]);
            
            $pdo->commit();
            
            $_SESSION['swal_data'] = array(
                'title' => 'Success!',
                'text' => 'Document deleted successfully!',
                'icon' => 'success'
            );
            header("Location: purchase_request_spare_parts.php");
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $swal_data = array(
                'title' => 'Error!',
                'text' => 'Failed to delete document: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
    }
    elseif (isset($_POST['convert_to_po'])) {
        // Convert PR to PO
        $pr_id = $_POST['pr_id'];
        
        try {
            // Get PR details
            $prStmt = $pdo->prepare("SELECT * FROM spare_parts_pr WHERE id = ? AND status = 'approved'");
            $prStmt->execute([$pr_id]);
            $pr = $prStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($pr) {
                // Generate PO number
                $year = date('Y');
                $poStmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_part_po WHERE YEAR(created_at) = ?");
                $poStmt->execute([$year]);
                $poResult = $poStmt->fetch(PDO::FETCH_ASSOC);
                $poSequence = $poResult['count'] + 1;
                $po_number = "PO-$year-" . str_pad($poSequence, 4, '0', STR_PAD_LEFT);
                
                // Create PO
                $insertPO = $pdo->prepare("INSERT INTO spare_part_po 
                    (po_number, pr_id, supplier_id, order_date, expected_delivery_date, status, total_amount) 
                    VALUES (?, ?, ?, ?, ?, 'pending', ?)");
                $insertPO->execute([$po_number, $pr_id, $pr['supplier_id'], date('Y-m-d'), $pr['expected_delivery_date'], $pr['total_estimated_cost']]);
                $po_id = $pdo->lastInsertId();
                
                // Get PR items
                $itemsStmt = $pdo->prepare("SELECT * FROM spare_parts_pr_items WHERE pr_id = ?");
                $itemsStmt->execute([$pr_id]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Convert items to PO items
                foreach ($items as $item) {
                    $poItemStmt = $pdo->prepare("INSERT INTO spare_part_po_items (po_id, part_id, quantity, unit_price, total_price) 
                        VALUES (?, ?, ?, ?, ?)");
                    $total_price = $item['quantity'] * ($item['unit_cost'] ?: 0);
                    $poItemStmt->execute([$po_id, $item['part_id'], $item['quantity'], $item['unit_cost'], $total_price]);
                }
                
                // Update PR status to processing
                $updatePR = $pdo->prepare("UPDATE spare_parts_pr SET status = 'processing' WHERE id = ?");
                $updatePR->execute([$pr_id]);
                
                $_SESSION['swal_data'] = array(
                    'title' => 'Success!',
                    'text' => 'Purchase Request converted to Purchase Order successfully!',
                    'icon' => 'success'
                );
            } else {
                $_SESSION['swal_data'] = array(
                    'title' => 'Error!',
                    'text' => 'Purchase Request must be approved before converting to PO.',
                    'icon' => 'error'
                );
            }
            
            header("Location: purchase_request_spare_parts.php");
            exit();
            
        } catch (PDOException $e) {
            $swal_data = array(
                'title' => 'Error!',
                'text' => 'Failed to convert to PO: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
    }
}
