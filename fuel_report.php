<?php
// view_po_items.php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'config/db_config.php';
// This page has no actions of its own: it only reads, and its filters come from
// the query string. The actions file exists so every page has one.
if (!defined('OCP_FUEL_REPORT_ACTIONS_RAN')) {
    require __DIR__ . '/actions/fuel_report-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. error_message is only
// taken when the endpoint actually set one.
$ocp_endpoint = require __DIR__ . '/api/fuel_report-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    if ($ocp_key === 'error_message' && $ocp_value === '') {
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
    <title>Gasoline PO Items - OCP Construction</title>
    <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
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
                        <h1 class="page-title">Gasoline PO Items</h1>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="gasoline_purchase_order.php">Purchase Orders</a></li>
                            <li class="breadcrumb-item active">PO Items</li>
                        </ol>
                    </div>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>
                    
                    <!-- Filter Card -->
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-6">
                        <form method="GET" action="" id="filterForm">
                            <div class="grid grid-cols-1 gap-6 items-end xl:grid-cols-4">
                                <div class="min-w-0">
                                    <label for="start_date" class="form-label fw-bold">
                                        <i class="fas fa-calendar-alt mr-1"></i>Start Date
                                    </label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="min-w-0">
                                    <label for="end_date" class="form-label fw-bold">
                                        <i class="fas fa-calendar-alt mr-1"></i>End Date
                                    </label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="min-w-0">
                                    <label for="gasoline_type" class="form-label fw-bold">
                                        <i class="fas fa-oil-can mr-1"></i>Gasoline Type
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
                                <div class="min-w-0">
                                    <div class="grid grid-cols-1 gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter mr-1"></i> Apply Filters
                                        </button>
                                        <?php if (!empty($start_date) || !empty($end_date) || !empty($gasoline_type)): ?>
                                            <a href="fuel_report.php" class="btn btn-warning">
                                                <i class="fas fa-times mr-1"></i> Clear All Filters
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Active Filters Display -->
                            <?php if (!empty($start_date) || !empty($end_date) || !empty($gasoline_type)): ?>
                            <div class="grid grid-cols-1 gap-6 mt-4">
                                <div class="min-w-0">
                                    <div class="flex items-center flex-wrap gap-2">
                                        <span class="fw-bold mr-2">Active Filters:</span>
                                        <?php if (!empty($start_date)): ?>
                                            <span class="badge badge-primary">
                                                From: <?php echo date('m-d-Y', strtotime($start_date)); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($end_date)): ?>
                                            <span class="badge badge-primary">
                                                To: <?php echo date('m-d-Y', strtotime($end_date)); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($gasoline_type)): ?>
                                            <span class="badge badge-success">
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
                    <div class="card mb-6">
                        <div class="card-body">
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                                <div class="min-w-0">
                                    <small class="text-muted">Total Records:</small>
                                    <div class="text-lg font-medium">
                                        <i class="fas fa-list mr-1 text-primary"></i>
                                        <?php echo count($po_items); ?>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <small class="text-muted">Total Quantity:</small>
                                    <div class="text-lg font-medium">
                                        <i class="fas fa-gas-pump mr-1 text-success"></i>
                                        <?php echo number_format($total_quantity, 2); ?> L
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <small class="text-muted">Total Amount:</small>
                                    <div class="text-lg font-medium">
                                        <i class="fas fa-peso-sign mr-1 text-warning"></i>
                                        ₱<?php echo number_format($total_amount, 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Main Table Card -->
                    <div class="card mb-6">
                        <div class="card-header">
                            <div class="flex items-center justify-between w-full">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    Gasoline PO Items Table
                                </div>
                                <div>
                                    <?php if (!empty($po_items)): ?>
                                    <a href="fuel_report_pdf.php?<?php echo http_build_query($_GET); ?>" 
                                       class="btn btn-danger btn-sm" target="_blank">
                                        <i class="fas fa-file-pdf mr-1"></i> Generate PDF Report
                                    </a>
                                    <?php endif; ?>
                                    <?php if (!empty($start_date) || !empty($end_date) || !empty($gasoline_type)): ?>
                                        <span class="badge badge-warning ml-2">Filtered</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($po_items) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover" id="poItemsTable">
                                    <thead>
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
                                                $vehicle_equipment_info = '<i class="fas fa-truck mr-1"></i> ' . 
                                                    htmlspecialchars($item['vehicle_name'] . ' (' . $item['plate_number'] . ')');
                                            } elseif ($item['equipment_name']) {
                                                $vehicle_equipment_info = '<i class="fas fa-tools mr-1"></i> ' . 
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
                                            $badge_class = 'neutral';
                                            if (strpos($type_lower, 'diesel') !== false) {
                                                $badge_class = 'warning';
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
                                                <span class="badge badge-<?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars($item['gasoline_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['supplier_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $vehicle_equipment_info; ?></td>
                                            <td>
                                                <?php if ($item['driver_name']): ?>
                                                    <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($item['driver_name']); ?>
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
                                    <tfoot>
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
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>No records found</strong> for the selected filters.
                                <?php if (!empty($start_date) || !empty($end_date) || !empty($gasoline_type)): ?>
                                    <a href="fuel_report.php" class="text-primary underline">Clear all filters</a> to view all records.
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
    
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js"></script>
    <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
    <script src="<?php echo ocp_asset('assets/js/fuel_report.js'); ?>"></script>
</body>
</html>
