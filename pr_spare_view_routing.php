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
// Get user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix, department, position, accounttype FROM users WHERE id = :id");
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

// Check if PR ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: purchase_request_spare_parts.php');
    exit();
}

$pr_id = $_GET['id'];

// Check for session-based SweetAlert data. This sat immediately before the closing PHP
// tag in the original page; the routing actions leave a message here when they redirect
// back, and the form below shows it, so it is read on every load.
$swal_data = array();
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

// All of this page's actions live in one file: the routing decisions - approving,
// rejecting, converting to a purchase order, a withdrawal slip or a job order, and
// the per-item stock handling. The forms post back to this page, so it is pulled in
// before anything is read or rendered - and after the header above, because the
// handlers work from $pr_id, $user and the request they route. It also carries
// getStatusBadge() and formatUserName(), which the markup below calls.
if (!defined('OCP_PR_SPARE_VIEW_ROUTING_ACTIONS_RAN')) {
    require __DIR__ . '/actions/pr_spare_view_routing-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup and the handlers above need, which are unpacked into this scope. The read
// block behind it works out, from the request itself, what it can become.
$ocp_endpoint = require __DIR__ . '/api/pr_spare_view_routing-endpoint.php';
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
    <title>PR Routing - <?php echo htmlspecialchars($pr['pr_number']); ?> - OCP Construction</title>
    <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link href="assets/css/app.css" rel="stylesheet" />
        <link href="assets/css/app.build.css" rel="stylesheet" />
</head>
<body class="sb-nav-fixed">
    <?php include 'includes/top_bar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'includes/side_menu.php'; ?>
        <div id="layoutSidenav_content" class="sb-content">
            <main>
                <div class="w-full px-6">
                    <div class="mb-6">
                    <h1 class="page-title">
                        <?php 
                        if ($pr['request_type'] === 'stock'): 
                            echo 'Motorpool Spare Parts/Materials (STOCK)'; 
                        elseif ($pr['request_type'] === 'issue'): 
                            echo 'Motorpool Spare Parts (JO)'; 
                        elseif ($pr['request_type'] === 'issue_materials'): 
                            echo 'Motorpool Materials (WS)'; 
                        else: 
                            echo 'PR Routing - ' . htmlspecialchars($pr['pr_number']); 
                        endif; 
                        ?>
                    </h1>
                    <ol class="breadcrumb mb-6">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item">
                            <a href="purchase_request_spare_parts.php">
                                <?php 
                                if ($pr['request_type'] === 'stock'): 
                                    echo 'Spare Parts & Materials PR';
                                elseif ($pr['request_type'] === 'issue'): 
                                    echo 'Spare Parts JO';
                                elseif ($pr['request_type'] === 'issue_materials'): 
                                    echo 'Materials WS';
                                else: 
                                    echo 'Spare Parts & Materials PR';
                                endif; 
                                ?>
                            </a>
                        </li>
                        <li class="breadcrumb-item active">
                            <?php 
                            if ($pr['request_type'] === 'stock'): 
                                echo 'PR Routing';
                            elseif ($pr['request_type'] === 'issue'): 
                                echo 'JO Routing';
                            elseif ($pr['request_type'] === 'issue_materials'): 
                                echo 'WS Routing';
                            else: 
                                echo 'PR Routing';
                            endif; 
                            ?>
                        </li>
                    </ol>
                    </div>

                    <!-- PR Details Card - ADD ROUTING ACTIONS BUTTON HERE -->
                    <div class="card mb-6">
                        <div class="card-header">
                            <i class="fas fa-info-circle mr-1"></i>
                            Spare Parts Purchase Request Details
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th>Request Type:</th>
                                            <td>
                                                <span class="<?php echo getRequestTypeBadge($pr['request_type'] ?? 'stock'); ?>">
                                                    <?php echo getRequestTypeText($pr['request_type'] ?? 'stock'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php 
                                        // Check request type to determine if we should show the number at all
                                        $show_number_line = true;
                                        $number_label = 'PR Number:';

                                        if ($pr['request_type'] === 'issue_materials'): 
                                            $show_number_line = false;
                                        elseif ($pr['request_type'] === 'issue'): 
                                            // Hide the number line completely for Issue Parts Purchase Request
                                            $show_number_line = false;
                                        endif; 

                                        if ($show_number_line): 
                                        ?>
                                        <tr>
                                            <th width="40%"><?php echo $number_label; ?></th>
                                            <td>
                                                <?php echo htmlspecialchars($pr['pr_number']); ?>
                                                <span class="<?php echo getStatusBadge($pr['status']); ?> ml-2">
                                                    <?php echo ucfirst($pr['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <!-- ADD JOB ORDER NUMBER FOR ISSUE REQUESTS -->
                                        <?php if ($pr['request_type'] === 'issue' && $job_order_exists): ?>
                                        <tr>
                                            <th>Job Order Number:</th>
                                            <td>
                                                <?php echo htmlspecialchars($job_order_details['job_order_number']); ?>
                                                <span class="badge 
                                                    <?php 
                                                    switch(strtolower($job_order_details['status'])) {
                                                        case 'draft': echo 'badge-primary'; break;
                                                        case 'pending': echo 'badge-warning'; break;
                                                        case 'approved': echo 'badge-success'; break;
                                                        case 'released': echo 'badge-success'; break;
                                                        case 'confirmed': echo 'badge-success'; break;
                                                        case 'completed': echo 'badge-success'; break;
                                                        case 'cancelled': echo 'badge-danger'; break;
                                                        default: echo 'badge-neutral';
                                                    }
                                                    ?> ml-2">
                                                    <?php echo ucfirst($job_order_details['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <!-- ADD PO NUMBER WITH STATUS -->
                                        <?php if (!empty($existing_pos)): ?>
                                        <tr>
                                            <th>PO Number:</th>
                                            <td>
                                                <?php 
                                                $po_numbers = array();
                                                foreach ($existing_pos as $po) {
                                                    $po_status_class = '';
                                                    switch($po['status']) {
                                                        case 'draft': $po_status_class = 'badge-neutral'; break;
                                                        case 'pending': $po_status_class = 'badge-warning'; break;
                                                        case 'approved': $po_status_class = 'badge-success'; break;
                                                        case 'confirmed': $po_status_class = 'badge-success'; break;
                                                        case 'delivered': $po_status_class = 'badge-success'; break;
                                                        case 'partially_received': $po_status_class = 'badge-warning'; break;
                                                        case 'cancelled': $po_status_class = 'badge-danger'; break;
                                                        case 'completed': $po_status_class = 'badge-success'; break;
                                                        default: $po_status_class = 'badge-neutral';
                                                    }
                                                    echo htmlspecialchars($po['po_number']) . ' <span class="badge ' . $po_status_class . '">' . ucfirst($po['status']) . '</span><br>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>

                                        <!-- ADD THIS BLOCK FOR WS NUMBER - Display only for Issue Materials Purchase Request -->
                                        <?php if ($pr['request_type'] === 'issue_materials'): ?>
                                            <?php if (!empty($withdrawal_slip_details) && !empty($withdrawal_slip_details['withdrawal_slip_number'])): ?>
                                            <tr>
                                                <th>WS Number:</th>
                                                <td>
                                                    <?php echo htmlspecialchars($withdrawal_slip_details['withdrawal_slip_number']); ?>
                                                    <span class="badge 
                                                        <?php 
                                                        switch(strtolower($withdrawal_slip_details['status'] ?? '')) {
                                                            case 'draft': echo 'badge-info'; break;
                                                            case 'pending': echo 'badge-warning'; break;
                                                            case 'approved': echo 'badge-success'; break;
                                                            case 'released': echo 'badge-success'; break;
                                                            case 'completed': echo 'badge-success'; break;
                                                            case 'cancelled': echo 'badge-danger'; break;
                                                            default: echo 'badge-neutral';
                                                        }
                                                        ?> ml-2">
                                                        <?php echo ucfirst($withdrawal_slip_details['status'] ?? 'Pending'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        
                                        <?php if ($is_issue_type): ?>
                                            <!-- For issue and issue_materials requests: Show technician and purpose -->
                                            
                                            <!-- Hide Technician for Issue Materials -->
                                            <!-- Display Technician for Issue Parts Purchase Request -->
                                        <?php if (!$is_issue_materials): ?>
                                        <tr>
                                            <th>Technician:</th>
                                            <td>
                                                <?php 
                                                // Check if we have technician data from the employee table
                                                if (!empty($pr['tech_firstname'])) {
                                                    $technician_name = $pr['tech_firstname'];
                                                    if (!empty($pr['tech_middlename'])) {
                                                        $technician_name .= ' ' . substr($pr['tech_middlename'], 0, 1) . '.';
                                                    }
                                                    $technician_name .= ' ' . $pr['tech_lastname'];
                                                    if (!empty($pr['tech_suffix'])) {
                                                        $technician_name .= ' ' . $pr['tech_suffix'];
                                                    }
                                                    // Optionally add position
                                                    if (!empty($pr['tech_position'])) {
                                                        $technician_name .= ' (' . $pr['tech_position'] . ')';
                                                    }
                                                    echo htmlspecialchars($technician_name);
                                                } else {
                                                    // If no technician data found, show the ID or N/A
                                                    echo htmlspecialchars($pr['technician'] ?? 'N/A');
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>

                                            <tr>
                                                <th>Purpose:</th>
                                                <td><?php echo htmlspecialchars($pr['purpose'] ?? 'N/A'); ?></td>
                                            </tr>

                                        <?php else: ?>
                                            <!-- For stock requests: Show supplier -->
                                            <tr>
                                                <th>Supplier:</th>
                                                <td><?php echo htmlspecialchars($pr['supplier_name'] ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                <div class="min-w-0">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="40%">Request Date:</th>
                                            <td><?php echo htmlspecialchars(ocp_date_mdy($pr['request_date'])); ?></td>
                                        </tr>
                                        
                                        <!-- Show different labels based on request type -->
                                        <?php if ($is_issue_materials): ?>
                                            <!-- For Issue Materials Request: Show "Prepared By:" -->
                                            <tr>
                                                <th>Prepared By:</th>
                                                <td><?php echo formatUserName($pr); ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <!-- For other request types: Show "Requested By:" -->
                                            <tr>
                                                <th>Requested By:</th>
                                                <td><?php echo formatUserName($pr); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        
                                        <!-- Hide Department for Issue Materials -->
                                        <?php if (!$is_issue_materials): ?>
                                        <tr>
                                            <th>Department:</th>
                                            <td><?php echo htmlspecialchars($pr['department'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <?php endif; ?>

                                        <?php if ($is_issue_materials): ?>
                                        <!-- For issue_materials requests: Show Employee -->
                                        <tr>
                                            <th>Issue to Employee:</th>
                                            <td>
                                                <?php 
                                                // FIXED: Check if employee_display_name exists and is not empty
                                                if (!empty($pr['employee_display_name']) && $pr['employee_display_name'] !== 'N/A') {
                                                    echo htmlspecialchars($pr['employee_display_name']);
                                                } else {
                                                    // Try to format the employee name from the fetched data
                                                    if (!empty($pr['emp_firstname'])) {
                                                        $employee_name = $pr['emp_firstname'];
                                                        if (!empty($pr['emp_middlename'])) {
                                                            $employee_name .= ' ' . substr($pr['emp_middlename'], 0, 1) . '.';
                                                        }
                                                        $employee_name .= ' ' . $pr['emp_lastname'];
                                                        if (!empty($pr['emp_suffix'])) {
                                                            $employee_name .= ' ' . $pr['emp_suffix'];
                                                        }
                                                        echo htmlspecialchars($employee_name);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>

                                         <!-- Add Vehicle/Equipment for Issue Parts Purchase Request -->
                                        <?php if ($pr['request_type'] === 'issue'): ?>
                                        <tr>
                                            <th>Vehicle/Equipment:</th>
                                            <td>
                                                <?php 
                                                if (!empty($pr['vehicle_name']) && !empty($pr['plate_number'])) {
                                                    echo htmlspecialchars($pr['vehicle_name'] . ' (' . $pr['plate_number'] . ')');
                                                } elseif (!empty($pr['equipment_name'])) {
                                                    echo htmlspecialchars($pr['equipment_name']);
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>

                                        <!-- NEW: Add Driver field here -->
                                        <?php if ($pr['request_type'] === 'issue'): ?>
                                        <tr>
                                            <th>Driver:</th>
                                            <td>
                                                <?php 
                                                // Check if we have driver data from the employee table
                                                if (!empty($pr['driver_firstname'])) {
                                                    $driver_name = $pr['driver_firstname'];
                                                    if (!empty($pr['driver_middlename'])) {
                                                        $driver_name .= ' ' . substr($pr['driver_middlename'], 0, 1) . '.';
                                                    }
                                                    $driver_name .= ' ' . $pr['driver_lastname'];
                                                    if (!empty($pr['driver_suffix'])) {
                                                        $driver_name .= ' ' . $pr['driver_suffix'];
                                                    }
                                                    // Optionally add position
                                                    if (!empty($pr['driver_position'])) {
                                                        $driver_name .= ' (' . $pr['driver_position'] . ')';
                                                    }
                                                    echo htmlspecialchars($driver_name);
                                                } else {
                                                    // If no driver data found, show the ID or N/A
                                                    if (!empty($pr['driver_id'])) {
                                                        // Try to fetch driver name directly if not in join
                                                        echo 'Driver ID: ' . htmlspecialchars($pr['driver_id']);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <?php if (!$is_issue_type): ?>
                                        <tr>
                                            <th>Total Estimated Cost:</th>
                                            <td>₱<?php echo number_format($total_estimated_cost, 2); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Horizontal Routing Progress -->
                            <div class="mt-6">
                                <h6 class="text-base font-semibold mb-4"><i class="fas fa-project-diagram mr-1"></i> Routing Progress</h6>
                                <div class="flex justify-between py-5 relative mb-5 w-full before:absolute before:top-[45px] before:left-0 before:right-0 before:h-[3px] before:bg-slate-200 before:content-['']">
                                    <?php
                                    // Define stages based on request type
                                    $stages = [];
                                    if ($is_issue_type) {
                                        $stages = [
                                            'requestor' => ['label' => 'Requestor', 'icon' => 'fa-user'],
                                            'warehouse' => ['label' => 'Motorpool', 'icon' => 'fa-warehouse'],
                                            'purchasing' => ['label' => 'Purchasing', 'icon' => 'fa-shopping-cart'],
                                            'approver' => ['label' => 'Approver (CEO)', 'icon' => 'fa-user-check'],
                                            'warehouse_releasing' => ['label' => 'Motorpool Releasing', 'icon' => 'fa-truck']
                                        ];
                                    } else {
                                        $stages = [
                                            'requestor' => ['label' => 'Requestor', 'icon' => 'fa-user'],
                                            'warehouse' => ['label' => 'Motorpool', 'icon' => 'fa-warehouse'],
                                            'purchasing' => ['label' => 'Purchasing', 'icon' => 'fa-shopping-cart'],
                                            'approver' => ['label' => 'Approver (CEO)', 'icon' => 'fa-user-check'],
                                            'warehouse_receiving' => ['label' => 'Motorpool Receiving', 'icon' => 'fa-truck-loading']
                                        ];
                                    }
                                    
                                    // Get all completed stages from routing history
                                    $completed_stages = [];
                                    foreach ($routing_history as $history) {
                                        if (in_array($history['action'], [
                                            'Forwarded to Motorpool', 
                                            'Approved by Motorpool', 
                                            'Approved by Purchasing', 
                                            'Approved by Approver',
                                            'Purchase Order Created',
                                            'Job Order Created',
                                            'Withdrawal Slip Created',
                                            'Items Received',
                                            'Items Released',
                                            'Completed Motorpool Receiving',
                                            'Completed Motorpool Releasing'
                                        ])) {
                                            $completed_stages[] = $history['stage_to'];
                                        }
                                        
                                        // Special case: if an action was taken from a stage, that stage is considered visited
                                        if ($history['stage_from'] && !in_array($history['stage_from'], $completed_stages)) {
                                            $completed_stages[] = $history['stage_from'];
                                        }
                                    }
                                    
                                    // Special case for requestor - always considered completed if we're beyond that stage
                                    if ($current_stage['stage'] !== 'requestor') {
                                        $completed_stages[] = 'requestor';
                                    }
                                    
                                    // Display all stages horizontally
                                    foreach ($stages as $stage => $stageInfo):
                                        $isCurrent = $current_stage['stage'] === $stage;
                                        $isCompleted = in_array($stage, $completed_stages);
                                        
                                        $stageClass = 'pending';
                                        if ($isCurrent && !in_array($current_stage['stage'], ['completed', 'rejected'])) {
                                            $stageClass = 'current';
                                        } elseif ($isCompleted) {
                                            $stageClass = 'completed';
                                        }
                                        // Tailwind utilities for the stage's state: the icon and the
                                        // status line take their colours from here (the old page
                                        // stylesheet drove them with descendant selectors).
                                        $stageIconClass = 'bg-slate-50 border-slate-200 text-slate-500';
                                        $stageStatusClass = 'text-slate-500';
                                        if ($stageClass === 'current') {
                                            $stageIconClass = 'bg-brand-600 border-brand-600 text-white ring-4 ring-brand-200';
                                            $stageStatusClass = 'text-brand-600 font-semibold';
                                        } elseif ($stageClass === 'completed') {
                                            $stageIconClass = 'bg-success-600 border-success-600 text-white';
                                            $stageStatusClass = 'text-success-600 font-semibold';
                                        }
                                    ?>
                                    <div class="flex-1 text-center relative px-1 z-10 min-w-0">
                                        <div class="h-10 w-10 xl:h-[50px] xl:w-[50px] rounded-full border-[3px] flex items-center justify-center text-base xl:text-xl mx-auto mb-2.5 relative z-10 transition-all <?php echo $stageIconClass; ?>">
                                            <i class="fas <?php echo $stageInfo['icon']; ?>"></i>
                                        </div>
                                        <div class="text-xs xl:text-sm font-semibold mb-1 leading-tight h-5 truncate"><?php echo $stageInfo['label']; ?></div>
                                        <div class="text-[11px] xl:text-xs h-5 truncate <?php echo $stageStatusClass; ?>">
                                            <?php if ($isCurrent && !in_array($current_stage['stage'], ['completed', 'rejected'])): ?>
                                                Current
                                            <?php elseif ($isCompleted): ?>
                                                Completed
                                            <?php else: ?>
                                                Pending
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Show final status if PR is completed or rejected -->
                                <?php if (in_array($current_stage['stage'], ['completed', 'rejected'])): ?>
                                <div class="rounded-lg p-4 text-center mt-6 <?php echo $current_stage['stage'] === 'completed' ? 'border-l-4 border-success-600 bg-success-50' : 'border-l-4 border-danger-600 bg-danger-50'; ?>">
                                    <h5 class="text-xl font-semibold mb-2">
                                        <i class="fas <?php echo $current_stage['stage'] === 'completed' ? 'fa-check-circle' : 'fa-times-circle'; ?> mr-2"></i>
                                        <?php 
                                        if ($current_stage['stage'] === 'completed') {
                                            if ($pr['request_type'] === 'issue') {
                                                echo 'JOB ORDER CONFIRMED';
                                            } elseif ($pr['request_type'] === 'issue_materials') {
                                                echo 'WITHDRAWAL SLIP COMPLETED';
                                            } else {
                                                echo 'PR COMPLETED';
                                            }
                                        } else {
                                            echo 'PR REJECTED';
                                        }
                                        ?>
                                    </h5>
                                    <p class="mb-0">
                                        <?php 
                                        if ($current_stage['stage'] === 'completed') {
                                            if ($pr['request_type'] === 'issue') {
                                                echo 'The job order for this spare parts request has been confirmed and processed successfully.';
                                            } elseif ($pr['request_type'] === 'issue_materials') {
                                                echo 'The withdrawal slip for this materials request has been confirmed and processed successfully.';
                                            } else {
                                                echo 'This spare parts purchase request has been successfully processed and completed.';
                                            }
                                        } else {
                                            echo 'This spare parts purchase request has been rejected during the routing process.';
                                        }
                                        ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Job Order (for issue requests with fully available stock) -->
                    <?php if ($is_issue_type && !$is_issue_materials && $job_order_exists): ?>
                    <div class="card mb-6">
                        <div class="card-header">
                            <i class="fas fa-tools mr-1"></i>
                            Existing Job Order
                        </div>
                        <div class="card-body">
                            <div class="table-responsive max-h-[400px] overflow-y-auto">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Job Order Number</th>
                                            <th>Date Created</th>
                                            <th>Technician</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                            <th>Items</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php echo htmlspecialchars($job_order_details['job_order_number']); ?></td>
                                            <td><?php echo formatDateMDY($job_order_details['job_order_date']); ?></td>
                                            <td>
                                                <?php 
                                                // Check if we have technician data from the PR query
                                                if (!empty($pr['tech_firstname'])) {
                                                    $technician_name = $pr['tech_firstname'];
                                                    if (!empty($pr['tech_middlename'])) {
                                                        $technician_name .= ' ' . substr($pr['tech_middlename'], 0, 1) . '.';
                                                    }
                                                    $technician_name .= ' ' . $pr['tech_lastname'];
                                                    if (!empty($pr['tech_suffix'])) {
                                                        $technician_name .= ' ' . $pr['tech_suffix'];
                                                    }
                                                    echo htmlspecialchars($technician_name);
                                                } else {
                                                    // Fallback to the stored technician value if the detailed data isn't available
                                                    echo htmlspecialchars($job_order_details['technician'] ?? 'N/A');
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($job_order_details['purpose']); ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch(strtolower($job_order_details['status'])) {
                                                        case 'draft': echo 'badge-primary'; break;
                                                        case 'pending': echo 'badge-warning'; break;
                                                        case 'approved': echo 'badge-success'; break;
                                                        case 'released': echo 'badge-success'; break;
                                                        case 'confirmed': echo 'badge-success'; break;
                                                        case 'completed': echo 'badge-success'; break;
                                                        case 'cancelled': echo 'badge-danger'; break;
                                                        default: echo 'badge-neutral';
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst($job_order_details['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo count($job_order_details['items'] ?? []); ?> item/s</td>
                                            <td>
                                                <!-- View Job Order Button -->
                                                <button type="button" class="btn btn-sm btn-primary view-job-order-btn" 
                                                        data-job-order-id="<?php echo $job_order_details['id']; ?>"
                                                        data-job-order-number="<?php echo htmlspecialchars($job_order_details['job_order_number']); ?>"
                                                        data-job-order-date="<?php echo formatDateMDY($job_order_details['job_order_date']); ?>"
                                                        data-technician="<?php echo htmlspecialchars($job_order_details['technician']); ?>"
                                                        data-purpose="<?php echo htmlspecialchars($job_order_details['purpose']); ?>"
                                                        data-job-order-status="<?php echo ucfirst($job_order_details['status']); ?>"
                                                        data-job-order-remarks="<?php echo htmlspecialchars($job_order_details['remarks'] ?? ''); ?>">
                                                    <i class="fas fa-eye mr-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Existing Withdrawal Slip (for issue_materials requests with fully available stock) -->
                    <?php if ($is_issue_materials && $withdrawal_slip_exists): ?>
                    <div class="card mb-6">
                        <div class="card-header">
                            <i class="fas fa-file-invoice mr-1"></i>
                            Existing Withdrawal Slip
                        </div>
                        <div class="card-body">
                            <div class="table-responsive max-h-[400px] overflow-y-auto">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Withdrawal Slip Number</th>
                                            <th>Date Created</th>
                                            <th>Prepared By</th>
                                            <th>Issue to Employee</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                            <th>Items</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php echo htmlspecialchars($withdrawal_slip_details['withdrawal_slip_number']); ?></td>
                                            <td><?php echo formatDateMDY($withdrawal_slip_details['withdrawal_date']); ?></td>
                                            <td><?php echo formatUserName($pr); ?></td>
                                            <td><?php echo htmlspecialchars($withdrawal_slip_details['employee_display_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($withdrawal_slip_details['purpose']); ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch(strtolower($withdrawal_slip_details['status'])) {
                                                        case 'draft': echo 'badge-info'; break;
                                                        case 'pending': echo 'badge-warning'; break;
                                                        case 'approved': echo 'badge-success'; break;
                                                        case 'released': echo 'badge-success'; break;
                                                        case 'completed': echo 'badge-success'; break;
                                                        case 'cancelled': echo 'badge-danger'; break;
                                                        default: echo 'badge-neutral';
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst($withdrawal_slip_details['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo count($withdrawal_slip_details['items'] ?? []); ?> item/s</td>
                                            <td>
                                                <!-- View Withdrawal Slip Button -->
                                                <button type="button" class="btn btn-sm btn-primary view-withdrawal-slip-btn" 
                                                        data-withdrawal-slip-id="<?php echo $withdrawal_slip_details['id']; ?>"
                                                        data-withdrawal-slip-number="<?php echo htmlspecialchars($withdrawal_slip_details['withdrawal_slip_number']); ?>"
                                                        data-withdrawal-slip-date="<?php echo formatDateMDY($withdrawal_slip_details['withdrawal_date']); ?>"
                                                        data-requested-by="<?php echo formatUserName($pr); ?>"
                                                        data-employee="<?php echo htmlspecialchars($withdrawal_slip_details['employee_display_name'] ?? 'N/A'); ?>"
                                                        data-purpose="<?php echo htmlspecialchars($withdrawal_slip_details['purpose']); ?>"
                                                        data-withdrawal-slip-status="<?php echo ucfirst($withdrawal_slip_details['status']); ?>"
                                                        data-withdrawal-slip-remarks="<?php echo htmlspecialchars($withdrawal_slip_details['remarks'] ?? ''); ?>">
                                                    <i class="fas fa-eye mr-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Existing Purchase Orders -->
                    <?php if (!empty($existing_pos)): ?>
                    <div class="card mb-6">
                        <div class="card-header">
                            <i class="fas fa-file-invoice-dollar mr-1"></i>
                            Existing Purchase Orders
                        </div>
                        <div class="card-body">
                            <div class="table-responsive max-h-[400px] overflow-y-auto">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>PO Number</th>
                                            <th>Date Created</th>
                                            <th>Expected Delivery</th>
                                            <th>Status</th>
                                            <th>Items</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($existing_pos as $po): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                                            <td><?php echo htmlspecialchars(ocp_date_mdy($po['po_date'])); ?></td>
                                            <td><?php echo $po['expected_delivery'] ?? 'Not set'; ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch($po['status']) {
                                                        case 'draft': echo 'badge-neutral'; break;
                                                        case 'pending': echo 'badge-warning'; break;
                                                        case 'approved': echo 'badge-success'; break;
                                                        case 'confirmed': echo 'badge-success'; break;
                                                        case 'delivered': echo 'badge-success'; break;
                                                        case 'partially_received': echo 'badge-warning'; break;
                                                        case 'cancelled': echo 'badge-danger'; break;
                                                        case 'completed': echo 'badge-success'; break;
                                                        default: echo 'badge-neutral';
                                                    }
                                                    ?>
                                                ">
                                                    <?php echo ucfirst($po['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $po['item_count']; ?> item/s</td>
                                            <td>
                                                <!-- View PO Button -->
                                                <button type="button" class="btn btn-sm btn-primary view-po-btn" 
                                                        data-po-id="<?php echo $po['id']; ?>"
                                                        data-po-number="<?php echo htmlspecialchars($po['po_number']); ?>"
                                                        data-po-date="<?php echo htmlspecialchars($po['po_date']); ?>"
                                                        data-expected-delivery="<?php echo htmlspecialchars($po['expected_delivery'] ?? 'Not set'); ?>"
                                                        data-total-amount="<?php echo number_format($po['calculated_total_amount'], 2); ?>"
                                                        data-po-status="<?php echo ucfirst($po['status']); ?>"
                                                        data-po-remarks="<?php echo htmlspecialchars($po['remarks'] ?? ''); ?>">
                                                    <i class="fas fa-eye mr-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Motorpool Processing (Releasing for Issue and Issue Materials Requests) -->
                    <?php if ($current_stage['stage'] === 'warehouse_releasing' && $is_issue_type && !empty($items)): ?>
                    <div class="card mb-6">
                        <div class="card-header">
                            <i class="fas fa-truck mr-1"></i>
                            Items to Release (<?php echo $pr['request_type'] === 'issue' ? 'Issue Parts Request' : 'Issue Materials Request'; ?>) - FIFO Method
                        </div>
                        <div class="card-body">
                            <!-- Show alert if items already processed -->
                            <?php if ($items_already_released): ?>
                            <div class="rounded-lg border-l-4 border-success-600 bg-success-50 p-4 mb-5">
                                <h6 class="text-base font-semibold"><i class="fas fa-check-circle mr-2"></i>Items Already Released</h6>
                                <p class="mb-0">The items for this <?php echo $pr['request_type'] === 'issue' ? 'issue' : 'issue materials'; ?> request have already been released using FIFO method.</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($is_motorpool_user): ?>
                            <!-- Motorpool User - Show form with FIFO batch details -->
                            <?php if (!$items_already_released): ?>
                            <form method="POST" action="" id="warehouseProcessingForm">
                                <input type="hidden" name="action" value="release_items">
                                <div class="table-responsive max-h-[400px] overflow-y-auto">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Part Name</th>
                                                <th>Category</th>
                                                <th>Quantity Requested</th>
                                                <th>Available Stock</th>
                                                <th>Quantity Released</th>
                                                <th>Status</th>
                                                <th>Release Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item): 
                                                // Get delivered_quantity from spare_parts_pr_items table
                                                $delivered_quantity = floatval($item['delivered_quantity'] ?? 0);
                                                $requested_quantity = floatval($item['quantity']);
                                                $remaining_quantity = $requested_quantity - $delivered_quantity;
                                                $available_stock = $item['stock_info']['stock_quantity'] ?? 0;
                                                
                                                // Determine status based on delivered_quantity vs requested_quantity
                                                $status_class = 'text-slate-500';
                                                $status_text = 'Pending';
                                                
                                                if ($delivered_quantity > 0) {
                                                    if ($delivered_quantity >= $requested_quantity) {
                                                        $status_class = 'text-success-600';
                                                        $status_text = 'Fully Released';
                                                    } else {
                                                        $status_class = 'text-warning-600';
                                                        $status_text = 'Partially Released';
                                                    }
                                                } elseif ($available_stock >= $remaining_quantity) {
                                                    $status_class = 'text-success-600';
                                                    $status_text = 'Ready for Release';
                                                } elseif ($available_stock > 0) {
                                                    $status_class = 'text-warning-600';
                                                    $status_text = 'Partially Available';
                                                } else {
                                                    $status_class = 'text-slate-500';
                                                    $status_text = 'Out of Stock';
                                                }
                                                
                                                // FIX: For "Partially Available" items, only allow release up to available stock
                                                $max_release = min($remaining_quantity, $available_stock);
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['part_name_display']); ?></td>
                                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                                <!-- FIXED: Display Quantity Requested with .00 -->
                                                <td><?php echo number_format($requested_quantity, 2); ?></td>
                                                <td><?php echo number_format($available_stock, 2); ?></td>
                                                <td>
                                                    <?php if ($remaining_quantity > 0 && $available_stock > 0): ?>
                                                        <input type="number" 
                                                            name="released_items[<?php echo $item['id']; ?>][quantity]" 
                                                            value="<?php echo $max_release; ?>"
                                                            min="1" max="<?php echo $max_release; ?>"
                                                            step="0.01"
                                                            class="form-control form-control-sm" required>
                                                    <?php elseif ($remaining_quantity > 0): ?>
                                                        <span class="text-danger">Out of stock</span>
                                                    <?php else: ?>
                                                        <span class="text-success"><?php echo number_format($delivered_quantity, 2); ?> released</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="font-bold <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($remaining_quantity > 0 && $available_stock > 0): ?>
                                                        <input type="date" 
                                                            name="released_items[<?php echo $item['id']; ?>][release_date]" 
                                                            value="<?php echo date('Y-m-d'); ?>"
                                                            class="form-control form-control-sm date-input" 
                                                            required>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-4">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                </div>
                                
                                <div class="mt-4">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <strong>FIFO Method:</strong> Items will be released from the oldest batches first. The system will automatically track which batches are used.
                                        <br><strong>Note:</strong> For "Partially Available" items, only the available stock can be released.
                                    </div>
                                    
                                    <!-- Show release button only if items haven't been released yet -->
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check mr-1"></i> Confirm Items Released (FIFO)
                                    </button>
                                </div>
                            </form>
                            <?php else: ?>
                            <!-- READ-ONLY VIEW AFTER ITEMS ARE RELEASED - UPDATED TO SHOW released_date FROM WITHDRAWAL SLIP ITEMS -->
                            <div class="table-responsive max-h-[400px] overflow-y-auto">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Part Name</th>
                                            <th>Category</th>
                                            <th>Quantity Requested</th>
                                            <th>Quantity Released</th>
                                            <th>Remaining</th>
                                            <th>Status</th>
                                            <th>Release Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): 
                                            // Get delivered_quantity from spare_parts_pr_items table
                                            $delivered_quantity = floatval($item['delivered_quantity'] ?? 0);
                                            $requested_quantity = floatval($item['quantity']);
                                            $remaining_quantity = $requested_quantity - $delivered_quantity;
                                            
                                            // Determine status based on delivered_quantity vs requested_quantity
                                            $status_class = 'text-slate-500';
                                            $status_text = 'Pending';
                                            
                                            if ($delivered_quantity > 0) {
                                                if ($delivered_quantity >= $requested_quantity) {
                                                    $status_class = 'text-success-600';
                                                    $status_text = 'Fully Released';
                                                } else {
                                                    $status_class = 'text-warning-600';
                                                    $status_text = 'Partially Released';
                                                }
                                            } else {
                                                $status_class = 'text-slate-500';
                                                $status_text = 'Not Released';
                                            }
                                            
                                            // ========================================================
                                            // FIX: Get release date from withdrawal slip items
                                            // ========================================================
                                            $release_date = '';
                                            
                                            // For issue_materials requests, get date from withdrawal slip items
                                            if ($is_issue_materials && $withdrawal_slip_exists && !empty($withdrawal_slip_details['items'])) {
                                                foreach ($withdrawal_slip_details['items'] as $ws_item) {
                                                    if ($ws_item['pr_item_id'] == $item['id'] && !empty($ws_item['released_date'])) {
                                                        // Format the date if it's in Y-m-d format
                                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ws_item['released_date'])) {
                                                            $date = new DateTime($ws_item['released_date']);
                                                            $release_date = $date->format('m-d-Y');
                                                        } else {
                                                            // If it's already formatted or in another format, use as is
                                                            $release_date = $ws_item['released_date'];
                                                        }
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            // For issue requests (JO), get date from job order items
                                            if (!$is_issue_materials && $job_order_exists && !empty($job_order_details['items'])) {
                                                foreach ($job_order_details['items'] as $jo_item) {
                                                    if ($jo_item['pr_item_id'] == $item['id'] && !empty($jo_item['released_date'])) {
                                                        // Format the date if it's in Y-m-d format
                                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $jo_item['released_date'])) {
                                                            $date = new DateTime($jo_item['released_date']);
                                                            $release_date = $date->format('m-d-Y');
                                                        } else {
                                                            // If it's already formatted or in another format, use as is
                                                            $release_date = $jo_item['released_date'];
                                                        }
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            // Fallback to legacy method if no date found in items
                                            if (empty($release_date)) {
                                                if ($job_order_exists && !empty($job_order_details['items'])) {
                                                    foreach ($job_order_details['items'] as $jo_item) {
                                                        if ($jo_item['pr_item_id'] == $item['id'] && !empty($jo_item['released_date'])) {
                                                            $release_date = formatDateMDY($jo_item['released_date']);
                                                            break;
                                                        }
                                                    }
                                                }
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['part_name_display']); ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <!-- FIXED: Display Quantity Requested with .00 -->
                                            <td><?php echo number_format($requested_quantity, 2); ?></td>
                                            <td><?php echo number_format($delivered_quantity, 2); ?></td>
                                            <td><?php echo number_format($remaining_quantity, 2); ?></td>
                                            <td>
                                                <span class="font-bold <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($release_date)): ?>
                                                    <span class="text-muted"><?php echo $release_date; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <!-- Non-Motorpool User - Show read-only view (before items are released) -->
                            <div class="table-responsive max-h-[400px] overflow-y-auto">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Part Name</th>
                                            <th>Category</th>
                                            <th>Quantity Requested</th>
                                            <th>Available Stock</th>
                                            <th>Quantity Released</th>
                                            <th>Status</th>
                                            <th>Release Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): 
                                            // Get delivered_quantity from spare_parts_pr_items table
                                            $delivered_quantity = floatval($item['delivered_quantity'] ?? 0);
                                            $requested_quantity = floatval($item['quantity']);
                                            $remaining_quantity = $requested_quantity - $delivered_quantity;
                                            $available_stock = $item['stock_info']['stock_quantity'] ?? 0;
                                            
                                            // Determine status based on delivered_quantity vs requested_quantity
                                            $status_class = 'text-slate-500';
                                            $status_text = 'Pending';
                                            
                                            if ($delivered_quantity > 0) {
                                                if ($delivered_quantity >= $requested_quantity) {
                                                    $status_class = 'text-success-600';
                                                    $status_text = 'Fully Released';
                                                } else {
                                                    $status_class = 'text-warning-600';
                                                    $status_text = 'Partially Released';
                                                }
                                            } elseif ($available_stock >= $remaining_quantity) {
                                                $status_class = 'text-success-600';
                                                $status_text = 'Ready for Release';
                                            } elseif ($available_stock > 0) {
                                                $status_class = 'text-warning-600';
                                                $status_text = 'Partially Available';
                                            } else {
                                                $status_class = 'text-slate-500';
                                                $status_text = 'Out of Stock';
                                            }
                                            
                                            // Get release date from withdrawal slip items if available (for read-only view)
                                            $release_date = '';
                                            if ($is_issue_materials && $withdrawal_slip_exists && !empty($withdrawal_slip_details['items'])) {
                                                foreach ($withdrawal_slip_details['items'] as $ws_item) {
                                                    if ($ws_item['pr_item_id'] == $item['id'] && !empty($ws_item['released_date'])) {
                                                        // Format the date if it's in Y-m-d format
                                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ws_item['released_date'])) {
                                                            $date = new DateTime($ws_item['released_date']);
                                                            $release_date = $date->format('m-d-Y');
                                                        } else {
                                                            // If it's already formatted or in another format, use as is
                                                            $release_date = $ws_item['released_date'];
                                                        }
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            // For issue requests (JO), get date from job order items
                                            if (!$is_issue_materials && $job_order_exists && !empty($job_order_details['items'])) {
                                                foreach ($job_order_details['items'] as $jo_item) {
                                                    if ($jo_item['pr_item_id'] == $item['id'] && !empty($jo_item['released_date'])) {
                                                        // Format the date if it's in Y-m-d format
                                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $jo_item['released_date'])) {
                                                            $date = new DateTime($jo_item['released_date']);
                                                            $release_date = $date->format('m-d-Y');
                                                        } else {
                                                            // If it's already formatted or in another format, use as is
                                                            $release_date = $jo_item['released_date'];
                                                        }
                                                        break;
                                                    }
                                                }
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['part_name_display']); ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <!-- FIXED: Display Quantity Requested with .00 -->
                                            <td><?php echo number_format($requested_quantity, 2); ?></td>
                                            <td><?php echo number_format($available_stock, 2); ?></td>
                                            <td>
                                                <?php if ($remaining_quantity > 0 && $available_stock > 0): ?>
                                                    <input type="number" 
                                                        value="<?php echo min($remaining_quantity, $available_stock); ?>"
                                                        class="form-control form-control-sm bg-slate-50 cursor-not-allowed" 
                                                        readonly>
                                                <?php elseif ($remaining_quantity > 0): ?>
                                                    <span class="text-danger">Out of stock</span>
                                                <?php else: ?>
                                                    <span class="text-success"><?php echo number_format($delivered_quantity, 2); ?> released</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="font-bold <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($release_date)): ?>
                                                    <span class="text-muted"><?php echo $release_date; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info mt-4">
                                <i class="fas fa-info-circle mr-2"></i>
                                Only Motorpool Department users can release items using FIFO method. You are viewing this information in read-only mode.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Motorpool Processing (Receiving for Stock Requests) -->
                    <?php if ($current_stage['stage'] === 'warehouse_receiving' && $pr['request_type'] === 'stock' && !empty($items)): ?>
                    <div class="card mb-6">
                        <div class="card-header">
                            <i class="fas fa-truck-loading mr-1"></i>
                            Items to Receive (Purchase Order)
                        </div>
                        <div class="card-body">
                            <!-- Show alert if items already processed -->
                            <?php if ($items_already_received): ?>
                            <div class="rounded-lg border-l-4 border-success-600 bg-success-50 p-4 mb-5">
                                <h6 class="text-base font-semibold"><i class="fas fa-check-circle mr-2"></i>Items Already Received</h6>
                                <p class="mb-0">The items for this purchase order have already been received. You cannot receive them again.</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($is_motorpool_user): ?>
                            <!-- Motorpool User - Show form with inputs -->
                            <?php if (!$items_already_received): ?>
                            <form method="POST" action="" id="warehouseProcessingForm">
                                <input type="hidden" name="action" value="receive_items">
                                <div class="table-responsive max-h-[400px] overflow-y-auto">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Part Name</th>
                                                <th>Category</th>
                                                <th>Quantity Ordered</th>
                                                <th>Quantity Received</th>
                                                <th>Remaining</th>
                                                <th>Unit Cost</th>
                                                <th>Total Cost</th>
                                                <th>Status</th>
                                                <th>Quantity to Receive</th>
                                                <th>Received Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            if (!empty($po_items_for_receiving)) {
                                                $items_to_process = $po_items_for_receiving;
                                            } else {
                                                $items_to_process = $items;
                                            }
                                            
                                            foreach ($items_to_process as $item): 
                                                $received_quantity = $item['received_quantity'] ?? 0;
                                                $remaining_quantity = $item['quantity'] - $received_quantity;
                                                $status = $item['status'] ?? 'pending';
                                                $received_date = $item['received_date'] ?? '';
                                                $item_id = isset($item['pr_item_id']) ? $item['id'] : $item['id'];
                                                $is_po_item = isset($item['pr_item_id']);
                                                
                                                // Format part name with part number in parentheses
                                                $part_name_display = htmlspecialchars($item['part_name']);
                                                if (!empty($item['part_number'])) {
                                                    $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                                }
                                                
                                                // Determine status class
                                                $status_class = 'text-slate-500';
                                                $status_text = 'Pending';
                                                if ($status === 'partially_received') {
                                                    $status_class = 'text-warning-600';
                                                    $status_text = 'Partially Received';
                                                } elseif ($status === 'delivered') {
                                                    $status_class = 'text-success-600';
                                                    $status_text = 'Delivered';
                                                }
                                                
                                                // Calculate total cost for display - FIXED
                                                $receiving_quantity = $remaining_quantity > 0 ? min($remaining_quantity, $item['quantity']) : 0;
                                                $total_cost_display = $item['unit_cost'] * $receiving_quantity;
                                            ?>
                                            <tr>
                                                <td><?php echo $part_name_display; ?></td>
                                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                                <td><?php echo number_format($item['quantity'], 2); ?></td>
                                                <td><?php echo number_format($received_quantity, 2); ?></td>
                                                <td><?php echo number_format($remaining_quantity, 2); ?></td>
                                                <td>₱<?php echo number_format($item['unit_cost'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_cost_display, 2); ?></td>
                                                <td>
                                                    <span class="font-bold <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($remaining_quantity > 0): ?>
                                                        <input type="number" 
                                                            name="received_items[<?php echo $item_id; ?>][quantity]" 
                                                            value="<?php echo $remaining_quantity; ?>"
                                                            min="0" max="<?php echo $remaining_quantity; ?>"
                                                            step="0.01"
                                                            class="form-control form-control-sm" required>
                                                        <!-- Hidden batch number field - still required for backend -->
                                                        <input type="hidden" 
                                                            name="received_items[<?php echo $item_id; ?>][batch_number]" 
                                                            value="BATCH-<?php echo date('m-d-Y-His'); ?>-<?php echo $item_id; ?>">
                                                    <?php else: ?>
                                                        <span class="text-success">Fully Received</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($remaining_quantity > 0): ?>
                                                        <!-- Show existing received_date if available, otherwise use today's date -->
                                                        <?php 
                                                        $today_formatted = date('Y-m-d');
                                                        $default_date = !empty($received_date) ? $received_date : $today_formatted;
                                                        ?>
                                                        <input type="date" 
                                                            name="received_items[<?php echo $item_id; ?>][received_date]" 
                                                            value="<?php echo $default_date; ?>"
                                                            class="form-control form-control-sm date-input" 
                                                            required>
                                                        <input type="hidden" 
                                                            name="received_items[<?php echo $item_id; ?>][unit_cost]" 
                                                            value="<?php echo $item['unit_cost']; ?>">
                                                    <?php else: ?>
                                                        <!-- Show the received_date if item is fully received -->
                                                        <?php if (!empty($received_date)): ?>
                                                            <span class="text-muted"><?php echo formatDateMDY($received_date); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-4">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                </div>
                                
                                <div class="mt-4">
                                    <!-- Disable button if items already processed -->
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check mr-1"></i> Confirm Items Received
                                    </button>
                                </div>
                            </form>
                            <?php else: ?>
                            
                            <!-- Non-Motorpool User - Show read-only view -->
                            <div class="table-responsive max-h-[400px] overflow-y-auto">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Part Name</th>
                                            <th>Category</th>
                                            <th>Quantity Ordered</th>
                                            <th>Quantity Received</th>
                                            <th>Remaining</th>
                                            <th>Unit Cost</th>
                                            <th>Total Cost</th>
                                            <th>Status</th>
                                            <th>Quantity to Receive</th>
                                            <th>Received Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // IMPORTANT: Use $po_items_for_receiving which already has formatted dates
                                        // Only fall back to $items if $po_items_for_receiving is empty
                                        if (!empty($po_items_for_receiving)) {
                                            $items_to_process = $po_items_for_receiving;
                                        } else {
                                            $items_to_process = $items;
                                        }
                                        
                                        foreach ($items_to_process as $item): 
                                            $received_quantity = floatval($item['received_quantity'] ?? 0);
                                            $remaining_quantity = floatval($item['quantity']) - $received_quantity;
                                            $status = $item['status'] ?? 'pending';
                                            $received_date = $item['received_date'] ?? '';
                                            
                                            // Format part name with part number in parentheses
                                            $part_name_display = htmlspecialchars($item['part_name']);
                                            if (!empty($item['part_number'])) {
                                                $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                            }
                                            
                                            // Determine status class
                                            $status_class = 'text-slate-500';
                                            $status_text = 'Pending';
                                            if ($status === 'partially_received') {
                                                $status_class = 'text-warning-600';
                                                $status_text = 'Partially Received';
                                            } elseif ($status === 'delivered' || $status === 'confirmed') {
                                                $status_class = 'text-success-600';
                                                $status_text = 'Delivered';
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo $part_name_display; ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo number_format($item['quantity'], 2); ?></td>
                                            <td><?php echo number_format($received_quantity, 2); ?></td>
                                            <td><?php echo number_format($remaining_quantity, 2); ?></td>
                                            <td>₱<?php echo number_format($item['unit_cost'] ?? 0, 2); ?></td>
                                            <td>₱<?php echo number_format(($item['unit_cost'] ?? 0) * $received_quantity, 2); ?></td>
                                            <td>
                                                <span class="font-bold <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($remaining_quantity > 0): ?>
                                                    <input type="number" 
                                                        value="<?php echo $remaining_quantity; ?>"
                                                        class="form-control form-control-sm bg-slate-50 cursor-not-allowed" 
                                                        readonly>
                                                <?php else: ?>
                                                    <span class="text-success">Fully Received</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                // Check if we have a valid received date
                                                if (!empty($received_date) && $received_date != '0000-00-00' && $received_date != '1970-01-01'): 
                                                    // If it's already formatted (mm-dd-yyyy), display as is
                                                    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $received_date)) {
                                                        echo '<span class="text-muted">' . $received_date . '</span>';
                                                    } else {
                                                        // Otherwise format it
                                                        try {
                                                            $date = new DateTime($received_date);
                                                            echo '<span class="text-muted">' . $date->format('m-d-Y') . '</span>';
                                                        } catch (Exception $e) {
                                                            echo '<span class="text-muted">-</span>';
                                                        }
                                                    }
                                                else: 
                                                ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <!-- Non-Motorpool User - Show read-only view -->
                            <div class="table-responsive max-h-[400px] overflow-y-auto">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Part Name</th>
                                            <th>Category</th>
                                            <th>Quantity Requested</th>
                                            <th>Quantity Received</th>
                                            <th>Remaining</th>
                                            <th>Unit Cost</th>
                                            <th>Total Cost</th>
                                            <th>Status</th>
                                            <th>Quantity to Receive</th>
                                            <th>Received Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        if (!empty($po_items_for_receiving)) {
                                            $items_to_process = $po_items_for_receiving;
                                        } else {
                                            $items_to_process = $items;
                                        }
                                        
                                        foreach ($items_to_process as $item): 
                                            $received_quantity = $item['received_quantity'] ?? 0;
                                            $remaining_quantity = $item['quantity'] - $received_quantity;
                                            $status = $item['status'] ?? 'pending';
                                            $received_date = $item['received_date'] ?? '';
                                            
                                            // Format part name with part number in parentheses
                                            $part_name_display = htmlspecialchars($item['part_name']);
                                            if (!empty($item['part_number'])) {
                                                $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                            }
                                            
                                            // Determine status class
                                            $status_class = 'text-slate-500';
                                            $status_text = 'Pending';
                                            if ($status === 'partially_received') {
                                                $status_class = 'text-warning-600';
                                                $status_text = 'Partially Received';
                                            } elseif ($status === 'delivered') {
                                                $status_class = 'text-success-600';
                                                $status_text = 'Delivered';
                                            }
                                        ?>
                                        <tr>
                                            <!-- REMOVED: Part Number column -->
                                            <td><?php echo $part_name_display; ?></td> <!-- Now shows "Motolite 2sm (0012)" -->
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo number_format($item['quantity'], 2); ?></td>
                                            <td><?php echo number_format($received_quantity, 2); ?></td>
                                            <td><?php echo number_format($remaining_quantity, 2); ?></td>
                                            <td>₱<?php echo number_format($item['unit_cost'], 2); ?></td>
                                            <td>₱<?php echo number_format($item['unit_cost'] * ($received_quantity), 2); ?></td>
                                            <td>
                                                <span class="font-bold <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($remaining_quantity > 0): ?>
                                                    <input type="number" 
                                                        value="<?php echo $remaining_quantity; ?>"
                                                        class="form-control form-control-sm bg-slate-50 cursor-not-allowed" 
                                                        readonly>
                                                <?php else: ?>
                                                    <span class="text-success">Fully Received</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($received_date)): ?>
                                                    <span class="text-muted"><?php echo formatDateMDY($received_date); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info mt-4">
                                <i class="fas fa-info-circle mr-2"></i>
                                Only Motorpool Department users can receive items. You are viewing this information in read-only mode.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 gap-6">
                        <!-- Items Requested -->
                        <div class="min-w-0">
                            <div class="card mb-6">
                                <div class="card-header">
                                    <i class="fas fa-list mr-1"></i>
                                    <!-- CHANGED: Show "Materials Requested" for Issue Materials Request -->
                                    <?php if ($is_issue_materials): ?>
                                        Materials Requested
                                    <?php else: ?>
                                        Spare Parts Requested
                                    <?php endif; ?>
                                    <!-- ADD ROUTING ACTIONS BUTTON -->
                                    <?php if (!in_array($current_stage['stage'], ['completed', 'rejected'])): ?>
                                    <button type="button" class="btn btn-primary btn-sm ml-auto" data-bs-toggle="modal" data-bs-target="#routingActionsModal">
                                        <i class="fas fa-route mr-1"></i> Routing Actions
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    
                                    <?php if ($all_items_fully_available && $is_issue_type): ?>
                                        <div class="mt-2 alert alert-success">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            <strong>All items are fully available in stock!</strong> A <?php echo $is_issue_materials ? 'Withdrawal Slip' : 'Job Order'; ?> must be created before approval.
                                        </div>
                                    <?php elseif ($has_partially_available_items && $is_issue_type): ?>
                                        <div class="mt-2 alert alert-warning">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            <strong>Some items are partially available in stock.</strong> Only available stock can be released. A <?php echo $is_issue_materials ? 'Withdrawal Slip' : 'Job Order'; ?> must be created before approval.
                                        </div>
                                    <?php elseif ($has_out_of_stock_items && $is_issue_type): ?>
                                        <div class="mt-2 alert alert-danger">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            <strong>Some items are out of stock.</strong> These items will require a Purchase Order.
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($is_issue_type): ?>
                                    <div class="alert alert-info mb-4">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <strong><?php echo $pr['request_type'] === 'issue' ? 'Issue Parts Request' : 'Issue Materials Request'; ?></strong> - Showing detailed stock and delivery information for each requested <?php echo $is_issue_materials ? 'material' : 'part'; ?>.
                                        <?php if ($has_out_of_stock_items): ?>
                                            <br><strong>Note:</strong> Items marked as "Out of Stock" can still be forwarded to Motorpool. They will require a Purchase Order.
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="table-responsive max-h-[400px] overflow-y-auto">
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <?php if ($is_issue_type): ?>
                                                    <!-- For Issue and Issue Materials requests -->
                                                    <?php if (!$is_issue_materials): ?>
                                                    <!-- Only show Vehicle/Equipment for Issue Parts Request, NOT for Issue Materials -->
                                                    <th>Vehicle/Equipment</th>
                                                    <?php endif; ?>
                                                    <th>Part Name</th> <!-- This will display "Part Name (Part Number)" -->
                                                    <th>Category</th>
                                                    <th>Current Stock</th>
                                                    <th>Quantity Requested</th>
                                                    <th>Quantity Released</th>
                                                    <th>Remaining Needed</th>
                                                    <th>Unit Cost</th>
                                                    <th>Total Cost</th>
                                                    <th>Stock Status</th>
                                                    <th>Release Status</th>
                                                    <?php else: ?>
                                                    <!-- For Stock requests - UPDATED: Added Quantity Received column -->
                                                    <th>Part Name</th>
                                                    <th>Category</th>
                                                    <th>Quantity Ordered</th>
                                                    <th>Quantity Received</th>
                                                    <th>Unit Cost</th>
                                                    <th>Total Cost</th>
                                                    <th>Delivery Status</th>
                                                    <th>Received Date</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($items as $item): 
                                                    // Calculate total cost based on remaining needed for issue types, 
                                                    // or full quantity for stock types
                                                    if ($is_issue_type) {
                                                        // For issue/issue_materials: use remaining needed
                                                        $quantity_for_cost = $item['remaining_needed'] ?? $item['quantity'];
                                                    } else {
                                                        // For stock requests: use full quantity
                                                        $quantity_for_cost = $item['quantity'];
                                                    }
                                                    $item_total_cost = $quantity_for_cost * ($item['unit_cost'] ?: 0);
                                                    $stock_info = $item['stock_info'];
                                                    $delivery_info = $item['delivery_info'];
                                                    $remaining_needed = $item['remaining_needed'];
                                                    $overall_status = $item['overall_status'];
                                                    // Get delivered_quantity from spare_parts_pr_items table
                                                    $delivered_quantity = floatval($item['delivered_quantity'] ?? 0);
                                                    
                                                    // Format part name with part number in parentheses
                                                    $part_name_display = htmlspecialchars($item['part_name']);
                                                    if (!empty($item['part_number'])) {
                                                        $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                                    }
                                                    
                                                    // FIXED: Determine release status with icons and proper colors
                                                    $release_status = 'Pending';
                                                    $release_icon = 'fa-clock';
                                                    $release_color_class = 'text-warning';
                                                    
                                                    if ($delivered_quantity > 0) {
                                                        if ($delivered_quantity >= $item['quantity']) {
                                                            $release_status = 'Released';
                                                            $release_icon = 'fa-check-circle';
                                                            $release_color_class = 'text-success';
                                                        } else {
                                                            $release_status = 'Partially Released';
                                                            $release_icon = 'fa-clock';
                                                            $release_color_class = 'text-warning';
                                                        }
                                                    } elseif ($overall_status === 'Out of Stock') {
                                                        $release_status = 'Rejected';
                                                        $release_icon = 'fa-times-circle';
                                                        $release_color_class = 'text-danger';
                                                    } elseif ($overall_status === 'Partially Available') {
                                                        $release_status = 'Pending';
                                                        $release_icon = 'fa-clock';
                                                        $release_color_class = 'text-warning';
                                                    } elseif ($overall_status === 'Fully Available') {
                                                        $release_status = 'Pending';
                                                        $release_icon = 'fa-clock';
                                                        $release_color_class = 'text-warning';
                                                    }
                                                ?>
                                                <tr>
                                                    <?php if ($is_issue_type): ?>
                                                    <!-- For Issue and Issue Materials requests -->
                                                    <?php if (!$is_issue_materials): ?>
                                                    <!-- Only show Vehicle/Equipment for Issue Parts Request -->
                                                    <td><?php echo htmlspecialchars($item['vehicle_equipment_display']); ?></td>
                                                    <?php endif; ?>
                                                    <!-- Part Name with number -->
                                                    <td><?php echo $part_name_display; ?></td>
                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                                    <!-- Current Stock -->
                                                    <td><?php echo $stock_info['available'] ? number_format($stock_info['stock_quantity'], 2) : '0.00'; ?></td>
                                                    <!-- Quantity Requested -->
                                                    <td><?php echo number_format($item['quantity'], 2); ?></td>
                                                    <!-- Quantity Released -->
                                                    <td><?php echo number_format($delivered_quantity, 2); ?></td>
                                                    <!-- Remaining Needed -->
                                                    <td><?php echo number_format($remaining_needed, 2); ?></td>
                                                    <!-- Unit Cost -->
                                                    <td>
                                                        <?php 
                                                        if ($is_issue_type): 
                                                            // For issue/issue_materials: show unit cost based on FIFO calculation
                                                            $unit_cost_display = $item['unit_cost'] ?? 0;
                                                            echo '₱' . number_format($unit_cost_display, 2);
                                                        else: 
                                                            // For stock requests
                                                            echo '₱' . number_format($item['unit_cost'] ?? 0, 2);
                                                        endif; 
                                                        ?>
                                                    </td>
                                                    <!-- Total Cost -->
                                                    <td>
                                                        <?php 
                                                        if ($is_issue_type): 
                                                            // Calculate actual fulfillable quantity = MIN(Remaining Needed, Current Stock)
                                                            $fulfillable_quantity = min($remaining_needed, $stock_info['stock_quantity']);
                                                            
                                                            // Calculate total cost based on fulfillable quantity
                                                            $actual_total_cost = $fulfillable_quantity * ($item['unit_cost'] ?? 0);
                                                            
                                                            // Show the total cost with the fulfillable quantity
                                                            echo '₱' . number_format($actual_total_cost, 2);
                                                        else: 
                                                            // For stock requests, show the original total cost
                                                            echo '₱' . number_format($item_total_cost, 2);
                                                        endif; 
                                                        ?>
                                                    </td>
                                                    <!-- Stock Status -->
                                                    <td class="<?php 
                                                        // Determine text color class based on stock availability and release status
                                                        if ($delivered_quantity >= $item['quantity']) {
                                                            echo 'text-success';
                                                            $stock_text = 'Fully Released';
                                                        } elseif ($delivered_quantity > 0) {
                                                            echo 'text-warning';
                                                            $stock_text = 'Partially Released';
                                                        } elseif ($overall_status === 'Out of Stock') {
                                                            echo 'text-danger';
                                                            $stock_text = 'Out of Stock';
                                                        } elseif ($stock_info['available'] && $stock_info['stock_quantity'] > 0) {
                                                            echo 'text-success';
                                                            $stock_text = 'Available';
                                                        } else {
                                                            echo 'text-warning';
                                                            $stock_text = 'Pending';
                                                        }
                                                    ?>">
                                                        <?php echo $stock_text; ?>
                                                    </td>
                                                    <!-- FIXED: Release Status with icon only (no badge) -->
                                                    <td class="<?php echo $release_color_class; ?>">
                                                        <i class="fas <?php echo $release_icon; ?> mr-1"></i>
                                                        <?php echo $release_status; ?>
                                                    </td>
                                                    <?php else: ?>
                                                    <!-- For Stock requests - UPDATED: Added Quantity Received column -->
                                                    <td><?php echo $part_name_display; ?></td>
                                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                                    <td><?php echo number_format($item['quantity'], 2); ?></td>
                                                    <td>
                                                        <?php 
                                                        // Get received quantity from delivery_info or directly from item
                                                        $received_qty = 0;
                                                        if (isset($item['delivered_quantity']) && $item['delivered_quantity'] > 0) {
                                                            $received_qty = $item['delivered_quantity'];
                                                        } elseif (isset($delivery_info['delivered_quantity']) && $delivery_info['delivered_quantity'] > 0) {
                                                            $received_qty = $delivery_info['delivered_quantity'];
                                                        }
                                                        echo number_format($received_qty, 2);
                                                        ?>
                                                    </td>
                                                    <td>₱<?php echo number_format($item['unit_cost'] ?? 0, 2); ?></td>
                                                    <td>₱<?php echo number_format($item_total_cost, 2); ?></td>
                                                    <!-- Delivery Status with icons -->
                                                    <td>
                                                        <?php 
                                                        // Get the raw delivery status
                                                        $delivery_status = $delivery_info['status'] ?? 'Pending';
                                                        
                                                        // Map the status to the desired display values
                                                        $display_status = 'Pending';
                                                        $delivery_icon = 'fa-clock';
                                                        $delivery_color_class = 'text-warning';
                                                        
                                                        // Get delivered quantity to help determine status
                                                        $received_qty = 0;
                                                        if (isset($item['delivered_quantity']) && $item['delivered_quantity'] > 0) {
                                                            $received_qty = $item['delivered_quantity'];
                                                        } elseif (isset($delivery_info['delivered_quantity']) && $delivery_info['delivered_quantity'] > 0) {
                                                            $received_qty = $delivery_info['delivered_quantity'];
                                                        }
                                                        
                                                        // Determine the correct status based on delivered quantity and raw status
                                                        if ($delivery_status === 'Rejected' || $delivery_status === 'cancelled') {
                                                            $display_status = 'Rejected';
                                                            $delivery_icon = 'fa-times-circle';
                                                            $delivery_color_class = 'text-danger';
                                                        } elseif ($received_qty >= $item['quantity']) {
                                                            $display_status = 'Fully Received';
                                                            $delivery_icon = 'fa-check-circle';
                                                            $delivery_color_class = 'text-success';
                                                        } elseif ($received_qty > 0) {
                                                            $display_status = 'Partially Received';
                                                            $delivery_icon = 'fa-check-circle';
                                                            $delivery_color_class = 'text-warning';
                                                        } else {
                                                            // Check if there's a PO but no items received yet
                                                            if (!empty($existing_pos)) {
                                                                // PO exists but no items received
                                                                $display_status = 'Pending';
                                                                $delivery_icon = 'fa-clock';
                                                                $delivery_color_class = 'text-warning';
                                                            } else {
                                                                // No PO created yet
                                                                $display_status = 'Pending';
                                                                $delivery_icon = 'fa-clock';
                                                                $delivery_color_class = 'text-warning';
                                                            }
                                                        }
                                                        ?>
                                                        <span class="<?php echo $delivery_color_class; ?>">
                                                            <i class="fas <?php echo $delivery_icon; ?> mr-1"></i>
                                                            <?php echo htmlspecialchars($display_status); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-muted">
                                                        <?php 
                                                        // Get the received date with better handling
                                                        $received_date_display = '—';
                                                        
                                                        // Check if we have a received date in the item
                                                        if (!empty($item['received_date']) && $item['received_date'] != '0000-00-00') {
                                                            $received_date_display = formatDateMDY($item['received_date']);
                                                        } 
                                                        // Try to get it from PO items
                                                        else if (!empty($existing_pos)) {
                                                            foreach ($existing_pos as $po) {
                                                                if (isset($po_items_details[$po['id']])) {
                                                                    foreach ($po_items_details[$po['id']] as $po_item) {
                                                                        if ($po_item['pr_item_id'] == $item['id'] && 
                                                                            !empty($po_item['received_date']) && 
                                                                            $po_item['received_date'] != '0000-00-00') {
                                                                            $received_date_display = formatDateMDY($po_item['received_date']);
                                                                            break 2;
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        
                                                        echo $received_date_display;
                                                        ?>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ROUTING ACTIONS MODAL (NEW) -->
                        <div class="modal fade" id="routingActionsModal" tabindex="-1" aria-labelledby="routingActionsModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="routingActionsModalLabel">
                                            <i class="fas fa-route mr-2"></i>
                                            Routing Actions - <?php echo htmlspecialchars($pr['pr_number']); ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <!-- CURRENT STAGE INDICATOR -->
                                        <div class="alert alert-info mb-6">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            <strong>Current Stage:</strong> 
                                            <?php 
                                            switch($current_stage['stage']) {
                                                case 'requestor': echo 'Requestor'; break;
                                                case 'warehouse': echo 'Motorpool Department'; break;
                                                case 'purchasing': echo 'Purchasing Department'; break;
                                                case 'approver': echo 'Approver (CEO)'; break;
                                                case 'warehouse_receiving': echo 'Motorpool Receiving'; break;
                                                case 'warehouse_releasing': echo 'Motorpool Releasing'; break;
                                                case 'completed': echo 'Completed'; break;
                                                case 'rejected': echo 'Rejected'; break;
                                                default: echo ucfirst($current_stage['stage']);
                                            }
                                            ?>
                                        </div>

                                        <!-- ROUTING ACTION FORMS (MOVED FROM THE COLUMN) -->
                                        <?php if ($current_stage['stage'] === 'requestor' && $pr['requested_by'] == $user_id): ?>
                                            <!-- Requestor Actions - Single Button -->
                                            <form method="POST" action="">
                                                <input type="hidden" name="action" value="forward_to_warehouse">
                                                
                                                <?php if ($is_issue_type): ?>
                                                <?php
                                                // FIX: Check only for "Out of Stock" items, allow "Partially Available"
                                                $has_any_stock = false;
                                                $out_of_stock_items = [];
                                                $items_with_stock = [];
                                                foreach ($items as $item) {
                                                    $stock_info = $item['stock_info'];
                                                    if ($stock_info['available'] && $stock_info['stock_quantity'] > 0) {
                                                        $has_any_stock = true;
                                                        $items_with_stock[] = [
                                                            'part_name' => $item['part_name'],
                                                            'requested' => $item['quantity'],
                                                            'available' => $stock_info['stock_quantity'],
                                                            'status' => $item['overall_status']
                                                        ];
                                                    } else {
                                                        $out_of_stock_items[] = [
                                                            'part_name' => $item['part_name'],
                                                            'requested' => $item['quantity'],
                                                            'available' => 0,
                                                            'status' => 'Out of Stock'
                                                        ];
                                                    }
                                                }
                                                
                                                // Allow forwarding if there's at least one item with available stock
                                                // Even if some items are out of stock
                                                if (!$has_any_stock): ?>
                                                <div class="alert alert-danger mb-4">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                                    <strong>Cannot Forward:</strong> No items have available stock. All items are out of stock.
                                                </div>
                                                <?php elseif (!empty($out_of_stock_items)): ?>
                                                <div class="alert alert-warning mb-4">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                                    <strong>Note:</strong> <?php echo count($out_of_stock_items); ?> item(s) are out of stock and will require a Purchase Order.
                                                    <div class="mt-2">
                                                        <strong>Items with stock available:</strong>
                                                        <ul class="list-disc pl-5 mb-0">
                                                            <?php foreach ($items_with_stock as $item_with_stock): ?>
                                                            <li><?php echo htmlspecialchars($item_with_stock['part_name']); ?>: <?php echo $item_with_stock['available']; ?> available (<?php echo $item_with_stock['status']; ?>)</li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <div class="mb-4">
                                                    <label for="remarks" class="form-label">Remarks (Optional)</label>
                                                    <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                                                </div>
                                                <div class="grid">
                                                    <button type="submit" class="btn btn-primary" <?php echo ($is_issue_type && !$has_any_stock) ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-forward mr-1"></i> Forward to Motorpool Department
                                                    </button>
                                                </div>
                                            </form>
                                            
                                        <?php elseif ($current_stage['stage'] === 'warehouse' && 
                                                $user['department'] === 'Motorpool' && 
                                                $user['accounttype'] === 'Admin'): ?>
                                            <!-- Motorpool Actions - Two Buttons -->
                                            <form method="POST" action="" id="warehouseActionsForm">
                                                <?php if ($is_issue_type): ?>
                                                <?php
                                                // FIX: Check if there's any available stock
                                                $no_stock_at_all = true;
                                                foreach ($items as $item) {
                                                    $stock_info = $item['stock_info'];
                                                    if ($stock_info['available'] && $stock_info['stock_quantity'] > 0) {
                                                        $no_stock_at_all = false;
                                                        break;
                                                    }
                                                }
                                                
                                                if ($no_stock_at_all): ?>
                                                <div class="alert alert-danger mb-4">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                                    <strong>Cannot Approve:</strong> No stock available for any requested items.
                                                </div>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <div class="mb-4">
                                                    <label for="remarks" class="form-label">Remarks</label>
                                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                </div>
                                                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                                    <div class="min-w-0">
                                                        <button type="submit" name="action" value="approve_warehouse" class="btn btn-success w-full" <?php echo ($is_issue_type && $no_stock_at_all) ? 'disabled' : ''; ?>>
                                                            <i class="fas fa-check mr-1"></i> Approve
                                                        </button>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <button type="submit" name="action" value="reject_warehouse" class="btn btn-danger w-full">
                                                            <i class="fas fa-times mr-1"></i> Reject
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                            
                                        <?php elseif ($current_stage['stage'] === 'purchasing' && 
                                                $user['department'] === 'Admin' && 
                                                $user['position'] === 'Purchaser' && 
                                                $user['accounttype'] === 'Admin'): ?>
                                            <!-- Purchasing Department Actions -->
                                            <?php if ($is_issue_type): ?>
                                                <!-- For issue and issue_materials requests - Check if there's any available stock -->
                                                <?php
                                                $has_available_stock = false;
                                                foreach ($items as $item) {
                                                    if ($item['stock_info']['available'] && $item['stock_info']['stock_quantity'] > 0) {
                                                        $has_available_stock = true;
                                                        break;
                                                    }
                                                }
                                                ?>
                                                
                                                <?php if ($has_available_stock): ?>
                                                    <!-- If there's available stock (fully or partially), Job Order or Withdrawal Slip is required -->
                                                    <form method="POST" action="" id="purchasingActionsForm">
                                                        <div class="mb-4">
                                                            <?php if (!$job_order_exists && !$withdrawal_slip_exists): ?>
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                                You must create a <?php echo $is_issue_materials ? 'Withdrawal Slip' : 'Job Order'; ?> before approving.
                                                            </div>
                                                            <?php endif; ?>
                                                            <label for="remarks" class="form-label">Remarks</label>
                                                            <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                        </div>
                                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                                            <div class="min-w-0">
                                                                <button type="submit" name="action" value="approve_purchasing" class="btn btn-success w-full" <?php echo (!$job_order_exists && !$withdrawal_slip_exists) ? 'disabled' : ''; ?>>
                                                                    <i class="fas fa-check mr-1"></i> Approve (With <?php echo $is_issue_materials ? 'Withdrawal Slip' : 'Job Order'; ?>)
                                                                </button>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <button type="submit" name="action" value="reject_purchasing" class="btn btn-danger w-full">
                                                                    <i class="fas fa-times mr-1"></i> Reject
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                    
                                                    <hr class="my-4">
                                                    
                                                    <!-- Create Job Order or Withdrawal Slip Section - Show for issue and issue_materials requests with available stock -->
                                                    <?php if ($is_issue_materials && !$withdrawal_slip_exists): ?>
                                                    <div class="mb-4">
                                                        <label for="withdrawal_slip_remarks" class="form-label">Withdrawal Slip Remarks (Optional)</label>
                                                        <textarea class="form-control" id="withdrawal_slip_remarks" name="withdrawal_slip_remarks" rows="2"></textarea>
                                                    </div>
                                                    <div class="grid">
                                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createWithdrawalSlipModal">
                                                            <i class="fas fa-file-invoice mr-1"></i> Create Withdrawal Slip
                                                        </button>
                                                    </div>
                                                    <?php elseif (!$is_issue_materials && !$job_order_exists): ?>
                                                    <div class="mb-4">
                                                        <label for="job_order_remarks" class="form-label">Job Order Remarks (Optional)</label>
                                                        <textarea class="form-control" id="job_order_remarks" name="job_order_remarks" rows="2"></textarea>
                                                    </div>
                                                    <div class="grid">
                                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createJobOrderModal">
                                                            <i class="fas fa-tools mr-1"></i> Create Job Order
                                                        </button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle mr-2"></i>
                                                        <?php echo $is_issue_materials ? 'Withdrawal Slip' : 'Job Order'; ?> has been created. You can now approve the request.
                                                    </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <!-- No available stock at all - PO is required -->
                                                    <form method="POST" action="" id="purchasingActionsForm">
                                                        <div class="mb-4">
                                                            <?php if (!$po_exists): ?>
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                                You must create a Purchase Order before approving.
                                                            </div>
                                                            <?php endif; ?>
                                                            <label for="remarks" class="form-label">Remarks</label>
                                                            <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                        </div>
                                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                                            <div class="min-w-0">
                                                                <button type="submit" name="action" value="approve_purchasing" class="btn btn-success w-full" <?php echo !$po_exists ? 'disabled' : ''; ?>>
                                                                    <i class="fas fa-check mr-1"></i> Approve
                                                                </button>
                                                            </div>
                                                            <div class="min-w-0">
                                                                <button type="submit" name="action" value="reject_purchasing" class="btn btn-danger w-full">
                                                                    <i class="fas fa-times mr-1"></i> Reject
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                    
                                                    <hr class="my-4">
                                                    
                                                    <!-- Create PO Section - Only show if no PO exists -->
                                                    <?php if (!$po_exists): ?>
                                                    <div class="mb-4">
                                                        <label for="po_remarks" class="form-label">PO Remarks (Optional)</label>
                                                        <textarea class="form-control" id="po_remarks" name="po_remarks" rows="2"></textarea>
                                                    </div>
                                                    <div class="grid">
                                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                                            <i class="fas fa-file-invoice-dollar mr-1"></i> Create Purchase Order
                                                        </button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle mr-2"></i>
                                                        Purchase Order has been created. You can now approve the request.
                                                    </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- For stock requests -->
                                                <form method="POST" action="" id="purchasingActionsForm">
                                                    <div class="mb-4">
                                                        <?php if (!$po_exists): ?>
                                                        <div class="alert alert-warning">
                                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                                            You must create a Purchase Order before approving.
                                                        </div>
                                                        <?php endif; ?>
                                                        <label for="remarks" class="form-label">Remarks</label>
                                                        <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                    </div>
                                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                                        <div class="min-w-0">
                                                            <button type="submit" name="action" value="approve_purchasing" class="btn btn-success w-full" <?php echo !$po_exists ? 'disabled' : ''; ?>>
                                                                <i class="fas fa-check mr-1"></i> Approve
                                                            </button>
                                                        </div>
                                                        <div class="min-w-0">
                                                            <button type="submit" name="action" value="reject_purchasing" class="btn btn-danger w-full">
                                                                <i class="fas fa-times mr-1"></i> Reject
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                                
                                                <hr class="my-4">
                                                
                                                <!-- Create PO Section - Only show for stock requests if no PO exists -->
                                                <?php if (!$po_exists): ?>
                                                <div class="mb-4">
                                                    <label for="po_remarks" class="form-label">PO Remarks (Optional)</label>
                                                    <textarea class="form-control" id="po_remarks" name="po_remarks" rows="2"></textarea>
                                                </div>
                                                <div class="grid">
                                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                                        <i class="fas fa-file-invoice-dollar mr-1"></i> Create Purchase Order
                                                    </button>
                                                </div>
                                                <?php else: ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle mr-2"></i>
                                                    Purchase Order has been created. You can now approve the request.
                                                </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                        <?php elseif ($current_stage['stage'] === 'approver' && 
                                                $user['department'] === 'Admin' && 
                                                $user['position'] === 'CEO' && 
                                                $user['accounttype'] === 'Admin'): ?>
                                            <!-- Approver Actions - Two Buttons -->
                                            <form method="POST" action="" id="approverActionsForm">
                                                <div class="mb-4">
                                                    <label for="remarks" class="form-label">Remarks</label>
                                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                </div>
                                                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                                    <div class="min-w-0">
                                                        <button type="submit" name="action" value="approve_approver" class="btn btn-success w-full">
                                                            <i class="fas fa-check mr-1"></i> Approve
                                                        </button>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <button type="submit" name="action" value="reject_approver" class="btn btn-danger w-full">
                                                            <i class="fas fa-times mr-1"></i> Reject
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                            
                                        <?php elseif ($current_stage['stage'] === 'warehouse_receiving' && 
                                                $user['department'] === 'Motorpool' && 
                                                $user['accounttype'] === 'Admin' &&
                                                $pr['request_type'] === 'stock'): ?>
                                            <!-- Motorpool Receiving Actions - Single Button -->
                                            <form method="POST" action="" id="warehouseReceivingActionsForm">
                                                <div class="mb-4">
                                                    <label for="remarks" class="form-label">Remarks</label>
                                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                </div>
                                                <div class="grid">
                                                    <button type="submit" name="action" value="complete_warehouse_receiving" class="btn btn-success" <?php echo $items_already_received ? '' : 'disabled'; ?>>
                                                        <i class="fas fa-check mr-1"></i> Complete Receiving
                                                    </button>
                                                </div>
                                                <?php if (!$items_already_received): ?>
                                                <div class="alert alert-warning mt-4">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                                    <strong>Action Required:</strong> You must first receive the items using "Confirm Items Received" before you can complete the receiving process.
                                                </div>
                                                <?php endif; ?>
                                            </form>
                                            
                                        <?php elseif ($current_stage['stage'] === 'warehouse_releasing' && 
                                                $user['department'] === 'Motorpool' && 
                                                $user['accounttype'] === 'Admin' &&
                                                $is_issue_type): ?>
                                            <!-- Motorpool Releasing Actions - Single Button -->
                                            <form method="POST" action="" id="warehouseReleasingActionsForm">
                                                <div class="mb-4">
                                                    <label for="remarks" class="form-label">Remarks</label>
                                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                                </div>
                                                <div class="grid">
                                                    <button type="submit" name="action" value="complete_warehouse_releasing" class="btn btn-success" <?php echo ($items_already_released && !$releasing_already_completed) ? '' : 'disabled'; ?>>
                                                        <i class="fas fa-check mr-1"></i> Complete Releasing
                                                    </button>
                                                </div>
                                                <?php if (!$items_already_released): ?>
                                                <div class="alert alert-warning mt-4">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                                    <strong>Action Required:</strong> You must first release the items using "Confirm Items Released (FIFO)" before you can complete the releasing process.
                                                </div>
                                                <?php elseif ($releasing_already_completed): ?>
                                                <div class="alert alert-info mt-4">
                                                    <i class="fas fa-info-circle mr-2"></i>
                                                    <strong>Releasing Completed:</strong> The Motorpool releasing process has already been completed for this PR.
                                                </div>
                                                <?php endif; ?>
                                            </form>
                                            
                                        <?php else: ?>
                                            <div class="alert alert-info text-center">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                No actions available for you at this stage.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Routing History -->
                    <div class="card mb-6">
                        <div class="card-header">
                            <i class="fas fa-history mr-1"></i>
                            Routing History
                        </div>
                        <div class="card-body">
                            <?php if (!empty($routing_history)): ?>
                            <div class="table-responsive max-h-[400px] overflow-y-auto">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Action</th>
                                            <th>Performed By</th>
                                            <th>Department</th>
                                            <th>Remarks</th>
                                            <th>From Stage</th>
                                            <th>To Stage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($routing_history as $history): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(ocp_datetime_mdy($history['created_at'])); ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    if (strpos($history['action'], 'Approved') !== false) echo 'badge-success';
                                                    elseif (strpos($history['action'], 'Rejected') !== false) echo 'badge-danger';
                                                    elseif (strpos($history['action'], 'Delivered') !== false) echo 'badge-info';
                                                    elseif (strpos($history['action'], 'Purchase Order') !== false) echo 'badge-warning';
                                                    elseif (strpos($history['action'], 'Job Order') !== false) echo 'badge-info';
                                                    elseif (strpos($history['action'], 'Withdrawal Slip') !== false) echo 'badge-info';
                                                    elseif (strpos($history['action'], 'Received') !== false) echo 'badge-primary';
                                                    elseif (strpos($history['action'], 'Released') !== false) echo 'badge-primary';
                                                    elseif (strpos($history['action'], 'Completed') !== false) echo 'badge-success';
                                                    elseif (strpos($history['action'], 'Forwarded') !== false) echo 'badge-info';
                                                    else echo 'badge-neutral';
                                                    ?>
                                                ">
                                                    <?php echo htmlspecialchars($history['action']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatUserName($history); ?></td>
                                            <td><?php echo htmlspecialchars($history['department'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($history['remarks']); ?></td>
                                            <td>
                                                <?php 
                                                switch($history['stage_from'] ?? '') {
                                                    case 'requestor': echo 'Requestor'; break;
                                                    case 'warehouse': echo 'Motorpool Department'; break;
                                                    case 'purchasing': echo 'Purchasing'; break;
                                                    case 'approver': echo 'Approver'; break;
                                                    case 'warehouse_receiving': echo 'Motorpool Receiving'; break;
                                                    case 'warehouse_releasing': echo 'Motorpool Releasing'; break;
                                                    case 'completed': echo 'Completed'; break;
                                                    case 'rejected': echo 'Rejected'; break;
                                                    default: echo ucfirst($history['stage_from'] ?? 'Unknown');
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                switch($history['stage_to'] ?? '') {
                                                    case 'requestor': echo 'Requestor'; break;
                                                    case 'warehouse': echo 'Motorpool Department'; break;
                                                    case 'purchasing': echo 'Purchasing'; break;
                                                    case 'approver': echo 'Approver'; break;
                                                    case 'warehouse_receiving': echo 'Motorpool Receiving'; break;
                                                    case 'warehouse_releasing': echo 'Motorpool Releasing'; break;
                                                    case 'completed': echo 'Completed'; break;
                                                    case 'rejected': echo 'Rejected'; break;
                                                    default: echo ucfirst($history['stage_to'] ?? 'Unknown');
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-6">
                                <i class="fas fa-history fa-3x text-muted mb-4"></i>
                                <h5 class="text-xl font-semibold">No Routing History</h5>
                                <p class="text-muted">Routing actions will appear here once performed.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

    <!-- Create Purchase Order Modal -->
    <div class="modal fade" id="createPOModal" tabindex="-1" aria-labelledby="createPOModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPOModalLabel">Create Purchase Order for Spare Parts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="createPOForm">
                    <input type="hidden" name="action" value="create_purchase_order">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-1"></i>
                            This will create a purchase order for the selected spare parts.
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <label for="expected_delivery" class="form-label">Expected Delivery Date</label>
                                <input type="date" class="form-control" id="expected_delivery" name="expected_delivery" required>
                            </div>
                            <div class="min-w-0">
                                <label for="po_remarks" class="form-label">PO Remarks</label>
                                <textarea class="form-control" id="po_remarks" name="po_remarks" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <h6 class="text-base font-semibold">Spare Parts for Purchase Order:</h6>
                        <div class="table-responsive max-h-[400px] overflow-y-auto" style="max-height: 400px;">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" id="selectAllItemsPO">
                                        </th>
                                        <th>Part Name</th>
                                        <th>Category</th>
                                        <th>Supplier</th>
                                        <th>Quantity</th>
                                        <th>Unit Cost</th>
                                        <th>Total Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($items)): ?>
                                        <?php foreach ($items as $item): 
                                            // Format part name with part number in parentheses
                                            $part_name_display = htmlspecialchars($item['part_name']);
                                            if (!empty($item['part_number'])) {
                                                $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                            }
                                        ?>
                                        <tr class="bg-slate-50">
                                            <td>
                                                <input type="checkbox" name="selected_items[]" value="<?php echo $item['id']; ?>" 
                                                    class="item-checkbox-po" data-item-id="<?php echo $item['id']; ?>" checked>
                                            </td>
                                            <td><?php echo $part_name_display; ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td>
                                                <!-- NEW: Supplier dropdown for each item -->
                                                <select name="supplier_id_<?php echo $item['id']; ?>" 
                                                        class="form-select form-select-sm min-w-[200px]" 
                                                        style="min-width: 150px;"
                                                        data-item-id="<?php echo $item['id']; ?>" required>
                                                    <option value="">Select Supplier</option>
                                                    <?php foreach ($all_suppliers as $supplier): ?>
                                                        <option value="<?php echo $supplier['id']; ?>" 
                                                            <?php echo ($supplier['id'] == $pr['supplier_id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" name="quantity_<?php echo $item['id']; ?>" 
                                                    value="<?php echo $item['quantity']; ?>" 
                                                    min="1" max="<?php echo $item['quantity']; ?>" 
                                                    class="form-control form-control-sm quantity-input-po" 
                                                    data-item-id="<?php echo $item['id']; ?>"
                                                    style="width: 80px;">
                                            </td>
                                            <td>
                                                <input type="number" name="unit_cost_<?php echo $item['id']; ?>" 
                                                    value="<?php echo $item['unit_cost']; ?>" 
                                                    step="0.01" min="0.01" 
                                                    class="form-control form-control-sm unit-cost-input-po" 
                                                    data-item-id="<?php echo $item['id']; ?>"
                                                    style="width: 100px;" required>
                                            </td>
                                            <td>
                                                <span class="total-cost-po" id="total_po_<?php echo $item['id']; ?>">
                                                    ₱<?php echo number_format($item['quantity'] * $item['unit_cost'], 2); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4"> <!-- Updated colspan from 6 to 7 -->
                                                <i class="fas fa-exclamation-circle text-warning mr-2"></i>
                                                No spare parts found for this purchase request.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mt-4">
                            <div class="min-w-0">
                                <strong>Total Items Selected: <span id="selectedCountPO"><?php echo count($items); ?></span></strong>
                            </div>
                            <div class="min-w-0 text-right">
                                <strong>Grand Total: ₱<span id="grandTotalPO"><?php echo number_format($total_estimated_cost, 2); ?></span></strong>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="createPOBtn" <?php echo empty($items) ? 'disabled' : ''; ?>>
                            <i class="fas fa-file-invoice-dollar mr-1"></i> Create Purchase Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Job Order Modal (only for issue requests) -->
    <div class="modal fade" id="createJobOrderModal" tabindex="-1" aria-labelledby="createJobOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createJobOrderModalLabel">Create Job Order for Spare Parts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="createJobOrderForm">
                    <input type="hidden" name="action" value="create_job_order">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-1"></i>
                            This will create a Job Order for the selected spare parts (for issue requests with available stock).
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <label for="job_order_date" class="form-label">Job Order Date</label>
                                <input type="date" class="form-control" id="job_order_date" name="job_order_date" required>
                            </div>
                            <div class="min-w-0">
                                <label for="job_order_remarks" class="form-label">Job Order Remarks</label>
                                <textarea class="form-control" id="job_order_remarks" name="job_order_remarks" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <h6 class="text-base font-semibold">Spare Parts for Job Order:</h6>
                        <div class="table-responsive max-h-[400px] overflow-y-auto" style="max-height: 400px;">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" id="selectAllItemsJO">
                                        </th>
                                        <th>Part Name</th>
                                        <th>Category</th>
                                        <th>Quantity Requested</th>
                                        <th>Available Stock</th>
                                        <th>Quantity for Job Order</th>
                                        <th>Vehicle/Equipment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($items)): ?>
                                        <?php foreach ($items as $item): 
                                            $available_stock = $item['stock_info']['stock_quantity'] ?? 0;
                                            $requested_quantity = $item['quantity'];
                                            // FIX: For "Partially Available" items, only allow up to available stock
                                            $max_quantity = min($available_stock, $requested_quantity);
                                            
                                            // Format part name with part number in parentheses
                                            $part_name_display = htmlspecialchars($item['part_name']);
                                            if (!empty($item['part_number'])) {
                                                $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                            }
                                        ?>
                                        <tr class="bg-slate-50">
                                            <td>
                                                <input type="checkbox" name="selected_items[]" value="<?php echo $item['id']; ?>" 
                                                    class="item-checkbox-jo" data-item-id="<?php echo $item['id']; ?>" 
                                                    <?php echo ($available_stock > 0) ? 'checked' : 'disabled'; ?>>
                                            </td>
                                            <td><?php echo $part_name_display; ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo number_format($requested_quantity, 2); ?></td>
                                            <td><?php echo number_format($available_stock, 2); ?></td>
                                            <td>
                                                <?php if ($available_stock > 0): ?>
                                                    <input type="number" name="quantity_<?php echo $item['id']; ?>" 
                                                        value="<?php echo $max_quantity; ?>" 
                                                        min="0.01" max="<?php echo $max_quantity; ?>" 
                                                        step="0.01"
                                                        class="form-control form-control-sm quantity-input-jo" 
                                                        data-item-id="<?php echo $item['id']; ?>"
                                                        data-max-stock="<?php echo $available_stock; ?>"
                                                        style="width: 80px;">
                                                <?php else: ?>
                                                    <span class="text-danger">Out of stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['vehicle_equipment_display']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="fas fa-exclamation-circle text-warning mr-2"></i>
                                                No spare parts found for this purchase request.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mt-4">
                            <div class="min-w-0">
                                <strong>Total Items Selected: <span id="selectedCountJO">0</span></strong>
                            </div>
                            <div class="min-w-0">
                                <strong>Purpose: <?php echo htmlspecialchars($pr['purpose'] ?? 'N/A'); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="createJobOrderBtn" disabled>
                            <i class="fas fa-tools mr-1"></i> Create Job Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Withdrawal Slip Modal (only for issue_materials requests) -->
    <div class="modal fade" id="createWithdrawalSlipModal" tabindex="-1" aria-labelledby="createWithdrawalSlipModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createWithdrawalSlipModalLabel">
                        <?php echo $is_issue_materials ? 'Create Withdrawal Slip for Materials' : 'Create Withdrawal Slip for Spare Parts'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="createWithdrawalSlipForm">
                    <input type="hidden" name="action" value="create_withdrawal_slip">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-1"></i>
                            This will create a Withdrawal Slip for the selected <?php echo $is_issue_materials ? 'materials' : 'spare parts'; ?> 
                            (for <?php echo $is_issue_materials ? 'issue materials' : 'issue'; ?> requests with available stock).
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                            <div class="min-w-0">
                                <label for="withdrawal_slip_date" class="form-label">Withdrawal Date</label>
                                <input type="date" class="form-control" id="withdrawal_slip_date" name="withdrawal_slip_date" required>
                            </div>
                            <div class="min-w-0">
                                <label for="withdrawal_slip_remarks" class="form-label">Withdrawal Slip Remarks</label>
                                <textarea class="form-control" id="withdrawal_slip_remarks" name="withdrawal_slip_remarks" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <h6 class="text-base font-semibold">Materials for Withdrawal Slip:</h6>
                        <div class="table-responsive max-h-[400px] overflow-y-auto" style="max-height: 400px;">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" id="selectAllItemsWS">
                                        </th>
                                        <th>Part Name</th>
                                        <th>Category</th>
                                        <th>Quantity Requested</th>
                                        <th>Available Stock</th>
                                        <th>Quantity for Withdrawal</th>
                                        <?php if (!$is_issue_materials): ?>
                                        <!-- Only show Vehicle/Equipment for Issue Parts Request, NOT for Issue Materials -->
                                        <th>Vehicle/Equipment</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($items)): ?>
                                        <?php foreach ($items as $item): 
                                            $available_stock = $item['stock_info']['stock_quantity'] ?? 0;
                                            $requested_quantity = $item['quantity'];
                                            // For "Partially Available" items, only allow up to available stock
                                            $max_quantity = min($available_stock, $requested_quantity);
                                            
                                            // Format part name with part number in parentheses
                                            $part_name_display = htmlspecialchars($item['part_name']);
                                            if (!empty($item['part_number'])) {
                                                $part_name_display .= ' (' . htmlspecialchars($item['part_number']) . ')';
                                            }
                                        ?>
                                        <tr class="bg-slate-50">
                                            <td>
                                                <input type="checkbox" name="selected_items[]" value="<?php echo $item['id']; ?>" 
                                                    class="item-checkbox-ws" data-item-id="<?php echo $item['id']; ?>" 
                                                    <?php echo ($available_stock > 0) ? 'checked' : 'disabled'; ?>>
                                            </td>
                                            <td><?php echo $part_name_display; ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo number_format($requested_quantity, 2); ?></td>
                                            <td><?php echo number_format($available_stock, 2); ?></td>
                                            <td>
                                                <?php if ($available_stock > 0): ?>
                                                    <input type="number" name="quantity_<?php echo $item['id']; ?>" 
                                                        value="<?php echo $max_quantity; ?>" 
                                                        min="0.01" max="<?php echo $max_quantity; ?>" 
                                                        step="0.01"
                                                        class="form-control form-control-sm quantity-input-ws" 
                                                        data-item-id="<?php echo $item['id']; ?>"
                                                        data-max-stock="<?php echo $available_stock; ?>">
                                                <?php else: ?>
                                                    <span class="text-danger">Out of stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if (!$is_issue_materials): ?>
                                            <!-- Only show Vehicle/Equipment for Issue Parts Request, NOT for Issue Materials -->
                                            <td><?php echo htmlspecialchars($item['vehicle_equipment_display']); ?></td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <?php if ($is_issue_materials): ?>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-exclamation-circle text-warning mr-2"></i>
                                                No spare parts found for this purchase request.
                                            </td>
                                            <?php else: ?>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="fas fa-exclamation-circle text-warning mr-2"></i>
                                                No spare parts found for this purchase request.
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mt-4">
                            <div class="min-w-0">
                                <strong>Total Items Selected: <span id="selectedCountWS">0</span></strong>
                            </div>
                            <div class="min-w-0">
                                <strong>Purpose: <?php echo htmlspecialchars($pr['purpose'] ?? 'N/A'); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="createWithdrawalSlipBtn" disabled>
                            <i class="fas fa-file-invoice mr-1"></i> Create Withdrawal Slip for <?php echo $is_issue_materials ? 'Materials' : 'Spare Parts'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Purchase Order Modal -->
    <div class="modal fade" id="viewPOModal" tabindex="-1" aria-labelledby="viewPOModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPOModalLabel">Purchase Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-6">
                        <div class="min-w-0">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">PO Number:</th>
                                    <td id="modal-po-number">-</td>
                                </tr>
                                <tr>
                                    <th>Expected Delivery:</th>
                                    <td id="modal-expected-delivery">-</td>
                                </tr>
                            </table>
                        </div>
                        <div class="min-w-0">
                            <table class="table table-borderless">
                                <tr>
                                    <th>PO Date:</th>
                                    <td id="modal-po-date">-</td>
                                </tr>
                                <tr>
                                    <th width="40%">Total Amount:</th>
                                    <td id="modal-total-amount">-</td>
                                </tr>
                                <tr>
                                    <th>Remarks:</th>
                                    <td id="modal-po-remarks">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <h6 class="text-base font-semibold">PO Items:</h6>
                    <div class="table-responsive max-h-[400px] overflow-y-auto" style="max-height: 400px;">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Part Name</th>
                                    <th>Category</th>
                                    <th>Supplier</th> <!-- ADDED Supplier column -->
                                    <th>Quantity</th>
                                    <th>Received</th>
                                    <th>Remaining</th>
                                    <th>Unit Cost</th>
                                    <th>Total Cost</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="modal-po-items">
                                <!-- PO items will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Job Order Modal -->
    <div class="modal fade" id="viewJobOrderModal" tabindex="-1" aria-labelledby="viewJobOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewJobOrderModalLabel">Job Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-6">
                        <div class="min-w-0">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Job Order Number:</th>
                                    <td id="modal-job-order-number">-</td>
                                </tr>
                                <tr>
                                    <th>Technician:</th>
                                    <td id="modal-technician">-</td>
                                </tr>
                            </table>
                        </div>
                        <div class="min-w-0">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Job Order Date:</th>
                                    <td id="modal-job-order-date">-</td>
                                </tr>
                                <tr>
                                    <th width="40%">Purpose:</th>
                                    <td id="modal-purpose">-</td>
                                </tr>
                                <tr>
                                    <th>Remarks:</th>
                                    <td id="modal-job-order-remarks">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Update the thead section in the Job Order Details modal -->
                    <h6 class="text-base font-semibold">Job Order Items:</h6>
                    <div class="table-responsive max-h-[400px] overflow-y-auto" style="max-height: 400px;">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Vehicle/Equipment</th>
                                    <th>Part Name</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total Cost</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="modal-job-order-items">
                                <!-- Job Order items will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Withdrawal Slip Modal - UPDATED TO HIDE VEHICLE/EQUIPMENT FOR ISSUE MATERIALS -->
    <div class="modal fade" id="viewWithdrawalSlipModal" tabindex="-1" aria-labelledby="viewWithdrawalSlipModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewWithdrawalSlipModalLabel">Withdrawal Slip Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-6">
                        <div class="min-w-0">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Withdrawal Slip Number:</th>
                                    <td id="modal-withdrawal-slip-number">-</td>
                                </tr>
                                <tr>
                                    <th>Prepared By:</th>
                                    <td id="modal-requested-by">-</td>
                                </tr>
                                <tr>
                                    <th>Issue to Employee:</th>
                                    <td id="modal-employee">-</td>
                                </tr>
                            </table>
                        </div>
                        <div class="min-w-0">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Withdrawal Date:</th>
                                    <td id="modal-withdrawal-slip-date">-</td>
                                </tr>
                                <tr>
                                    <th width="40%">Purpose:</th>
                                    <td id="modal-ws-purpose">-</td>
                                </tr>
                                <tr>
                                    <th>Remarks:</th>
                                    <td id="modal-withdrawal-slip-remarks">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <h6 class="text-base font-semibold">Withdrawal Slip Items:</h6>
                    <div class="table-responsive max-h-[400px] overflow-y-auto" style="max-height: 400px;">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Part Name</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total Cost</th>
                                    <?php if (!$is_issue_materials): ?>
                                    <!-- Only show Vehicle/Equipment for Issue Parts Request, NOT for Issue Materials -->
                                    <th>Vehicle/Equipment</th>
                                    <?php endif; ?>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="modal-withdrawal-slip-items">
                                <!-- Withdrawal Slip items will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Purchase Order Modal -->
    <div class="modal fade" id="editPOModal" tabindex="-1" aria-labelledby="editPOModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPOModalLabel">Edit Purchase Order Quantities</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="editPOForm">
                    <input type="hidden" name="action" value="update_po_quantity">
                    <input type="hidden" name="po_id" id="edit_po_id">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            You can only edit quantities for PO items. Other fields are read-only.
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-6">
                            <div class="min-w-0">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">PO Number:</th>
                                        <td id="edit-modal-po-number">-</td>
                                    </tr>
                                    <tr>
                                        <th>PO Date:</th>
                                        <td id="edit-modal-po-date">-</td>
                                    </tr>
                                    <tr>
                                        <th>Expected Delivery:</th>
                                        <td id="edit-modal-expected-delivery">-</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="min-w-0">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Total Amount:</th>
                                        <td id="edit-modal-total-amount">-</td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td id="edit-modal-po-status">-</td>
                                    </tr>
                                    <tr>
                                        <th>Remarks:</th>
                                        <td id="edit-modal-po-remarks">-</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <h6 class="text-base font-semibold">Edit PO Item Quantities:</h6>
                        <div class="table-responsive max-h-[400px] overflow-y-auto" style="max-height: 400px;">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Part Number</th>
                                        <th>Part Name</th>
                                        <th>Category</th>
                                        <th>Current Quantity</th>
                                        <th>New Quantity</th>
                                        <th>Unit Cost</th>
                                        <th>Total Cost</th>
                                    </tr>
                                </thead>
                                <tbody id="edit-modal-po-items">
                                    <!-- PO items for editing will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save mr-1"></i> Update Quantities
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php
    /* Data island consumed by assets/js/pr_spare_view_routing.js. */
    $__ocp_data = [];
    /* swalDataTitle = $swal_data['title'] [guarded] */
    if (!empty($swal_data)) {
        $__ocp_data["swalDataTitle"] = $swal_data['title'];
    }
    /* swalDataText = $swal_data['text'] [guarded] */
    if (!empty($swal_data)) {
        $__ocp_data["swalDataText"] = $swal_data['text'];
    }
    /* swalDataIcon = $swal_data['icon'] [guarded] */
    if (!empty($swal_data)) {
        $__ocp_data["swalDataIcon"] = $swal_data['icon'];
    }
    $__ocp_data["isIssueMaterials"] = $is_issue_materials ? 'Materials' : 'Spare Parts';
    /* poItemsDetails */
    ob_start();
    include __DIR__ . "/includes/partials/pr_spare_view_routing/poItemsDetails.php";
    $__ocp_data["poItemsDetails"] = ob_get_clean();
    $__ocp_data["prSupplierName"] = $pr['supplier_name'] ?? 'N/A';
    // The technician's name as the job-order modal shows it. The markup used to compute
    // this at the point it echoed it; the script that fills the modal reads the value
    // from the island now, so it is computed here instead.
    $technician_formatted = 'N/A';
    if (!empty($pr['tech_firstname'])) {
        $technician_formatted = $pr['tech_firstname'];
        if (!empty($pr['tech_middlename'])) {
            $technician_formatted .= ' ' . substr($pr['tech_middlename'], 0, 1) . '.';
        }
        $technician_formatted .= ' ' . $pr['tech_lastname'];
        if (!empty($pr['tech_suffix'])) {
            $technician_formatted .= ' ' . $pr['tech_suffix'];
        }
    }
    $__ocp_data["technicianFormatted"] = $technician_formatted;
    /* jobOrderDetails */
    ob_start();
    include __DIR__ . "/includes/partials/pr_spare_view_routing/jobOrderDetails.php";
    $__ocp_data["jobOrderDetails"] = ob_get_clean();
    /* withdrawalSlipDetails */
    ob_start();
    include __DIR__ . "/includes/partials/pr_spare_view_routing/withdrawalSlipDetails.php";
    $__ocp_data["withdrawalSlipDetails"] = ob_get_clean();
    $__ocp_data["prVehicleName"] = $pr['vehicle_name'] ?? $pr['equipment_name'] ?? 'N/A';
    /* Flags for the script. It is a separate request, so it cannot test this
     * page's variables itself; these answer for it. Each is false on an ordinary
     * load, so nothing is shown unless there is something to show. */
    $__ocp_data["hasMessage"] = (!empty($swal_data));
    /* The script is a separate request, so it cannot test this page's variables
     * itself. A real boolean, not the 'Materials'/'Spare Parts' label it used to
     * carry: JS reads any non-empty string as true, so that label made every
     * request take the materials branch and the script drew one column too few,
     * against the header this page renders for issue parts. */
    $__ocp_data["isMaterialRequest"] = (bool) $is_issue_materials;
    ocp_page_data("pr_spare_view_routing", $__ocp_data);
    unset($__ocp_data);
    ?>
    <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
    <script src="assets/js/pr_spare_view_routing.js.php"></script>
</body>
</html>
