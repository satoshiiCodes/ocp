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

// Initialize variables
$message = '';
$message_type = '';
$equipment = null;
$fuel_records = [];
$spare_parts_records = [];
$rental_records = [];

// Check if equipment data was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['equipment_id'])) {
    $equipment_id = $_POST['equipment_id'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $equipment_id = $_GET['id'];
} else {
    // If no equipment ID, redirect back to equipment page
    header('Location: heavy_equipment.php');
    exit();
}

// Get equipment details from database
try {
    $stmt = $pdo->prepare("SELECT * FROM equipment WHERE id = :id");
    $stmt->bindParam(':id', $equipment_id);
    $stmt->execute();
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$equipment) {
        $message = 'Equipment not found.';
        $message_type = 'danger';
    } else {
        // Get fuel records for this equipment
        $stmt = $pdo->prepare("
            SELECT gasoline_type, quantity_liters, price_per_liter, movement_date, driver_operator, manual_driver_name 
            FROM gasoline_movements 
            WHERE equipment_id = :equipment_id 
            ORDER BY movement_date DESC, created_at DESC
        ");
        $stmt->bindParam(':equipment_id', $equipment_id);
        $stmt->execute();
        $fuel_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get spare parts replacement records for this equipment with employee details
        $stmt = $pdo->prepare("
            SELECT 
                sp.part_name,
                sp.unit_of_measure,
                spm.quantity,
                spm.price_per_unit,
                spm.movement_date,
                spm.technician,
                spm.purpose,
                e.firstname,
                e.middlename,
                e.lastname,
                e.suffix
            FROM spare_parts_movements spm
            INNER JOIN spare_parts sp ON spm.part_id = sp.id
            LEFT JOIN employee e ON spm.technician = e.id
            WHERE spm.equipment_id = :equipment_id 
            AND spm.movement_type = 'out'
            ORDER BY spm.movement_date DESC, spm.created_at DESC
        ");
        $stmt->bindParam(':equipment_id', $equipment_id);
        $stmt->execute();
        $spare_parts_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get rental records for this equipment
        $stmt = $pdo->prepare("
            SELECT 
                pr.id,
                p.project_name,
                pr.start_date,
                pr.end_date,
                pr.rate,
                pr.rate_type,
                pr.total_cost,
                pr.notes,
                pr.created_at
            FROM project_rentals pr
            INNER JOIN projects p ON pr.project_id = p.id
            WHERE pr.equipment_id = :equipment_id
            ORDER BY pr.start_date DESC, pr.created_at DESC
        ");
        $stmt->bindParam(':equipment_id', $equipment_id);
        $stmt->execute();
        $rental_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $message = 'Error fetching equipment details: ' . $e->getMessage();
    $message_type = 'danger';
    $equipment = null;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>View Heavy Equipment - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <style>
            .equipment-details-card {
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
            .fuel-table th,
            .parts-table th,
            .rentals-table th {
                background-color: #f8f9fc;
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
                        <h1 class="mt-4">View Heavy Equipment</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="heavy_equipment.php">Equipment</a></li>
                            <li class="breadcrumb-item active">View Equipment</li>
                        </ol>
                        
                        <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($equipment): ?>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card equipment-details-card mb-4">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="card-title mb-0">Equipment Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">ID</div>
                                            <div class="col-sm-9"><?php echo htmlspecialchars($equipment['id']); ?></div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">Equipment Name</div>
                                            <div class="col-sm-9"><?php echo htmlspecialchars($equipment['equipment_name']); ?></div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">Fuel Type</div>
                                            <div class="col-sm-9">
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars(ucfirst($equipment['fuel_type'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">Description</div>
                                            <div class="col-sm-9">
                                                <?php 
                                                if (!empty($equipment['description'])) {
                                                    echo htmlspecialchars($equipment['description']);
                                                } else {
                                                    echo '<span class="text-muted">No description provided</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">Status</div>
                                            <div class="col-sm-9">
                                                <?php if ($equipment['is_active']): ?>
                                                <span class="badge status-badge badge-active">Active</span>
                                                <?php else: ?>
                                                <span class="badge status-badge badge-inactive">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">Created At</div>
                                            <div class="col-sm-9"><?php echo date('M j, Y g:i A', strtotime($equipment['created_at'])); ?></div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-sm-3 detail-label">Last Updated</div>
                                            <div class="col-sm-9"><?php echo date('M j, Y g:i A', strtotime($equipment['updated_at'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <a href="edit_equipment.php?id=<?php echo $equipment['id']; ?>" class="btn btn-warning">
                                            <i class="fas fa-edit me-1"></i> Edit Equipment
                                        </a>
                                        <a href="heavy_equipment.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-1"></i> Back to Equipment
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Fuel Records Section -->
                                <div class="card equipment-details-card mb-4">
                                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Fuel Records</h5>
                                        <span class="badge bg-light text-dark"><?php echo count($fuel_records); ?> records</span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($fuel_records)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped table-hover fuel-table" id="fuelTable">
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
                                                        <td><?php echo date('M j, Y', strtotime($record['movement_date'])); ?></td>
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
                                        <div class="text-center py-4">
                                            <i class="fas fa-gas-pump fa-2x text-muted mb-3"></i>
                                            <p class="text-muted">No fuel records found for this equipment.</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Spare Parts Replacement Section -->
                                <div class="card equipment-details-card mb-4">
                                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Spare Parts Replacement History</h5>
                                        <span class="badge bg-light text-dark"><?php echo count($spare_parts_records); ?> records</span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($spare_parts_records)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped table-hover parts-table" id="partsTable">
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
                                                        <td><?php echo date('M j, Y', strtotime($record['movement_date'])); ?></td>
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
                                        <div class="text-center py-4">
                                            <i class="fas fa-cogs fa-2x text-muted mb-3"></i>
                                            <p class="text-muted">No spare parts replacement records found for this equipment.</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Rentals History Section -->
                                <div class="card equipment-details-card mb-4">
                                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Rentals History</h5>
                                        <span class="badge bg-light text-dark"><?php echo count($rental_records); ?> records</span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($rental_records)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped table-hover rentals-table" id="rentalsTable">
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
                                                                echo date('M j, Y g:i A', strtotime($record['start_date']));
                                                            } else {
                                                                echo date('M j, Y', strtotime($record['start_date']));
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            if ($record['rate_type'] === 'hourly') {
                                                                echo date('M j, Y g:i A', strtotime($record['end_date']));
                                                            } else {
                                                                echo date('M j, Y', strtotime($record['end_date']));
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>₱<?php echo number_format($rate, 2); ?></td>
                                                        <td><?php echo ucfirst($record['rate_type']); ?></td>
                                                        <td>₱<?php echo number_format($total_cost, 2); ?></td>
                                                        <td><?php echo !empty($record['notes']) ? htmlspecialchars($record['notes']) : '<span class="text-muted">N/A</span>'; ?></td>
                                                        <td><?php echo date('M j, Y', strtotime($record['created_at'])); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-calendar-alt fa-2x text-muted mb-3"></i>
                                            <p class="text-muted">No rental records found for this equipment.</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <!-- Fuel Statistics -->
                                <div class="card equipment-details-card mb-4">
                                    <div class="card-header bg-info text-white">
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
                                        <div class="row mb-2">
                                            <div class="col-8">Total Fuel:</div>
                                            <div class="col-4 fw-bold"><?php echo number_format($total_liters, 2); ?> L</div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-8">Avg. Cost/Liter:</div>
                                            <div class="col-4 fw-bold">₱<?php echo number_format($avg_cost_per_liter, 2); ?></div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-8">Total Cost:</div>
                                            <div class="col-4 fw-bold">₱<?php echo number_format($total_cost, 2); ?></div>
                                        </div>
                                        <?php } else { ?>
                                        <p class="text-muted text-center">No fuel data available</p>
                                        <?php } ?>
                                    </div>
                                </div>
                                
                                <!-- Spare Parts Statistics -->
                                <div class="card equipment-details-card mb-4">
                                    <div class="card-header bg-secondary text-white">
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
                                        <div class="row mb-2">
                                            <div class="col-8">Total Parts:</div>
                                            <div class="col-4 fw-bold"><?php echo intval($total_parts); ?></div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-8">Avg. Cost/Part:</div>
                                            <div class="col-4 fw-bold">₱<?php echo number_format($avg_cost_per_part, 2); ?></div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-8">Total Cost:</div>
                                            <div class="col-4 fw-bold">₱<?php echo number_format($total_parts_cost, 2); ?></div>
                                        </div>
                                        <?php } else { ?>
                                        <p class="text-muted text-center">No parts data available</p>
                                        <?php } ?>
                                    </div>
                                </div>
                                
                                <!-- Rental Statistics -->
                                <div class="card equipment-details-card mb-4">
                                    <div class="card-header bg-warning text-dark">
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
                                        <div class="row mb-2">
                                            <div class="col-8">Total Rentals:</div>
                                            <div class="col-4 fw-bold"><?php echo $total_rentals; ?></div>
                                        </div>
                                        <?php if ($total_days_rented > 0): ?>
                                        <div class="row mb-2">
                                            <div class="col-8">Days Rented:</div>
                                            <div class="col-4 fw-bold"><?php echo $total_days_rented; ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($total_hours_rented > 0): ?>
                                        <div class="row mb-2">
                                            <div class="col-8">Hours Rented:</div>
                                            <div class="col-4 fw-bold"><?php echo $total_hours_rented; ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="row mb-2">
                                            <div class="col-8">Total Income:</div>
                                            <div class="col-4 fw-bold">₱<?php echo number_format($total_rental_income, 2); ?></div>
                                        </div>
                                        <?php } else { ?>
                                        <p class="text-muted text-center">No rental data available</p>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="card mb-4">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-truck-monster fa-3x text-muted mb-3"></i>
                                <h5>Equipment Not Found</h5>
                                <p class="text-muted">The equipment you're looking for doesn't exist or may have been removed.</p>
                                <a href="heavy_equipment.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Equipment
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
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script>
            // Simple sidebar toggle functionality
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.body.classList.toggle('sb-sidenav-toggled');
            });
            
            window.addEventListener('DOMContentLoaded', event => {
                const fuelTable = document.getElementById('fuelTable');
                if (fuelTable) {
                    new simpleDatatables.DataTable(fuelTable, {
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
                
                const partsTable = document.getElementById('partsTable');
                if (partsTable) {
                    new simpleDatatables.DataTable(partsTable, {
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
                
                const rentalsTable = document.getElementById('rentalsTable');
                if (rentalsTable) {
                    new simpleDatatables.DataTable(rentalsTable, {
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
            });
        </script>
    </body>
</html>