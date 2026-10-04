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

// Function to generate batch number
function generateBatchNumber($pdo) {
    $prefix = 'BATCH';
    $year = date('Y');
    $month = date('m');
    
    // Get the latest batch number for this year and month
    $stmt = $pdo->prepare("SELECT batch_number FROM spare_parts_batches 
                          WHERE batch_number LIKE :pattern 
                          ORDER BY id DESC LIMIT 1");
    $pattern = $prefix . '-' . $year . $month . '%';
    $stmt->bindParam(':pattern', $pattern);
    $stmt->execute();
    $lastBatch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastBatch) {
        $lastNumber = intval(substr($lastBatch['batch_number'], -4));
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return $prefix . '-' . $year . $month . '-' . $newNumber;
}

// Process spare parts movements
$message = '';
$message_type = ''; // success or danger
$swal_data = []; // For SweetAlert2 data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'initial_part') {
        // Process initial parts
        $part_id = $_POST['part_id'];
        $quantity = $_POST['quantity'];
        $price_per_unit = $_POST['price_per_unit'];
        $date_added = $_POST['date_added'];
        
        try {
            // Insert initial parts record
            $insertStmt = $pdo->prepare("INSERT INTO spare_parts_movements (part_id, quantity, price_per_unit, movement_type, movement_date, notes) 
                                        VALUES (:part_id, :quantity, :price_per_unit, 'in', :date_added, 'Initial stock')");
            $insertStmt->bindParam(':part_id', $part_id);
            $insertStmt->bindParam(':quantity', $quantity);
            $insertStmt->bindParam(':price_per_unit', $price_per_unit);
            $insertStmt->bindParam(':date_added', $date_added);
            
            if ($insertStmt->execute()) {
                // Insert into spare_parts_batches for FIFO tracking
                $batchStmt = $pdo->prepare("INSERT INTO spare_parts_batches (part_id, quantity, price_per_unit, date_received, notes) 
                                          VALUES (:part_id, :quantity, :price_per_unit, :date_added, 'Initial stock')");
                $batchStmt->bindParam(':part_id', $part_id);
                $batchStmt->bindParam(':quantity', $quantity);
                $batchStmt->bindParam(':price_per_unit', $price_per_unit);
                $batchStmt->bindParam(':date_added', $date_added);
                $batchStmt->execute();
                
                // Update inventory levels
                $updateStmt = $pdo->prepare("INSERT INTO spare_parts_inventory (part_id, quantity, price_per_unit) 
                                            VALUES (:part_id, :quantity, :price_per_unit)
                                            ON DUPLICATE KEY UPDATE 
                                            quantity = quantity + :quantity,
                                            price_per_unit = :price_per_unit");
                $updateStmt->bindParam(':part_id', $part_id);
                $updateStmt->bindParam(':quantity', $quantity);
                $updateStmt->bindParam(':price_per_unit', $price_per_unit);
                $updateStmt->execute();
                
                $swal_data = [
                    'title' => 'Success!',
                    'text' => 'Initial parts added successfully!',
                    'icon' => 'success'
                ];
            } else {
                $swal_data = [
                    'title' => 'Error!',
                    'text' => 'Error adding initial parts. Please try again.',
                    'icon' => 'error'
                ];
            }
        } catch(PDOException $e) {
            $swal_data = [
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            ];
        }
    }
}

// Fetch data for dropdowns and tables
try {
    // Get parts categories
    $categoriesStmt = $pdo->prepare("SELECT id, category_name FROM spare_parts_categories ORDER BY category_name");
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get parts
    $partsStmt = $pdo->prepare("
        SELECT sp.id, sp.part_number, sp.part_name, sp.description, spc.category_name, sp.unit_of_measure, sp.min_stock_level
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        ORDER BY sp.part_name
    ");
    $partsStmt->execute();
    $parts = $partsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get suppliers
    $suppliersStmt = $pdo->prepare("SELECT id, supplier_name FROM spare_parts_suppliers ORDER BY supplier_name");
    $suppliersStmt->execute();
    $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get vehicles
    $vehiclesStmt = $pdo->prepare("SELECT id, vehicle_name, plate_number FROM vehicles ORDER BY vehicle_name");
    $vehiclesStmt->execute();
    $vehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get equipment
    $equipmentStmt = $pdo->prepare("SELECT id, equipment_name FROM equipment ORDER BY equipment_name");
    $equipmentStmt->execute();
    $equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get spare parts inventory summary with minimum level check from spare_parts table
    $inventoryStmt = $pdo->prepare("
        SELECT 
            sp.id as part_id, 
            COALESCE(spi.quantity, 0) as quantity, 
            COALESCE(spi.price_per_unit, 0) as price_per_unit,
            sp.part_number,
            sp.part_name,
            sp.description,
            spc.category_name,
            sp.unit_of_measure,
            COALESCE(sp.min_stock_level, 0) as min_stock_level,
            CASE 
                WHEN COALESCE(spi.quantity, 0) <= 0 THEN 'out-of-stock'
                WHEN COALESCE(spi.quantity, 0) <= COALESCE(sp.min_stock_level, 0) AND COALESCE(sp.min_stock_level, 0) > 0 THEN 'low-stock'
                ELSE 'normal'
            END AS stock_status
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_inventory spi ON sp.id = spi.part_id
        ORDER BY sp.part_name
    ");
    $inventoryStmt->execute();
    $parts_inventory = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get low parts alerts
    $lowPartsStmt = $pdo->prepare("
        SELECT spi.part_id, spi.quantity, spi.price_per_unit,
               sp.part_number, sp.part_name, sp.description, spc.category_name,
               sp.min_stock_level
        FROM spare_parts_inventory spi
        JOIN spare_parts sp ON spi.part_id = sp.id
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        WHERE spi.quantity <= sp.min_stock_level AND sp.min_stock_level > 0
        ORDER BY spi.quantity ASC, sp.part_name
    ");
    $lowPartsStmt->execute();
    $low_parts_items = $lowPartsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent parts movements
    $movementsStmt = $pdo->prepare("
        SELECT spm.*, 
            s.supplier_name,
            v.vehicle_name, v.plate_number,
            e.equipment_name,
            sp.part_number, sp.part_name,
            spb.price_per_unit as batch_price,
            spb.batch_number,
            spb.purchase_request,
            emp.firstname, emp.middlename, emp.lastname, emp.suffix,
            tech.firstname as tech_firstname, 
            tech.middlename as tech_middlename, 
            tech.lastname as tech_lastname, 
            tech.suffix as tech_suffix
        FROM spare_parts_movements spm
        LEFT JOIN spare_parts_suppliers s ON spm.supplier_id = s.id
        LEFT JOIN vehicles v ON spm.vehicle_id = v.id
        LEFT JOIN equipment e ON spm.equipment_id = e.id
        LEFT JOIN spare_parts sp ON spm.part_id = sp.id
        LEFT JOIN spare_parts_batches spb ON spm.batch_id = spb.id
        LEFT JOIN employee emp ON spm.employee_id = emp.id
        LEFT JOIN employee tech ON spm.technician = tech.id
        ORDER BY spm.movement_date DESC, spm.created_at DESC
        LIMIT 50
    ");
    $movementsStmt->execute();
    $parts_movements = $movementsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get parts batches for FIFO tracking
    $batchesStmt = $pdo->prepare("
        SELECT spb.*, sp.part_number, sp.part_name, s.supplier_name
        FROM spare_parts_batches spb
        JOIN spare_parts sp ON spb.part_id = sp.id
        LEFT JOIN spare_parts_suppliers s ON spb.supplier_id = s.id
        WHERE spb.quantity > 0
        ORDER BY spb.part_id, spb.date_received ASC, spb.id ASC
    ");
    $batchesStmt->execute();
    $parts_batches = $batchesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $swal_data = [
        'title' => 'Error!',
        'text' => 'Error fetching data: ' . $e->getMessage(),
        'icon' => 'error'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Motorpool Inventory Management - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            .table-responsive {
                overflow-x: auto;
            }
            .dataTable-top {
                padding: 8px 0;
            }
            .dataTable-bottom {
                padding: 8px 0;
            }
            .inventory-table th, .movements-table th {
                background-color: #f8f9fa;
                font-weight: 600;
            }
            .text-success {
                color: #198754 !important;
            }
            .text-danger {
                color: #dc3545 !important;
            }
            .text-warning {
                color: #fd7e14 !important;
            }
            .card-title {
                font-size: 1.1rem;
                font-weight: 600;
            }
            .stock-out {
                background-color: #ffcccc !important;
            }
            .stock-low {
                background-color: #fff3cd !important;
            }
            .badge-out-of-stock {
                background-color: #dc3545;
                color: white;
            }
            .badge-low-stock {
                background-color: #fd7e14;
                color: white;
            }
            .badge-normal-stock {
                background-color: #198754;
                color: white;
            }
            .form-floating > .form-select {
                padding-top: 1.625rem;
                padding-bottom: 0.625rem;
            }
            .stock-level-bar {
                height: 20px;
                background-color: #e9ecef;
                border-radius: 4px;
                overflow: hidden;
            }
            .stock-level-fill {
                height: 100%;
                background-color: #0d6efd;
                transition: width 0.3s ease;
            }
            .action-card {
                transition: transform 0.2s ease-in-out;
                height: 100%;
            }
            .action-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }
            .action-card .card-body {
                padding: 1.5rem;
            }
            .action-icon {
                font-size: 2rem;
                margin-bottom: 1rem;
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
                        <h1 class="mt-4">Motorpool Inventory Management</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Motorpool Inventory</li>
                        </ol>
                        
                        <!-- Low Parts Alerts -->
                        <?php if (!empty($low_parts_items)): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Low Parts Alert</h5>
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
                                    <span class="badge badge-<?php echo $status === 'out-of-stock' ? 'out-of-stock' : 'low-stock'; ?>">
                                        <?php echo $status === 'out-of-stock' ? 'Out of Stock' : 'Low Stock'; ?>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Quick Actions Cards -->
                        <div class="row mb-4">
                            <div class="col-xl-2 mb-4">
                            </div>
                            <!-- Parts In Operations -->
                            <div class="col-xl-8 mb-4">
                                <div class="card bg-primary text-white action-card">
                                    <div class="card-body text-center">
                                        <div class="action-icon">
                                            <i class="fas fa-boxes"></i>
                                        </div>
                                        <h5 class="card-title">Parts In Operations</h5>
                                        <p class="card-text">Manage initial stock and inventory additions</p>
                                        <div class="d-grid gap-2 d-md-block mt-3">
                                            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#initialPartsModal">
                                                <i class="fas fa-boxes me-1"></i> Initial Stock
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Parts Inventory Summary -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-cogs me-1"></i>
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
                                                $total_value = $item['quantity'] * $item['price_per_unit'];
                                                $row_class = '';
                                                $status_badge = '';
                                                
                                                if ($item['stock_status'] === 'out-of-stock') {
                                                    $row_class = 'stock-out';
                                                    $status_badge = '<span class="badge badge-out-of-stock">Out of Stock</span>';
                                                } elseif ($item['stock_status'] === 'low-stock') {
                                                    $row_class = 'stock-low';
                                                    $status_badge = '<span class="badge badge-low-stock">Low Stock</span>';
                                                } else {
                                                    $status_badge = '<span class="badge badge-normal-stock">Normal</span>';
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
                                                <td>₱<?php echo number_format($item['price_per_unit'], 2); ?></td>
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
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-layer-group me-1"></i>
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
                                                <td><?php echo htmlspecialchars($batch['date_received'] ?? ''); ?></td>
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
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-exchange-alt me-1"></i>
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
                                                <td><?php echo htmlspecialchars($movement['movement_date'] ?? ''); ?></td>
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
        <div class="modal fade" id="initialPartsModal" tabindex="-1" aria-labelledby="initialPartsModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="initialPartsModalLabel">Add Initial Parts</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="initial_part">
                        <div class="modal-body">
                            <div class="mb-3">
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
                            
                            <div class="mb-3">
                                <label for="initial_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="initial_quantity" name="quantity" step="1" min="1" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="initial_price_per_unit" class="form-label">Price per Unit (₱) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="initial_price_per_unit" name="price_per_unit" step="0.01" min="0" required>
                            </div>
                            
                            <div class="mb-3">
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

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script>
            // Initialize DataTables and Select2
            window.addEventListener('DOMContentLoaded', event => {
                // Initialize Select2 for searchable dropdown
                $('.select2-search').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: 'Search for a part...',
                    allowClear: true,
                    dropdownParent: $('#initialPartsModal')
                });
                
                const inventoryTable = document.getElementById('inventoryTable');
                if (inventoryTable) {
                    new simpleDatatables.DataTable(inventoryTable, {
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
                
                const batchesTable = document.getElementById('batchesTable');
                if (batchesTable) {
                    new simpleDatatables.DataTable(batchesTable, {
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
                
                const movementsTable = document.getElementById('movementsTable');
                if (movementsTable) {
                    new simpleDatatables.DataTable(movementsTable, {
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
                
                // Show SweetAlert2 notifications if there are any
                <?php if (!empty($swal_data)): ?>
                    Swal.fire({
                        title: '<?php echo $swal_data['title']; ?>',
                        text: '<?php echo $swal_data['text']; ?>',
                        icon: '<?php echo $swal_data['icon']; ?>',
                        confirmButtonText: 'OK'
                    });
                <?php endif; ?>
                
                // Show modal if there was an error with form submission
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($swal_data['icon']) && $swal_data['icon'] === 'error'): ?>
                    <?php if (isset($_POST['action']) && $_POST['action'] === 'initial_part'): ?>
                        var initialPartsModal = new bootstrap.Modal(document.getElementById('initialPartsModal'));
                        initialPartsModal.show();
                        // Reinitialize Select2 after modal is shown
                        $('#initialPartsModal').on('shown.bs.modal', function () {
                            $('.select2-search').select2({
                                theme: 'bootstrap-5',
                                width: '100%',
                                placeholder: 'Search for a part...',
                                allowClear: true,
                                dropdownParent: $('#initialPartsModal')
                            });
                        });
                    <?php endif; ?>
                <?php endif; ?>
            });
            
            // Logout function with SweetAlert2
            document.getElementById('logoutLink').addEventListener('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'You want to logout from the system.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, logout!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'action/logout.php';
                    }
                });
            });
            
            // Simple sidebar toggle functionality
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.body.classList.toggle('sb-sidenav-toggled');
            });
        </script>
    </body>
</html>