<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Check if project ID is provided via POST
if (!isset($_POST['id']) || empty($_POST['id'])) {
    // If not in POST, check if we have a stored project ID in session
    if (!isset($_SESSION['current_project_id'])) {
        header('Location: projects.php');
        exit();
    }
    $project_id = $_SESSION['current_project_id'];
} else {
    $project_id = $_POST['id'];
    // Store in session for subsequent requests
    $_SESSION['current_project_id'] = $project_id;
}

// Database connection
require_once 'includes/db_config.php';

// Get project details with engineers
try {
    $projectStmt = $pdo->prepare("
        SELECT p.*, GROUP_CONCAT(CONCAT(u.firstname, ' ', u.lastname) SEPARATOR ', ') as engineers 
        FROM projects p 
        LEFT JOIN project_engineers pe ON p.id = pe.project_id 
        LEFT JOIN users u ON pe.user_id = u.id 
        WHERE p.id = :id
        GROUP BY p.id
    ");
    $projectStmt->bindParam(':id', $project_id);
    $projectStmt->execute();
    
    if ($projectStmt->rowCount() === 0) {
        header('Location: projects.php');
        exit();
    }
    
    $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching project: " . $e->getMessage());
}

// Check if stock_movements table exists and get related data
$stock_movements = [];
$stock_table_exists = false;

try {
    // Check if stock_movements table exists
    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'stock_movements'");
    if ($checkTableStmt->rowCount() > 0) {
        $stock_table_exists = true;
        
        // Check if stock_movements has a project_id column
        $checkColumnStmt = $pdo->query("SHOW COLUMNS FROM stock_movements LIKE 'project_id'");
        if ($checkColumnStmt->rowCount() > 0) {
            // Get stock movements for this project with item names and codes
            $stockStmt = $pdo->prepare("
                SELECT sm.movement_date, sm.quantity, sm.unit_cost,
                       inames.item_name, inames.item_code
                FROM stock_movements sm 
                LEFT JOIN item_names inames ON sm.item_id = inames.id 
                WHERE sm.project_id = :project_id 
                ORDER BY sm.movement_date DESC
            ");
            $stockStmt->bindParam(':project_id', $project_id);
            $stockStmt->execute();
            $stock_movements = $stockStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch(PDOException $e) {
    // If there's an error, we'll just not show the stock movements
    $stock_table_exists = false;
}

// Get subcon materials data
$subcon_materials = [];
try {
    // Check if stock_movements has a subcon_id column
    $checkColumnStmt = $pdo->query("SHOW COLUMNS FROM stock_movements LIKE 'subcon_id'");
    if ($checkColumnStmt->rowCount() > 0) {
        // Get subcon materials for this project
        $subconStmt = $pdo->prepare("
            SELECT sm.movement_date, sm.quantity, sm.unit_cost,
                   inames.item_name, inames.item_code, s.subcon_name
            FROM stock_movements sm 
            LEFT JOIN item_names inames ON sm.item_id = inames.id 
            LEFT JOIN subcons s ON sm.subcon_id = s.id 
            WHERE sm.project_id = :project_id AND sm.subcon_id IS NOT NULL
            ORDER BY sm.movement_date DESC
        ");
        $subconStmt->bindParam(':project_id', $project_id);
        $subconStmt->execute();
        $subcon_materials = $subconStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    // If there's an error, we'll just not show the subcon materials
    $subcon_materials_error = "Error fetching subcon materials: " . $e->getMessage();
}

// Get eligible workers (Foreman, Skilled, Welder, Helper)
$eligible_workers = [];
try {
    $workersStmt = $pdo->prepare("
        SELECT id, employee_id, firstname, lastname, position 
        FROM employee 
        WHERE position IN ('Foreman', 'Skilled', 'Welder', 'Helper') AND status = 'active'
        ORDER BY firstname, lastname
    ");
    $workersStmt->execute();
    $eligible_workers = $workersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // If there's an error, we'll just not show the workers dropdown
    $eligible_workers_error = "Error fetching workers: " . $e->getMessage();
}

// Get current project workers
$project_workers = [];
try {
    $projectWorkersStmt = $pdo->prepare("
        SELECT e.id, e.employee_id, e.firstname, e.lastname, e.position 
        FROM project_workers pw 
        JOIN employee e ON pw.user_id = e.id 
        WHERE pw.project_id = :project_id
        ORDER BY e.firstname, e.lastname
    ");
    $projectWorkersStmt->bindParam(':project_id', $project_id);
    $projectWorkersStmt->execute();
    $project_workers = $projectWorkersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // If there's an error, we'll just not show the current workers
    $project_workers_error = "Error fetching project workers: " . $e->getMessage();
}

// Get vehicles for rental
$vehicles = [];
try {
    $vehiclesStmt = $pdo->prepare("
        SELECT id, vehicle_name, plate_number, fuel_type 
        FROM vehicles 
        WHERE is_active = 1
        ORDER BY vehicle_name
    ");
    $vehiclesStmt->execute();
    $vehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $vehicles_error = "Error fetching vehicles: " . $e->getMessage();
}

// Get equipment for rental
$equipment = [];
try {
    $equipmentStmt = $pdo->prepare("
        SELECT id, equipment_name, fuel_type 
        FROM equipment 
        WHERE is_active = 1
        ORDER BY equipment_name
    ");
    $equipmentStmt->execute();
    $equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $equipment_error = "Error fetching equipment: " . $e->getMessage();
}

// Get project rentals
$project_rentals = [];
try {
    $rentalsStmt = $pdo->prepare("
        SELECT pr.*, 
               COALESCE(v.vehicle_name, e.equipment_name) as item_name,
               COALESCE(v.plate_number, 'N/A') as plate_number
        FROM project_rentals pr
        LEFT JOIN vehicles v ON pr.vehicle_id = v.id
        LEFT JOIN equipment e ON pr.equipment_id = e.id
        WHERE pr.project_id = :project_id
        ORDER BY pr.start_date DESC
    ");
    $rentalsStmt->bindParam(':project_id', $project_id);
    $rentalsStmt->execute();
    $project_rentals = $rentalsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $rentals_error = "Error fetching rentals: " . $e->getMessage();
}

// Handle form submission to add workers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_workers'])) {
    if (isset($_POST['worker_ids']) && is_array($_POST['worker_ids'])) {
        try {
            // Prepare the insert statement
            $insertStmt = $pdo->prepare("
                INSERT INTO project_workers (project_id, user_id, assigned_date) 
                VALUES (:project_id, :user_id, NOW())
            ");
            
            // Add each selected worker
            foreach ($_POST['worker_ids'] as $worker_id) {
                // Check if this worker is already assigned to the project
                $checkStmt = $pdo->prepare("
                    SELECT id FROM project_workers 
                    WHERE project_id = :project_id AND user_id = :user_id
                ");
                $checkStmt->bindParam(':project_id', $project_id);
                $checkStmt->bindParam(':user_id', $worker_id);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() === 0) {
                    // Worker not already assigned, so add them
                    $insertStmt->bindParam(':project_id', $project_id);
                    $insertStmt->bindParam(':user_id', $worker_id);
                    $insertStmt->execute();
                }
            }
            
            // Refresh the page to show the updated worker list
            echo "<script>window.location.href = 'view_project.php';</script>";
            exit();
        } catch(PDOException $e) {
            $add_worker_error = "Error adding workers: " . $e->getMessage();
        }
    } else {
        $add_worker_error = "Please select at least one worker to add.";
    }
}

// Handle form submission to add vehicle/equipment rental
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_rental'])) {
    $rental_type = $_POST['rental_type'];
    $item_id = $_POST['item_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $rate = $_POST['rate'];
    $rate_type = $_POST['rate_type'];
    $notes = $_POST['notes'];
    
    // Calculate total cost based on rate type
    if ($rate_type === 'daily') {
        // Calculate days between start and end date
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        $days = $interval->days + 1; // Include both start and end dates
        $total_cost = $days * $rate;
    } else { // hourly
        // For hourly, we need to calculate hours between start and end datetime
        // Assuming we have datetime inputs for hourly calculation
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        $hours = $interval->h + ($interval->days * 24);
        $total_cost = $hours * $rate;
    }
    
    try {
        // Prepare the insert statement
        $insertStmt = $pdo->prepare("
            INSERT INTO project_rentals (project_id, vehicle_id, equipment_id, start_date, end_date, rate, rate_type, total_cost, notes) 
            VALUES (:project_id, :vehicle_id, :equipment_id, :start_date, :end_date, :rate, :rate_type, :total_cost, :notes)
        ");
        
        $vehicle_id = ($rental_type == 'vehicle') ? $item_id : null;
        $equipment_id = ($rental_type == 'equipment') ? $item_id : null;
        
        $insertStmt->bindParam(':project_id', $project_id);
        $insertStmt->bindParam(':vehicle_id', $vehicle_id);
        $insertStmt->bindParam(':equipment_id', $equipment_id);
        $insertStmt->bindParam(':start_date', $start_date);
        $insertStmt->bindParam(':end_date', $end_date);
        $insertStmt->bindParam(':rate', $rate);
        $insertStmt->bindParam(':rate_type', $rate_type);
        $insertStmt->bindParam(':total_cost', $total_cost);
        $insertStmt->bindParam(':notes', $notes);
        
        $insertStmt->execute();
        
        // Refresh the page to show the updated rentals list
        echo "<script>window.location.href = 'view_project.php';</script>";
        exit();
    } catch(PDOException $e) {
        $add_rental_error = "Error adding rental: " . $e->getMessage();
    }
}

// Handle removal of a worker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_worker'])) {
    $worker_id = $_POST['remove_worker'];
    
    try {
        $removeStmt = $pdo->prepare("
            DELETE FROM project_workers 
            WHERE project_id = :project_id AND user_id = :user_id
        ");
        $removeStmt->bindParam(':project_id', $project_id);
        $removeStmt->bindParam(':user_id', $worker_id);
        $removeStmt->execute();
        
        // Refresh the page to show the updated worker list
        echo "<script>window.location.href = 'view_project.php';</script>";
        exit();
    } catch(PDOException $e) {
        $remove_worker_error = "Error removing worker: " . $e->getMessage();
    }
}

// Handle removal of a rental
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_rental'])) {
    $rental_id = $_POST['remove_rental'];
    
    try {
        $removeStmt = $pdo->prepare("
            DELETE FROM project_rentals 
            WHERE id = :id AND project_id = :project_id
        ");
        $removeStmt->bindParam(':id', $rental_id);
        $removeStmt->bindParam(':project_id', $project_id);
        $removeStmt->execute();
        
        // Refresh the page to show the updated rentals list
        echo "<script>window.location.href = 'view_project.php';</script>";
        exit();
    } catch(PDOException $e) {
        $remove_rental_error = "Error removing rental: " . $e->getMessage();
    }
}

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

// Set status class for badge
$status_class = '';
switch($project['status']) {
    case 'planning': $status_class = 'bg-secondary'; break;
    case 'active': $status_class = 'bg-success'; break;
    case 'completed': $status_class = 'bg-primary'; break;
    case 'on-hold': $status_class = 'bg-warning'; break;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?php echo htmlspecialchars($project['project_name']); ?> - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Project: <?php echo htmlspecialchars($project['project_name']); ?></h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="projects.php">Projects</a></li>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($project['project_name']); ?></li>
                        </ol>
                        
                        <!-- Display any error messages -->
                        <?php if (isset($add_worker_error)): ?>
                        <div class="alert alert-danger"><?php echo $add_worker_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($remove_worker_error)): ?>
                        <div class="alert alert-danger"><?php echo $remove_worker_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($eligible_workers_error)): ?>
                        <div class="alert alert-danger"><?php echo $eligible_workers_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($project_workers_error)): ?>
                        <div class="alert alert-danger"><?php echo $project_workers_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($add_rental_error)): ?>
                        <div class="alert alert-danger"><?php echo $add_rental_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($remove_rental_error)): ?>
                        <div class="alert alert-danger"><?php echo $remove_rental_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($vehicles_error)): ?>
                        <div class="alert alert-danger"><?php echo $vehicles_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($equipment_error)): ?>
                        <div class="alert alert-danger"><?php echo $equipment_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($rentals_error)): ?>
                        <div class="alert alert-danger"><?php echo $rentals_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($subcon_materials_error)): ?>
                        <div class="alert alert-danger"><?php echo $subcon_materials_error; ?></div>
                        <?php endif; ?>
                        
                        <!-- Project Details Card -->
                        <div class="card mb-4">
                            <div class="card-header bg-info">
                                <i class="fas fa-info-circle me-1"></i>
                                Project Details
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Project Name:</strong> <?php echo htmlspecialchars($project['project_name']); ?></p>
                                        <p><strong>Project Code:</strong> <?php echo htmlspecialchars($project['project_code']); ?></p>
                                        <p><strong>Status:</strong> <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($project['status'])); ?></span></p>
                                        <p><strong>Address:</strong> <?php echo !empty($project['address']) ? nl2br(htmlspecialchars($project['address'])) : 'Not specified'; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Start Date:</strong> <?php echo !empty($project['start_date']) ? htmlspecialchars($project['start_date']) : 'Not set'; ?></p>
                                        <p><strong>End Date:</strong> <?php echo !empty($project['end_date']) ? htmlspecialchars($project['end_date']) : 'Not set'; ?></p>
                                        <p><strong>Engineer:</strong> <?php echo !empty($project['engineers']) ? htmlspecialchars($project['engineers']) : 'No engineers assigned'; ?></p>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <p><strong>Description:</strong></p>
                                        <p><?php echo !empty($project['description']) ? nl2br(htmlspecialchars($project['description'])) : 'No description provided'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Project Workers Card -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center bg-warning text-dark">
                                <div>
                                    <i class="fas fa-users me-1"></i>
                                    Project Workers
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addWorkersModal">
                                    <i class="fas fa-plus me-1"></i> Add Workers
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($project_workers)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="workersTable">
                                        <thead>
                                            <tr>
                                                <th>Employee ID</th>
                                                <th>Name</th>
                                                <th>Position</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($project_workers as $worker): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($worker['employee_id']); ?></td>
                                                <td><?php echo htmlspecialchars($worker['firstname'] . ' ' . $worker['lastname']); ?></td>
                                                <td><?php echo htmlspecialchars($worker['position']); ?></td>
                                                <td>
                                                    <form method="POST" action="view_project.php" style="display: inline;">
                                                        <input type="hidden" name="remove_worker" value="<?php echo $worker['id']; ?>">
                                                        <input type="hidden" name="id" value="<?php echo $project_id; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Are you sure you want to remove this worker from the project?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No workers assigned to this project yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Stock Movements Card (if available) -->
                        <?php if ($stock_table_exists && !empty($stock_movements)): ?>
                        <div class="card mb-4">
                            <div class="card-header  bg-success text-white">
                                <i class="fas fa-exchange-alt me-1"></i>
                                Materials Stock In
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="stockTable">
                                        <thead>
                                            <tr>
                                                <th>Movement Date</th>
                                                <th>Item</th>
                                                <th>Quantity</th>
                                                <th>Unit Cost</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stock_movements as $movement): 
                                                // Format item display with item code
                                                $item_display = htmlspecialchars($movement['item_name'] ?? 'N/A');
                                                if (!empty($movement['item_code'])) {
                                                    $item_display .= ' (' . htmlspecialchars($movement['item_code']) . ')';
                                                }
                                                
                                                // Calculate total value: quantity * unit_cost
                                                $total_value = $movement['quantity'] * $movement['unit_cost'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($movement['movement_date']); ?></td>
                                                <td><?php echo $item_display; ?></td>
                                                <td><?php echo htmlspecialchars($movement['quantity']); ?></td>
                                                <td>₱<?php echo number_format($movement['unit_cost'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php elseif ($stock_table_exists): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-exchange-alt me-1"></i>
                                Stock Movements
                            </div>
                            <div class="card-body">
                                <p class="text-center">No stock movements found for this project.</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Subcon Materials Card -->
                        <?php if (!empty($subcon_materials)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-truck-loading me-1"></i>
                                Subcon Materials
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="subconTable">
                                        <thead>
                                            <tr>
                                                <th>Movement Date</th>
                                                <th>Item</th>
                                                <th>Quantity</th>
                                                <th>Unit Cost</th>
                                                <th>Total Value</th>
                                                <th>Subcontractor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subcon_materials as $material): 
                                                // Format item display with item code
                                                $item_display = htmlspecialchars($material['item_name'] ?? 'N/A');
                                                if (!empty($material['item_code'])) {
                                                    $item_display .= ' (' . htmlspecialchars($material['item_code']) . ')';
                                                }
                                                
                                                // Calculate total value: quantity * unit_cost
                                                $total_value = $material['quantity'] * $material['unit_cost'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($material['movement_date']); ?></td>
                                                <td><?php echo $item_display; ?></td>
                                                <td><?php echo htmlspecialchars($material['quantity']); ?></td>
                                                <td>₱<?php echo number_format($material['unit_cost'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                                <td><?php echo htmlspecialchars($material['subcon_name'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Vehicle and Equipment Rental Card -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center bg-secondary text-white">
                                <div>
                                    <i class="fas fa-truck me-1"></i>
                                    Vehicle & Heavy Equipment Rental
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRentalModal">
                                    <i class="fas fa-plus me-1"></i> Add Rental
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($project_rentals)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="rentalsTable">
                                        <thead>
                                            <tr>
                                                <th>Vehicle/Heavy Equipment</th>
                                                <th>Plate Number</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Rate Type</th>
                                                <th>Rate</th>
                                                <th>Total Cost</th>
                                                <th>Notes</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($project_rentals as $rental): 
                                                // Format dates based on rate type
                                                $start_date_display = ($rental['rate_type'] == 'daily') 
                                                    ? date('M j, Y', strtotime($rental['start_date']))
                                                    : date('M j, Y g:i A', strtotime($rental['start_date']));
                                                
                                                $end_date_display = ($rental['rate_type'] == 'daily') 
                                                    ? date('M j, Y', strtotime($rental['end_date']))
                                                    : date('M j, Y g:i A', strtotime($rental['end_date']));
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($rental['item_name']); ?></td>
                                                <td><?php echo htmlspecialchars($rental['plate_number']); ?></td>
                                                <td><?php echo $start_date_display; ?></td>
                                                <td><?php echo $end_date_display; ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($rental['rate_type'])); ?></td>
                                                <td>₱<?php echo number_format($rental['rate'], 2); ?></td>
                                                <td>₱<?php echo number_format($rental['total_cost'], 2); ?></td>
                                                <td><?php echo !empty($rental['notes']) ? htmlspecialchars($rental['notes']) : 'N/A'; ?></td>
                                                <td>
                                                    <form method="POST" action="view_project.php" style="display: inline;">
                                                        <input type="hidden" name="remove_rental" value="<?php echo $rental['id']; ?>">
                                                        <input type="hidden" name="id" value="<?php echo $project_id; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Are you sure you want to remove this rental from the project?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No vehicle or equipment rentals for this project yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <form method="POST" action="projects.php" style="display: inline;">
                                <button type="submit" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Projects
                                </button>
                            </form>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>

        <!-- Add Workers Modal -->
        <div class="modal fade" id="addWorkersModal" tabindex="-1" aria-labelledby="addWorkersModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addWorkersModalLabel">Add Workers to Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="view_project.php">
                        <div class="modal-body">
                            <input type="hidden" name="id" value="<?php echo $project_id; ?>">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="workerSearch" placeholder="Search workers...">
                                <label for="workerSearch">Search Workers</label>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Select Workers (Only showing Foreman, Skilled, Welder, and Helper positions)</label>
                                <div class="worker-list-container border rounded p-2" style="max-height: 300px; overflow-y: auto;">
                                    <?php if (!empty($eligible_workers)): ?>
                                        <?php foreach ($eligible_workers as $worker): 
                                            // Check if worker is already assigned to this project
                                            $is_assigned = false;
                                            foreach ($project_workers as $project_worker) {
                                                if ($project_worker['id'] == $worker['id']) {
                                                    $is_assigned = true;
                                                    break;
                                                }
                                            }
                                            
                                            if (!$is_assigned): 
                                            $worker_display = $worker['employee_id'] . ' - ' . $worker['firstname'] . ' ' . $worker['lastname'] . ' (' . $worker['position'] . ')';
                                            ?>
                                            <div class="form-floating worker-item mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="worker_ids[]" value="<?php echo $worker['id']; ?>" id="worker_<?php echo $worker['id']; ?>">
                                                    <label class="form-check-label" for="worker_<?php echo $worker['id']; ?>">
                                                        <?php echo htmlspecialchars($worker_display); ?>
                                                    </label>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-muted">No eligible workers found</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_workers" class="btn btn-primary">Add Selected Workers</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Rental Modal -->
        <div class="modal fade" id="addRentalModal" tabindex="-1" aria-labelledby="addRentalModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addRentalModalLabel">Add Vehicle/Equipment Rental</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="view_project.php">
                        <div class="modal-body">
                            <input type="hidden" name="id" value="<?php echo $project_id; ?>">
                            <div class="mb-3">
                                <label for="rental_type" class="form-label">Rental Type</label>
                                <select class="form-select" id="rental_type" name="rental_type" required>
                                    <option value="">Select Type</option>
                                    <option value="vehicle">Vehicle</option>
                                    <option value="equipment">Equipment</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="vehicle_select_container" style="display: none;">
                                <label for="vehicle_id" class="form-label">Select Vehicle</label>
                                <select class="form-select" id="vehicle_id" name="item_id">
                                    <option value="">Select Vehicle</option>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?php echo $vehicle['id']; ?>" data-plate="<?php echo $vehicle['plate_number']; ?>">
                                        <?php echo htmlspecialchars($vehicle['vehicle_name'] . ' (' . $vehicle['plate_number'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="equipment_select_container" style="display: none;">
                                <label for="equipment_id" class="form-label">Select Equipment</label>
                                <select class="form-select" id="equipment_id" name="item_id">
                                    <option value="">Select Equipment</option>
                                    <?php foreach ($equipment as $item): ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['equipment_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="rate_type" class="form-label">Rate Type</label>
                                <select class="form-select" id="rate_type" name="rate_type" required>
                                    <option value="daily">Daily</option>
                                    <option value="hourly">Hourly</option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="text" class="form-control date-input" id="start_date" name="start_date" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="text" class="form-control date-input" id="end_date" name="end_date" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="rate" class="form-label">Rate (₱)</label>
                                <input type="number" class="form-control" id="rate" name="rate" step="0.01" min="0" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="alert alert-info">
                                    <strong>Estimated Total Cost: </strong>
                                    <span id="total_cost_display">₱0.00</span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_rental" class="btn btn-primary">Add Rental</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script>
            // Function to filter workers in real-time
            document.addEventListener('DOMContentLoaded', function() {
                const workerSearch = document.getElementById('workerSearch');
                const workerItems = document.querySelectorAll('.worker-item');
                
                workerSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    
                    workerItems.forEach(function(item) {
                        const label = item.querySelector('.form-check-label').textContent.toLowerCase();
                        if (label.includes(searchTerm)) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
                
                // Handle rental type selection
                const rentalType = document.getElementById('rental_type');
                const vehicleSelect = document.getElementById('vehicle_select_container');
                const equipmentSelect = document.getElementById('equipment_select_container');
                
                rentalType.addEventListener('change', function() {
                    if (this.value === 'vehicle') {
                        vehicleSelect.style.display = 'block';
                        equipmentSelect.style.display = 'none';
                        document.getElementById('equipment_id').disabled = true;
                        document.getElementById('vehicle_id').disabled = false;
                    } else if (this.value === 'equipment') {
                        vehicleSelect.style.display = 'none';
                        equipmentSelect.style.display = 'block';
                        document.getElementById('vehicle_id').disabled = true;
                        document.getElementById('equipment_id').disabled = false;
                    } else {
                        vehicleSelect.style.display = 'none';
                        equipmentSelect.style.display = 'none';
                        document.getElementById('vehicle_id').disabled = true;
                        document.getElementById('equipment_id').disabled = true;
                    }
                });
                
                // Handle rate type change to toggle between date and datetime inputs
                const rateTypeSelect = document.getElementById('rate_type');
                const startDateInput = document.getElementById('start_date');
                const endDateInput = document.getElementById('end_date');
                
                function updateDateInputs() {
                    const rateType = rateTypeSelect.value;
                    
                    if (rateType === 'daily') {
                        // Set input type to date for daily rate
                        startDateInput.type = 'date';
                        endDateInput.type = 'date';
                    } else {
                        // Set input type to datetime-local for hourly rate
                        startDateInput.type = 'datetime-local';
                        endDateInput.type = 'datetime-local';
                        
                        // Remove seconds from datetime format
                        startDateInput.step = 60;
                        endDateInput.step = 60;
                    }
                }
                
                // Initialize date inputs based on default rate type
                updateDateInputs();
                
                // Update date inputs when rate type changes
                rateTypeSelect.addEventListener('change', updateDateInputs);
                
                // Calculate total cost when dates or rate change
                const rateInput = document.getElementById('rate');
                const totalCostDisplay = document.getElementById('total_cost_display');
                
                function calculateTotalCost() {
                    if (!startDateInput.value || !endDateInput.value || !rateInput.value) {
                        totalCostDisplay.textContent = '₱0.00';
                        return;
                    }
                    
                    const rateType = rateTypeSelect.value;
                    const rate = parseFloat(rateInput.value);
                    
                    if (rateType === 'daily') {
                        const startDate = new Date(startDateInput.value);
                        const endDate = new Date(endDateInput.value);
                        
                        if (startDate > endDate) {
                            totalCostDisplay.textContent = 'Invalid dates';
                            return;
                        }
                        
                        // Calculate days between dates
                        const timeDiff = endDate - startDate;
                        const daysDiff = Math.ceil(timeDiff / (1000 * 60 * 60 * 24)) + 1; // Include both start and end dates
                        totalCost = daysDiff * rate;
                    } else {
                        const startDateTime = new Date(startDateInput.value);
                        const endDateTime = new Date(endDateInput.value);
                        
                        if (startDateTime > endDateTime) {
                            totalCostDisplay.textContent = 'Invalid dates';
                            return;
                        }
                        
                        // Calculate hours between dates
                        const timeDiff = endDateTime - startDateTime;
                        const hoursDiff = Math.ceil(timeDiff / (1000 * 60 * 60));
                        totalCost = hoursDiff * rate;
                    }
                    
                    totalCostDisplay.textContent = '₱' + totalCost.toFixed(2);
                }
                
                startDateInput.addEventListener('change', calculateTotalCost);
                endDateInput.addEventListener('change', calculateTotalCost);
                rateInput.addEventListener('input', calculateTotalCost);
                rateTypeSelect.addEventListener('change', calculateTotalCost);
                
                // Initialize DataTables
                const workersTable = document.getElementById('workersTable');
                if (workersTable) {
                    new simpleDatatables.DataTable(workersTable);
                }
                
                const stockTable = document.getElementById('stockTable');
                if (stockTable) {
                    new simpleDatatables.DataTable(stockTable);
                }
                
                const subconTable = document.getElementById('subconTable');
                if (subconTable) {
                    new simpleDatatables.DataTable(subconTable);
                }
                
                const rentalsTable = document.getElementById('rentalsTable');
                if (rentalsTable) {
                    new simpleDatatables.DataTable(rentalsTable);
                }
            });
        </script>
    </body>
</html>