<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Process form submission
$message = '';
$message_type = ''; // success or danger
$swal_data = []; // For SweetAlert2 data

// Handle delete action
if (isset($_POST['delete_tank'])) {
    $tank_id = $_POST['tank_id'];
    
    try {
        // Check if tank exists
        $checkStmt = $pdo->prepare("SELECT id FROM gasoline_tanks WHERE id = :id");
        $checkStmt->bindParam(':id', $tank_id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            // Delete tank
            $deleteStmt = $pdo->prepare("DELETE FROM gasoline_tanks WHERE id = :id");
            $deleteStmt->bindParam(':id', $tank_id);
            
            if ($deleteStmt->execute()) {
                $_SESSION['swal_data'] = [
                    'icon' => 'success',
                    'title' => 'Success!',
                    'text' => 'Gasoline tank deleted successfully!'
                ];
            } else {
                $_SESSION['swal_data'] = [
                    'icon' => 'error',
                    'title' => 'Error!',
                    'text' => 'Error deleting gasoline tank. Please try again.'
                ];
            }
        } else {
            $_SESSION['swal_data'] = [
                'icon' => 'error',
                'title' => 'Error!',
                'text' => 'Tank not found.'
            ];
        }
    } catch(PDOException $e) {
        $_SESSION['swal_data'] = [
            'icon' => 'error',
            'title' => 'Database Error!',
            'text' => 'Database error: ' . $e->getMessage()
        ];
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Handle edit action
if (isset($_POST['edit_tank'])) {
    $tank_id = $_POST['tank_id'];
    $tank_name = trim($_POST['tank_name']);
    $location = trim($_POST['location']);
    $capacity_liters = trim($_POST['capacity_liters']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Basic validation
    if (empty($tank_name) || empty($location) || empty($capacity_liters)) {
        $_SESSION['swal_data'] = [
            'icon' => 'error',
            'title' => 'Validation Error!',
            'text' => 'Tank name, location, and capacity are required.'
        ];
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_type'] = 'edit';
    } else if (!is_numeric($capacity_liters) || $capacity_liters <= 0) {
        $_SESSION['swal_data'] = [
            'icon' => 'error',
            'title' => 'Validation Error!',
            'text' => 'Capacity must be a positive number.'
        ];
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_type'] = 'edit';
    } else {
        try {
            // Check if tank already exists (excluding current tank)
            $checkStmt = $pdo->prepare("SELECT id FROM gasoline_tanks WHERE tank_name = :tank_name AND location = :location AND id != :id");
            $checkStmt->bindParam(':tank_name', $tank_name);
            $checkStmt->bindParam(':location', $location);
            $checkStmt->bindParam(':id', $tank_id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $_SESSION['swal_data'] = [
                    'icon' => 'error',
                    'title' => 'Error!',
                    'text' => 'Another tank with this name and location already exists.'
                ];
                $_SESSION['form_data'] = $_POST;
                $_SESSION['form_type'] = 'edit';
            } else {
                // Update tank
                $updateStmt = $pdo->prepare("UPDATE gasoline_tanks SET tank_name = :tank_name, location = :location, capacity_liters = :capacity_liters, description = :description, is_active = :is_active, updated_at = NOW() WHERE id = :id");
                $updateStmt->bindParam(':tank_name', $tank_name);
                $updateStmt->bindParam(':location', $location);
                $updateStmt->bindParam(':capacity_liters', $capacity_liters);
                $updateStmt->bindParam(':description', $description);
                $updateStmt->bindParam(':is_active', $is_active);
                $updateStmt->bindParam(':id', $tank_id);
                
                if ($updateStmt->execute()) {
                    $_SESSION['swal_data'] = [
                        'icon' => 'success',
                        'title' => 'Success!',
                        'text' => 'Gasoline tank updated successfully!'
                    ];
                } else {
                    $_SESSION['swal_data'] = [
                        'icon' => 'error',
                        'title' => 'Error!',
                        'text' => 'Error updating gasoline tank. Please try again.'
                    ];
                    $_SESSION['form_data'] = $_POST;
                    $_SESSION['form_type'] = 'edit';
                }
            }
        } catch(PDOException $e) {
            $_SESSION['swal_data'] = [
                'icon' => 'error',
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage()
            ];
            $_SESSION['form_data'] = $_POST;
            $_SESSION['form_type'] = 'edit';
        }
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Handle add new tank
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tank_name']) && !isset($_POST['edit_tank'])) {
    $tank_name = trim($_POST['tank_name']);
    $location = trim($_POST['location']);
    $capacity_liters = trim($_POST['capacity_liters']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Basic validation
    if (empty($tank_name) || empty($location) || empty($capacity_liters)) {
        $_SESSION['swal_data'] = [
            'icon' => 'error',
            'title' => 'Validation Error!',
            'text' => 'Tank name, location, and capacity are required.'
        ];
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_type'] = 'add';
    } else if (!is_numeric($capacity_liters) || $capacity_liters <= 0) {
        $_SESSION['swal_data'] = [
            'icon' => 'error',
            'title' => 'Validation Error!',
            'text' => 'Capacity must be a positive number.'
        ];
        $_SESSION['form_data'] = $_POST;
        $_SESSION['form_type'] = 'add';
    } else {
        try {
            // Check if tank already exists
            $checkStmt = $pdo->prepare("SELECT id FROM gasoline_tanks WHERE tank_name = :tank_name AND location = :location");
            $checkStmt->bindParam(':tank_name', $tank_name);
            $checkStmt->bindParam(':location', $location);
            $checkStmt->execute();

            if ($checkStmt->rowCount() > 0) {
                $_SESSION['swal_data'] = [
                    'icon' => 'error',
                    'title' => 'Error!',
                    'text' => 'A tank with this name and location already exists.'
                ];
                $_SESSION['form_data'] = $_POST;
                $_SESSION['form_type'] = 'add';
            } else {
                // Insert new tank
                $insertStmt = $pdo->prepare("INSERT INTO gasoline_tanks (tank_name, location, capacity_liters, description, is_active) 
                                        VALUES (:tank_name, :location, :capacity_liters, :description, :is_active)");
                $insertStmt->bindParam(':tank_name', $tank_name);
                $insertStmt->bindParam(':location', $location);
                $insertStmt->bindParam(':capacity_liters', $capacity_liters);
                $insertStmt->bindParam(':description', $description);
                $insertStmt->bindParam(':is_active', $is_active);
                
                if ($insertStmt->execute()) {
                    $_SESSION['swal_data'] = [
                        'icon' => 'success',
                        'title' => 'Success!',
                        'text' => 'Gasoline tank added successfully!'
                    ];
                } else {
                    $_SESSION['swal_data'] = [
                        'icon' => 'error',
                        'title' => 'Error!',
                        'text' => 'Error adding gasoline tank. Please try again.'
                    ];
                    $_SESSION['form_data'] = $_POST;
                    $_SESSION['form_type'] = 'add';
                }
            }

        } catch(PDOException $e) {
            $_SESSION['swal_data'] = [
                'icon' => 'error',
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage()
            ];
            $_SESSION['form_data'] = $_POST;
            $_SESSION['form_type'] = 'add';
        }
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Get session data for alerts and form repopulation
if (isset($_SESSION['swal_data'])) {
    $swal_data = $_SESSION['swal_data'];
    unset($_SESSION['swal_data']);
}

$form_data = [];
$form_type = '';
if (isset($_SESSION['form_data'])) {
    $form_data = $_SESSION['form_data'];
    unset($_SESSION['form_data']);
}
if (isset($_SESSION['form_type'])) {
    $form_type = $_SESSION['form_type'];
    unset($_SESSION['form_type']);
}

// Get all gasoline tanks from the database
try {
    $tanksStmt = $pdo->prepare("SELECT * FROM gasoline_tanks ORDER BY created_at DESC");
    $tanksStmt->execute();
    $tanks = $tanksStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $tanks = [];
    if (empty($swal_data)) {
        $swal_data = [
            'icon' => 'error',
            'title' => 'Database Error!',
            'text' => 'Error fetching gasoline tanks: ' . $e->getMessage()
        ];
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
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Gasoline Tanks - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <style>
            .btn-group .btn:last-child {
                margin-right: 5px;
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
                        <h1 class="mt-4">Gasoline Tanks</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Gasoline Tanks</li>
                        </ol>
                        
                        <!-- Display all tanks in a table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-gas-pump me-1"></i>
                                    All Gasoline Tanks
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTankModal">
                                    <i class="fas fa-plus me-1"></i> Add Tank
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($tanks)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>Tank Name</th>
                                                <th>Location</th>
                                                <th>Capacity (Liters)</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tanks as $tank): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($tank['tank_name']); ?></td>
                                                <td><?php echo htmlspecialchars($tank['location']); ?></td>
                                                <td><?php echo number_format($tank['capacity_liters'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($tank['description'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $tank['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $tank['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewTankModal<?php echo $tank['id']; ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editTankModal<?php echo $tank['id']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline" id="deleteForm<?php echo $tank['id']; ?>">
                                                            <input type="hidden" name="tank_id" value="<?php echo $tank['id']; ?>">
                                                            <button type="button" class="btn btn-danger btn-sm delete-tank-btn" data-tank-id="<?php echo $tank['id']; ?>" data-tank-name="<?php echo htmlspecialchars($tank['tank_name']); ?>" data-location="<?php echo htmlspecialchars($tank['location']); ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No gasoline tanks found. Add your first tank using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Tank Modal -->
        <div class="modal fade" id="addTankModal" tabindex="-1" aria-labelledby="addTankModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addTankModalLabel">Add New Gasoline Tank</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addTankForm">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="tank_name" name="tank_name" 
                                       placeholder="Tank Name" 
                                       value="<?php echo ($form_type === 'add' && isset($form_data['tank_name'])) ? htmlspecialchars($form_data['tank_name']) : ''; ?>" 
                                       required maxlength="100">
                                <label for="tank_name">Tank Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="location" name="location" 
                                       placeholder="Location"
                                       value="<?php echo ($form_type === 'add' && isset($form_data['location'])) ? htmlspecialchars($form_data['location']) : ''; ?>" 
                                       required maxlength="255">
                                <label for="location">Location <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="capacity_liters" name="capacity_liters" 
                                       placeholder="Capacity in Liters"
                                       value="<?php echo ($form_type === 'add' && isset($form_data['capacity_liters'])) ? htmlspecialchars($form_data['capacity_liters']) : ''; ?>" 
                                       min="0.01" step="0.01" required>
                                <label for="capacity_liters">Capacity (Liters) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="description" name="description" 
                                          placeholder="Description" 
                                          style="height: 100px"><?php echo ($form_type === 'add' && isset($form_data['description'])) ? htmlspecialchars($form_data['description']) : ''; ?></textarea>
                                <label for="description">Description</label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                    <?php echo ($form_type === 'add' && isset($form_data['is_active']) && $form_data['is_active']) ? 'checked' : 'checked'; ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Tank</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modals for each tank (View, Edit) -->
        <?php foreach ($tanks as $tank): ?>
        <!-- View Tank Modal -->
        <div class="modal fade" id="viewTankModal<?php echo $tank['id']; ?>" tabindex="-1" aria-labelledby="viewTankModalLabel<?php echo $tank['id']; ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewTankModalLabel<?php echo $tank['id']; ?>">Tank Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <strong>Tank Name:</strong> <?php echo htmlspecialchars($tank['tank_name']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Location:</strong> <?php echo htmlspecialchars($tank['location']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Capacity:</strong> <?php echo number_format($tank['capacity_liters'], 2); ?> Liters
                        </div>
                        <div class="mb-3">
                            <strong>Description:</strong> <?php echo htmlspecialchars($tank['description'] ?? 'N/A'); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Status:</strong> 
                            <span class="badge bg-<?php echo $tank['is_active'] ? 'success' : 'secondary'; ?>">
                                <?php echo $tank['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($tank['created_at'])); ?>
                        </div>
                        <?php if ($tank['updated_at'] != $tank['created_at']): ?>
                        <div class="mb-3">
                            <strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($tank['updated_at'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Tank Modal -->
        <div class="modal fade" id="editTankModal<?php echo $tank['id']; ?>" tabindex="-1" aria-labelledby="editTankModalLabel<?php echo $tank['id']; ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTankModalLabel<?php echo $tank['id']; ?>">Edit Gasoline Tank</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editTankForm<?php echo $tank['id']; ?>">
                        <div class="modal-body">
                            <input type="hidden" name="tank_id" value="<?php echo $tank['id']; ?>">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_tank_name_<?php echo $tank['id']; ?>" name="tank_name" 
                                       placeholder="Tank Name" 
                                       value="<?php echo ($form_type === 'edit' && isset($form_data['tank_id']) && $form_data['tank_id'] == $tank['id']) ? htmlspecialchars($form_data['tank_name']) : htmlspecialchars($tank['tank_name']); ?>" 
                                       required maxlength="100">
                                <label for="edit_tank_name_<?php echo $tank['id']; ?>">Tank Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_location_<?php echo $tank['id']; ?>" name="location" 
                                       placeholder="Location"
                                       value="<?php echo ($form_type === 'edit' && isset($form_data['tank_id']) && $form_data['tank_id'] == $tank['id']) ? htmlspecialchars($form_data['location']) : htmlspecialchars($tank['location']); ?>" 
                                       required maxlength="255">
                                <label for="edit_location_<?php echo $tank['id']; ?>">Location <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="edit_capacity_liters_<?php echo $tank['id']; ?>" name="capacity_liters" 
                                       placeholder="Capacity in Liters"
                                       value="<?php echo ($form_type === 'edit' && isset($form_data['tank_id']) && $form_data['tank_id'] == $tank['id']) ? htmlspecialchars($form_data['capacity_liters']) : $tank['capacity_liters']; ?>" 
                                       min="0.01" step="0.01" required>
                                <label for="edit_capacity_liters_<?php echo $tank['id']; ?>">Capacity (Liters) <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="edit_description_<?php echo $tank['id']; ?>" name="description" 
                                          placeholder="Description" 
                                          style="height: 100px"><?php echo ($form_type === 'edit' && isset($form_data['tank_id']) && $form_data['tank_id'] == $tank['id']) ? htmlspecialchars($form_data['description']) : htmlspecialchars($tank['description'] ?? ''); ?></textarea>
                                <label for="edit_description_<?php echo $tank['id']; ?>">Description</label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="edit_is_active_<?php echo $tank['id']; ?>" name="is_active" 
                                    <?php echo ($form_type === 'edit' && isset($form_data['tank_id']) && $form_data['tank_id'] == $tank['id']) ? (isset($form_data['is_active']) ? 'checked' : '') : ($tank['is_active'] ? 'checked' : ''); ?>>
                                <label class="form-check-label" for="edit_is_active_<?php echo $tank['id']; ?>">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="edit_tank" class="btn btn-primary">Update Tank</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
        <script>
            // Simple sidebar toggle functionality
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.body.classList.toggle('sb-sidenav-toggled');
            });
            
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple);
                }
                
                // SweetAlert2 configuration
                const Swal = window.Swal;
                
                // Show SweetAlert2 notification if there's data to show
                <?php if (!empty($swal_data)): ?>
                    Swal.fire({
                        icon: '<?php echo $swal_data['icon']; ?>',
                        title: '<?php echo $swal_data['title']; ?>',
                        text: '<?php echo $swal_data['text']; ?>',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        // Auto-open the appropriate modal after alert if there was an error
                        <?php if ($swal_data['icon'] === 'error'): ?>
                            <?php if ($form_type === 'add'): ?>
                                var addTankModal = new bootstrap.Modal(document.getElementById('addTankModal'));
                                addTankModal.show();
                            <?php elseif ($form_type === 'edit' && isset($form_data['tank_id'])): ?>
                                var editTankModal = new bootstrap.Modal(document.getElementById('editTankModal<?php echo $form_data['tank_id']; ?>'));
                                editTankModal.show();
                            <?php endif; ?>
                        <?php endif; ?>
                    });
                <?php endif; ?>
                
                // Auto-open modals based on form type
                <?php if ($form_type === 'add'): ?>
                    var addTankModal = new bootstrap.Modal(document.getElementById('addTankModal'));
                    addTankModal.show();
                <?php elseif ($form_type === 'edit' && isset($form_data['tank_id'])): ?>
                    var editTankModal = new bootstrap.Modal(document.getElementById('editTankModal<?php echo $form_data['tank_id']; ?>'));
                    editTankModal.show();
                <?php endif; ?>
                
                // Delete tank confirmation with SweetAlert2
                document.querySelectorAll('.delete-tank-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const tankId = this.getAttribute('data-tank-id');
                        const tankName = this.getAttribute('data-tank-name');
                        const location = this.getAttribute('data-location');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the tank "${tankName}" located at "${location}". This action cannot be undone.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Create a form and submit it
                                const form = document.getElementById('deleteForm' + tankId);
                                if (form) {
                                    // Add the delete_tank parameter
                                    const deleteInput = document.createElement('input');
                                    deleteInput.type = 'hidden';
                                    deleteInput.name = 'delete_tank';
                                    deleteInput.value = '1';
                                    form.appendChild(deleteInput);
                                    
                                    form.submit();
                                }
                            }
                        });
                    });
                });
                
                // Form validation with SweetAlert2
                document.getElementById('addTankForm')?.addEventListener('submit', function(e) {
                    const tankName = document.getElementById('tank_name').value.trim();
                    const location = document.getElementById('location').value.trim();
                    const capacity = document.getElementById('capacity_liters').value.trim();
                    
                    if (!tankName || !location || !capacity) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please fill in all required fields.',
                            confirmButtonColor: '#3085d6'
                        });
                        return false;
                    }
                    
                    if (isNaN(capacity) || parseFloat(capacity) <= 0) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Capacity must be a positive number.',
                            confirmButtonColor: '#3085d6'
                        });
                        return false;
                    }
                });
                
                // Edit form validation
                <?php foreach ($tanks as $tank): ?>
                document.getElementById('editTankForm<?php echo $tank['id']; ?>')?.addEventListener('submit', function(e) {
                    const tankName = document.getElementById('edit_tank_name_<?php echo $tank['id']; ?>').value.trim();
                    const location = document.getElementById('edit_location_<?php echo $tank['id']; ?>').value.trim();
                    const capacity = document.getElementById('edit_capacity_liters_<?php echo $tank['id']; ?>').value.trim();
                    
                    if (!tankName || !location || !capacity) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please fill in all required fields.',
                            confirmButtonColor: '#3085d6'
                        });
                        return false;
                    }
                    
                    if (isNaN(capacity) || parseFloat(capacity) <= 0) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Capacity must be a positive number.',
                            confirmButtonColor: '#3085d6'
                        });
                        return false;
                    }
                });
                <?php endforeach; ?>
            });
        </script>
    </body>
</html>