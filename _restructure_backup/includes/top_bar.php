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

<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
    <a class="navbar-brand ps-3" href="dashboard.php">OCP Construction</a>
    <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0">
        <!-- Spacer for alignment -->
    </div>
    
    <!-- Notification Bell -->
    <?php if (!empty($user_steps)): ?>
    <div class="navbar-nav me-3">
        <li class="nav-item dropdown">
            <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" 
               data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell fa-fw"></i>
                <?php if ($notification_count > 0): ?>
                <span class="position-absolute translate-middle badge rounded-pill bg-danger">
                    <?php echo $notification_count; ?>
                    <span class="visually-hidden">unread notifications</span>
                </span>
                <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end dropdown-notifications" 
                aria-labelledby="notificationDropdown" style="min-width: 400px; max-width: 500px;">
                <li>
                    <div class="dropdown-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Purchase Request Notifications</span>
                            <?php if ($notification_count > 0): ?>
                            <span class="badge bg-primary"><?php echo $notification_count; ?> pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <li><hr class="dropdown-divider"></li>
                
                <?php if ($notification_count > 0): ?>
                    <?php foreach ($pending_notifications as $notification): ?>
                    <li>
                        <form method="POST" action="pr_view_routing.php" class="d-inline w-100">
                            <input type="hidden" name="pr_id" value="<?php echo htmlspecialchars($notification['pr_id']); ?>">
                            <button type="submit" class="dropdown-item d-flex align-items-start py-2 w-100 border-0 bg-transparent">
                                <div class="flex-shrink-0 me-3">
                                    <div class="bg-primary rounded-circle p-2 text-white">
                                        <i class="fas fa-file-alt fa-fw"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 text-start">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h6 class="mb-1">PR #<?php echo htmlspecialchars($notification['purchase_request_no']); ?></h6>
                                        <small class="text-muted"><?php echo $notification['formatted_routing_date']; ?></small>
                                    </div>
                                    <p class="mb-1 small">
                                        <strong>Project:</strong> <?php echo htmlspecialchars($notification['project_name']); ?><br>
                                        <strong>Created by:</strong> <?php echo htmlspecialchars($notification['formatted_creator']); ?><br>
                                        <strong>Current Step:</strong> <?php echo htmlspecialchars($notification['step_name']); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-warning text-dark">Action Required</span>
                                        <small class="text-muted"><?php echo $notification['formatted_date']; ?></small>
                                    </div>
                                </div>
                            </button>
                        </form>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>
                        <div class="dropdown-item text-center py-3">
                            <i class="fas fa-bell-slash fa-2x text-muted mb-2"></i>
                            <p class="mb-0 text-muted">No pending notifications</p>
                            <small class="text-muted">You're all caught up!</small>
                        </div>
                    </li>
                <?php endif; ?>
                
                <li>
                    <div class="dropdown-footer text-center py-2">
                        <a href="purchase_request.php?filter=pending" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i> View All Purchase Requests
                        </a>
                    </div>
                </li>
            </ul>
        </li>
    </div>
    <?php endif; ?>
    
    <!-- User Profile Dropdown -->
    <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" 
               data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user fa-fw"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                <li><a class="dropdown-item" href="#!">Settings</a></li>
                <li><a class="dropdown-item" href="#!">Activity Log</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#!" id="logoutLink">Logout</a></li>
            </ul>
        </li>
    </ul>
</nav>

<style>
.dropdown-notifications {
    max-height: 500px;
    overflow-y: auto;
}

.dropdown-notifications .dropdown-item {
    border-left: 3px solid transparent;
    transition: all 0.2s ease;
}

.dropdown-notifications .dropdown-item:hover {
    background-color: #f8f9fa;
    border-left-color: #0d6efd;
}

.dropdown-notifications .bg-primary {
    background-color: #0d6efd !important;
}

.dropdown-notifications .dropdown-header {
    background-color: #f8f9fa;
    font-weight: 600;
    padding: 0.75rem 1rem;
}

.dropdown-notifications .dropdown-footer {
    background-color: #f8f9fa;
    padding: 0.75rem 1rem;
}

/* Custom scrollbar for dropdown */
.dropdown-notifications::-webkit-scrollbar {
    width: 6px;
}

.dropdown-notifications::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.dropdown-notifications::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.dropdown-notifications::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Badge positioning */
.position-relative .badge {
    font-size: 0.6rem;
    padding: 0.25em 0.4em;
    min-width: 1.2em;
}

/* Form button styles */
.dropdown-notifications form button {
    cursor: pointer;
}

.dropdown-notifications form button:hover {
    background-color: #f8f9fa !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Logout functionality
    document.getElementById('logoutLink').addEventListener('click', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Logout Confirmation',
            text: 'Are you sure you want to logout?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Logout',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                 // Perform logout actions here (clear session, redirect, etc.)
                    window.location.href = 'action/logout.php';
            }
        });
    });
    
    // Auto-refresh notifications every 30 seconds
    setInterval(function() {
        // You can implement AJAX refresh here if needed
        // For now, we'll just reload the page if user is on dashboard or purchase requests page
        const currentPage = window.location.pathname;
        if (currentPage.includes('dashboard.php') || currentPage.includes('purchase_request.php')) {
            // Optionally refresh notifications via AJAX in the future
        }
    }, 30000);
});
</script>