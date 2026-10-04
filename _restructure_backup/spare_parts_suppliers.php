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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's a delete operation
    if (isset($_POST['delete_id'])) {
        $delete_id = $_POST['delete_id'];
        
        try {
            $deleteStmt = $pdo->prepare("DELETE FROM spare_parts_suppliers WHERE id = :id");
            $deleteStmt->bindParam(':id', $delete_id);
            
            if ($deleteStmt->execute()) {
                $swal_data = [
                    'title' => 'Success!',
                    'text' => 'Supplier deleted successfully!',
                    'icon' => 'success'
                ];
            } else {
                $swal_data = [
                    'title' => 'Error!',
                    'text' => 'Error deleting supplier. Please try again.',
                    'icon' => 'error'
                ];
            }
        } catch(PDOException $e) {
            $swal_data = [
                'title' => 'Database Error!',
                'text' => 'Error: ' . $e->getMessage(),
                'icon' => 'error'
            ];
        }
    } 
    // Check if it's an edit operation
    else if (isset($_POST['edit_id'])) {
        $edit_id = $_POST['edit_id'];
        $supplier_name = trim($_POST['supplier_name']);
        $contact_person = trim($_POST['contact_person']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        // Basic validation
        if (empty($supplier_name)) {
            $swal_data = [
                'title' => 'Validation Error!',
                'text' => 'Supplier name is required.',
                'icon' => 'error'
            ];
        } else {
            try {
                // Check if supplier name already exists (excluding current supplier)
                $checkStmt = $pdo->prepare("SELECT id FROM spare_parts_suppliers WHERE supplier_name = :supplier_name AND id != :id");
                $checkStmt->bindParam(':supplier_name', $supplier_name);
                $checkStmt->bindParam(':id', $edit_id);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $swal_data = [
                        'title' => 'Error!',
                        'text' => 'Supplier name already exists. Please use a different name.',
                        'icon' => 'error'
                    ];
                } else {
                    // Update supplier
                    $updateStmt = $pdo->prepare("UPDATE spare_parts_suppliers SET supplier_name = :supplier_name, contact_person = :contact_person, email = :email, phone = :phone, address = :address WHERE id = :id");
                    $updateStmt->bindParam(':supplier_name', $supplier_name);
                    $updateStmt->bindParam(':contact_person', $contact_person);
                    $updateStmt->bindParam(':email', $email);
                    $updateStmt->bindParam(':phone', $phone);
                    $updateStmt->bindParam(':address', $address);
                    $updateStmt->bindParam(':id', $edit_id);
                    
                    if ($updateStmt->execute()) {
                        $swal_data = [
                            'title' => 'Success!',
                            'text' => 'Supplier updated successfully!',
                            'icon' => 'success'
                        ];
                    } else {
                        $swal_data = [
                            'title' => 'Error!',
                            'text' => 'Error updating supplier. Please try again.',
                            'icon' => 'error'
                        ];
                    }
                }
            } catch(PDOException $e) {
                $swal_data = [
                    'title' => 'Database Error!',
                    'text' => 'Error: ' . $e->getMessage(),
                    'icon' => 'error'
                ];
            }
        }
    }
    // It's an add operation
    else {
        $supplier_name = trim($_POST['supplier_name']);
        $contact_person = trim($_POST['contact_person']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        // Basic validation
        if (empty($supplier_name)) {
            $swal_data = [
                'title' => 'Validation Error!',
                'text' => 'Supplier name is required.',
                'icon' => 'error'
            ];
        } else {
            try {
                // Check if supplier name already exists
                $checkStmt = $pdo->prepare("SELECT id FROM spare_parts_suppliers WHERE supplier_name = :supplier_name");
                $checkStmt->bindParam(':supplier_name', $supplier_name);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    $swal_data = [
                        'title' => 'Error!',
                        'text' => 'Supplier name already exists. Please use a different name.',
                        'icon' => 'error'
                    ];
                } else {
                    // Insert new supplier
                    $insertStmt = $pdo->prepare("INSERT INTO spare_parts_suppliers (supplier_name, contact_person, email, phone, address) VALUES (:supplier_name, :contact_person, :email, :phone, :address)");
                    $insertStmt->bindParam(':supplier_name', $supplier_name);
                    $insertStmt->bindParam(':contact_person', $contact_person);
                    $insertStmt->bindParam(':email', $email);
                    $insertStmt->bindParam(':phone', $phone);
                    $insertStmt->bindParam(':address', $address);
                    
                    if ($insertStmt->execute()) {
                        $swal_data = [
                            'title' => 'Success!',
                            'text' => 'Supplier added successfully!',
                            'icon' => 'success'
                        ];
                    } else {
                        $swal_data = [
                            'title' => 'Error!',
                            'text' => 'Error adding supplier. Please try again.',
                            'icon' => 'error'
                        ];
                    }
                }
            } catch(PDOException $e) {
                $swal_data = [
                    'title' => 'Database Error!',
                    'text' => 'Error: ' . $e->getMessage(),
                    'icon' => 'error'
                ];
            }
        }
    }
}

// Get all suppliers from the database
try {
    $suppliersStmt = $pdo->prepare("SELECT * FROM spare_parts_suppliers ORDER BY created_at DESC");
    $suppliersStmt->execute();
    $suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $suppliers = [];
    if (empty($swal_data)) {
        $swal_data = [
            'title' => 'Database Error!',
            'text' => 'Error fetching suppliers: ' . $e->getMessage(),
            'icon' => 'error'
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
        <title>Spare Parts Suppliers - OCP Construction</title>
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
                        <h1 class="mt-4">Spare Parts Suppliers</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Spare Parts Suppliers</li>
                        </ol>
                        
                        <!-- Display all suppliers in a table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    All Spare Parts Suppliers
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
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Created At</th>
                                                <th>Updated At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($suppliers as $supplier): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($supplier['id']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['contact_person']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['created_at']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['updated_at']); ?></td>
                                                <td>
                                                     <div class="btn-group" role="group">
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewSupplierModal" 
                                                                data-id="<?php echo $supplier['id']; ?>"
                                                                data-supplier_name="<?php echo htmlspecialchars($supplier['supplier_name']); ?>"
                                                                data-contact_person="<?php echo htmlspecialchars($supplier['contact_person']); ?>"
                                                                data-email="<?php echo htmlspecialchars($supplier['email']); ?>"
                                                                data-phone="<?php echo htmlspecialchars($supplier['phone']); ?>"
                                                                data-address="<?php echo htmlspecialchars($supplier['address']); ?>"
                                                                data-created_at="<?php echo htmlspecialchars($supplier['created_at']); ?>"
                                                                data-updated_at="<?php echo htmlspecialchars($supplier['updated_at']); ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editSupplierModal" 
                                                                data-id="<?php echo $supplier['id']; ?>"
                                                                data-supplier_name="<?php echo htmlspecialchars($supplier['supplier_name']); ?>"
                                                                data-contact_person="<?php echo htmlspecialchars($supplier['contact_person']); ?>"
                                                                data-email="<?php echo htmlspecialchars($supplier['email']); ?>"
                                                                data-phone="<?php echo htmlspecialchars($supplier['phone']); ?>"
                                                                data-address="<?php echo htmlspecialchars($supplier['address']); ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                                data-id="<?php echo $supplier['id']; ?>"
                                                                data-supplier_name="<?php echo htmlspecialchars($supplier['supplier_name']); ?>">
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
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="addSupplierForm">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="supplier_name" name="supplier_name" 
                                       value="<?php echo isset($_POST['supplier_name']) && !isset($_POST['edit_id']) ? htmlspecialchars($_POST['supplier_name']) : ''; ?>" 
                                       required maxlength="255" placeholder="Supplier Name">
                                <label for="supplier_name">Supplier Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                               value="<?php echo isset($_POST['contact_person']) && !isset($_POST['edit_id']) ? htmlspecialchars($_POST['contact_person']) : ''; ?>" 
                                               maxlength="100" placeholder="Contact Person">
                                        <label for="contact_person">Contact Person</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo isset($_POST['email']) && !isset($_POST['edit_id']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                               maxlength="100" placeholder="Email">
                                        <label for="email">Email</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo isset($_POST['phone']) && !isset($_POST['edit_id']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                                       maxlength="20" placeholder="Phone">
                                <label for="phone">Phone</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="address" name="address" 
                                          style="height: 100px" placeholder="Address"><?php echo isset($_POST['address']) && !isset($_POST['edit_id']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
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

        <!-- Edit Supplier Modal -->
        <div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editSupplierModalLabel">Edit Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="editSupplierForm">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_supplier_name" name="supplier_name" 
                                       required maxlength="255" placeholder="Supplier Name">
                                <label for="edit_supplier_name">Supplier Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="edit_contact_person" name="contact_person" 
                                               maxlength="100" placeholder="Contact Person">
                                        <label for="edit_contact_person">Contact Person</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="email" class="form-control" id="edit_email" name="email" 
                                               maxlength="100" placeholder="Email">
                                        <label for="edit_email">Email</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_phone" name="phone" 
                                       maxlength="20" placeholder="Phone">
                                <label for="edit_phone">Phone</label>
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

        <!-- View Supplier Modal -->
        <div class="modal fade" id="viewSupplierModal" tabindex="-1" aria-labelledby="viewSupplierModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewSupplierModalLabel">Supplier Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <strong>Supplier Name:</strong>
                                <p id="view_supplier_name" class="text-muted"></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Contact Person:</strong>
                                <p id="view_contact_person" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Email:</strong>
                                <p id="view_email" class="text-muted"></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Phone:</strong>
                                <p id="view_phone" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Address:</strong>
                                <p id="view_address" class="text-muted"></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Created At:</strong>
                                <p id="view_created_at" class="text-muted"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Updated At:</strong>
                                <p id="view_updated_at" class="text-muted"></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Form (hidden) -->
        <form method="POST" action="" id="deleteSupplierForm">
            <input type="hidden" name="delete_id" id="delete_id">
        </form>

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
                
                // Handle edit modal data
                const editModal = document.getElementById('editSupplierModal');
                if (editModal) {
                    editModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        const id = button.getAttribute('data-id');
                        const supplier_name = button.getAttribute('data-supplier_name');
                        const contact_person = button.getAttribute('data-contact_person');
                        const email = button.getAttribute('data-email');
                        const phone = button.getAttribute('data-phone');
                        const address = button.getAttribute('data-address');
                        
                        document.getElementById('edit_id').value = id;
                        document.getElementById('edit_supplier_name').value = supplier_name;
                        document.getElementById('edit_contact_person').value = contact_person || '';
                        document.getElementById('edit_email').value = email || '';
                        document.getElementById('edit_phone').value = phone || '';
                        document.getElementById('edit_address').value = address || '';
                    });
                }
                
                // Handle view modal data
                const viewModal = document.getElementById('viewSupplierModal');
                if (viewModal) {
                    viewModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        document.getElementById('view_supplier_name').textContent = button.getAttribute('data-supplier_name');
                        document.getElementById('view_contact_person').textContent = button.getAttribute('data-contact_person') || 'N/A';
                        document.getElementById('view_email').textContent = button.getAttribute('data-email') || 'N/A';
                        document.getElementById('view_phone').textContent = button.getAttribute('data-phone') || 'N/A';
                        document.getElementById('view_address').textContent = button.getAttribute('data-address') || 'N/A';
                        document.getElementById('view_created_at').textContent = button.getAttribute('data-created_at');
                        document.getElementById('view_updated_at').textContent = button.getAttribute('data-updated_at');
                    });
                }
                
                // Handle delete buttons
                const deleteButtons = document.querySelectorAll('.delete-btn');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const supplier_name = this.getAttribute('data-supplier_name');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the supplier "${supplier_name}". This action cannot be undone.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                document.getElementById('delete_id').value = id;
                                document.getElementById('deleteSupplierForm').submit();
                            }
                        });
                    });
                });
                
                // Show SweetAlert2 notifications based on PHP response
                <?php if (!empty($swal_data)): ?>
                    Swal.fire({
                        title: '<?php echo $swal_data['title']; ?>',
                        text: '<?php echo $swal_data['text']; ?>',
                        icon: '<?php echo $swal_data['icon']; ?>',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        <?php if ($swal_data['icon'] === 'success' && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                            // If success and it was a form submission, don't reopen the modal
                        <?php else: ?>
                            // If error, reopen the appropriate modal
                            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                                <?php if (isset($_POST['edit_id'])): ?>
                                    var editSupplierModal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
                                    editSupplierModal.show();
                                <?php elseif (!isset($_POST['delete_id'])): ?>
                                    var addSupplierModal = new bootstrap.Modal(document.getElementById('addSupplierModal'));
                                    addSupplierModal.show();
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    });
                <?php endif; ?>
                
                // Form validation for add supplier
                const addSupplierForm = document.getElementById('addSupplierForm');
                if (addSupplierForm) {
                    addSupplierForm.addEventListener('submit', function(e) {
                        const supplierName = document.getElementById('supplier_name').value.trim();
                        if (!supplierName) {
                            e.preventDefault();
                            Swal.fire({
                                title: 'Validation Error!',
                                text: 'Supplier name is required.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
                
                // Form validation for edit supplier
                const editSupplierForm = document.getElementById('editSupplierForm');
                if (editSupplierForm) {
                    editSupplierForm.addEventListener('submit', function(e) {
                        const supplierName = document.getElementById('edit_supplier_name').value.trim();
                        if (!supplierName) {
                            e.preventDefault();
                            Swal.fire({
                                title: 'Validation Error!',
                                text: 'Supplier name is required.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
            });
        </script>
    </body>
</html>