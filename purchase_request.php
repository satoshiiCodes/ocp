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

// All of this page's actions live in one file: creating a purchase request,
// updating one, changing its status and deleting it. The forms post back to this
// page, so it is pulled in before anything is read or rendered.
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

if (!defined('OCP_PURCHASE_REQUEST_ACTIONS_RAN')) {
    require __DIR__ . '/actions/purchase_request-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. The same file answers
// the page's JavaScript when it asks for one purchase request by ?id=, and it
// carries the helper the form needs below.
$ocp_endpoint = require __DIR__ . '/api/purchase_request-endpoint.php';
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

// Function to get document type badge
function getDocumentTypeBadge($document_type) {
    switch ($document_type) {
        case 'ws':
            return '<span class="badge badge-success" title="Warehouse Stock Only">WS</span>';
        case 'po_ws':
            return '<span class="badge badge-warning" title="PO with Warehouse Stock">PO/WS</span>';
        case 'pr_po':
            return '<span class="badge badge-danger" title="PR to PO">PR/PO</span>';
        case 'direct_po':
            return '<span class="badge badge-info" title="Direct Purchase Order">Direct PO</span>';
        default:
            return '<span class="badge badge-neutral">N/A</span>';
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
            return '<span class="badge badge-info">Project</span>';
        case 'supplier':
            return '<span class="badge badge-primary">Stock</span>';
        default:
            return '<span class="badge badge-neutral">' . ucfirst($type) . '</span>';
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
                        <h1 class="page-title">Warehouse PR</h1>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="inventory.php">Warehouse Inventory</a></li>
                            <li class="breadcrumb-item active">Purchase Requests</li>
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
                            <div class="card bg-slate-600! text-white">
                                <div class="card-body flex items-center gap-4">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/20 text-lg">
                                        <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-2xl font-bold leading-tight"><?php echo count($purchase_requests); ?></span>
                                        <span class="block text-sm">Total PRs</span>
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
                                Purchase Requests
                            </div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPRModal">
                                <i class="fas fa-plus mr-1"></i> Create PR
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
                                            $document_numbers = '<div class="document-numbers flex flex-col gap-1">';
                                            
                                            if (!empty($pr_display)) {
                                                $document_numbers .= '<div class="document-number-item pr-number text-sm text-primary">' . $pr_display . '</div>';
                                            }
                                            if (!empty($po_display)) {
                                                $document_numbers .= '<div class="document-number-item po-number text-sm text-brand-600">' . $po_display . '</div>';
                                            }
                                            if (!empty($ws_display)) {
                                                $document_numbers .= '<div class="document-number-item ws-number text-sm text-warning-600">' . $ws_display . '</div>';
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
                                                        echo '<span class="badge badge-neutral">N/A</span>';
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
                                                    <div class="relative flex">
                                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="dropdown" aria-expanded="false" title="Generate PDF">
                                                            <i class="fas fa-file-pdf"></i>
                                                        </button>
                                                        <ul class="dropdown-menu app-dropdown">
                                                            <?php if ($pr['document_type'] == 'ws'): ?>
                                                                <!-- For WS document type, only show WS PDF if available -->
                                                                <?php if ($has_ws_pdf): ?>
                                                                <li>
                                                                    <a class="app-dropdown-item" href="generate_ws_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                        <i class="fas fa-file-pdf text-danger mr-2"></i> Withdrawal Slip (WS)
                                                                    </a>
                                                                </li>
                                                                <?php endif; ?>
                                                            <?php elseif ($pr['document_type'] == 'po_ws'): ?>
                                                                <!-- For PO/WS document type, show PR, PO, and WS options if available -->
                                                                <?php if ($has_pr_pdf): ?>
                                                                <li>
                                                                    <a class="app-dropdown-item" href="generate_pr_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                        <i class="fas fa-file-pdf text-danger mr-2"></i> PR (Project)
                                                                    </a>
                                                                </li>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($has_po_pdf): ?>
                                                                <li>
                                                                    <a class="app-dropdown-item" href="generate_po_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                        <i class="fas fa-file-pdf text-danger mr-2"></i> PO (Project)
                                                                    </a>
                                                                </li>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($has_ws_pdf): ?>
                                                                <li>
                                                                    <a class="app-dropdown-item" href="generate_ws_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                        <i class="fas fa-file-pdf text-danger mr-2"></i> Withdrawal Slip (WS)
                                                                    </a>
                                                                </li>
                                                                <?php endif; ?>
                                                            <?php elseif ($pr['document_type'] == 'pr_po'): ?>
                                                                <!-- For PR/PO document type, show PR and PO options if available -->
                                                                <?php if ($pr['request_type'] == 'project'): ?>
                                                                    <?php if ($has_pr_pdf): ?>
                                                                    <li>
                                                                        <a class="app-dropdown-item" href="generate_pr_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger mr-2"></i> PR (Project)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php if ($has_po_pdf): ?>
                                                                    <li>
                                                                        <a class="app-dropdown-item" href="generate_po_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger mr-2"></i> PO (Project)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php if ($has_ws_pdf): ?>
                                                                    <li>
                                                                        <a class="app-dropdown-item" href="generate_ws_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger mr-2"></i> Withdrawal Slip (WS)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <?php if ($has_pr_pdf): ?>
                                                                    <li>
                                                                        <a class="app-dropdown-item" href="generate_pr_supplier_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger mr-2"></i> PR (Supplier)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php if ($has_po_pdf): ?>
                                                                    <li>
                                                                        <a class="app-dropdown-item" href="generate_po_supplier_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger mr-2"></i> PO (Supplier)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <!-- Default/fallback options -->
                                                                <?php if ($pr['request_type'] == 'project'): ?>
                                                                    <?php if ($has_pr_pdf): ?>
                                                                    <li>
                                                                        <a class="app-dropdown-item" href="generate_pr_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger mr-2"></i> PR (Project)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <?php if ($has_pr_pdf): ?>
                                                                    <li>
                                                                        <a class="app-dropdown-item" href="generate_pr_supplier_pdf.php?id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger mr-2"></i> PR (Supplier)
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($has_po_pdf): ?>
                                                                    <li><hr class="my-1"></li>
                                                                    <li>
                                                                        <a class="app-dropdown-item" href="generate_po_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger mr-2"></i> PO (Project)
                                                                        </a>
                                                                    </li>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($has_ws_pdf): ?>
                                                                    <li><hr class="my-1"></li>
                                                                    <li>
                                                                        <a class="app-dropdown-item" href="generate_ws_pdf.php?pr_id=<?php echo $pr['id']; ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger mr-2"></i> Withdrawal Slip (WS)
                                                                        </a>
                                                                    </li>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                    <?php endif; ?>

                                                    <button class="btn btn-sm bg-info-600 text-white view-pr" data-id="<?php echo $pr['id']; ?>" 
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
                                <i class="fas fa-inbox fa-3x text-muted mb-4"></i>
                                <h5>No Purchase Requests Found</h5>
                                <p class="text-muted">Create your first purchase request to get started.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPRModal">
                                    <i class="fas fa-plus mr-1"></i> Create First PR
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
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPRModalLabel">Create New Purchase Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="createPRForm">
                    <input type="hidden" name="create_pr" value="1">
                    <div class="modal-body max-h-[70vh] overflow-y-auto">
                        <!-- Requested By / Request Date / Request Type: one row from tablet up, one
                             column on a phone. The grid has four items, but the first is the hidden
                             PR Number, so it lays out as the three fields.
                             `.pr-form-row` (assets/css/tailwind.css) carries the breakpoint and the
                             tighter stacked spacing on small screens. -->
                        <div class="pr-form-row">
                            <div class="min-w-0" hidden>
                                <div class="form-floating mb-4">
                                    <input type="text" class="form-control" value="<?php echo $pr_number; ?>" readonly disabled>
                                    <label>PR Number</label>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <input type="text" class="form-control" value="<?php echo $display_name; ?>" readonly disabled>
                                    <label>Requested By</label>
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
                                    <input type="hidden" name="request_type" id="request_type" value="project" required>
                                    <input type="text" class="form-control" id="request_type_display" value="Project" readonly>
                                    <label>Request Type</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Request Type Selector -->
                        <div class="request-type-selector mb-4 border border-slate-200 rounded-lg p-4">
                            <h6>Select Request Type <span class="text-danger">*</span></h6>
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="request-type-option active cursor-pointer rounded border border-transparent p-2 m-1 hover:bg-slate-50 [&.active]:bg-brand-50 [&.active]:border-brand-500" data-type="project" onclick="selectRequestType('project')">
                                        <div class="flex items-center">
                                            <div class="mr-3">
                                                <i class="fas fa-project-diagram fa-2x text-info-600"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Project Purchase Request</h6>
                                                <p class="mb-0 text-muted small hidden md:block">For items needed for specific projects</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="request-type-option cursor-pointer rounded border border-transparent p-2 m-1 hover:bg-slate-50 [&.active]:bg-brand-50 [&.active]:border-brand-500" data-type="supplier" onclick="selectRequestType('supplier')">
                                        <div class="flex items-center">
                                            <div class="mr-3">
                                                <i class="fas fa-truck fa-2x text-primary"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">Stock Purchase Request</h6>
                                                <p class="mb-0 text-muted small hidden md:block">For items needed from specific suppliers</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Project Field (shown by default) -->
                        <div class="type-dependent-field" id="project-field" style="display: block;">
                            <div class="form-floating mb-4">
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
                        
                        <!-- Stock Status Preview -->
                        <div id="stockPreview" class="alert alert-info" style="display: none;" hidden>
                            <i class="fas fa-info-circle mr-2"></i>
                            <span id="stockMessage"></span>
                        </div>
                        
                        <!-- Items Section -->
                        <div class="mb-4">
                            <h6>Items Requested <span class="text-danger">*</span></h6>
                            <div id="itemsContainer">
                                <!-- First item row -->
                                <div class="item-row mb-2 p-3" data-item-index="0">
                                    <!-- Anchored at the row's top-right; the row reserves its width. -->
                                    <div class="remove-btn-container">
                                        <button type="button" class="btn btn-danger btn-sm remove-item" style="display: none;">
                                            <i class="fas fa-times mr-1"></i> Remove
                                        </button>
                                    </div>
                                    <div class="item-fields row">
                                        <div class="min-w-0 item-col">
                                            <!-- Searchable dropdown container for item - UPDATED for the new display format -->
                                            <div class="searchable-dropdown-container relative w-full" id="item-searchable-container-0">
                                                <input type="text" 
                                                       class="searchable-dropdown-input item-search-input form-control" 
                                                       id="item-search-input-0" 
                                                       placeholder="Type to search items..."
                                                       autocomplete="off"
                                                       data-item-index="0">
                                                <input type="hidden" name="items[0][item_id]" id="item-id-0" class="item-id-hidden" required>
                                                <div class="searchable-dropdown-list" id="item-dropdown-list-0"></div>
                                            </div>
                                        </div>
                                        <div class="min-w-0 qty-col">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" name="items[0][quantity]" min="1" required>
                                                <label>Quantity <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="min-w-0 unit-cost-col" style="display: none;">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" name="items[0][unit_cost]" step="0.01" min="0" value="0">
                                                <label>Unit Cost (₱)</label>
                                            </div>
                                        </div>
                                        <div class="min-w-0 warehouse-col">
                                            <div class="form-floating">
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
                            
                            <div class="flex justify-between items-center mt-4">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addItem">
                                    <i class="fas fa-plus mr-1"></i> Add Another Item
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="checkStockBtn" onclick="checkStockAvailability()" hidden>
                                    <i class="fas fa-search mr-1"></i> Check Stock
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer sticky bottom-0 z-10">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Purchase Request</button>
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
                    <h5 class="modal-title" id="viewPRModalLabel">Purchase Request Details</h5>
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
                        <p>Are you sure you want to delete this purchase request?</p>
                        <p><strong id="delete_pr_number"></strong></p>
                        <p class="text-danger">Warning: This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer sticky bottom-0 z-10">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete PR</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
    <?php
    /* Data island consumed by assets/js/purchase_request.js. */
    $__ocp_data = [];
    /* items */
    ob_start();
    include __DIR__ . "/includes/partials/purchase_request/items.php";
    $__ocp_data["items"] = ob_get_clean();
    /* swalData [guarded] */
    if (!empty($swal_data)) {
        ob_start();
        include __DIR__ . "/includes/partials/purchase_request/swalData.php";
        $__ocp_data["swalData"] = ob_get_clean();
    }
    /* swalData2 [guarded] */
    if (!empty($swal_data)) {
        ob_start();
        include __DIR__ . "/includes/partials/purchase_request/swalData2.php";
        $__ocp_data["swalData2"] = ob_get_clean();
    }
    /* swalData3 [guarded] */
    if (!empty($swal_data)) {
        ob_start();
        include __DIR__ . "/includes/partials/purchase_request/swalData3.php";
        $__ocp_data["swalData3"] = ob_get_clean();
    }
    /* The warehouse dropdown is built by the script when a row is added, and the
     * browser fetches that script as its own request where $warehouses is not in
     * scope. The options are therefore rendered here and handed to the script in the
     * data island, which reads them in the browser. */
    $__ocp_warehouse_html = '';
    foreach (is_array($warehouses ?? null) ? $warehouses : [] as $warehouse) {
        $__ocp_warehouse_html .= '<option value="'
            . htmlspecialchars($warehouse['id'], ENT_QUOTES) . '">'
            . htmlspecialchars($warehouse['warehouse_name'] . ' - ' . $warehouse['location'], ENT_QUOTES)
            . '</option>';
    }
    $__ocp_data["warehouseOptionsHtml"] = $__ocp_warehouse_html;
    unset($__ocp_warehouse_html, $warehouse);
    /* Flags for the script. It is a separate request, so it cannot test this
     * page's variables itself; these answer for it. Each is false on an ordinary
     * load, so nothing is shown unless there is something to show. */
    $__ocp_data["hasMessage"] = (!empty($swal_data));
    $__ocp_data["isError"] = (($swal_data['icon'] ?? '') === 'error');
    ocp_page_data("purchase_request", $__ocp_data);
    unset($__ocp_data);
    ?>
    <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
    <script src="assets/js/purchase_request.js.php"></script>
</body>
</html>
