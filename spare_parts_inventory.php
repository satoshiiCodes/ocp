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
// $swal_data and the message variables carry what the actions file wants shown.
// They are created here, before the actions run, so the markup below always finds
// them defined.
$message = '';
$message_type = ''; // success or danger
$swal_data = []; // For SweetAlert2 data

// A message an action left behind before redirecting (the movement delete and a
// successful edit both report that way) is picked up here. The actions file runs next and
// only fills $swal_data again when it has something of its own to say, and the endpoint's
// swal_data is skipped while it is empty - so this one survives to the island below.
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

// All of this page's actions live in one file: the initial stock, and the movement
// delete and edit behind the movements table's Actions column, with their batch-number
// helper. The forms post back to this page, so it is pulled in before anything is read
// or rendered.
if (!defined('OCP_SPARE_PARTS_INVENTORY_ACTIONS_RAN')) {
    require __DIR__ . '/actions/spare_parts_inventory-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. swal_data is only taken
// when the endpoint set one, so a message from the actions file is not wiped out.
$ocp_endpoint = require __DIR__ . '/api/spare_parts_inventory-endpoint.php';
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
        <title>Motorpool Inventory Management - OCP Construction</title>
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
                            <h1 class="page-title">Motorpool Inventory Management</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active">Motorpool Inventory</li>
                            </ol>
                        </div>
                        
                        <!-- Low Parts Alerts. The bottom margin is set on this alert rather than on
                             the .alert component: that component is used by ~95 alerts across the
                             app, three of which deliberately carry mb-0, and a default margin there
                             would override them. -->
                        <?php if (!empty($low_parts_items)): ?>
                        <div class="alert alert-warning mb-6" role="alert">
                            <div class="min-w-0 flex-1">
                                <h5 class="font-semibold mb-1"><i class="fas fa-exclamation-triangle"></i> Low Parts Alert</h5>
                                <p>The following parts are below their minimum levels:</p>
                                <ul class="mb-0">
                                    <?php foreach ($low_parts_items as $item): 
                                        $status = $item['quantity'] <= 0 ? 'out-of-stock' : 'low-stock';
                                    ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($item['part_name'] ?? ''); ?></strong> 
                                        (<?php echo htmlspecialchars($item['part_number'] ?? ''); ?>): 
                                        <?php echo number_format($item['quantity'], 0); ?> 
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
                        
                        <!-- Quick Actions Cards. One card, so the row is a single column and the
                             card is centred. The empty grid child that used to sit here took the
                             first column and pushed this card off to the left. -->
                        <div class="grid grid-cols-1 gap-6 mb-6">
                            <!-- Parts In Operations -->
                            <div class="mx-auto w-full max-w-md min-w-0">
                                <div class="card action-card">
                                    <div class="card-body bg-brand-600 text-center text-white">
                                        <div class="mb-4 text-3xl">
                                            <i class="fas fa-boxes"></i>
                                        </div>
                                        <h5 class="mb-1 text-base font-semibold">Parts In Operations</h5>
                                        <p class="text-sm">Manage initial stock and inventory additions</p>
                                        <div class="mt-4 flex justify-center gap-2">
                                            <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#initialPartsModal">
                                                <i class="fas fa-boxes mr-1"></i> Initial Stock
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Parts Inventory Summary -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-cogs mr-1"></i>
                                Current Parts Inventory Summary
                            </div>
                            <div class="card-body">
                                <?php if (!empty($parts_inventory)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover inventory-table" id="inventoryTable">
                                        <thead>
                                            <tr>
                                                <th>Part Number</th>
                                                <th>Part Name</th>
                                                <th>Category</th>
                                                <th>Description</th>
                                                <th>Quantity</th>
                                                <th>Unit</th>
                                                <th>Min Level</th>
                                                <th>Status</th>
                                                <th>Avg Price/Unit</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($parts_inventory as $item): 
                                                // Quantity, average price and total value all come from the batches the stock is
                                                // held in, so the three figures on a row multiply out. A part held in no batches
                                                // falls back to its inventory row, which the endpoint has already applied.
                                                $avg_price = (float) ($item['avg_price_per_unit'] ?? $item['price_per_unit']);
                                                $total_value = (float) ($item['stock_value'] ?? 0);
                                                $row_class = '';
                                                $status_badge = '';
                                                
                                                if ($item['stock_status'] === 'out-of-stock') {
                                                    $row_class = 'stock-out';
                                                    $status_badge = '<span class="badge badge-danger">Out of Stock</span>';
                                                } elseif ($item['stock_status'] === 'low-stock') {
                                                    $row_class = 'stock-low';
                                                    $status_badge = '<span class="badge badge-warning">Low Stock</span>';
                                                } else {
                                                    $status_badge = '<span class="badge badge-success">Normal</span>';
                                                }
                                            ?>
                                            <tr class="<?php echo $row_class; ?>">
                                                <td><?php echo htmlspecialchars($item['part_number'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($item['part_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($item['category_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
                                                <td><?php echo number_format($item['quantity'], 0); ?></td>
                                                <td><?php echo htmlspecialchars($item['unit_of_measure'] ?? ''); ?></td>
                                                <td><?php echo $item['min_stock_level'] > 0 ? number_format($item['min_stock_level'], 0) : 'Not Set'; ?></td>
                                                <td><?php echo $status_badge; ?></td>
                                                <td>₱<?php echo number_format($avg_price, 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No parts inventory data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Parts Batches (FIFO Tracking) -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-layer-group mr-1"></i>
                                Parts Batches (FIFO Tracking)
                            </div>
                            <div class="card-body">
                                <?php if (!empty($parts_batches)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="batchesTable">
                                        <thead>
                                            <tr>
                                                <th>Date Received</th>
                                                <th>Part Number</th>
                                                <th>Part Name</th>
                                                <th>Supplier</th>
                                                <th>Quantity</th>
                                                <th>Price/Unit</th>
                                                <th>Total Value</th>
                                                <th>Purchase Order</th>
                                                <th>Purchase Request</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($parts_batches as $batch): 
                                                $total_value = $batch['quantity'] * $batch['price_per_unit'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(ocp_date_mdy($batch['date_received'] ?? null)); ?></td>
                                                <td><?php echo htmlspecialchars($batch['part_number'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($batch['part_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($batch['supplier_name'] ?? 'Initial Stock'); ?></td>
                                                <td><?php echo number_format($batch['quantity'], 0); ?></td>
                                                <td>₱<?php echo number_format($batch['price_per_unit'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                                <td><?php echo htmlspecialchars($batch['purchase_order'] ?? 'Initial Stock'); ?></td>
                                                <td><?php echo htmlspecialchars($batch['purchase_request'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No parts batches available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Parts Movements -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-exchange-alt mr-1"></i>
                                Recent Parts Movements
                            </div>
                            <div class="card-body">
                                <?php if (!empty($parts_movements)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover movements-table" id="movementsTable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Part</th>
                                                <th>Type</th>
                                                <th>Source/Destination</th>
                                                <th>Quantity</th>
                                                <th>Price/Unit</th>
                                                <th>Total Value</th>
                                                <th>Technician</th>
                                                <th>Purpose</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($parts_movements as $movement): 
                                                $movement_type = $movement['movement_type'];
                                                $type_class = $movement_type === 'in' ? 'text-success' : 'text-danger';
                                                $type_icon = $movement_type === 'in' ? 'fa-arrow-down' : 'fa-arrow-up';
                                                
                                                // Determine source/target information
                                                $source_target = '';
                                                if ($movement_type === 'in') {
                                                    if (!empty($movement['supplier_name'])) {
                                                        $source_target = '<strong>From:</strong> ' . htmlspecialchars($movement['supplier_name']);
                                                    } else {
                                                        $source_target = '<strong>From:</strong> Initial Stock';
                                                    }
                                                } else {
                                                    if (!empty($movement['vehicle_name'])) {
                                                        $source_target = '<strong>To Vehicle:</strong> ' . htmlspecialchars($movement['vehicle_name']) . ' (' . htmlspecialchars($movement['plate_number'] ?? '') . ')';
                                                    } elseif (!empty($movement['equipment_name'])) {
                                                        $source_target = '<strong>To Equipment:</strong> ' . htmlspecialchars($movement['equipment_name']);
                                                    } elseif (!empty($movement['employee_id'])) {
                                                        // Format employee name like "Josue B. Barangan III"
                                                        $employee_name = $movement['firstname'] ?? '';
                                                        
                                                        if (!empty($movement['middlename'])) {
                                                            $employee_name .= ' ' . substr($movement['middlename'], 0, 1) . '.';
                                                        }
                                                        
                                                        $employee_name .= ' ' . ($movement['lastname'] ?? '');
                                                        
                                                        if (!empty($movement['suffix'])) {
                                                            $employee_name .= ' ' . $movement['suffix'];
                                                        }
                                                        
                                                        $source_target = '<strong>Issued to:</strong> ' . htmlspecialchars($employee_name);
                                                    } else {
                                                        $source_target = '<strong>To:</strong> Unknown';
                                                    }
                                                }
                                                
                                                // Use batch price if available (for out movements), otherwise use movement price
                                                $price_per_unit = !empty($movement['batch_price']) ? $movement['batch_price'] : ($movement['price_per_unit'] ?? 0);
                                                $total_value = $movement['quantity'] * $price_per_unit;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(ocp_date_mdy($movement['movement_date'] ?? null)); ?></td>
                                                <td><?php echo htmlspecialchars(($movement['part_name'] ?? '') . ' (' . ($movement['part_number'] ?? '') . ')'); ?></td>
                                                <td class="<?php echo $type_class; ?>">
                                                    <i class="fas <?php echo $type_icon; ?>"></i> 
                                                    <?php echo strtoupper($movement_type); ?>
                                                </td>
                                                <td><?php echo $source_target; ?></td>
                                                <td><?php echo number_format($movement['quantity'], 0); ?></td>
                                                <td>₱<?php echo number_format($price_per_unit, 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                                <td>
                                                <?php 
                                                if (!empty($movement['technician']) && isset($movement['tech_firstname'])) {
                                                    // Format technician name like "Josue B. Barangan III"
                                                    $tech_name = $movement['tech_firstname'] ?? '';
                                                    
                                                    if (!empty($movement['tech_middlename'])) {
                                                        $tech_name .= ' ' . substr($movement['tech_middlename'], 0, 1) . '.';
                                                    }
                                                    
                                                    $tech_name .= ' ' . ($movement['tech_lastname'] ?? '');
                                                    
                                                    if (!empty($movement['tech_suffix'])) {
                                                        $tech_name .= ' ' . $movement['tech_suffix'];
                                                    }
                                                    
                                                    echo htmlspecialchars($tech_name);
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($movement['purpose'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <div class="inline-flex items-center gap-2" role="group">
                                                        <button class="btn btn-sm btn-outline-primary view-part-movement" data-id="<?php echo htmlspecialchars($movement['id']); ?>" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-warning edit-part-movement" data-id="<?php echo htmlspecialchars($movement['id']); ?>" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger delete-part-movement" data-id="<?php echo htmlspecialchars($movement['id']); ?>" data-description="<?php echo htmlspecialchars(($movement['part_name'] ?? '') . ' (' . ($movement['part_number'] ?? '') . ') - ' . ($movement['movement_date'] ?? '')); ?>" title="Delete">
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
                                <p class="text-center">No parts movements recorded yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Initial Parts Modal -->
        <div class="modal" id="initialPartsModal" tabindex="-1" aria-labelledby="initialPartsModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="initialPartsModalLabel">Add Initial Parts</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="initial_part">
                        <div class="modal-body">
                            <div class="mb-4">
                                <label for="initial_part_id" class="form-label">Part <span class="text-danger">*</span></label>
                                <select class="form-select select2-search" id="initial_part_id" name="part_id" required style="width: 100%;">
                                    <option value="">Select Part</option>
                                    <?php foreach ($parts as $part): ?>
                                    <option value="<?php echo $part['id']; ?>">
                                        <?php echo htmlspecialchars($part['part_number'] . ' - ' . $part['part_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="initial_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="initial_quantity" name="quantity" step="1" min="1" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="initial_price_per_unit" class="form-label">Price per Unit (₱) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="initial_price_per_unit" name="price_per_unit" step="0.01" min="0" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="date_added" class="form-label">Date Added <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_added" name="date_added" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Initial Parts</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Parts Movement Modal -->
        <div class="modal" id="viewPartMovementModal" tabindex="-1" aria-labelledby="viewPartMovementModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewPartMovementModalLabel">Parts Movement Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="partMovementDetails">
                        <!-- Details will be loaded via JavaScript -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Parts Movement Modal -->
        <div class="modal" id="editPartMovementModal" tabindex="-1" aria-labelledby="editPartMovementModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPartMovementModalLabel">Edit Parts Movement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editPartMovementForm">
                        <input type="hidden" name="edit_part_movement" value="1">
                        <input type="hidden" name="movement_id" id="edit_part_movement_id">
                        <div class="modal-body">
                            <div class="mb-4">
                                <label for="edit_part_info" class="form-label">Part</label>
                                <input type="text" class="form-control" id="edit_part_info" readonly disabled>
                            </div>

                            <div class="mb-4">
                                <label for="edit_part_type" class="form-label">Type</label>
                                <input type="text" class="form-control" id="edit_part_type" readonly disabled>
                            </div>

                            <div class="mb-4">
                                <label for="edit_part_source_target" class="form-label">Source/Destination</label>
                                <input type="text" class="form-control" id="edit_part_source_target" readonly disabled>
                            </div>

                            <div class="mb-4">
                                <label for="edit_part_movement_date" class="form-label">Movement Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit_part_movement_date" name="movement_date" required>
                            </div>

                            <div class="mb-4">
                                <label for="edit_part_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_part_quantity" name="quantity" step="0.01" min="0.01" required>
                            </div>

                            <div class="mb-4">
                                <label for="edit_part_price_per_unit" class="form-label">Price per Unit (₱) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_part_price_per_unit" name="price_per_unit" step="0.01" min="0" required>
                            </div>

                            <div class="mb-4">
                                <label for="edit_part_technician" class="form-label">Technician</label>
                                <select class="form-select" id="edit_part_technician" name="technician">
                                    <option value="">-- None --</option>
                                    <?php foreach ($employees as $employee):
                                        $technician_name = $employee['firstname'] ?? '';
                                        if (!empty($employee['middlename'])) {
                                            $technician_name .= ' ' . substr($employee['middlename'], 0, 1) . '.';
                                        }
                                        $technician_name .= ' ' . ($employee['lastname'] ?? '');
                                        if (!empty($employee['suffix'])) {
                                            $technician_name .= ' ' . $employee['suffix'];
                                        }
                                    ?>
                                    <option value="<?php echo htmlspecialchars($employee['id']); ?>">
                                        <?php echo htmlspecialchars(trim($technician_name)); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="edit_part_purpose" class="form-label">Purpose</label>
                                <textarea class="form-control" id="edit_part_purpose" name="purpose" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Movement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Parts Movement Modal -->
        <div class="modal" id="deletePartMovementModal" tabindex="-1" aria-labelledby="deletePartMovementModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deletePartMovementModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="deletePartMovementForm">
                        <input type="hidden" name="delete_part_movement" value="1">
                        <input type="hidden" name="movement_id" id="delete_part_movement_id">
                        <div class="modal-body">
                            <p>Are you sure you want to delete this parts movement?</p>
                            <p><strong id="delete_part_movement_description"></strong></p>
                            <p class="text-danger">Warning: This action cannot be undone.</p>
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
        /* Data island consumed by assets/js/spare_parts_inventory.js. */
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
        /* A rejected movement edit is reported in this same request, so the script can
         * put the user back where they were: the flag says the modal is to be reopened,
         * and the values are what they typed, which the actions file did not change. */
        $__ocp_data["postedEditMovement"] = (isset($_POST['edit_part_movement']) ? 1 : '');
        $__ocp_data["postedEditValues"] = (isset($_POST['edit_part_movement']) ? [
            'movement_id' => $_POST['movement_id'] ?? '',
            'movement_date' => $_POST['movement_date'] ?? '',
            'quantity' => $_POST['quantity'] ?? '',
            'price_per_unit' => $_POST['price_per_unit'] ?? '',
            'technician' => $_POST['technician'] ?? '',
            'purpose' => $_POST['purpose'] ?? '',
        ] : null);
        ocp_page_data("spare_parts_inventory", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/spare_parts_inventory.js.php"></script>
    </body>
</html>
