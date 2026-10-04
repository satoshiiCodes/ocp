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
// All of this page's actions live in one file: deleting a movement, the stock
// movements themselves, and editing one. The forms post back to this page, so it
// is pulled in before anything is read or rendered.
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

if (!defined('OCP_INVENTORY_ACTIONS_RAN')) {
    require __DIR__ . '/actions/inventory-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. The same file answers
// the page's JavaScript when it asks for one movement by ?id=, and it carries the
// shared helpers. swal_data is only taken when the endpoint set one, so a message
// from the actions file is not wiped out.
$ocp_endpoint = require __DIR__ . '/api/inventory-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    if ($ocp_key === 'swal_data' && empty($ocp_value)) {
        continue;
    }
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Inventory Management - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                            <h1 class="page-title">Warehouse Inventory Management</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Inventory</li>
                            </ol>
                        </div>
                        
                        <!-- Low Stock Alerts -->
                        <?php if (!empty($low_stock_items)): ?>
                        <div class="alert alert-warning" role="alert">
                            <div class="flex-1">
                                <h5 class="font-semibold mb-1"><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h5>
                                <p>The following items are below their minimum stock levels:</p>
                                <ul class="mb-0">
                                    <?php foreach ($low_stock_items as $item): 
                                        $status = $item['quantity'] <= 0 ? 'out-of-stock' : 'low-stock';
                                    ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($item['item_code'] . ' - ' . $item['item_name']); ?></strong> 
                                        at <?php echo htmlspecialchars($item['warehouse_name']); ?>: 
                                        <?php echo $item['quantity']; ?> in stock 
                                        (Min: <?php echo $item['min_stock_level']; ?>)
                                        <span class="badge badge-<?php echo $status === 'out-of-stock' ? 'danger' : 'warning'; ?>">
                                            <?php echo $status === 'out-of-stock' ? 'Out of Stock' : 'Low Stock'; ?>
                                        </span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Quick Actions Cards. Two, so the row is two columns. The Stock Out
                             card was removed: the subcon entry was its only action, and once that
                             was gone it was an icon and a heading with nothing to do. -->
                        <div class="grid grid-cols-1 gap-6 mb-6 xl:grid-cols-2">
                            <!-- Stock In Actions -->
                            <div class="min-w-0">
                                <div class="card bg-brand-600! text-white h-full transition-transform hover:-translate-y-1">
                                    <div class="card-body text-center">
                                        <div class="mb-4 text-3xl">
                                            <i class="fas fa-arrow-down"></i>
                                        </div>
                                        <h5 class="card-title text-white!">Stock In Operations</h5>
                                        <p>Manage incoming inventory</p>
                                        <div class="mt-4 flex justify-center gap-2">
                                            <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#initialStockModal">
                                                <i class="fas fa-boxes mr-1"></i> Initial Stock
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Transfer & Management Actions -->
                            <div class="min-w-0">
                                <div class="card bg-success-600! text-white h-full transition-transform hover:-translate-y-1">
                                    <div class="card-body text-center">
                                        <div class="mb-4 text-3xl">
                                            <i class="fas fa-exchange-alt"></i>
                                        </div>
                                        <h5 class="card-title text-white!">Transfer & Management</h5>
                                        <p>Transfer between warehouses</p>
                                        <div class="mt-4 flex justify-center gap-2">
                                            <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#transferModal">
                                                <i class="fas fa-warehouse mr-1"></i> Transfer Stock
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Inventory Summary -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    Current Inventory Summary (From Inventory Table)
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="ocp-table-search ml-auto max-w-[400px]">
                                    <input type="text" class="form-control" id="inventorySearch" placeholder="Search inventory...">
                                </div>
                                <?php if (!empty($inventory)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="inventoryTable">
                                        <thead>
                                            <tr>
                                                <th>Item Code</th>
                                                <th>Item Name</th>
                                                <th>Warehouse</th>
                                                <th>Location</th>
                                                <th>Quantity</th>
                                                <th>Min Stock</th>
                                                <th>Status</th>
                                                <th>Avg Unit Cost</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($inventory as $item): 
                                                $total_value = $item['quantity'] * $item['unit_cost'];
                                                $row_class = '';
                                                $status_badge = '';
                                                
                                                if ($item['stock_status'] === 'out-of-stock') {
                                                    $row_class = 'bg-danger-100!';
                                                    $status_badge = '<span class="badge badge-danger">Out of Stock</span>';
                                                } elseif ($item['stock_status'] === 'low-stock') {
                                                    $row_class = 'bg-warning-100!';
                                                    $status_badge = '<span class="badge badge-warning">Low Stock</span>';
                                                } else {
                                                    $status_badge = '<span class="badge badge-success">Normal</span>';
                                                }
                                            ?>
                                            <tr class="<?php echo $row_class; ?>">
                                                <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['warehouse_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['location']); ?></td>
                                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                                <td><?php echo $item['min_stock_level'] > 0 ? $item['min_stock_level'] : 'Not Set'; ?></td>
                                                <td><?php echo $status_badge; ?></td>
                                                <td>₱<?php echo number_format($item['unit_cost'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No inventory data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Inventory Batches (FIFO Tracking) -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <div>
                                    <i class="fas fa-layer-group mr-1"></i>
                                    Inventory Batches (FIFO Tracking)
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="ocp-table-search ml-auto max-w-[400px]">
                                    <input type="text" class="form-control" id="batchesSearch" placeholder="Search batches...">
                                </div>
                                <?php if (!empty($inventory_batches)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="batchesTable">
                                        <thead>
                                            <tr>
                                                <th>Date Received</th>
                                                <th>Item Code</th>
                                                <th>Item Name</th>
                                                <th>Warehouse</th>
                                                <th>Location</th>
                                                <th>Quantity</th>
                                                <th>Unit Cost</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($inventory_batches as $batch): 
                                                $total_value = $batch['quantity'] * $batch['unit_cost'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(ocp_date_mdy($batch['received_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($batch['item_code']); ?></td>
                                                <td><?php echo htmlspecialchars($batch['item_name']); ?></td>
                                                <td><?php echo htmlspecialchars($batch['warehouse_name']); ?></td>
                                                <td><?php echo htmlspecialchars($batch['location']); ?></td>
                                                <td><?php echo number_format($batch['quantity'], 2); ?></td>
                                                <td>₱<?php echo number_format($batch['unit_cost'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No inventory batches available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Stock Movements -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    Recent Stock Movements
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="ocp-table-search ml-auto max-w-[400px]">
                                    <input type="text" class="form-control" id="movementsSearch" placeholder="Search movements...">
                                </div>
                                <?php if (!empty($movements)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="movementsTable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Item</th>
                                                <th>Type</th>
                                                <th>Supplier/Project/Subcon</th>
                                                <th>Warehouse</th>
                                                <th>Quantity</th>
                                                <th>Unit Cost</th>
                                                <th>Total Value</th>
                                                <th>Purchase Order</th>
                                                <th>Purchase Request</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($movements as $movement): 
                                                $movement_type = $movement['movement_type'];
                                                $type_class = $movement_type === 'in' ? 'text-success' : 'text-danger';
                                                $type_icon = $movement_type === 'in' ? 'fa-arrow-down' : 'fa-arrow-up';
                                                
                                                // Determine source/target information
                                                $source_target = '';
                                                if ($movement_type === 'in') {
                                                    if ($movement['supplier_name']) {
                                                        $source_target = 'From: ' . $movement['supplier_name'];
                                                    } elseif ($movement['transfer_from']) {
                                                        $source_target = 'Transfer From: ' . $movement['from_warehouse_name'];
                                                    } else {
                                                        $source_target = 'From: Initial Stock';
                                                    }
                                                } else {
                                                    if ($movement['project_name'] && $movement['subcon_name']) {
                                                        $source_target = 'To: ' . $movement['project_name'] . ' (Subcon: ' . $movement['subcon_name'] . ')';
                                                    } elseif ($movement['project_name']) {
                                                        $source_target = 'To: ' . $movement['project_name'];
                                                    } elseif ($movement['subcon_name']) {
                                                        $source_target = 'To: Subcon: ' . $movement['subcon_name'];
                                                    } elseif ($movement['transfer_to']) {
                                                        $source_target = 'Transfer To: ' . $movement['to_warehouse_name'];
                                                    } else {
                                                        $source_target = 'To: Unknown';
                                                    }
                                                }
                                                
                                                // Determine warehouse information
                                                $warehouse_info = '';
                                                if ($movement['transfer_from'] || $movement['transfer_to']) {
                                                    // This is a transfer operation
                                                    if ($movement_type === 'in') {
                                                        // For incoming transfers, show the destination warehouse with "To: " prefix
                                                        $warehouse_info = 'To: ' . ($movement['warehouse_name'] ?? 'Unknown');
                                                    } else {
                                                        // For outgoing transfers, show the source warehouse with "From: " prefix
                                                        $warehouse_info = 'From: ' . ($movement['warehouse_name'] ?? 'Unknown');
                                                    }
                                                } else {
                                                    // Regular stock in/out operations
                                                    if ($movement_type === 'in') {
                                                        $warehouse_info = 'To: ' . ($movement['warehouse_name'] ?? 'Unknown');
                                                    } else {
                                                        $warehouse_info = 'From: ' . ($movement['warehouse_name'] ?? 'Unknown');
                                                    }
                                                }
                                                
                                                $total_value = $movement['quantity'] * $movement['unit_cost'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(ocp_date_mdy($movement['movement_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($movement['item_code'] . ' - ' . $movement['item_name']); ?></td>
                                                <td class="<?php echo $type_class; ?>">
                                                    <i class="fas <?php echo $type_icon; ?>"></i> 
                                                    <?php echo strtoupper($movement_type); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($source_target); ?></td>
                                                <td><?php echo htmlspecialchars($warehouse_info); ?></td>
                                                <td><?php echo htmlspecialchars($movement['quantity']); ?></td>
                                                <td>₱<?php echo number_format($movement['unit_cost'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                                <td><?php echo htmlspecialchars($movement['purchase_order'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($movement['purchase_request'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <div class="inline-flex gap-2" role="group">
                                                        <button class="btn btn-sm bg-info-600 text-white view-movement" data-id="<?php echo $movement['id']; ?>" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-warning edit-movement" data-id="<?php echo $movement['id']; ?>" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger delete-movement" data-id="<?php echo $movement['id']; ?>" data-description="<?php echo htmlspecialchars($movement['item_code'] . ' - ' . $movement['item_name'] . ' (' . $movement['movement_date'] . ')'); ?>" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No stock movements recorded yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Initial Stock Modal -->
        <div class="modal fade" id="initialStockModal" tabindex="-1" aria-labelledby="initialStockModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="initialStockModalLabel">Add Initial Stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="initial_stock">
                        <div class="modal-body">
                            <div class="mb-4">
                                <label for="initial_item_id" class="form-label">Item <span class="text-danger">*</span></label>
                                <select class="form-select select2-search" id="initial_item_id" name="item_id" required style="width: 100%;">
                                    <option value="">Search for an item...</option>
                                    <?php foreach ($items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['item_code'] . ' - ' . $item['item_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="initial_warehouse_id" class="form-label">Warehouse <span class="text-danger">*</span></label>
                                <select class="form-select" id="initial_warehouse_id" name="warehouse_id" required>
                                    <option value="">Select Warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo $warehouse['id']; ?>">
                                        <?php echo htmlspecialchars($warehouse['warehouse_name'] . ' - ' . $warehouse['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="initial_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="initial_quantity" name="quantity" min="1" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="initial_unit_cost" class="form-label">Unit Cost (₱) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="initial_unit_cost" name="unit_cost" step="0.01" min="0" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="date_added" class="form-label">Date Added <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_added" name="date_added" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Initial Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Stock Out to Project with Subcon Modal -->
        <div class="modal fade" id="stockOutSubconModal" tabindex="-1" aria-labelledby="stockOutSubconModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="stockOutSubconModalLabel">Stock Out to Project with Subcon (FIFO)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="stock_out_subcon">
                        <div class="modal-body">
                            <div class="mb-4">
                                <label for="subcon_item_id" class="form-label">Item <span class="text-danger">*</span></label>
                                <select class="form-select select2-search" id="subcon_item_id" name="item_id" required style="width: 100%;">
                                    <option value="">Search for an item...</option>
                                    <?php foreach ($items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['item_code'] . ' - ' . $item['item_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="subcon_project_id" class="form-label">Project <span class="text-danger">*</span></label>
                                <select class="form-select" id="subcon_project_id" name="project_id" required>
                                    <option value="">Select Project</option>
                                    <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" data-threshold="<?php echo $project['threshold_amount']; ?>">
                                        <?php echo htmlspecialchars($project['project_name'] . ' (Threshold: ₱' . number_format($project['threshold_amount'], 2) . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="subcon_id" class="form-label">Subcontractor <span class="text-danger">*</span></label>
                                <select class="form-select" id="subcon_id" name="subcon_id" required>
                                    <option value="">Select Subcontractor</option>
                                    <?php foreach ($subcons as $subcon): ?>
                                    <option value="<?php echo $subcon['id']; ?>">
                                        <?php echo htmlspecialchars($subcon['subcon_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="subcon_warehouse_id" class="form-label">From Warehouse <span class="text-danger">*</span></label>
                                <select class="form-select" id="subcon_warehouse_id" name="warehouse_id" required>
                                    <option value="">Select Warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo $warehouse['id']; ?>">
                                        <?php echo htmlspecialchars($warehouse['warehouse_name'] . ' - ' . $warehouse['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="subcon_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="subcon_quantity" name="quantity" min="1" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="subcon_date_issued" class="form-label">Date Issued <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="subcon_date_issued" name="date_issued" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> The total cost will be deducted from the project's threshold amount.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Record Stock Out to Subcon</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Transfer Modal -->
        <div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="transferModalLabel">Transfer Between Warehouses (FIFO)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="transfer">
                        <div class="modal-body">
                            <div class="mb-4">
                                <label for="transfer_item_id" class="form-label">Item <span class="text-danger">*</span></label>
                                <select class="form-select select2-search" id="transfer_item_id" name="item_id" required style="width: 100%;">
                                    <option value="">Search for an item...</option>
                                    <?php foreach ($items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['item_code'] . ' - ' . $item['item_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="from_warehouse_id" class="form-label">From Warehouse <span class="text-danger">*</span></label>
                                <select class="form-select" id="from_warehouse_id" name="from_warehouse_id" required>
                                    <option value="">Select Source Warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo $warehouse['id']; ?>">
                                        <?php echo htmlspecialchars($warehouse['warehouse_name'] . ' - ' . $warehouse['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="to_warehouse_id" class="form-label">To Warehouse <span class="text-danger">*</span></label>
                                <select class="form-select" id="to_warehouse_id" name="to_warehouse_id" required>
                                    <option value="">Select Destination Warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo $warehouse['id']; ?>">
                                        <?php echo htmlspecialchars($warehouse['warehouse_name'] . ' - ' . $warehouse['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="transfer_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="transfer_quantity" name="quantity" min="1" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="transfer_date" class="form-label">Transfer Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="transfer_date" name="transfer_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Transfer Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- View Movement Modal -->
        <div class="modal fade" id="viewMovementModal" tabindex="-1" aria-labelledby="viewMovementModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewMovementModalLabel">Stock Movement Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="movementDetails">
                        <!-- Details will be loaded via JavaScript -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Movement Modal -->
        <div class="modal fade" id="editMovementModal" tabindex="-1" aria-labelledby="editMovementModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editMovementModalLabel">Edit Stock Movement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editMovementForm">
                        <input type="hidden" name="edit_movement" value="1">
                        <input type="hidden" name="movement_id" id="edit_movement_id">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_item_info" readonly disabled>
                                <label for="edit_item_info">Item</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_movement_type" readonly disabled>
                                <label for="edit_movement_type">Movement Type</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_source_target" readonly disabled>
                                <label for="edit_source_target">Source/Target</label>
                            </div>
                            
                            <!-- Add Warehouse information -->
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_warehouse_info" readonly disabled>
                                <label for="edit_warehouse_info">Warehouse</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="edit_quantity" name="quantity" min="1" required>
                                <label for="edit_quantity">Quantity <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="edit_unit_cost" name="unit_cost" step="0.01" min="0" required>
                                <label for="edit_unit_cost">Unit Cost (₱) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="date" class="form-control" id="edit_movement_date" name="movement_date" required>
                                <label for="edit_movement_date">Movement Date <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_purchase_order" name="purchase_order">
                                <label for="edit_purchase_order">Purchase Order (Optional)</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_purchase_request" name="purchase_request">
                                <label for="edit_purchase_request">Purchase Request (Optional)</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="confirmEditBtn">Update Movement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Delete Movement Modal -->
        <div class="modal fade" id="deleteMovementModal" tabindex="-1" aria-labelledby="deleteMovementModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteMovementModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="delete_movement" value="1">
                        <input type="hidden" name="movement_id" id="delete_movement_id">
                        <div class="modal-body">
                            <p>Are you sure you want to delete this stock movement?</p>
                            <p><strong id="delete_movement_description"></strong></p>
                            <p class="text-danger">Warning: This action cannot be undone and will affect inventory levels.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Movement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <?php
        /* Data island consumed by assets/js/inventory.js. */
        $__ocp_data = [];
        /* swalDataTitle = $swal_data['title'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataTitle"] = $swal_data['title'];
        }
        /* swalDataText = $swal_data['text'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataText"] = $swal_data['text'];
        }
        /* swalDataIcon = $swal_data['icon'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataIcon"] = $swal_data['icon'];
        }
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = (!empty($swal_data));
        $__ocp_data["isError"] = (($swal_data['icon'] ?? '') === 'error');
        $__ocp_data["postedAction"] = ($_POST['action'] ?? '');
        $__ocp_data["postedEditMovement"] = (isset($_POST['edit_movement']) ? 1 : '');
        ocp_page_data("inventory", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/inventory.js.php"></script>
    </body>
</html>
