<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'config/db_config.php';
require_once __DIR__ . '/includes/page_data.php';

// All of this page's actions live in one file: creating a purchase request,
// updating one, changing its status, deleting it, and the document-number lookup
// its JavaScript makes. The forms post back to this page, so it is pulled in
// before anything is read or rendered.
// Check for session-based SweetAlert data. This belongs in the page, not only in the
// actions file: a handler that stores a message and redirects is answered by a fresh GET,
// where the actions file does not run - so the message has to be picked up here.
if (!isset($swal_data) || !is_array($swal_data) || $swal_data === []) {
    $swal_data = array();
}
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

if (!defined('OCP_PURCHASE_REQUEST_SPARE_PARTS_ACTIONS_RAN')) {
    require __DIR__ . '/actions/purchase_request_spare_parts-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. The same file answers
// the page's JavaScript when it asks for one request by ?id=, and it carries the
// shared helpers the markup uses below.
$ocp_endpoint = require __DIR__ . '/api/purchase_request_spare_parts-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);
// Function to get status badge class
function getStatusBadge($status) {
    switch ($status) {
        case 'approved':
            return 'badge badge-success';
        case 'rejected':
            return 'badge badge-danger';
        case 'pending':
            return 'badge badge-warning';
        case 'processing':
            return 'badge badge-info';
        case 'completed':
            return 'badge badge-primary';
        default:
            return 'badge badge-neutral';
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
            return 'badge badge-primary';
        case 'issue':
            return 'badge badge-success';
        case 'issue_materials':
            return 'badge badge-warning';
        default:
            return 'badge badge-neutral';
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
    <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link href="assets/css/app.css" rel="stylesheet" />
        <link href="assets/css/app.build.css" rel="stylesheet" />
</head>
<body class="sb-nav-fixed">
    <?php include 'includes/top_bar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'includes/side_menu.php'; ?>
        <div id="layoutSidenav_content" class="sb-content">
            <main>
                <div class="w-full px-6">
                    <div class="mb-6">
                        <h1 class="page-title">Motorpool PR</h1>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="spare_parts_inventory.php">Motorpool Inventory</a></li>
                            <li class="breadcrumb-item active">Motorpool PR</li>
                        </ol>
                    </div>

                    <!-- Status Summary Cards -->
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3 mb-6">
                        <div class="min-w-0">
                            <div class="card bg-brand-600! text-white">
                                <div class="card-body flex items-center gap-4">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/20 text-lg">
                                        <i class="fas fa-clock" aria-hidden="true"></i>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-2xl font-bold leading-tight"><?php 
                                            $pending_count = 0;
                                            foreach ($status_counts as $status) {
                                                if ($status['status'] == 'pending') {
                                                    $pending_count = $status['count'];
                                                    break;
                                                }
                                            }
                                            echo $pending_count;
                                        ?></span>
                                        <span class="block text-sm">Pending</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <div class="card bg-info-600! text-white">
                                <div class="card-body flex items-center gap-4">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/20 text-lg">
                                        <i class="fas fa-spinner" aria-hidden="true"></i>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-2xl font-bold leading-tight"><?php 
                                            $processing_count = 0;
                                            foreach ($status_counts as $status) {
                                                if ($status['status'] == 'processing') {
                                                    $processing_count = $status['count'];
                                                    break;
                                                }
                                            }
                                            echo $processing_count;
                                        ?></span>
                                        <span class="block text-sm">Processing</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <div class="card bg-success-600! text-white">
                                <div class="card-body flex items-center gap-4">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/20 text-lg">
                                        <i class="fas fa-check" aria-hidden="true"></i>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-2xl font-bold leading-tight"><?php 
                                            $approved_count = 0;
                                            foreach ($status_counts as $status) {
                                                if ($status['status'] == 'approved') {
                                                    $approved_count = $status['count'];
                                                    break;
                                                }
                                            }
                                            echo $approved_count;
                                        ?></span>
                                        <span class="block text-sm">Approved</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <div class="card bg-danger-600! text-white">
                                <div class="card-body flex items-center gap-4">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/20 text-lg">
                                        <i class="fas fa-times" aria-hidden="true"></i>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-2xl font-bold leading-tight"><?php 
                                            $rejected_count = 0;
                                            foreach ($status_counts as $status) {
                                                if ($status['status'] == 'rejected') {
                                                    $rejected_count = $status['count'];
                                                    break;
                                                }
                                            }
                                            echo $rejected_count;
                                        ?></span>
                                        <span class="block text-sm">Rejected</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <div class="card bg-brand-600! text-white">
                                <div class="card-body flex items-center gap-4">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/20 text-lg">
                                        <i class="fas fa-check-double" aria-hidden="true"></i>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-2xl font-bold leading-tight"><?php 
                                            $completed_count = 0;
                                            foreach ($status_counts as $status) {
                                                if ($status['status'] == 'completed') {
                                                    $completed_count = $status['count'];
                                                    break;
                                                }
                                            }
                                            echo $completed_count;
                                        ?></span>
                                        <span class="block text-sm">Completed</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <div class="card bg-brand-600! text-white">
                                <div class="card-body flex items-center gap-4">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/20 text-lg">
                                        <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-2xl font-bold leading-tight"><?php echo $total_requests; ?></span>
                                        <span class="block text-sm">Total Requests</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Purchase Requests List -->
                <div class="card mb-6">
                    <div class="card-header flex justify-between items-center">
                        <div>
                            <i class="fas fa-table mr-1"></i>
                            Spare Parts/Materials PR, Withdrawal Slips & Job Order
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPRModal">
                            <i class="fas fa-plus mr-1"></i> Create New Document
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
                                                        <span class="jo-number text-success-600">
                                                            <?php echo htmlspecialchars($jo_number); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted italic">Not yet Created</span>
                                                    <?php endif; ?>
                                                <?php elseif ($is_withdrawal_slip): ?>
                                                    <?php if ($ws_number): ?>
                                                        <span class="ws-number text-warning-600">
                                                            <?php echo htmlspecialchars($ws_number); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted italic">Not yet Created</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="spr-number text-primary">
                                                        <?php echo htmlspecialchars($pr['pr_number']); ?>
                                                    </span>
                                                    <?php if ($po_number): ?>
                                                        <br>
                                                        <span class="po-number text-brand-600">
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
                                                <div class="relative flex">
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </button>
                                                    <ul class="dropdown-menu app-dropdown">
                                                        <?php if ($pr['request_type'] === 'stock'): ?>
                                                            <!-- Stock Purchase Request PDF options -->
                                                            <li>
                                                                <a class="app-dropdown-item" href="generate_spare_parts_pr_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                    <i class="fas fa-file-pdf text-danger"></i> Purchase Request
                                                                </a>
                                                            </li>
                                                            <?php if ($po_number): ?>
                                                            <li>
                                                                <a class="app-dropdown-item" href="generate_spare_parts_po_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                    <i class="fas fa-file-pdf text-danger"></i> Purchase Order
                                                                </a>
                                                            </li>
                                                            <?php endif; ?>
                                                        <?php elseif ($pr['request_type'] === 'issue'): ?>
                                                            <!-- Issue Parts PDF options -->
                                                            <?php if ($jo_number): ?>
                                                            <li>
                                                                <a class="app-dropdown-item" href="job_order_slip_PDF.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                    <i class="fas fa-file-pdf text-danger"></i> Job Order Slip
                                                                </a>
                                                            </li>
                                                            <?php endif; ?>
                                                        <?php elseif ($pr['request_type'] === 'issue_materials'): ?>
                                                            <!-- Issue Materials PDF options -->
                                                            <?php if ($ws_number): ?>
                                                            <li>
                                                                <a class="app-dropdown-item" href="generate_spare_parts_ws_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                    <i class="fas fa-file-pdf text-danger"></i> Withdrawal Slip
                                                                </a>
                                                            </li>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                                <?php endif; ?>

                                                <!-- View Details button -->
                                                <button class="btn btn-sm bg-info-600 text-white view-pr-btn" data-id="<?php echo $pr['id']; ?>" 
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
                            <i class="fas fa-inbox fa-3x text-muted mb-4"></i>
                            <h5>No Documents Found</h5>
                            <p class="text-muted">Create your first purchase request or withdrawal slip to get started.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPRModal">
                                <i class="fas fa-plus mr-1"></i> Create First Document
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
                    <div class="modal-body max-h-[70vh] overflow-y-auto">
                        <!-- Requested By / Request Date / Expected Delivery Date: one row from
                             tablet up, one column on a phone. The grid has four items, but the first
                             is the hidden PR Number, so it lays out as the three fields.
                             `.pr-form-row` (assets/css/tailwind.css) carries the breakpoint and the
                             tighter stacked spacing on small screens. -->
                        <div class="pr-form-row">
                            <div class="min-w-0" hidden>
                                <div class="form-floating mb-4">
                                    <input type="text" class="form-control" id="document_number" value="<?php echo $default_pr_number; ?>" readonly disabled>
                                    <label id="document_number_label">PR Number</label>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <input type="text" class="form-control" id="prepared_by_field" value="<?php echo $display_name; ?>" readonly disabled>
                                    <label id="prepared_by_label">Requested By</label>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <input type="date" class="form-control" name="request_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    <label>Request Date <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <input type="date" class="form-control" name="expected_delivery_date">
                                    <label>Expected Delivery Date</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Request Type Selector -->
                        <div class="request-type-selector mb-4 border border-slate-200 rounded-lg p-4">
                            <h6>Select Document Type <span class="text-danger">*</span></h6>
                            <div class="request-type-grid grid gap-4 grid-cols-[repeat(auto-fit,minmax(300px,1fr))]">
                                <!-- Stock Purchase Request -->
                                <div class="request-type-option active cursor-pointer rounded border border-transparent p-2 m-1 hover:bg-slate-50 [&.active]:bg-brand-50 [&.active]:border-brand-500" data-type="stock" onclick="selectRequestType('stock')">
                                    <div class="flex items-center">
                                        <div class="mr-3">
                                            <i class="fas fa-boxes fa-2x text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Stock Purchase Request (SPR)</h6>
                                            <p class="mb-0 text-muted small">For replenishing inventory stock levels</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Issue Parts Purchase Request -->
                                <div class="request-type-option cursor-pointer rounded border border-transparent p-2 m-1 hover:bg-slate-50 [&.active]:bg-brand-50 [&.active]:border-brand-500" data-type="issue" onclick="selectRequestType('issue')">
                                    <div class="flex items-center">
                                        <div class="mr-3">
                                            <i class="fas fa-truck-loading fa-2x text-success"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Issue Parts Job Order (JO)</h6>
                                            <p class="mb-0 text-muted small">For direct issuance to vehicles/equipment</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Issue Materials Withdrawal Slip -->
                                <div class="request-type-option cursor-pointer rounded border border-transparent p-2 m-1 hover:bg-slate-50 [&.active]:bg-brand-50 [&.active]:border-brand-500" data-type="issue_materials" onclick="selectRequestType('issue_materials')">
                                    <div class="flex items-center">
                                        <div class="mr-3">
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
                        <div class="issue-fields border border-slate-200 rounded-lg p-4 mb-4 bg-warning-100" id="issue_fields" style="display: none;">
                            <h6 class="text-success-700"><i class="fas fa-truck-loading mr-1"></i> Issue Parts Details</h6>
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                                <div class="min-w-0">
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
                                <div class="min-w-0">
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
                            
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
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
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
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
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <input type="text" class="form-control" id="issue_purpose" name="purpose">
                                        <label for="issue_purpose">Purpose <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Issue Materials Fields -->
                        <div class="materials-fields border border-slate-200 rounded-lg p-4 mb-4 bg-warning-100" id="materials_fields" style="display: none;">
                            <h6 class="text-warning-700"><i class="fas fa-hard-hat mr-1"></i> Issue Materials Details</h6>
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
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
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <input type="text" class="form-control" id="materials_purpose" name="materials_purpose">
                                        <label for="materials_purpose">Purpose <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Supplier Field -->
                        <div class="grid grid-cols-1 gap-6 supplier-field">
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
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
                        <div class="mb-4">
                            <h6>Requested Items <span class="text-danger">*</span></h6>
                            <div id="itemsContainer">
                                <div class="item-row mb-4 p-3" id="item-row-0">
                                    <!-- Anchored at the row's top-right; the row reserves its width. -->
                                    <div class="remove-btn-container">
                                        <button type="button" class="btn btn-danger btn-sm remove-item" style="display: none;">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </div>
                                    <!-- `.row` is a selector hook: assets/js/purchase_request_spare_parts.js reads
                                         this element with row.querySelector('.row').children and rewrites its
                                         children's class names, so the four fields must stay its direct children,
                                         in order. assets/css/tailwind.css lays them out in one row. -->
                                    <div class="item-fields row">
                                        <div class="min-w-0 item-col">
                                            <!-- Searchable dropdown container -->
                                            <div class="searchable-dropdown-container relative w-full" id="searchable-container-0">
                                                <input type="text" 
                                                    class="searchable-dropdown-input form-control" 
                                                    id="search-input-0" 
                                                    placeholder="Type to search items..."
                                                    autocomplete="off"
                                                    required>
                                                <input type="hidden" name="items[0][part_id]" id="part-id-0" required>
                                                <div class="searchable-dropdown-list" id="dropdown-list-0"></div>
                                            </div>
                                            <!-- Removed selected-item-info div -->
                                        </div>
                                        <div class="min-w-0 qty-col">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" name="items[0][quantity]" min="1" required 
                                                    onchange="calculateTotal(this, 0)">
                                                <label>Quantity <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="min-w-0 cost-fields">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" name="items[0][unit_cost]" 
                                                    id="unit-cost-0" onchange="calculateTotal(this, 0)" placeholder="Enter unit cost" value="0">
                                                <label>Unit Cost (₱)</label>
                                            </div>
                                        </div>
                                        <div class="min-w-0 cost-fields">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="item-total-0" value="₱0.00" readonly>
                                                <label>Item Total</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-between items-center">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addItem">
                                    <i class="fas fa-plus mr-1"></i> Add Another Item
                                </button>
                                <div class="fw-bold cost-fields" id="grand-total">Grand Total: ₱0.00</div>
                            </div>
                        </div>
                        
                        <!-- Remarks Field -->
                        <div class="grid grid-cols-1 gap-6">
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <textarea class="form-control" name="remarks" id="remarks" style="height: 100px"></textarea>
                                    <label for="remarks">Remarks / Notes</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer sticky bottom-0 z-10">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submit_button">Create Purchase Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View PR Modal -->
    <div class="modal fade" id="viewPRModal" tabindex="-1" aria-labelledby="viewPRModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPRModalLabel">Document Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body max-h-[70vh] overflow-y-auto" id="prDetails">
                    <!-- Details will be loaded via JavaScript -->
                </div>
                <div class="modal-footer sticky bottom-0 z-10">
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
                    <div class="modal-body max-h-[70vh] overflow-y-auto">
                        <p>Are you sure you want to convert this Purchase Request to a Purchase Order?</p>
                        <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
                        <p>Once converted, the PR status will change to "processing" and a new PO will be created.</p>
                    </div>
                    <div class="modal-footer sticky bottom-0 z-10">
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
                    <div class="modal-body max-h-[70vh] overflow-y-auto">
                        <p>Are you sure you want to delete this document?</p>
                        <p><strong id="delete_pr_number"></strong></p>
                        <p class="text-danger">Warning: This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer sticky bottom-0 z-10">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
<?php
    /* Data island consumed by assets/js/purchase_request_spare_parts.js. The script
     * is a separate request, so it cannot see this page's variables: the part lists
     * and the employee lists it builds its dropdowns from are handed over here. */
    $__ocp_data = [];
    $__ocp_data["allParts"] = $all_parts ?? [];
    $__ocp_data["issueParts"] = $issue_parts ?? [];
    $__ocp_data["issueMaterials"] = $issue_materials ?? [];
    $__ocp_data["formattedEmployees"] = $formatted_employees ?? [];
    $__ocp_data["formattedMechanics"] = $formatted_mechanics ?? [];
    $__ocp_data["formattedDrivers"] = $formatted_drivers ?? [];
    /* swalData = $swal_data [guarded] */
    if (!empty($swal_data)) {
        $__ocp_data["swalData"] = $swal_data['title'] ?? '';
        $__ocp_data["swalData2"] = $swal_data['text'] ?? '';
        $__ocp_data["swalData3"] = $swal_data['icon'] ?? '';
    }
    /* Flag for the script. Its alert block tested a key this page never published, so the
     * message the actions file prepared was never shown. This answers for it. */
    $__ocp_data["hasMessage"] = (!empty($swal_data));
    ocp_page_data("purchase_request_spare_parts", $__ocp_data);
    unset($__ocp_data);
?>
    <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
    <script src="<?php echo ocp_asset('assets/js/purchase_request_spare_parts.js'); ?>"></script>
</body>
</html>
