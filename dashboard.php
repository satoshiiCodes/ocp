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

/*
 * The chart data partials under includes/partials/dashboard/ print json_encode() of their value.
 * The data island, however, encodes whatever it is given a second time - so putting that text
 * straight into the island handed the browser a *string*. Chart.js then drew it as written:
 * the 12 month names became 73 single-character labels ("[", '"', "J", "a", "n", ...) and each
 * year of amounts became one 28-character data point.
 *
 * This turns the partial output back into the array the island can encode properly. Anything
 * that is already a list is passed through, so it is safe to call on either.
 */
function ocp_dashboard_list($value)
{
    if (is_array($value)) {
        return $value;
    }
    if (is_string($value)) {
        $decoded = json_decode(trim($value), true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return [];
}

/* The pie chart counts. A string here becomes one chart slice instead of a number. */
function ocp_dashboard_count($value)
{
    return is_numeric($value) ? (int) $value : 0;
}
    // Get user details including department and position
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix, department, position FROM users WHERE id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if user is from Motorpool department
    $is_motorpool = ($user['department'] == 'Motorpool');
    // Check if user is from Warehouse department
    $is_warehouse = ($user['department'] == 'Warehouse');
    // Check if user is Admin HR Officer
    $is_admin_hr_officer = ($user['department'] == 'Admin' && $user['position'] == 'HR Officer');
    // Check if user is Admin Accounting
    $is_admin_accounting = ($user['department'] == 'Admin' && $user['position'] == 'Accounting');
    // Check if user is Admin Purchaser
    $is_admin_purchaser = ($user['department'] == 'Admin' && $user['position'] == 'Purchaser');
    
    // Format the display name
    $display_name = $user['firstname'];
    
    if (!empty($user['middlename'])) {
        $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
    }
    
    $display_name .= ' ' . $user['lastname'];
    
    if (!empty($user['suffix'])) {
        $display_name .= ' ' . $user['suffix'];
    }

    // The dashboard has no actions of its own: it only reads. The actions file
    // exists so every page has one, matching the layout the rest of the app uses.
    if (!defined('OCP_DASHBOARD_ACTIONS_RAN')) {
        require __DIR__ . '/actions/dashboard-actions.php';
    }

    // All of this page's fetching lives in one file: it returns the variables the
    // markup below needs, which are unpacked into this scope. The block is gated by
    // the role flags above, so each viewer gets only the panels they may see.
    $ocp_endpoint = require __DIR__ . '/api/dashboard-endpoint.php';
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
        <title>Dashboard - OCP Dashboard</title>
        
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
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
                        <h1 class="mt-6">Dashboard</h1>
                        <ol class="breadcrumb mb-6">
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                        
                        <?php if ($is_motorpool): ?>
                            <!-- Motorpool User: Only show Motorpool Inventory Status -->
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                <div class="min-w-0">
                                    <div class="card mb-6">
                                        <div class="card-header">
                                            <i class="fas fa-tools mr-1"></i>
                                            Motorpool Inventory Status - Low Stock | No Stock Items
                                        </div>
                                        <div class="card-body">
                                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                <div class="min-w-0">
                                                    <!-- Smaller pie chart container -->
                                                    <div class="small-pie-chart-container">
                                                        <canvas id="sparePartsPieChart" width="300" height="200"></canvas>
                                                    </div>
                                                </div>
                                                <div class="min-w-0 mt-6">
                                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                        <div class="min-w-0">
                                                            <h6 class="font-semibold">Low Stock Spare Parts (<?php echo count($spare_low_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                            <th>Part Number</th>
                                                                            <th>Part Name</th>
                                                                            <th>Category</th>
                                                                            <th>Qty</th>
                                                                            <th>Min</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($spare_low_stock_items)): ?>
                                                                            <?php foreach ($spare_low_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                                                                    <td><span class="badge badge-warning"><?php echo $item['quantity']; ?></span></td>
                                                                                    <td><?php echo $item['min_stock_level']; ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="5" class="text-center">No low stock spare parts</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="min-w-0 mt-4">
                                                            <h6 class="font-semibold">No Stock Spare Parts (<?php echo count($spare_no_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Part Number</th>
                                                                            <th>Part Name</th>
                                                                            <th>Category</th>
                                                                            <th>Status</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($spare_no_stock_items)): ?>
                                                                            <?php foreach ($spare_no_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                                                                    <td><span class="badge badge-danger">Out of Stock</span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="4" class="text-center">No out of stock spare parts</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($is_warehouse): ?>
                            <!-- Warehouse User: Only show Warehouse Status - Low Stock | No Stock Items -->
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                <div class="min-w-0">
                                    <div class="card mb-6">
                                        <div class="card-header">
                                            <i class="fas fa-boxes mr-1"></i>
                                            Warehouse Status - Low Stock | No Stock Items
                                        </div>
                                        <div class="card-body">
                                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                <div class="min-w-0">
                                                    <div class="small-pie-chart-container">
                                                        <canvas id="inventoryPieChart" width="300" height="200"></canvas>
                                                    </div>
                                                </div>
                                                <div class="min-w-0 mt-6">
                                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                        <div class="min-w-0">
                                                            <h6 class="font-semibold">No Stock Items (<?php echo count($no_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Item Code</th>
                                                                            <th>Item Name</th>
                                                                            <th>Status</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($no_stock_items)): ?>
                                                                            <?php foreach ($no_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                                                    <td><span class="badge badge-danger">Out of Stock</span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="3" class="text-center">No out of stock items</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="min-w-0 mt-4">
                                                            <h6 class="font-semibold">Low Stock Items (<?php echo count($low_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Item Code</th>
                                                                            <th>Item Name</th>
                                                                            <th>Qty</th>
                                                                            <th>Min</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($low_stock_items)): ?>
                                                                            <?php foreach ($low_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                                                    <td><span class="badge badge-warning"><?php echo $item['quantity']; ?></span></td>
                                                                                    <td><?php echo $item['min_stock_level']; ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="4" class="text-center">No low stock items</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($is_admin_hr_officer): ?>
                            <!-- Admin HR Officer: Only show Top 5 Employees with Most Late Attendances -->
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                <div class="min-w-0">
                                    <div class="card mb-6">
                                        <div class="card-header">
                                            <i class="fas fa-table mr-1"></i>
                                            Top 5 Employees with Most Late Attendances
                                        </div>
                                        <div class="card-body">
                                            <table id="lateEmployeesTable" class="table table-bordered table-striped">
                                                <thead>
                                                     <tr>
                                                        <th>Employee Name</th>
                                                        <th>Department</th>
                                                        <th>Times Late</th>
                                                     </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($late_employees)): ?>
                                                        <?php foreach ($late_employees as $employee): ?>
                                                            <tr>
                                                                <td><strong><?php echo htmlspecialchars($employee['employee_name']); ?></strong></td>
                                                                <td><?php echo htmlspecialchars($employee['department'] ?? 'Not Assigned'); ?></td>
                                                                <td>
                                                                    <span class="badge badge-danger"><?php echo $employee['late_count']; ?> times</span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="3" class="text-center">No late attendance records found after 7:30 AM</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($is_admin_accounting): ?>
                            <!-- Admin Accounting User: Only show Monthly Expenses Overview -->
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                <div class="min-w-0">
                                    <div class="card mb-6">
                                        <div class="card-header">
                                            <i class="fas fa-chart-bar mr-1"></i>
                                            Monthly Expenses Overview - <?php echo date('Y'); ?>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="expensesChart" width="100%" height="40"></canvas>
                                            
                                            <div class="row mt-6">
                                                <div class="min-w-0">
                                                    <h6 class="font-semibold">Monthly Breakdown</h6>
                                                    <div style="max-height: 200px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Month</th>
                                                                    <th>Amount (₱)</th>
                                                                    <th>Transactions</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                $has_expense_data = false;
                                                                for ($i = 0; $i < 12; $i++): 
                                                                    if ($expense_amounts[$i] > 0 || $expense_counts[$i] > 0):
                                                                        $has_expense_data = true;
                                                                ?>
                                                                    <tr>
                                                                        <td><strong><?php echo $months_full[$i]; ?></strong></td>
                                                                        <td>₱<?php echo number_format($expense_amounts[$i], 2); ?></td>
                                                                        <td><?php echo $expense_counts[$i]; ?></td>
                                                                    </tr>
                                                                <?php 
                                                                    endif;
                                                                endfor; 
                                                                if (!$has_expense_data):
                                                                ?>
                                                                    <tr>
                                                                        <td colspan="3" class="text-center">No expense records found for <?php echo date('Y'); ?></td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mt-4">
                                                <div class="min-w-0">
                                                    <h6 class="font-semibold">Expense Types Breakdown</h6>
                                                    <div style="max-height: 200px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Expense Type</th>
                                                                    <th>Transactions</th>
                                                                    <th>Total Amount (₱)</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if (!empty($expense_types)): ?>
                                                                    <?php foreach ($expense_types as $type): ?>
                                                                        <?php if ($type['transaction_count'] > 0): ?>
                                                                        <tr>
                                                                            <td><strong><?php echo htmlspecialchars($type['expense_name']); ?></strong></td>
                                                                            <td><?php echo $type['transaction_count']; ?></td>
                                                                            <td>₱<?php echo number_format($type['total_amount'], 2); ?></td>
                                                                        </tr>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <tr>
                                                                        <td colspan="3" class="text-center">No expense types found</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($is_admin_purchaser): ?>
                            <!-- Admin Purchaser: Show Motorpool Inventory, Warehouse Inventory, and Gasoline PO -->
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="card mb-6">
                                        <div class="card-header">
                                            <i class="fas fa-tools mr-1"></i>
                                            Motorpool Inventory Status - Low Stock | No Stock Items
                                        </div>
                                        <div class="card-body">
                                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                <div class="min-w-0">
                                                    <div class="small-pie-chart-container">
                                                        <canvas id="sparePartsPieChart" width="300" height="200"></canvas>
                                                    </div>
                                                </div>
                                                <div class="min-w-0 mt-6">
                                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                        <div class="min-w-0">
                                                            <h6 class="font-semibold">Low Stock Spare Parts (<?php echo count($spare_low_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Part Number</th>
                                                                            <th>Part Name</th>
                                                                            <th>Category</th>
                                                                            <th>Qty</th>
                                                                            <th>Min</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($spare_low_stock_items)): ?>
                                                                            <?php foreach ($spare_low_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                                                                    <td><span class="badge badge-warning"><?php echo $item['quantity']; ?></span></td>
                                                                                    <td><?php echo $item['min_stock_level']; ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="5" class="text-center">No low stock spare parts</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="min-w-0 mt-4">
                                                            <h6 class="font-semibold">No Stock Spare Parts (<?php echo count($spare_no_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Part Number</th>
                                                                            <th>Part Name</th>
                                                                            <th>Category</th>
                                                                            <th>Status</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($spare_no_stock_items)): ?>
                                                                            <?php foreach ($spare_no_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                                                                    <td><span class="badge badge-danger">Out of Stock</span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="4" class="text-center">No out of stock spare parts</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="min-w-0">
                                    <div class="card mb-6">
                                        <div class="card-header">
                                            <i class="fas fa-boxes mr-1"></i>
                                            Warehouse Status - Low Stock | No Stock Items
                                        </div>
                                        <div class="card-body">
                                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                <div class="min-w-0">
                                                    <div class="small-pie-chart-container">
                                                        <canvas id="inventoryPieChart" width="300" height="200"></canvas>
                                                    </div>
                                                </div>
                                                <div class="min-w-0 mt-6">
                                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                        <div class="min-w-0">
                                                            <h6 class="font-semibold">No Stock Items (<?php echo count($no_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Item Code</th>
                                                                            <th>Item Name</th>
                                                                            <th>Status</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($no_stock_items)): ?>
                                                                            <?php foreach ($no_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                                                    <td><span class="badge badge-danger">Out of Stock</span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="3" class="text-center">No out of stock items</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="min-w-0 mt-4">
                                                            <h6 class="font-semibold">Low Stock Items (<?php echo count($low_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Item Code</th>
                                                                            <th>Item Name</th>
                                                                            <th>Qty</th>
                                                                            <th>Min</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($low_stock_items)): ?>
                                                                            <?php foreach ($low_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                                                    <td><span class="badge badge-warning"><?php echo $item['quantity']; ?></span></td>
                                                                                    <td><?php echo $item['min_stock_level']; ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="4" class="text-center">No low stock items</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                <div class="min-w-0">
                                    <div class="card mb-6">
                                        <div class="card-header">
                                            <i class="fas fa-gas-pump mr-1"></i>
                                            Gasoline PO - Completed Orders Per Month (<?php echo $current_year; ?>)
                                        </div>
                                        <div class="card-body">
                                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                <div class="min-w-0">
                                                    <canvas id="gasolineChart" width="100%" height="40"></canvas>
                                                </div>
                                            </div>
                                            
                                            <div class="row mt-6">
                                                <div class="min-w-0">
                                                    <h6 class="font-semibold">Monthly Breakdown</h6>
                                                    <div style="max-height: 250px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Month</th>
                                                                    <th>PO Count</th>
                                                                    <th>Item Count</th>
                                                                    <th>Amount (₱)</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                $has_data = false;
                                                                for ($i = 0; $i < 12; $i++): 
                                                                    if ($gasoline_po_counts[$i] > 0 || $gasoline_amounts[$i] > 0):
                                                                        $has_data = true;
                                                                ?>
                                                                    <tr>
                                                                        <td><strong><?php echo $months_full[$i]; ?></strong></td>
                                                                        <td><?php echo $gasoline_po_counts[$i]; ?></td>
                                                                        <td><?php echo $gasoline_item_counts[$i]; ?></td>
                                                                        <td>₱<?php echo number_format($gasoline_amounts[$i], 2); ?></td>
                                                                    </tr>
                                                                <?php 
                                                                    endif;
                                                                endfor; 
                                                                if (!$has_data):
                                                                ?>
                                                                    <tr>
                                                                        <td colspan="4" class="text-center">No completed gasoline purchase orders found</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <div class="min-w-0 mt-4">
                                                    <h6 class="font-semibold">Gasoline Type Breakdown</h6>
                                                    <div style="max-height: 250px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Gasoline Type</th>
                                                                    <th>PO Count</th>
                                                                    <th>Item Count</th>
                                                                    <th>Total Amount (₱)</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if (!empty($gasoline_types_data)): ?>
                                                                    <?php foreach ($gasoline_types_data as $type): ?>
                                                                        <tr>
                                                                            <td><strong><?php echo htmlspecialchars($type['gasoline_type']); ?></strong></td>
                                                                            <td><?php echo $type['po_count']; ?></td>
                                                                            <td><?php echo $type['item_count']; ?></td>
                                                                            <td>₱<?php echo number_format($type['total_amount'], 2); ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <tr>
                                                                        <td colspan="4" class="text-center">No completed gasoline purchase orders found</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Non-Motorpool & Non-Warehouse & Non-Admin HR Officer & Non-Admin Accounting & Non-Admin Purchaser User: Show all sections -->
                            <!-- Spare Parts Inventory Low Stock and No Stock Chart -->
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="card mb-6">
                                        <div class="card-header">
                                            <i class="fas fa-tools mr-1"></i>
                                            Motorpool Inventory Status - Low Stock | No Stock Items
                                        </div>
                                        <div class="card-body">
                                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                <div class="min-w-0">
                                                    <div class="small-pie-chart-container">
                                                        <canvas id="sparePartsPieChart" width="300" height="200"></canvas>
                                                    </div>
                                                </div>
                                                <div class="min-w-0 mt-6">
                                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                        <div class="min-w-0">
                                                            <h6 class="font-semibold">Low Stock Spare Parts (<?php echo count($spare_low_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Part Number</th>
                                                                            <th>Part Name</th>
                                                                            <th>Category</th>
                                                                            <th>Qty</th>
                                                                            <th>Min</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($spare_low_stock_items)): ?>
                                                                            <?php foreach ($spare_low_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                                                                    <td><span class="badge badge-warning"><?php echo $item['quantity']; ?></span></td>
                                                                                    <td><?php echo $item['min_stock_level']; ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="5" class="text-center">No low stock spare parts</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="min-w-0 mt-4">
                                                            <h6 class="font-semibold">No Stock Spare Parts (<?php echo count($spare_no_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Part Number</th>
                                                                            <th>Part Name</th>
                                                                            <th>Category</th>
                                                                            <th>Status</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($spare_no_stock_items)): ?>
                                                                            <?php foreach ($spare_no_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                                                                    <td><span class="badge badge-danger">Out of Stock</span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="4" class="text-center">No out of stock spare parts</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Inventory Low Stock and No Stock Chart -->
                                <div class="min-w-0">
                                    <div class="card mb-6">
                                        <div class="card-header">
                                            <i class="fas fa-boxes mr-1"></i>
                                            Warehouse Status - Low Stock | No Stock Items
                                        </div>
                                        <div class="card-body">
                                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                <div class="min-w-0">
                                                    <div class="small-pie-chart-container">
                                                        <canvas id="inventoryPieChart" width="300" height="200"></canvas>
                                                    </div>
                                                </div>
                                                <div class="min-w-0 mt-6">
                                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                        <div class="min-w-0">
                                                            <h6 class="font-semibold">No Stock Items (<?php echo count($no_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Item Code</th>
                                                                            <th>Item Name</th>
                                                                            <th>Status</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($no_stock_items)): ?>
                                                                            <?php foreach ($no_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                                                    <td><span class="badge badge-danger">Out of Stock</span></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="3" class="text-center">No out of stock items</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="min-w-0 mt-4">
                                                            <h6 class="font-semibold">Low Stock Items (<?php echo count($low_stock_items); ?>)</h6>
                                                            <div style="max-height: 200px; overflow-y: auto;">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead>
                                                                         <tr>
                                                                            <th>Item Code</th>
                                                                            <th>Item Name</th>
                                                                            <th>Qty</th>
                                                                            <th>Min</th>
                                                                         </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php if (!empty($low_stock_items)): ?>
                                                                            <?php foreach ($low_stock_items as $item): ?>
                                                                                <tr>
                                                                                    <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                                                    <td><span class="badge badge-warning"><?php echo $item['quantity']; ?></span></td>
                                                                                    <td><?php echo $item['min_stock_level']; ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <tr>
                                                                                <td colspan="4" class="text-center">No low stock items</td>
                                                                            </tr>
                                                                        <?php endif; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Gasoline Purchase Orders with Completed Status -->
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="card mb-6">
                                        <div class="card-header">
                                            <i class="fas fa-gas-pump mr-1"></i>
                                            Gasoline PO - Completed Orders Per Month (<?php echo $current_year; ?>)
                                        </div>
                                        <div class="card-body">
                                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                                <div class="min-w-0">
                                                    <canvas id="gasolineChart" width="100%" height="40"></canvas>
                                                </div>
                                            </div>
                                            
                                            <div class="row mt-6">
                                                <div class="min-w-0">
                                                    <h6 class="font-semibold">Monthly Breakdown</h6>
                                                    <div style="max-height: 250px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Month</th>
                                                                    <th>PO Count</th>
                                                                    <th>Item Count</th>
                                                                    <th>Amount (₱)</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                $has_data = false;
                                                                for ($i = 0; $i < 12; $i++): 
                                                                    if ($gasoline_po_counts[$i] > 0 || $gasoline_amounts[$i] > 0):
                                                                        $has_data = true;
                                                                ?>
                                                                    <tr>
                                                                        <td><strong><?php echo $months_full[$i]; ?></strong></td>
                                                                        <td><?php echo $gasoline_po_counts[$i]; ?></td>
                                                                        <td><?php echo $gasoline_item_counts[$i]; ?></td>
                                                                        <td>₱<?php echo number_format($gasoline_amounts[$i], 2); ?></td>
                                                                    </tr>
                                                                <?php 
                                                                    endif;
                                                                endfor; 
                                                                if (!$has_data):
                                                                ?>
                                                                    <tr>
                                                                        <td colspan="4" class="text-center">No completed gasoline purchase orders found</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <div class="min-w-0 mt-4">
                                                    <h6 class="font-semibold">Gasoline Type Breakdown</h6>
                                                    <div style="max-height: 250px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Gasoline Type</th>
                                                                    <th>PO Count</th>
                                                                    <th>Item Count</th>
                                                                    <th>Total Amount (₱)</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if (!empty($gasoline_types_data)): ?>
                                                                    <?php foreach ($gasoline_types_data as $type): ?>
                                                                        <tr>
                                                                            <td><strong><?php echo htmlspecialchars($type['gasoline_type']); ?></strong></td>
                                                                            <td><?php echo $type['po_count']; ?></td>
                                                                            <td><?php echo $type['item_count']; ?></td>
                                                                            <td>₱<?php echo number_format($type['total_amount'], 2); ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <tr>
                                                                        <td colspan="4" class="text-center">No completed gasoline purchase orders found</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Monthly Expenses Chart -->
                                <div class="min-w-0">
                                    <div class="card mb-6">
                                        <div class="card-header">
                                            <i class="fas fa-chart-bar mr-1"></i>
                                            Monthly Expenses Overview - <?php echo $current_year; ?>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="expensesChart" width="100%" height="40"></canvas>
                                            
                                            <div class="row mt-6">
                                                <div class="min-w-0">
                                                    <h6 class="font-semibold">Monthly Breakdown</h6>
                                                    <div style="max-height: 200px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Month</th>
                                                                    <th>Amount (₱)</th>
                                                                    <th>Transactions</th>
                                                                 </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                $has_expense_data = false;
                                                                for ($i = 0; $i < 12; $i++): 
                                                                    if ($expense_amounts[$i] > 0 || $expense_counts[$i] > 0):
                                                                        $has_expense_data = true;
                                                                ?>
                                                                    <tr>
                                                                        <td><strong><?php echo $months_full[$i]; ?></strong></td>
                                                                        <td>₱<?php echo number_format($expense_amounts[$i], 2); ?></td>
                                                                        <td><?php echo $expense_counts[$i]; ?></td>
                                                                    </tr>
                                                                <?php 
                                                                    endif;
                                                                endfor; 
                                                                if (!$has_expense_data):
                                                                ?>
                                                                    <tr>
                                                                        <td colspan="3" class="text-center">No expense records found for <?php echo $current_year; ?></td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row mt-4">
                                                <div class="min-w-0">
                                                    <h6 class="font-semibold">Expense Types Breakdown</h6>
                                                    <div style="max-height: 200px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered">
                                                            <thead>
                                                                 <tr>
                                                                    <th>Expense Type</th>
                                                                    <th>Transactions</th>
                                                                    <th>Total Amount (₱)</th>
                                                                  </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if (!empty($expense_types)): ?>
                                                                    <?php foreach ($expense_types as $type): ?>
                                                                        <?php if ($type['transaction_count'] > 0): ?>
                                                                            <td><strong><?php echo htmlspecialchars($type['expense_name']); ?></strong></td>
                                                                            <td><?php echo $type['transaction_count']; ?></td>
                                                                            <td>₱<?php echo number_format($type['total_amount'], 2); ?></td>
                                                                        </tr>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <tr>
                                                                        <td colspan="3" class="text-center">No expense types found</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Late Employees Table -->
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-1">
                                <div class="min-w-0">
                                    <div class="card mb-6">
                                        <div class="card-header">
                                            <i class="fas fa-table mr-1"></i>
                                            Top 5 Employees with Most Late Attendances
                                        </div>
                                        <div class="card-body">
                                            <table id="lateEmployeesTable" class="table table-bordered table-striped">
                                                <thead>
                                                        <th>Employee Name</th>
                                                        <th>Department</th>
                                                        <th>Times Late</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($late_employees)): ?>
                                                        <?php foreach ($late_employees as $employee): ?>
                                                            <tr>
                                                                <td><strong><?php echo htmlspecialchars($employee['employee_name']); ?></strong></td>
                                                                <td><?php echo htmlspecialchars($employee['department'] ?? 'Not Assigned'); ?></td>
                                                                <td>
                                                                    <span class="badge badge-danger"><?php echo $employee['late_count']; ?> times</span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="3" class="text-center">No late attendance records found after 7:30 AM</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
                <?php
        /* Data island consumed by assets/js/dashboard.js. */
        $__ocp_data = [];
        /* The spare parts counts feed the Motorpool pie chart, which the Purchaser's page renders
         * too. They were supplied for Motorpool alone, so the Purchaser's copy of that chart was
         * built from undefined values. */
        /* spareNoStockCount = $spare_no_stock_count [guarded] */
        if ($is_motorpool || $is_admin_purchaser) {
            $__ocp_data["spareNoStockCount"] = ocp_dashboard_count($spare_no_stock_count ?? null);
        }
        /* spareLowStockCount = $spare_low_stock_count [guarded] */
        if ($is_motorpool || $is_admin_purchaser) {
            $__ocp_data["spareLowStockCount"] = ocp_dashboard_count($spare_low_stock_count ?? null);
        }
        /* spareAdequateStockCount = $spare_adequate_stock_count [guarded] */
        if ($is_motorpool || $is_admin_purchaser) {
            $__ocp_data["spareAdequateStockCount"] = ocp_dashboard_count($spare_adequate_stock_count ?? null);
        }
        /* The warehouse counts feed the Warehouse pie chart, likewise rendered on the Purchaser's
         * page as well as the Warehouse user's own. */
        /* noStockCount = $no_stock_count [guarded] */
        if ($is_warehouse || $is_admin_purchaser) {
            $__ocp_data["noStockCount"] = ocp_dashboard_count($no_stock_count ?? null);
        }
        /* lowStockCount = $low_stock_count [guarded] */
        if ($is_warehouse || $is_admin_purchaser) {
            $__ocp_data["lowStockCount"] = ocp_dashboard_count($low_stock_count ?? null);
        }
        /* adequateStockCount = $adequate_stock_count [guarded] */
        if ($is_warehouse || $is_admin_purchaser) {
            $__ocp_data["adequateStockCount"] = ocp_dashboard_count($adequate_stock_count ?? null);
        }
        /* months: the expenses chart and the gasoline chart both need the labels, so the
         * Accounting user's page is not the only one that reads it. */
        /* months [guarded] */
        if ($is_admin_accounting || $is_admin_purchaser) {
            ob_start();
            include __DIR__ . "/includes/partials/dashboard/months.php";
            $__ocp_data["months"] = ocp_dashboard_list(ob_get_clean());
        }
        /* expenseAmounts [guarded] */
        if ($is_admin_accounting) {
            ob_start();
            include __DIR__ . "/includes/partials/dashboard/expenseAmounts.php";
            $__ocp_data["expenseAmounts"] = ocp_dashboard_list(ob_get_clean());
        }
        /* expenseCounts [guarded] */
        if ($is_admin_accounting) {
            ob_start();
            include __DIR__ . "/includes/partials/dashboard/expenseCounts.php";
            $__ocp_data["expenseCounts"] = ocp_dashboard_list(ob_get_clean());
        }
        /* gasolineAmounts [guarded] */
        if ($is_admin_purchaser) {
            ob_start();
            include __DIR__ . "/includes/partials/dashboard/gasolineAmounts.php";
            $__ocp_data["gasolineAmounts"] = ocp_dashboard_list(ob_get_clean());
        }
        /* gasolinePoCounts [guarded] */
        if ($is_admin_purchaser) {
            ob_start();
            include __DIR__ . "/includes/partials/dashboard/gasolinePoCounts.php";
            $__ocp_data["gasolinePoCounts"] = ocp_dashboard_list(ob_get_clean());
        }
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["isMotorpool"] = ((bool) $is_motorpool);
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["isWarehouse"] = ((bool) $is_warehouse);
        $__ocp_data["isHrOfficer"] = ((bool) $is_admin_hr_officer);
        $__ocp_data["isAccounting"] = ((bool) $is_admin_accounting);
        $__ocp_data["isPurchaser"] = ((bool) $is_admin_purchaser);
        ocp_page_data("dashboard", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/dashboard.js.php"></script>
    </body>
</html>

