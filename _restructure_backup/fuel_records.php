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

// Check if vehicle data was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_id'])) {
    $vehicle_id = $_POST['vehicle_id'];
    
    // Get vehicle details from database
    try {
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = :id");
        $stmt->bindParam(':id', $vehicle_id);
        $stmt->execute();
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vehicle) {
            $message = 'Vehicle not found.';
            $message_type = 'danger';
        } else {
            // Get fuel records for this vehicle
            $stmt = $pdo->prepare("
                SELECT gm.*, 
                       s.name as supplier_name,
                       t_from.tank_name as transfer_from_name,
                       t_to.tank_name as transfer_to_name
                FROM gasoline_movements gm
                LEFT JOIN suppliers s ON gm.supplier_id = s.id
                LEFT JOIN gasoline_tanks t_from ON gm.transfer_from = t_from.id
                LEFT JOIN gasoline_tanks t_to ON gm.transfer_to = t_to.id
                WHERE gm.vehicle_id = :vehicle_id 
                ORDER BY gm.movement_date DESC, gm.created_at DESC
            ");
            $stmt->bindParam(':vehicle_id', $vehicle_id);
            $stmt->execute();
            $fuel_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(PDOException $e) {
        $message = 'Error fetching vehicle details: ' . $e->getMessage();
        $message_type = 'danger';
        $vehicle = null;
    }
} else {
    // If not POST, redirect back to vehicles page
    header('Location: vehicles.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>View Vehicle - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <style>
            .vehicle-details-card {
                box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
                border: 1px solid #e3e6f0;
                border-radius: 0.35rem;
            }
            .detail-label {
                font-weight: 600;
                color: #6e707e;
            }
            .status-badge {
                font-size: 0.8rem;
                padding: 0.35em 0.65em;
            }
            .badge-active {
                background-color: #198754;
            }
            .badge-inactive {
                background-color: #6c757d;
            }
            .fuel-record-row {
                transition: background-color 0.2s;
            }
            .fuel-record-row:hover {
                background-color: #f8f9fa;
            }
            .modal-lg {
                max-width: 900px;
            }
            .badge-in {
                background-color: #198754;
            }
            .badge-out {
                background-color: #dc3545;
            }
        </style>
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">View Vehicle</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="vehicles.php">Vehicles</a></li>
                            <li class="breadcrumb-item active">View Vehicle</li>
                        </ol>
                        
                        <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($vehicle): ?>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card vehicle-details-card mb-4">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="card-title mb-0">Vehicle Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">ID</div>
                                            <div class="col-sm-9"><?php echo htmlspecialchars($vehicle['id']); ?></div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">Vehicle Name</div>
                                            <div class="col-sm-9"><?php echo htmlspecialchars($vehicle['vehicle_name']); ?></div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">Plate Number</div>
                                            <div class="col-sm-9"><?php echo htmlspecialchars($vehicle['plate_number']); ?></div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">Fuel Type</div>
                                            <div class="col-sm-9">
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars(ucfirst($vehicle['fuel_type'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">Description</div>
                                            <div class="col-sm-9">
                                                <?php 
                                                if (!empty($vehicle['description'])) {
                                                    echo htmlspecialchars($vehicle['description']);
                                                } else {
                                                    echo '<span class="text-muted">No description provided</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">Status</div>
                                            <div class="col-sm-9">
                                                <?php if ($vehicle['is_active']): ?>
                                                <span class="badge status-badge badge-active">Active</span>
                                                <?php else: ?>
                                                <span class="badge status-badge badge-inactive">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">Created At</div>
                                            <div class="col-sm-9"><?php echo date('M j, Y g:i A', strtotime($vehicle['created_at'])); ?></div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">Last Updated</div>
                                            <div class="col-sm-9"><?php echo date('M j, Y g:i A', strtotime($vehicle['updated_at'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <form method="POST" action="vehicles.php" style="display: inline;">
                                            <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['id']; ?>">
                                            <button type="submit" class="btn btn-warning">
                                                <i class="fas fa-edit me-1"></i> Edit Vehicle
                                            </button>
                                        </form>
                                        <a href="vehicles.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-1"></i> Back to Vehicles
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card vehicle-details-card mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="card-title mb-0">Quick Actions</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-primary mb-2" data-bs-toggle="modal" data-bs-target="#fuelRecordsModal">
                                                <i class="fas fa-gas-pump me-1"></i> Fuel Records
                                            </button>
                                            <button class="btn btn-outline-success mb-2">
                                                <i class="fas fa-tools me-1"></i> Maintenance
                                            </button>
                                            <button class="btn btn-outline-warning mb-2">
                                                <i class="fas fa-exclamation-triangle me-1"></i> Report Issue
                                            </button>
                                            <button class="btn btn-outline-info mb-2">
                                                <i class="fas fa-history me-1"></i> Usage History
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card vehicle-details-card mb-4">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="card-title mb-0">Vehicle Status</h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-car fa-3x text-success"></i>
                                        </div>
                                        <h5 class="card-title">Operational</h5>
                                        <p class="card-text">This vehicle is currently available for use.</p>
                                        <div class="progress mb-3">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: 85%" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted">Overall condition: Good</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fuel Records Modal -->
                        <div class="modal fade" id="fuelRecordsModal" tabindex="-1" aria-labelledby="fuelRecordsModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title" id="fuelRecordsModalLabel">
                                            Fuel Records for <?php echo htmlspecialchars($vehicle['vehicle_name']); ?>
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <?php if (!empty($fuel_records)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
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
                                                    <tr class="fuel-record-row">
                                                        <td><?php echo date('M j, Y', strtotime($record['movement_date'])); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $record['movement_type'] == 'in' ? 'badge-in' : 'badge-out'; ?>">
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
                                            <i class="fas fa-gas-pump fa-3x text-muted mb-3"></i>
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
                        <div class="card mb-4">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-car fa-3x text-muted mb-3"></i>
                                <h5>Vehicle Not Found</h5>
                                <p class="text-muted">The vehicle you're looking for doesn't exist or may have been removed.</p>
                                <a href="vehicles.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Vehicles
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Simple sidebar toggle functionality
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.body.classList.toggle('sb-sidenav-toggled');
            });
            
            // Auto show modal if URL has #fuel-records hash
            document.addEventListener('DOMContentLoaded', function() {
                if (window.location.hash === '#fuel-records') {
                    var fuelModal = new bootstrap.Modal(document.getElementById('fuelRecordsModal'));
                    fuelModal.show();
                }
            });
        </script>
    </body>
</html>