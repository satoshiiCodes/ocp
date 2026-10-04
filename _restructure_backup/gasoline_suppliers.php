<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Initialize message variables for SweetAlert2
$swal_message = '';
$swal_type = ''; // success, error, warning, info
$swal_title = '';

// Process delete request
if (isset($_POST['delete_supplier'])) {
    $supplier_id = $_POST['supplier_id'];
    
    try {
        // Check if supplier exists
        $checkStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE id = :id");
        $checkStmt->bindParam(':id', $supplier_id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            // Delete supplier
            $deleteStmt = $pdo->prepare("DELETE FROM gasoline_suppliers WHERE id = :id");
            $deleteStmt->bindParam(':id', $supplier_id);
            
            if ($deleteStmt->execute()) {
                $swal_title = 'Success!';
                $swal_message = 'Gasoline supplier deleted successfully!';
                $swal_type = 'success';
                
            } else {
                $swal_title = 'Error!';
                $swal_message = 'Error deleting gasoline supplier. Please try again.';
                $swal_type = 'error';
            }
        } else {
            $swal_title = 'Error!';
            $swal_message = 'Gasoline supplier not found.';
            $swal_type = 'error';
        }
    } catch(PDOException $e) {
        $swal_title = 'Database Error!';
        $swal_message = 'Database error: ' . $e->getMessage();
        $swal_type = 'error';
    }
}

// Process update request
if (isset($_POST['update_supplier'])) {
    $supplier_id = $_POST['supplier_id'];
    $supplier_name = trim($_POST['supplier_name']);
    $contact_person = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Basic validation
    if (empty($supplier_name)) {
        $swal_title = 'Validation Error!';
        $swal_message = 'Supplier name is required.';
        $swal_type = 'error';
    } else {
        try {
            // Check if supplier already exists (excluding current supplier)
            $checkStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE supplier_name = :supplier_name AND id != :id");
            $checkStmt->bindParam(':supplier_name', $supplier_name);
            $checkStmt->bindParam(':id', $supplier_id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $swal_title = 'Error!';
                $swal_message = 'Gasoline supplier already exists. Please use a different name.';
                $swal_type = 'error';
            } else {
                // Update supplier
                $updateStmt = $pdo->prepare("UPDATE gasoline_suppliers SET supplier_name = :supplier_name, contact_person = :contact_person, phone = :phone, email = :email, address = :address, is_active = :is_active, updated_at = NOW() WHERE id = :id");
                $updateStmt->bindParam(':id', $supplier_id);
                $updateStmt->bindParam(':supplier_name', $supplier_name);
                $updateStmt->bindParam(':contact_person', $contact_person);
                $updateStmt->bindParam(':phone', $phone);
                $updateStmt->bindParam(':email', $email);
                $updateStmt->bindParam(':address', $address);
                $updateStmt->bindParam(':is_active', $is_active);
                
                if ($updateStmt->execute()) {
                    $swal_title = 'Success!';
                    $swal_message = 'Gasoline supplier updated successfully!';
                    $swal_type = 'success';
                    
                } else {
                    $swal_title = 'Error!';
                    $swal_message = 'Error updating gasoline supplier. Please try again.';
                    $swal_type = 'error';
                }
            }
        } catch(PDOException $e) {
            $swal_title = 'Database Error!';
            $swal_message = 'Database error: ' . $e->getMessage();
            $swal_type = 'error';
        }
    }
}

// Process form submission for adding new supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supplier_name']) && !isset($_POST['update_supplier']) && !isset($_POST['delete_supplier'])) {
    $supplier_name = trim($_POST['supplier_name']);
    $supplier_type = 'Fuel'; // Fixed value as requested
    $contact_person = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Basic validation
    if (empty($supplier_name)) {
        $swal_title = 'Validation Error!';
        $swal_message = 'Supplier name is required.';
        $swal_type = 'error';
    } else {
        try {
            // Check if supplier already exists
            $checkStmt = $pdo->prepare("SELECT id FROM gasoline_suppliers WHERE supplier_name = :supplier_name");
            $checkStmt->bindParam(':supplier_name', $supplier_name);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $swal_title = 'Error!';
                $swal_message = 'Gasoline supplier already exists. Please use a different name.';
                $swal_type = 'error';
            } else {
                // Insert new supplier
                $insertStmt = $pdo->prepare("INSERT INTO gasoline_suppliers (supplier_name, supplier_type, contact_person, phone, email, address, is_active) 
                                           VALUES (:supplier_name, :supplier_type, :contact_person, :phone, :email, :address, :is_active)");
                $insertStmt->bindParam(':supplier_name', $supplier_name);
                $insertStmt->bindParam(':supplier_type', $supplier_type);
                $insertStmt->bindParam(':contact_person', $contact_person);
                $insertStmt->bindParam(':phone', $phone);
                $insertStmt->bindParam(':email', $email);
                $insertStmt->bindParam(':address', $address);
                $insertStmt->bindParam(':is_active', $is_active);
                
                if ($insertStmt->execute()) {
                    $swal_title = 'Success!';
                    $swal_message = 'Gasoline supplier added successfully!';
                    $swal_type = 'success';
                    
                } else {
                    $swal_title = 'Error!';
                    $swal_message = 'Error adding gasoline supplier. Please try again.';
                    $swal_type = 'error';
                }
            }
        } catch(PDOException $e) {
            $swal_title = 'Database Error!';
            $swal_message = 'Database error: ' . $e->getMessage();
            $swal_type = 'error';
        }
    }
}

// Check for SweetAlert2 message in URL parameters (for redirects)
if (isset($_GET['swal_message']) && isset($_GET['swal_type']) && isset($_GET['swal_title'])) {
    $swal_title = urldecode($_GET['swal_title']);
    $swal_message = urldecode($_GET['swal_message']);
    $swal_type = $_GET['swal_type'];
}

// Get all gasoline suppliers from the database
try {
    $suppliersStmt = $pdo->prepare("SELECT * FROM gasoline_suppliers ORDER BY created_at DESC");
    $suppliersStmt->execute();
    $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $suppliers = [];
    if (empty($swal_message)) {
        $swal_title = 'Database Error!';
        $swal_message = 'Error fetching gasoline suppliers: ' . $e->getMessage();
        $swal_type = 'error';
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
        <title>Gasoline Suppliers - OCP Construction</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <!-- SweetAlert2 CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
                        <h1 class="mt-4">Gasoline Suppliers</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Gasoline Suppliers</li>
                        </ol>
                        
                        <!-- Display all gasoline suppliers in a table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-gas-pump me-1"></i>
                                    All Gasoline Suppliers
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                    <i class="fas fa-plus me-1"></i> Add Gasoline Supplier
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($suppliers)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>Supplier Name</th>
                                                <th>Supplier Type</th>
                                                <th>Contact Person</th>
                                                <th>Phone</th>
                                                <th>Email</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($suppliers as $supplier): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['supplier_type']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['contact_person']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $supplier['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $supplier['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewSupplierModal<?php echo $supplier['id']; ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editSupplierModal<?php echo $supplier['id']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-danger btn-sm delete-supplier-btn" 
                                                                    data-supplier-id="<?php echo $supplier['id']; ?>"
                                                                    data-supplier-name="<?php echo htmlspecialchars($supplier['supplier_name']); ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                    
                                                    <!-- View Modal -->
                                                    <div class="modal fade" id="viewSupplierModal<?php echo $supplier['id']; ?>" tabindex="-1" aria-labelledby="viewSupplierModalLabel<?php echo $supplier['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="viewSupplierModalLabel<?php echo $supplier['id']; ?>">Supplier Details</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <strong>Supplier Name:</strong> <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <strong>Supplier Type:</strong> <?php echo htmlspecialchars($supplier['supplier_type']); ?>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <strong>Contact Person:</strong> <?php echo htmlspecialchars($supplier['contact_person']); ?>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <strong>Phone:</strong> <?php echo htmlspecialchars($supplier['phone']); ?>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <strong>Email:</strong> <?php echo htmlspecialchars($supplier['email']); ?>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <strong>Address:</strong> <?php echo htmlspecialchars($supplier['address']); ?>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <strong>Status:</strong> 
                                                                        <span class="badge bg-<?php echo $supplier['is_active'] ? 'success' : 'secondary'; ?>">
                                                                            <?php echo $supplier['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                        </span>
                                                                    </div>
                                                                    <?php if (!empty($supplier['created_at'])): ?>
                                                                    <div class="mb-3">
                                                                        <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($supplier['created_at'])); ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($supplier['updated_at'])): ?>
                                                                    <div class="mb-3">
                                                                        <strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($supplier['updated_at'])); ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Edit Modal -->
                                                    <div class="modal fade" id="editSupplierModal<?php echo $supplier['id']; ?>" tabindex="-1" aria-labelledby="editSupplierModalLabel<?php echo $supplier['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="editSupplierModalLabel<?php echo $supplier['id']; ?>">Edit Supplier</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <form method="POST" action="" id="editForm<?php echo $supplier['id']; ?>">
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" id="edit_supplier_name_<?php echo $supplier['id']; ?>" name="supplier_name" 
                                                                                   value="<?php echo htmlspecialchars($supplier['supplier_name']); ?>" 
                                                                                   required maxlength="255" placeholder="Supplier Name">
                                                                            <label for="edit_supplier_name_<?php echo $supplier['id']; ?>">Supplier Name <span class="text-danger">*</span></label>
                                                                        </div>
                                                                        
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" id="edit_supplier_type_<?php echo $supplier['id']; ?>" name="supplier_type" 
                                                                                   value="Fuel" disabled readonly placeholder="Supplier Type">
                                                                            <label for="edit_supplier_type_<?php echo $supplier['id']; ?>">Supplier Type</label>
                                                                            <input type="hidden" name="supplier_type" value="Fuel">
                                                                        </div>
                                                                        
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" id="edit_contact_person_<?php echo $supplier['id']; ?>" name="contact_person" 
                                                                                   value="<?php echo htmlspecialchars($supplier['contact_person']); ?>" 
                                                                                   maxlength="255" placeholder="Contact Person">
                                                                            <label for="edit_contact_person_<?php echo $supplier['id']; ?>">Contact Person</label>
                                                                        </div>
                                                                        
                                                                        <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" id="edit_phone_<?php echo $supplier['id']; ?>" name="phone" 
                                                                                   value="<?php echo htmlspecialchars($supplier['phone']); ?>" 
                                                                                   maxlength="20" placeholder="Phone">
                                                                            <label for="edit_phone_<?php echo $supplier['id']; ?>">Phone</label>
                                                                        </div>
                                                                        
                                                                        <div class="form-floating mb-3">
                                                                            <input type="email" class="form-control" id="edit_email_<?php echo $supplier['id']; ?>" name="email" 
                                                                                   value="<?php echo htmlspecialchars($supplier['email']); ?>" 
                                                                                   maxlength="100" placeholder="Email">
                                                                            <label for="edit_email_<?php echo $supplier['id']; ?>">Email</label>
                                                                        </div>
                                                                        
                                                                        <div class="form-floating mb-3">
                                                                            <textarea class="form-control" id="edit_address_<?php echo $supplier['id']; ?>" name="address" 
                                                                                      style="height: 100px" placeholder="Address"><?php echo htmlspecialchars($supplier['address']); ?></textarea>
                                                                            <label for="edit_address_<?php echo $supplier['id']; ?>">Address</label>
                                                                        </div>
                                                                        
                                                                        <div class="form-check form-switch mb-3">
                                                                            <input class="form-check-input" type="checkbox" id="edit_is_active_<?php echo $supplier['id']; ?>" name="is_active" value="1" <?php echo $supplier['is_active'] ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="edit_is_active_<?php echo $supplier['id']; ?>">Active Supplier</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                        <button type="submit" name="update_supplier" class="btn btn-primary">Update Supplier</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No gasoline suppliers found. Add your first supplier using the button above.</p>
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
                        <h5 class="modal-title" id="addSupplierModalLabel">Add New Gasoline Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addSupplierForm">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="supplier_name" name="supplier_name" 
                                       value="" 
                                       required maxlength="255" placeholder="Supplier Name">
                                <label for="supplier_name">Supplier Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="supplier_type" name="supplier_type" 
                                       value="Fuel" disabled readonly placeholder="Supplier Type">
                                <label for="supplier_type">Supplier Type</label>
                                <input type="hidden" name="supplier_type" value="Fuel">
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                       value="" 
                                       maxlength="255" placeholder="Contact Person">
                                <label for="contact_person">Contact Person</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="" 
                                       maxlength="20" placeholder="Phone">
                                <label for="phone">Phone</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="" 
                                       maxlength="100" placeholder="Email">
                                <label for="email">Email</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="address" name="address" 
                                          style="height: 100px" placeholder="Address"></textarea>
                                <label for="address">Address</label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                                <label class="form-check-label" for="is_active">Active Supplier</label>
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

        <!-- Delete Confirmation Form (Hidden) -->
        <form id="deleteForm" method="POST" action="" style="display: none;">
            <input type="hidden" name="supplier_id" id="delete_supplier_id">
            <input type="hidden" name="delete_supplier" value="1">
        </form>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <!-- SweetAlert2 JS -->
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
                
                // Show SweetAlert2 messages if any
                <?php if (!empty($swal_message)): ?>
                    Swal.fire({
                        title: '<?php echo $swal_title; ?>',
                        text: '<?php echo $swal_message; ?>',
                        icon: '<?php echo $swal_type; ?>',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6'
                    });
                <?php endif; ?>
                
                // Show add modal if there was an error with form submission
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $swal_type === 'error' && !isset($_POST['update_supplier']) && !isset($_POST['delete_supplier'])): ?>
                    var addSupplierModal = new bootstrap.Modal(document.getElementById('addSupplierModal'));
                    addSupplierModal.show();
                <?php endif; ?>
                
                // Initialize delete functionality after page load
                initializeDeleteButtons();
            });
            
            // Clear form fields when modal is hidden
            document.getElementById('addSupplierModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('addSupplierForm').reset();
            });
            
            // Function to initialize delete buttons
            function initializeDeleteButtons() {
                document.querySelectorAll('.delete-supplier-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const supplierId = this.getAttribute('data-supplier-id');
                        const supplierName = this.getAttribute('data-supplier-name');
                        
                        showDeleteConfirmation(supplierId, supplierName);
                    });
                });
            }
            
            
            // Function to show delete confirmation with SweetAlert2
            function showDeleteConfirmation(supplierId, supplierName) {
                Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the supplier "${supplierName}". This action cannot be undone!`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        deleteSupplier(supplierId);
                    }
                });
            }
            
            // Function to delete supplier
            function deleteSupplier(supplierId) {
                // Set the supplier ID and submit the form
                document.getElementById('delete_supplier_id').value = supplierId;
                document.getElementById('deleteForm').submit();
            }
            
            // Form submission handling with SweetAlert2 confirmation for add/update
            document.getElementById('addSupplierForm').addEventListener('submit', function(e) {
                const supplierName = document.getElementById('supplier_name').value.trim();
                
                if (!supplierName) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Validation Error!',
                        text: 'Supplier name is required.',
                        icon: 'error',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }
                
                // You can add additional confirmation for add if needed
                // For now, just allow the form to submit normally
            });
            
            // Add similar handling for edit forms if needed
            document.querySelectorAll('form[id^="editForm"]').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const supplierName = this.querySelector('input[name="supplier_name"]').value.trim();
                    
                    if (!supplierName) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Validation Error!',
                            text: 'Supplier name is required.',
                            icon: 'error',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#3085d6'
                        });
                        return;
                    }
                    
                    // Optional: Add confirmation dialog for update
                    e.preventDefault();
                    Swal.fire({
                        title: 'Update Supplier?',
                        text: 'Are you sure you want to update this supplier?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, update it!',
                        cancelButtonText: 'Cancel',
                        customClass: {
                            confirmButton: 'btn btn-primary me-2',
                            cancelButton: 'btn btn-secondary'
                        },
                        buttonsStyling: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit();
                        }
                    });
                });
            });
        </script>
    </body>
</html>