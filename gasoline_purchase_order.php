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
// Get user details and their role
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.firstname, u.middlename, u.lastname, u.suffix, u.accounttype, u.position FROM users u WHERE u.id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user can approve POs (Admin with CEO position)
$can_approve_po = ($user['accounttype'] === 'Admin' && $user['position'] === 'CEO');

// Check if user can delete POs (Admin with CEO position)
$can_delete_po = ($user['accounttype'] === 'Admin' && $user['position'] === 'CEO');

// Check if user can complete POs (Admin with Purchaser position)
$can_complete_po = ($user['accounttype'] === 'Admin' && $user['position'] === 'Purchaser');

// Check if user can update invoice (Admin with Purchaser position)
$can_update_invoice = ($user['accounttype'] === 'Admin' && $user['position'] === 'Purchaser');

// Format the display name
$display_name = $user['firstname'];
if (!empty($user['middlename'])) {
    $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
}
$display_name .= ' ' . $user['lastname'];
if (!empty($user['suffix'])) {
    $display_name .= ' ' . $user['suffix'];
}

// All of this page's actions live in one file: creating, updating, approving,
// completing, cancelling and deleting a PO, updating an invoice, and loading one
// into the form for editing. The forms post back to this page, so it is pulled in
// before anything is read or rendered. It also reads the four role flags above.
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

if (!defined('OCP_GASOLINE_PURCHASE_ORDER_ACTIONS_RAN')) {
    require __DIR__ . '/actions/gasoline_purchase_order-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. The same file answers
// the page's JavaScript when it posts for one PO, and it carries the shared
// helpers. swal_data and edit_po_data are seeded from the actions file, so a
// message or an edit form it set is not wiped out here.
$ocp_endpoint = require __DIR__ . '/api/gasoline_purchase_order-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);

// Helper function to format employee name with position
function formatEmployeeNameWithPosition($employee) {
    $name = $employee['firstname'];
    if (!empty($employee['middlename'])) {
        $name .= ' ' . substr($employee['middlename'], 0, 1) . '.';
    }
    $name .= ' ' . $employee['lastname'];
    if (!empty($employee['suffix'])) {
        $name .= ' ' . $employee['suffix'];
    }
    return $name . ' (Driver)';
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Gasoline Purchase Orders - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <!-- Include signature pad library -->
        <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
        <link href="assets/css/app.css" rel="stylesheet" />
        <link href="assets/css/app.build.css" rel="stylesheet" />
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content" class="sb-content">
                <main>
                    <div class="w-full px-6">
                        <div class="mb-6">
                            <h1 class="page-title">Gasoline Purchase Orders</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Purchase Orders</li>
                            </ol>
                        </div>
                        
                        <!-- Statistics Cards -->
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4 mb-6">
                            
                            <div class="min-w-0">
                                <div class="card bg-warning-600! text-white stat-card h-full transition hover:-translate-y-1">
                                    <div class="card-body">
                                        <div class="stat-icon text-3xl mb-4">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <h5 class="card-title text-white!">Pending</h5>
                                        <h2 class="mb-0 font-bold text-2xl">
                                            <?php 
                                                $pending_count = 0;
                                                foreach ($status_counts as $stat) {
                                                    if ($stat['status'] === 'pending') {
                                                        $pending_count = $stat['count'];
                                                        break;
                                                    }
                                                }
                                                echo $pending_count;
                                            ?>
                                        </h2>
                                        <p class="card-text">Pending purchase orders</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="min-w-0">
                                <div class="card bg-info-600! text-white stat-card h-full transition hover:-translate-y-1">
                                    <div class="card-body">
                                        <div class="stat-icon text-3xl mb-4">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <h5 class="card-title text-white!">Approved</h5>
                                        <h2 class="mb-0 font-bold text-2xl">
                                            <?php 
                                                $approved_count = 0;
                                                foreach ($status_counts as $stat) {
                                                    if ($stat['status'] === 'approved') {
                                                        $approved_count = $stat['count'];
                                                        break;
                                                    }
                                                }
                                                echo $approved_count;
                                            ?>
                                        </h2>
                                        <p class="card-text">Approved purchase orders</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Completed status card -->
                            <div class="min-w-0">
                                <div class="card bg-success-600! text-white stat-card h-full transition hover:-translate-y-1">
                                    <div class="card-body">
                                        <div class="stat-icon text-3xl mb-4">
                                            <i class="fas fa-check-double"></i>
                                        </div>
                                        <h5 class="card-title text-white!">Completed</h5>
                                        <h2 class="mb-0 font-bold text-2xl">
                                            <?php 
                                                $completed_count = 0;
                                                foreach ($status_counts as $stat) {
                                                    if ($stat['status'] === 'completed') {
                                                        $completed_count = $stat['count'];
                                                        break;
                                                    }
                                                }
                                                echo $completed_count;
                                            ?>
                                        </h2>
                                        <p class="card-text">Completed purchase orders</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Canceled status card -->
                            <div class="min-w-0">
                                <div class="card bg-danger-600! text-white stat-card h-full transition hover:-translate-y-1">
                                    <div class="card-body">
                                        <div class="stat-icon text-3xl mb-4">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <h5 class="card-title text-white!">Canceled</h5>
                                        <h2 class="mb-0 font-bold text-2xl">
                                            <?php 
                                                $canceled_count = 0;
                                                foreach ($status_counts as $stat) {
                                                    if ($stat['status'] === 'cancelled') {
                                                        $canceled_count = $stat['count'];
                                                        break;
                                                    }
                                                }
                                                echo $canceled_count;
                                            ?>
                                        </h2>
                                        <p class="card-text">Canceled purchase orders</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Waiting for Approval Label (visible to all users) -->
                        <?php if ($pending_count > 0): ?>
                        <div class="alert alert-warning mb-6">
                            <i class="fas fa-hourglass-half"></i>
                            <div>
                                <strong><?php echo $pending_count; ?> purchase order(s)</strong> waiting for CEO approval.
                                <?php if ($can_approve_po): ?>
                                <span class="badge badge-warning ml-2">You are the CEO - please review pending orders</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Pending Items for Issuance -->
                        <?php if (!empty($pending_items)): ?>
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-gas-pump mr-1"></i>
                                Pending Gasoline Issuance
                                <span class="badge badge-warning float-end"><?php echo count($pending_items); ?> items</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="pendingItemsTable">
                                        <thead>
                                            <tr>
                                                <th>PO Number</th>
                                                <th>Gasoline Type</th>
                                                <th>Supplier</th>
                                                <th>Vehicle/Equipment</th>
                                                <th>Driver/Operator</th>
                                                <th>Purpose</th>
                                                <th>Quantity (L)</th>
                                                <th>Price/Liter</th>
                                                <th>Total</th>
                                                <th>Date Issued</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pending_items as $item): 
                                                $total_value = $item['quantity_liters'] * ($item['price_per_liter'] ?? 0);
                                                
                                                // Determine vehicle/equipment info
                                                $vehicle_equipment = '';
                                                if ($item['vehicle_id']) {
                                                    $vehicle_equipment = htmlspecialchars($item['vehicle_name'] ?? '') . ' (' . htmlspecialchars($item['plate_number'] ?? '') . ')';
                                                } elseif ($item['equipment_id']) {
                                                    $vehicle_equipment = htmlspecialchars($item['equipment_name'] ?? '');
                                                }
                                                
                                                // Get driver/operator name
                                                $driver_operator_name = '';
                                                if (!empty($item['manual_driver_name'])) {
                                                    $driver_operator_name = htmlspecialchars($item['manual_driver_name']) . ' (Manual Entry)';
                                                } elseif (!empty($item['employee_fullname'])) {
                                                    $driver_operator_name = htmlspecialchars($item['employee_fullname']);
                                                    if (!empty($item['employee_id'])) {
                                                        $driver_operator_name .= ' (' . htmlspecialchars($item['employee_id']) . ')';
                                                    }
                                                } else {
                                                    $driver_operator_name = 'Not specified';
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['po_number'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($item['gasoline_type'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($item['supplier_name'] ?? ''); ?></td>
                                                <td><?php echo $vehicle_equipment; ?></td>
                                                <td><?php echo $driver_operator_name; ?></td>
                                                <td><?php echo htmlspecialchars($item['purpose'] ?? ''); ?></td>
                                                <td class="text-end"><?php echo number_format($item['quantity_liters'] ?? 0, 2); ?></td>
                                                <td class="text-end">
                                                    <?php if (!empty($item['price_per_liter'])): ?>
                                                        ₱<?php echo number_format($item['price_per_liter'], 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if (!empty($item['price_per_liter'])): ?>
                                                        ₱<?php echo number_format($total_value, 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['date_issued'] ?? ''); ?></td>
                                                <td>
                                                    <div class="action-btn-group flex flex-col gap-1 items-center justify-center">
                                                        <button class="btn btn-sm btn-success issue-gasoline-btn" 
                                                                data-item-id="<?php echo $item['id']; ?>"
                                                                data-gasoline-type="<?php echo htmlspecialchars($item['gasoline_type'] ?? ''); ?>"
                                                                data-quantity="<?php echo $item['quantity_liters']; ?>"
                                                                data-bs-toggle="tooltip" 
                                                                data-bs-title="Issue Gasoline">
                                                            <i class="fas fa-gas-pump"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Purchase Orders Table -->
                        <div class="card mb-6">
                            <div class="card-header flex justify-between items-center">
                                <div>
                                    <i class="fas fa-file-invoice mr-1"></i>
                                    Gasoline Purchase Orders
                                    <?php if ($pending_count > 0): ?>
                                    <span class="badge badge-warning ml-2"><?php echo $pending_count; ?> waiting for approval</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                        <i class="fas fa-plus-circle mr-1"></i> Create New PO
                                    </button>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <?php if (!empty($purchase_orders)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="poTable">
                                        <thead>
                                            <tr>
                                                <th>PO Number</th>
                                                <th>PO Date</th>
                                                <th>Supplier</th>
                                                <th>Gasoline Type(s)</th>
                                                <th>Quantity (L)</th>
                                                <th>Total Amount</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($purchase_orders as $po): 
                                                $status_class = ['pending' => 'badge-warning', 'approved' => 'badge-info', 'ordered' => 'badge-primary', 'delivered' => 'badge-success', 'completed' => 'badge-success', 'cancelled' => 'badge-danger'][$po['status'] ?? 'pending'] ?? 'badge-neutral';
                                                $status_text = ucfirst($po['status'] ?? 'pending');
                                                $total_amount = !empty($po['total_amount']) ? $po['total_amount'] : ($po['total_amount_calc'] ?? 0);
                                                
                                                // Format gasoline types for display
                                                $gasoline_types = isset($po['gasoline_types']) && $po['gasoline_types'] !== null ? $po['gasoline_types'] : 'N/A';
                                                $total_quantity = isset($po['total_quantity_liters']) ? number_format($po['total_quantity_liters'], 2) : '0.00';
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($po['po_number'] ?? ''); ?></strong>
                                                    <?php if (($po['status'] ?? '') === 'pending'): ?>
                                                    <span class="badge badge-warning ml-1" data-bs-toggle="tooltip" data-bs-title="Waiting for CEO approval">
                                                        <i class="fas fa-clock"></i> Pending Approval
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($po['invoice_number'])): ?>
                                                    <br><span class="badge badge-neutral ml-1"><i class="fas fa-receipt mr-1"></i>Invoice: <?php echo htmlspecialchars($po['invoice_number']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(ocp_date_mdy($po['po_date'] ?? null)); ?></td>
                                                <td><?php echo htmlspecialchars($po['supplier_name'] ?? ''); ?></td>
                                                <td class="text-wrap">
                                                <?php if ($gasoline_types !== 'N/A'): ?>
                                                    <?php 
                                                    $types = explode(', ', $gasoline_types);
                                                    foreach ($types as $type):
                                                        $badge_color = '';
                                                        $type_lower = strtolower(trim($type));
                                                        
                                                        if (strpos($type_lower, 'diesel') !== false) {
                                                            $badge_color = 'badge-warning'; // Yellow background, dark text for Diesel
                                                        } elseif (strpos($type_lower, 'premium') !== false) {
                                                            $badge_color = 'badge-danger'; // Red background for Premium
                                                        } elseif (strpos($type_lower, 'unleaded') !== false) {
                                                            $badge_color = 'badge-success'; // Green background for Unleaded
                                                        } else {
                                                            $badge_color = 'badge-neutral'; // Default gray for other types
                                                        }
                                                    ?>
                                                    <span class="badge mr-1 mb-1 <?php echo $badge_color; ?>">
                                                        <?php echo htmlspecialchars(trim($type)); ?>
                                                    </span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No items</span>
                                                <?php endif; ?>
                                                </td>
                                                <td class="text-end"><?php echo $total_quantity; ?> L</td>
                                                <td class="text-end">
                                                    <?php if ($total_amount > 0): ?>
                                                        ₱<?php echo number_format($total_amount, 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No price set</span>
                                                    <?php endif; ?>
                                                  </td>
                                                <td class="text-center">
                                                    <span class="badge status-badge <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                  </td>
                                                  <td>
                                                    <div class="action-btn-group flex flex-col gap-1 items-center justify-center">
                                                        <!-- COMPLETE PO BUTTON (for Approved POs only) - Only visible to Admin with Purchaser position -->
                                                        <?php if (($po['status'] ?? '') === 'approved' && $can_complete_po): ?>
                                                        <button class="btn btn-sm btn-success complete-po-btn w-full" 
                                                                data-bs-toggle="tooltip" 
                                                                data-bs-title="Mark as Completed"
                                                                data-po-id="<?php echo $po['id']; ?>"
                                                                data-po-number="<?php echo htmlspecialchars($po['po_number'] ?? ''); ?>">
                                                            <i class="fas fa-check-double mr-1"></i> Complete
                                                        </button>
                                                        <?php endif; ?>
                                                        
                                                        <!-- UPDATE INVOICE BUTTON (for Completed POs only) - Only visible to Admin with Purchaser position -->
                                                        <?php if (($po['status'] ?? '') === 'completed' && $can_update_invoice): ?>
                                                        <button class="btn btn-sm btn-success update-invoice-btn w-full" 
                                                                data-bs-toggle="tooltip" 
                                                                data-bs-title="Update Invoice Number"
                                                                data-po-id="<?php echo $po['id']; ?>"
                                                                data-po-number="<?php echo htmlspecialchars($po['po_number'] ?? ''); ?>"
                                                                data-invoice="<?php echo htmlspecialchars($po['invoice_number'] ?? ''); ?>">
                                                            <i class="fas fa-receipt mr-1"></i> Update Invoice
                                                        </button>
                                                        <?php endif; ?>
                                                        
                                                        <!-- APPROVE PO BUTTON -->
                                                        <?php if (($po['status'] ?? '') === 'pending' && $can_approve_po): ?>
                                                        <button class="btn btn-sm bg-info-600 text-white approve-po-btn w-full" 
                                                                data-bs-toggle="tooltip" 
                                                                data-bs-title="Approve PO"
                                                                data-po-id="<?php echo $po['id']; ?>"
                                                                data-po-number="<?php echo htmlspecialchars($po['po_number'] ?? ''); ?>">
                                                            <i class="fas fa-check mr-1"></i> Approve
                                                        </button>
                                                        <?php endif; ?>
                                                        
                                                        <!-- ROW FOR ICON-ONLY BUTTONS -->
                                                        <div class="action-icons-row flex gap-1 justify-center w-full">

                                                            <!-- GENERATE PDF BUTTON - Only show if status is 'approved' or 'completed' -->
                                                            <?php if (($po['status'] ?? '') === 'approved' || ($po['status'] ?? '') === 'completed'): ?>
                                                            <a href="generate_gas_po_pdf.php?id=<?php echo $po['id']; ?>" 
                                                               class="btn btn-sm btn-danger flex-1 min-w-0" 
                                                               data-bs-toggle="tooltip" 
                                                               data-bs-title="Generate PDF"
                                                               target="_blank">
                                                                <i class="fas fa-file-pdf"></i>
                                                            </a>
                                                            <?php endif; ?>
                                                            
                                                            <!-- VIEW DETAILS BUTTON -->
                                                            <button class="btn btn-sm bg-info-600 text-white view-po-btn flex-1 min-w-0" 
                                                                    data-bs-toggle="tooltip" 
                                                                    data-bs-title="View Details"
                                                                    data-po-id="<?php echo $po['id']; ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>

                                                            <!-- EDIT PO BUTTON -->
                                                            <?php if (($po['status'] ?? '') === 'pending' || ($po['status'] ?? '') === 'approved'): ?>
                                                            <a href="?edit_po=<?php echo $po['id']; ?>" class="btn btn-sm btn-warning edit-po-btn flex-1 min-w-0" 
                                                                    data-bs-toggle="tooltip" 
                                                                    data-bs-title="Edit PO">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php endif; ?>
                                                            
                                                            <!-- DELETE PO BUTTON -->
                                                            <?php if (in_array($po['status'] ?? '', ['pending', 'cancelled', 'delivered']) && $can_delete_po): ?>
                                                            <button class="btn btn-sm btn-danger delete-po-btn flex-1 min-w-0" 
                                                                    data-bs-toggle="tooltip" 
                                                                    data-bs-title="Delete PO"
                                                                    data-po-id="<?php echo $po['id']; ?>"
                                                                    data-po-number="<?php echo htmlspecialchars($po['po_number'] ?? ''); ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                   </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No purchase orders found. <a href="#" data-bs-toggle="modal" data-bs-target="#createPOModal">Create your first PO</a></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Create PO Modal -->
        <div class="modal fade" id="createPOModal" tabindex="-1" aria-labelledby="createPOModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createPOModalLabel"><?php echo $edit_po_data ? 'Edit Purchase Order' : 'Create New Gasoline Purchase Order'; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="createPOForm">
                        <input type="hidden" name="action" value="<?php echo $edit_po_data ? 'update_po' : 'create_po'; ?>">
                        <?php if ($edit_po_data): ?>
                        <input type="hidden" name="po_id" value="<?php echo $edit_po_data['id']; ?>">
                        <?php endif; ?>
                        <div class="modal-body">
                            <?php if (!$can_approve_po): ?>
                            <div class="alert alert-warning mb-4">
                                <i class="fas fa-info-circle mr-1"></i>
                                <strong>Note:</strong> This purchase order will be created with status "Pending" and will require CEO approval.
                            </div>
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <input type="text" class="form-control" id="po_number" name="po_number" 
                                            value="<?php echo htmlspecialchars($edit_po_data['po_number'] ?? generatePONumber()); ?>" 
                                            required readonly style="background-color: #e9ecef;">
                                        <label for="po_number">PO Number <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <input type="date" class="form-control" id="po_date" name="po_date" 
                                            value="<?php echo htmlspecialchars($edit_po_data['po_date'] ?? date('Y-m-d')); ?>" required>
                                        <label for="po_date">PO Date <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <?php if (!$can_approve_po): ?>
                                            <!-- For non-CEO users: use hidden input to ensure value is submitted -->
                                            <input type="hidden" name="status" value="pending">
                                            <select class="form-control" id="status" disabled style="background-color: #e9ecef;">
                                                <option value="pending" selected>Pending (Waiting for CEO Approval)</option>
                                            </select>
                                        <?php else: ?>
                                            <!-- For CEO users: normal select -->
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="pending" <?php echo ($edit_po_data && ($edit_po_data['status'] ?? '') === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                            </select>
                                        <?php endif; ?>
                                        <label for="status">Status <span class="text-danger">*</span></label>
                                    </div>
                                    <?php if (!$can_approve_po): ?>
                                    <small class="text-muted">This PO will need CEO approval</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Only Prepared By remains -->
                            <div class="grid grid-cols-1 gap-6">
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <select class="form-select" id="prepared_by" name="prepared_by" required readonly style="background-color: #e9ecef; pointer-events: none;">
                                            <option value="">Select Preparer</option>
                                            <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" 
                                                <?php if ($edit_po_data && ($user['id'] == ($edit_po_data['prepared_by'] ?? null))): ?>
                                                    selected
                                                <?php elseif (!$edit_po_data && $user['id'] == $_SESSION['user_id']): ?>
                                                    selected
                                                <?php endif; ?>>
                                                <?php echo htmlspecialchars($user['fullname']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="prepared_by">Prepared By <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            <h5>PO Items (Gasoline Distribution Details)</h5>
                            <p class="text-muted mb-4">Note: The main supplier for this PO will be determined from the first item's supplier selection.</p>
                            <div id="poItems">
                                <!-- Item template will be added here by JavaScript -->
                                <?php if ($edit_po_data && !empty($edit_po_data['items'])): ?>
                                    <?php foreach ($edit_po_data['items'] as $index => $item): ?>
                                    <div class="item-row border border-slate-200 rounded-lg p-4 mb-4 bg-slate-50">
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                            <div class="min-w-0">
                                                <div class="form-floating mb-4">
                                                    <select class="form-select item-gasoline-type" name="item_gasoline_type[]" required>
                                                        <option value="">Select Gasoline Type</option>
                                                        <?php foreach ($gasolineTypes as $type): ?>
                                                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($item['gasoline_type'] ?? '') == $type ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($type); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label>Gasoline Type <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="form-floating mb-4">
                                                    <select class="form-select item-supplier" name="item_supplier_id[]" required>
                                                        <option value="">Select Supplier</option>
                                                        <?php foreach ($suppliers as $supplier): ?>
                                                        <option value="<?php echo $supplier['id']; ?>" <?php echo ($item['supplier_id'] ?? '') == $supplier['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label>Supplier <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="form-floating mb-4">
                                                    <select class="form-select item-vehicle" name="item_vehicle_id[]">
                                                        <option value="">Select Vehicle (Optional)</option>
                                                        <?php foreach ($vehicles as $vehicle): ?>
                                                        <option value="<?php echo $vehicle['id']; ?>" <?php echo ($item['vehicle_id'] ?? '') == $vehicle['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($vehicle['vehicle_name'] . ' (' . $vehicle['plate_number'] . ')'); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label>Vehicle</label>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="form-floating mb-4">
                                                    <select class="form-select item-equipment" name="item_equipment_id[]">
                                                        <option value="">Select Equipment (Optional)</option>
                                                        <?php foreach ($equipment as $eq): ?>
                                                        <option value="<?php echo $eq['id']; ?>" <?php echo ($item['equipment_id'] ?? '') == $eq['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($eq['equipment_name']); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label>Equipment</label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                            <div class="min-w-0">
                                                <div class="form-floating mb-4">
                                                    <select class="form-select item-driver-select" name="item_driver_operator_id[]" data-index="<?php echo $index; ?>">
                                                        <option value="">Select Driver/Operator</option>
                                                        <?php foreach ($employees as $employee): 
                                                            $employee_name = formatEmployeeNameWithPosition($employee);
                                                        ?>
                                                        <option value="<?php echo $employee['id']; ?>" <?php echo ($item['driver_operator_id'] == $employee['id'] && empty($item['manual_driver_name'])) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($employee_name); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                        <option value="other" <?php echo (!empty($item['manual_driver_name'])) ? 'selected' : ''; ?>>Other (Manual Entry)</option>
                                                    </select>
                                                    <label>Driver/Operator <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="form-floating mb-4">
                                                    <input type="text" class="form-control item-purpose" name="item_purpose[]" 
                                                        value="<?php echo htmlspecialchars($item['purpose'] ?? ''); ?>" required>
                                                    <label>Purpose <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Manual Driver Name Field (full width) -->
                                        <div class="grid grid-cols-1 gap-6" id="manual-driver-row-<?php echo $index; ?>" style="<?php echo (!empty($item['manual_driver_name']) ? 'display: flex;' : 'display: none;'); ?>">
                                            <div class="min-w-0">
                                                <div class="form-floating mb-4">
                                                    <input type="text" class="form-control item-manual-driver" name="item_manual_driver_name[]" 
                                                        value="<?php echo htmlspecialchars($item['manual_driver_name'] ?? ''); ?>" 
                                                        placeholder="Enter driver/operator name">
                                                    <label>Manual Driver/Operator Name</label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                            <div class="min-w-0">
                                                <div class="form-floating mb-4">
                                                    <input type="number" class="form-control item-quantity" name="item_quantity_liters[]" 
                                                        step="0.01" min="0.01" placeholder="Quantity" 
                                                        value="<?php echo htmlspecialchars($item['quantity_liters'] ?? ''); ?>" required>
                                                    <label>Quantity (Liters) <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="form-floating mb-4">
                                                    <input type="number" class="form-control item-price" name="item_price_per_liter[]" 
                                                        step="0.01" min="0" placeholder="Price"
                                                        value="<?php echo htmlspecialchars($item['price_per_liter'] ?? ''); ?>">
                                                    <label>Price per Liter (₱) <span class="text-muted">Optional</span></label>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="form-floating mb-4">
                                                    <input type="number" class="form-control item-odometer" name="item_odometer_reading[]"
                                                        value="<?php echo htmlspecialchars($item['odometer_reading'] ?? ''); ?>">
                                                    <label>Odometer Reading (Optional)</label>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="form-floating mb-4">
                                                    <input type="date" class="form-control item-date" name="item_date_issued[]" 
                                                        value="<?php echo htmlspecialchars($item['date_issued'] ?? ''); ?>" required>
                                                    <label>Date Issued <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 gap-6">
                                            <div class="min-w-0">
                                                <div class="form-floating mb-4">
                                                    <?php 
                                                    $item_total = ($item['quantity_liters'] ?? 0) * (($item['price_per_liter'] ?? 0));
                                                    ?>
                                                    <input type="text" class="form-control item-total font-bold text-success-600!" readonly placeholder="Total"
                                                        value="<?php echo number_format($item_total, 2); ?>">
                                                    <label>Total Amount (₱)</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <div class="item-row border border-slate-200 rounded-lg p-4 mb-4 bg-slate-50">
                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <select class="form-select item-gasoline-type" name="item_gasoline_type[]" required>
                                                    <option value="">Select Gasoline Type</option>
                                                    <?php foreach ($gasolineTypes as $type): ?>
                                                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Gasoline Type <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <select class="form-select item-supplier" name="item_supplier_id[]" required>
                                                    <option value="">Select Supplier</option>
                                                    <?php foreach ($suppliers as $supplier): ?>
                                                    <option value="<?php echo $supplier['id']; ?>">
                                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Supplier <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <select class="form-select item-vehicle" name="item_vehicle_id[]">
                                                    <option value="">Select Vehicle (Optional)</option>
                                                    <?php foreach ($vehicles as $vehicle): ?>
                                                    <option value="<?php echo $vehicle['id']; ?>">
                                                        <?php echo htmlspecialchars($vehicle['vehicle_name'] . ' (' . $vehicle['plate_number'] . ')'); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Vehicle</label>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <select class="form-select item-equipment" name="item_equipment_id[]">
                                                    <option value="">Select Equipment (Optional)</option>
                                                    <?php foreach ($equipment as $eq): ?>
                                                    <option value="<?php echo $eq['id']; ?>">
                                                        <?php echo htmlspecialchars($eq['equipment_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Equipment</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <select class="form-select item-driver-select" name="item_driver_operator_id[]" data-index="0">
                                                    <option value="">Select Driver/Operator</option>
                                                    <?php foreach ($employees as $employee): 
                                                        $employee_name = formatEmployeeNameWithPosition($employee);
                                                    ?>
                                                    <option value="<?php echo $employee['id']; ?>">
                                                        <?php echo htmlspecialchars($employee_name); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                    <option value="other">Other (Manual Entry)</option>
                                                </select>
                                                <label>Driver/Operator <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="text" class="form-control item-purpose" name="item_purpose[]" required>
                                                <label>Purpose <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Manual Driver Name Field (full width) -->
                                    <div class="grid grid-cols-1 gap-6" id="manual-driver-row-0" style="display: none;">
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="text" class="form-control item-manual-driver" name="item_manual_driver_name[]" 
                                                       placeholder="Enter driver/operator name">
                                                <label>Manual Driver/Operator Name</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="number" class="form-control item-quantity" name="item_quantity_liters[]" 
                                                    step="0.01" min="0.01" placeholder="Quantity" required>
                                                <label>Quantity (Liters) <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="number" class="form-control item-price" name="item_price_per_liter[]" 
                                                    step="0.01" min="0" placeholder="Price">
                                                <label>Price per Liter (₱) <span class="text-muted">Optional</span></label>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="number" class="form-control item-odometer" name="item_odometer_reading[]">
                                                <label>Odometer Reading (Optional)</label>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="date" class="form-control item-date" name="item_date_issued[]" 
                                                    value="<?php echo date('Y-m-d'); ?>" required>
                                                <label>Date Issued <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 gap-6">
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="text" class="form-control item-total font-bold text-success-600!" readonly placeholder="Total">
                                                <label>Total Amount (₱)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <button type="button" class="btn btn-sm btn-success" id="addItemBtn">
                                    <i class="fas fa-plus mr-1"></i> Add Item
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" id="removeItemBtn">
                                    <i class="fas fa-minus mr-1"></i> Remove Last Item
                                </button>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-6">
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <?php
                                        $grand_total = 0;
                                        if ($edit_po_data && !empty($edit_po_data['items'])) {
                                            foreach ($edit_po_data['items'] as $item) {
                                                $grand_total += ($item['quantity_liters'] ?? 0) * (($item['price_per_liter'] ?? 0));
                                            }
                                        }
                                        ?>
                                        <input type="number" class="form-control" id="total_amount" name="total_amount" 
                                            value="<?php echo number_format($grand_total, 2); ?>" readonly>
                                        <label for="total_amount">Grand Total Amount (₱)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary"><?php echo $edit_po_data ? 'Update Purchase Order' : 'Create Purchase Order'; ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Complete PO Modal (with Invoice Number and Signature) -->
        <div class="modal fade" id="completePOModal" tabindex="-1" aria-labelledby="completePOModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="completePOModalLabel">Complete Purchase Order - Signature Required</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="completePOForm">
                        <input type="hidden" name="action" value="complete_po">
                        <input type="hidden" id="complete_po_id" name="po_id">
                        <input type="hidden" id="completion_signature_data" name="completion_signature_data">
                        <div class="modal-body">
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle mr-1"></i>
                                You are about to mark as completed: <strong id="complete_po_number_display"></strong>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="complete_invoice_number" name="invoice_number" 
                                       placeholder="Enter Invoice Number (Optional)">
                                <label for="complete_invoice_number">Invoice Number <span class="text-muted">(Optional)</span></label>
                            </div>
                            
                            <div class="alert alert-warning mb-4">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Please sign below to authorize the completion of this purchase order.
                            </div>
                            
                            <!-- Signature Pad for Completion -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Purchaser's Signature</label>
                                <div class="signature-pad-container border-2 border-slate-200 rounded-lg bg-white mb-4">
                                    <canvas id="completionSignatureCanvas" class="signature-pad w-full h-48 border border-slate-300 rounded touch-none" width="500" height="200"></canvas>
                                </div>
                                <div class="signature-actions mt-2 flex gap-2 justify-center">
                                    <button type="button" class="btn btn-sm btn-secondary" id="clearCompletionSignatureBtn">
                                        <i class="fas fa-eraser mr-1"></i> Clear Signature
                                    </button>
                                </div>
                                <small class="text-muted">Draw your signature in the box above</small>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-1"></i>
                                Your signature will be saved and displayed on the PO PDF document.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success" id="submitCompleteBtn">Complete Purchase Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Update Invoice Modal (for Completed POs) -->
        <div class="modal fade" id="updateInvoiceModal" tabindex="-1" aria-labelledby="updateInvoiceModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateInvoiceModalLabel">Update Invoice Number</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="updateInvoiceForm">
                        <input type="hidden" name="action" value="update_invoice">
                        <input type="hidden" id="update_invoice_po_id" name="po_id">
                        <div class="modal-body">
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle mr-1"></i>
                                Updating invoice for: <strong id="update_invoice_po_number_display"></strong>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="update_invoice_number" name="invoice_number" 
                                       placeholder="Enter Invoice Number">
                                <label for="update_invoice_number">Invoice Number <span class="text-muted">(Optional)</span></label>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-1"></i>
                                You can update the invoice number for this completed purchase order at any time.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success">Update Invoice</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Cancel PO Modal -->
        <div class="modal fade" id="cancelPOModal" tabindex="-1" aria-labelledby="cancelPOModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cancelPOModalLabel">Cancel Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="cancel_po">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <select class="form-select" id="cancel_po_id" name="po_id" required>
                                    <option value="">Select PO</option>
                                    <?php foreach ($purchase_orders as $po): ?>
                                    <?php if (($po['status'] ?? '') !== 'delivered' && ($po['status'] ?? '') !== 'cancelled' && ($po['status'] ?? '') !== 'completed'): ?>
                                    <option value="<?php echo $po['id']; ?>">
                                        <?php echo htmlspecialchars(($po['po_number'] ?? '') . ' - ' . ($po['supplier_name'] ?? '')); ?>
                                    </option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <label for="cancel_po_id">Purchase Order <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="cancel_reason" name="cancel_reason" style="height: 100px" required></textarea>
                                <label for="cancel_reason">Reason for Cancellation <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-danger">Cancel Purchase Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Approve PO Modal with Signature -->
        <div class="modal fade" id="approvePOModal" tabindex="-1" aria-labelledby="approvePOModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="approvePOModalLabel">Approve Purchase Order - Signature Required</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="approvePOForm">
                        <input type="hidden" name="action" value="approve_po">
                        <input type="hidden" id="approve_po_id" name="po_id">
                        <input type="hidden" id="signature_data" name="signature_data">
                        <input type="hidden" id="signature_type" name="signature_type" value="draw">
                        <div class="modal-body">
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle mr-1"></i>
                                You are about to approve: <strong id="approve_po_number_display"></strong>
                            </div>
                            
                            <div class="alert alert-warning mb-4">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Please sign below to authorize this purchase order.
                            </div>
                            
                            <!-- Signature Pad -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">CEO Signature</label>
                                <div class="signature-pad-container border-2 border-slate-200 rounded-lg bg-white mb-4">
                                    <canvas id="signatureCanvas" class="signature-pad w-full h-48 border border-slate-300 rounded touch-none" width="500" height="200"></canvas>
                                </div>
                                <div class="signature-actions mt-2 flex gap-2 justify-center">
                                    <button type="button" class="btn btn-sm btn-secondary" id="clearSignatureBtn">
                                        <i class="fas fa-eraser mr-1"></i> Clear Signature
                                    </button>
                                </div>
                                <small class="text-muted">Draw your signature in the box above</small>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-1"></i>
                                Your signature will be saved and displayed on the PO PDF document.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn bg-info-600 text-white" id="submitApproveBtn">Approve Purchase Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- View PO Details Modal -->
        <div class="modal fade" id="viewPOModal" tabindex="-1" aria-labelledby="viewPOModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewPOModalLabel">Purchase Order Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="poDetailsContent">
                        <!-- Content will be loaded via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="printPODetailsBtn">
                            <i class="fas fa-print mr-1"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Issue Gasoline Modal -->
        <div class="modal fade" id="issueGasolineModal" tabindex="-1" aria-labelledby="issueGasolineModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="issueGasolineModalLabel">Issue Gasoline</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="issueGasolineForm">
                        <input type="hidden" name="action" value="issue_gasoline">
                        <input type="hidden" id="issue_po_item_id" name="po_item_id">
                        <div class="modal-body">
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle mr-1"></i>
                                <strong>Item Details:</strong>
                                <div id="itemDetails"></div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <select class="form-select" id="issue_tank_id" name="tank_id" required>
                                    <option value="">Select Source Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="issue_tank_id">From Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="date" class="form-control" id="issue_date" name="issue_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <label for="issue_date">Issue Date <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                This will issue gasoline from the selected tank and record the movement.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success">Issue Gasoline</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Delete PO Modal -->
        <div class="modal fade" id="deletePOModal" tabindex="-1" aria-labelledby="deletePOModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deletePOModalLabel">Delete Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="deletePOForm">
                        <input type="hidden" name="action" value="delete_po">
                        <input type="hidden" id="delete_po_id" name="po_id">
                        <input type="hidden" id="delete_po_number" name="po_number">
                        <div class="modal-body">
                            <div class="alert alert-danger mb-4">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <strong>Warning:</strong> You are about to delete: <strong id="delete_po_number_display"></strong>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="delete_reason" name="delete_reason" style="height: 100px" required></textarea>
                                <label for="delete_reason">Reason for Deletion <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-1"></i>
                                <strong>Note:</strong> This action cannot be undone. Only pending, cancelled, or delivered POs can be deleted.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Purchase Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
        /* jQuery arrives here, long after assets/js/ui.js ran from the shared top bar. That file
         * installs its $().modal() / tooltip() / dropdown() bridge as soon as it can see jQuery -
         * tell it now, so the bridge is in place before the page's own script below asks for a
         * modal. Without this the auto-open on ?edit_po= could run first and throw. */
        if (window.ocpAttachJQueryBridge) { window.ocpAttachJQueryBridge(); }
        </script>
        <?php
        /* Data island consumed by assets/js/gasoline_purchase_order.js. */
        $__ocp_data = [];
        /* swalData [guarded] */
        if (!empty($swal_data)) {
            ob_start();
            include __DIR__ . "/includes/partials/gasoline_purchase_order/swalData.php";
            $__ocp_data["swalData"] = ob_get_clean();
        }
        /* swalData2 [guarded] */
        if (!empty($swal_data)) {
            ob_start();
            include __DIR__ . "/includes/partials/gasoline_purchase_order/swalData2.php";
            $__ocp_data["swalData2"] = ob_get_clean();
        }
        /* swalData3 [guarded] */
        if (!empty($swal_data)) {
            ob_start();
            include __DIR__ . "/includes/partials/gasoline_purchase_order/swalData3.php";
            $__ocp_data["swalData3"] = ob_get_clean();
        }
        $__ocp_data["today"] = date('Y-m-d');
        $__ocp_data["canApprovePo"] = $can_approve_po ? 'true' : 'false';
        /* The item-row dropdowns are built by the script, which the browser fetches
         * as its own request where none of these lists are in scope. The options are
         * therefore rendered here and handed over as ready-made markup. */
        $__ocp_data["gasolineTypeOptionsHtml"] = implode('', array_map(
            static fn($t) => '<option value="' . htmlspecialchars($t, ENT_QUOTES) . '">'
                . htmlspecialchars($t, ENT_QUOTES) . '</option>',
            is_array($gasolineTypes ?? null) ? $gasolineTypes : []
        ));
        $__ocp_data["supplierOptionsHtml"] = implode('', array_map(
            static fn($s) => '<option value="' . (int) $s['id'] . '">'
                . htmlspecialchars($s['supplier_name'], ENT_QUOTES) . '</option>',
            is_array($suppliers ?? null) ? $suppliers : []
        ));
        $__ocp_data["vehicleOptionsHtml"] = implode('', array_map(
            static fn($v) => '<option value="' . (int) $v['id'] . '">'
                . htmlspecialchars($v['vehicle_name'] . ' (' . $v['plate_number'] . ')', ENT_QUOTES) . '</option>',
            is_array($vehicles ?? null) ? $vehicles : []
        ));
        $__ocp_data["equipmentOptionsHtml"] = implode('', array_map(
            static fn($e) => '<option value="' . (int) $e['id'] . '">'
                . htmlspecialchars($e['equipment_name'], ENT_QUOTES) . '</option>',
            is_array($equipment ?? null) ? $equipment : []
        ));
        $__ocp_data["employeeOptionsHtml"] = implode('', array_map(
            static fn($e) => '<option value="' . (int) $e['id'] . '">'
                . htmlspecialchars(formatEmployeeNameWithPosition($e), ENT_QUOTES) . '</option>',
            is_array($employees ?? null) ? $employees : []
        ));
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = (!empty($swal_data));
        $__ocp_data["openEditPo"] = ((bool) $edit_po_data);
        ocp_page_data("gasoline_purchase_order", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/gasoline_purchase_order.js.php"></script>
    </body>
</html>
