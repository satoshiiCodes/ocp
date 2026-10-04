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

// Process spare parts issuance to employees
$message = '';
$message_type = ''; // success or danger
$swal_data = []; // For SweetAlert2 data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'issue_to_employee') {
        // Process parts issuance to employee
        $employee_id = $_POST['employee_id'];
        $purpose = $_POST['purpose'];
        $date_issued = $_POST['date_issued'];
        
        // Get array of parts data
        $part_ids = $_POST['part_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            $total_issued_items = 0;
            $overall_total_cost = 0;
            $issuance_details = []; // Store details for success message
            
            // Process each part
            foreach ($part_ids as $index => $part_id) {
                if (empty($part_id) || empty($quantities[$index]) || $quantities[$index] <= 0) {
                    continue; // Skip empty entries
                }
                
                $quantity = (int)$quantities[$index];
                
                // Check if enough parts are available
                $checkStmt = $pdo->prepare("SELECT SUM(quantity) as total_quantity FROM spare_parts_batches 
                                          WHERE part_id = :part_id AND quantity > 0");
                $checkStmt->bindParam(':part_id', $part_id);
                $checkStmt->execute();
                $current_stock = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$current_stock || $current_stock['total_quantity'] < $quantity) {
                    throw new Exception("Insufficient stock for part ID: $part_id. Available: " . ($current_stock['total_quantity'] ?? 0) . ", Requested: $quantity");
                }
                
                // Get the oldest batches first (FIFO)
                $batchStmt = $pdo->prepare("SELECT id, quantity, price_per_unit FROM spare_parts_batches 
                                          WHERE part_id = :part_id AND quantity > 0 
                                          ORDER BY date_received ASC, id ASC");
                $batchStmt->bindParam(':part_id', $part_id);
                $batchStmt->execute();
                $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $remaining_quantity = $quantity;
                $part_total_cost = 0;
                $batch_usage = []; // Track batch usage for detailed records
                $price_details = []; // Store individual price details for display
                
                foreach ($batches as $batch) {
                    if ($remaining_quantity <= 0) break;
                    
                    $batch_quantity_used = min($remaining_quantity, $batch['quantity']);
                    $batch_cost = $batch_quantity_used * $batch['price_per_unit'];
                    $part_total_cost += $batch_cost;
                    
                    // Record batch usage
                    $batch_usage[] = [
                        'batch_id' => $batch['id'],
                        'quantity_used' => $batch_quantity_used,
                        'price_per_unit' => $batch['price_per_unit'],
                        'batch_cost' => $batch_cost
                    ];
                    
                    // Store price details for display
                    $price_details[] = [
                        'quantity' => $batch_quantity_used,
                        'price_per_unit' => $batch['price_per_unit'],
                        'subtotal' => $batch_cost
                    ];
                    
                    // Update the batch quantity
                    $updateBatchStmt = $pdo->prepare("UPDATE spare_parts_batches SET quantity = quantity - :quantity_used 
                                                     WHERE id = :batch_id");
                    $updateBatchStmt->bindParam(':quantity_used', $batch_quantity_used);
                    $updateBatchStmt->bindParam(':batch_id', $batch['id']);
                    $updateBatchStmt->execute();
                    
                    $remaining_quantity -= $batch_quantity_used;
                }
                
                // Insert detailed parts issuance records for each batch used
                foreach ($batch_usage as $batch) {
                    $insertStmt = $pdo->prepare("INSERT INTO spare_parts_movements (part_id, employee_id, quantity, price_per_unit, movement_type, movement_date, purpose, batch_id) 
                                                VALUES (:part_id, :employee_id, :quantity, :price_per_unit, 'out', :date_issued, :purpose, :batch_id)");
                    $insertStmt->bindParam(':part_id', $part_id);
                    $insertStmt->bindParam(':employee_id', $employee_id);
                    $insertStmt->bindParam(':quantity', $batch['quantity_used']);
                    $insertStmt->bindParam(':price_per_unit', $batch['price_per_unit']);
                    $insertStmt->bindParam(':date_issued', $date_issued);
                    $insertStmt->bindParam(':purpose', $purpose);
                    $insertStmt->bindParam(':batch_id', $batch['batch_id']);
                    $insertStmt->execute();
                }
                
                // Calculate new weighted average price after issuing parts for inventory
                $avgPriceStmt = $pdo->prepare("
                    SELECT 
                        SUM(quantity * price_per_unit) / SUM(quantity) as avg_price
                    FROM spare_parts_batches 
                    WHERE part_id = :part_id AND quantity > 0
                ");
                $avgPriceStmt->bindParam(':part_id', $part_id);
                $avgPriceStmt->execute();
                $avgPriceData = $avgPriceStmt->fetch(PDO::FETCH_ASSOC);
                
                $weighted_avg_price = $avgPriceData['avg_price'] ?? 0;
                
                // Update inventory levels
                $updateStmt = $pdo->prepare("UPDATE spare_parts_inventory SET quantity = quantity - :quantity, price_per_unit = :price_per_unit
                                            WHERE part_id = :part_id");
                $updateStmt->bindParam(':part_id', $part_id);
                $updateStmt->bindParam(':quantity', $quantity);
                $updateStmt->bindParam(':price_per_unit', $weighted_avg_price);
                $updateStmt->execute();
                
                // Get part details for display
                $partInfoStmt = $pdo->prepare("SELECT part_number, part_name FROM spare_parts WHERE id = :part_id");
                $partInfoStmt->bindParam(':part_id', $part_id);
                $partInfoStmt->execute();
                $part_info = $partInfoStmt->fetch(PDO::FETCH_ASSOC);
                
                // Build price breakdown for this part
                $price_breakdown = '';
                foreach ($price_details as $batch_index => $detail) {
                    $price_breakdown .= 'Batch ' . ($batch_index + 1) . ': ' . number_format($detail['quantity'], 0) . 
                                      ' × ₱' . number_format($detail['price_per_unit'], 2) . 
                                      ' = ₱' . number_format($detail['subtotal'], 2) . '<br>';
                }
                
                // Store issuance details
                $issuance_details[] = [
                    'part_name' => $part_info['part_name'] . ' (' . $part_info['part_number'] . ')',
                    'quantity' => $quantity,
                    'price_breakdown' => $price_breakdown,
                    'part_total' => $part_total_cost
                ];
                
                // Also create a record in employee_materials_issued table for tracking with actual prices
                // We'll create separate records for each batch price
                foreach ($batch_usage as $batch) {
                    $employeeIssueStmt = $pdo->prepare("INSERT INTO employee_materials_issued (employee_id, part_id, quantity, price_per_unit, total_price, purpose, date_issued, batch_id) 
                                                       VALUES (:employee_id, :part_id, :quantity, :price_per_unit, :total_price, :purpose, :date_issued, :batch_id)");
                    $employeeIssueStmt->bindParam(':employee_id', $employee_id);
                    $employeeIssueStmt->bindParam(':part_id', $part_id);
                    $employeeIssueStmt->bindParam(':quantity', $batch['quantity_used']);
                    $employeeIssueStmt->bindParam(':price_per_unit', $batch['price_per_unit']);
                    $employeeIssueStmt->bindParam(':total_price', $batch['batch_cost']);
                    $employeeIssueStmt->bindParam(':purpose', $purpose);
                    $employeeIssueStmt->bindParam(':date_issued', $date_issued);
                    $employeeIssueStmt->bindParam(':batch_id', $batch['batch_id']);
                    $employeeIssueStmt->execute();
                }
                
                $total_issued_items += $quantity;
                $overall_total_cost += $part_total_cost;
            }
            
            if ($total_issued_items === 0) {
                throw new Exception("No valid parts selected for issuance.");
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Build success message with all issuance details
            $success_html = 'Materials issued to employee successfully!<br><br>';
            $success_html .= '<div class="text-start">';
            $success_html .= '<strong>Issuance Summary:</strong><br>';
            
            foreach ($issuance_details as $index => $detail) {
                $success_html .= '<div class="mb-2">';
                $success_html .= '<strong>Item ' . ($index + 1) . ':</strong> ' . $detail['part_name'] . '<br>';
                $success_html .= '<strong>Quantity:</strong> ' . number_format($detail['quantity'], 0) . '<br>';
                $success_html .= '<strong>Price Breakdown (FIFO):</strong><br>' . $detail['price_breakdown'];
                $success_html .= '<strong>Item Total:</strong> ₱' . number_format($detail['part_total'], 2) . '<br>';
                $success_html .= '</div>';
            }
            
            $success_html .= '<hr>';
            $success_html .= '<strong>Total Items Issued:</strong> ' . number_format($total_issued_items, 0) . '<br>';
            $success_html .= '<strong>Grand Total Price:</strong> ₱' . number_format($overall_total_cost, 2) . '<br>';
            $success_html .= '<small class="text-muted">Note: Materials issued from oldest batches first.</small>';
            $success_html .= '</div>';
            
            $swal_data = [
                'title' => 'Success!',
                'html' => $success_html,
                'icon' => 'success'
            ];
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $swal_data = [
                'title' => 'Error!',
                'text' => 'Error: ' . $e->getMessage(),
                'icon' => 'error'
            ];
        } catch(PDOException $e) {
            $pdo->rollBack();
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
    // Get parts with current price - ONLY WHERE category_name is 'Materials'
    $partsStmt = $pdo->prepare("
        SELECT sp.id, sp.part_number, sp.part_name, sp.description, spc.category_name, sp.unit_of_measure,
               COALESCE(spi.quantity, 0) as quantity, 
               COALESCE(spi.price_per_unit, 0) as current_price,
               (
                   SELECT price_per_unit 
                   FROM spare_parts_batches 
                   WHERE part_id = sp.id AND quantity > 0 
                   ORDER BY date_received ASC, id ASC 
                   LIMIT 1
               ) as next_fifo_price
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_inventory spi ON sp.id = spi.part_id
        WHERE COALESCE(spi.quantity, 0) > 0
        AND spc.category_name = 'Materials'
        ORDER BY sp.part_name
    ");
    $partsStmt->execute();
    $parts = $partsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active employees
    $employeesStmt = $pdo->prepare("
        SELECT id, employee_id, firstname, middlename, lastname, suffix, position 
        FROM employee 
        WHERE status = 'active' 
        ORDER BY lastname, firstname
    ");
    $employeesStmt->execute();
    $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent materials issued to employees with price information
    $issuedStmt = $pdo->prepare("
        SELECT emi.*, 
               e.employee_id as emp_id, 
               CONCAT(e.firstname, ' ', e.lastname) as employee_name,
               e.position,
               sp.part_number, sp.part_name,
               spc.category_name
        FROM employee_materials_issued emi
        JOIN employee e ON emi.employee_id = e.id
        JOIN spare_parts sp ON emi.part_id = sp.id
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        ORDER BY emi.date_issued DESC, emi.created_at DESC
        LIMIT 50
    ");
    $issuedStmt->execute();
    $materials_issued = $issuedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get parts inventory summary for available stock - ONLY WHERE category_name is 'Materials'
    $inventoryStmt = $pdo->prepare("
        SELECT 
            sp.id as part_id, 
            COALESCE(spi.quantity, 0) as quantity, 
            COALESCE(spi.price_per_unit, 0) as current_price,
            sp.part_number,
            sp.part_name,
            sp.description,
            spc.category_name,
            sp.unit_of_measure
        FROM spare_parts sp
        JOIN spare_parts_categories spc ON sp.category_id = spc.id
        LEFT JOIN spare_parts_inventory spi ON sp.id = spi.part_id
        WHERE COALESCE(spi.quantity, 0) > 0
        AND spc.category_name = 'Materials'
        ORDER BY sp.part_name
    ");
    $inventoryStmt->execute();
    $parts_inventory = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
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
        <title>Issue Materials to Employees - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
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
            .inventory-table th, .issued-table th {
                background-color: #f8f9fa;
                font-weight: 600;
            }
            .text-success {
                color: #198754 !important;
            }
            .text-danger {
                color: #dc3545 !important;
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
            .employee-card {
                border-left: 4px solid #0d6efd;
            }
            .part-card {
                border-left: 4px solid #198754;
            }
            .available-stock {
                font-size: 0.9rem;
                color: #6c757d;
            }
            .employee-info {
                font-size: 0.9rem;
                color: #495057;
            }
            .price-info {
                font-size: 0.85rem;
                color: #6c757d;
            }
            .total-price {
                font-weight: bold;
                color: #198754;
            }
            .price-breakdown {
                font-size: 0.85rem;
                color: #666;
            }
            .part-item {
                border: 1px solid #dee2e6;
                border-radius: 5px;
                padding: 15px;
                margin-bottom: 15px;
                background-color: #f8f9fa;
            }
            .remove-part-btn {
                margin-top: 8px;
            }
            .estimated-total {
                font-size: 1.2rem;
                font-weight: bold;
                color: #198754;
            }
            .part-item-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            .part-item-number {
                font-weight: bold;
                color: #0d6efd;
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
                        <h1 class="mt-4">Issue Materials to Employees (FIFO Pricing)</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="spare_parts_inventory.php">Spare Parts</a></li>
                            <li class="breadcrumb-item active">Issue to Employees</li>
                        </ol>
                        
                        <!-- Quick Actions Cards -->
                        <div class="row mb-4">
                            <!-- Issue Materials Card -->
                            <div class="col-xl-6 col-md-6 mb-4">
                                <div class="card bg-primary text-white action-card">
                                    <div class="card-body text-center">
                                        <div class="action-icon">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                        <h5 class="card-title">Issue Materials to Employee</h5>
                                        <p class="card-text">Issue spare parts/materials to employees using FIFO pricing</p>
                                        <div class="d-grid gap-2 d-md-block mt-3">
                                            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#issueModal">
                                                <i class="fas fa-hand-holding me-1"></i> Issue Materials
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Available Stock Card -->
                            <div class="col-xl-6 col-md-6 mb-4">
                                <div class="card bg-success text-white action-card">
                                    <div class="card-body text-center">
                                        <div class="action-icon">
                                            <i class="fas fa-boxes"></i>
                                        </div>
                                        <h5 class="card-title">Available Materials</h5>
                                        <p class="card-text">View available materials in inventory</p>
                                        <div class="d-grid gap-2 d-md-block mt-3">
                                            <button class="btn btn-light btn-sm" onclick="scrollToInventory()">
                                                <i class="fas fa-eye me-1"></i> View Stock
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Current Available Employees -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-users me-1"></i>
                                Available Employees (Active Status)
                            </div>
                            <div class="card-body">
                                <?php if (!empty($employees)): ?>
                                <div class="row">
                                    <?php foreach ($employees as $employee): 
                                        $full_name = $employee['firstname'];
                                        if (!empty($employee['middlename'])) {
                                            $full_name .= ' ' . substr($employee['middlename'], 0, 1) . '.';
                                        }
                                        $full_name .= ' ' . $employee['lastname'];
                                        if (!empty($employee['suffix'])) {
                                            $full_name .= ' ' . $employee['suffix'];
                                        }
                                    ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card employee-card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($full_name); ?></h6>
                                                <div class="employee-info">
                                                    <div><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee['employee_id']); ?></div>
                                                    <div><strong>Position:</strong> <?php echo htmlspecialchars($employee['position']); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No active employees found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Available Materials Inventory -->
                        <div class="card mb-4" id="inventorySection">
                            <div class="card-header">
                                <i class="fas fa-cogs me-1"></i>
                                Available Materials in Inventory (Materials Category Only)
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
                                                <th>Available Quantity</th>
                                                <th>Unit</th>
                                                <th>Current Price/Unit</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($parts_inventory as $item): 
                                                $total_value = $item['quantity'] * $item['current_price'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                                <td><?php echo number_format($item['quantity'], 0); ?></td>
                                                <td><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                                                <td>₱<?php echo number_format($item['current_price'], 2); ?></td>
                                                <td class="total-price">₱<?php echo number_format($total_value, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No available materials in inventory.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Materials Issued to Employees -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-history me-1"></i>
                                Recent Materials Issued to Employees (Actual FIFO Prices)
                            </div>
                            <div class="card-body">
                                <?php if (!empty($materials_issued)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover issued-table" id="issuedTable">
                                        <thead>
                                            <tr>
                                                <th>Date Issued</th>
                                                <th>Employee</th>
                                                <th>Employee ID</th>
                                                <th>Position</th>
                                                <th>Material</th>
                                                <th>Category</th>
                                                <th>Quantity</th>
                                                <th>Actual Price/Unit</th>
                                                <th>Total Price</th>
                                                <th>Purpose</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($materials_issued as $issue): 
                                                $total_price = $issue['quantity'] * $issue['price_per_unit'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($issue['date_issued']); ?></td>
                                                <td><?php echo htmlspecialchars($issue['employee_name']); ?></td>
                                                <td><?php echo htmlspecialchars($issue['emp_id']); ?></td>
                                                <td><?php echo htmlspecialchars($issue['position']); ?></td>
                                                <td><?php echo htmlspecialchars($issue['part_name'] . ' (' . $issue['part_number'] . ')'); ?></td>
                                                <td><?php echo htmlspecialchars($issue['category_name']); ?></td>
                                                <td><?php echo number_format($issue['quantity'], 0); ?></td>
                                                <td>₱<?php echo number_format($issue['price_per_unit'], 2); ?></td>
                                                <td class="total-price">₱<?php echo number_format($total_price, 2); ?></td>
                                                <td><?php echo htmlspecialchars($issue['purpose']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No materials issued to employees yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Issue Materials Modal -->
        <div class="modal fade" id="issueModal" tabindex="-1" aria-labelledby="issueModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="issueModalLabel">Issue Materials to Employee (FIFO)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="issueForm">
                        <input type="hidden" name="action" value="issue_to_employee">
                        <div class="modal-body">
                            <!-- Employee Selection -->
                            <div class="form-floating mb-4">
                                <select class="form-select" id="employee_id" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): 
                                        $full_name = $employee['firstname'];
                                        if (!empty($employee['middlename'])) {
                                            $full_name .= ' ' . substr($employee['middlename'], 0, 1) . '.';
                                        }
                                        $full_name .= ' ' . $employee['lastname'];
                                        if (!empty($employee['suffix'])) {
                                            $full_name .= ' ' . $employee['suffix'];
                                        }
                                    ?>
                                    <option value="<?php echo $employee['id']; ?>">
                                        <?php echo htmlspecialchars($full_name . ' (' . $employee['employee_id'] . ') - ' . $employee['position']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="employee_id">Employee <span class="text-danger">*</span></label>
                            </div>
                            
                            <!-- General Purpose -->
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="purpose" name="purpose" required>
                                <label for="purpose">Purpose <span class="text-danger">*</span></label>
                                <div class="form-text ms-2">e.g., Maintenance work, Repair, Project requirement</div>
                            </div>
                            
                            <!-- Date Issued -->
                            <div class="form-floating mb-4">
                                <input type="date" class="form-control" id="date_issued" name="date_issued" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <label for="date_issued">Date Issued <span class="text-danger">*</span></label>
                            </div>
                            
                            <!-- Materials Selection Section -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">Materials to Issue (Materials Category Only)</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPartRow()">
                                        <i class="fas fa-plus me-1"></i> Add Another Material
                                    </button>
                                </div>
                                
                                <div id="partsContainer">
                                    <!-- Material rows will be added here -->
                                    <div class="part-item" data-part-index="0">
                                        <div class="part-item-header">
                                            <span class="part-item-number">Item #1</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-part-btn" onclick="removePartRow(this)" <?php echo count($parts) > 1 ? '' : 'disabled'; ?>>
                                                <i class="fas fa-times"></i> Remove
                                            </button>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-7 mb-3">
                                                <div class="form-floating">
                                                    <select class="form-select part-select" name="part_id[]" required onchange="updatePartStock(this)">
                                                        <option value="">Select Material</option>
                                                        <?php foreach ($parts as $part): ?>
                                                        <option value="<?php echo $part['id']; ?>" 
                                                                data-quantity="<?php echo $part['quantity']; ?>"
                                                                data-next-fifo-price="<?php echo $part['next_fifo_price']; ?>"
                                                                data-part-number="<?php echo htmlspecialchars($part['part_number']); ?>"
                                                                data-part-name="<?php echo htmlspecialchars($part['part_name']); ?>"
                                                                data-unit="<?php echo htmlspecialchars($part['unit_of_measure']); ?>">
                                                            <?php echo htmlspecialchars($part['part_number'] . ' - ' . $part['part_name'] . ' (' . $part['category_name'] . ')'); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label>Material <span class="text-danger">*</span></label>
                                                    <div class="form-text available-stock" id="stockText_0">Available: 0 | Next FIFO Price: ₱0.00</div>
                                                </div>
                                            </div>
                                            <div class="col-md-5 mb-3">
                                                <div class="form-floating">
                                                    <input type="number" class="form-control quantity-input" name="quantity[]" step="1" min="1" value="" required oninput="validatePartQuantity(this); updateTotalEstimate();">
                                                    <label>Quantity <span class="text-danger">*</span></label>
                                                    <div class="form-text quantity-error text-danger" style="display: none;">Quantity exceeds available stock!</div>
                                                    <div class="form-text estimated-cost text-success" style="display: none;">Estimate: ₱0.00</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Total Estimate -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h6 class="card-title">Total Estimate</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">Total Items:</small>
                                            <div id="totalItemsCount">0</div>
                                        </div>
                                        <div class="col-6 text-end">
                                            <small class="text-muted">Estimated Grand Total:</small>
                                            <div class="estimated-total" id="grandTotalEstimate">₱0.00</div>
                                        </div>
                                    </div>
                                    <div class="price-breakdown mt-2">
                                        <small><em>Note: Estimates based on oldest batch prices. Final cost may vary if multiple batches are used.</em></small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Information Alert -->
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>FIFO Pricing Note:</strong> Materials will be issued using First-In-First-Out method. 
                                Each batch will be charged at its actual purchase price. The price shown is from the oldest available batch.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" id="submitBtn">Issue Materials</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script>
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
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
                
                const issuedTable = document.getElementById('issuedTable');
                if (issuedTable) {
                    new simpleDatatables.DataTable(issuedTable, {
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
                        html: '<?php echo isset($swal_data['html']) ? addslashes($swal_data['html']) : addslashes($swal_data['text']); ?>',
                        icon: '<?php echo $swal_data['icon']; ?>',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        <?php if ($swal_data['icon'] === 'success'): ?>
                            // Clear form on success
                            document.getElementById('issueForm').reset();
                            resetPartsContainer();
                        <?php endif; ?>
                    });
                <?php endif; ?>
                
                // Show modal if there was an error with form submission
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($swal_data['icon']) && $swal_data['icon'] === 'error'): ?>
                    var issueModal = new bootstrap.Modal(document.getElementById('issueModal'));
                    issueModal.show();
                <?php endif; ?>
                
                // Initialize
                updateTotalEstimate();
            });
            
            let partCounter = 1;
            
            // Function to add new part row
            function addPartRow() {
                const container = document.getElementById('partsContainer');
                const newIndex = container.children.length;
                
                const partItem = document.createElement('div');
                partItem.className = 'part-item';
                partItem.setAttribute('data-part-index', newIndex);
                
                partItem.innerHTML = `
                    <div class="part-item-header">
                        <span class="part-item-number">Item #${newIndex + 1}</span>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-part-btn" onclick="removePartRow(this)">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <div class="form-floating">
                                <select class="form-select part-select" name="part_id[]" required onchange="updatePartStock(this)">
                                    <option value="">Select Material</option>
                                    <?php foreach ($parts as $part): ?>
                                    <option value="<?php echo $part['id']; ?>" 
                                            data-quantity="<?php echo $part['quantity']; ?>"
                                            data-next-fifo-price="<?php echo $part['next_fifo_price']; ?>"
                                            data-part-number="<?php echo htmlspecialchars($part['part_number']); ?>"
                                            data-part-name="<?php echo htmlspecialchars($part['part_name']); ?>"
                                            data-unit="<?php echo htmlspecialchars($part['unit_of_measure']); ?>">
                                        <?php echo htmlspecialchars($part['part_number'] . ' - ' . $part['part_name'] . ' (' . $part['category_name'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Material <span class="text-danger">*</span></label>
                                <div class="form-text available-stock" id="stockText_${newIndex}">Available: 0 | Next FIFO Price: ₱0.00</div>
                            </div>
                        </div>
                        <div class="col-md-5 mb-3">
                            <div class="form-floating">
                                <input type="number" class="form-control quantity-input" name="quantity[]" step="1" min="1" value="" required oninput="validatePartQuantity(this); updateTotalEstimate();">
                                <label>Quantity <span class="text-danger">*</span></label>
                                <div class="form-text quantity-error text-danger" style="display: none;">Quantity exceeds available stock!</div>
                                <div class="form-text estimated-cost text-success" style="display: none;">Estimate: ₱0.00</div>
                            </div>
                        </div>
                    </div>
                `;
                
                container.appendChild(partItem);
                
                // Update remove buttons - enable if more than one part
                updateRemoveButtons();
                partCounter++;
            }
            
            // Function to remove part row
            function removePartRow(button) {
                const partItem = button.closest('.part-item');
                const container = document.getElementById('partsContainer');
                
                if (container.children.length > 1) {
                    partItem.remove();
                    // Re-index all items
                    reindexPartItems();
                    updateTotalEstimate();
                    updateRemoveButtons();
                }
            }
            
            // Function to re-index part items
            function reindexPartItems() {
                const container = document.getElementById('partsContainer');
                const items = container.querySelectorAll('.part-item');
                
                items.forEach((item, index) => {
                    item.setAttribute('data-part-index', index);
                    const numberSpan = item.querySelector('.part-item-number');
                    if (numberSpan) {
                        numberSpan.textContent = `Item #${index + 1}`;
                    }
                });
            }
            
            // Function to update remove buttons state
            function updateRemoveButtons() {
                const container = document.getElementById('partsContainer');
                const removeButtons = container.querySelectorAll('.remove-part-btn');
                
                // Disable remove button if only one part remains
                removeButtons.forEach(button => {
                    if (container.children.length <= 1) {
                        button.disabled = true;
                        button.classList.add('disabled');
                    } else {
                        button.disabled = false;
                        button.classList.remove('disabled');
                    }
                });
            }
            
            // Function to update part stock information
            function updatePartStock(selectElement) {
                const partItem = selectElement.closest('.part-item');
                const index = partItem.getAttribute('data-part-index');
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                
                const availableStock = selectedOption ? selectedOption.getAttribute('data-quantity') || 0 : 0;
                const nextFifoPrice = selectedOption ? parseFloat(selectedOption.getAttribute('data-next-fifo-price')) || 0 : 0;
                
                const stockText = document.getElementById(`stockText_${index}`);
                if (stockText) {
                    stockText.textContent = `Available: ${availableStock} | Next FIFO Price: ₱${nextFifoPrice.toFixed(2)}`;
                }
                
                // Validate quantity for this part
                validatePartQuantity(partItem.querySelector('.quantity-input'));
                updateTotalEstimate();
            }
            
            // Function to validate quantity for a specific part
            function validatePartQuantity(inputElement) {
                const partItem = inputElement.closest('.part-item');
                const selectElement = partItem.querySelector('.part-select');
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                const availableStock = selectedOption ? parseInt(selectedOption.getAttribute('data-quantity')) || 0 : 0;
                
                const quantity = inputElement.value ? parseInt(inputElement.value) : 0;
                const errorElement = partItem.querySelector('.quantity-error');
                const estimateElement = partItem.querySelector('.estimated-cost');
                
                if (quantity > 0 && selectedOption) {
                    const nextFifoPrice = parseFloat(selectedOption.getAttribute('data-next-fifo-price')) || 0;
                    const estimatedCost = quantity * nextFifoPrice;
                    
                    estimateElement.textContent = `Estimate: ₱${estimatedCost.toFixed(2)}`;
                    estimateElement.style.display = 'block';
                } else {
                    estimateElement.style.display = 'none';
                }
                
                if (quantity > availableStock) {
                    errorElement.style.display = 'block';
                } else {
                    errorElement.style.display = 'none';
                }
                
                return quantity <= availableStock;
            }
            
            // Function to update total estimate
            function updateTotalEstimate() {
                const partItems = document.querySelectorAll('.part-item');
                let totalItems = 0;
                let grandTotal = 0;
                
                partItems.forEach(item => {
                    const selectElement = item.querySelector('.part-select');
                    const quantityInput = item.querySelector('.quantity-input');
                    const selectedOption = selectElement.options[selectElement.selectedIndex];
                    
                    if (selectedOption && quantityInput.value) {
                        const quantity = parseInt(quantityInput.value) || 0;
                        const nextFifoPrice = parseFloat(selectedOption.getAttribute('data-next-fifo-price')) || 0;
                        
                        if (quantity > 0) {
                            totalItems += quantity;
                            grandTotal += quantity * nextFifoPrice;
                        }
                    }
                });
                
                document.getElementById('totalItemsCount').textContent = totalItems;
                document.getElementById('grandTotalEstimate').textContent = `₱${grandTotal.toFixed(2)}`;
            }
            
            // Function to validate all parts before submission
            function validateAllParts() {
                const partItems = document.querySelectorAll('.part-item');
                let isValid = true;
                let errorMessages = [];
                
                partItems.forEach((item, index) => {
                    const selectElement = item.querySelector('.part-select');
                    const quantityInput = item.querySelector('.quantity-input');
                    
                    if (!selectElement.value) {
                        isValid = false;
                        errorMessages.push(`Item #${index + 1}: Please select a material`);
                    }
                    
                    if (!quantityInput.value || parseInt(quantityInput.value) <= 0) {
                        isValid = false;
                        errorMessages.push(`Item #${index + 1}: Please enter a valid quantity`);
                    }
                    
                    if (!validatePartQuantity(quantityInput)) {
                        isValid = false;
                        const selectedOption = selectElement.options[selectElement.selectedIndex];
                        const partName = selectedOption ? selectedOption.textContent.split(' - ')[1] || selectedOption.textContent : 'Selected Material';
                        errorMessages.push(`Item #${index + 1}: Quantity exceeds available stock for ${partName}`);
                    }
                });
                
                return { isValid, errorMessages };
            }
            
            // Function to reset parts container to initial state
            function resetPartsContainer() {
                const container = document.getElementById('partsContainer');
                container.innerHTML = `
                    <div class="part-item" data-part-index="0">
                        <div class="part-item-header">
                            <span class="part-item-number">Item #1</span>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-part-btn" onclick="removePartRow(this)" disabled>
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-7 mb-3">
                                <div class="form-floating">
                                    <select class="form-select part-select" name="part_id[]" required onchange="updatePartStock(this)">
                                        <option value="">Select Material</option>
                                        <?php foreach ($parts as $part): ?>
                                        <option value="<?php echo $part['id']; ?>" 
                                                data-quantity="<?php echo $part['quantity']; ?>"
                                                data-next-fifo-price="<?php echo $part['next_fifo_price']; ?>"
                                                data-part-number="<?php echo htmlspecialchars($part['part_number']); ?>"
                                                data-part-name="<?php echo htmlspecialchars($part['part_name']); ?>"
                                                data-unit="<?php echo htmlspecialchars($part['unit_of_measure']); ?>">
                                            <?php echo htmlspecialchars($part['part_number'] . ' - ' . $part['part_name'] . ' (' . $part['category_name'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Material <span class="text-danger">*</span></label>
                                    <div class="form-text available-stock" id="stockText_0">Available: 0 | Next FIFO Price: ₱0.00</div>
                                </div>
                            </div>
                            <div class="col-md-5 mb-3">
                                <div class="form-floating">
                                    <input type="number" class="form-control quantity-input" name="quantity[]" step="1" min="1" value="" required oninput="validatePartQuantity(this); updateTotalEstimate();">
                                    <label>Quantity <span class="text-danger">*</span></label>
                                    <div class="form-text quantity-error text-danger" style="display: none;">Quantity exceeds available stock!</div>
                                    <div class="form-text estimated-cost text-success" style="display: none;">Estimate: ₱0.00</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                partCounter = 1;
                updateTotalEstimate();
            }
            
            // Function to scroll to inventory section
            function scrollToInventory() {
                const element = document.getElementById('inventorySection');
                element.scrollIntoView({ behavior: 'smooth' });
            }
            
            // Form validation before submission
            document.getElementById('issueForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate required fields
                const employeeId = document.getElementById('employee_id').value;
                const purpose = document.getElementById('purpose').value;
                
                if (!employeeId || !purpose) {
                    Swal.fire({
                        title: 'Validation Error!',
                        text: 'Please fill in all required fields (Employee and Purpose).',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return false;
                }
                
                // Validate parts
                const validation = validateAllParts();
                
                if (!validation.isValid) {
                    Swal.fire({
                        title: 'Validation Error!',
                        html: validation.errorMessages.join('<br>'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return false;
                }
                
                // If all validations pass, submit the form
                this.submit();
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