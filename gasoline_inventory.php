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


// All of this page's actions live in one file: gasoline in, gasoline out, a
// transfer between tanks, setting a minimum level and an opening stock entry. The
// forms post back to this page, so it is pulled in before anything is read or
// rendered.
if (!defined('OCP_GASOLINE_INVENTORY_ACTIONS_RAN')) {
    require __DIR__ . '/actions/gasoline_inventory-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. swal_data is only taken
// when the endpoint set one, so a message from the actions file is not wiped out.
$ocp_endpoint = require __DIR__ . '/api/gasoline_inventory-endpoint.php';
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
        <title>Gasoline Inventory Management - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
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
                            <h1 class="page-title">Gasoline Inventory Management</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class='breadcrumb-item active'>Gasoline Inventory</li>
                            </ol>
                        </div>
                        
                        <!-- Low Gasoline Alerts -->
                        <?php if (!empty($low_gas_items)): ?>
                        <div class="alert alert-warning" role="alert">
                            <div class="flex-1">
                                <h5 class="font-semibold"><i class="fas fa-exclamation-triangle"></i> Low Gasoline Alert</h5>
                                <p>The following gasoline types are below their minimum levels:</p>
                                <ul class="mb-0">
                                    <?php foreach ($low_gas_items as $item): 
                                        $status = $item['quantity_liters'] <= 0 ? 'out-of-stock' : 'low-stock';
                                        $percent = ($item['quantity_liters'] / $item['capacity_liters']) * 100;
                                    ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($item['gasoline_type']); ?></strong> 
                                        in <?php echo htmlspecialchars($item['tank_name']); ?>: 
                                        <?php echo number_format($item['quantity_liters'], 2); ?>L 
                                        (Min: <?php echo $item['min_stock_liters']; ?>L, 
                                        Capacity: <?php echo number_format($item['capacity_liters'], 2); ?>L,
                                        <?php echo number_format($percent, 1); ?>% full)
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
                        
                        <!-- Quick Actions Cards -->
                        <div class="grid grid-cols-1 gap-6 mb-6 xl:grid-cols-3">
                            <!-- Gasoline In Operations -->
                            <div class="min-w-0">
                                <div class="card h-full transition-transform hover:-translate-y-1">
                                    <div class="card-body text-center">
                                        <div class="text-3xl mb-4 text-brand-600">
                                            <i class="fas fa-arrow-down"></i>
                                        </div>
                                        <h5 class="card-title">Gasoline In Operations</h5>
                                        <p class="mb-4">Manage incoming gasoline from suppliers and initial stock</p>
                                        <div class="grid grid-cols-1 gap-2 mt-4">
                                            <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#gasInModal">
                                                <i class="fas fa-truck-loading mr-1"></i> From Supplier
                                            </button>
                                            <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#initialGasModal">
                                                <i class="fas fa-gas-pump mr-1"></i> Initial Stock
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Gasoline Out Operations -->
                            <div class="min-w-0">
                                <div class="card h-full transition-transform hover:-translate-y-1">
                                    <div class="card-body text-center">
                                        <div class="text-3xl mb-4 text-warning-600">
                                            <i class="fas fa-arrow-up"></i>
                                        </div>
                                        <h5 class="card-title">Gasoline Out Operations</h5>
                                        <p class="mb-4">Issue gasoline to vehicles and equipment using FIFO</p>
                                        <div class="grid grid-cols-1 gap-2 mt-4">
                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#gasOutModal">
                                                <i class="fas fa-car mr-1"></i> To Vehicle/Heavy Equipment
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Transfer & Management -->
                            <div class="min-w-0">
                                <div class="card h-full transition-transform hover:-translate-y-1">
                                    <div class="card-body text-center">
                                        <div class="text-3xl mb-4 text-success-600">
                                            <i class="fas fa-exchange-alt"></i>
                                        </div>
                                        <h5 class="card-title">Transfer & Management</h5>
                                        <p class="mb-4">Transfer between tanks and manage inventory settings</p>
                                        <div class="grid grid-cols-1 gap-2 mt-4">
                                            <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#transferModal">
                                                <i class="fas fa-sync-alt mr-1"></i> Transfer
                                            </button>
                                            <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#minGasModal">
                                                <i class="fas fa-sliders-h mr-1"></i> Min Levels
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Gasoline Inventory Summary -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-gas-pump mr-1"></i>
                                Current Gasoline Inventory Summary
                                <button class="btn btn-sm btn-outline-primary ml-auto" data-bs-toggle="modal" data-bs-target="#minGasModal">
                                    <i class="fas fa-cog"></i> Manage Minimum Levels
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($gasoline_inventory)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="inventoryTable">
                                        <thead>
                                            <tr>
                                                <th>Gasoline Type</th>
                                                <th>Tank</th>
                                                <th>Location</th>
                                                <th>Quantity (L)</th>
                                                <th>Capacity (L)</th>
                                                <th>Fill Level</th>
                                                <th>Min Level</th>
                                                <th>Status</th>
                                                <th>Avg Price/Liter</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($gasoline_inventory as $item): 
                                                $total_value = $item['quantity_liters'] * $item['price_per_liter'];
                                                $row_class = '';
                                                $status_badge = '';
                                                $percent = $item['capacity_liters'] > 0 ? ($item['quantity_liters'] / $item['capacity_liters']) * 100 : 0;
                                                
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
                                                <td><?php echo htmlspecialchars($item['gasoline_type']); ?></td>
                                                <td><?php echo htmlspecialchars($item['tank_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['location']); ?></td>
                                                <td><?php echo number_format($item['quantity_liters'], 2); ?></td>
                                                <td><?php echo number_format($item['capacity_liters'], 2); ?></td>
                                                <td>
                                                    <div class="h-5 bg-slate-200 rounded overflow-hidden">
                                                        <div class="h-full bg-brand-600 transition-all" style="width: <?php echo $percent; ?>%"></div>
                                                    </div>
                                                    <small><?php echo number_format($percent, 1); ?>%</small>
                                                </td>
                                                <td><?php echo $item['min_stock_liters'] > 0 ? number_format($item['min_stock_liters'], 2) . 'L' : 'Not Set'; ?></td>
                                                <td><?php echo $status_badge; ?></td>
                                                <td>₱<?php echo number_format($item['price_per_liter'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No gasoline inventory data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Gasoline Batches (FIFO Tracking) -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-layer-group mr-1"></i>
                                Gasoline Batches (FIFO Tracking)
                            </div>
                            <div class="card-body">
                                <?php if (!empty($gasoline_batches)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="batchesTable">
                                        <thead>
                                            <tr>
                                                <th>Date Received</th>
                                                <th>Gasoline Type</th>
                                                <th>Tank</th>
                                                <th>Supplier</th>
                                                <th>Quantity (L)</th>
                                                <th>Price/Liter</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($gasoline_batches as $batch): 
                                                $total_value = $batch['quantity_liters'] * $batch['price_per_liter'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(ocp_date_mdy($batch['date_received'])); ?></td>
                                                <td><?php echo htmlspecialchars($batch['gasoline_type']); ?></td>
                                                <td><?php echo htmlspecialchars($batch['tank_name'] . ' - ' . $batch['location']); ?></td>
                                                <td><?php echo htmlspecialchars($batch['supplier_name'] ?? 'Initial Stock'); ?></td>
                                                <td><?php echo number_format($batch['quantity_liters'], 2); ?></td>
                                                <td>₱<?php echo number_format($batch['price_per_liter'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No gasoline batches available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Gasoline Movements -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-exchange-alt mr-1"></i>
                                Recent Gasoline Movements
                            </div>
                            <div class="card-body">
                                <?php if (!empty($gasoline_movements)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="movementsTable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Gasoline Type</th>
                                                <th>Type</th>
                                                <th>Source/Destination</th>
                                                <th>Tank</th>
                                                <th>Quantity (L)</th>
                                                <th>Price/Liter</th>
                                                <th>Total Value</th>
                                                <th>Purchase Order</th>
                                                <th>Purchase Request</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($gasoline_movements as $movement): 
                                                $movement_type = $movement['movement_type'];
                                                $type_class = $movement_type === 'in' ? 'text-success' : 'text-danger';
                                                $type_icon = $movement_type === 'in' ? 'fa-arrow-down' : 'fa-arrow-up';
                                                
                                                // Determine source/target information
                                                $source_target = '';
                                                if ($movement_type === 'in') {
                                                    if ($movement['supplier_name']) {
                                                        $source_target = 'From: ' . $movement['supplier_name'];
                                                    } elseif ($movement['transfer_from']) {
                                                        $source_target = 'Transfer From: ' . $movement['from_tank_name'];
                                                    } else {
                                                        $source_target = 'From: Initial Stock';
                                                    }
                                                } else {
                                                    if ($movement['vehicle_name']) {
                                                        $source_target = 'To Vehicle: ' . $movement['vehicle_name'] . ' (' . $movement['plate_number'] . ')';
                                                    } elseif ($movement['equipment_name']) {
                                                        $source_target = 'To Equipment: ' . $movement['equipment_name'];
                                                    } elseif ($movement['transfer_to']) {
                                                        $source_target = 'Transfer To: ' . $movement['to_tank_name'];
                                                    } else {
                                                        $source_target = 'To: Unknown';
                                                    }
                                                }
                                                
                                                $tank_info = $movement['tank_name'] ?? 'Unknown';
                                                
                                                // Use batch price if available (for out movements), otherwise movement price.
                                                // The fallback is coalesced: price_per_liter is null for some out movements,
                                                // and number_format() on null raised a deprecation that printed into the
                                                // table cell, where it was visible to the user.
                                                $price_per_liter = !empty($movement['batch_price'])
                                                    ? $movement['batch_price']
                                                    : ($movement['price_per_liter'] ?? 0);
                                                $total_value = $movement['quantity_liters'] * $price_per_liter;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(ocp_date_mdy($movement['movement_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($movement['gasoline_type']); ?></td>
                                                <td class="<?php echo $type_class; ?>">
                                                    <i class="fas <?php echo $type_icon; ?>"></i> 
                                                    <?php echo strtoupper($movement_type); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($source_target); ?></td>
                                                <td><?php echo htmlspecialchars($tank_info); ?></td>
                                                <td><?php echo number_format($movement['quantity_liters'], 2); ?></td>
                                                <td>₱<?php echo number_format($price_per_liter, 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                                <td><?php echo htmlspecialchars($movement['purchase_order'] ?? 'N/A'); ?></td> 
                                                <td><?php echo htmlspecialchars($movement['purchase_request'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No gasoline movements recorded yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Gasoline In Modal -->
        <div class="modal" id="gasInModal" tabindex="-1" aria-labelledby="gasInModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="gasInModalLabel">Gasoline In from Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="gas_in">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <select class="form-select" id="gasoline_type" name="gasoline_type" required>
                                    <option value="">Select Gasoline Type</option>
                                    <?php foreach ($gasolineTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="gasoline_type">Gasoline Type <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <select class="form-select" id="supplier_id" name="supplier_id" required>
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>">
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <select class="form-select" id="tank_id" name="tank_id" required>
                                    <option value="">Select Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>" data-capacity="<?php echo $tank['capacity_liters']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location'] . ' (' . number_format($tank['capacity_liters'], 2) . 'L)'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="tank_id">Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="quantity_liters" name="quantity_liters" step="0.01" min="0.01" required>
                                <label for="quantity_liters">Quantity (Liters) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="price_per_liter" name="price_per_liter" step="0.01" min="0" required>
                                <label for="price_per_liter">Price per Liter (₱) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="purchase_order" name="purchase_order">
                                <label for="purchase_order">Purchase Order #</label>
                            </div>
                            
                            <!-- NEW: Purchase Request Input -->
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="purchase_request" name="purchase_request">
                                <label for="purchase_request">Purchase Request #</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="date" class="form-control" id="date_received" name="date_received" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <label for="date_received">Date Received <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Record Gasoline In</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Gasoline Out Modal -->
        <div class="modal" id="gasOutModal" tabindex="-1" aria-labelledby="gasOutModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="gasOutModalLabel">Gasoline Out to Vehicle/Heavy Equipment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="gasOutForm">
                        <input type="hidden" name="action" value="gas_out">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <select class="form-select" id="out_gasoline_type" name="gasoline_type" required>
                                    <option value="">Select Gasoline Type</option>
                                    <?php foreach ($gasolineTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="out_gasoline_type">Gasoline Type <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <select class="form-select" id="out_tank_id" name="tank_id" required>
                                    <option value="">Select Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="out_tank_id">From Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-6 mb-4 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-select" id="vehicle_id" name="vehicle_id">
                                            <option value="">Select Vehicle (Optional)</option>
                                            <?php foreach ($vehicles as $vehicle): ?>
                                            <option value="<?php echo $vehicle['id']; ?>">
                                                <?php echo htmlspecialchars($vehicle['vehicle_name'] . ' (' . $vehicle['plate_number'] . ')'); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="vehicle_id">Vehicle</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-select" id="equipment_id" name="equipment_id">
                                            <option value="">Select Equipment (Optional)</option>
                                            <?php foreach ($equipment as $eq): ?>
                                            <option value="<?php echo $eq['id']; ?>">
                                                <?php echo htmlspecialchars($eq['equipment_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="equipment_id">Equipment</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="driver_operator" name="driver_operator" required>
                                <label for="driver_operator">Driver/Operator Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="purpose" name="purpose" required>
                                <label for="purpose">Purpose <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="out_quantity_liters" name="quantity_liters" step="0.01" min="0.01" required>
                                <label for="out_quantity_liters">Quantity (Liters) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="odometer_reading" name="odometer_reading">
                                <label for="odometer_reading">Odometer Reading (Optional)</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="date" class="form-control" id="date_issued" name="date_issued" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <label for="date_issued">Date Issued <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Record Gasoline Out</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Transfer Modal -->
        <div class="modal" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="transferModalLabel">Transfer Between Tanks</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="gas_transfer">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <select class="form-select" id="transfer_gasoline_type" name="gasoline_type" required>
                                    <option value="">Select Gasoline Type</option>
                                    <?php foreach ($gasolineTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="transfer_gasoline_type">Gasoline Type <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <select class="form-select" id="from_tank_id" name="from_tank_id" required>
                                    <option value="">Select Source Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for='from_tank_id'>From Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <select class="form-select" id="to_tank_id" name="to_tank_id" required>
                                    <option value="">Select Destination Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="to_tank_id">To Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="transfer_quantity_liters" name="quantity_liters" step="0.01" min="0.01" required>
                                <label for="transfer_quantity_liters">Quantity (Liters) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="reason" name="reason">
                                <label for="reason">Reason for Transfer (Optional)</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="date" class="form-control" id="transfer_date" name="transfer_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <label for="transfer_date">Transfer Date <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Transfer Gasoline</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Set Minimum Gasoline Level Modal -->
        <div class="modal" id="minGasModal" tabindex="-1" aria-labelledby="minGasModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="minGasModalLabel">Set Minimum Gasoline Level</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="set_min_gas">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <select class="form-select" id="min_gas_tank_id" name="tank_id" required>
                                    <option value="">Select Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="min_gas_tank_id">Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <select class="form-select" id="min_gas_type" name="gasoline_type" required>
                                    <option value="">Select Gasoline Type</option>
                                    <?php foreach ($gasolineTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="min_gas_type">Gasoline Type <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="min_stock_liters" name="min_stock_liters" step="0.01" min="0" required>
                                <label for="min_stock_liters">Minimum Stock Level (Liters) <span class="text-danger">*</span></label>
                                <div class="form-text ml-2">Set to 0 to disable low stock alerts for this gasoline type in this tank</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Minimum Level</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Initial Gasoline Modal -->
        <div class="modal" id="initialGasModal" tabindex="-1" aria-labelledby="initialGasModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="initialGasModalLabel">Add Initial Gasoline</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="initial_gas">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <select class="form-select" id="initial_gasoline_type" name="gasoline_type" required>
                                    <option value="">Select Gasoline Type</option>
                                    <?php foreach ($gasolineTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="initial_gasoline_type">Gasoline Type <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <select class="form-select" id="initial_tank_id" name="tank_id" required>
                                    <option value="">Select Tank</option>
                                    <?php foreach ($tanks as $tank): ?>
                                    <option value="<?php echo $tank['id']; ?>">
                                        <?php echo htmlspecialchars($tank['tank_name'] . ' - ' . $tank['location']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="initial_tank_id">Tank <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="initial_quantity_liters" name="quantity_liters" step="0.01" min="0.01" required>
                                <label for="initial_quantity_liters">Quantity (Liters) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="number" class="form-control" id="initial_price_per_liter" name="price_per_liter" step="0.01" min="0" required>
                                <label for="initial_price_per_liter">Price per Liter (₱) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="date" class="form-control" id="date_added" name="date_added" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <label for="date_added">Date Added <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Initial Gasoline</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <?php
        /* Data island consumed by assets/js/gasoline_inventory.js. */
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
        ocp_page_data("gasoline_inventory", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/gasoline_inventory.js.php"></script>
    </body>
</html>
