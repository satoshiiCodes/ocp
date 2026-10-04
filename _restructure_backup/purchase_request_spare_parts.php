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
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix, department, position, accounttype FROM users WHERE id = :id");
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

// Generate PR number for spare parts
function generateSparePartsPRNumber($pdo, $request_type = 'stock') {
    $year = date('Y');
    
    if ($request_type === 'issue_materials') {
        // Generate Withdrawal Slip (WS) number for issue materials
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_pr WHERE YEAR(created_at) = ? AND request_type = 'issue_materials'");
        $stmt->execute([$year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $sequence = $result['count'] + 1;
        return "PRWS-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    } 
    elseif ($request_type === 'issue') {
        // Generate Issue Parts Purchase Request (IPPR) number for issue parts
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_pr WHERE YEAR(created_at) = ? AND request_type = 'issue'");
        $stmt->execute([$year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $sequence = $result['count'] + 1;
        return "IPPR-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    } 
    else {
        // Generate regular PR number for stock
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_pr WHERE YEAR(created_at) = ? AND request_type = 'stock'");
        $stmt->execute([$year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $sequence = $result['count'] + 1;
        return "SPR-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}

// Generate Withdrawal Slip Number
function generateWithdrawalSlipNumber($pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_withdrawal_slips WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sequence = $result['count'] + 1;
    return "WS-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

// Generate Job Order Number
function generateJobOrderNumber($pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts_job_orders WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $sequence = $result['count'] + 1;
    return "JO-$year-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

// Function to format employee name for display
function formatEmployeeName($employee) {
    $name = $employee['firstname'];
    if (!empty($employee['middlename'])) {
        $name .= ' ' . substr($employee['middlename'], 0, 1) . '.';
    }
    $name .= ' ' . $employee['lastname'];
    if (!empty($employee['suffix'])) {
        $name .= ' ' . $employee['suffix'];
    }
    return $name;
}

// Function to format date as mm-dd-yyyy
function formatDate($date) {
    if (empty($date) || $date == '0000-00-00') {
        return 'Not Set';
    }
    return date('m-d-Y', strtotime($date));
}

// Process form submissions
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

// Fetch data
try {
    // Get purchase requests with supplier information
    $prStmt = $pdo->prepare("
        SELECT pr.*, 
               u.firstname, u.middlename, u.lastname, u.suffix,
               s.supplier_name,
               v.vehicle_name, v.plate_number,
               e.equipment_name,
               emp.firstname as emp_firstname, emp.middlename as emp_middlename, 
               emp.lastname as emp_lastname, emp.suffix as emp_suffix,
               emp.position as emp_position,
               d.firstname as driver_firstname, d.middlename as driver_middlename, 
               d.lastname as driver_lastname, d.suffix as driver_suffix,
               d.position as driver_position
        FROM spare_parts_pr pr
        LEFT JOIN users u ON pr.requested_by = u.id
        LEFT JOIN spare_parts_suppliers s ON pr.supplier_id = s.id
        LEFT JOIN vehicles v ON pr.vehicle_id = v.id
        LEFT JOIN equipment e ON pr.equipment_id = e.id
        LEFT JOIN employee emp ON pr.employee_id = emp.id
        LEFT JOIN employee d ON pr.driver_id = d.id
        ORDER BY pr.created_at DESC
    ");
    $prStmt->execute();
    $purchase_requests = $prStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get ALL spare parts for dropdown (with current stock) - for Stock Purchase Request
    $allPartsStmt = $pdo->prepare("
        SELECT sp.id, sp.part_number, sp.part_name, spc.category_name,
               COALESCE(spi.quantity, 0) as current_stock,
               COALESCE(spi.price_per_unit, 0) as current_price,
               COALESCE(spml.min_stock, 0) as min_stock
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_inventory spi ON sp.id = spi.part_id
        LEFT JOIN spare_parts_min_levels spml ON sp.id = spml.part_id
        ORDER BY sp.part_name
    ");
    $allPartsStmt->execute();
    $all_parts = $allPartsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get spare parts EXCLUDING 'Materials' category - for Issue Parts Purchase Request
    $issuePartsStmt = $pdo->prepare("
        SELECT sp.id, sp.part_number, sp.part_name, spc.category_name,
               COALESCE(spi.quantity, 0) as current_stock,
               COALESCE(spi.price_per_unit, 0) as current_price,
               COALESCE(spml.min_stock, 0) as min_stock
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_inventory spi ON sp.id = spi.part_id
        LEFT JOIN spare_parts_min_levels spml ON sp.id = spml.part_id
        WHERE spc.category_name != 'Materials'
        ORDER BY sp.part_name
    ");
    $issuePartsStmt->execute();
    $issue_parts = $issuePartsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get spare parts ONLY 'Materials' category - for Issue Materials Purchase Request
    $issueMaterialsStmt = $pdo->prepare("
        SELECT sp.id, sp.part_number, sp.part_name, spc.category_name,
               COALESCE(spi.quantity, 0) as current_stock,
               COALESCE(spi.price_per_unit, 0) as current_price,
               COALESCE(spml.min_stock, 0) as min_stock
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_inventory spi ON sp.id = spi.part_id
        LEFT JOIN spare_parts_min_levels spml ON sp.id = spml.part_id
        WHERE spc.category_name = 'Materials'
        ORDER BY sp.part_name
    ");
    $issueMaterialsStmt->execute();
    $issue_materials = $issueMaterialsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get suppliers for dropdown
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM spare_parts_suppliers ORDER BY supplier_name");
    $suppliersStmt->execute();
    $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get vehicles for dropdown
    $vehiclesStmt = $pdo->prepare("SELECT id, vehicle_name, plate_number FROM vehicles ORDER BY vehicle_name");
    $vehiclesStmt->execute();
    $vehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get equipment for dropdown
    $equipmentStmt = $pdo->prepare("SELECT id, equipment_name FROM equipment ORDER BY equipment_name");
    $equipmentStmt->execute();
    $equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get employees for dropdown
    $employeesStmt = $pdo->prepare("
        SELECT id, employee_id, firstname, middlename, lastname, suffix, position 
        FROM employee 
        WHERE status = 'active'
        ORDER BY lastname, firstname
    ");
    $employeesStmt->execute();
    $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format employee names for display
    $formatted_employees = [];
    foreach ($employees as $emp) {
        $emp['display_name'] = formatEmployeeName($emp) . ' - ' . $emp['position'];
        $formatted_employees[] = $emp;
    }
    
    // Get mechanics for technician dropdown
    $mechanicsStmt = $pdo->prepare("
        SELECT id, employee_id, firstname, middlename, lastname, suffix, position 
        FROM employee 
        WHERE status = 'active' 
        AND position = 'Mechanic'
        ORDER BY lastname, firstname
    ");
    $mechanicsStmt->execute();
    $mechanics = $mechanicsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format mechanic names for display
    $formatted_mechanics = [];
    foreach ($mechanics as $mech) {
        $mech['display_name'] = formatEmployeeName($mech) . ' - ' . $mech['position'];
        $formatted_mechanics[] = $mech;
    }
    
    // Get drivers for dropdown
    $driversStmt = $pdo->prepare("
        SELECT id, employee_id, firstname, middlename, lastname, suffix, position 
        FROM employee 
        WHERE status = 'active' 
        AND position = 'Driver'
        ORDER BY lastname, firstname
    ");
    $driversStmt->execute();
    $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format driver names for display
    $formatted_drivers = [];
    foreach ($drivers as $drv) {
        $drv['display_name'] = formatEmployeeName($drv) . ' - ' . $drv['position'];
        $formatted_drivers[] = $drv;
    }
    
    // Get PR status counts for dashboard
    $statusStmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM spare_parts_pr 
        GROUP BY status
    ");
    $statusStmt->execute();
    $status_counts = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total documents count
    $totalCountStmt = $pdo->prepare("SELECT COUNT(*) as total FROM spare_parts_pr");
    $totalCountStmt->execute();
    $totalCount = $totalCountStmt->fetch(PDO::FETCH_ASSOC);
    $total_requests = $totalCount['total'];
    
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

// Function to format employee name for PR display
function formatEmployeeNameForDisplay($pr) {
    if (empty($pr['emp_firstname'])) {
        return 'N/A';
    }
    
    $name = $pr['emp_firstname'];
    if (!empty($pr['emp_middlename'])) {
        $name .= ' ' . substr($pr['emp_middlename'], 0, 1) . '.';
    }
    $name .= ' ' . $pr['emp_lastname'];
    if (!empty($pr['emp_suffix'])) {
        $name .= ' ' . $pr['emp_suffix'];
    }
    return $name;
}

// Function to format driver name for PR display
function formatDriverNameForDisplay($pr) {
    if (empty($pr['driver_firstname'])) {
        return 'N/A';
    }
    
    $name = $pr['driver_firstname'];
    if (!empty($pr['driver_middlename'])) {
        $name .= ' ' . substr($pr['driver_middlename'], 0, 1) . '.';
    }
    $name .= ' ' . $pr['driver_lastname'];
    if (!empty($pr['driver_suffix'])) {
        $name .= ' ' . $pr['driver_suffix'];
    }
    return $name;
}

// Function to get request type badge
function getRequestTypeBadge($type) {
    switch ($type) {
        case 'stock':
            return 'badge bg-primary';
        case 'issue':
            return 'badge bg-success';
        case 'issue_materials':
            return 'badge bg-warning text-dark';
        default:
            return 'badge bg-secondary';
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
            return 'Issue Materials Withdrawal Slip';
        default:
            return ucfirst($type);
    }
}

// Function to get document number prefix
function getDocumentNumberPrefix($type) {
    switch ($type) {
        case 'issue_materials':
            return 'WS';
        case 'issue':
            return 'IPPR';
        default:
            return 'SPR';
    }
}

// Function to get document type text
function getDocumentTypeText($type) {
    switch ($type) {
        case 'issue_materials':
            return 'Withdrawal Slip';
        case 'issue':
            return 'Issue Parts Purchase Request';
        default:
            return 'Purchase Request';
    }
}

// Generate initial PR number for the form (default to stock type)
$default_pr_number = generateSparePartsPRNumber($pdo, 'stock');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Motorpool PR - OCP Construction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .status-badge {
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
            padding: 10px 0;
        }
        .item-row:last-child {
            border-bottom: none;
        }
        .action-buttons {
            display: flex;
            flex-wrap: nowrap;
            gap: 5px;
            white-space: nowrap;
            justify-content: center;
        }
        .action-buttons .btn {
            min-width: 35px;
            padding: 0.25rem 0.5rem;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .request-type-badge {
            font-size: 0.7rem;
            margin-left: 5px;
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
        .issue-fields {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #fff3cd;
        }
        .issue-fields h6 {
            color: #198754;
        }
        .materials-fields {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #fff3cd;
        }
        .materials-fields h6 {
            color: #856404;
        }
        .cost-fields {
            transition: all 0.3s ease;
        }
        .cost-fields.hidden {
            display: none;
        }
        @media (max-width: 1400px) {
            .action-buttons {
                flex-wrap: wrap;
                gap: 3px;
                justify-content: center;
            }
            .action-buttons .btn {
                font-size: 0.75rem;
                padding: 0.2rem 0.4rem;
            }
        }
        .badge-bg-warning {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }
        .request-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 10px;
        }
        .ws-number {
            color: #fd7e14;
        }
        .spr-number {
            color: #0d6efd;
        }
        .ippr-number {
            color: #28a745;
        }
        .po-number {
            color: #6f42c1;
        }
        .text-muted-italic {
            color: #6c757d;
            font-style: italic;
        }
        .jo-number {
            color: #20c997;
        }
        .total-requests-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .pdf-dropdown {
            min-width: 200px;
            padding: 0.5rem 0;
        }
        .pdf-dropdown .dropdown-item {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        .pdf-dropdown .dropdown-item i {
            width: 20px;
            margin-right: 10px;
        }
        .pdf-dropdown .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        .pdf-dropdown-divider {
            height: 1px;
            margin: 0.5rem 0;
            background-color: #e9ecef;
        }
        
        /* Searchable dropdown styles */
        .searchable-dropdown-container {
            position: relative;
            width: 100%;
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
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        .searchable-dropdown-item:hover,
        .searchable-dropdown-item.selected {
            background-color: #e7f1ff;
        }
        .searchable-dropdown-item:last-child {
            border-bottom: none;
        }
        .searchable-dropdown-item .item-code {
            font-weight: 600;
            color: #0d6efd;
        }
        .searchable-dropdown-item .item-name {
            color: #212529;
        }
        .searchable-dropdown-item .item-category {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .searchable-dropdown-no-results {
            padding: 0.5rem 1rem;
            color: #6c757d;
            font-style: italic;
        }
        /* Removed selected-item-info styles */
    </style>
</head>
<body class="sb-nav-fixed">
    <?php include 'includes/top_bar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'includes/side_menu.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-4">Motorpool PR</h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="spare_parts_inventory.php">Motorpool Inventory</a></li>
                        <li class="breadcrumb-item active">Motorpool PR</li>
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
                            <div class="card total-requests-card text-white">
                                <div class="card-body text-center">
                                    <h4 class="card-title">
                                        <?php echo $total_requests; ?>
                                    </h4>
                                    <p class="card-text">Total Requests</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Purchase Requests List -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-table me-1"></i>
                            Spare Parts/Materials PR, Withdrawal Slips & Job Order
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPRModal">
                            <i class="fas fa-plus me-1"></i> Create New Document
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($purchase_requests)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" id="prTable">
                                <thead>
                                    <tr>
                                        <th>Document #</th>
                                        <th>Document Type</th>
                                        <th>Supplier/Vehicle/Employee</th>
                                        <th>Request Date</th>
                                        <th>Expected Delivery</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($purchase_requests as $pr): ?>
                                    <?php 
                                        $is_withdrawal_slip = ($pr['request_type'] ?? 'stock') === 'issue_materials';
                                        $is_issue_parts = ($pr['request_type'] ?? 'stock') === 'issue';
                                        
                                        if ($is_withdrawal_slip) {
                                            $document_type = 'Withdrawal Slip';
                                        } elseif ($is_issue_parts) {
                                            $document_type = 'Issue Parts PR';
                                        } else {
                                            $document_type = 'Purchase Request';
                                        }
                                        
                                        // Determine what to show in the Supplier/Vehicle/Employee column
                                        $assignment_info = 'N/A';
                                        
                                        // For Stock Purchase Requests, show supplier name
                                        if (($pr['request_type'] ?? 'stock') === 'stock' && !empty($pr['supplier_name'])) {
                                            $assignment_info = 'Supplier: ' . $pr['supplier_name'];
                                        }
                                        // For Issue Parts Purchase Request, show vehicle or equipment
                                        elseif ($is_issue_parts) {
                                            if (!empty($pr['vehicle_name'])) {
                                                $assignment_info = 'Vehicle: ' . $pr['vehicle_name'] . ' (' . $pr['plate_number'] . ')';
                                            } elseif (!empty($pr['equipment_name'])) {
                                                $assignment_info = 'Equipment: ' . $pr['equipment_name'];
                                            }
                                        }
                                        // For Issue Materials Withdrawal Slip, show employee name
                                        elseif ($is_withdrawal_slip && !empty($pr['emp_firstname'])) {
                                            $assignment_info = 'Employee: ' . formatEmployeeNameForDisplay($pr);
                                        }
                                        
                                        // Get PO number for stock requests
                                        $po_number = null;
                                        if (($pr['request_type'] ?? 'stock') === 'stock') {
                                            $poCheckStmt = $pdo->prepare("SELECT po_number FROM spare_part_po WHERE pr_id = ? LIMIT 1");
                                            $poCheckStmt->execute([$pr['id']]);
                                            $poData = $poCheckStmt->fetch(PDO::FETCH_ASSOC);
                                            $po_number = $poData ? $poData['po_number'] : null;
                                        }
                                        
                                        // Get Job Order number for issue parts
                                        $jo_number = null;
                                        if ($is_issue_parts) {
                                            $joCheckStmt = $pdo->prepare("SELECT job_order_number FROM spare_parts_job_orders WHERE pr_id = ? LIMIT 1");
                                            $joCheckStmt->execute([$pr['id']]);
                                            $joData = $joCheckStmt->fetch(PDO::FETCH_ASSOC);
                                            $jo_number = $joData ? $joData['job_order_number'] : null;
                                        }
                                        
                                        // Get Withdrawal Slip number for issue materials
                                        $ws_number = null;
                                        if ($is_withdrawal_slip) {
                                            $wsCheckStmt = $pdo->prepare("SELECT withdrawal_slip_number FROM spare_parts_withdrawal_slips WHERE pr_id = ? LIMIT 1");
                                            $wsCheckStmt->execute([$pr['id']]);
                                            $wsData = $wsCheckStmt->fetch(PDO::FETCH_ASSOC);
                                            $ws_number = $wsData ? $wsData['withdrawal_slip_number'] : null;
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <?php if ($is_issue_parts): ?>
                                                    <?php if ($jo_number): ?>
                                                        <span class="jo-number">
                                                            <?php echo htmlspecialchars($jo_number); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">Not yet Created</span>
                                                    <?php endif; ?>
                                                <?php elseif ($is_withdrawal_slip): ?>
                                                    <?php if ($ws_number): ?>
                                                        <span class="ws-number">
                                                            <?php echo htmlspecialchars($ws_number); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">Not yet Created</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="spr-number">
                                                        <?php echo htmlspecialchars($pr['pr_number']); ?>
                                                    </span>
                                                    <?php if ($po_number): ?>
                                                        <br>
                                                        <span class="po-number">
                                                            <?php echo htmlspecialchars($po_number); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="<?php echo getRequestTypeBadge($pr['request_type'] ?? 'stock'); ?> request-type-badge">
                                                <?php echo getRequestTypeText($pr['request_type'] ?? 'stock'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($assignment_info); ?></td>
                                        <td><?php echo formatDate($pr['request_date']); ?></td>
                                        <td><?php echo !empty($pr['expected_delivery_date']) ? formatDate($pr['expected_delivery_date']) : 'Not Set'; ?></td>
                                        <td>
                                            <span class="<?php echo getStatusBadge($pr['status']); ?> status-badge">
                                                <?php echo ucfirst($pr['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <!-- PDF Dropdown - Only show if there's at least one PDF option -->
                                                <?php 
                                                $has_pdf_options = false;
                                                
                                                // Check for Stock PR PDF options
                                                if ($pr['request_type'] === 'stock') {
                                                    $has_pdf_options = true; // Always show for stock PR as it has at least the PR PDF
                                                }
                                                // Check for Issue Parts PDF options
                                                elseif ($pr['request_type'] === 'issue' && $jo_number) {
                                                    $has_pdf_options = true;
                                                }
                                                // Check for Issue Materials PDF options
                                                elseif ($pr['request_type'] === 'issue_materials' && $ws_number) {
                                                    $has_pdf_options = true;
                                                }
                                                
                                                if ($has_pdf_options): 
                                                ?>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-danger dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </button>
                                                    <ul class="dropdown-menu pdf-dropdown">
                                                        <?php if ($pr['request_type'] === 'stock'): ?>
                                                            <!-- Stock Purchase Request PDF options -->
                                                            <li>
                                                                <a class="dropdown-item" href="generate_spare_parts_pr_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                    <i class="fas fa-file-pdf text-danger"></i> Purchase Request
                                                                </a>
                                                            </li>
                                                            <?php if ($po_number): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="generate_spare_parts_po_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                    <i class="fas fa-file-pdf text-danger"></i> Purchase Order
                                                                </a>
                                                            </li>
                                                            <?php endif; ?>
                                                        <?php elseif ($pr['request_type'] === 'issue'): ?>
                                                            <!-- Issue Parts PDF options -->
                                                            <?php if ($jo_number): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="job_order_slip_PDF.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                    <i class="fas fa-file-pdf text-danger"></i> Job Order Slip
                                                                </a>
                                                            </li>
                                                            <?php endif; ?>
                                                        <?php elseif ($pr['request_type'] === 'issue_materials'): ?>
                                                            <!-- Issue Materials PDF options -->
                                                            <?php if ($ws_number): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="generate_spare_parts_ws_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                    <i class="fas fa-file-pdf text-danger"></i> Withdrawal Slip
                                                                </a>
                                                            </li>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                                <?php endif; ?>

                                                <!-- View Details button -->
                                                <button class="btn btn-sm btn-info view-pr-btn" data-id="<?php echo $pr['id']; ?>" 
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <!-- View Routing button -->
                                                <a href="pr_spare_view_routing.php?id=<?php echo $pr['id']; ?>" 
                                                class="btn btn-sm btn-warning" 
                                                data-bs-toggle="tooltip" data-bs-placement="top" title="View Routing">
                                                    <i class="fas fa-route"></i>
                                                </a>
                                                
                                                <!-- Delete button -->
                                                <?php if ($pr['status'] == 'pending' && $pr['requested_by'] == $_SESSION['user_id']): ?>
                                                <button class="btn btn-sm btn-danger delete-pr-btn" data-id="<?php echo $pr['id']; ?>" 
                                                        data-pr-number="<?php echo $pr['pr_number']; ?>"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Delete Document">
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
                            <h5>No Documents Found</h5>
                            <p class="text-muted">Create your first purchase request or withdrawal slip to get started.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPRModal">
                                <i class="fas fa-plus me-1"></i> Create First Document
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <!-- Create PR Modal -->
    <div class="modal fade" id="createPRModal" tabindex="-1" aria-labelledby="createPRModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPRModalLabel">Create New Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="createPRForm">
                    <input type="hidden" name="create_pr" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12" hidden>
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="document_number" value="<?php echo $default_pr_number; ?>" readonly disabled>
                                    <label id="document_number_label">PR Number</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="prepared_by_field" value="<?php echo $display_name; ?>" readonly disabled>
                                    <label id="prepared_by_label">Requested By</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" name="request_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    <label>Request Date <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" name="expected_delivery_date">
                                    <label>Expected Delivery Date</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Request Type Selector -->
                        <div class="request-type-selector mb-3">
                            <h6>Select Document Type <span class="text-danger">*</span></h6>
                            <div class="request-type-grid">
                                <!-- Stock Purchase Request -->
                                <div class="request-type-option active" data-type="stock" onclick="selectRequestType('stock')">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-boxes fa-2x text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Stock Purchase Request (SPR)</h6>
                                            <p class="mb-0 text-muted small">For replenishing inventory stock levels</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Issue Parts Purchase Request -->
                                <div class="request-type-option" data-type="issue" onclick="selectRequestType('issue')">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-truck-loading fa-2x text-success"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Issue Parts Job Order (JO)</h6>
                                            <p class="mb-0 text-muted small">For direct issuance to vehicles/equipment</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Issue Materials Withdrawal Slip -->
                                <div class="request-type-option" data-type="issue_materials" onclick="selectRequestType('issue_materials')">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-hard-hat fa-2x text-warning"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Issue Materials Withdrawal Slip (WS)</h6>
                                            <p class="mb-0 text-muted small">For materials issuance to employees</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hidden input for request type -->
                        <input type="hidden" name="request_type" id="request_type" value="stock" required>
                        
                        <!-- Issue Parts Fields -->
                        <div class="issue-fields" id="issue_fields" style="display: none;">
                            <h6><i class="fas fa-truck-loading me-1"></i> Issue Parts Details</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="pr_vehicle_id" name="vehicle_id">
                                            <option value="">Select Vehicle (Optional)</option>
                                            <?php foreach ($vehicles as $vehicle): ?>
                                            <option value="<?php echo $vehicle['id']; ?>">
                                                <?php echo htmlspecialchars($vehicle['vehicle_name'] . ' (' . $vehicle['plate_number'] . ')'); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="pr_vehicle_id">Vehicle</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="pr_equipment_id" name="equipment_id">
                                            <option value="">Select Equipment (Optional)</option>
                                            <?php foreach ($equipment as $eq): ?>
                                            <option value="<?php echo $eq['id']; ?>">
                                                <?php echo htmlspecialchars($eq['equipment_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="pr_equipment_id">Equipment</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="technician" name="technician">
                                            <option value="">Select Technician</option>
                                            <?php foreach ($formatted_mechanics as $mech): ?>
                                            <option value="<?php echo $mech['id']; ?>">
                                                <?php echo htmlspecialchars($mech['display_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="technician">Technician Name <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="driver_id" name="driver_id">
                                            <option value="">Select Driver</option>
                                            <?php foreach ($formatted_drivers as $drv): ?>
                                            <option value="<?php echo $drv['id']; ?>">
                                                <?php echo htmlspecialchars($drv['display_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="driver_id">Driver Name <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="issue_purpose" name="purpose">
                                        <label for="issue_purpose">Purpose <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Issue Materials Fields -->
                        <div class="materials-fields" id="materials_fields" style="display: none;">
                            <h6><i class="fas fa-hard-hat me-1"></i> Issue Materials Details</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="employee_id" name="employee_id">
                                            <option value="">Select Employee</option>
                                            <?php foreach ($formatted_employees as $emp): ?>
                                            <option value="<?php echo $emp['id']; ?>">
                                                <?php echo htmlspecialchars($emp['display_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="employee_id">Employee Name <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="materials_purpose" name="materials_purpose">
                                        <label for="materials_purpose">Purpose <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Supplier Field -->
                        <div class="row supplier-field">
                            <div class="col-md-12">
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
                        </div>
                        
                        <!-- Items Section -->
                        <div class="mb-3">
                            <h6>Requested Items <span class="text-danger">*</span></h6>
                            <div id="itemsContainer">
                                <div class="item-row mb-3 p-3 border rounded" id="item-row-0">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <!-- Searchable dropdown container -->
                                            <div class="searchable-dropdown-container" id="searchable-container-0">
                                                <input type="text" 
                                                    class="searchable-dropdown-input" 
                                                    id="search-input-0" 
                                                    placeholder="Type to search items..."
                                                    autocomplete="off"
                                                    required>
                                                <input type="hidden" name="items[0][part_id]" id="part-id-0" required>
                                                <div class="searchable-dropdown-list" id="dropdown-list-0"></div>
                                            </div>
                                            <!-- Removed selected-item-info div -->
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-floating mb-3">
                                                <input type="number" class="form-control" name="items[0][quantity]" min="1" required 
                                                    onchange="calculateTotal(this, 0)">
                                                <label>Quantity <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-2 cost-fields">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" name="items[0][unit_cost]" 
                                                    id="unit-cost-0" onchange="calculateTotal(this, 0)" placeholder="Enter unit cost" value="0">
                                                <label>Unit Cost (₱)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3 cost-fields">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="item-total-0" value="₱0.00" readonly>
                                                <label>Item Total</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-10"></div>
                                        <div class="col-md-2 d-flex align-items-center">
                                            <button type="button" class="btn btn-danger btn-sm remove-item" style="display: none;">
                                                <i class="fas fa-times"></i> Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addItem">
                                    <i class="fas fa-plus me-1"></i> Add Another Item
                                </button>
                                <div class="fw-bold cost-fields" id="grand-total">Grand Total: ₱0.00</div>
                            </div>
                        </div>
                        
                        <!-- Remarks Field -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-floating mb-3">
                                    <textarea class="form-control" name="remarks" id="remarks" style="height: 100px"></textarea>
                                    <label for="remarks">Remarks / Notes</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submit_button">Create Purchase Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View PR Modal -->
    <div class="modal fade" id="viewPRModal" tabindex="-1" aria-labelledby="viewPRModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPRModalLabel">Document Details</h5>
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

    <!-- Convert to PO Modal -->
    <div class="modal fade" id="convertToPOModal" tabindex="-1" aria-labelledby="convertToPOModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="convertToPOModalLabel">Convert to Purchase Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="convert_to_po" value="1">
                    <input type="hidden" name="pr_id" id="convert_pr_id">
                    <div class="modal-body">
                        <p>Are you sure you want to convert this Purchase Request to a Purchase Order?</p>
                        <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
                        <p>Once converted, the PR status will change to "processing" and a new PO will be created.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Convert to PO</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete PR Modal -->
    <div class="modal fade" id="deletePRModal" tabindex="-1" aria-labelledby="deletePRModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePRModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="delete_pr" value="1">
                    <input type="hidden" name="pr_id" id="delete_pr_id">
                    <div class="modal-body">
                        <p>Are you sure you want to delete this document?</p>
                        <p><strong id="delete_pr_number"></strong></p>
                        <p class="text-danger">Warning: This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
    <script>
        // PHP parts data for JavaScript
        const allPartsData = <?php echo json_encode($all_parts); ?>;
        const issuePartsData = <?php echo json_encode($issue_parts); ?>;
        const issueMaterialsData = <?php echo json_encode($issue_materials); ?>;
        const employeesData = <?php echo json_encode($formatted_employees); ?>;
        const mechanicsData = <?php echo json_encode($formatted_mechanics); ?>;
        const driversData = <?php echo json_encode($formatted_drivers); ?>;
        
        // Track item count globally
        let itemCount = 1;
        
        // Store selected parts with their data
        const selectedPartsData = {};

        // Format number with commas
        function formatNumberWithCommas(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // Parse formatted number back to float
        function parseFormattedNumber(str) {
            return parseFloat(str.replace(/[^0-9.-]+/g, ""));
        }

        // Function to generate document number via AJAX
        async function generateDocumentNumber(requestType) {
            try {
                const response = await fetch('action/generate_document_number.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `request_type=${requestType}`
                });
                
                const data = await response.json();
                if (data.success) {
                    return data.document_number;
                } else {
                    console.error('Error generating document number:', data.error);
                    return null;
                }
            } catch (error) {
                console.error('Error generating document number:', error);
                return null;
            }
        }

        // Function to get current parts data based on request type
        function getCurrentPartsData() {
            const requestType = document.getElementById('request_type').value;
            switch(requestType) {
                case 'issue':
                    return issuePartsData;
                case 'issue_materials':
                    return issueMaterialsData;
                case 'stock':
                default:
                    return allPartsData;
            }
        }

        // Function to create searchable dropdown for an item row - REMOVED selected-info creation
        function createSearchableDropdown(rowIndex, initialValue = null) {
            const container = document.getElementById(`searchable-container-${rowIndex}`);
            if (!container) return;
            
            const searchInput = document.getElementById(`search-input-${rowIndex}`);
            const hiddenInput = document.getElementById(`part-id-${rowIndex}`);
            const dropdownList = document.getElementById(`dropdown-list-${rowIndex}`);
            
            const partsData = getCurrentPartsData();
            
            // Function to render dropdown items based on search term
            function renderDropdown(searchTerm = '') {
                const searchLower = searchTerm.toLowerCase();
                const filteredParts = partsData.filter(part => 
                    part.part_number.toLowerCase().includes(searchLower) ||
                    part.part_name.toLowerCase().includes(searchLower) ||
                    part.category_name.toLowerCase().includes(searchLower)
                );
                
                if (filteredParts.length === 0) {
                    dropdownList.innerHTML = '<div class="searchable-dropdown-no-results">No items found</div>';
                    return;
                }
                
                let html = '';
                filteredParts.forEach(part => {
                    html += `
                        <div class="searchable-dropdown-item" data-id="${part.id}" 
                            data-price="${part.current_price}"
                            data-category="${part.category_name}"
                            data-part-number="${part.part_number}"
                            data-part-name="${part.part_name}">
                            <div class="item-code">${part.part_number}</div>
                            <div class="item-name">${part.part_name}</div>
                            <div class="item-category">${part.category_name}</div>
                        </div>
                    `;
                });
                dropdownList.innerHTML = html;
                
                // Add click handlers to dropdown items
                document.querySelectorAll(`#dropdown-list-${rowIndex} .searchable-dropdown-item`).forEach(item => {
                    item.addEventListener('click', function() {
                        const id = this.dataset.id;
                        const partNumber = this.dataset.partNumber;
                        const partName = this.dataset.partName;
                        const category = this.dataset.category;
                        const price = this.dataset.price;
                        
                        // Set hidden input value
                        hiddenInput.value = id;
                        
                        // Update search input with selected item
                        searchInput.value = `${partNumber} - ${partName} (${category})`;
                        
                        // Hide dropdown
                        dropdownList.classList.remove('show');
                        
                        // Store selected part data
                        selectedPartsData[`row-${rowIndex}`] = {
                            id: id,
                            partNumber: partNumber,
                            partName: partName,
                            category: category,
                            price: price
                        };
                        
                        // REMOVED THE AUTO-FILL OF UNIT COST
                        // Unit cost should be entered manually by the user
                        
                        // Trigger calculation
                        calculateTotal({}, rowIndex);
                    });
                });
            }
            
            // Handle input events for searching
            searchInput.addEventListener('input', function() {
                if (this.value.trim() === '') {
                    // Clear selection if input is empty
                    hiddenInput.value = '';
                    delete selectedPartsData[`row-${rowIndex}`];
                }
                renderDropdown(this.value);
                dropdownList.classList.add('show');
            });
            
            // Show dropdown on focus
            searchInput.addEventListener('focus', function() {
                renderDropdown(this.value);
                dropdownList.classList.add('show');
            });
            
            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!container.contains(e.target)) {
                    dropdownList.classList.remove('show');
                }
            });
            
            // Handle keyboard navigation
            searchInput.addEventListener('keydown', function(e) {
                const items = dropdownList.querySelectorAll('.searchable-dropdown-item');
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

        // Request type selection function
        async function selectRequestType(type) {
            // Update hidden input
            document.getElementById('request_type').value = type;
            
            // Update active class on options
            document.querySelectorAll('.request-type-option').forEach(option => {
                option.classList.remove('active');
            });
            document.querySelector(`.request-type-option[data-type="${type}"]`).classList.add('active');
            
            // Generate new document number based on type
            const newNumber = await generateDocumentNumber(type);
            if (newNumber) {
                document.getElementById('document_number').value = newNumber;
            }
            
            // Update document label and submit button text
            const documentNumberLabel = document.getElementById('document_number_label');
            const submitButton = document.getElementById('submit_button');
            const preparedByLabel = document.getElementById('prepared_by_label');
            
            if (type === 'issue_materials') {
                documentNumberLabel.textContent = 'Withdrawal Slip #';
                submitButton.textContent = 'Create Withdrawal Slip';
                preparedByLabel.textContent = 'Prepared By';
            } else if (type === 'issue') {
                documentNumberLabel.textContent = 'IPPR Number';
                submitButton.textContent = 'Create Issue Purchase Request';
                preparedByLabel.textContent = 'Requested By';
            } else {
                documentNumberLabel.textContent = 'PR Number';
                submitButton.textContent = 'Create Purchase Request';
                preparedByLabel.textContent = 'Requested By';
            }
            
            // Show/hide fields based on request type
            const issueFields = document.getElementById('issue_fields');
            const materialsFields = document.getElementById('materials_fields');
            const supplierField = document.querySelector('.supplier-field');
            const costFields = document.querySelectorAll('.cost-fields');
            const grandTotal = document.getElementById('grand-total');
            
            // Get the required fields
            const technicianField = document.getElementById('technician');
            const driverField = document.getElementById('driver_id');
            const issuePurposeField = document.getElementById('issue_purpose');
            const employeeField = document.getElementById('employee_id');
            const materialsPurposeField = document.getElementById('materials_purpose');
            
            if (type === 'issue') {
                // Show issue fields, hide others
                issueFields.style.display = 'block';
                materialsFields.style.display = 'none';
                
                // Hide supplier field
                supplierField.style.display = 'none';
                
                // Hide all cost fields
                costFields.forEach(field => {
                    field.classList.add('hidden');
                });
                
                // Hide grand total
                grandTotal.style.display = 'none';
                
                // Make issue fields required
                technicianField.setAttribute('required', 'required');
                driverField.setAttribute('required', 'required');
                issuePurposeField.setAttribute('required', 'required');
                
                // Remove required from materials fields
                employeeField.removeAttribute('required');
                materialsPurposeField.removeAttribute('required');
                
                // Remove required from unit cost fields since they're hidden
                document.querySelectorAll('input[name*="unit_cost"]').forEach(field => {
                    field.removeAttribute('required');
                });
                
                // Update item row column widths for issue type
                updateItemRowLayout(type);
                
                // Initialize the vehicle/equipment disabling for issue type
                initializeVehicleEquipmentDisabling();
                
                // Refresh all searchable dropdowns with new data
                refreshAllSearchableDropdowns();
            } else if (type === 'issue_materials') {
                // Show materials fields, hide others
                materialsFields.style.display = 'block';
                issueFields.style.display = 'none';
                
                // Hide supplier field
                supplierField.style.display = 'none';
                
                // Hide all cost fields
                costFields.forEach(field => {
                    field.classList.add('hidden');
                });
                
                // Hide grand total
                grandTotal.style.display = 'none';
                
                // Make materials fields required
                employeeField.setAttribute('required', 'required');
                materialsPurposeField.setAttribute('required', 'required');
                
                // Remove required from issue fields
                technicianField.removeAttribute('required');
                driverField.removeAttribute('required');
                issuePurposeField.removeAttribute('required');
                
                // Remove required from unit cost fields since they're hidden
                document.querySelectorAll('input[name*="unit_cost"]').forEach(field => {
                    field.removeAttribute('required');
                });
                
                // Update item row column widths for materials type
                updateItemRowLayout(type);
                
                // Refresh all searchable dropdowns with new data
                refreshAllSearchableDropdowns();
            } else {
                // Hide all issue fields
                issueFields.style.display = 'none';
                materialsFields.style.display = 'none';
                
                // Show supplier field
                supplierField.style.display = 'block';
                
                // Show all cost fields
                costFields.forEach(field => {
                    field.classList.remove('hidden');
                });
                
                // Show grand total
                grandTotal.style.display = 'block';
                
                // Remove required from all issue fields
                technicianField.removeAttribute('required');
                driverField.removeAttribute('required');
                issuePurposeField.removeAttribute('required');
                employeeField.removeAttribute('required');
                materialsPurposeField.removeAttribute('required');
                
                // Add required back to unit cost fields
                document.querySelectorAll('input[name*="unit_cost"]').forEach(field => {
                    field.setAttribute('required', 'required');
                });
                
                // Update item row column widths for stock type
                updateItemRowLayout(type);
                
                // Re-enable both vehicle and equipment dropdowns
                const vehicleSelect = document.getElementById('pr_vehicle_id');
                const equipmentSelect = document.getElementById('pr_equipment_id');
                if (vehicleSelect) vehicleSelect.disabled = false;
                if (equipmentSelect) equipmentSelect.disabled = false;
                
                // Refresh all searchable dropdowns with new data
                refreshAllSearchableDropdowns();
            }
        }

        // Function to refresh all searchable dropdowns when request type changes
        function refreshAllSearchableDropdowns() {
            const items = document.querySelectorAll('.item-row');
            items.forEach((item, index) => {
                // Clear existing selection
                const hiddenInput = document.getElementById(`part-id-${index}`);
                const searchInput = document.getElementById(`search-input-${index}`);
                
                if (hiddenInput) hiddenInput.value = '';
                if (searchInput) searchInput.value = '';
                
                // Recreate the dropdown with new data
                createSearchableDropdown(index);
            });
            
            // Clear selected parts data
            Object.keys(selectedPartsData).forEach(key => delete selectedPartsData[key]);
        }

        // Function to update item row layout based on request type
        function updateItemRowLayout(type) {
            const itemRows = document.querySelectorAll('.item-row');
            
            itemRows.forEach(row => {
                const columns = row.querySelector('.row').children;
                
                if (type === 'issue' || type === 'issue_materials') {
                    // For issue types: Part selection (col-8) and Quantity (col-4)
                    columns[0].className = 'col-md-8'; // Part selection
                    columns[1].className = 'col-md-4'; // Quantity
                    // Hide unit cost and item total columns
                    if (columns[2]) {
                        columns[2].style.display = 'none';
                        columns[2].className = 'col-md-2 cost-fields hidden';
                    }
                    if (columns[3]) {
                        columns[3].style.display = 'none';
                        columns[3].className = 'col-md-3 cost-fields hidden';
                    }
                } else {
                    // For stock type: Original layout
                    columns[0].className = 'col-md-5'; // Part selection
                    columns[1].className = 'col-md-2'; // Quantity
                    // Show unit cost and item total columns
                    if (columns[2]) {
                        columns[2].style.display = 'block';
                        columns[2].className = 'col-md-2 cost-fields';
                    }
                    if (columns[3]) {
                        columns[3].style.display = 'block';
                        columns[3].className = 'col-md-3 cost-fields';
                    }
                }
            });
        }

        // Function to initialize vehicle/equipment disabling logic
        function initializeVehicleEquipmentDisabling() {
            const vehicleSelect = document.getElementById('pr_vehicle_id');
            const equipmentSelect = document.getElementById('pr_equipment_id');
            
            if (vehicleSelect && equipmentSelect) {
                // Add event listeners for both dropdowns
                vehicleSelect.addEventListener('change', function() {
                    if (this.value) {
                        equipmentSelect.disabled = true;
                        equipmentSelect.value = '';
                    } else {
                        equipmentSelect.disabled = false;
                    }
                });
                
                equipmentSelect.addEventListener('change', function() {
                    if (this.value) {
                        vehicleSelect.disabled = true;
                        vehicleSelect.value = '';
                    } else {
                        vehicleSelect.disabled = false;
                    }
                });
                
                // Initialize state based on current values
                if (vehicleSelect.value) {
                    equipmentSelect.disabled = true;
                } else if (equipmentSelect.value) {
                    vehicleSelect.disabled = true;
                }
            }
        }

        // Initialize DataTables and Event Listeners
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
                    }
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
            selectRequestType('stock');

            // Initialize vehicle/equipment disabling
            initializeVehicleEquipmentDisabling();

            // View Details button handler (using event delegation)
            document.body.addEventListener('click', function(e) {
                const viewBtn = e.target.closest('.view-pr-btn');
                if (viewBtn) {
                    e.preventDefault();
                    const prId = viewBtn.getAttribute('data-id');
                    
                    fetch('action/get_spare_parts_pr_details.php?id=' + prId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const pr = data.pr;
                                const items = data.items;
                                const po_number = data.po_number;
                                
                                let itemsHtml = '';
                                let totalCost = 0;
                                
                                // Build items table
                                if (items.length > 0) {
                                    itemsHtml = `
                                        <div class="table-responsive mt-3">
                                            <table class="table table-bordered table-sm">
                                                <thead class="table-light">
                                                    <tr>
                                    `;
                                    
                                    // Check if this is a Stock Purchase Request
                                    if (pr.request_type === 'stock') {
                                        // For Stock Purchase Request: Remove Unit Cost and Total Cost columns
                                        itemsHtml += `
                                                    <th>Part Name</th>
                                                    <th>Category</th>
                                                    <th class="text-center">Quantity</th>
                                        `;
                                    } else {
                                        // For Issue Parts and Withdrawal Slip: Remove Unit Cost and Total Cost columns
                                        itemsHtml += `
                                                    <th>Part Name</th>
                                                    <th>Category</th>
                                                    <th class="text-center">Quantity</th>
                                        `;
                                    }
                                    
                                    itemsHtml += `
                                                </tr>
                                            </thead>
                                            <tbody>
                                    `;
                                    
                                    items.forEach(item => {
                                        // Combine Part Name with Part Number in parentheses
                                        const partNameWithNumber = `${item.part_name} (${item.part_number})`;
                                        
                                        if (pr.request_type === 'stock') {
                                            // Stock Purchase Request: Only show part name, category, and quantity
                                            itemsHtml += `
                                                <tr>
                                                    <td>${partNameWithNumber}</td>
                                                    <td>${item.category_name}</td>
                                                    <td class="text-center">${item.quantity}</td>
                                                </tr>
                                            `;
                                        } else {
                                            // Issue Parts and Withdrawal Slip: Only show part name, category, and quantity
                                            itemsHtml += `
                                                <tr>
                                                    <td>${partNameWithNumber}</td>
                                                    <td>${item.category_name}</td>
                                                    <td class="text-center">${item.quantity}</td>
                                                </tr>
                                            `;
                                        }
                                    });
                                    
                                    // No footer needed for any type
                                    itemsHtml += `
                                            </tbody>
                                        </table>
                                    </div>
                                    `;
                                } else {
                                    itemsHtml = '<div class="alert alert-info">No items found for this document.</div>';
                                }
                                
                                // Build the complete details HTML with request type
                                let requestTypeBadge;
                                let documentTypeText;
                                let preparedByLabel;

                                // Format document number with PO if available - with proper colors
                                let documentNumberDisplay;
                                if (pr.request_type === 'stock' && po_number) {
                                    documentNumberDisplay = `
                                        <span class="spr-number">${pr.pr_number}</span>, 
                                        <span class="po-number">${po_number}</span>
                                    `;
                                } else if (pr.request_type === 'issue') {
                                    // For Issue Parts Purchase Request, show Job Order Number if available
                                    if (data.job_order_number) {
                                        documentNumberDisplay = `<span class="jo-number">${data.job_order_number}</span>`;
                                    } else {
                                        documentNumberDisplay = `<span class="text-muted-italic">Not yet created</span>`;
                                    }
                                } else if (pr.request_type === 'issue_materials') {
                                    // For Issue Materials Withdrawal Slip, show Withdrawal Slip Number if available
                                    if (data.withdrawal_slip_number) {
                                        documentNumberDisplay = `<span class="ws-number">${data.withdrawal_slip_number}</span>`;
                                    } else {
                                        documentNumberDisplay = `<span class="text-muted-italic">Not yet created</span>`;
                                    }
                                } else {
                                    documentNumberDisplay = `<span class="${pr.request_type === 'issue_materials' ? 'ws-number' : 'spr-number'}">${pr.pr_number}</span>`;
                                }
                                
                                switch(pr.request_type) {
                                    case 'stock':
                                        requestTypeBadge = '<span class="badge bg-primary">Stock Purchase Request</span>';
                                        documentTypeText = 'Purchase Request';
                                        preparedByLabel = 'Requested By';
                                        break;
                                    case 'issue':
                                        requestTypeBadge = '<span class="badge bg-success">Issue Parts Purchase Request</span>';
                                        documentTypeText = 'Issue Parts Purchase Request';
                                        preparedByLabel = 'Requested By';
                                        break;
                                    case 'issue_materials':
                                        requestTypeBadge = '<span class="badge bg-warning text-dark">Issue Materials Withdrawal Slip</span>';
                                        documentTypeText = 'Withdrawal Slip';
                                        preparedByLabel = 'Prepared By';
                                        break;
                                    default:
                                        requestTypeBadge = `<span class="badge bg-secondary">${pr.request_type || 'Stock'} Purchase Request</span>`;
                                        documentTypeText = 'Purchase Request';
                                        preparedByLabel = 'Requested By';
                                }
                                
                                // Format technician name if available
                                let technicianName = 'N/A';
                                if (pr.tech_firstname) {
                                    technicianName = pr.tech_firstname;
                                    if (pr.tech_middlename) {
                                        technicianName += ' ' + pr.tech_middlename.charAt(0) + '.';
                                    }
                                    technicianName += ' ' + pr.tech_lastname;
                                    if (pr.tech_suffix) {
                                        technicianName += ' ' + pr.tech_suffix;
                                    }
                                }
                                
                                // Format driver name if available
                                let driverName = 'N/A';
                                if (pr.driver_firstname) {
                                    driverName = pr.driver_firstname;
                                    if (pr.driver_middlename) {
                                        driverName += ' ' + pr.driver_middlename.charAt(0) + '.';
                                    }
                                    driverName += ' ' + pr.driver_lastname;
                                    if (pr.driver_suffix) {
                                        driverName += ' ' + pr.driver_suffix;
                                    }
                                }
                                
                                // Format employee name if available
                                let employeeName = 'N/A';
                                if (pr.emp_firstname) {
                                    employeeName = pr.emp_firstname;
                                    if (pr.emp_middlename) {
                                        employeeName += ' ' + pr.emp_middlename.charAt(0) + '.';
                                    }
                                    employeeName += ' ' + pr.emp_lastname;
                                    if (pr.emp_suffix) {
                                        employeeName += ' ' + pr.emp_suffix;
                                    }
                                }
                                
                                // Build issue details if available
                                let issueDetailsHtml = '';
                                if (pr.request_type === 'issue') {
                                    let vehicleEquipment = '';
                                    if (pr.vehicle_id && pr.vehicle_name) {
                                        vehicleEquipment = `Vehicle: ${pr.vehicle_name} (${pr.plate_number})`;
                                    } else if (pr.equipment_id && pr.equipment_name) {
                                        vehicleEquipment = `Equipment: ${pr.equipment_name}`;
                                    }
                                    
                                    issueDetailsHtml = `
                                        <div class="card mb-4">
                                            <div class="card-body">
                                                <h6 class="card-subtitle mb-3 text-muted">Issue Details</h6>
                                                <div class="mb-2">
                                                    <strong>Vehicle/Equipment:</strong> ${vehicleEquipment || 'N/A'}
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Technician:</strong> ${technicianName}
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Driver:</strong> ${driverName}
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Purpose:</strong> ${pr.purpose || 'N/A'}
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                } else if (pr.request_type === 'issue_materials' && pr.employee_id) {
                                    issueDetailsHtml = `
                                        <div class="card mb-4">
                                            <div class="card-body">
                                                <h6 class="card-subtitle mb-3 text-muted">Issue Details</h6>
                                                <div class="mb-2">
                                                    <strong>Employee:</strong> ${employeeName}
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Position:</strong> ${pr.emp_position || 'N/A'}
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Purpose:</strong> ${pr.purpose || 'N/A'}
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                }
                                
                                // Only show supplier information for stock purchase requests
                                const supplierHtml = (pr.request_type === 'stock') ? 
                                    `<div class="mb-2"><strong>Supplier:</strong> ${pr.supplier_name || 'Not Specified'}</div>` : 
                                    '';
                                
                                const detailsHtml = `
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-subtitle mb-3 text-muted">${documentTypeText} Information</h6>
                                                    <div class="mb-2">
                                                        <strong>Document #:</strong> 
                                                        ${documentNumberDisplay}
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Document Type:</strong> ${requestTypeBadge}
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Status:</strong> <span class="${getStatusBadgeClass(pr.status)}">${pr.status.charAt(0).toUpperCase() + pr.status.slice(1)}</span>
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>${preparedByLabel}:</strong> ${pr.firstname} ${pr.middlename ? pr.middlename.charAt(0) + '.' : ''} ${pr.lastname} ${pr.suffix || ''}
                                                    </div>
                                                    ${supplierHtml}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-subtitle mb-3 text-muted">Dates & Timeline</h6>
                                                    <div class="mb-2">
                                                        <strong>Request Date:</strong> ${formatDate(pr.request_date)}
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Expected Delivery:</strong> ${pr.expected_delivery_date ? formatDate(pr.expected_delivery_date) : 'Not Set'}
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Created At:</strong> ${formatDateTime(pr.created_at)}
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Last Updated:</strong> ${pr.updated_at ? formatDateTime(pr.updated_at) : 'N/A'}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    ${issueDetailsHtml}
                                    
                                    ${pr.remarks ? `
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted">Remarks / Notes</h6>
                                            <p class="mb-0">${pr.remarks}</p>
                                        </div>
                                    </div>
                                    ` : ''}
                                    
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-3 text-muted">Requested Items</h6>
                                            ${itemsHtml}
                                        </div>
                                    </div>
                                `;
                                
                                document.getElementById('prDetails').innerHTML = detailsHtml;
                                document.getElementById('viewPRModalLabel').textContent = documentTypeText + ' Details';
                                const viewModal = new bootstrap.Modal(document.getElementById('viewPRModal'));
                                viewModal.show();
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'Failed to load document details.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error!',
                                text: 'Failed to load document details.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });

            // Delete button handler (using event delegation)
            document.body.addEventListener('click', function(e) {
                const deleteBtn = e.target.closest('.delete-pr-btn');
                if (deleteBtn) {
                    e.preventDefault();
                    const prId = deleteBtn.getAttribute('data-id');
                    const prNumber = deleteBtn.getAttribute('data-pr-number');
                    
                    document.getElementById('delete_pr_id').value = prId;
                    document.getElementById('delete_pr_number').textContent = prNumber;
                    
                    const deleteModal = new bootstrap.Modal(document.getElementById('deletePRModal'));
                    deleteModal.show();
                }
            });

            // Item management - Add Item
            document.getElementById('addItem').addEventListener('click', function() {
                const container = document.getElementById('itemsContainer');
                const newItem = document.createElement('div');
                newItem.className = 'item-row mb-3 p-3 border rounded';
                newItem.id = 'item-row-' + itemCount;
                
                // Get current request type
                const requestType = document.getElementById('request_type').value;
                
                // Determine column classes based on request type
                const partColClass = (requestType === 'issue' || requestType === 'issue_materials') ? 'col-md-8' : 'col-md-5';
                const quantityColClass = (requestType === 'issue' || requestType === 'issue_materials') ? 'col-md-4' : 'col-md-2';
                const unitCostDisplay = (requestType === 'issue' || requestType === 'issue_materials') ? 'none' : 'block';
                const unitCostRequired = (requestType === 'issue' || requestType === 'issue_materials') ? '' : 'required';
                
                newItem.innerHTML = `
                    <div class="row">
                        <div class="${partColClass}">
                            <div class="searchable-dropdown-container" id="searchable-container-${itemCount}">
                                <input type="text" 
                                    class="searchable-dropdown-input" 
                                    id="search-input-${itemCount}" 
                                    placeholder="Type to search items..."
                                    autocomplete="off"
                                    required>
                                <input type="hidden" name="items[${itemCount}][part_id]" id="part-id-${itemCount}" required>
                                <div class="searchable-dropdown-list" id="dropdown-list-${itemCount}"></div>
                            </div>
                            <!-- Removed selected-item-info div -->
                        </div>
                        <div class="${quantityColClass}">
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" name="items[${itemCount}][quantity]" min="1" required 
                                    onchange="calculateTotal(this, ${itemCount})">
                                <label>Quantity <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-2 cost-fields" style="display: ${unitCostDisplay}">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" name="items[${itemCount}][unit_cost]" 
                                    id="unit-cost-${itemCount}" onchange="calculateTotal(this, ${itemCount})" 
                                    placeholder="Enter unit cost" value="0" ${unitCostRequired}>
                                <label>Unit Cost (₱) <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-3 cost-fields" style="display: ${unitCostDisplay}">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="item-total-${itemCount}" value="₱0.00" readonly>
                                <label>Item Total</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-10"></div>
                        <div class="col-md-2 d-flex align-items-center">
                            <button type="button" class="btn btn-danger btn-sm remove-item">
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>
                    </div>
                `;
                
                container.appendChild(newItem);
                
                // Initialize searchable dropdown for this new item
                createSearchableDropdown(itemCount);
                
                itemCount++;

                // Show remove buttons for all items if there's more than one
                const allItems = document.querySelectorAll('.item-row');
                const removeButtons = document.querySelectorAll('.remove-item');
                
                if (allItems.length > 1) {
                    removeButtons.forEach(btn => {
                        btn.style.display = 'block';
                    });
                } else {
                    removeButtons.forEach(btn => {
                        btn.style.display = 'none';
                    });
                }
                
                // Re-apply request type styling
                selectRequestType(requestType);
            });

            // Remove item - Fixed with proper DOM node reference
            document.addEventListener('click', function(e) {
                const removeBtn = e.target.closest('.remove-item');
                if (removeBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const itemRow = removeBtn.closest('.item-row');
                    if (!itemRow) return;
                    
                    // Check if this is the first item and if there are more than one items
                    const allItems = document.querySelectorAll('.item-row');
                    if (allItems.length > 1) {
                        // Get the parent container
                        const container = document.getElementById('itemsContainer');
                        
                        // Remove the item row
                        if (container && itemRow && container.contains(itemRow)) {
                            container.removeChild(itemRow);
                            
                            // Remove from selectedPartsData
                            const rowIndex = itemRow.id.split('-')[2];
                            delete selectedPartsData[`row-${rowIndex}`];
                            
                            // Update all item indices
                            updateItemIndices();
                            
                            // Recalculate grand total
                            calculateGrandTotal();
                            
                            // Update remove buttons visibility
                            const remainingItems = document.querySelectorAll('.item-row');
                            const removeButtons = document.querySelectorAll('.remove-item');
                            
                            if (remainingItems.length === 1) {
                                removeButtons.forEach(btn => {
                                    btn.style.display = 'none';
                                });
                            } else {
                                removeButtons.forEach(btn => {
                                    btn.style.display = 'block';
                                });
                            }
                        }
                    } else {
                        // If it's the last item, show SweetAlert warning
                        Swal.fire({
                            title: 'Cannot Remove',
                            text: 'You must have at least one item in the request.',
                            icon: 'warning',
                            confirmButtonText: 'OK',
                            timer: 2000,
                            showConfirmButton: true
                        });
                    }
                }
            });

            // Function to update item indices after removal
            function updateItemIndices() {
                const items = document.querySelectorAll('.item-row');
                items.forEach((item, index) => {
                    // Update the ID
                    item.id = 'item-row-' + index;
                    
                    // Update searchable container ID
                    const container = item.querySelector('.searchable-dropdown-container');
                    if (container) {
                        container.id = `searchable-container-${index}`;
                        
                        const searchInput = container.querySelector('.searchable-dropdown-input');
                        if (searchInput) searchInput.id = `search-input-${index}`;
                        
                        const hiddenInput = container.querySelector('input[type="hidden"]');
                        if (hiddenInput) hiddenInput.id = `part-id-${index}`;
                        
                        const dropdownList = container.querySelector('.searchable-dropdown-list');
                        if (dropdownList) dropdownList.id = `dropdown-list-${index}`;
                    }
                    
                    // Update unit cost
                    const unitCostInput = item.querySelector('input[name*="unit_cost"]');
                    if (unitCostInput) {
                        unitCostInput.name = `items[${index}][unit_cost]`;
                        unitCostInput.id = `unit-cost-${index}`;
                        unitCostInput.setAttribute('onchange', `calculateTotal(this, ${index})`);
                    }
                    
                    // Update quantity input
                    const quantityInput = item.querySelector('input[name*="quantity"]');
                    if (quantityInput) {
                        quantityInput.name = `items[${index}][quantity]`;
                        quantityInput.setAttribute('onchange', `calculateTotal(this, ${index})`);
                    }
                    
                    // Update item total
                    const itemTotal = item.querySelector('input[id*="item-total"]');
                    if (itemTotal) {
                        itemTotal.id = `item-total-${index}`;
                    }
                });
                
                // Re-initialize searchable dropdowns with new indices
                items.forEach((item, index) => {
                    createSearchableDropdown(index);
                });
            }

            // Helper function to format date
            function formatDate(dateString) {
                if (!dateString || dateString === '0000-00-00') return 'Not Set';
                const date = new Date(dateString);
                return (date.getMonth() + 1).toString().padStart(2, '0') + '-' + 
                    date.getDate().toString().padStart(2, '0') + '-' + 
                    date.getFullYear();
            }

            // Helper function to format datetime
            function formatDateTime(dateTimeString) {
                if (!dateTimeString) return 'N/A';
                const date = new Date(dateTimeString);
                return (date.getMonth() + 1).toString().padStart(2, '0') + '-' + 
                    date.getDate().toString().padStart(2, '0') + '-' + 
                    date.getFullYear() + ' ' + 
                    date.getHours().toString().padStart(2, '0') + ':' + 
                    date.getMinutes().toString().padStart(2, '0');
            }

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

            // Format unit cost input on blur
            document.addEventListener('blur', function(e) {
                if (e.target.name && e.target.name.includes('unit_cost')) {
                    const input = e.target;
                    const value = parseFormattedNumber(input.value);
                    if (!isNaN(value)) {
                        input.value = formatNumberWithCommas(value.toFixed(2));
                        // Also trigger calculation
                        const index = input.id ? input.id.split('-')[2] : 0;
                        if (index !== undefined) {
                            calculateTotal(input, index);
                        }
                    }
                }
            }, true);
        });

        // Calculate item total
        function calculateTotal(inputElement, index) {
            const requestType = document.getElementById('request_type').value;
            
            // Only calculate totals for stock type
            if (requestType === 'stock') {
                const row = document.getElementById('item-row-' + index);
                if (!row) return;
                
                const quantity = parseFloat(row.querySelector('input[name*="quantity"]').value) || 0;
                const unitCostInput = row.querySelector('input[name*="unit_cost"]');
                if (!unitCostInput) return;
                
                const unitCost = parseFormattedNumber(unitCostInput.value) || 0;
                const total = quantity * unitCost;
                
                // Update item total display
                const itemTotalInput = document.getElementById('item-total-' + index);
                if (itemTotalInput) {
                    itemTotalInput.value = '₱' + formatNumberWithCommas(total.toFixed(2));
                }
                
                // Calculate grand total
                calculateGrandTotal();
            }
        }

        // Calculate grand total
        function calculateGrandTotal() {
            const requestType = document.getElementById('request_type').value;
            
            // Only calculate grand total for stock type
            if (requestType === 'stock') {
                let grandTotal = 0;
                document.querySelectorAll('.item-row').forEach((row, index) => {
                    const quantity = parseFloat(row.querySelector('input[name*="quantity"]').value) || 0;
                    const unitCostInput = row.querySelector('input[name*="unit_cost"]');
                    if (unitCostInput) {
                        const unitCost = parseFormattedNumber(unitCostInput.value) || 0;
                        grandTotal += quantity * unitCost;
                    }
                });
                
                const grandTotalElement = document.getElementById('grand-total');
                if (grandTotalElement) {
                    grandTotalElement.textContent = 'Grand Total: ₱' + formatNumberWithCommas(grandTotal.toFixed(2));
                }
            }
        }

        // Reset when modal is closed
        document.getElementById('createPRModal').addEventListener('hidden.bs.modal', function () {
            // Clear selected parts data
            Object.keys(selectedPartsData).forEach(key => delete selectedPartsData[key]);
            
            // Reset form to default
            selectRequestType('stock');
            
            // Clear issue fields
            document.getElementById('pr_vehicle_id').value = '';
            document.getElementById('pr_equipment_id').value = '';
            document.getElementById('technician').value = '';
            document.getElementById('driver_id').value = '';
            document.getElementById('issue_purpose').value = '';
            document.getElementById('employee_id').value = '';
            document.getElementById('materials_purpose').value = '';
            document.getElementById('supplier_id').value = '';
            
            // Reset item rows to default layout
            updateItemRowLayout('stock');
            
            // Reset all item rows except the first one
            const itemsContainer = document.getElementById('itemsContainer');
            while (itemsContainer.children.length > 1) {
                if (itemsContainer.lastChild) {
                    itemsContainer.removeChild(itemsContainer.lastChild);
                }
            }
            
            // Reset the first item row
            const firstRow = itemsContainer.querySelector('.item-row');
            if (firstRow) {
                const searchInput = firstRow.querySelector('.searchable-dropdown-input');
                if (searchInput) searchInput.value = '';
                
                const hiddenInput = firstRow.querySelector('input[type="hidden"]');
                if (hiddenInput) hiddenInput.value = '';
                
                const quantityInput = firstRow.querySelector('input[name*="quantity"]');
                if (quantityInput) quantityInput.value = '';
                
                const unitCostInput = firstRow.querySelector('input[name*="unit_cost"]');
                if (unitCostInput) unitCostInput.value = '0';
                
                const itemTotalInput = firstRow.querySelector('input[id*="item-total"]');
                if (itemTotalInput) itemTotalInput.value = '₱0.00';
            }
            
            // Reset item count
            itemCount = 1;
            
            // Reset grand total
            document.getElementById('grand-total').textContent = 'Grand Total: ₱0.00';
            
            // Re-initialize first searchable dropdown
            createSearchableDropdown(0);
        });

        // Logout function
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
</body>
</html>