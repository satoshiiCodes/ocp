<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'config/db_config.php';

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

// All of this page's actions live in one file. This page has no forms of its own:
// it is opened with the record to show, and without one the actions file sends the
// request back to the list.
if (!defined('OCP_VIEW_HEAVY_EQUIPMENT_ACTIONS_RAN')) {
    require __DIR__ . '/actions/view_heavy_equipment-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope.
$ocp_endpoint = require __DIR__ . '/api/view_heavy_equipment-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>View Heavy Equipment - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
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
                            <h1 class="page-title">View Heavy Equipment</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="heavy_equipment.php">Equipment</a></li>
                                <li class="breadcrumb-item active">View Equipment</li>
                            </ol>
                        </div>
                        
                        <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($equipment): ?>
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                            <div class="min-w-0 xl:col-span-8">
                                <div class="card mb-6">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Equipment Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                                            <div class="min-w-0 text-sm font-semibold text-slate-500">ID</div>
                                            <div class="min-w-0"><?php echo htmlspecialchars($equipment['id']); ?></div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                                            <div class="min-w-0 text-sm font-semibold text-slate-500">Equipment Name</div>
                                            <div class="min-w-0"><?php echo htmlspecialchars($equipment['equipment_name']); ?></div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                                            <div class="min-w-0 text-sm font-semibold text-slate-500">Fuel Type</div>
                                            <div class="min-w-0">
                                                <span class="badge badge-neutral">
                                                    <?php echo htmlspecialchars(ucfirst($equipment['fuel_type'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                                            <div class="min-w-0 text-sm font-semibold text-slate-500">Description</div>
                                            <div class="min-w-0">
                                                <?php 
                                                if (!empty($equipment['description'])) {
                                                    echo htmlspecialchars($equipment['description']);
                                                } else {
                                                    echo '<span class="text-muted">No description provided</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                                            <div class="min-w-0 text-sm font-semibold text-slate-500">Status</div>
                                            <div class="min-w-0">
                                                <?php if ($equipment['is_active']): ?>
                                                <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                <span class="badge badge-neutral">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                                            <div class="min-w-0 text-sm font-semibold text-slate-500">Created At</div>
                                            <div class="min-w-0"><?php echo date('m-d-Y g:i A', strtotime($equipment['created_at'])); ?></div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                                            <div class="min-w-0 text-sm font-semibold text-slate-500">Last Updated</div>
                                            <div class="min-w-0"><?php echo date('m-d-Y g:i A', strtotime($equipment['updated_at'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <a href="edit_equipment.php?id=<?php echo $equipment['id']; ?>" class="btn btn-warning">
                                            <i class="fas fa-edit mr-1"></i> Edit Equipment
                                        </a>
                                        <a href="heavy_equipment.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left mr-1"></i> Back to Equipment
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Fuel Records Section -->
                                <div class="card mb-6">
                                    <div class="card-header flex justify-between items-center">
                                        <h5 class="card-title mb-0">Fuel Records</h5>
                                        <span class="badge badge-neutral"><?php echo count($fuel_records); ?> records</span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($fuel_records)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped table-hover" id="fuelTable">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Gasoline Type</th>
                                                        <th>Quantity (Liters)</th>
                                                        <th>Price per Liter</th>
                                                        <th>Total</th>
                                                        <th>Driver/Operator</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($fuel_records as $record): 
                                                        // Convert null values to 0 to avoid number_format error
                                                        $quantity = floatval($record['quantity_liters'] ?? 0);
                                                        $price = floatval($record['price_per_liter'] ?? 0);
                                                        $total = $quantity * $price;
                                                        
                                                        // Determine driver name: use driver_operator if not null, otherwise use manual_driver_name
                                                        $driver_name = '';
                                                        if (!empty($record['driver_operator']) && $record['driver_operator'] !== 'NULL') {
                                                            $driver_name = htmlspecialchars($record['driver_operator']);
                                                        } elseif (!empty($record['manual_driver_name']) && $record['manual_driver_name'] !== 'NULL') {
                                                            $driver_name = htmlspecialchars($record['manual_driver_name']);
                                                        } else {
                                                            $driver_name = '<span class="text-muted">N/A</span>';
                                                        }
                                                    ?>
                                                    <tr>
                                                        <td><?php echo date('m-d-Y', strtotime($record['movement_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($record['gasoline_type']); ?></td>
                                                        <td><?php echo number_format($quantity, 2); ?></td>
                                                        <td>₱<?php echo number_format($price, 2); ?></td>
                                                        <td>₱<?php echo number_format($total, 2); ?></td>
                                                        <td><?php echo $driver_name; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <div class="text-center py-6">
                                            <i class="fas fa-gas-pump fa-2x text-muted mb-4"></i>
                                            <p class="text-muted">No fuel records found for this equipment.</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Spare Parts Replacement Section -->
                                <div class="card mb-6">
                                    <div class="card-header flex justify-between items-center">
                                        <h5 class="card-title mb-0">Spare Parts Replacement History</h5>
                                        <span class="badge badge-neutral"><?php echo count($spare_parts_records); ?> records</span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($spare_parts_records)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped table-hover" id="partsTable">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Part Name</th>
                                                        <th>Quantity</th>
                                                        <th>Unit</th>
                                                        <th>Price per Unit</th>
                                                        <th>Total</th>
                                                        <th>Maintenance Personnel</th>
                                                        <th>Purpose</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($spare_parts_records as $record): 
                                                        // Convert null values to 0 to avoid number_format error
                                                        $quantity = intval($record['quantity'] ?? 0);
                                                        $price = floatval($record['price_per_unit'] ?? 0);
                                                        $total = $quantity * $price;
                                                        
                                                        // Format the technician name as "Firstname M. Lastname Suffix"
                                                        $technician_display = '';
                                                        if (!empty($record['firstname']) && !empty($record['lastname'])) {
                                                            // Start with firstname
                                                            $technician_display = htmlspecialchars($record['firstname']);
                                                            
                                                            // Add middle initial if exists
                                                            if (!empty($record['middlename'])) {
                                                                $technician_display .= ' ' . strtoupper(substr($record['middlename'], 0, 1)) . '.';
                                                            }
                                                            
                                                            // Add lastname
                                                            $technician_display .= ' ' . htmlspecialchars($record['lastname']);
                                                            
                                                            // Add suffix if exists
                                                            if (!empty($record['suffix'])) {
                                                                $technician_display .= ' ' . htmlspecialchars($record['suffix']);
                                                            }
                                                        } elseif (!empty($record['technician'])) {
                                                            // Fallback to raw technician ID if no employee record found
                                                            $technician_display = 'ID: ' . htmlspecialchars($record['technician']);
                                                        } else {
                                                            $technician_display = '<span class="text-muted">N/A</span>';
                                                        }
                                                    ?>
                                                    <tr>
                                                        <td><?php echo date('m-d-Y', strtotime($record['movement_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($record['part_name']); ?></td>
                                                        <td><?php echo $quantity; ?></td>
                                                        <td><?php echo htmlspecialchars($record['unit_of_measure']); ?></td>
                                                        <td>₱<?php echo number_format($price, 2); ?></td>
                                                        <td>₱<?php echo number_format($total, 2); ?></td>
                                                        <td><?php echo $technician_display; ?></td>
                                                        <td><?php echo !empty($record['purpose']) ? htmlspecialchars($record['purpose']) : '<span class="text-muted">N/A</span>'; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <div class="text-center py-6">
                                            <i class="fas fa-cogs fa-2x text-muted mb-4"></i>
                                            <p class="text-muted">No spare parts replacement records found for this equipment.</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Rentals History Section -->
                                <div class="card mb-6">
                                    <div class="card-header flex justify-between items-center">
                                        <h5 class="card-title mb-0">Rentals History</h5>
                                        <span class="badge badge-neutral"><?php echo count($rental_records); ?> records</span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($rental_records)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped table-hover" id="rentalsTable">
                                                <thead>
                                                    <tr>
                                                        <th>Project Name</th>
                                                        <th>Start Date</th>
                                                        <th>End Date</th>
                                                        <th>Rate</th>
                                                        <th>Rate Type</th>
                                                        <th>Total Cost</th>
                                                        <th>Notes</th>
                                                        <th>Created At</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($rental_records as $record): 
                                                        // Convert null values to 0 to avoid number_format error
                                                        $rate = floatval($record['rate'] ?? 0);
                                                        $total_cost = floatval($record['total_cost'] ?? 0);
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($record['project_name']); ?></td>
                                                        <td>
                                                            <?php 
                                                            if ($record['rate_type'] === 'hourly') {
                                                                echo date('m-d-Y g:i A', strtotime($record['start_date']));
                                                            } else {
                                                                echo date('m-d-Y', strtotime($record['start_date']));
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            if ($record['rate_type'] === 'hourly') {
                                                                echo date('m-d-Y g:i A', strtotime($record['end_date']));
                                                            } else {
                                                                echo date('m-d-Y', strtotime($record['end_date']));
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>₱<?php echo number_format($rate, 2); ?></td>
                                                        <td><?php echo ucfirst($record['rate_type']); ?></td>
                                                        <td>₱<?php echo number_format($total_cost, 2); ?></td>
                                                        <td><?php echo !empty($record['notes']) ? htmlspecialchars($record['notes']) : '<span class="text-muted">N/A</span>'; ?></td>
                                                        <td><?php echo date('m-d-Y', strtotime($record['created_at'])); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <div class="text-center py-6">
                                            <i class="fas fa-calendar-alt fa-2x text-muted mb-4"></i>
                                            <p class="text-muted">No rental records found for this equipment.</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="min-w-0 xl:col-span-4">
                                <!-- Fuel Statistics -->
                                <div class="card mb-6">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Fuel Statistics</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        if (!empty($fuel_records)) {
                                            $total_liters = 0;
                                            $total_cost = 0;
                                            
                                            foreach ($fuel_records as $record) {
                                                $quantity = floatval($record['quantity_liters'] ?? 0);
                                                $price = floatval($record['price_per_liter'] ?? 0);
                                                
                                                $total_liters += $quantity;
                                                $total_cost += ($quantity * $price);
                                            }
                                            
                                            $avg_cost_per_liter = $total_liters > 0 ? $total_cost / $total_liters : 0;
                                        ?>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-2">
                                            <div class="min-w-0">Total Fuel:</div>
                                            <div class="min-w-0 fw-bold"><?php echo number_format($total_liters, 2); ?> L</div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-2">
                                            <div class="min-w-0">Avg. Cost/Liter:</div>
                                            <div class="min-w-0 fw-bold">₱<?php echo number_format($avg_cost_per_liter, 2); ?></div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-2">
                                            <div class="min-w-0">Total Cost:</div>
                                            <div class="min-w-0 fw-bold">₱<?php echo number_format($total_cost, 2); ?></div>
                                        </div>
                                        <?php } else { ?>
                                        <p class="text-muted text-center">No fuel data available</p>
                                        <?php } ?>
                                    </div>
                                </div>
                                
                                <!-- Spare Parts Statistics -->
                                <div class="card mb-6">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Parts Replacement Statistics</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        if (!empty($spare_parts_records)) {
                                            $total_parts = 0;
                                            $total_parts_cost = 0;
                                            
                                            foreach ($spare_parts_records as $record) {
                                                $quantity = intval($record['quantity'] ?? 0);
                                                $price = floatval($record['price_per_unit'] ?? 0);
                                                
                                                $total_parts += $quantity;
                                                $total_parts_cost += ($quantity * $price);
                                            }
                                            
                                            $avg_cost_per_part = $total_parts > 0 ? $total_parts_cost / $total_parts : 0;
                                        ?>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-2">
                                            <div class="min-w-0">Total Parts:</div>
                                            <div class="min-w-0 fw-bold"><?php echo intval($total_parts); ?></div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-2">
                                            <div class="min-w-0">Avg. Cost/Part:</div>
                                            <div class="min-w-0 fw-bold">₱<?php echo number_format($avg_cost_per_part, 2); ?></div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-2">
                                            <div class="min-w-0">Total Cost:</div>
                                            <div class="min-w-0 fw-bold">₱<?php echo number_format($total_parts_cost, 2); ?></div>
                                        </div>
                                        <?php } else { ?>
                                        <p class="text-muted text-center">No parts data available</p>
                                        <?php } ?>
                                    </div>
                                </div>
                                
                                <!-- Rental Statistics -->
                                <div class="card mb-6">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Rental Statistics</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        if (!empty($rental_records)) {
                                            $total_rentals = count($rental_records);
                                            $total_rental_income = 0;
                                            $total_days_rented = 0;
                                            $total_hours_rented = 0;
                                            
                                            foreach ($rental_records as $record) {
                                                $total_rental_income += floatval($record['total_cost'] ?? 0);
                                                
                                                // Calculate time rented
                                                $start_date = new DateTime($record['start_date']);
                                                $end_date = new DateTime($record['end_date']);
                                                
                                                if ($record['rate_type'] === 'hourly') {
                                                    $interval = $start_date->diff($end_date);
                                                    $hours_rented = $interval->h + ($interval->days * 24);
                                                    $total_hours_rented += $hours_rented;
                                                } else {
                                                    // For daily rentals, calculate days (including partial days)
                                                    $interval = $start_date->diff($end_date);
                                                    $days_rented = $interval->days + 1;
                                                    $total_days_rented += $days_rented;
                                                }
                                            }
                                        ?>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-2">
                                            <div class="min-w-0">Total Rentals:</div>
                                            <div class="min-w-0 fw-bold"><?php echo $total_rentals; ?></div>
                                        </div>
                                        <?php if ($total_days_rented > 0): ?>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-2">
                                            <div class="min-w-0">Days Rented:</div>
                                            <div class="min-w-0 fw-bold"><?php echo $total_days_rented; ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($total_hours_rented > 0): ?>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-2">
                                            <div class="min-w-0">Hours Rented:</div>
                                            <div class="min-w-0 fw-bold"><?php echo $total_hours_rented; ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-2">
                                            <div class="min-w-0">Total Income:</div>
                                            <div class="min-w-0 fw-bold">₱<?php echo number_format($total_rental_income, 2); ?></div>
                                        </div>
                                        <?php } else { ?>
                                        <p class="text-muted text-center">No rental data available</p>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="card mb-6">
                            <div class="card-body text-center py-8">
                                <i class="fas fa-truck-monster fa-3x text-muted mb-4"></i>
                                <h5>Equipment Not Found</h5>
                                <p class="text-muted">The equipment you're looking for doesn't exist or may have been removed.</p>
                                <a href="heavy_equipment.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left mr-1"></i> Back to Equipment
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="<?php echo ocp_asset('assets/js/view_heavy_equipment.js'); ?>"></script>
    </body>
</html>
