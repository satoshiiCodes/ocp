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
$add_supplier_name = '';
$add_contact_person = '';
$add_phone = '';
$add_email = '';
$add_address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's an add, edit, or delete operation
    if (isset($_POST['delete_id'])) {
        // Delete supplier
        $delete_id = $_POST['delete_id'];
        try {
            $deleteStmt = $pdo->prepare("DELETE FROM suppliers WHERE id = :id");
            $deleteStmt->bindParam(':id', $delete_id);
            
            if ($deleteStmt->execute()) {
                $swal_data = array(
                    'title' => 'Success!',
                    'text' => 'Supplier deleted successfully!',
                    'icon' => 'success'
                );
            } else {
                $swal_data = array(
                    'title' => 'Error!',
                    'text' => 'Error deleting supplier. Please try again.',
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
        // Edit supplier
        $edit_id = $_POST['edit_id'];
        $supplier_name = trim($_POST['supplier_name']);
        $contact_person = trim($_POST['contact_person']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        
        // Basic validation
        if (empty($supplier_name)) {
            $swal_data = array(
                'title' => 'Validation Error!',
                'text' => 'Supplier name is required.',
                'icon' => 'error'
            );
            
            // Store values for edit form repopulation
            $edit_supplier_name = $supplier_name;
            $edit_contact_person = $contact_person;
            $edit_phone = $phone;
            $edit_email = $email;
            $edit_address = $address;
        } else {
            try {
                // Check if supplier already exists (excluding current supplier)
                $checkStmt = $pdo->prepare("SELECT id FROM suppliers WHERE supplier_name = :supplier_name AND id != :id");
                $checkStmt->bindParam(':supplier_name', $supplier_name);
                $checkStmt->bindParam(':id', $edit_id);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $swal_data = array(
                        'title' => 'Error!',
                        'text' => 'Supplier name already exists. Please use a different name.',
                        'icon' => 'error'
                    );
                    
                    // Store values for edit form repopulation
                    $edit_supplier_name = $supplier_name;
                    $edit_contact_person = $contact_person;
                    $edit_phone = $phone;
                    $edit_email = $email;
                    $edit_address = $address;
                } else {
                    // Update supplier
                    $updateStmt = $pdo->prepare("UPDATE suppliers SET supplier_name = :supplier_name, contact_person = :contact_person, 
                                               phone = :phone, email = :email, address = :address WHERE id = :id");
                    $updateStmt->bindParam(':id', $edit_id);
                    $updateStmt->bindParam(':supplier_name', $supplier_name);
                    $updateStmt->bindParam(':contact_person', $contact_person);
                    $updateStmt->bindParam(':phone', $phone);
                    $updateStmt->bindParam(':email', $email);
                    $updateStmt->bindParam(':address', $address);
                    
                    if ($updateStmt->execute()) {
                        $swal_data = array(
                            'title' => 'Success!',
                            'text' => 'Supplier updated successfully!',
                            'icon' => 'success'
                        );
                    } else {
                        $swal_data = array(
                            'title' => 'Error!',
                            'text' => 'Error updating supplier. Please try again.',
                            'icon' => 'error'
                        );
                        
                        // Store values for edit form repopulation
                        $edit_supplier_name = $supplier_name;
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
                $edit_supplier_name = $supplier_name;
                $edit_contact_person = $contact_person;
                $edit_phone = $phone;
                $edit_email = $email;
                $edit_address = $address;
            }
        }
    } else {
        // Add new supplier
        $supplier_name = trim($_POST['supplier_name']);
        $contact_person = trim($_POST['contact_person']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        
        // Store values for add form repopulation (in case of error)
        $add_supplier_name = $supplier_name;
        $add_contact_person = $contact_person;
        $add_phone = $phone;
        $add_email = $email;
        $add_address = $address;
        
        // Basic validation
        if (empty($supplier_name)) {
            $swal_data = array(
                'title' => 'Validation Error!',
                'text' => 'Supplier name is required.',
                'icon' => 'error'
            );
        } else {
            try {
                // Check if supplier already exists
                $checkStmt = $pdo->prepare("SELECT id FROM suppliers WHERE supplier_name = :supplier_name");
                $checkStmt->bindParam(':supplier_name', $supplier_name);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $swal_data = array(
                        'title' => 'Error!',
                        'text' => 'Supplier already exists. Please use a different name.',
                        'icon' => 'error'
                    );
                } else {
                    // Insert new supplier
                    $insertStmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, contact_person, phone, email, address) 
                                               VALUES (:supplier_name, :contact_person, :phone, :email, :address)");
                    $insertStmt->bindParam(':supplier_name', $supplier_name);
                    $insertStmt->bindParam(':contact_person', $contact_person);
                    $insertStmt->bindParam(':phone', $phone);
                    $insertStmt->bindParam(':email', $email);
                    $insertStmt->bindParam(':address', $address);
                    
                    if ($insertStmt->execute()) {
                        $swal_data = array(
                            'title' => 'Success!',
                            'text' => 'Supplier added successfully!',
                            'icon' => 'success'
                        );
                        
                        // Clear form fields for add form
                        $add_supplier_name = '';
                        $add_contact_person = '';
                        $add_phone = '';
                        $add_email = '';
                        $add_address = '';
                    } else {
                        $swal_data = array(
                            'title' => 'Error!',
                            'text' => 'Error adding supplier. Please try again.',
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

// Get all suppliers from the database
try {
    $suppliersStmt = $pdo->prepare("SELECT * FROM suppliers ORDER BY created_at DESC");
    $suppliersStmt->execute();
    $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $suppliers = [];
    $swal_data = array(
        'title' => 'Error!',
        'text' => 'Error fetching suppliers: ' . $e->getMessage(),
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
        <title>Suppliers - OCP Construction</title>
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
                        <h1 class="mt-4">Suppliers</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Suppliers</li>
                        </ol>
                        
                        <!-- Display all suppliers in a table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    All Suppliers
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                    <i class="fas fa-plus me-1"></i> Add Supplier
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($suppliers)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Supplier Name</th>
                                                <th>Contact Person</th>
                                                <th>Phone</th>
                                                <th>Email</th>
                                                <th>Address</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($suppliers as $supplier): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($supplier['id']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['contact_person']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['address']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-info btn-sm view-supplier" 
                                                                    data-bs-toggle="modal" data-bs-target="#viewSupplierModal"
                                                                    data-id="<?php echo $supplier['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($supplier['supplier_name']); ?>"
                                                                    data-contact="<?php echo htmlspecialchars($supplier['contact_person']); ?>"
                                                                    data-phone="<?php echo htmlspecialchars($supplier['phone']); ?>"
                                                                    data-email="<?php echo htmlspecialchars($supplier['email']); ?>"
                                                                    data-address="<?php echo htmlspecialchars($supplier['address']); ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-primary btn-sm edit-supplier" 
                                                                    data-bs-toggle="modal" data-bs-target="#editSupplierModal"
                                                                    data-id="<?php echo $supplier['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($supplier['supplier_name']); ?>"
                                                                    data-contact="<?php echo htmlspecialchars($supplier['contact_person']); ?>"
                                                                    data-phone="<?php echo htmlspecialchars($supplier['phone']); ?>"
                                                                    data-email="<?php echo htmlspecialchars($supplier['email']); ?>"
                                                                    data-address="<?php echo htmlspecialchars($supplier['address']); ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-danger btn-sm delete-supplier" 
                                                                    data-bs-toggle="modal" data-bs-target="#deleteSupplierModal"
                                                                    data-id="<?php echo $supplier['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($supplier['supplier_name']); ?>">
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
                                <p class="text-center">No suppliers found. Add your first supplier using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Supplier Modal -->
        <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="supplier_name" name="supplier_name" 
                                       value="<?php echo htmlspecialchars($add_supplier_name); ?>" 
                                       required maxlength="255" placeholder="Supplier Name">
                                <label for="supplier_name">Supplier Name <span class="text-danger">*</span></label>
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
                                       maxlength="20" placeholder="Phone">
                                <label for="phone">Phone</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($add_email); ?>" 
                                       maxlength="100" placeholder="Email">
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
                            <button type="submit" class="btn btn-primary">Add Supplier</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Supplier Modal -->
        <div class="modal fade" id="viewSupplierModal" tabindex="-1" aria-labelledby="viewSupplierModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewSupplierModalLabel">Supplier Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Supplier Name:</label>
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

        <!-- Edit Supplier Modal -->
        <div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editSupplierModalLabel">Edit Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" id="edit_id" name="edit_id" value="">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_supplier_name" name="supplier_name" 
                                       required maxlength="255" placeholder="Supplier Name">
                                <label for="edit_supplier_name">Supplier Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_contact_person" name="contact_person" 
                                       maxlength="255" placeholder="Contact Person">
                                <label for="edit_contact_person">Contact Person</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_phone" name="phone" 
                                       maxlength="20" placeholder="Phone">
                                <label for="edit_phone">Phone</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="edit_email" name="email" 
                                       maxlength="100" placeholder="Email">
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
                            <button type="submit" class="btn btn-primary">Update Supplier</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteSupplierModal" tabindex="-1" aria-labelledby="deleteSupplierModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteSupplierModalLabel">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" id="delete_id" name="delete_id" value="">
                        <div class="modal-body">
                            <p>Are you sure you want to delete the supplier: <strong id="delete-name"></strong>?</p>
                            <p class="text-danger">This action cannot be undone.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Supplier</button>
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
                            <?php if (isset($edit_supplier_name)): ?>
                                document.getElementById('edit_id').value = '<?php echo $_POST["edit_id"]; ?>';
                                document.getElementById('edit_supplier_name').value = '<?php echo addslashes($edit_supplier_name); ?>';
                                document.getElementById('edit_contact_person').value = '<?php echo addslashes($edit_contact_person); ?>';
                                document.getElementById('edit_phone').value = '<?php echo addslashes($edit_phone); ?>';
                                document.getElementById('edit_email').value = '<?php echo addslashes($edit_email); ?>';
                                document.getElementById('edit_address').value = '<?php echo addslashes($edit_address); ?>';
                            <?php endif; ?>
                            
                            var editSupplierModal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
                            editSupplierModal.show();
                        <?php else: ?>
                            var addSupplierModal = new bootstrap.Modal(document.getElementById('addSupplierModal'));
                            addSupplierModal.show();
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
                
                // View supplier modal
                const viewButtons = document.querySelectorAll('.view-supplier');
                viewButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        document.getElementById('view-name').textContent = this.getAttribute('data-name');
                        document.getElementById('view-contact').textContent = this.getAttribute('data-contact') || 'N/A';
                        document.getElementById('view-phone').textContent = this.getAttribute('data-phone') || 'N/A';
                        document.getElementById('view-email').textContent = this.getAttribute('data-email') || 'N/A';
                        document.getElementById('view-address').textContent = this.getAttribute('data-address') || 'N/A';
                    });
                });
                
                // Edit supplier modal
                const editButtons = document.querySelectorAll('.edit-supplier');
                editButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        document.getElementById('edit_id').value = this.getAttribute('data-id');
                        document.getElementById('edit_supplier_name').value = this.getAttribute('data-name');
                        document.getElementById('edit_contact_person').value = this.getAttribute('data-contact') || '';
                        document.getElementById('edit_phone').value = this.getAttribute('data-phone') || '';
                        document.getElementById('edit_email').value = this.getAttribute('data-email') || '';
                        document.getElementById('edit_address').value = this.getAttribute('data-address') || '';
                    });
                });
                
                // Delete supplier modal
                const deleteButtons = document.querySelectorAll('.delete-supplier');
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