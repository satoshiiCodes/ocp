<?php
// view_po_items.php
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

// Format display name
$display_name = $user['firstname'];
if (!empty($user['middlename'])) {
    $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
}
$display_name .= ' ' . $user['lastname'];
if (!empty($user['suffix'])) {
    $display_name .= ' ' . $user['suffix'];
}

// Initialize filter variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$gasoline_type = isset($_GET['gasoline_type']) ? $_GET['gasoline_type'] : '';

// Build the WHERE clause based on filters
$where_conditions = [];
$params = [];

if (!empty($start_date)) {
    $where_conditions[] = "pi.date_issued >= :start_date";
    $params[':start_date'] = $start_date;
}
if (!empty($end_date)) {
    $where_conditions[] = "pi.date_issued <= :end_date";
    $params[':end_date'] = $end_date;
}
if (!empty($gasoline_type)) {
    $where_conditions[] = "pi.gasoline_type = :gasoline_type";
    $params[':gasoline_type'] = $gasoline_type;
}

$where_clause = !empty($where_conditions) ? " WHERE " . implode(" AND ", $where_conditions) : "";

// Fetch distinct gasoline types for dropdown
try {
    $typeStmt = $pdo->query("SELECT DISTINCT gasoline_type FROM gasoline_po_items ORDER BY gasoline_type");
    $gasoline_types = $typeStmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $gasoline_types = [];
}

// Fetch all records from gasoline_po_items with filters
try {
    $itemsQuery = "
        SELECT 
            pi.*,
            po.po_number,
            s.supplier_name,
            v.vehicle_name,
            v.plate_number,
            e.equipment_name,
            CONCAT(emp.firstname, ' ', emp.lastname) as driver_name
        FROM gasoline_po_items pi
        LEFT JOIN gasoline_purchase_orders po ON pi.po_id = po.id
        LEFT JOIN gasoline_suppliers s ON pi.supplier_id = s.id
        LEFT JOIN vehicles v ON pi.vehicle_id = v.id
        LEFT JOIN equipment e ON pi.equipment_id = e.id
        LEFT JOIN employee emp ON pi.driver_operator_id = emp.id
        $where_clause
        ORDER BY pi.date_issued DESC, pi.id DESC
    ";
    
    $itemsStmt = $pdo->prepare($itemsQuery);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $itemsStmt->bindValue($key, $value);
    }
    
    $itemsStmt->execute();
    $po_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary statistics
    $total_quantity = 0;
    $total_amount = 0;
    $gasoline_type_totals = [];
    
    foreach ($po_items as $item) {
        $quantity = $item['quantity_liters'];
        $amount = $quantity * ($item['price_per_liter'] ?? 0);
        $total_quantity += $quantity;
        $total_amount += $amount;
        
        // Calculate per gasoline type totals
        $type = $item['gasoline_type'];
        if (!isset($gasoline_type_totals[$type])) {
            $gasoline_type_totals[$type] = ['quantity' => 0, 'amount' => 0];
        }
        $gasoline_type_totals[$type]['quantity'] += $quantity;
        $gasoline_type_totals[$type]['amount'] += $amount;
    }
    
} catch(PDOException $e) {
    $error_message = "Error fetching data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gasoline PO Items - OCP Construction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link href="css/styles.css" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        .filter-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .summary-card {
            background-color: #e9ecef;
            border-left: 4px solid #0d6efd;
            margin-bottom: 1.5rem;
        }
        .summary-stats {
            font-size: 1.1rem;
            font-weight: 500;
        }
        .type-summary {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.75rem;
            margin-top: 1rem;
        }
        .type-badge {
            display: inline-block;
            padding: 0.35rem 0.65rem;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.375rem;
            background-color: #6c757d;
        }
        .type-badge.regular {
            background-color: #28a745;
        }
        .type-badge.premium {
            background-color: #007bff;
        }
        .type-badge.diesel {
            background-color: #6f42c1;
        }
        .clear-filter {
            margin-top: 1.8rem;
        }
        @media (max-width: 768px) {
            .clear-filter {
                margin-top: 0.5rem;
            }
        }
        .active-filter {
            background-color: #cfe2ff;
            border-color: #b6d4fe;
            color: #084298;
        }
        .btn-pdf {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        .btn-pdf:hover {
            background-color: #bb2d3b;
            color: white;
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
                    <h1 class="mt-4">Gasoline PO Items</h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="gasoline_purchase_order.php">Purchase Orders</a></li>
                        <li class="breadcrumb-item active">PO Items</li>
                    </ol>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>
                    
                    <!-- Filter Card -->
                    <div class="filter-card">
                        <form method="GET" action="" id="filterForm">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label fw-bold">
                                        <i class="fas fa-calendar-alt me-1"></i>Start Date
                                    </label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="end_date" class="form-label fw-bold">
                                        <i class="fas fa-calendar-alt me-1"></i>End Date
                                    </label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="gasoline_type" class="form-label fw-bold">
                                        <i class="fas fa-oil-can me-1"></i>Gasoline Type
                                    </label>
                                    <select class="form-select" id="gasoline_type" name="gasoline_type">
                                        <option value="">All Types</option>
                                        <?php foreach ($gasoline_types as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type); ?>" 
                                                <?php echo $gasoline_type == $type ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter me-1"></i> Apply Filters
                                        </button>
                                        <?php if (!empty($start_date) || !empty($end_date) || !empty($gasoline_type)): ?>
                                            <a href="fuel_report.php" class="btn btn-warning">
                                                <i class="fas fa-times me-1"></i> Clear All Filters
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Active Filters Display -->
                            <?php if (!empty($start_date) || !empty($end_date) || !empty($gasoline_type)): ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <span class="fw-bold me-2">Active Filters:</span>
                                        <?php if (!empty($start_date)): ?>
                                            <span class="badge bg-primary">
                                                From: <?php echo date('M d, Y', strtotime($start_date)); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($end_date)): ?>
                                            <span class="badge bg-primary">
                                                To: <?php echo date('M d, Y', strtotime($end_date)); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($gasoline_type)): ?>
                                            <span class="badge bg-success">
                                                Type: <?php echo htmlspecialchars($gasoline_type); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <!-- Summary Statistics Card -->
                    <?php if (!empty($po_items)): ?>
                    <div class="card summary-card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <small class="text-muted">Total Records:</small>
                                    <div class="summary-stats">
                                        <i class="fas fa-list me-1 text-primary"></i>
                                        <?php echo count($po_items); ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Total Quantity:</small>
                                    <div class="summary-stats">
                                        <i class="fas fa-gas-pump me-1 text-success"></i>
                                        <?php echo number_format($total_quantity, 2); ?> L
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Total Amount:</small>
                                    <div class="summary-stats">
                                        <i class="fas fa-peso-sign me-1 text-warning"></i>
                                        ₱<?php echo number_format($total_amount, 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Main Table Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    Gasoline PO Items Table
                                </div>
                                <div>
                                    <?php if (!empty($po_items)): ?>
                                    <a href="fuel_report_pdf.php?<?php echo http_build_query($_GET); ?>" 
                                       class="btn btn-pdf btn-sm" target="_blank">
                                        <i class="fas fa-file-pdf me-1"></i> Generate PDF Report
                                    </a>
                                    <?php endif; ?>
                                    <?php if (!empty($start_date) || !empty($end_date) || !empty($gasoline_type)): ?>
                                        <span class="badge bg-warning text-dark ms-2">Filtered</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($po_items) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover" id="poItemsTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Date Issued</th>
                                            <th>PO #</th>
                                            <th>Gasoline Type</th>
                                            <th>Supplier</th>
                                            <th>Vehicle/Equipment</th>
                                            <th>Driver/Operator</th>
                                            <th>Purpose</th>
                                            <th class="text-end">Quantity (L)</th>
                                            <th class="text-end">Price/Liter</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-end">Odometer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($po_items as $item): 
                                            $total = $item['quantity_liters'] * ($item['price_per_liter'] ?? 0);
                                            $vehicle_equipment_info = '';
                                            
                                            if ($item['vehicle_name']) {
                                                $vehicle_equipment_info = '<i class="fas fa-truck me-1"></i> ' . 
                                                    htmlspecialchars($item['vehicle_name'] . ' (' . $item['plate_number'] . ')');
                                            } elseif ($item['equipment_name']) {
                                                $vehicle_equipment_info = '<i class="fas fa-tools me-1"></i> ' . 
                                                    htmlspecialchars($item['equipment_name']);
                                            } else {
                                                $vehicle_equipment_info = '—';
                                            }
                                            
                                            // Format date to mm-dd-yyyy
                                            $formatted_date = '';
                                            if (!empty($item['date_issued'])) {
                                                $date = new DateTime($item['date_issued']);
                                                $formatted_date = $date->format('m-d-Y');
                                            } else {
                                                $formatted_date = '—';
                                            }
                                            
                                            // Determine badge class for gasoline type
                                            $type_lower = strtolower($item['gasoline_type']);
                                            $badge_class = 'secondary';
                                            if (strpos($type_lower, 'diesel') !== false) {
                                                $badge_class = 'warning text-dark';
                                            } elseif (strpos($type_lower, 'premium') !== false) {
                                                $badge_class = 'danger';
                                            } elseif (strpos($type_lower, 'unleaded') !== false) {
                                                $badge_class = 'success';
                                            } elseif (strpos($type_lower, 'regular') !== false) {
                                                $badge_class = 'primary';
                                            } elseif (strpos($type_lower, 'plus') !== false) {
                                                $badge_class = 'info';
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($formatted_date); ?></td>
                                            <td>
                                                <span class="fw-bold"><?php echo htmlspecialchars($item['po_number'] ?? 'N/A'); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars($item['gasoline_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['supplier_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $vehicle_equipment_info; ?></td>
                                            <td>
                                                <?php if ($item['driver_name']): ?>
                                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($item['driver_name']); ?>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span title="<?php echo htmlspecialchars($item['purpose']); ?>">
                                                    <?php echo htmlspecialchars(substr($item['purpose'], 0, 30)) . (strlen($item['purpose']) > 30 ? '...' : ''); ?>
                                                </span>
                                            </td>
                                            <td class="text-end fw-bold"><?php echo number_format($item['quantity_liters'], 2); ?></td>
                                            <td class="text-end">
                                                <?php echo $item['price_per_liter'] ? '₱' . number_format($item['price_per_liter'], 2) : '—'; ?>
                                            </td>
                                            <td class="text-end fw-bold text-primary">
                                                <?php echo $total ? '₱' . number_format($total, 2) : '—'; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php echo $item['odometer_reading'] ? number_format($item['odometer_reading']) . ' km' : '—'; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-secondary">
                                        <tr>
                                            <th colspan="7" class="text-end">Totals:</th>
                                            <th class="text-end"><?php echo number_format($total_quantity, 2); ?> L</th>
                                            <th class="text-end">—</th>
                                            <th class="text-end">₱<?php echo number_format($total_amount, 2); ?></th>
                                            <th class="text-end">—</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>No records found</strong> for the selected filters.
                                <?php if (!empty($start_date) || !empty($end_date) || !empty($gasoline_type)): ?>
                                    <a href="fuel_report.php" class="alert-link">Clear all filters</a> to view all records.
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
            <?php include 'includes/footer.php';?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize DataTable only if there are records
            <?php if (count($po_items) > 0): ?>
            const datatablesSimple = document.getElementById('poItemsTable');
            if (datatablesSimple) {
                new simpleDatatables.DataTable(datatablesSimple, {
                    perPage: 25,
                    perPageSelect: [10, 25, 50, 100],
                    labels: {
                        placeholder: "Search...",
                        perPage: "{select} entries per page",
                        noRows: "No entries found",
                        info: "Showing {start} to {end} of {rows} entries"
                    }
                });
            }
            <?php endif; ?>
            
            // Date validation
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            const filterForm = document.getElementById('filterForm');
            
            if (startDate && endDate) {
                // Ensure end date is not before start date
                startDate.addEventListener('change', function() {
                    if (this.value) {
                        endDate.min = this.value;
                        if (endDate.value && endDate.value < this.value) {
                            endDate.value = this.value;
                        }
                    } else {
                        endDate.min = '';
                    }
                });
                
                endDate.addEventListener('change', function() {
                    if (this.value && startDate.value && this.value < startDate.value) {
                        alert('End date cannot be before start date');
                        this.value = startDate.value;
                    }
                });
            }
            
            // Form validation before submit
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    if (startDate.value && endDate.value && endDate.value < startDate.value) {
                        e.preventDefault();
                        alert('End date must be greater than or equal to start date');
                    }
                });
            }
            
        });
    </script>
</body>
</html>