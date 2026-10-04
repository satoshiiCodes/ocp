<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Process form submissions
$message = '';
$message_type = ''; // success or danger

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try {
        // Validate delete_id is numeric
        if (!is_numeric($_POST['delete_id'])) {
            throw new Exception("Invalid equipment ID");
        }
        
        $deleteStmt = $pdo->prepare("DELETE FROM equipment WHERE id = :id");
        $deleteStmt->bindParam(':id', $_POST['delete_id'], PDO::PARAM_INT);
        
        if ($deleteStmt->execute()) {
            $_SESSION['alert'] = [
                'type' => 'success',
                'title' => 'Success!',
                'message' => 'Equipment deleted successfully!'
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            throw new Exception("Error deleting equipment");
        }
    } catch(PDOException $e) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Database Error',
            'message' => 'Database error: ' . $e->getMessage()
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(Exception $e) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Error',
            'message' => 'Error: ' . $e->getMessage()
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Handle edit request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    try {
        // Validate edit_id is numeric
        if (!is_numeric($_POST['edit_id'])) {
            throw new Exception("Invalid equipment ID");
        }
        
        $equipment_name = trim($_POST['equipment_name']);
        $fuel_type = trim($_POST['fuel_type']);
        $description = trim($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Basic validation
        if (empty($equipment_name)) {
            throw new Exception("Equipment name is required");
        }
        
        // Check if equipment name already exists (excluding current record)
        $checkStmt = $pdo->prepare("SELECT id FROM equipment WHERE equipment_name = :equipment_name AND id != :id");
        $checkStmt->bindParam(':equipment_name', $equipment_name);
        $checkStmt->bindParam(':id', $_POST['edit_id'], PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            throw new Exception("Equipment with this name already exists");
        }
        
        $updateStmt = $pdo->prepare("UPDATE equipment SET equipment_name = :equipment_name, fuel_type = :fuel_type, 
                                    description = :description, is_active = :is_active WHERE id = :id");
        $updateStmt->bindParam(':id', $_POST['edit_id'], PDO::PARAM_INT);
        $updateStmt->bindParam(':equipment_name', $equipment_name);
        $updateStmt->bindParam(':fuel_type', $fuel_type);
        $updateStmt->bindParam(':description', $description);
        $updateStmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
        
        if ($updateStmt->execute()) {
            $_SESSION['alert'] = [
                'type' => 'success',
                'title' => 'Success!',
                'message' => 'Equipment updated successfully!'
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            throw new Exception("Error updating equipment");
        }
    } catch(PDOException $e) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Database Error',
            'message' => 'Database error: ' . $e->getMessage()
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(Exception $e) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Error',
            'message' => 'Error: ' . $e->getMessage()
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Process new equipment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['equipment_name']) && !isset($_POST['edit_id']) && !isset($_POST['delete_id'])) {
    $equipment_name = trim($_POST['equipment_name']);
    $fuel_type = trim($_POST['fuel_type']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Basic validation
    if (empty($equipment_name)) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Validation Error',
            'message' => 'Equipment name is required.'
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        try {
            // Check if equipment already exists
            $checkStmt = $pdo->prepare("SELECT id FROM equipment WHERE equipment_name = :equipment_name");
            $checkStmt->bindParam(':equipment_name', $equipment_name);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Validation Error',
                    'message' => 'Equipment with this name already exists.'
                ];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                // Insert new equipment
                $insertStmt = $pdo->prepare("INSERT INTO equipment (equipment_name, fuel_type, description, is_active) 
                                           VALUES (:equipment_name, :fuel_type, :description, :is_active)");
                $insertStmt->bindParam(':equipment_name', $equipment_name);
                $insertStmt->bindParam(':fuel_type', $fuel_type);
                $insertStmt->bindParam(':description', $description);
                $insertStmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
                
                if ($insertStmt->execute()) {
                    $_SESSION['alert'] = [
                        'type' => 'success',
                        'title' => 'Success!',
                        'message' => 'Equipment added successfully!'
                    ];
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    throw new Exception("Error adding equipment");
                }
            }
        } catch(PDOException $e) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Database Error',
                'message' => 'Database error: ' . $e->getMessage()
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch(Exception $e) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Error',
                'message' => 'Error: ' . $e->getMessage()
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

// Get all equipment from the database
try {
    $equipmentStmt = $pdo->prepare("SELECT * FROM equipment ORDER BY created_at DESC");
    $equipmentStmt->execute();
    $equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $equipment = [];
    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => 'Database Error',
        'message' => 'Error fetching equipment: ' . $e->getMessage()
    ];
}

// Get equipment details for editing if edit_id is set in POST
$edit_equipment = null;
$show_edit_modal = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_request_id'])) {
    try {
        // Validate edit_request_id is numeric
        if (!is_numeric($_POST['edit_request_id'])) {
            throw new Exception("Invalid equipment ID");
        }
        
        $editStmt = $pdo->prepare("SELECT * FROM equipment WHERE id = :id");
        $editStmt->bindParam(':id', $_POST['edit_request_id'], PDO::PARAM_INT);
        $editStmt->execute();
        $edit_equipment = $editStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$edit_equipment) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Error',
                'message' => 'Equipment not found.'
            ];
        } else {
            $show_edit_modal = true;
        }
    } catch(PDOException $e) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Database Error',
            'message' => 'Error fetching equipment details: ' . $e->getMessage()
        ];
    } catch(Exception $e) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Error',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

// Get user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
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

// Check for alert in session
if (isset($_SESSION['alert'])) {
    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']);
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Heavy Equipment Vehicles- OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <style>
            .status-badge {
                padding: 0.35em 0.65em;
                font-size: 0.75em;
                font-weight: 700;
                border-radius: 0.25rem;
            }
            .badge-active {
                color: #fff;
                background-color: #198754;
            }
            .badge-inactive {
                color: #fff;
                background-color: #dc3545;
            }
            .fuel-badge {
                padding: 0.35em 0.65em;
                font-size: 0.75em;
                font-weight: 700;
                border-radius: 0.25rem;
                background-color: #6c757d;
                color: white;
            }
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
                        <h1 class="mt-4">Heavy Equipment Vehicles</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Heavy Equipment Vehicles</li>
                        </ol>
                        
                        <!-- Display all equipment in a table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-tools me-1"></i>
                                    All Heavy Equipment Vehicles
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                                    <i class="fas fa-plus me-1"></i> Add Heavy Equipment Vehicle
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($equipment)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Heavy Vehicle Name</th>
                                                <th>Fuel Type</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($equipment as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['id']); ?></td>
                                                <td><?php echo htmlspecialchars($item['equipment_name']); ?></td>
                                                <td>
                                                    <span class="fuel-badge">
                                                        <?php echo htmlspecialchars(ucfirst($item['fuel_type'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($item['description'])) {
                                                        echo htmlspecialchars($item['description']);
                                                    } else {
                                                        echo '<span class="text-muted">No description</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($item['is_active']): ?>
                                                        <span class="status-badge badge-active">Active</span>
                                                    <?php else: ?>
                                                        <span class="status-badge badge-inactive">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <!-- View Button with POST form -->
                                                        <form method="POST" class="d-inline" action="view_heavy_equipment.php" style="display: inline;">
                                                            <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-info">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <!-- Edit Button with POST form -->
                                                        <form method="POST" class="d-inline" action="" style="display: inline;">
                                                            <input type="hidden" name="edit_request_id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <!-- Delete Button -->
                                                        <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                                data-id="<?php echo $item['id']; ?>" 
                                                                data-name="<?php echo htmlspecialchars($item['equipment_name']); ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No equipment found. Add your first equipment using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Equipment Modal -->
        <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addEquipmentModalLabel">Add New Equipment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addEquipmentForm">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="equipment_name" name="equipment_name" 
                                       placeholder="Equipment Name" required maxlength="100">
                                <label for="equipment_name">Equipment Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="mb-3">
                                <label for="fuel_type" class="form-label">Fuel Type</label>
                                <select class="form-select" id="fuel_type" name="fuel_type">
                                    <option value="gasoline">Gasoline</option>
                                    <option value="diesel">Diesel</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3" placeholder="Equipment description"></textarea>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Equipment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Equipment Modal -->
        <?php if ($show_edit_modal && isset($edit_equipment)): ?>
        <div class="modal fade" id="editEquipmentModal" tabindex="-1" aria-labelledby="editEquipmentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEquipmentModalLabel">Edit Equipment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editEquipmentForm">
                        <input type="hidden" name="edit_id" id="edit_id" value="<?php echo isset($edit_equipment['id']) ? $edit_equipment['id'] : ''; ?>">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_equipment_name" name="equipment_name" 
                                       placeholder="Equipment Name" 
                                       value="<?php echo isset($edit_equipment['equipment_name']) ? htmlspecialchars($edit_equipment['equipment_name']) : ''; ?>" 
                                       required maxlength="100">
                                <label for="edit_equipment_name">Equipment Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_fuel_type" class="form-label">Fuel Type</label>
                                <select class="form-select" id="edit_fuel_type" name="fuel_type">
                                    <option value="gasoline" <?php echo (isset($edit_equipment['fuel_type']) && $edit_equipment['fuel_type'] == 'gasoline') ? 'selected' : ''; ?>>Gasoline</option>
                                    <option value="diesel" <?php echo (isset($edit_equipment['fuel_type']) && $edit_equipment['fuel_type'] == 'diesel') ? 'selected' : ''; ?>>Diesel</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" 
                                          rows="3" placeholder="Equipment description"><?php echo isset($edit_equipment['description']) ? htmlspecialchars($edit_equipment['description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" 
                                    <?php echo (isset($edit_equipment['is_active']) && $edit_equipment['is_active']) ? 'checked' : ''; ?> value="1">
                                <label class="form-check-label" for="edit_is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Equipment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Hidden form for delete operations -->
        <form method="POST" action="" id="deleteForm" style="display: none;">
            <input type="hidden" name="delete_id" id="delete_id" value="">
        </form>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
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
                
                // Show edit modal if edit_equipment is set
                <?php if ($show_edit_modal && isset($edit_equipment)): ?>
                    var editEquipmentModal = new bootstrap.Modal(document.getElementById('editEquipmentModal'));
                    editEquipmentModal.show();
                <?php endif; ?>
                
                // Delete button functionality
                document.querySelectorAll('.delete-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const equipmentId = this.getAttribute('data-id');
                        const equipmentName = this.getAttribute('data-name');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the equipment: ${equipmentName}. This action cannot be undone.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Set the delete_id and submit the form
                                document.getElementById('delete_id').value = equipmentId;
                                document.getElementById('deleteForm').submit();
                            }
                        });
                    });
                });
                
                // Show SweetAlert if there's an alert message
                <?php if (isset($alert)): ?>
                    Swal.fire({
                        icon: '<?php echo $alert['type']; ?>',
                        title: '<?php echo $alert['title']; ?>',
                        text: '<?php echo $alert['message']; ?>',
                        confirmButtonColor: '#3085d6',
                    });
                <?php endif; ?>
                
                // Form validation for add equipment
                const addEquipmentForm = document.getElementById('addEquipmentForm');
                if (addEquipmentForm) {
                    addEquipmentForm.addEventListener('submit', function(e) {
                        const equipmentName = document.getElementById('equipment_name').value.trim();
                        
                        if (!equipmentName) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: 'Equipment name is required.',
                                confirmButtonColor: '#3085d6',
                            });
                            return false;
                        }
                    });
                }
                
                // Form validation for edit equipment
                const editEquipmentForm = document.getElementById('editEquipmentForm');
                if (editEquipmentForm) {
                    editEquipmentForm.addEventListener('submit', function(e) {
                        const equipmentName = document.getElementById('edit_equipment_name').value.trim();
                        
                        if (!equipmentName) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'error',
                                title: 'Validation Error',
                                text: 'Equipment name is required.',
                                confirmButtonColor: '#3085d6',
                            });
                            return false;
                        }
                    });
                }
            });
        </script>
    </body>
</html>