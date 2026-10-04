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
    header('Location: purchase_request.php');
    exit();
}

$pr_id = $_GET['id'];

// All of this page's actions live in one file: the routing decisions - approving,
// rejecting, converting to a purchase order or a withdrawal slip, and the per-item
// stock handling. The forms post back to this page, so it is pulled in before
// anything is read or rendered - and after the header above, because the handlers
// work from $pr_id, $user and the request they route.
if (!defined('OCP_PR_VIEW_ROUTING_ACTIONS_RAN')) {
    require __DIR__ . '/actions/pr_view_routing-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup and the handlers above need, which are unpacked into this scope. The read
// block behind it works out, from the request itself, what it can become.
$ocp_endpoint = require __DIR__ . '/api/pr_view_routing-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);
// Function to get status badge class
function getStatusBadge($status) {
    switch ($status) {
        case 'approved':
            return 'badge badge-success';
        case 'rejected':
            return 'badge badge-danger';
        case 'pending':
            return 'badge badge-warning';
        case 'processing':
            return 'badge badge-info';
        case 'completed':
            return 'badge badge-primary';
        default:
            return 'badge badge-neutral';
    }
}

// Function to format user name
function formatUserName($user) {
    if (!$user) return 'Unknown User';
    
    $name = $user['firstname'] ?? '';
    if (!empty($user['middlename'])) {
        $name .= ' ' . substr($user['middlename'], 0, 1) . '.';
    }
    $name .= ' ' . ($user['lastname'] ?? '');
    if (!empty($user['suffix'])) {
        $name .= ' ' . $user['suffix'];
    }
    return $name;
}

// Function to get request type badge based on document_type
function getRequestTypeBadge($type, $document_type) {
    if ($type === 'project') {
        switch($document_type) {
            case 'ws':
                return '<span class="badge badge-success">Project WS</span>';
            case 'pr_po':
                return '<span class="badge badge-info">Project PO</span>';
            case 'po_ws':
                return '<span class="badge badge-warning">Project PO+WS</span>';
            default:
                return '<span class="badge badge-info">Project PR</span>';
        }
    } elseif ($type === 'supplier') {
        return '<span class="badge badge-primary">Stock PR</span>';
    } else {
        return '<span class="badge badge-neutral">' . ucfirst($type) . '</span>';
    }
}

// Function to format date to mm-dd-yyyy
function formatDateMDY($date) {
    if (!$date || $date == '0000-00-00') {
        return '-';
    }
    return date('m-d-Y', strtotime($date));
}

// Function to format datetime to mm-dd-yyyy hh:mm:ss
function formatDateTimeMDY($datetime) {
    if (!$datetime) {
        return '-';
    }
    return date('m-d-Y H:i:s', strtotime($datetime));
}

// Function to format item name with code
function formatItemNameWithCode($item_name, $item_code) {
    if (empty($item_code)) {
        return htmlspecialchars($item_name ?? 'N/A');
    }
    return htmlspecialchars($item_name ?? 'N/A') . ' (' . htmlspecialchars($item_code) . ')';
}

// Function to safely get array value with default
function safeArrayGet($array, $key, $default = '') {
    if (is_array($array) && isset($array[$key])) {
        return $array[$key];
    }
    return $default;
}

// Check for session-based SweetAlert data
$swal_data = array();
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

// Check if threshold is zero (needs to be set)
$threshold_needs_setting = ($request_type === 'project' && $threshold_amount == 0 && $document_type !== 'ws');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>
        <?php 
        if ($request_type === 'project') {
            switch($document_type) {
                case 'ws':
                    echo 'WS Routing - ' . htmlspecialchars(!empty($existing_ws) ? ($existing_ws[0]['ws_number'] ?? 'Not Created') : 'Not Created') . ' - OCP Construction';
                    break;
                case 'pr_po':
                    echo 'PR Routing - ' . htmlspecialchars($pr['pr_number'] ?? '') . ' - OCP Construction';
                    break;
                case 'po_ws':
                    echo 'PR Routing - ' . htmlspecialchars($pr['pr_number'] ?? '') . ' - OCP Construction';
                    break;
                default:
                    echo 'PR Routing - ' . htmlspecialchars($pr['pr_number'] ?? '') . ' - OCP Construction';
            }
        } else {
            echo 'PR Routing - ' . htmlspecialchars($pr['pr_number'] ?? '') . ' - OCP Construction';
        }
        ?>
    </title>
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
                        if ($request_type === 'project') {
                            switch($document_type) {
                                case 'ws':
                                    $ws_number = (!empty($existing_ws) && isset($existing_ws[0]['ws_number'])) ? $existing_ws[0]['ws_number'] : 'Not Created';
                                    echo 'Materials Request (PROJECT)';
                                    break;
                                case 'pr_po':
                                    echo 'Materials Request (PROJECT)';
                                    break;
                                case 'po_ws':
                                    echo 'Materials Request (PROJECT)';
                                    break;
                                default:
                                    echo 'Materials Request (PROJECT)';
                            }
                        } else {
                            echo 'Materials Request (STOCK)';
                        }
                        ?>
                    </h1>
                    <ol class="breadcrumb mb-6">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="purchase_request.php">Purchase Requests</a></li>
                        <li class="breadcrumb-item active">
                            <?php 
                            if ($request_type === 'project') {
                                switch($document_type) {
                                    case 'ws':
                                        echo 'WS Routing (Warehouse)';
                                        break;
                                    case 'pr_po':
                                        echo 'PR Routing (Warehouse)';
                                        break;
                                    case 'po_ws':
                                        echo 'PR Routing (Warehouse)';
                                        break;
                                    default:
                                        echo 'PR Routing (Warehouse)';
                                }
                            } else {
                                echo 'PR Routing (Warehouse)';
                            }
                            ?>
                        </li>
                    </ol>
                    </div>

                    <!-- PR Details Card -->
                    <div class="card mb-6">
                        <div class="card-header">
                            <i class="fas fa-info-circle mr-1"></i>
                            <?php if ($request_type === 'project' && $document_type === 'ws'): ?>
                                Withdrawal Slip Details
                            <?php else: ?>
                                Purchase Request Details
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th>Document Type:</th>
                                            <td>
                                                <?php 
                                                if ($request_type === 'project') {
                                                    switch($document_type) {
                                                        case 'ws':
                                                            echo '<span class="badge badge-success">WS</span>';
                                                            break;
                                                        case 'pr_po':
                                                            echo '<span class="badge badge-danger">PR + PO</span>';
                                                            break;
                                                        case 'po_ws':
                                                            echo '<span class="badge badge-warning">PR + PO + WS</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="badge badge-neutral">' . htmlspecialchars($document_type ?? '') . '</span>';
                                                    }
                                                } else {
                                                    echo '<span class="badge badge-primary">Stock PR</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        
                                        <?php if ($document_type !== 'ws'): ?>
                                        <tr>
                                            <th width="40%">PR Number:</th>
                                            <td><strong><?php echo htmlspecialchars($pr['pr_number'] ?? ''); ?></strong>
                                                <span class="<?php echo getStatusBadge($pr['status'] ?? 'pending'); ?>">
                                                    <?php echo ucfirst($pr['status'] ?? 'pending'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <!-- FIX: Always show PO Number for po_ws document type, even if not created -->
                                        <?php if ($document_type === 'po_ws' || $document_type === 'pr_po'): ?>
                                        <tr>
                                            <th width="40%">PO Number:</th>
                                            <td>
                                                <?php if (!empty($existing_pos)): ?>
                                                    <strong><?php echo htmlspecialchars($existing_pos[0]['po_number'] ?? ''); ?></strong>
                                                    <span class="badge
                                                        <?php 
                                                        switch(strtolower($po_status ?? 'not created')) {
                                                            case 'confirmed':
                                                            case 'delivered':
                                                                echo 'badge-success';
                                                                break;
                                                            case 'draft':
                                                            case 'pending':
                                                                echo 'badge-warning';
                                                                break;
                                                            case 'processing':
                                                                echo 'badge-info';
                                                                break;
                                                            case 'approved':
                                                                echo 'badge-success';
                                                                break;
                                                            case 'partially_received':
                                                                echo 'badge-warning';
                                                                break;
                                                            case 'cancelled':
                                                            case 'rejected':
                                                                echo 'badge-danger';
                                                                break;
                                                            case 'not created':
                                                                echo 'badge-info';
                                                                break;
                                                            default:
                                                                echo 'badge-neutral';
                                                        }
                                                        ?>">
                                                        <?php echo $po_status ?? 'Not Created'; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Not yet created</span>
                                                    <span class="badge badge-info">Not Created</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php elseif (($document_type === 'pr_po') && !empty($existing_pos)): ?>
                                        <tr>
                                            <th width="40%">PO Number:</th>
                                            <td>
                                                <strong><?php echo htmlspecialchars($existing_pos[0]['po_number'] ?? ''); ?></strong>
                                                <span class="badge
                                                    <?php 
                                                    switch(strtolower($po_status ?? 'not created')) {
                                                        case 'confirmed':
                                                        case 'delivered':
                                                            echo 'badge-success';
                                                            break;
                                                        case 'draft':
                                                        case 'pending':
                                                            echo 'badge-warning';
                                                            break;
                                                        case 'processing':
                                                            echo 'badge-info';
                                                            break;
                                                        case 'approved':
                                                            echo 'badge-success';
                                                            break;
                                                        case 'partially_received':
                                                            echo 'badge-warning';
                                                            break;
                                                        case 'cancelled':
                                                        case 'rejected':
                                                            echo 'badge-danger';
                                                            break;
                                                        case 'not created':
                                                            echo 'badge-info';
                                                            break;
                                                        default:
                                                            echo 'badge-neutral';
                                                    }
                                                    ?>">
                                                    <?php echo $po_status ?? 'Not Created'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <!-- FIX: Always show WS Number for po_ws document type, even if not created -->
                                        <?php if ($document_type === 'po_ws' || $document_type === 'ws'): ?>
                                        <tr>
                                            <th width="40%">WS Number:</th>
                                            <td>
                                                <?php if (!empty($existing_ws)): ?>
                                                    <strong><?php echo htmlspecialchars($existing_ws[0]['ws_number'] ?? ''); ?></strong>
                                                    <span class="badge
                                                        <?php 
                                                        switch(strtolower($ws_status ?? 'not created')) {
                                                            case 'confirmed':
                                                            case 'released':
                                                                echo 'badge-success';
                                                                break;
                                                            case 'approved':
                                                                echo 'badge-success';
                                                                break;
                                                            case 'processing':
                                                                echo 'badge-info';
                                                                break;
                                                            case 'pending':
                                                                echo 'badge-warning';
                                                                break;
                                                            case 'draft':
                                                                echo 'badge-neutral';
                                                                break;
                                                            case 'cancelled':
                                                            case 'rejected':
                                                                echo 'badge-danger';
                                                                break;
                                                            case 'not created':
                                                                echo 'badge-info';
                                                                break;
                                                            default:
                                                                echo 'badge-neutral';
                                                        }
                                                        ?>">
                                                        <?php echo $ws_status ?? 'Not Created'; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Not yet created</span>
                                                    <span class="badge badge-info">Not Created</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                <div class="min-w-0">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="40%">Request Date:</th>
                                            <td><?php echo formatDateMDY($pr['request_date'] ?? null); ?></td>
                                        </tr>
                                        
                                        <tr>
                                            <th>Requested By:</th>
                                            <td><?php echo formatUserName($pr); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Department:</th>
                                            <td><?php echo htmlspecialchars($pr['department'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <?php if ($request_type === 'project'): ?>
                                        <tr>
                                            <th>Project:</th>
                                            <td><?php echo htmlspecialchars($pr['project_name'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <?php else: ?>
                                        <tr>
                                            <th>Supplier:</th>
                                            <td><?php echo htmlspecialchars($pr['supplier_name'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <?php if ($request_type === 'project'): ?>
                                        <tr>
                                            <th>Threshold Amount:</th>
                                            <td>
                                                ₱<?php echo number_format($threshold_amount, 2); ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>

                            <!-- Show final status if PR is completed or rejected -->
                            <?php if (in_array($current_stage['stage'] ?? '', ['completed', 'rejected'])): ?>
                            <div class="rounded-lg p-4 text-center mt-6 <?php echo ($current_stage['stage'] ?? '') === 'completed' ? 'border-l-4 border-success-600 bg-success-50' : 'border-l-4 border-danger-600 bg-danger-50'; ?>">
                                <h5 class="text-xl font-semibold mb-2">
                                    <i class="fas <?php echo ($current_stage['stage'] ?? '') === 'completed' ? 'fa-check-circle' : 'fa-times-circle'; ?> mr-2"></i>
                                    <?php 
                                    if (($current_stage['stage'] ?? '') === 'completed'): 
                                        if ($document_type === 'ws'):
                                            echo 'WITHDRAWAL SLIP COMPLETED';
                                        elseif ($document_type === 'po_ws'):
                                            echo 'PR COMPLETED';
                                        else:
                                            echo 'PR COMPLETED';
                                        endif;
                                    else: 
                                        echo 'PR REJECTED';
                                    endif; 
                                    ?>
                                </h5>
                                <p class="mb-0">
                                    <?php if (($current_stage['stage'] ?? '') === 'completed'): ?>
                                        <?php if ($document_type === 'ws'): ?>
                                            This withdrawal slip has been successfully processed and completed.
                                        <?php elseif ($document_type === 'po_ws'): ?>
                                            This purchase request has been successfully processed and completed.
                                        <?php else: ?>
                                            This purchase request has been successfully processed and completed.
                                        <?php endif; ?>
                                    <?php else: ?>
                                        This purchase request has been rejected during the routing process.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Horizontal Routing Progress -->
                            <div class="mt-6">
                                <h6 class="text-base font-semibold mb-4"><i class="fas fa-project-diagram mr-1"></i> Routing Progress</h6>
                                <div class="overflow-x-auto py-2.5 mb-5">
                                    <div class="flex justify-between py-5 relative mb-5 min-w-full before:absolute before:top-10 before:left-0 before:right-0 before:h-[3px] before:bg-slate-200 before:content-['']">
                                        <?php
                                        // Define stages based on document_type
                                        if ($document_type === 'ws') {
                                            // Pure Withdrawal Slip flow
                                            $stages = [
                                                'requestor' => ['label' => 'Requestor', 'icon' => 'fa-user'],
                                                'warehouse' => ['label' => 'Warehouse', 'icon' => 'fa-warehouse'],
                                                'purchasing' => ['label' => 'Purchasing', 'icon' => 'fa-shopping-cart'],
                                                'approver' => ['label' => 'Approver (CEO)', 'icon' => 'fa-user-check'],
                                                'warehouse_releasing' => ['label' => 'Warehouse Releasing', 'icon' => 'fa-truck-loading'],
                                                'completed' => ['label' => 'Completed', 'icon' => 'fa-check-circle']
                                            ];
                                        } elseif ($document_type === 'pr_po') {
                                            // Pure Purchase Order flow
                                            $stages = [
                                                'requestor' => ['label' => 'Requestor', 'icon' => 'fa-user'],
                                                'warehouse' => ['label' => 'Warehouse', 'icon' => 'fa-warehouse'],
                                                'purchasing' => ['label' => 'Purchasing', 'icon' => 'fa-shopping-cart'],
                                                'accounting' => ['label' => 'Accounting', 'icon' => 'fa-calculator'],
                                                'approver' => ['label' => 'Approver (CEO)', 'icon' => 'fa-user-check'],
                                                'purchasing_final' => ['label' => 'Purchasing Final', 'icon' => 'fa-shopping-cart'],
                                                'warehouse_receiving' => ['label' => 'Warehouse Receiving', 'icon' => 'fa-truck-loading'],
                                                'purchasing_completion' => ['label' => 'Purchasing Completion', 'icon' => 'fa-clipboard-check'],
                                                'accounting_final' => ['label' => 'Accounting Final', 'icon' => 'fa-calculator'],
                                                'completed' => ['label' => 'Completed', 'icon' => 'fa-check-circle']
                                            ];
                                        } elseif ($document_type === 'po_ws') {
                                            // Mixed PO+WS flow
                                            $stages = [
                                                'requestor' => ['label' => 'Requestor', 'icon' => 'fa-user'],
                                                'warehouse' => ['label' => 'Warehouse', 'icon' => 'fa-warehouse'],
                                                'purchasing' => ['label' => 'Purchasing', 'icon' => 'fa-shopping-cart'],
                                                'accounting' => ['label' => 'Accounting', 'icon' => 'fa-calculator'],
                                                'approver' => ['label' => 'Approver (CEO)', 'icon' => 'fa-user-check'],
                                                'warehouse_releasing' => ['label' => 'Warehouse Releasing', 'icon' => 'fa-truck-loading'],
                                                'purchasing_final' => ['label' => 'Purchasing Final', 'icon' => 'fa-shopping-cart'],
                                                'warehouse_receiving' => ['label' => 'Warehouse Receiving', 'icon' => 'fa-truck-loading'],
                                                'purchasing_completion' => ['label' => 'Purchasing Completion', 'icon' => 'fa-clipboard-check'],
                                                'accounting_final' => ['label' => 'Accounting Final', 'icon' => 'fa-calculator'],
                                                'completed' => ['label' => 'Completed', 'icon' => 'fa-check-circle']
                                            ];
                                        } else {
                                            // Supplier request
                                            $stages = [
                                                'requestor' => ['label' => 'Requestor', 'icon' => 'fa-user'],
                                                'warehouse' => ['label' => 'Warehouse', 'icon' => 'fa-warehouse'],
                                                'purchasing' => ['label' => 'Purchasing', 'icon' => 'fa-shopping-cart'],
                                                'accounting' => ['label' => 'Accounting', 'icon' => 'fa-calculator'],
                                                'approver' => ['label' => 'Approver (CEO)', 'icon' => 'fa-user-check'],
                                                'purchasing_final' => ['label' => 'Purchasing Final', 'icon' => 'fa-shopping-cart'],
                                                'warehouse_receiving' => ['label' => 'Warehouse Receiving', 'icon' => 'fa-truck-loading'],
                                                'purchasing_completion' => ['label' => 'Purchasing Completion', 'icon' => 'fa-clipboard-check'],
                                                'accounting_final' => ['label' => 'Accounting Final', 'icon' => 'fa-calculator'],
                                                'completed' => ['label' => 'Completed', 'icon' => 'fa-check-circle']
                                            ];
                                        }
                                        
                                        // Get all completed stages from routing history
                                        $completed_stages = [];
                                        foreach ($routing_history as $history) {
                                            if (in_array($history['action'] ?? '', [
                                                'Forwarded to Warehouse', 
                                                'Approved by Warehouse', 
                                                'Approved by Purchasing', 
                                                'Approved by Accounting', 
                                                'Approved by Approver (CEO)', 
                                                'Approved by Purchasing (Final)',
                                                'Purchase Order Created',
                                                'Withdrawal Slip Created',
                                                'Items Received',
                                                'Withdrawal Slip Processed',
                                                'Completed by Purchasing',
                                                'Completed Warehouse Receiving',
                                                'Completed Warehouse Releasing',
                                                'Finalized by Accounting'
                                            ])) {
                                                $completed_stages[] = $history['stage_to'] ?? '';
                                            }
                                            
                                            // Special case: if an action was taken from a stage, that stage is considered visited
                                            if (!empty($history['stage_from']) && !in_array($history['stage_from'], $completed_stages)) {
                                                $completed_stages[] = $history['stage_from'];
                                            }
                                        }
                                        
                                        // Special case for requestor
                                        if (($current_stage['stage'] ?? 'requestor') !== 'requestor') {
                                            $completed_stages[] = 'requestor';
                                        }
                                        
                                        // Display all stages horizontally
                                        foreach ($stages as $stage => $stageInfo):
                                            $isCurrent = ($current_stage['stage'] ?? '') === $stage;
                                            $isCompleted = in_array($stage, $completed_stages);
                                            
                                            $stageClass = 'pending';
                                            if ($isCurrent && !in_array($current_stage['stage'] ?? '', ['completed', 'rejected'])) {
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
                                        <div class="shrink-0 min-w-[140px] max-w-[160px] text-center relative px-1 z-10 mx-0.5">
                                            <div class="h-10 w-10 rounded-full border-[3px] flex items-center justify-center text-lg mx-auto mb-2.5 relative z-10 transition-all <?php echo $stageIconClass; ?>">
                                                <i class="fas <?php echo $stageInfo['icon']; ?>"></i>
                                            </div>
                                            <div class="text-xs font-semibold mb-1 leading-tight h-[30px] flex items-center justify-center break-words px-0.5"><?php echo $stageInfo['label']; ?></div>
                                            <div class="text-[11px] <?php echo $stageStatusClass; ?>">
                                                <?php if ($isCurrent && !in_array($current_stage['stage'] ?? '', ['completed', 'rejected'])): ?>
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
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Purchase Orders - Only show if document_type allows PO -->
                    <?php if (!empty($existing_pos) && $document_type !== 'ws'): ?>
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
                                            <!-- REMOVED: Total Amount column -->
                                            <th>Status</th>
                                            <th>Items</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($existing_pos as $po): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($po['po_number'] ?? ''); ?></strong></td>
                                            <td><?php echo formatDateMDY($po['po_date'] ?? null); ?></td>
                                            <td><?php echo isset($po['expected_delivery']) ? formatDateMDY($po['expected_delivery']) : 'Not set'; ?></td>
                                            <!-- REMOVED: Total Amount data cell -->
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch($po['status'] ?? '') {
                                                        case 'draft': echo 'badge-neutral'; break;
                                                        case 'sent': echo 'badge-info'; break;
                                                        case 'pending': echo 'badge-warning'; break;
                                                        case 'processing': echo 'badge-info'; break;
                                                        case 'approved': echo 'badge-success'; break;
                                                        case 'confirmed': echo 'badge-success'; break;
                                                        case 'delivered': echo 'badge-success'; break;
                                                        case 'partially_received': echo 'badge-warning'; break;
                                                        case 'cancelled': echo 'badge-danger'; break;
                                                        default: echo 'badge-neutral';
                                                    }
                                                    ?>
                                                ">
                                                    <?php echo ucfirst($po['status'] ?? 'draft'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $po['item_count'] ?? 0; ?> item/s</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary view-po-btn" 
                                                        data-po-id="<?php echo $po['id'] ?? 0; ?>"
                                                        data-po-number="<?php echo htmlspecialchars($po['po_number'] ?? ''); ?>"
                                                        data-po-date="<?php echo htmlspecialchars($po['po_date'] ?? ''); ?>"
                                                        data-expected-delivery="<?php echo htmlspecialchars($po['expected_delivery'] ?? 'Not set'); ?>"
                                                        data-po-status="<?php echo ucfirst($po['status'] ?? 'draft'); ?>"
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

                    <!-- Existing Withdrawal Slips - Only show if document_type allows WS -->
                    <?php if (!empty($existing_ws) && ($document_type === 'ws' || $document_type === 'po_ws')): ?>
                    <div class="card mb-6">
                        <div class="card-header">
                            <i class="fas fa-file-invoice mr-1"></i>
                            Existing Withdrawal Slips
                        </div>
                        <div class="card-body">
                            <div class="table-responsive max-h-[400px] overflow-y-auto">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>WS Number</th>
                                            <th>Date Created</th>
                                            <th>Warehouse</th>
                                            <th>Status</th>
                                            <th>Items</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($existing_ws as $ws): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($ws['ws_number'] ?? ''); ?></strong></td>
                                            <td><?php echo formatDateMDY($ws['ws_date'] ?? null); ?></td>
                                            <td>
                                                <?php 
                                                $warehouse_name = 'N/A';
                                                if (!empty($ws['warehouse_id'])) {
                                                    $warehouseStmt = $pdo->prepare("SELECT warehouse_name FROM warehouses WHERE id = ?");
                                                    $warehouseStmt->execute([$ws['warehouse_id']]);
                                                    $warehouse = $warehouseStmt->fetch(PDO::FETCH_ASSOC);
                                                    $warehouse_name = $warehouse['warehouse_name'] ?? 'N/A';
                                                }
                                                echo htmlspecialchars($warehouse_name);
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch($ws['status'] ?? '') {
                                                        case 'draft': echo 'badge-neutral'; break;
                                                        case 'pending': echo 'badge-warning'; break;
                                                        case 'processing': echo 'badge-info'; break;
                                                        case 'approved': echo 'badge-success'; break;
                                                        case 'confirmed': echo 'badge-success'; break;
                                                        case 'released': echo 'badge-success'; break;
                                                        case 'cancelled': echo 'badge-danger'; break;
                                                        default: echo 'badge-neutral';
                                                    }
                                                    ?>
                                                ">
                                                    <?php echo ucfirst($ws['status'] ?? 'draft'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $ws['item_count'] ?? 0; ?> item/s</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary view-ws-btn" 
                                                        data-ws-id="<?php echo $ws['id'] ?? 0; ?>"
                                                        data-ws-number="<?php echo htmlspecialchars($ws['ws_number'] ?? ''); ?>"
                                                        data-ws-date="<?php echo htmlspecialchars($ws['ws_date'] ?? ''); ?>"
                                                        data-warehouse="<?php echo htmlspecialchars($warehouse_name); ?>"
                                                        data-ws-status="<?php echo ucfirst($ws['status'] ?? 'draft'); ?>"
                                                        data-ws-remarks="<?php echo htmlspecialchars($ws['remarks'] ?? ''); ?>">
                                                    <i class="fas fa-eye mr-1"></i> View
                                                </button>
                                                
                                                <?php if (($current_stage['stage'] ?? '') === 'warehouse_releasing' && 
                                                        $user['department'] === 'Warehouse' && 
                                                        $user['accounttype'] === 'Admin' &&
                                                        ($ws['status'] ?? '') === 'approved' &&
                                                        !$ws_already_processed): ?>
                                                <button type="button" class="btn btn-sm btn-success process-ws-btn" 
                                                        data-ws-id="<?php echo $ws['id'] ?? 0; ?>"
                                                        data-ws-number="<?php echo htmlspecialchars($ws['ws_number'] ?? ''); ?>">
                                                    <i class="fas fa-truck-loading mr-1"></i> Release Items
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Warehouse Releasing Items (Withdrawal Slip) - Only show for WS or PO_WS -->
                    <?php if (($current_stage['stage'] ?? '') === 'warehouse_releasing' && !empty($ws_items_for_releasing) && ($document_type === 'ws' || $document_type === 'po_ws')): ?>
                    <div class="card mb-6">
                        <div class="card-header">
                            <i class="fas fa-truck-loading mr-1"></i>
                            Items to Release (Withdrawal Slip)
                        </div>
                        <div class="card-body">
                            <?php if ($ws_already_processed): ?>
                            <div class="rounded-lg border-l-4 border-success-600 bg-success-50 p-4 mb-5">
                                <h6 class="text-base font-semibold"><i class="fas fa-check-circle mr-2"></i>Withdrawal Slip Already Processed</h6>
                                <p class="mb-0">The items for this withdrawal slip have already been released.</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($is_warehouse_user): ?>
                            <div class="table-responsive max-h-[400px] overflow-y-auto">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Warehouse</th>
                                            <th>Quantity to Release</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ws_items_for_releasing as $ws_item): ?>
                                        <tr>
                                            <td><?php echo formatItemNameWithCode($ws_item['item_name'] ?? '', $ws_item['item_code'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($ws_item['warehouse_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $ws_item['quantity'] ?? 0; ?></td>
                                            <td>
                                                <span class="badge <?php echo ($ws_item['status'] ?? '') === 'released' ? 'badge-success' : ($ws_approved ? 'badge-primary' : 'badge-neutral'); ?>">
                                                    <?php 
                                                    if (($ws_item['status'] ?? '') === 'released') {
                                                        echo 'Released';
                                                    } elseif ($ws_approved) {
                                                        echo 'Approved';
                                                    } else {
                                                        echo 'Pending Approval';
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-4">
                                <?php if (!$ws_already_processed): ?>
                                    <?php if ($ws_approved): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        <strong>Action Required:</strong> Withdrawal slip has been approved. Click the "Release Items" button in the Withdrawal Slips section above to release items from inventory.
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Withdrawal slip is pending approval from Approver (CEO). You cannot release items until the withdrawal slip has been approved.
                                    </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    Items have been released. Click "Complete Warehouse Releasing" in the Routing Actions modal to finish this stage.
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive max-h-[400px] overflow-y-auto">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Warehouse</th>
                                            <th>Quantity to Release</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ws_items_for_releasing as $ws_item): ?>
                                        <tr>
                                            <td><?php echo formatItemNameWithCode($ws_item['item_name'] ?? '', $ws_item['item_code'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($ws_item['warehouse_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $ws_item['quantity'] ?? 0; ?></td>
                                            <td>
                                                <span class="badge <?php echo ($ws_item['status'] ?? '') === 'released' ? 'badge-success' : ($ws_approved ? 'badge-primary' : 'badge-neutral'); ?>">
                                                    <?php 
                                                    if (($ws_item['status'] ?? '') === 'released') {
                                                        echo 'Released';
                                                    } elseif ($ws_approved) {
                                                        echo 'Approved';
                                                    } else {
                                                        echo 'Pending Approval';
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info mt-4">
                                <i class="fas fa-info-circle mr-2"></i>
                                Only Warehouse Department users can release items. You are viewing this information in read-only mode.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Warehouse Receiving Items - Only show if document_type allows PO -->
                    <?php if (($current_stage['stage'] ?? '') === 'warehouse_receiving' && !empty($po_items_for_receiving) && $document_type !== 'ws'): ?>
                    <div class="card mb-6">
                        <div class="card-header">
                            <i class="fas fa-truck-loading mr-1"></i>
                            Items to Receive (Purchase Order)
                        </div>
                        <div class="card-body">
                            <?php if ($items_already_received): ?>
                            <div class="rounded-lg border-l-4 border-success-600 bg-success-50 p-4 mb-5">
                                <h6 class="text-base font-semibold"><i class="fas fa-check-circle mr-2"></i>Items Already Received</h6>
                                <p class="mb-0">The items for this purchase order have already been received.</p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($is_warehouse_user): ?>
                            <form method="POST" action="" id="receivingForm">
                                <input type="hidden" name="action" value="receive_items">
                                <div class="table-responsive max-h-[400px] overflow-y-auto">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Item Name</th>
                                                <th>Warehouse</th>
                                                <th>Quantity Ordered</th>
                                                <th>Quantity Received</th>
                                                <th>Remaining</th>
                                                <th>Unit Cost</th>
                                                <th>Total Cost</th>
                                                <th>Status</th>
                                                <th>Quantity to Receive</th>
                                                <th class="hidden">Batch Number</th>
                                                <th>Received Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($po_items_for_receiving as $po_item): 
                                                $received_quantity = $po_item['received_quantity'] ?? 0;
                                                $remaining_quantity = ($po_item['quantity'] ?? 0) - $received_quantity;
                                                $status = $po_item['status'] ?? 'pending';
                                                
                                                $status_class = 'text-slate-500';
                                                $status_text = 'Pending';
                                                if ($status === 'partially_received') {
                                                    $status_class = 'text-warning-600';
                                                    $status_text = 'Partially Received';
                                                } elseif ($status === 'delivered') {
                                                    $status_class = 'text-success-600';
                                                    $status_text = 'Delivered';
                                                }
                                                
                                                // Calculate total cost based on ordered quantity for display purposes
                                                $total_cost_display = ($po_item['quantity'] ?? 0) * ($po_item['unit_cost'] ?? 0);
                                            ?>
                                            <tr>
                                                <td><?php echo formatItemNameWithCode($po_item['item_name'] ?? '', $po_item['item_code'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($po_item['warehouse_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo $po_item['quantity'] ?? 0; ?></td>
                                                <td><?php echo $received_quantity; ?></td>
                                                <td><?php echo $remaining_quantity; ?></td>
                                                <td>₱<?php echo number_format($po_item['unit_cost'] ?? 0, 2); ?></td>
                                                <td>₱<?php echo number_format($total_cost_display, 2); ?></td> <!-- FIXED: Now displays correct amount -->
                                                <td>
                                                    <span class="font-bold <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($remaining_quantity > 0): ?>
                                                        <input type="number" 
                                                            name="received_items[<?php echo $po_item['id'] ?? 0; ?>][quantity]" 
                                                            value="<?php echo $remaining_quantity; ?>"
                                                            min="0" max="<?php echo $remaining_quantity; ?>"
                                                            class="form-control form-control-sm" 
                                                            <?php echo $items_already_received ? 'readonly' : 'required'; ?>>
                                                    <?php else: ?>
                                                        <span class="text-success">Fully Received</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="hidden">
                                                    <?php if ($remaining_quantity > 0): ?>
                                                        <input type="text" 
                                                            name="received_items[<?php echo $po_item['id'] ?? 0; ?>][batch_number]" 
                                                            value="BATCH-<?php echo date('Ymd-His'); ?>-<?php echo $po_item['id'] ?? 0; ?>"
                                                            class="form-control form-control-sm" 
                                                            <?php echo $items_already_received ? 'readonly' : 'required'; ?>>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($remaining_quantity > 0): ?>
                                                        <input type="date" 
                                                            name="received_items[<?php echo $po_item['id'] ?? 0; ?>][received_date]" 
                                                            value="<?php echo date('Y-m-d'); ?>"
                                                            class="form-control form-control-sm" 
                                                            <?php echo $items_already_received ? 'readonly' : 'required'; ?>>
                                                        <input type="hidden" 
                                                            name="received_items[<?php echo $po_item['id'] ?? 0; ?>][unit_cost]" 
                                                            value="<?php echo $po_item['unit_cost'] ?? 0; ?>">
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
                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" <?php echo $items_already_received ? 'readonly' : 'required'; ?>></textarea>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-success" <?php echo $items_already_received ? 'disabled' : ''; ?>>
                                        <i class="fas fa-check mr-1"></i> Confirm Items Received
                                    </button>
                                </div>
                            </form>
                            <?php else: ?>
                            <!-- Read-only view for non-warehouse users -->
                            <div class="table-responsive max-h-[400px] overflow-y-auto">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Warehouse</th>
                                            <th>Quantity Ordered</th>
                                            <th>Quantity Received</th>
                                            <th>Remaining</th>
                                            <th>Unit Cost</th>
                                            <th>Total Cost</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($po_items_for_receiving as $po_item): 
                                            $received_quantity = $po_item['received_quantity'] ?? 0;
                                            $remaining_quantity = ($po_item['quantity'] ?? 0) - $received_quantity;
                                            $status = $po_item['status'] ?? 'pending';
                                            
                                            $status_class = 'text-slate-500';
                                            $status_text = 'Pending';
                                            if ($status === 'partially_received') {
                                                $status_class = 'text-warning-600';
                                                $status_text = 'Partially Received';
                                            } elseif ($status === 'delivered') {
                                                $status_class = 'text-success-600';
                                                $status_text = 'Delivered';
                                            }
                                            
                                            // Calculate total cost based on ordered quantity for display purposes
                                            $total_cost_display = ($po_item['quantity'] ?? 0) * ($po_item['unit_cost'] ?? 0);
                                        ?>
                                        <tr>
                                            <td><?php echo formatItemNameWithCode($po_item['item_name'] ?? '', $po_item['item_code'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($po_item['warehouse_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $po_item['quantity'] ?? 0; ?></td>
                                            <td><?php echo $received_quantity; ?></td>
                                            <td><?php echo $remaining_quantity; ?></td>
                                            <td>₱<?php echo number_format($po_item['unit_cost'] ?? 0, 2); ?></td>
                                            <td>₱<?php echo number_format($total_cost_display, 2); ?></td> <!-- FIXED: Now displays correct amount -->
                                            <td>
                                                <span class="font-bold <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info mt-4">
                                <i class="fas fa-info-circle mr-2"></i>
                                Only Warehouse Department users can receive items. You are viewing this information in read-only mode.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 gap-6">
                        <!-- Items Requested -->
                        <div class="min-w-0">
                            <div class="card mb-6">
                                <div class="card-header flex justify-between items-center">
                                    <div>
                                        <i class="fas fa-list mr-1"></i>
                                        <?php if ($document_type === 'ws'): ?>
                                            Withdrawal Slip Items
                                        <?php else: ?>
                                            Items Requested
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if (($current_stage['stage'] ?? '') === 'approver' && 
                                                $user['department'] === 'Admin' && 
                                                $user['position'] === 'CEO' && 
                                                $user['accounttype'] === 'Admin' && 
                                                $request_type === 'project' &&
                                                $document_type !== 'ws'): ?>
                                        <button type="button" class="btn btn-warning mr-2" data-bs-toggle="modal" data-bs-target="#adjustThresholdModal">
                                            <i class="fas fa-balance-scale mr-1"></i> Adjust Threshold
                                        </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#routingActionsModal">
                                            <i class="fas fa-route mr-1"></i> Routing Actions
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive max-h-[400px] overflow-y-auto">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Item Name</th>
                                                    <?php if ($document_type === 'ws'): ?>
                                                        <!-- WS Document Type - Simplified Headers -->
                                                        <th>Quantity Requested</th>
                                                        <th>Warehouse</th>
                                                        <th>Current Stock</th>
                                                        <th>Released</th>
                                                        <th>Remaining Needed</th>
                                                        <th>Unit Cost</th>
                                                        <th>Total Cost</th>
                                                        <th>Status</th>
                                                        <th>Release Status</th>
                                                    <?php elseif ($request_type === 'project' && $document_type === 'pr_po'): ?>
                                                        <!-- PR_PO Document Type (Pure Purchase Order) - FIXED -->
                                                        <th>Quantity Requested</th>
                                                        <th>Quantity Received</th>
                                                        <th>Current Stock</th>
                                                        <th>Unit Cost</th>
                                                        <th>Total Cost</th>
                                                        <th>Status</th>
                                                        <th>Delivery Status</th>
                                                        <th>Received Date</th>
                                                    <?php elseif ($request_type === 'project' && $document_type === 'po_ws'): ?>
                                                        <!-- PO_WS Document Type (Mixed) -->
                                                        <th>Quantity Requested</th>
                                                        <th>Current Stock</th>
                                                        <th>Released</th>
                                                        <th>Received</th>
                                                        <th>Remaining Needed/Ordered</th>
                                                        <th>Unit Cost</th>
                                                        <th>Total Cost</th>
                                                        <th>Status</th>
                                                        <th>Release Status</th>
                                                        <th>Delivery Status</th>
                                                    <?php else: ?>
                                                        <!-- Stock PR -->
                                                        <th>Quantity Ordered</th>
                                                        <th>Quantity Received</th>
                                                        <th>Unit Cost</th>
                                                        <th>Total Cost</th>
                                                        <th>Delivery Status</th>
                                                        <th>Received Date</th>
                                                    <?php endif; ?>
                                                    <th class="hidden">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                foreach ($items as $item): 
                                                    if ($request_type === 'supplier') {
                                                        $delivered_qty = $item['supplier_received_quantity'] ?? 0;
                                                    } else {
                                                        $delivered_qty = $item['db_delivered_quantity'] ?? 0;
                                                    }
                                                    
                                                    // Calculate remaining quantity needed
                                                    $remaining_needed = $item['quantity'] - $delivered_qty;
                                                    
                                                    $current_stock = $item['current_stock'] ?? 0;
                                                    $unit_cost = $item['unit_cost'] ?? 0;
                                                    
                                                    // FIX: Calculate total cost based on remaining needed quantity
                                                    // If fully delivered (remaining_needed = 0), total cost should be 0.00
                                                    $total_cost = ($remaining_needed > 0) ? ($remaining_needed * $unit_cost) : 0;
                                                    
                                                    // Determine status flags
                                                    $is_delivered = $delivered_qty >= $item['quantity'];
                                                    $is_partially_delivered = $delivered_qty > 0 && $delivered_qty < $item['quantity'];
                                                    
                                                    // Get received date for project PR_PO items
                                                    $po_received_date = '-';
                                                    if ($request_type === 'project' && $document_type === 'pr_po' && $delivered_qty > 0) {
                                                        $poReceivedDateStmt = $pdo->prepare("
                                                            SELECT MAX(received_date) as last_received 
                                                            FROM po_items 
                                                            WHERE pr_item_id = ? AND received_quantity > 0
                                                        ");
                                                        $poReceivedDateStmt->execute([$item['id']]);
                                                        $po_received_data = $poReceivedDateStmt->fetch(PDO::FETCH_ASSOC);
                                                        if ($po_received_data && $po_received_data['last_received']) {
                                                            $po_received_date = formatDateMDY($po_received_data['last_received']);
                                                        }
                                                    }
                                                    
                                                    // Status class for stock
                                                    if ($request_type === 'project') {
                                                        if ($current_stock >= $remaining_needed && $remaining_needed > 0) {
                                                            $status_class = 'text-success-600';
                                                            $status_text = 'Available';
                                                        } elseif ($current_stock > 0 && $remaining_needed > 0) {
                                                            $status_class = 'text-warning-600';
                                                            $status_text = 'Low Stock';
                                                        } elseif ($remaining_needed > 0) {
                                                            $status_class = 'text-danger-600';
                                                            $status_text = 'Out of Stock';
                                                        } else {
                                                            $status_class = 'text-success-600';
                                                            $status_text = 'Fully Released';
                                                        }
                                                    } else {
                                                        if ($remaining_needed > 0) {
                                                            $status_class = 'text-success-600';
                                                            $status_text = 'Available';
                                                        } else {
                                                            $status_class = 'text-success-600';
                                                            $status_text = 'Fully Received';
                                                        }
                                                    }
                                                    
                                                    // Delivery status
                                                    if ($request_type === 'project') {
                                                        if ($is_delivered) {
                                                            $delivery_status_class = 'text-success-600';
                                                            $delivery_status_text = 'Released';
                                                            $delivery_status_icon = 'fa-check-circle';
                                                        } elseif ($is_partially_delivered) {
                                                            $delivery_status_class = 'text-warning-600';
                                                            $delivery_status_text = 'Partially Released';
                                                            $delivery_status_icon = 'fa-clock';
                                                        } else {
                                                            $delivery_status_class = 'text-warning';
                                                            $delivery_status_text = 'Pending';
                                                            $delivery_status_icon = 'fa-clock';
                                                        }
                                                    } else {
                                                        // Stock PR delivery status
                                                        if (($pr['status'] ?? '') === 'rejected') {
                                                            $delivery_status_class = 'text-danger';
                                                            $delivery_status_text = 'Rejected';
                                                            $delivery_status_icon = 'fa-times-circle';
                                                        } elseif ($is_delivered) {
                                                            $delivery_status_class = 'text-success-600';
                                                            $delivery_status_text = 'Fully Received';
                                                            $delivery_status_icon = 'fa-check-circle';
                                                        } elseif ($is_partially_delivered) {
                                                            $delivery_status_class = 'text-warning-600';
                                                            $delivery_status_text = 'Partially Received';
                                                            $delivery_status_icon = 'fa-clock';
                                                        } else {
                                                            $delivery_status_class = 'text-warning';
                                                            $delivery_status_text = 'Pending';
                                                            $delivery_status_icon = 'fa-clock';
                                                        }
                                                    }

                                                    // Get received date from stock movements (for Stock PR)
                                                    $received_date = '-';
                                                    if ($request_type !== 'project' && $delivered_qty > 0) {
                                                        $receivedDateStmt = $pdo->prepare("
                                                            SELECT MAX(movement_date) as last_received 
                                                            FROM stock_movements 
                                                            WHERE item_id = ? AND purchase_request = ? AND movement_type = 'in'
                                                        ");
                                                        $receivedDateStmt->execute([$item['item_id'], $pr['pr_number']]);
                                                        $received_data = $receivedDateStmt->fetch(PDO::FETCH_ASSOC);
                                                        if ($received_data && $received_data['last_received']) {
                                                            $received_date = formatDateMDY($received_data['last_received']);
                                                        }
                                                    }
                                                ?>
                                                <tr>
                                                    <td><?php echo formatItemNameWithCode($item['item_name'] ?? '', $item['item_code'] ?? ''); ?></td>
                                                    
                                                    <?php if ($document_type === 'ws'): ?>
                                                        <!-- WS Document Type -->
                                                        <?php
                                                        // Calculate FIFO-based cost for display purposes.
                                                        //
                                                        // The quantity is what the page's endpoint worked out
                                                        // can actually be handed over, not the whole remainder:
                                                        // taking the remainder drew a slip for 12 against 3 on
                                                        // hand, and processing it could only fail. The shortfall
                                                        // is reported under the input.
                                                        $quantity_to_withdraw = $item['quantity_to_withdraw'] ?? min($remaining_needed, $current_stock);
                                                        $fifo_unit_cost = 0;
                                                        $fifo_total_cost = 0;
                                                        
                                                        if ($current_stock > 0 && $quantity_to_withdraw > 0) {
                                                            $fifoBatchesStmt = $pdo->prepare("
                                                                SELECT id, quantity, unit_cost, batch_number, received_date 
                                                                FROM inventory_batches 
                                                                WHERE item_id = :item_id AND warehouse_id = :warehouse_id AND quantity > 0 
                                                                ORDER BY received_date ASC, id ASC
                                                            ");
                                                            $fifoBatchesStmt->bindParam(':item_id', $item['item_id']);
                                                            $fifoBatchesStmt->bindParam(':warehouse_id', $item['warehouse_id']);
                                                            $fifoBatchesStmt->execute();
                                                            $fifo_batches = $fifoBatchesStmt->fetchAll(PDO::FETCH_ASSOC);
                                                            
                                                            $remaining_to_withdraw = $quantity_to_withdraw;
                                                            $total_cost_fifo = 0;
                                                            
                                                            foreach ($fifo_batches as $batch) {
                                                                if ($remaining_to_withdraw <= 0) break;
                                                                
                                                                $batch_qty = floatval($batch['quantity']);
                                                                $batch_cost = floatval($batch['unit_cost']);
                                                                
                                                                $take_qty = min($remaining_to_withdraw, $batch_qty);
                                                                $total_cost_fifo += $take_qty * $batch_cost;
                                                                
                                                                $remaining_to_withdraw -= $take_qty;
                                                            }
                                                            
                                                            if ($quantity_to_withdraw > 0) {
                                                                $fifo_unit_cost = $total_cost_fifo / $quantity_to_withdraw;
                                                                $fifo_total_cost = $total_cost_fifo;
                                                            }
                                                        }
                                                        
                                                        // If fully delivered, show 0.00
                                                        if ($quantity_to_withdraw <= 0) {
                                                            $fifo_unit_cost = 0;
                                                            $fifo_total_cost = 0;
                                                        } else if ($fifo_unit_cost == 0) {
                                                            $fifo_unit_cost = $unit_cost;
                                                            $fifo_total_cost = $unit_cost * $quantity_to_withdraw;
                                                        }
                                                        ?>
                                                        <td><?php echo htmlspecialchars($item['quantity'] ?? 0); ?></td>
                                                        <td><?php echo htmlspecialchars($item['warehouse_name'] ?? 'No Warehouse'); ?></td>
                                                        <td><?php echo htmlspecialchars($current_stock); ?></td>
                                                        <td><?php echo htmlspecialchars($delivered_qty); ?></td>
                                                        <td><?php echo htmlspecialchars($remaining_needed); ?></td>
                                                        <td>₱<?php echo number_format($fifo_unit_cost, 2); ?></td>
                                                        <td>₱<?php echo number_format($fifo_total_cost, 2); ?></td>
                                                        <td>
                                                            <span class="font-bold <?php echo $status_class; ?>">
                                                                <?php echo $status_text; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="<?php echo $delivery_status_class; ?>">
                                                                <i class="fas <?php echo $delivery_status_icon; ?> mr-1"></i>
                                                                <?php echo $delivery_status_text; ?>
                                                            </span>
                                                        </td>
                                                        
                                                    <?php elseif ($request_type === 'project' && $document_type === 'pr_po'): ?>
                                                        <!-- PR_PO Document Type - FIXED: Use remaining needed for total cost -->
                                                        <td><?php echo htmlspecialchars($item['quantity'] ?? 0); ?></td>
                                                        <td><?php echo htmlspecialchars($delivered_qty); ?></td>
                                                        <td><?php echo htmlspecialchars($current_stock); ?></td>
                                                        <td>
                                                            <?php 
                                                            // If fully delivered, show 0.00
                                                            if ($remaining_needed <= 0) {
                                                                echo '₱0.00';
                                                            } else {
                                                                echo '₱' . number_format($unit_cost, 2);
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>₱<?php echo number_format($total_cost, 2); ?></td> <!-- FIXED: Now 0.00 when fully delivered -->
                                                        <td>
                                                            <?php 
                                                            if ($current_stock >= $remaining_needed && $remaining_needed > 0) {
                                                                echo '<span class="font-bold text-success-600">Available</span>';
                                                            } elseif ($current_stock > 0 && $remaining_needed > 0) {
                                                                echo '<span class="font-bold text-warning-600">Low Stock</span>';
                                                            } elseif ($remaining_needed > 0) {
                                                                echo '<span class="font-bold text-danger-600">Out of Stock</span>';
                                                            } else {
                                                                echo '<span class="font-bold text-success-600">Fully Received</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            if ($is_delivered) {
                                                                echo '<span class="text-success-600"><i class="fas fa-check-circle mr-1"></i>Fully Received</span>';
                                                            } elseif ($is_partially_delivered) {
                                                                echo '<span class="text-warning-600"><i class="fas fa-clock mr-1"></i>Partially Received (' . $delivered_qty . ' of ' . $item['quantity'] . ')</span>';
                                                            } elseif (($pr['status'] ?? '') === 'rejected') {
                                                                echo '<span class="text-danger"><i class="fas fa-times-circle mr-1"></i>Rejected</span>';
                                                            } else {
                                                                echo '<span class="text-warning"><i class="fas fa-clock mr-1"></i>Pending</span>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?php echo $po_received_date; ?></td>
                                                        
                                                    <?php elseif ($request_type === 'project' && $document_type === 'po_ws'): ?>
                                                        <!-- PO_WS Document Type (Mixed) - FIXED: Combined batches + PO cost for Partial Stock -->
                                                        <?php
                                                        $supplier_received = $item['supplier_received_quantity'] ?? 0;
                                                        $delivered_from_warehouse = $item['db_delivered_quantity'] ?? 0;
                                                        $total_fulfilled = $supplier_received + $delivered_from_warehouse;
                                                        $remaining_needed_po_ws = $item['quantity'] - $total_fulfilled;
                                                        
                                                        // Calculate the actual cost based on batches that will be used in FIFO order + PO cost
                                                        $actual_unit_cost = 0;
                                                        $actual_total_cost = 0;
                                                        
                                                        if ($remaining_needed_po_ws > 0) {
                                                            $total_cost_combined = 0;
                                                            $remaining_to_calculate = $remaining_needed_po_ws;
                                                            
                                                            // PART 1: Get cost from inventory batches (FIFO)
                                                            if ($current_stock > 0) {
                                                                $fifoBatchesStmt = $pdo->prepare("
                                                                    SELECT id, quantity, unit_cost, batch_number, received_date 
                                                                    FROM inventory_batches 
                                                                    WHERE item_id = :item_id AND warehouse_id = :warehouse_id AND quantity > 0 
                                                                    ORDER BY received_date ASC, id ASC
                                                                ");
                                                                $fifoBatchesStmt->bindParam(':item_id', $item['item_id']);
                                                                $fifoBatchesStmt->bindParam(':warehouse_id', $item['warehouse_id']);
                                                                $fifoBatchesStmt->execute();
                                                                $fifo_batches = $fifoBatchesStmt->fetchAll(PDO::FETCH_ASSOC);
                                                                
                                                                $batches_used = [];
                                                                
                                                                // Take from batches first (FIFO order)
                                                                foreach ($fifo_batches as $batch) {
                                                                    if ($remaining_to_calculate <= 0) break;
                                                                    
                                                                    $batch_qty = floatval($batch['quantity']);
                                                                    $batch_cost = floatval($batch['unit_cost']);
                                                                    
                                                                    $take_qty = min($remaining_to_calculate, $batch_qty);
                                                                    $total_cost_combined += $take_qty * $batch_cost;
                                                                    
                                                                    $remaining_to_calculate -= $take_qty;
                                                                }
                                                            }
                                                            
                                                            // PART 2: Remaining quantity will come from Purchase Order
                                                            if ($remaining_to_calculate > 0) {
                                                                // Use the unit cost from PR item for PO portion
                                                                $total_cost_combined += $remaining_to_calculate * $unit_cost;
                                                            }
                                                            
                                                            if ($remaining_needed_po_ws > 0) {
                                                                $actual_unit_cost = $total_cost_combined / $remaining_needed_po_ws;
                                                                $actual_total_cost = $total_cost_combined;
                                                            }
                                                        }
                                                        
                                                        // Determine release status
                                                        if ($delivered_from_warehouse >= $item['quantity']) {
                                                            $release_status_class = 'text-success-600';
                                                            $release_status_icon = 'fa-check-circle';
                                                            $release_status_text = 'Released';
                                                        } elseif ($delivered_from_warehouse > 0) {
                                                            $release_status_class = 'text-warning-600';
                                                            $release_status_icon = 'fa-clock';
                                                            $release_status_text = 'Partial Release';
                                                        } else {
                                                            $release_status_class = 'text-warning';
                                                            $release_status_icon = 'fa-clock';
                                                            $release_status_text = 'Pending';
                                                        }
                                                        
                                                        // Determine supplier delivery status
                                                        $quantity_to_purchase = $item['quantity'] - $delivered_from_warehouse; // This is what needs to be purchased
                                                        
                                                        if ($quantity_to_purchase <= 0) {
                                                            $supplier_status_class = 'text-muted';
                                                            $supplier_status_icon = 'fa-minus-circle';
                                                            $supplier_status_text = 'Not Required';
                                                        } elseif ($supplier_received >= $quantity_to_purchase) {
                                                            $supplier_status_class = 'text-success-600';
                                                            $supplier_status_icon = 'fa-check-circle';
                                                            $supplier_status_text = 'Received';
                                                        } elseif ($supplier_received > 0) {
                                                            $supplier_status_class = 'text-warning-600';
                                                            $supplier_status_icon = 'fa-clock';
                                                            $supplier_status_text = 'Partial';
                                                        } else {
                                                            $supplier_status_class = 'text-warning';
                                                            $supplier_status_icon = 'fa-clock';
                                                            $supplier_status_text = 'Pending';
                                                        }
                                                        
                                                        // Determine stock status text with proper class
                                                        if ($remaining_needed_po_ws <= 0) {
                                                            $status_class = 'text-success-600';
                                                            $status_text = 'Fulfilled';
                                                        } elseif ($current_stock >= $remaining_needed_po_ws && $remaining_needed_po_ws > 0) {
                                                            $status_class = 'text-success-600';
                                                            $status_text = 'In Stock';
                                                        } elseif ($current_stock > 0 && $remaining_needed_po_ws > 0) {
                                                            $status_class = 'text-warning-600';
                                                            $status_text = 'Partial Stock'; // Combined from batches + PO
                                                        } else {
                                                            $status_class = 'text-danger-600';
                                                            $status_text = 'Need Purchase';
                                                        }
                                                        ?>
                                                        <td><?php echo htmlspecialchars($item['quantity'] ?? 0); ?></td>
                                                        <td><?php echo htmlspecialchars($current_stock); ?></td>
                                                        <td><?php echo htmlspecialchars($delivered_from_warehouse); ?></td>
                                                        <td><?php echo htmlspecialchars($supplier_received); ?></td>
                                                        <td><?php echo $remaining_needed_po_ws; ?></td>
                                                        <td>₱<?php echo number_format($actual_unit_cost, 2); ?></td> <!-- FIXED: Combined batches + PO cost -->
                                                        <td>₱<?php echo number_format($actual_total_cost, 2); ?></td> <!-- FIXED: Combined batches + PO total -->
                                                        <td>
                                                            <span class="font-bold <?php echo $status_class; ?>">
                                                                <?php echo $status_text; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="<?php echo $release_status_class; ?>">
                                                                <i class="fas <?php echo $release_status_icon; ?> mr-1"></i>
                                                                <?php echo $release_status_text; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="<?php echo $supplier_status_class; ?>">
                                                                <i class="fas <?php echo $supplier_status_icon; ?> mr-1"></i>
                                                                <?php echo $supplier_status_text; ?>
                                                                <?php if ($supplier_status_text === 'Partial'): ?>
                                                                    (<?php echo $supplier_received; ?> of <?php echo $quantity_to_purchase; ?>)
                                                                <?php endif; ?>
                                                            </span>
                                                        </td>
                                                        
                                                    <?php else: ?>
                                                        <!-- Stock PR - FIXED: Use remaining needed for total cost -->
                                                        <td><?php echo htmlspecialchars($item['quantity'] ?? 0); ?></td>
                                                        <td><?php echo htmlspecialchars($delivered_qty); ?></td>
                                                        <td>
                                                            <?php 
                                                            // If fully delivered, show 0.00
                                                            if ($remaining_needed <= 0) {
                                                                echo '₱0.00';
                                                            } else {
                                                                echo '₱' . number_format($unit_cost, 2);
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>₱<?php echo number_format($total_cost, 2); ?></td> <!-- FIXED: Now 0.00 when fully delivered -->
                                                        <td>
                                                            <span class="<?php echo $delivery_status_class; ?>">
                                                                <i class="fas <?php echo $delivery_status_icon; ?> mr-1"></i>
                                                                <?php echo $delivery_status_text; ?>
                                                                <?php if ($is_partially_delivered): ?>
                                                                    (<?php echo $delivered_qty; ?> of <?php echo $item['quantity']; ?>)
                                                                <?php endif; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $received_date; ?></td>
                                                    <?php endif; ?>
                                                    
                                                    <td class="hidden">
                                                        <!-- Keep existing actions code -->
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Routing Actions Modal -->
                    <div class="modal fade" id="routingActionsModal" tabindex="-1" aria-labelledby="routingActionsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-l">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="routingActionsModalLabel">
                                        <i class="fas fa-route mr-1"></i>
                                        Routing Actions
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <?php if (($current_stage['stage'] ?? '') === 'requestor' && ($pr['requested_by'] ?? 0) == $user_id): ?>
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="forward_to_warehouse">
                                            <div class="mb-4">
                                                <label for="remarks" class="form-label">Remarks (Optional)</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                                            </div>
                                            <div class="mt-4">
                                                <button type="submit" class="btn btn-primary w-full">
                                                    <i class="fas fa-forward mr-1"></i> Forward to Warehouse Department
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'warehouse' && 
                                               $user['department'] === 'Warehouse' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <form method="POST" action="" id="warehouseActionsForm">
                                            <div class="mb-4">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3 mt-4">
                                                <button type="submit" name="action" value="approve_warehouse" class="btn btn-success">
                                                    <i class="fas fa-check mr-1"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject_warehouse" class="btn btn-danger">
                                                    <i class="fas fa-times mr-1"></i> Reject
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'purchasing' && 
                                               $user['department'] === 'Admin' && 
                                               $user['position'] === 'Purchaser' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <form method="POST" action="" id="purchasingActionsForm">
                                            <div class="mb-4">
                                                <?php 
                                                $can_approve = true;
                                                $approve_disabled_reason = '';
                                                
                                                if ($request_type === 'project') {
                                                    if ($document_type === 'ws' && !$ws_exists) {
                                                        $can_approve = false;
                                                        $approve_disabled_reason = 'Withdrawal Slip is required for WS document type.';
                                                    } elseif ($document_type === 'pr_po' && !$po_exists) {
                                                        $can_approve = false;
                                                        $approve_disabled_reason = 'Purchase Order is required for PR_PO document type.';
                                                    } elseif ($document_type === 'po_ws' && (!$po_exists || !$ws_exists)) {
                                                        $can_approve = false;
                                                        $approve_disabled_reason = 'Both Purchase Order and Withdrawal Slip are required.';
                                                    }
                                                } else {
                                                    if (!$po_exists) {
                                                        $can_approve = false;
                                                        $approve_disabled_reason = 'Purchase Order is required for this PR.';
                                                    }
                                                }
                                                ?>
                                                <?php if (!$can_approve): ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    <?php echo $approve_disabled_reason; ?>
                                                </div>
                                                <?php endif; ?>
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3 mt-4">
                                                <button type="submit" name="action" value="approve_purchasing" class="btn btn-success" <?php echo !$can_approve ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-check mr-1"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject_purchasing" class="btn btn-danger">
                                                    <i class="fas fa-times mr-1"></i> Reject
                                                </button>
                                            </div>
                                        </form>
                                        
                                        <hr class="my-4">
                                        
                                        <?php if ($request_type === 'project'): ?>
                                            <?php if ($document_type === 'ws' && !$ws_exists): ?>
                                                <div class="rounded-lg border-l-4 border-success-600 bg-success-50 p-4 mb-4">
                                                    <h6 class="text-base font-semibold"><i class="fas fa-info-circle mr-2"></i>Pure Withdrawal Slip Flow (WS)</h6>
                                                    <p class="mb-0">Create a Withdrawal Slip for this PR.</p>
                                                </div>
                                                <div class="mt-4">
                                                    <button type="button" class="btn btn-success w-full" data-bs-toggle="modal" data-bs-target="#createWSModal">
                                                        <i class="fas fa-file-invoice mr-1"></i> Create Withdrawal Slip
                                                    </button>
                                                </div>
                                            <?php elseif ($document_type === 'pr_po' && !$po_exists): ?>
                                                <div class="mb-4">
                                                    <label for="po_remarks" class="form-label">PO Remarks (Optional)</label>
                                                    <textarea class="form-control" id="po_remarks" name="po_remarks" rows="2"></textarea>
                                                </div>
                                                <div class="mt-4">
                                                    <button type="button" class="btn btn-primary w-full" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                                        <i class="fas fa-file-invoice-dollar mr-1"></i> Create Purchase Order
                                                    </button>
                                                </div>
                                            <?php elseif ($document_type === 'po_ws'): ?>
                                                <div class="alert alert-warning mb-4">
                                                    <h6 class="text-base font-semibold"><i class="fas fa-info-circle mr-2"></i>PO & WS</h6>
                                                    <p class="mb-0">This PR has items with stock and items that need purchasing. Create BOTH documents.</p>
                                                </div>
                                                
                                                <div class="grid grid-cols-2 gap-4 mt-4">
                                                    <!-- Withdrawal Slip Button -->
                                                    <?php if (!$ws_exists): ?>
                                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createWSModal">
                                                        <i class="fas fa-file-invoice mr-1"></i> Create Withdrawal Slip
                                                    </button>
                                                    <?php else: ?>
                                                    <button type="button" class="btn btn-success" disabled>
                                                        <i class="fas fa-check mr-1"></i> Withdrawal Slip Created
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Purchase Order Button -->
                                                    <?php if (!$po_exists): ?>
                                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                                        <i class="fas fa-file-invoice-dollar mr-1"></i> Create Purchase Order
                                                    </button>
                                                    <?php else: ?>
                                                    <button type="button" class="btn btn-primary" disabled>
                                                        <i class="fas fa-check mr-1"></i> Purchase Order Created
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if ($ws_exists && $po_exists): ?>
                                                <div class="alert alert-success mt-4">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    Both documents have been created successfully! You can now approve the PR.
                                                </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($show_po_button && !$po_exists && !$ws_exists): ?>
                                                <div class="mb-4">
                                                    <label for="po_remarks" class="form-label">PO Remarks (Optional)</label>
                                                    <textarea class="form-control" id="po_remarks" name="po_remarks" rows="2"></textarea>
                                                </div>
                                                <div class="mt-4">
                                                    <button type="button" class="btn btn-primary w-full" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                                        <i class="fas fa-file-invoice-dollar mr-1"></i> Create Purchase Order
                                                    </button>
                                                </div>
                                            <?php elseif ($po_exists): ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Purchase Order already created for this PR.
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'accounting' && 
                                               $user['department'] === 'Admin' && 
                                               $user['position'] === 'Accounting' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <form method="POST" action="" id="accountingActionsForm">
                                            <div class="mb-4">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3 mt-4">
                                                <button type="submit" name="action" value="approve_accounting" class="btn btn-success">
                                                    <i class="fas fa-check mr-1"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject_accounting" class="btn btn-danger">
                                                    <i class="fas fa-times mr-1"></i> Reject
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'approver' && 
                                               $user['department'] === 'Admin' && 
                                               $user['position'] === 'CEO' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <?php if ($request_type === 'project' && $threshold_status === 'exceed' && $document_type !== 'ws'): ?>
                                        <div class="rounded-lg border-l-4 border-warning-600 bg-warning-50 p-4 mb-5">
                                            <h6 class="text-base font-semibold"><i class="fas fa-exclamation-triangle mr-2"></i>Approval Restriction</h6>
                                            <p class="mb-0">The total PO amount (₱<?php echo number_format($total_po_amount, 2); ?>) exceeds the project threshold (₱<?php echo number_format($threshold_amount, 2); ?>). You must adjust the threshold amount before you can approve this PR.</p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="" id="approverActionsForm">
                                            <div class="mb-4">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3 mt-4">
                                                <button type="submit" name="action" value="approve_approver" class="btn btn-success" <?php echo ($request_type === 'project' && $threshold_status === 'exceed' && !$threshold_amount_adjusted && $document_type !== 'ws') ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-check mr-1"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject_approver" class="btn btn-danger">
                                                    <i class="fas fa-times mr-1"></i> Reject
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'purchasing_final' && 
                                               $user['department'] === 'Admin' && 
                                               $user['position'] === 'Purchaser' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <form method="POST" action="" id="purchasingFinalActionsForm">
                                            <div class="mb-4">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3 mt-4">
                                                <button type="submit" name="action" value="approve_purchasing_final" class="btn btn-success">
                                                    <i class="fas fa-check mr-1"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject_purchasing_final" class="btn btn-danger">
                                                    <i class="fas fa-times mr-1"></i> Reject
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'warehouse_receiving' && 
                                               $user['department'] === 'Warehouse' && 
                                               $user['accounttype'] === 'Admin' && 
                                               $document_type !== 'ws'): ?>
                                        <form method="POST" action="" id="warehouseReceivingActionsForm">
                                            <div class="mb-4">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="mt-4">
                                                <button type="submit" name="action" value="complete_warehouse_receiving" class="btn btn-success">
                                                    <i class="fas fa-check mr-1"></i> Complete
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'warehouse_releasing' && 
                                               $user['department'] === 'Warehouse' && 
                                               $user['accounttype'] === 'Admin' &&
                                               ($document_type === 'ws' || $document_type === 'po_ws')): ?>
                                        <div class="rounded-lg border-l-4 border-info-600 bg-info-50 p-4 mb-4">
                                            <h6 class="text-base font-semibold"><i class="fas fa-info-circle mr-2"></i>Warehouse Releasing Stage</h6>
                                            <p class="mb-0">Process the withdrawal slip to release items from inventory.</p>
                                        </div>
                                        
                                        <?php if ($request_type === 'project' && $ws_exists): 
                                            $wsStatusStmt = $pdo->prepare("SELECT status FROM withdrawal_slips WHERE pr_id = ? ORDER BY id DESC LIMIT 1");
                                            $wsStatusStmt->execute([$pr_id]);
                                            $ws_status_check = $wsStatusStmt->fetch(PDO::FETCH_ASSOC);
                                        ?>
                                            <?php if (!$ws_status_check || ($ws_status_check['status'] ?? '') !== 'released'): ?>
                                            <div class="rounded-lg border-l-4 border-warning-600 bg-warning-50 p-4 mb-5">
                                                <h6 class="text-base font-semibold"><i class="fas fa-exclamation-triangle mr-2"></i>Withdrawal Slip Processing Required</h6>
                                                <?php if (!$ws_approved): ?>
                                                <p class="mb-0">This withdrawal slip is pending approval from Approver (CEO). You must wait for approval before you can release items.</p>
                                                <?php else: ?>
                                                <p class="mb-0">You must process the withdrawal slip before completing warehouse releasing. Use the "Release Items" button in the Withdrawal Slips section above.</p>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="" id="warehouseReleasingActionsForm">
                                            <div class="mb-4">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="mt-4">
                                                <?php if ($request_type === 'project' && $ws_exists): ?>
                                                    <?php 
                                                    $wsStatusStmt = $pdo->prepare("SELECT status FROM withdrawal_slips WHERE pr_id = ? ORDER BY id DESC LIMIT 1");
                                                    $wsStatusStmt->execute([$pr_id]);
                                                    $ws_status_check = $wsStatusStmt->fetch(PDO::FETCH_ASSOC);
                                                    ?>
                                                    <?php if ($ws_status_check && ($ws_status_check['status'] ?? '') === 'released'): ?>
                                                        <button type="submit" name="action" value="complete_warehouse_releasing" class="btn btn-success">
                                                            <i class="fas fa-check mr-1"></i> Complete Warehouse Releasing
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" name="action" value="complete_warehouse_releasing" class="btn btn-success" disabled>
                                                            <i class="fas fa-check mr-1"></i> Complete Warehouse Releasing
                                                        </button>
                                                        <small class="block text-muted mt-2">
                                                            <?php if (!$ws_approved): ?>
                                                                Withdrawal slip must be approved by Approver first.
                                                            <?php else: ?>
                                                                Items must be released first.
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="complete_warehouse_releasing" class="btn btn-success" disabled>
                                                        <i class="fas fa-check mr-1"></i> Complete Warehouse Releasing
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'purchasing_completion' && 
                                               $user['department'] === 'Admin' && 
                                               $user['position'] === 'Purchaser' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <form method="POST" action="" id="completionActionsForm">
                                            <div class="mb-4">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="mt-4">
                                                <button type="submit" name="action" value="complete_purchasing" class="btn btn-success">
                                                    <i class="fas fa-check mr-1"></i> Complete
                                                </button>
                                            </div>
                                        </form>
                                        
                                    <?php elseif (($current_stage['stage'] ?? '') === 'accounting_final' && 
                                               $user['department'] === 'Admin' && 
                                               $user['position'] === 'Accounting' && 
                                               $user['accounttype'] === 'Admin'): ?>
                                        <form method="POST" action="" id="accountingFinalActionsForm">
                                            <div class="mb-4">
                                                <label for="remarks" class="form-label">Remarks</label>
                                                <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                                            </div>
                                            <div class="mt-4">
                                                <button type="submit" name="action" value="finalize_accounting" class="btn btn-success">
                                                    <i class="fas fa-check mr-1"></i> Finalize
                                                </button>
                                            </div>
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

                    <!-- Adjust Threshold Amount Modal - Only show for project and not WS -->
                    <?php if ($request_type === 'project' && $document_type !== 'ws'): ?>
                    <div class="modal fade" id="adjustThresholdModal" tabindex="-1" aria-labelledby="adjustThresholdModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="adjustThresholdModalLabel">
                                        <i class="fas fa-balance-scale mr-1"></i>
                                        Adjust Threshold Amount
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <?php if ($threshold_status === 'exceed'): ?>
                                    <div class="rounded-lg border-l-4 border-warning-600 bg-warning-50 p-4 mb-4">
                                        <h6 class="text-base font-semibold"><i class="fas fa-exclamation-triangle mr-2"></i>Threshold Exceeded</h6>
                                        <p class="mb-0">The total PO amount (₱<?php echo number_format($total_po_amount, 2); ?>) exceeds the project threshold (₱<?php echo number_format($threshold_amount, 2); ?>). You must adjust the threshold before approving.</p>
                                    </div>
                                    <?php elseif ($threshold_needs_setting): ?>
                                    <div class="rounded-lg border-l-4 border-warning-600 bg-warning-50 p-4 mb-4">
                                        <h6 class="text-base font-semibold"><i class="fas fa-exclamation-triangle mr-2"></i>Threshold Not Set</h6>
                                        <p class="mb-0">The project threshold amount is ₱0.00. You may set a threshold amount if needed.</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info mb-4">
                                        <h6 class="text-base font-semibold"><i class="fas fa-info-circle mr-2"></i>Threshold Information</h6>
                                        <p class="mb-0">The total PO amount is within the project threshold. You may adjust the threshold if needed, but it's not required for approval.</p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" action="" id="adjustThresholdForm">
                                        <input type="hidden" name="action" value="adjust_threshold_amount">
                                        <div class="mb-4">
                                            <label for="threshold_amount_to_adjust" class="form-label">Amount to Adjust (₱)</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="threshold_amount_to_adjust" 
                                                   name="threshold_amount_to_adjust" 
                                                   placeholder="Enter amount to add or subtract">
                                            <div class="form-text">
                                                Current threshold: ₱<?php echo number_format($threshold_amount, 2); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-2 gap-3 mt-4">
                                            <button type="submit" name="adjustment_type" value="add" class="btn btn-success">
                                                <i class="fas fa-plus-circle mr-1"></i> Add
                                            </button>
                                            <button type="submit" name="adjustment_type" value="subtract" class="btn btn-danger">
                                                <i class="fas fa-minus-circle mr-1"></i> Subtract
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Create Purchase Order Modal - Only show if document_type allows PO -->
                    <?php if ($request_type === 'project' ? ($document_type === 'pr_po' || $document_type === 'po_ws') : true): ?>
                    <div class="modal fade" id="createPOModal" tabindex="-1" aria-labelledby="createPOModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="createPOModalLabel">Create Purchase Order</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="" id="createPOForm">
                                    <input type="hidden" name="action" value="create_purchase_order">
                                    <div class="modal-body">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <?php if ($request_type === 'project'): ?>
                                                This will create a purchase order for items that need to be purchased.
                                            <?php else: ?>
                                                This will create a purchase order for all supplier-requested items.
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                                            <div class="min-w-0">
                                                <label for="expected_delivery" class="form-label">Expected Delivery Date</label>
                                                <input type="date" class="form-control" id="expected_delivery" name="expected_delivery" 
                                                        min="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                            <div class="min-w-0">
                                                <label for="po_remarks" class="form-label">PO Remarks</label>
                                                <textarea class="form-control" id="po_remarks" name="po_remarks" rows="2"></textarea>
                                            </div>
                                        </div>
                                        
                                        <h6 class="text-base font-semibold">Items for Purchase Order:</h6>
                                        <div class="table-responsive max-h-[400px] overflow-y-auto" style="max-height: 400px;">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th width="30">
                                                            <input type="checkbox" id="selectAllPOItems">
                                                        </th>
                                                        <th>Item Name</th>
                                                        <?php if ($request_type === 'project'): ?>
                                                        <th>Warehouse</th>
                                                        <?php endif; ?>
                                                        <th>Supplier</th>
                                                        <th>Qty to Order</th>
                                                        <th>Unit Cost</th>
                                                        <th>Total Cost</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    if ($request_type === 'supplier' || !empty($items_for_po)): 
                                                        if ($request_type === 'supplier') {
                                                            $supplier_items_for_po = [];
                                                            foreach ($items as $item) {
                                                                $received_qty = $item['supplier_received_quantity'] ?? 0;
                                                                $remaining_needed = ($item['quantity'] ?? 0) - $received_qty;
                                                                
                                                                if ($remaining_needed > 0) {
                                                                    $supplier_items_for_po[] = [
                                                                        'pr_item_id' => $item['id'] ?? 0,
                                                                        'item_id' => $item['item_id'] ?? 0,
                                                                        'item_code' => $item['item_code'] ?? '',
                                                                        'item_name' => $item['item_name'] ?? '',
                                                                        'warehouse_id' => $item['warehouse_id'] ?? 0,
                                                                        'warehouse_name' => $item['warehouse_name'] ?? '',
                                                                        'supplier_id' => $item['supplier_id'] ?? 0,
                                                                        'item_supplier_name' => $item['item_supplier_name'] ?? '',
                                                                        'requested_quantity' => $item['quantity'] ?? 0,
                                                                        'delivered_quantity' => 0,
                                                                        'supplier_received_quantity' => $received_qty,
                                                                        'current_stock' => 0,
                                                                        'remaining_needed' => $remaining_needed,
                                                                        'quantity_to_order' => $remaining_needed,
                                                                        'unit_cost' => $item['unit_cost'] ?? 0
                                                                    ];
                                                                }
                                                            }
                                                            $display_items = $supplier_items_for_po;
                                                        } else {
                                                            $display_items = $items_for_po;
                                                        }
                                                        
                                                        if (!empty($display_items)): 
                                                            foreach ($display_items as $item): 
                                                    ?>
                                                    <tr class="bg-slate-50">
                                                        <td>
                                                            <input type="checkbox" name="selected_items[]" value="<?php echo $item['pr_item_id'] ?? 0; ?>" 
                                                                class="po-item-checkbox" data-item-id="<?php echo $item['pr_item_id'] ?? 0; ?>" checked>
                                                        </td>
                                                        <td><?php echo formatItemNameWithCode($item['item_name'] ?? '', $item['item_code'] ?? ''); ?></td>
                                                        <?php if ($request_type === 'project'): ?>
                                                        <td><?php echo htmlspecialchars($item['warehouse_name'] ?? 'N/A'); ?></td>
                                                        <?php endif; ?>
                                                        <td>
                                                            <select name="supplier_id_<?php echo $item['pr_item_id'] ?? 0; ?>" class="form-select form-select-sm min-w-[200px]" required>
                                                                <option value="">Select Supplier</option>
                                                                <?php foreach ($all_suppliers as $supplier): ?>
                                                                <option value="<?php echo $supplier['id'] ?? 0; ?>" 
                                                                    <?php echo (($item['supplier_id'] ?? 0) == ($supplier['id'] ?? 0)) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($supplier['supplier_name'] ?? ''); ?>
                                                                </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="number" name="quantity_<?php echo $item['pr_item_id'] ?? 0; ?>" 
                                                                value="<?php echo $item['quantity_to_order'] ?? 0; ?>" 
                                                                min="1" max="<?php echo $item['quantity_to_order'] ?? 0; ?>" 
                                                                class="form-control form-control-sm po-quantity-input" 
                                                                data-item-id="<?php echo $item['pr_item_id'] ?? 0; ?>"
                                                                style="width: 80px;">
                                                        </td>
                                                        <td>
                                                            <input type="number" name="unit_cost_<?php echo $item['pr_item_id'] ?? 0; ?>" 
                                                                value="<?php echo $item['unit_cost'] ?? 0; ?>" 
                                                                step="0.01" min="0.01" 
                                                                class="form-control form-control-sm po-unit-cost-input" 
                                                                data-item-id="<?php echo $item['pr_item_id'] ?? 0; ?>"
                                                                style="width: 100px;" required>
                                                        </td>
                                                        <td>
                                                            <span class="po-total-cost" id="po_total_<?php echo $item['pr_item_id'] ?? 0; ?>">
                                                                ₱<?php echo number_format(($item['quantity_to_order'] ?? 0) * ($item['unit_cost'] ?? 0), 2); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <?php 
                                                            endforeach;
                                                        else: 
                                                    ?>
                                                    <tr>
                                                        <td colspan="<?php echo $request_type === 'project' ? '7' : '6'; ?>" class="text-center py-4">
                                                            <i class="fas fa-check-circle text-success mr-2"></i>
                                                            <?php if ($request_type === 'project'): ?>
                                                                All items are sufficiently stocked. No purchase order needed.
                                                            <?php else: ?>
                                                                All items have been fully received. No purchase order needed.
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php else: ?>
                                                    <tr>
                                                        <td colspan="<?php echo $request_type === 'project' ? '7' : '6'; ?>" class="text-center py-4">
                                                            <i class="fas fa-exclamation-circle text-warning mr-2"></i>
                                                            No items available for purchase order.
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mt-4">
                                            <div class="min-w-0">
                                                <strong>Total Items Selected: <span id="poSelectedCount">0</span></strong>
                                            </div>
                                            <div class="min-w-0 text-right">
                                                <strong>Grand Total: ₱<span id="poGrandTotal">0.00</span></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success" id="createPOBtn" 
                                            <?php 
                                            $has_items_for_po = false;
                                            if ($request_type === 'project') {
                                                $has_items_for_po = !empty($items_for_po);
                                            } else {
                                                foreach ($items as $item) {
                                                    $received_qty = $item['supplier_received_quantity'] ?? 0;
                                                    if (($item['quantity'] ?? 0) - $received_qty > 0) {
                                                        $has_items_for_po = true;
                                                        break;
                                                    }
                                                }
                                            }
                                            echo $has_items_for_po ? '' : 'disabled';
                                            ?>>
                                            <i class="fas fa-file-invoice-dollar mr-1"></i> Create Purchase Order
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Create Withdrawal Slip Modal - Only show if document_type allows WS -->
                    <?php if ($request_type === 'project' && ($document_type === 'ws' || $document_type === 'po_ws')): ?>
                    <div class="modal fade" id="createWSModal" tabindex="-1" aria-labelledby="createWSModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="createWSModalLabel">Create Withdrawal Slip</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="" id="createWSForm">
                                    <input type="hidden" name="action" value="create_withdrawal_slip">
                                    <div class="modal-body">
                                        <div class="alert alert-success">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            This will create a withdrawal slip to withdraw items from inventory.
                                        </div>
                                        
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-4">
                                            <div class="min-w-0">
                                                <label for="ws_remarks" class="form-label">Withdrawal Slip Remarks</label>
                                                <textarea class="form-control" id="ws_remarks" name="ws_remarks" rows="2"></textarea>
                                            </div>
                                        </div>
                                        
                                        <h6 class="text-base font-semibold">Items for Withdrawal Slip:</h6>
                                        <div class="table-responsive max-h-[400px] overflow-y-auto" style="max-height: 400px;">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th width="30">
                                                            <input type="checkbox" id="selectAllWSItems">
                                                        </th>
                                                        <th>Item Name</th>
                                                        <th>Warehouse</th>
                                                        <th>Requested</th>
                                                        <th>Released</th>
                                                        <th>Remaining</th>
                                                        <th>Current Stock</th>
                                                        <th>Qty to Withdraw</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($items_for_withdrawal)): 
                                                        foreach ($items_for_withdrawal as $item): 
                                                    ?>
                                                    <tr class="bg-success-50">
                                                        <td>
                                                            <input type="checkbox" name="selected_withdrawal_items[]" value="<?php echo $item['pr_item_id'] ?? 0; ?>" 
                                                                   class="ws-item-checkbox" data-item-id="<?php echo $item['pr_item_id'] ?? 0; ?>" checked>
                                                        </td>
                                                        <td><?php echo formatItemNameWithCode($item['item_name'] ?? '', $item['item_code'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($item['warehouse_name'] ?? 'N/A'); ?></td>
                                                        <td><?php echo $item['requested_quantity'] ?? 0; ?></td>
                                                        <td><?php echo $item['delivered_quantity'] ?? 0; ?></td>
                                                        <td><?php echo $item['remaining_needed'] ?? 0; ?></td>
                                                        <td><?php echo $item['current_stock'] ?? 0; ?></td>
                                                        <td>
                                                            <input type="number" name="withdrawal_quantity_<?php echo $item['pr_item_id'] ?? 0; ?>" 
                                                                   value="<?php echo $item['quantity_to_withdraw'] ?? 0; ?>" 
                                                                   min="1" max="<?php echo $item['quantity_to_withdraw'] ?? 0; ?>" 
                                                                   class="form-control form-control-sm ws-quantity-input" 
                                                                   data-item-id="<?php echo $item['pr_item_id'] ?? 0; ?>"
                                                                   style="width: 80px;">
                                                            <?php if (!empty($item['quantity_short'])): ?>
                                                                <small class="text-danger block">
                                                                    <?php echo number_format($item['quantity_short']); ?> short of stock
                                                                </small>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php 
                                                        endforeach;
                                                    else: 
                                                    ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center py-4">
                                                            <i class="fas fa-exclamation-circle text-warning mr-2"></i>
                                                            No items available for withdrawal slip.
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mt-4">
                                            <div class="min-w-0">
                                                <strong>Total Items Selected: <span id="wsSelectedCount">0</span></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success" id="createWSBtn" 
                                            <?php echo !empty($items_for_withdrawal) ? '' : 'disabled'; ?>>
                                            <i class="fas fa-file-invoice mr-1"></i> Create Withdrawal Slip
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

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
                                                    <th>Item Name</th>
                                                    <?php if ($request_type === 'project'): ?>
                                                    <th>Warehouse</th>
                                                    <?php endif; ?>
                                                    <th>Supplier</th>
                                                    <th>Quantity</th>
                                                    <th>Received</th>
                                                    <th>Remaining</th>
                                                    <th>Unit Cost</th>
                                                    <th>Total Cost</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="modal-po-items">
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

                    <!-- View Withdrawal Slip Modal -->
                    <div class="modal fade" id="viewWSModal" tabindex="-1" aria-labelledby="viewWSModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="viewWSModalLabel">Withdrawal Slip Details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-6">
                                        <div class="min-w-0">
                                            <table class="table table-borderless">
                                                <tr>
                                                    <th width="40%">WS Number:</th>
                                                    <td id="modal-ws-number">-</td>
                                                </tr>
                                                <tr>
                                                    <th width="40%">Status:</th>
                                                    <td id="modal-ws-status">-</td>
                                                </tr>
                                                <tr>
                                                    <th>Warehouse:</th>
                                                    <td id="modal-ws-warehouse">-</td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="min-w-0">
                                            <table class="table table-borderless">
                                                <tr>
                                                    <th>WS Date:</th>
                                                    <td id="modal-ws-date">-</td>
                                                </tr>
                                                <tr>
                                                    <th>Remarks:</th>
                                                    <td id="modal-ws-remarks">-</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>

                                    <h6 class="text-base font-semibold">Withdrawal Slip Items:</h6>
                                    <div class="table-responsive max-h-[400px] overflow-y-auto" style="max-height: 400px;">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Item Name</th>
                                                    <th>Warehouse</th>
                                                    <th>Quantity</th>
                                                    <th>Unit Cost</th>
                                                    <th>Total Cost</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="modal-ws-items">
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

                    <!-- Process Withdrawal Slip Modal - Only show if document_type allows WS -->
                    <?php if ($request_type === 'project' && ($document_type === 'ws' || $document_type === 'po_ws')): ?>
                    <div class="modal fade" id="processWSModal" tabindex="-1" aria-labelledby="processWSModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="processWSModalLabel">Withdrawal Slip Items Release</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="" id="processWSForm">
                                    <input type="hidden" name="action" value="process_withdrawal_slip">
                                    <input type="hidden" name="ws_id" id="process_ws_id">
                                    <div class="modal-body">
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            This will process the withdrawal slip and withdraw items from inventory using FIFO method. This action cannot be undone.
                                        </div>
                                        
                                        <?php if (!$ws_approved): ?>
                                        <div class="alert alert-danger">
                                            <i class="fas fa-exclamation-circle mr-1"></i>
                                            <strong>Cannot release items:</strong> This withdrawal slip has not been approved by Approver (CEO) yet. Please wait for approval.
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">Withdrawal Slip Number:</label>
                                            <input type="text" class="form-control" id="process_ws_number" readonly>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="released_date" class="form-label">Released Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="released_date" name="released_date" 
                                                   value="<?php echo date('Y-m-d'); ?>" <?php echo !$ws_approved ? 'disabled' : ''; ?> required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success" <?php echo !$ws_approved ? 'disabled' : ''; ?>>
                                            Release Items
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Deliver Item Modal -->
                    <div class="modal fade" id="deliverItemModal" tabindex="-1" aria-labelledby="deliverItemModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="deliverItemModalLabel">Release Item to Project (FIFO)</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="" id="deliverForm">
                                    <input type="hidden" name="action" id="deliver_action" value="deliver_to_project">
                                    <input type="hidden" name="item_id" id="deliver_item_id">
                                    <input type="hidden" name="warehouse_id" id="deliver_warehouse_id">
                                    <input type="hidden" name="requested_quantity" id="deliver_requested_quantity">
                                    <input type="hidden" name="quantity" id="deliver_quantity">
                                    <div class="modal-body">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <span id="delivery-method-text">This will release the item to the project using FIFO (First-In, First-Out) method.</span>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">Item:</label>
                                            <input type="text" class="form-control" id="deliver_item_name" readonly>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">Warehouse:</label>
                                            <input type="text" class="form-control" id="deliver_warehouse_name" readonly>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">Requested Quantity:</label>
                                            <input type="text" class="form-control" id="deliver_requested_quantity_display" readonly>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">Available Stock:</label>
                                            <input type="text" class="form-control" id="deliver_available_stock" readonly>
                                        </div>
                                        
                                        <div class="mb-4" id="deliver_quantity_field">
                                            <label for="deliver_quantity_input" class="form-label">Quantity to Release <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="deliver_quantity_input" name="deliver_quantity" 
                                                   min="1">
                                            <div class="form-text">Enter the quantity you want to release</div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="deliver_date_issued" class="form-label">Date Issued <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="deliver_date_issued" name="date_issued" 
                                                   value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="deliver_remarks" class="form-label">Remarks (Optional)</label>
                                            <textarea class="form-control" id="deliver_remarks" name="remarks" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success" id="deliver_submit_btn">Release to Project</button>
                                    </div>
                                </form>
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
                                            <td><?php echo formatDateTimeMDY($history['created_at'] ?? null); ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    if (strpos($history['action'] ?? '', 'Approved') !== false) echo 'badge-success';
                                                    elseif (strpos($history['action'] ?? '', 'Rejected') !== false) echo 'badge-danger';
                                                    elseif (strpos($history['action'] ?? '', 'Delivered') !== false) echo 'badge-info';
                                                    elseif (strpos($history['action'] ?? '', 'Purchase Order') !== false) echo 'badge-warning';
                                                    elseif (strpos($history['action'] ?? '', 'Withdrawal Slip') !== false) echo 'badge-info';
                                                    elseif (strpos($history['action'] ?? '', 'Received') !== false) echo 'badge-primary';
                                                    elseif (strpos($history['action'] ?? '', 'Completed') !== false) echo 'badge-success';
                                                    elseif (strpos($history['action'] ?? '', 'Finalized') !== false) echo 'badge-success';
                                                    elseif (strpos($history['action'] ?? '', 'Threshold Amount Added') !== false) echo 'badge-success';
                                                    elseif (strpos($history['action'] ?? '', 'Threshold Amount Subtracted') !== false) echo 'badge-danger';
                                                    else echo 'badge-info';
                                                    ?>
                                                ">
                                                    <?php echo htmlspecialchars($history['action'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatUserName($history); ?></td>
                                            <td><?php echo htmlspecialchars($history['department'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($history['remarks'] ?? ''); ?></td>
                                            <td>
                                                <?php 
                                                switch($history['stage_from'] ?? '') {
                                                    case 'requestor': echo 'Requestor'; break;
                                                    case 'warehouse': echo 'Warehouse'; break;
                                                    case 'purchasing': echo 'Purchasing'; break;
                                                    case 'accounting': echo 'Accounting'; break;
                                                    case 'approver': echo 'Approver (CEO)'; break;
                                                    case 'purchasing_final': echo 'Purchasing Final'; break;
                                                    case 'warehouse_receiving': echo 'Warehouse Receiving'; break;
                                                    case 'warehouse_releasing': echo 'Warehouse Releasing'; break;
                                                    case 'purchasing_completion': echo 'Purchasing Completion'; break;
                                                    case 'accounting_final': echo 'Accounting Final'; break;
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
                                                    case 'warehouse': echo 'Warehouse'; break;
                                                    case 'purchasing': echo 'Purchasing'; break;
                                                    case 'accounting': echo 'Accounting'; break;
                                                    case 'approver': echo 'Approver (CEO)'; break;
                                                    case 'purchasing_final': echo 'Purchasing Final'; break;
                                                    case 'warehouse_receiving': echo 'Warehouse Receiving'; break;
                                                    case 'warehouse_releasing': echo 'Warehouse Releasing'; break;
                                                    case 'purchasing_completion': echo 'Purchasing Completion'; break;
                                                    case 'accounting_final': echo 'Accounting Final'; break;
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

    <?php
    /* Data island consumed by assets/js/pr_view_routing.js. */
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
    $__ocp_data["requestType"] = $request_type;
    $__ocp_data["documentType"] = $document_type;
    $__ocp_data["itemsForWithdrawal"] = !empty($items_for_withdrawal) ? 'true' : 'false';
    /* poItemsDetails */
    ob_start();
    include __DIR__ . "/includes/partials/pr_view_routing/poItemsDetails.php";
    $__ocp_data["poItemsDetails"] = ob_get_clean();
    $__ocp_data["requestType2"] = $request_type === 'project' ? 'true' : 'false';
    $__ocp_data["requestType3"] = $request_type === 'project' ? '9' : '8';
    /* wsItemsDetails */
    ob_start();
    include __DIR__ . "/includes/partials/pr_view_routing/wsItemsDetails.php";
    $__ocp_data["wsItemsDetails"] = ob_get_clean();
    $__ocp_data["thresholdAmount"] = $threshold_amount;
    /* Whether there is anything to raise a PO for. The script is fetched by the
     * browser as its own request, where $request_type, $items_for_po and $items are
     * not in scope, so the answer is worked out here exactly as the script did. */
    if ($request_type === 'project') {
        $__ocp_items_available = !empty($items_for_po);
    } else {
        $__ocp_items_available = false;
        foreach ((array) $items as $__ocp_item) {
            $__ocp_received = $__ocp_item['supplier_received_quantity'] ?? 0;
            if (($__ocp_item['quantity'] ?? 0) - $__ocp_received > 0) {
                $__ocp_items_available = true;
                break;
            }
        }
    }
    $__ocp_data["itemsAvailable"] = $__ocp_items_available;
    unset($__ocp_items_available, $__ocp_item, $__ocp_received);
    /* The script also branches on the request type to decide which handlers to
     * install, so that answer travels with it too. */
    $__ocp_data["isProjectRequest"] = ($request_type === 'project');
    /* Flags for the script. It is a separate request, so it cannot test this
     * page's variables itself; these answer for it. Each is false on an ordinary
     * load, so nothing is shown unless there is something to show. */
    $__ocp_data["hasMessage"] = (!empty($swal_data));
    ocp_page_data("pr_view_routing", $__ocp_data);
    unset($__ocp_data);
    ?>
    <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
    <script src="assets/js/pr_view_routing.js.php"></script>
</body>
</html>
