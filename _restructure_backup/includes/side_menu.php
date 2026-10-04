<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'db_config.php';

// Get current user details
$user_id = $_SESSION['user_id'];
$userStmt = $pdo->prepare("SELECT department, position, accounttype FROM users WHERE id = :id");
$userStmt->bindParam(':id', $user_id);
$userStmt->execute();
$current_user = $userStmt->fetch(PDO::FETCH_ASSOC);

// NEW: Check if user can view Master Data section
$can_view_master_data = false;
if (($current_user['department'] == 'Admin' && in_array($current_user['position'], ['CEO', 'HR Officer', 'Purchaser'])) ||
    $current_user['department'] == 'Warehouse' ||
    $current_user['department'] == 'Motorpool' ||
    $current_user['department'] == 'Engineering') {
    $can_view_master_data = true;
}

// Check if user can view Administration menu
$can_view_administration = false;
if ($current_user['department'] == 'Admin') {
    if ($current_user['position'] == 'HR Officer' || $current_user['position'] == 'CEO') {
        $can_view_administration = true;
    }
}

// Check if user can view Warehouse Management menus
$can_view_warehouse = false;
if ($current_user['department'] == 'Warehouse') {
    $can_view_warehouse = true;
} elseif ($current_user['department'] == 'Admin' && ($current_user['position'] == 'Purchaser' || $current_user['position'] == 'CEO')) {
    $can_view_warehouse = true;
}

// Check if user can view Expenses Management menu
$can_view_expenses = false;
if ($current_user['department'] == 'Admin' && ($current_user['position'] == 'Accounting' || $current_user['position'] == 'CEO')) {
    $can_view_expenses = true;
}

// Check if user can view Motorpool Management menus
$can_view_motorpool = false;
if ($current_user['department'] == 'Motorpool') {
    $can_view_motorpool = true;
} elseif ($current_user['department'] == 'Admin' && ($current_user['position'] == 'Purchaser' || $current_user['position'] == 'CEO')) {
    $can_view_motorpool = true;
}

// NEW: Check if user can view Inventory section
$can_view_inventory = false;
if ($current_user['department'] == 'Motorpool') {
    $can_view_inventory = true;
} elseif ($current_user['department'] == 'Warehouse') {
    $can_view_inventory = true;
} elseif ($current_user['department'] == 'Admin' && $current_user['position'] == 'Purchaser') {
    $can_view_inventory = true;
} elseif ($current_user['department'] == 'Admin' && $current_user['position'] == 'CEO') {
    $can_view_inventory = true;
}

// NEW: Check if user can view Fuel Management, Fleet Management, Purchase Management, and Reports
$can_view_purchase_sections = false;
if ($current_user['department'] == 'Admin' && ($current_user['position'] == 'Purchaser' || $current_user['position'] == 'CEO')) {
    $can_view_purchase_sections = true;
}

// NEW: Check if user can view Purchase Management section and Warehouse PR
$can_view_purchase_management = false;
if (($current_user['department'] == 'Admin' && in_array($current_user['position'], ['CEO', 'Accounting', 'Purchaser'])) ||
    $current_user['department'] == 'Warehouse' ||
    $current_user['department'] == 'Engineering') {
    $can_view_purchase_management = true;
}

// NEW: Check if user can view Motorpool PR specifically
$can_view_motorpool_pr = false;
if (($current_user['department'] == 'Admin' && in_array($current_user['position'], ['CEO', 'Purchaser'])) ||
    $current_user['department'] == 'Motorpool') {
    $can_view_motorpool_pr = true;
}

// NEW: Check if user can view PO Fuel specifically
$can_view_po_fuel = false;
if ($current_user['department'] == 'Admin' && in_array($current_user['position'], ['CEO', 'Purchaser'])) {
    $can_view_po_fuel = true;
}

// NEW: Check if user can view System section (Backup Database)
$can_view_system = false;
if ($current_user['department'] == 'Admin' && $current_user['position'] == 'CEO') {
    $can_view_system = true;
}

// NEW: Check if user can view Projects menu
$can_view_projects = false;
if (($current_user['department'] == 'Admin' && ($current_user['position'] == 'CEO' || $current_user['position'] == 'HR Officer' || $current_user['position'] == 'Purchaser')) ||
    ($current_user['department'] == 'Engineering')) {
    $can_view_projects = true;
}

// Get pending PR routing notifications for the current user
$pending_notifications = [];
$notification_count = 0;

try {
    // Define routing sequence to match user criteria
    $routing_sequence = [
        1 => [
            'department' => 'Warehouse',
            'position' => 'Warehouse Manager',
            'accounttype' => 'Admin',
            'name' => 'Warehouse Department'
        ],
        2 => [
            'department' => 'Admin',
            'position' => 'Purchaser',
            'accounttype' => 'Admin',
            'name' => 'Purchasing Department'
        ],
        3 => [
            'department' => 'BAC',
            'position' => 'Chairman',
            'accounttype' => 'Admin',
            'name' => 'BAC Office'
        ],
        4 => [
            'department' => 'Admin',
            'position' => 'Accounting',
            'accounttype' => 'Admin',
            'name' => 'Accounting Department'
        ],
        5 => [
            'department' => 'Warehouse',
            'position' => 'Warehouse Manager',
            'accounttype' => 'Admin',
            'name' => 'Warehouse Department (Final)'
        ]
    ];
    
    // Check if current user matches any step criteria
    $user_steps = [];
    foreach ($routing_sequence as $step => $criteria) {
        if ($current_user['department'] == $criteria['department'] && 
            $current_user['position'] == $criteria['position'] && 
            $current_user['accounttype'] == $criteria['accounttype']) {
            $user_steps[] = $step;
        }
    }
    
    // If user matches any step criteria, get pending PRs for those steps
    if (!empty($user_steps)) {
        $placeholders = str_repeat('?,', count($user_steps) - 1) . '?';
        $notificationStmt = $pdo->prepare("
            SELECT 
                prr.purchase_request_id,
                prr.current_step,
                prr.current_status,
                pr.purchase_request_no,
                pr.date,
                p.project_name,
                u.firstname,
                u.middlename,
                u.lastname,
                u.suffix,
                prr.created_at as routing_updated
            FROM pr_routing prr
            INNER JOIN purchase_requests pr ON prr.purchase_request_id = pr.id
            LEFT JOIN projects p ON pr.project_id = p.id
            LEFT JOIN users u ON pr.created_by = u.id
            WHERE prr.current_step IN ($placeholders)
            AND prr.current_status IN ('pending', 'in_progress','returned')
            ORDER BY prr.updated_at DESC
            LIMIT 10
        ");
        
        $notificationStmt->execute($user_steps);
        $pending_notifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC);
        $notification_count = count($pending_notifications);
        
        // Format notification data
        foreach ($pending_notifications as &$notification) {
            // Format creator name
            $creator_name = $notification['firstname'];
            if (!empty($notification['middlename'])) {
                $creator_name .= ' ' . substr($notification['middlename'], 0, 1) . '.';
            }
            $creator_name .= ' ' . $notification['lastname'];
            if (!empty($notification['suffix'])) {
                $creator_name .= ' ' . $notification['suffix'];
            }
            $notification['formatted_creator'] = $creator_name;
            
            // Format date
            $notification['formatted_date'] = date('M j, Y', strtotime($notification['date']));
            $notification['formatted_routing_date'] = date('M j, Y g:i A', strtotime($notification['routing_updated']));
            
            // Get step name
            $step = $notification['current_step'];
            $step_names = [
                1 => 'Warehouse Department',
                2 => 'Purchasing Department', 
                3 => 'BAC Office',
                4 => 'Accounting Department',
                5 => 'Warehouse Department (Final)'
            ];
            $notification['step_name'] = isset($step_names[$step]) ? $step_names[$step] : "Step $step";
            
            // Store PR ID directly (no encryption needed)
            $notification['pr_id'] = $notification['purchase_request_id'];
        }
        unset($notification); // Break reference
    }
    
} catch (PDOException $e) {
    // Log error but don't show to user
    error_log("Notification error: " . $e->getMessage());
    $pending_notifications = [];
    $notification_count = 0;
}
?>

<div id="layoutSidenav_nav">
    <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
        <div class="sb-sidenav-menu">
            <div class="nav">
                <!-- Dashboard -->
                <div class="sb-sidenav-menu-heading">Core</div>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                    Dashboard
                </a>
                
                <!-- Master Data Section - Only show for specified users -->
                <?php if ($can_view_master_data): ?>
                <div class="sb-sidenav-menu-heading">Master Data</div>
                <?php endif; ?>
                
                <!-- Projects - Only show for Admin (CEO, HR Officer, Purchaser) or Engineering department -->
                <?php if ($can_view_projects): ?>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : ''; ?>" href="projects.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-project-diagram"></i></div>
                    Projects
                </a>
                <?php endif; ?>
                
                <!-- Materials Management Collapse - Only show for users with warehouse access -->
                <?php if ($can_view_warehouse): ?>
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseMasterData" aria-expanded="false" aria-controls="collapseMasterData">
                    <div class="sb-nav-link-icon"><i class="fas fa-database"></i></div>
                    Warehouse Management
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse" id="collapseMasterData" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'items_categories.php' ? 'active' : ''; ?>" href="items_categories.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-tags"></i></div>
                            Items Categories
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'item_names.php' ? 'active' : ''; ?>" href="item_names.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-box"></i></div>
                            Item Names
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'active' : ''; ?>" href="suppliers.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-truck-loading"></i></div>
                            Suppliers
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'warehouses.php' ? 'active' : ''; ?>" href="warehouses.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-warehouse"></i></div>
                            Warehouses
                        </a>
                    </nav>
                </div>
                <?php endif; ?>

                <!-- Spare Parts Management Collapse - Only show for users with motorpool access -->
                <?php if ($can_view_motorpool): ?>
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseSpareParts" aria-expanded="false" aria-controls="collapseSpareParts">
                    <div class="sb-nav-link-icon"><i class="fas fa-cogs"></i></div>
                    Motorpool Management
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse" id="collapseSpareParts" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'spare_parts_categories.php' ? 'active' : ''; ?>" href="spare_parts_categories.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-tags"></i></div>
                            Spare Parts Categories
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'spare_parts.php' ? 'active' : ''; ?>" href="spare_parts.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-cog"></i></div>
                            Spare Parts Name
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'spare_parts_suppliers.php' ? 'active' : ''; ?>" href="spare_parts_suppliers.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-truck-loading"></i></div>
                            Spare Parts Suppliers
                        </a>
                    </nav>
                </div>
                <?php endif; ?>

                <!-- Fuel Management Collapse - Only show for Admin/Purchaser or Admin/CEO -->
                <?php if ($can_view_purchase_sections): ?>
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseFuel" aria-expanded="false" aria-controls="collapseFuel">
                    <div class="sb-nav-link-icon"><i class="fas fa-gas-pump"></i></div>
                    Fuel Management
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse" id="collapseFuel" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'gasoline_suppliers.php' ? 'active' : ''; ?>" href="gasoline_suppliers.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-truck"></i></div>
                            Suppliers
                        </a>
                        <!-- <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'gasoline_tank.php' ? 'active' : ''; ?>" href="gasoline_tank.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-oil-can"></i></div>
                            Tanks
                        </a> -->
                    </nav>
                </div>
                <?php endif; ?>

                <!-- Fleet Management Collapse - Only show for Admin/Purchaser or Admin/CEO -->
                <?php if ($can_view_purchase_sections): ?>
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseFleet" aria-expanded="false" aria-controls="collapseFleet">
                    <div class="sb-nav-link-icon"><i class="fas fa-car"></i></div>
                    Fleet Management
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse" id="collapseFleet" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'vehicles.php' ? 'active' : ''; ?>" href="vehicles.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-car-side"></i></div>
                            Vehicles
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'heavy_equipment.php' ? 'active' : ''; ?>" href="heavy_equipment.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-tools"></i></div>
                            Heavy Equipments
                        </a>
                    </nav>
                </div>
                <?php endif; ?>

                <!-- Purchase Request Section - Only show for specified users -->
                <?php if ($can_view_purchase_management || $can_view_motorpool_pr): ?>
                <div class="sb-sidenav-menu-heading">Purchase Management</div>
                <?php endif; ?>
                
                <!-- Warehouse PR - Only show for users with purchase management access -->
                <?php if ($can_view_purchase_management): ?>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'purchase_request.php' ? 'active' : ''; ?>" href="purchase_request.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-shopping-cart"></i></div>
                    Warehouse PR
                </a>
                <?php endif; ?>
                
                <!-- PR Spare Parts Menu Item - Only show for Admin (CEO, Purchaser) or Motorpool -->
                <?php if ($can_view_motorpool_pr): ?>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'purchase_request_spare_parts.php' ? 'active' : ''; ?>" href="purchase_request_spare_parts.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-wrench"></i></div>
                    Motorpool PR
                </a>
                <?php endif; ?>
                
                <!-- PO Fuel Menu Item - Only show for Admin (CEO or Purchaser) -->
                <?php if ($can_view_po_fuel): ?>
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'gasoline_purchase_order.php' ? 'active' : ''; ?>" href="gasoline_purchase_order.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-gas-pump"></i></div>
                    PO Fuel
                </a>
                <?php endif; ?>

                <!-- Expenses Management Section - Only show for Admin/Accounting and Admin/CEO -->
                <?php if ($can_view_expenses): ?>
                <div class="sb-sidenav-menu-heading">Expenses Management</div>

                <!-- Cash on Hand Menu Item (NEW) -->
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cash_on_hand.php' ? 'active' : ''; ?>" href="cash_on_hand.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-wallet"></i></div>
                    Cash on Hand
                </a>

                <!-- Expenses Type Menu Item -->
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'expenses_type.php' ? 'active' : ''; ?>" href="expenses_type.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-tag"></i></div>
                    Expenses Type
                </a>

                <!-- Expenses Menu Item -->
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'active' : ''; ?>" href="expenses.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-money-bill-wave"></i></div>
                    Expenses
                </a>
                <?php endif; ?>

                <!-- Inventory Section - Only show for users with inventory access -->
                <?php if ($can_view_inventory): ?>
                <div class="sb-sidenav-menu-heading">Inventory</div>
                
                <!-- Inventory Collapse -->
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseInventory" aria-expanded="false" aria-controls="collapseInventory">
                    <div class="sb-nav-link-icon"><i class="fas fa-clipboard-list"></i></div>
                    Inventory Management
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse" id="collapseInventory" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <?php if ($current_user['department'] == 'Warehouse' || ($current_user['department'] == 'Admin' && ($current_user['position'] == 'Purchaser' || $current_user['position'] == 'CEO'))): ?>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>" href="inventory.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-boxes"></i></div>
                            Warehouse Inventory
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($current_user['department'] == 'Motorpool' || ($current_user['department'] == 'Admin' && ($current_user['position'] == 'Purchaser' || $current_user['position'] == 'CEO'))): ?>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'spare_parts_inventory.php' ? 'active' : ''; ?>" href="spare_parts_inventory.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-cogs"></i></div>
                            Motorpool Inventory
                        </a>
                        <?php endif; ?>
                        <!-- <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'gasoline_inventory.php' ? 'active' : ''; ?>" href="gasoline_inventory.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-gas-pump"></i></div>
                            Fuel Inventory
                        </a> -->
                    </nav>
                </div>
                <?php endif; ?>

                <!-- Reports Section - Only show for Admin/Purchaser or Admin/CEO -->
                <?php if ($can_view_purchase_sections): ?>
                <div class="sb-sidenav-menu-heading">Reports</div>
                
                <!-- Fuel Report Menu Item -->
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'fuel_report.php' ? 'active' : ''; ?>" href="fuel_report.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-chart-line"></i></div>
                    Fuel Report
                </a>
                <?php endif; ?>

                <!-- User Management Section - Only show for HR Officer and CEO -->
                <?php if ($can_view_administration): ?>
                <div class="sb-sidenav-menu-heading">Administration</div>
                
                <!-- User Management Collapse -->
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseUsers" aria-expanded="false" aria-controls="collapseUsers">
                    <div class="sb-nav-link-icon"><i class="fas fa-users-cog"></i></div>
                    User Management
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse" id="collapseUsers" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'employee_registration.php' ? 'active' : ''; ?>" href="employee_registration.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-user-friends"></i></div>
                            Employees
                        </a>
                        <!-- Upload Attendance Menu Item -->
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'upload_attendance.php' ? 'active' : ''; ?>" href="upload_attendance.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-upload"></i></div>
                            Upload Attendance
                        </a>
                        <!-- Payroll Menu Item (NEW) -->
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'payroll.php' ? 'active' : ''; ?>" href="payroll.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                            Payroll
                        </a>
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'registration.php' ? 'active' : ''; ?>" href="registration.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-user-plus"></i></div>
                            User Accounts
                        </a>
                    </nav>
                </div>
                <?php endif; ?>

                <!-- System Section - Only show for Admin/CEO -->
                <?php if ($can_view_system): ?>
                <div class="sb-sidenav-menu-heading">System</div>
                
                <!-- Backup Menu Item -->
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'backup_sql.php' ? 'active' : ''; ?>" href="backup_sql.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-database"></i></div>
                    Backup Database
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="sb-sidenav-footer">
            <div class="small">Logged in as:</div>
            <span style="color: #48e66dff;">
                <?php echo htmlspecialchars($display_name); ?>
            </span>
        </div>
    </nav>
</div>