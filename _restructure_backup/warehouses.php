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

// Process delete request
if (isset($_POST['delete_id'])) {
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM warehouses WHERE id = :id");
        $deleteStmt->bindParam(':id', $_POST['delete_id']);
        
        if ($deleteStmt->execute()) {
            $swal_data = [
                'title' => 'Success!',
                'text' => 'Warehouse deleted successfully!',
                'icon' => 'success'
            ];
        } else {
            $swal_data = [
                'title' => 'Error!',
                'text' => 'Error deleting warehouse. Please try again.',
                'icon' => 'error'
            ];
        }
    } catch(PDOException $e) {
        $swal_data = [
            'title' => 'Database Error!',
            'text' => 'Database error: ' . $e->getMessage(),
            'icon' => 'error'
        ];
    }
}

// Process update request
if (isset($_POST['update_id'])) {
    $warehouse_name = trim($_POST['warehouse_name']);
    $location = trim($_POST['location']);
    $capacity = trim($_POST['capacity']);
    $manager = trim($_POST['manager']);
    $phone = trim($_POST['phone']);
    $update_id = $_POST['update_id'];
    
    // Basic validation
    if (empty($warehouse_name) || empty($location)) {
        $swal_data = [
            'title' => 'Validation Error!',
            'text' => 'Warehouse name and location are required.',
            'icon' => 'error'
        ];
    } else {
        try {
            // Check if warehouse already exists (excluding current record)
            $checkStmt = $pdo->prepare("SELECT id FROM warehouses WHERE warehouse_name = :warehouse_name AND location = :location AND id != :id");
            $checkStmt->bindParam(':warehouse_name', $warehouse_name);
            $checkStmt->bindParam(':location', $location);
            $checkStmt->bindParam(':id', $update_id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $swal_data = [
                    'title' => 'Error!',
                    'text' => 'Another warehouse with this name and location already exists.',
                    'icon' => 'error'
                ];
            } else {
                // Update warehouse
                $updateStmt = $pdo->prepare("UPDATE warehouses SET warehouse_name = :warehouse_name, location = :location, 
                                           capacity = :capacity, manager = :manager, phone = :phone WHERE id = :id");
                $updateStmt->bindParam(':warehouse_name', $warehouse_name);
                $updateStmt->bindParam(':location', $location);
                $updateStmt->bindParam(':capacity', $capacity);
                $updateStmt->bindParam(':manager', $manager);
                $updateStmt->bindParam(':phone', $phone);
                $updateStmt->bindParam(':id', $update_id);
                
                if ($updateStmt->execute()) {
                    $swal_data = [
                        'title' => 'Success!',
                        'text' => 'Warehouse updated successfully!',
                        'icon' => 'success'
                    ];
                } else {
                    $swal_data = [
                        'title' => 'Error!',
                        'text' => 'Error updating warehouse. Please try again.',
                        'icon' => 'error'
                    ];
                }
            }
        } catch(PDOException $e) {
            $swal_data = [
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            ];
        }
    }
}

// Process add new warehouse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id']) && !isset($_POST['update_id'])) {
    $warehouse_name = trim($_POST['warehouse_name']);
    $location = trim($_POST['location']);
    $capacity = trim($_POST['capacity']);
    $manager = trim($_POST['manager']);
    $phone = trim($_POST['phone']);
    
    // Basic validation
    if (empty($warehouse_name) || empty($location)) {
        $swal_data = [
            'title' => 'Validation Error!',
            'text' => 'Warehouse name and location are required.',
            'icon' => 'error'
        ];
    } else {
        try {
            // Check if warehouse already exists
            $checkStmt = $pdo->prepare("SELECT id FROM warehouses WHERE warehouse_name = :warehouse_name AND location = :location");
            $checkStmt->bindParam(':warehouse_name', $warehouse_name);
            $checkStmt->bindParam(':location', $location);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $swal_data = [
                    'title' => 'Error!',
                    'text' => 'Warehouse with this name and location already exists.',
                    'icon' => 'error'
                ];
            } else {
                // Insert new warehouse
                $insertStmt = $pdo->prepare("INSERT INTO warehouses (warehouse_name, location, capacity, manager, phone) 
                                           VALUES (:warehouse_name, :location, :capacity, :manager, :phone)");
                $insertStmt->bindParam(':warehouse_name', $warehouse_name);
                $insertStmt->bindParam(':location', $location);
                $insertStmt->bindParam(':capacity', $capacity);
                $insertStmt->bindParam(':manager', $manager);
                $insertStmt->bindParam(':phone', $phone);
                
                if ($insertStmt->execute()) {
                    $swal_data = [
                        'title' => 'Success!',
                        'text' => 'Warehouse added successfully!',
                        'icon' => 'success'
                    ];
                    
                    // Clear form fields by redirecting
                    header("Location: ".$_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $swal_data = [
                        'title' => 'Error!',
                        'text' => 'Error adding warehouse. Please try again.',
                        'icon' => 'error'
                    ];
                }
            }
        } catch(PDOException $e) {
            $swal_data = [
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            ];
        }
    }
}

// Get all warehouses from the database
try {
    $warehousesStmt = $pdo->prepare("SELECT * FROM warehouses ORDER BY created_at DESC");
    $warehousesStmt->execute();
    $warehouses = $warehousesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $warehouses = [];
    $swal_data = [
        'title' => 'Error!',
        'text' => 'Error fetching warehouses: ' . $e->getMessage(),
        'icon' => 'error'
    ];
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

// Store form values for repopulation only if it was an add operation
$form_values = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id']) && !isset($_POST['update_id'])) {
    $form_values = [
        'warehouse_name' => $_POST['warehouse_name'] ?? '',
        'location' => $_POST['location'] ?? '',
        'capacity' => $_POST['capacity'] ?? '',
        'manager' => $_POST['manager'] ?? '',
        'phone' => $_POST['phone'] ?? ''
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Warehouses - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                        <h1 class="mt-4">Warehouses</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Warehouses</li>
                        </ol>
                        
                        <!-- Display all warehouses in a table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    All Warehouses
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWarehouseModal">
                                    <i class="fas fa-plus me-1"></i> Add Warehouse
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($warehouses)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Warehouse Name</th>
                                                <th>Location</th>
                                                <th>Capacity</th>
                                                <th>Manager</th>
                                                <th>Phone</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($warehouses as $warehouse): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($warehouse['id']); ?></td>
                                                <td><?php echo htmlspecialchars($warehouse['warehouse_name']); ?></td>
                                                <td><?php echo htmlspecialchars($warehouse['location']); ?></td>
                                                <td><?php echo htmlspecialchars($warehouse['capacity']); ?></td>
                                                <td><?php echo htmlspecialchars($warehouse['manager']); ?></td>
                                                <td><?php echo htmlspecialchars($warehouse['phone']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="view_id" value="<?php echo $warehouse['id']; ?>">
                                                            <button type="button" class="btn btn-info btn-sm view-btn" data-warehouse='<?php echo json_encode($warehouse); ?>'>
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="edit_id" value="<?php echo $warehouse['id']; ?>">
                                                            <button type="button" class="btn btn-primary btn-sm edit-btn" data-warehouse='<?php echo json_encode($warehouse); ?>'>
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline" onsubmit="return confirmDelete(event, this)">
                                                            <input type="hidden" name="delete_id" value="<?php echo $warehouse['id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm">
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
                                <p class="text-center">No warehouses found. Add your first warehouse using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Warehouse Modal -->
        <div class="modal fade" id="addWarehouseModal" tabindex="-1" aria-labelledby="addWarehouseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addWarehouseModalLabel">Add New Warehouse</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="warehouse_name" name="warehouse_name" 
                                       placeholder="Warehouse Name" 
                                       value="<?php echo isset($form_values['warehouse_name']) ? htmlspecialchars($form_values['warehouse_name']) : ''; ?>" 
                                       required maxlength="255">
                                <label for="warehouse_name">Warehouse Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="location" name="location" 
                                       placeholder="Location"
                                       value="<?php echo isset($form_values['location']) ? htmlspecialchars($form_values['location']) : ''; ?>" 
                                       required maxlength="255">
                                <label for="location">Location <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="capacity" name="capacity" 
                                       placeholder="Capacity"
                                       value="<?php echo isset($form_values['capacity']) ? htmlspecialchars($form_values['capacity']) : ''; ?>" 
                                       min="1">
                                <label for="capacity">Capacity</label>
                                <div class="form-text ms-1">Total storage capacity.</div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="manager" name="manager" 
                                       placeholder="Manager"
                                       value="<?php echo isset($form_values['manager']) ? htmlspecialchars($form_values['manager']) : ''; ?>" 
                                       maxlength="255">
                                <label for="manager">Manager</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       placeholder="Phone"
                                       value="<?php echo isset($form_values['phone']) ? htmlspecialchars($form_values['phone']) : ''; ?>" 
                                       maxlength="20">
                                <label for="phone">Phone</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Warehouse</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- View Warehouse Modal -->
        <div class="modal fade" id="viewWarehouseModal" tabindex="-1" aria-labelledby="viewWarehouseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewWarehouseModalLabel">Warehouse Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Warehouse Name:</label>
                            <p id="view_warehouse_name" class="form-control-static"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Location:</label>
                            <p id="view_location" class="form-control-static"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Capacity:</label>
                            <p id="view_capacity" class="form-control-static"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Manager:</label>
                            <p id="view_manager" class="form-control-static"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Phone:</label>
                            <p id="view_phone" class="form-control-static"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Edit Warehouse Modal -->
        <div class="modal fade" id="editWarehouseModal" tabindex="-1" aria-labelledby="editWarehouseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editWarehouseModalLabel">Edit Warehouse</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="update_id" id="edit_id">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_warehouse_name" name="warehouse_name" 
                                       placeholder="Warehouse Name" required maxlength="255">
                                <label for="edit_warehouse_name">Warehouse Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_location" name="location" 
                                       placeholder="Location" required maxlength="255">
                                <label for="edit_location">Location <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="edit_capacity" name="capacity" 
                                       placeholder="Capacity" min="1">
                                <label for="edit_capacity">Capacity</label>
                                <div class="form-text ms-1">Total storage capacity.</div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_manager" name="manager" 
                                       placeholder="Manager" maxlength="255">
                                <label for="edit_manager">Manager</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_phone" name="phone" 
                                       placeholder="Phone" maxlength="20">
                                <label for="edit_phone">Phone</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Warehouse</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

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
                
                // Show SweetAlert if there's a message
                <?php if (!empty($swal_data)): ?>
                    Swal.fire({
                        title: '<?php echo $swal_data['title']; ?>',
                        text: '<?php echo $swal_data['text']; ?>',
                        icon: '<?php echo $swal_data['icon']; ?>',
                        confirmButtonText: 'OK'
                    });
                <?php endif; ?>
                
                // Show add modal if there was an error with form submission
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($swal_data) && $swal_data['icon'] === 'error' && !isset($_POST['delete_id']) && !isset($_POST['update_id'])): ?>
                    var addWarehouseModal = new bootstrap.Modal(document.getElementById('addWarehouseModal'));
                    addWarehouseModal.show();
                <?php endif; ?>
                
                // Handle view button click
                document.querySelectorAll('.view-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const warehouse = JSON.parse(this.getAttribute('data-warehouse'));
                        
                        document.getElementById('view_warehouse_name').textContent = warehouse.warehouse_name;
                        document.getElementById('view_location').textContent = warehouse.location;
                        document.getElementById('view_capacity').textContent = warehouse.capacity || 'N/A';
                        document.getElementById('view_manager').textContent = warehouse.manager || 'N/A';
                        document.getElementById('view_phone').textContent = warehouse.phone || 'N/A';
                        
                        var viewModal = new bootstrap.Modal(document.getElementById('viewWarehouseModal'));
                        viewModal.show();
                    });
                });
                
                // Handle edit button click
                document.querySelectorAll('.edit-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const warehouse = JSON.parse(this.getAttribute('data-warehouse'));
                        
                        document.getElementById('edit_id').value = warehouse.id;
                        document.getElementById('edit_warehouse_name').value = warehouse.warehouse_name;
                        document.getElementById('edit_location').value = warehouse.location;
                        document.getElementById('edit_capacity').value = warehouse.capacity || '';
                        document.getElementById('edit_manager').value = warehouse.manager || '';
                        document.getElementById('edit_phone').value = warehouse.phone || '';
                        
                        var editModal = new bootstrap.Modal(document.getElementById('editWarehouseModal'));
                        editModal.show();
                    });
                });
                
                // Show edit modal if there was an error with update
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($swal_data) && $swal_data['icon'] === 'error' && isset($_POST['update_id'])): ?>
                    var editWarehouseModal = new bootstrap.Modal(document.getElementById('editWarehouseModal'));
                    editWarehouseModal.show();
                    
                    // Pre-fill the form with submitted values
                    document.getElementById('edit_id').value = '<?php echo isset($_POST["update_id"]) ? $_POST["update_id"] : ""; ?>';
                    document.getElementById('edit_warehouse_name').value = '<?php echo isset($_POST["warehouse_name"]) ? $_POST["warehouse_name"] : ""; ?>';
                    document.getElementById('edit_location').value = '<?php echo isset($_POST["location"]) ? $_POST["location"] : ""; ?>';
                    document.getElementById('edit_capacity').value = '<?php echo isset($_POST["capacity"]) ? $_POST["capacity"] : ""; ?>';
                    document.getElementById('edit_manager').value = '<?php echo isset($_POST["manager"]) ? $_POST["manager"] : ""; ?>';
                    document.getElementById('edit_phone').value = '<?php echo isset($_POST["phone"]) ? $_POST["phone"] : ""; ?>';
                <?php endif; ?>
            });
            
            // Custom confirmation for delete using SweetAlert2
            function confirmDelete(event, form) {
                event.preventDefault();
                
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            }
        </script>
    </body>
</html>