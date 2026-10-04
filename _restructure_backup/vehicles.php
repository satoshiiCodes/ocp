<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Process form submission for adding vehicle
$message = '';
$message_type = ''; // success or danger
$show_modal = false;

// Handle delete request
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM vehicles WHERE id = :id");
        $deleteStmt->bindParam(':id', $delete_id);
        
        if ($deleteStmt->execute()) {
            $_SESSION['alert'] = [
                'type' => 'success',
                'title' => 'Success!',
                'text' => 'Vehicle deleted successfully!'
            ];
            
            // Refresh the page to show the updated list
            header("Location: ".str_replace('?delete_id='.$delete_id, '', $_SERVER['REQUEST_URI']));
            exit();
        } else {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Error!',
                'text' => 'Error deleting vehicle. Please try again.'
            ];
        }
    } catch(PDOException $e) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Database Error!',
            'text' => 'Database error: ' . $e->getMessage()
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'edit_vehicle') {
        // Handle edit vehicle form submission
        $vehicle_id = $_POST['vehicle_id'];
        $vehicle_name = trim($_POST['vehicle_name']);
        $plate_number = trim($_POST['plate_number']);
        $fuel_type = trim($_POST['fuel_type']);
        $description = trim($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Basic validation
        if (empty($vehicle_name) || empty($plate_number)) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Validation Error!',
                'text' => 'Vehicle name and plate number are required.'
            ];
            $show_modal = 'edit';
        } else {
            try {
                // Check if another vehicle already has this plate number
                $checkStmt = $pdo->prepare("SELECT id FROM vehicles WHERE plate_number = :plate_number AND id != :id");
                $checkStmt->bindParam(':plate_number', $plate_number);
                $checkStmt->bindParam(':id', $vehicle_id);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $_SESSION['alert'] = [
                        'type' => 'error',
                        'title' => 'Duplicate Entry!',
                        'text' => 'Another vehicle with this plate number already exists.'
                    ];
                    $show_modal = 'edit';
                } else {
                    // Update vehicle
                    $updateStmt = $pdo->prepare("UPDATE vehicles SET vehicle_name = :vehicle_name, plate_number = :plate_number, 
                                               fuel_type = :fuel_type, description = :description, is_active = :is_active 
                                               WHERE id = :id");
                    $updateStmt->bindParam(':vehicle_name', $vehicle_name);
                    $updateStmt->bindParam(':plate_number', $plate_number);
                    $updateStmt->bindParam(':fuel_type', $fuel_type);
                    $updateStmt->bindParam(':description', $description);
                    $updateStmt->bindParam(':is_active', $is_active);
                    $updateStmt->bindParam(':id', $vehicle_id);
                    
                    if ($updateStmt->execute()) {
                        $_SESSION['alert'] = [
                            'type' => 'success',
                            'title' => 'Success!',
                            'text' => 'Vehicle updated successfully!'
                        ];
                        
                        // Refresh the page to show the updated vehicle
                        header("Location: ".$_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $_SESSION['alert'] = [
                            'type' => 'error',
                            'title' => 'Error!',
                            'text' => 'Error updating vehicle. Please try again.'
                        ];
                        $show_modal = 'edit';
                    }
                }
            } catch(PDOException $e) {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Database Error!',
                    'text' => 'Database error: ' . $e->getMessage()
                ];
                $show_modal = 'edit';
            }
        }
    } else {
        // Handle add vehicle form submission (original code)
        $vehicle_name = trim($_POST['vehicle_name']);
        $plate_number = trim($_POST['plate_number']);
        $fuel_type = trim($_POST['fuel_type']);
        $description = trim($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Basic validation
        if (empty($vehicle_name) || empty($plate_number)) {
            $_SESSION['alert'] = [
                'type' => 'error',
                'title' => 'Validation Error!',
                'text' => 'Vehicle name and plate number are required.'
            ];
            $show_modal = 'add';
        } else {
            try {
                // Check if vehicle already exists
                $checkStmt = $pdo->prepare("SELECT id FROM vehicles WHERE plate_number = :plate_number");
                $checkStmt->bindParam(':plate_number', $plate_number);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $_SESSION['alert'] = [
                        'type' => 'error',
                        'title' => 'Duplicate Entry!',
                        'text' => 'Vehicle with this plate number already exists.'
                    ];
                    $show_modal = 'add';
                } else {
                    // Insert new vehicle
                    $insertStmt = $pdo->prepare("INSERT INTO vehicles (vehicle_name, plate_number, fuel_type, description, is_active) 
                                               VALUES (:vehicle_name, :plate_number, :fuel_type, :description, :is_active)");
                    $insertStmt->bindParam(':vehicle_name', $vehicle_name);
                    $insertStmt->bindParam(':plate_number', $plate_number);
                    $insertStmt->bindParam(':fuel_type', $fuel_type);
                    $insertStmt->bindParam(':description', $description);
                    $insertStmt->bindParam(':is_active', $is_active);
                    
                    if ($insertStmt->execute()) {
                        $_SESSION['alert'] = [
                            'type' => 'success',
                            'title' => 'Success!',
                            'text' => 'Vehicle added successfully!'
                        ];
                        
                        // Clear form fields
                        $_POST = array();
                        
                        // Refresh the page to show the new vehicle
                        header("Location: ".$_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $_SESSION['alert'] = [
                            'type' => 'error',
                            'title' => 'Error!',
                            'text' => 'Error adding vehicle. Please try again.'
                        ];
                        $show_modal = 'add';
                    }
                }
            } catch(PDOException $e) {
                $_SESSION['alert'] = [
                    'type' => 'error',
                    'title' => 'Database Error!',
                    'text' => 'Database error: ' . $e->getMessage()
                ];
                $show_modal = 'add';
            }
        }
    }
}

// Get all vehicles from the database
try {
    $vehiclesStmt = $pdo->prepare("SELECT * FROM vehicles ORDER BY created_at DESC");
    $vehiclesStmt->execute();
    $vehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $vehicles = [];
    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => 'Database Error!',
        'text' => 'Error fetching vehicles: ' . $e->getMessage()
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
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Vehicles - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <style>
            .status-badge {
                font-size: 0.8rem;
                padding: 0.35em 0.65em;
            }
            .badge-active {
                background-color: #198754;
            }
            .badge-inactive {
                background-color: #6c757d;
            }
            .action-buttons {
                display: flex;
                gap: 5px;
            }
            .action-btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
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
                        <h1 class="mt-4">Vehicles</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Vehicles</li>
                        </ol>
                        
                        <!-- Display all vehicles in a table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-car me-1"></i>
                                    All Vehicles
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                                    <i class="fas fa-plus me-1"></i> Add Vehicle
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($vehicles)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Vehicle Name</th>
                                                <th>Plate Number</th>
                                                <th>Fuel Type</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($vehicles as $vehicle): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($vehicle['id']); ?></td>
                                                <td><?php echo htmlspecialchars($vehicle['vehicle_name']); ?></td>
                                                <td><?php echo htmlspecialchars($vehicle['plate_number']); ?></td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo htmlspecialchars(ucfirst($vehicle['fuel_type'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (!empty($vehicle['description'])) {
                                                        echo htmlspecialchars($vehicle['description']);
                                                    } else {
                                                        echo '<span class="text-muted">No description</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($vehicle['is_active']): ?>
                                                    <span class="badge status-badge badge-active">Active</span>
                                                    <?php else: ?>
                                                    <span class="badge status-badge badge-inactive">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <form method="POST" action="view_vehicle.php" style="display: inline;">
                                                            <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-info action-btn" title="View">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <button class="btn btn-sm btn-warning action-btn edit-vehicle-btn" 
                                                                title="Edit" 
                                                                data-id="<?php echo $vehicle['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($vehicle['vehicle_name']); ?>"
                                                                data-plate="<?php echo htmlspecialchars($vehicle['plate_number']); ?>"
                                                                data-fuel="<?php echo $vehicle['fuel_type']; ?>"
                                                                data-description="<?php echo htmlspecialchars($vehicle['description']); ?>"
                                                                data-active="<?php echo $vehicle['is_active']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger action-btn delete-vehicle-btn" 
                                                                title="Delete" 
                                                                data-id="<?php echo $vehicle['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($vehicle['vehicle_name']); ?>"
                                                                data-plate="<?php echo htmlspecialchars($vehicle['plate_number']); ?>">
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
                                <p class="text-center">No vehicles found. Add your first vehicle using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Vehicle Modal -->
        <div class="modal fade" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addVehicleModalLabel">Add New Vehicle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="vehicle_name" name="vehicle_name" 
                                       placeholder="Vehicle Name" 
                                       value="<?php echo isset($_POST['vehicle_name']) ? htmlspecialchars($_POST['vehicle_name']) : ''; ?>" 
                                       required maxlength="100">
                                <label for="vehicle_name">Vehicle Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="plate_number" name="plate_number" 
                                       placeholder="Plate Number"
                                       value="<?php echo isset($_POST['plate_number']) ? htmlspecialchars($_POST['plate_number']) : ''; ?>" 
                                       required maxlength="20">
                                <label for="plate_number">Plate Number <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="mb-3">
                                <label for="fuel_type" class="form-label">Fuel Type</label>
                                <select class="form-select" id="fuel_type" name="fuel_type">
                                    <option value="gasoline" <?php echo (isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'gasoline') ? 'selected' : ''; ?>>Gasoline</option>
                                    <option value="diesel" <?php echo (isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'diesel') ? 'selected' : ''; ?>>Diesel</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3" placeholder="Vehicle description (optional)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Vehicle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Vehicle Modal -->
        <div class="modal fade" id="editVehicleModal" tabindex="-1" aria-labelledby="editVehicleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editVehicleModalLabel">Edit Vehicle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="edit_vehicle">
                        <input type="hidden" name="vehicle_id" id="edit_vehicle_id">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_vehicle_name" name="vehicle_name" 
                                       placeholder="Vehicle Name" required maxlength="100">
                                <label for="edit_vehicle_name">Vehicle Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_plate_number" name="plate_number" 
                                       placeholder="Plate Number" required maxlength="20">
                                <label for="edit_plate_number">Plate Number <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_fuel_type" class="form-label">Fuel Type</label>
                                <select class="form-select" id="edit_fuel_type" name="fuel_type">
                                    <option value="gasoline">Gasoline</option>
                                    <option value="diesel">Diesel</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" 
                                          rows="3" placeholder="Vehicle description (optional)"></textarea>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Vehicle</button>
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
                
                // Show SweetAlert2 alerts from PHP session
                <?php if (isset($_SESSION['alert'])): ?>
                    Swal.fire({
                        icon: '<?php echo $_SESSION['alert']['type']; ?>',
                        title: '<?php echo $_SESSION['alert']['title']; ?>',
                        text: '<?php echo $_SESSION['alert']['text']; ?>',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    });
                    <?php unset($_SESSION['alert']); ?>
                <?php endif; ?>
                
                // Show modal if there was an error with form submission
                <?php if ($show_modal === 'add'): ?>
                    var addVehicleModal = new bootstrap.Modal(document.getElementById('addVehicleModal'));
                    addVehicleModal.show();
                <?php elseif ($show_modal === 'edit'): ?>
                    var editVehicleModal = new bootstrap.Modal(document.getElementById('editVehicleModal'));
                    editVehicleModal.show();
                <?php endif; ?>
                
                // Handle edit vehicle button clicks
                const editButtons = document.querySelectorAll('.edit-vehicle-btn');
                editButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const vehicleId = this.getAttribute('data-id');
                        const vehicleName = this.getAttribute('data-name');
                        const plateNumber = this.getAttribute('data-plate');
                        const fuelType = this.getAttribute('data-fuel');
                        const description = this.getAttribute('data-description');
                        const isActive = this.getAttribute('data-active');
                        
                        // Populate the edit form
                        document.getElementById('edit_vehicle_id').value = vehicleId;
                        document.getElementById('edit_vehicle_name').value = vehicleName;
                        document.getElementById('edit_plate_number').value = plateNumber;
                        document.getElementById('edit_fuel_type').value = fuelType;
                        document.getElementById('edit_description').value = description;
                        document.getElementById('edit_is_active').checked = (isActive === '1');
                        
                        // Show the modal
                        const editModal = new bootstrap.Modal(document.getElementById('editVehicleModal'));
                        editModal.show();
                    });
                });
                
                // Handle delete vehicle button clicks with SweetAlert2
                const deleteButtons = document.querySelectorAll('.delete-vehicle-btn');
                
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const vehicleId = this.getAttribute('data-id');
                        const vehicleName = this.getAttribute('data-name');
                        const plateNumber = this.getAttribute('data-plate');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the vehicle: ${vehicleName} (${plateNumber}). This action cannot be undone.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = `?delete_id=${vehicleId}`;
                            }
                        });
                    });
                });
            });
        </script>
    </body>
</html>