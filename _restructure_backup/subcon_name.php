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
$swal_data = array(); // For storing SweetAlert data

// Initialize form values for add operation
$add_subcon_name = '';
$add_contact_person = '';
$add_phone = '';
$add_email = '';
$add_address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's an add, edit, or delete operation
    if (isset($_POST['delete_id'])) {
        // Delete subcontractor
        $delete_id = $_POST['delete_id'];
        try {
            $deleteStmt = $pdo->prepare("DELETE FROM subcons WHERE id = :id");
            $deleteStmt->bindParam(':id', $delete_id);
            
            if ($deleteStmt->execute()) {
                $swal_data = array(
                    'title' => 'Success!',
                    'text' => 'Subcontractor deleted successfully!',
                    'icon' => 'success'
                );
            } else {
                $swal_data = array(
                    'title' => 'Error!',
                    'text' => 'Error deleting subcontractor. Please try again.',
                    'icon' => 'error'
                );
            }
        } catch(PDOException $e) {
            $swal_data = array(
                'title' => 'Database Error!',
                'text' => 'Database error: ' . $e->getMessage(),
                'icon' => 'error'
            );
        }
    } else if (isset($_POST['edit_id'])) {
        // Edit subcontractor
        $edit_id = $_POST['edit_id'];
        $subcon_name = trim($_POST['subcon_name']);
        $contact_person = trim($_POST['contact_person']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        
        // Basic validation
        if (empty($subcon_name)) {
            $swal_data = array(
                'title' => 'Validation Error!',
                'text' => 'Subcontractor name is required.',
                'icon' => 'error'
            );
            
            // Store values for edit form repopulation
            $edit_subcon_name = $subcon_name;
            $edit_contact_person = $contact_person;
            $edit_phone = $phone;
            $edit_email = $email;
            $edit_address = $address;
        } else {
            try {
                // Check if subcontractor already exists (excluding current subcontractor)
                $checkStmt = $pdo->prepare("SELECT id FROM subcons WHERE subcon_name = :subcon_name AND id != :id");
                $checkStmt->bindParam(':subcon_name', $subcon_name);
                $checkStmt->bindParam(':id', $edit_id);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $swal_data = array(
                        'title' => 'Error!',
                        'text' => 'Subcontractor name already exists. Please use a different name.',
                        'icon' => 'error'
                    );
                    
                    // Store values for edit form repopulation
                    $edit_subcon_name = $subcon_name;
                    $edit_contact_person = $contact_person;
                    $edit_phone = $phone;
                    $edit_email = $email;
                    $edit_address = $address;
                } else {
                    // Update subcontractor
                    $updateStmt = $pdo->prepare("UPDATE subcons SET subcon_name = :subcon_name, contact_person = :contact_person, 
                                               phone = :phone, email = :email, address = :address WHERE id = :id");
                    $updateStmt->bindParam(':id', $edit_id);
                    $updateStmt->bindParam(':subcon_name', $subcon_name);
                    $updateStmt->bindParam(':contact_person', $contact_person);
                    $updateStmt->bindParam(':phone', $phone);
                    $updateStmt->bindParam(':email', $email);
                    $updateStmt->bindParam(':address', $address);
                    
                    if ($updateStmt->execute()) {
                        $swal_data = array(
                            'title' => 'Success!',
                            'text' => 'Subcontractor updated successfully!',
                            'icon' => 'success'
                        );
                    } else {
                        $swal_data = array(
                            'title' => 'Error!',
                            'text' => 'Error updating subcontractor. Please try again.',
                            'icon' => 'error'
                        );
                        
                        // Store values for edit form repopulation
                        $edit_subcon_name = $subcon_name;
                        $edit_contact_person = $contact_person;
                        $edit_phone = $phone;
                        $edit_email = $email;
                        $edit_address = $address;
                    }
                }
            } catch(PDOException $e) {
                $swal_data = array(
                    'title' => 'Database Error!',
                    'text' => 'Database error: ' . $e->getMessage(),
                    'icon' => 'error'
                );
                
                // Store values for edit form repopulation
                $edit_subcon_name = $subcon_name;
                $edit_contact_person = $contact_person;
                $edit_phone = $phone;
                $edit_email = $email;
                $edit_address = $address;
            }
        }
    } else {
        // Add new subcontractor
        $subcon_name = trim($_POST['subcon_name']);
        $contact_person = trim($_POST['contact_person']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        
        // Store values for add form repopulation (in case of error)
        $add_subcon_name = $subcon_name;
        $add_contact_person = $contact_person;
        $add_phone = $phone;
        $add_email = $email;
        $add_address = $address;
        
        // Basic validation
        if (empty($subcon_name)) {
            $swal_data = array(
                'title' => 'Validation Error!',
                'text' => 'Subcontractor name is required.',
                'icon' => 'error'
            );
        } else {
            try {
                // Check if subcontractor already exists
                $checkStmt = $pdo->prepare("SELECT id FROM subcons WHERE subcon_name = :subcon_name");
                $checkStmt->bindParam(':subcon_name', $subcon_name);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $swal_data = array(
                        'title' => 'Error!',
                        'text' => 'Subcontractor already exists. Please use a different name.',
                        'icon' => 'error'
                    );
                } else {
                    // Insert new subcontractor
                    $insertStmt = $pdo->prepare("INSERT INTO subcons (subcon_name, contact_person, phone, email, address) 
                                               VALUES (:subcon_name, :contact_person, :phone, :email, :address)");
                    $insertStmt->bindParam(':subcon_name', $subcon_name);
                    $insertStmt->bindParam(':contact_person', $contact_person);
                    $insertStmt->bindParam(':phone', $phone);
                    $insertStmt->bindParam(':email', $email);
                    $insertStmt->bindParam(':address', $address);
                    
                    if ($insertStmt->execute()) {
                        $swal_data = array(
                            'title' => 'Success!',
                            'text' => 'Subcontractor added successfully!',
                            'icon' => 'success'
                        );
                        
                        // Clear form fields for add form
                        $add_subcon_name = '';
                        $add_contact_person = '';
                        $add_phone = '';
                        $add_email = '';
                        $add_address = '';
                    } else {
                        $swal_data = array(
                            'title' => 'Error!',
                            'text' => 'Error adding subcontractor. Please try again.',
                            'icon' => 'error'
                        );
                    }
                }
            } catch(PDOException $e) {
                $swal_data = array(
                    'title' => 'Database Error!',
                    'text' => 'Database error: ' . $e->getMessage(),
                    'icon' => 'error'
                );
            }
        }
    }
}

// Get all subcontractors from the database
try {
    $subconsStmt = $pdo->prepare("SELECT * FROM subcons ORDER BY created_at DESC");
    $subconsStmt->execute();
    $subcons = $subconsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $subcons = [];
    $swal_data = array(
        'title' => 'Error!',
        'text' => 'Error fetching subcontractors: ' . $e->getMessage(),
        'icon' => 'error'
    );
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
        <title>Subcontractors - OCP Construction</title>
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
                        <h1 class="mt-4">Subcontractors</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Subcontractors</li>
                        </ol>
                        
                        <!-- Display all subcontractors in a table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    All Subcontractors
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubconModal">
                                    <i class="fas fa-plus me-1"></i> Add Subcontractor
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($subcons)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Subcontractor Name</th>
                                                <th>Contact Person</th>
                                                <th>Phone</th>
                                                <th>Email</th>
                                                <th>Address</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subcons as $subcon): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($subcon['id']); ?></td>
                                                <td><?php echo htmlspecialchars($subcon['subcon_name']); ?></td>
                                                <td><?php echo htmlspecialchars($subcon['contact_person']); ?></td>
                                                <td><?php echo htmlspecialchars($subcon['phone']); ?></td>
                                                <td><?php echo htmlspecialchars($subcon['email']); ?></td>
                                                <td><?php echo htmlspecialchars($subcon['address']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-info btn-sm view-subcon" 
                                                                    data-bs-toggle="modal" data-bs-target="#viewSubconModal"
                                                                    data-id="<?php echo $subcon['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($subcon['subcon_name']); ?>"
                                                                    data-contact="<?php echo htmlspecialchars($subcon['contact_person']); ?>"
                                                                    data-phone="<?php echo htmlspecialchars($subcon['phone']); ?>"
                                                                    data-email="<?php echo htmlspecialchars($subcon['email']); ?>"
                                                                    data-address="<?php echo htmlspecialchars($subcon['address']); ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-primary btn-sm edit-subcon" 
                                                                    data-bs-toggle="modal" data-bs-target="#editSubconModal"
                                                                    data-id="<?php echo $subcon['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($subcon['subcon_name']); ?>"
                                                                    data-contact="<?php echo htmlspecialchars($subcon['contact_person']); ?>"
                                                                    data-phone="<?php echo htmlspecialchars($subcon['phone']); ?>"
                                                                    data-email="<?php echo htmlspecialchars($subcon['email']); ?>"
                                                                    data-address="<?php echo htmlspecialchars($subcon['address']); ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-danger btn-sm delete-subcon" 
                                                                    data-bs-toggle="modal" data-bs-target="#deleteSubconModal"
                                                                    data-id="<?php echo $subcon['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($subcon['subcon_name']); ?>">
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
                                <p class="text-center">No subcontractors found. Add your first subcontractor using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Subcontractor Modal -->
        <div class="modal fade" id="addSubconModal" tabindex="-1" aria-labelledby="addSubconModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addSubconModalLabel">Add New Subcontractor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="subcon_name" name="subcon_name" 
                                       value="<?php echo htmlspecialchars($add_subcon_name); ?>" 
                                       required maxlength="255" placeholder="Subcontractor Name">
                                <label for="subcon_name">Subcontractor Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                       value="<?php echo htmlspecialchars($add_contact_person); ?>" 
                                       maxlength="255" placeholder="Contact Person">
                                <label for="contact_person">Contact Person</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($add_phone); ?>" 
                                       maxlength="50" placeholder="Phone">
                                <label for="phone">Phone</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($add_email); ?>" 
                                       maxlength="255" placeholder="Email">
                                <label for="email">Email</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="address" name="address" 
                                          style="height: 100px" placeholder="Address"><?php echo htmlspecialchars($add_address); ?></textarea>
                                <label for="address">Address</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Subcontractor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Subcontractor Modal -->
        <div class="modal fade" id="viewSubconModal" tabindex="-1" aria-labelledby="viewSubconModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewSubconModalLabel">Subcontractor Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Subcontractor Name:</label>
                            <p id="view-name" class="form-control-static"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Contact Person:</label>
                            <p id="view-contact" class="form-control-static"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Phone:</label>
                            <p id="view-phone" class="form-control-static"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email:</label>
                            <p id="view-email" class="form-control-static"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Address:</label>
                            <p id="view-address" class="form-control-static"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Subcontractor Modal -->
        <div class="modal fade" id="editSubconModal" tabindex="-1" aria-labelledby="editSubconModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editSubconModalLabel">Edit Subcontractor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" id="edit_id" name="edit_id" value="">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_subcon_name" name="subcon_name" 
                                       required maxlength="255" placeholder="Subcontractor Name">
                                <label for="edit_subcon_name">Subcontractor Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_contact_person" name="contact_person" 
                                       maxlength="255" placeholder="Contact Person">
                                <label for="edit_contact_person">Contact Person</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_phone" name="phone" 
                                       maxlength="50" placeholder="Phone">
                                <label for="edit_phone">Phone</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="edit_email" name="email" 
                                       maxlength="255" placeholder="Email">
                                <label for="edit_email">Email</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="edit_address" name="address" 
                                          style="height: 100px" placeholder="Address"></textarea>
                                <label for="edit_address">Address</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Subcontractor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteSubconModal" tabindex="-1" aria-labelledby="deleteSubconModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteSubconModalLabel">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" id="delete_id" name="delete_id" value="">
                        <div class="modal-body">
                            <p>Are you sure you want to delete the subcontractor: <strong id="delete-name"></strong>?</p>
                            <p class="text-danger">This action cannot be undone.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Subcontractor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

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
                
                // Show SweetAlert if there's a message
                <?php if (!empty($swal_data)): ?>
                    Swal.fire({
                        title: '<?php echo $swal_data['title']; ?>',
                        text: '<?php echo $swal_data['text']; ?>',
                        icon: '<?php echo $swal_data['icon']; ?>',
                        confirmButtonText: 'OK'
                    });
                    
                    // Show modal if there was an error with form submission
                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $swal_data['icon'] === 'error'): ?>
                        <?php if (isset($_POST['edit_id'])): ?>
                            // Pre-populate edit form if there was an error during edit
                            <?php if (isset($edit_subcon_name)): ?>
                                document.getElementById('edit_id').value = '<?php echo $_POST["edit_id"]; ?>';
                                document.getElementById('edit_subcon_name').value = '<?php echo addslashes($edit_subcon_name); ?>';
                                document.getElementById('edit_contact_person').value = '<?php echo addslashes($edit_contact_person); ?>';
                                document.getElementById('edit_phone').value = '<?php echo addslashes($edit_phone); ?>';
                                document.getElementById('edit_email').value = '<?php echo addslashes($edit_email); ?>';
                                document.getElementById('edit_address').value = '<?php echo addslashes($edit_address); ?>';
                            <?php endif; ?>
                            
                            var editSubconModal = new bootstrap.Modal(document.getElementById('editSubconModal'));
                            editSubconModal.show();
                        <?php else: ?>
                            var addSubconModal = new bootstrap.Modal(document.getElementById('addSubconModal'));
                            addSubconModal.show();
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
                
                // View subcontractor modal
                const viewButtons = document.querySelectorAll('.view-subcon');
                viewButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        document.getElementById('view-name').textContent = this.getAttribute('data-name');
                        document.getElementById('view-contact').textContent = this.getAttribute('data-contact') || 'N/A';
                        document.getElementById('view-phone').textContent = this.getAttribute('data-phone') || 'N/A';
                        document.getElementById('view-email').textContent = this.getAttribute('data-email') || 'N/A';
                        document.getElementById('view-address').textContent = this.getAttribute('data-address') || 'N/A';
                    });
                });
                
                // Edit subcontractor modal
                const editButtons = document.querySelectorAll('.edit-subcon');
                editButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        document.getElementById('edit_id').value = this.getAttribute('data-id');
                        document.getElementById('edit_subcon_name').value = this.getAttribute('data-name');
                        document.getElementById('edit_contact_person').value = this.getAttribute('data-contact') || '';
                        document.getElementById('edit_phone').value = this.getAttribute('data-phone') || '';
                        document.getElementById('edit_email').value = this.getAttribute('data-email') || '';
                        document.getElementById('edit_address').value = this.getAttribute('data-address') || '';
                    });
                });
                
                // Delete subcontractor modal
                const deleteButtons = document.querySelectorAll('.delete-subcon');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        document.getElementById('delete_id').value = this.getAttribute('data-id');
                        document.getElementById('delete-name').textContent = this.getAttribute('data-name');
                    });
                });
            });
        </script>
    </body>
</html>