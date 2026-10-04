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

// All of this page's actions live in one file. This page has no forms of its
// own: it is opened by a POST carrying the vehicle to show, and without one the
// actions file sends the request back to the vehicle list.
if (!defined('OCP_FUEL_RECORDS_ACTIONS_RAN')) {
    require __DIR__ . '/actions/fuel_records-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope.
$ocp_endpoint = require __DIR__ . '/api/fuel_records-endpoint.php';
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
        <title>View Vehicle - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
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
                            <h1 class="page-title">View Vehicle</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="vehicles.php">Vehicles</a></li>
                                <li class="breadcrumb-item active">View Vehicle</li>
                            </ol>
                        </div>
                        
                        <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                            <div class="flex-1"><?php echo $message; ?></div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vehicle): ?>
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <div class="min-w-0">
                                <div class="card mb-6">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Vehicle Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="grid grid-cols-1 gap-2 mb-4 sm:grid-cols-4">
                                            <div class="min-w-0 font-semibold text-slate-600">ID</div>
                                            <div class="min-w-0 sm:col-span-3"><?php echo htmlspecialchars($vehicle['id']); ?></div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-2 mb-4 sm:grid-cols-4">
                                            <div class="min-w-0 font-semibold text-slate-600">Vehicle Name</div>
                                            <div class="min-w-0 sm:col-span-3"><?php echo htmlspecialchars($vehicle['vehicle_name']); ?></div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-2 mb-4 sm:grid-cols-4">
                                            <div class="min-w-0 font-semibold text-slate-600">Plate Number</div>
                                            <div class="min-w-0 sm:col-span-3"><?php echo htmlspecialchars($vehicle['plate_number']); ?></div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-2 mb-4 sm:grid-cols-4">
                                            <div class="min-w-0 font-semibold text-slate-600">Fuel Type</div>
                                            <div class="min-w-0 sm:col-span-3">
                                                <span class="badge badge-neutral">
                                                    <?php echo htmlspecialchars(ucfirst($vehicle['fuel_type'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-2 mb-4 sm:grid-cols-4">
                                            <div class="min-w-0 font-semibold text-slate-600">Description</div>
                                            <div class="min-w-0 sm:col-span-3">
                                                <?php 
                                                if (!empty($vehicle['description'])) {
                                                    echo htmlspecialchars($vehicle['description']);
                                                } else {
                                                    echo '<span class="text-muted">No description provided</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-2 mb-4 sm:grid-cols-4">
                                            <div class="min-w-0 font-semibold text-slate-600">Status</div>
                                            <div class="min-w-0 sm:col-span-3">
                                                <?php if ($vehicle['is_active']): ?>
                                                <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                <span class="badge badge-neutral">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-2 mb-4 sm:grid-cols-4">
                                            <div class="min-w-0 font-semibold text-slate-600">Created At</div>
                                            <div class="min-w-0 sm:col-span-3"><?php echo date('m-d-Y g:i A', strtotime($vehicle['created_at'])); ?></div>
                                        </div>
                                        <div class="grid grid-cols-1 gap-2 mb-4 sm:grid-cols-4">
                                            <div class="min-w-0 font-semibold text-slate-600">Last Updated</div>
                                            <div class="min-w-0 sm:col-span-3"><?php echo date('m-d-Y g:i A', strtotime($vehicle['updated_at'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <form method="POST" action="vehicles.php" class="inline">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['id']; ?>">
                                            <button type="submit" class="btn btn-warning">
                                                <i class="fas fa-edit mr-1"></i> Edit Vehicle
                                            </button>
                                        </form>
                                        <a href="vehicles.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left mr-1"></i> Back to Vehicles
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="card mb-6">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Quick Actions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="grid grid-cols-1 gap-2">
                                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#fuelRecordsModal">
                                                <i class="fas fa-gas-pump mr-1"></i> Fuel Records
                                            </button>
                                            <button class="btn btn-outline-secondary">
                                                <i class="fas fa-tools mr-1"></i> Maintenance
                                            </button>
                                            <button class="btn btn-outline-danger">
                                                <i class="fas fa-exclamation-triangle mr-1"></i> Report Issue
                                            </button>
                                            <button class="btn btn-outline-primary">
                                                <i class="fas fa-history mr-1"></i> Usage History
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mb-6">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Vehicle Status</h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <div class="mb-4">
                                            <i class="fas fa-car fa-3x text-success"></i>
                                        </div>
                                        <h5 class="card-title">Operational</h5>
                                        <p class="mb-4">This vehicle is currently available for use.</p>
                                        <div class="h-2 bg-slate-200 rounded-full overflow-hidden mb-4">
                                            <div class="h-full bg-success-600" role="progressbar" style="width: 85%" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted">Overall condition: Good</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fuel Records Modal -->
                        <div class="modal" id="fuelRecordsModal" tabindex="-1" aria-labelledby="fuelRecordsModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="fuelRecordsModalLabel">
                                            Fuel Records for <?php echo htmlspecialchars($vehicle['vehicle_name']); ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <?php if (!empty($fuel_records)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Type</th>
                                                        <th>Gasoline</th>
                                                        <th>Quantity (L)</th>
                                                        <th>Price/L</th>
                                                        <th>Total</th>
                                                        <th>Operator</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($fuel_records as $record): ?>
                                                    <tr>
                                                        <td><?php echo date('m-d-Y', strtotime($record['movement_date'])); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $record['movement_type'] == 'in' ? 'badge-success' : 'badge-danger'; ?>">
                                                                <?php echo strtoupper($record['movement_type']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($record['gasoline_type']); ?></td>
                                                        <td><?php echo number_format($record['quantity_liters'], 2); ?></td>
                                                        <td>₱<?php echo number_format($record['price_per_liter'], 2); ?></td>
                                                        <td>₱<?php echo number_format($record['quantity_liters'] * $record['price_per_liter'], 2); ?></td>
                                                        <td><?php echo !empty($record['driver_operator']) ? htmlspecialchars($record['driver_operator']) : 'N/A'; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-gas-pump fa-3x text-muted mb-4"></i>
                                            <h5>No Fuel Records Found</h5>
                                            <p class="text-muted">This vehicle doesn't have any fuel records yet.</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php else: ?>
                        <div class="card mb-6">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-car fa-3x text-muted mb-4"></i>
                                <h5>Vehicle Not Found</h5>
                                <p class="text-muted">The vehicle you're looking for doesn't exist or may have been removed.</p>
                                <a href="vehicles.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left mr-1"></i> Back to Vehicles
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>

        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="<?php echo ocp_asset('assets/js/fuel_records.js'); ?>"></script>
    </body>
</html>
